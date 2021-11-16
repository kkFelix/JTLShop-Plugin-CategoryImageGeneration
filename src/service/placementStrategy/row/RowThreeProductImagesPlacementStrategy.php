<?php


namespace Plugin\t4it_category_image_generation\src\service\placementStrategy\row;


use Plugin\t4it_category_image_generation\src\Constants;
use Plugin\t4it_category_image_generation\src\service\placementStrategy\ThreeProductImagePlacementStrategyInterface;
use Plugin\t4it_category_image_generation\src\utils\ImageUtils;

class RowThreeProductImagesPlacementStrategy implements ThreeProductImagePlacementStrategyInterface
{
    private static int $WIDTH = 1024;
    private static int $HEIGHT = 512;

    public static function getName(): string
    {
        return __("admin.settings.image-strategy.row");
    }

    public static function getCode(): string
    {
        return Constants::IMAGE_GENERATION_STRATEGY_PREFIX . "row-three";
    }

    public function placeProductImages($productImage1, $productImage2, $productImage3)
    {
        $categoryImage = ImageUtils::createTransparentImage(self::$WIDTH, self::$HEIGHT);

        $productImage1 = ImageUtils::resizeImageToMaxWidthHeight($productImage1, 340, 340, 1);
        $productImage2 = ImageUtils::resizeImageToMaxWidthHeight($productImage2, 340, 340, 1);
        $productImage3 = ImageUtils::resizeImageToMaxWidthHeight($productImage3, 340, 340, 1);

        $offsetY = 86;

        // TODO: current impl is for 4:2 ratio add own strategies for other ratios
//        $offsetY = RowUtils::calculateOffsetYByRatio($imageRatio);

        \imagecopyresized($categoryImage, $productImage1, 0, $offsetY, 0, 0, 340, 340, imagesx($productImage1), imagesy($productImage1));
        \imagecopyresized($categoryImage, $productImage2, 340 + 1, $offsetY, 0, 0, 340, 340, imagesx($productImage2), imagesy($productImage2));
        \imagecopyresized($categoryImage, $productImage3, 340 + 1 + 340 + 1, $offsetY, 0, 0, 340, 340, imagesx($productImage3), imagesy($productImage3));

        return $categoryImage;
    }
}