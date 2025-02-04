<?php

namespace Plugin\t4it_category_image_generation\src\db\dao;

use JTL\DB\DbInterface;
use JTL\DB\ReturnType;
use Plugin\t4it_category_image_generation\src\db\entity\Category;
use Plugin\t4it_category_image_generation\src\db\entity\Image;
use Plugin\t4it_category_image_generation\src\utils\CategoryImageGenerator;

class CategoryHelperDao
{
    /**
     * @param DbInterface $db
     * @return Category[]
     */
    public static function findAllWithoutImage(DbInterface $db): array
    {
        $resultObjects = $db->queryPrepared('
            SELECT
	            k.kKategorie
            FROM
	            tkategorie k
            LEFT OUTER JOIN tkategoriepict kp
	            ON kp.kKategorie = k.kKategorie
	        WHERE
	            kp.kKategoriePict IS NULL',
            [], ReturnType::ARRAY_OF_OBJECTS);

        $categoriesWithoutImage = array();
        foreach ($resultObjects as $resultObject) {
            $categoryWithoutImage = new Category();
            $categoryWithoutImage->setKKategorie($resultObject->kKategorie);

            array_push($categoriesWithoutImage, $categoryWithoutImage);
        }

        return $categoriesWithoutImage;
    }

    /**
     * @param int $categoryId
     * @param int $maxArticleImages
     * @param DbInterface $db
     * @return Image[]
     */
    public static function findRandomArticleImages(int $categoryId, int $maxArticleImages, DbInterface $db): array
    {
        $imagesResult = self::fetchRandomArticleImagesForCategories([$categoryId], $maxArticleImages, $db);
        if (\sizeof($imagesResult) < $maxArticleImages) {
            $categoryPathsResult = $db->queryPrepared('
            SELECT
                k1.kKategorie k1,
                k2.kKategorie k2,
                k3.kKategorie k3,
                k4.kKategorie k4,
                k5.kKategorie k5
            FROM tkategorie k1
            LEFT JOIN tkategorie k2 ON k2.kOberKategorie = k1.kKategorie
            LEFT JOIN tkategorie k3 ON k3.kOberKategorie = k2.kKategorie	
            LEFT JOIN tkategorie k4 ON k4.kOberKategorie = k3.kKategorie		
            LEFT JOIN tkategorie k5 ON k5.kOberKategorie = k4.kKategorie			
            WHERE
                k1.kKategorie = :categoryId',
                ['categoryId' => $categoryId],
                ReturnType::ARRAY_OF_OBJECTS);

            $relevantCategoryIds = [];
            foreach ($categoryPathsResult as $categoryPathResult) {
                array_push($relevantCategoryIds, $categoryPathResult->k1);
                array_push($relevantCategoryIds, $categoryPathResult->k2);
                array_push($relevantCategoryIds, $categoryPathResult->k3);
                array_push($relevantCategoryIds, $categoryPathResult->k4);
                array_push($relevantCategoryIds, $categoryPathResult->k5);
            }
            $relevantCategoryIds = array_filter($relevantCategoryIds);
            $relevantCategoryIds = array_unique($relevantCategoryIds);

            $imagesResult = self::fetchRandomArticleImagesForCategories($relevantCategoryIds, $maxArticleImages, $db);
        }

        $images = array();
        foreach ($imagesResult as $imageResult) {
            $image = new Image();
            $image->setCPath($imageResult->cPfad);

            array_push($images, $image);
        }

        return $images;
    }

    /**
     * @param int $categoryId
     * @param string $imageName
     * @param DbInterface $db
     */
    public static function saveCategoryImage(int $categoryId, string $imageName, DbInterface $db)
    {
        $db->insert('tkategoriepict', (object)[
            'cPfad' => $imageName,
            'kKategorie' => $categoryId
        ]);
    }

    public static function removeGeneratedImages(DbInterface $db): void
    {
        $categoryIdsObjects = $db->queryPrepared("
                    SELECT 
                           kp.kKategorie 
                    FROM tkategoriepict kp 
                    WHERE kp.cPfad LIKE :pathPrefix",
            ['pathPrefix' => CategoryImageGenerator::getImageNamePrefix() . '%'],
            ReturnType::ARRAY_OF_OBJECTS);

        $categoryIds = \array_map(function ($o) {
            return $o->kKategorie;
        }, $categoryIdsObjects);

        foreach ($categoryIds as $categoryId) {
            $db->delete('tkategoriepict', 'kKategorie', $categoryId);
        }
    }

    public static function removeGeneratedImage(int $categoryId, DbInterface $db): void
    {
        $categoryIdsObjects = $db->queryPrepared("
                    SELECT 
                           kp.kKategorie 
                    FROM tkategoriepict kp 
                    WHERE 
                          kp.kKategorie = :categoryId
                          AND kp.cPfad LIKE :pathPrefix",
            [
                'pathPrefix' => CategoryImageGenerator::getImageNamePrefix() . '%',
                'categoryId' => $categoryId
            ],
            ReturnType::ARRAY_OF_OBJECTS);

        $categoryIds = \array_map(function ($o) {
            return $o->kKategorie;
        }, $categoryIdsObjects);

        foreach ($categoryIds as $categoryId) {
            $db->delete('tkategoriepict', 'kKategorie', $categoryId);
        }
    }

    /**
     * @param array $relevantCategoryIds
     * @param int $maxArticleImages
     * @param DbInterface $db
     * @return array|int|object
     */
    private static function fetchRandomArticleImagesForCategories(array $relevantCategoryIds, int $maxArticleImages, DbInterface $db)
    {
        $imagesResult = $db->query('
                SELECT
                     DISTINCT b.cPfad
                FROM tartikel a
                JOIN tartikelpict ap ON 
                    ap.kArtikel = a.kArtikel
                    AND ap.nNr = 1
                JOIN tbild b ON 
                    b.kBild = ap.kBild
                    AND LOWER(b.cPfad) REGEXP \'^.*(.png|.jpeg|.jpg|.gif)$\'
                JOIN tkategorieartikel ka ON ka.kArtikel = a.kArtikel
                WHERE
                    ka.kKategorie IN (' . implode(',', $relevantCategoryIds) . ')
                ORDER BY RAND()
                LIMIT ' . $maxArticleImages . '
        ',
            ReturnType::ARRAY_OF_OBJECTS);
        return $imagesResult;
    }


}
