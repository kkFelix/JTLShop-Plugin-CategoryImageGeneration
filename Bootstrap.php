<?php declare(strict_types=1);
/**
 * @package Plugin\things4it_category_image_generation
 * @author  Johannes Wendig
 */

namespace Plugin\t4it_category_image_generation;

use JTL\Alert\Alert;
use JTL\Events\Dispatcher;
use JTL\Events\Event;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Plugin\t4it_category_image_generation\adminmenu\RegenerateCategoryImageTab;
use Plugin\t4it_category_image_generation\src\Constants;
use Plugin\t4it_category_image_generation\src\cron\CategoryImageGenerationCronJob;
use Plugin\t4it_category_image_generation\src\db\dao\CategoryHelperDao;
use Plugin\t4it_category_image_generation\src\db\dao\SettingsDao;
use Plugin\t4it_category_image_generation\src\service\CategoryImageGenerationService;
use Plugin\t4it_category_image_generation\src\service\CategoryImageGenerationServiceInterface;
use Plugin\t4it_category_image_generation\src\service\placementStrategy\offset\ratio1to1\OffsetRatio1to1OneProductImagePlacementStrategy;
use Plugin\t4it_category_image_generation\src\service\placementStrategy\offset\ratio1to1\OffsetRatio1to1ThreeProductImagesPlacementStrategy;
use Plugin\t4it_category_image_generation\src\service\placementStrategy\offset\ratio1to1\OffsetRatio1to1TwoProductImagesPlacementStrategy;
use Plugin\t4it_category_image_generation\src\service\placementStrategy\offset\ratio4to3\OffsetRatio4to3OneProductImagePlacementStrategy;
use Plugin\t4it_category_image_generation\src\service\placementStrategy\offset\ratio4to3\OffsetRatio4to3ThreeProductImagesPlacementStrategy;
use Plugin\t4it_category_image_generation\src\service\placementStrategy\offset\ratio4to3\OffsetRatio4to3TwoProductImagesPlacementStrategy;
use Plugin\t4it_category_image_generation\src\service\placementStrategy\row\flat\RowFlatOneProductImagePlacementStrategy;
use Plugin\t4it_category_image_generation\src\service\placementStrategy\row\flat\RowFlatThreeProductImagesPlacementStrategy;
use Plugin\t4it_category_image_generation\src\service\placementStrategy\row\flat\RowFlatTwoProductImagesPlacementStrategy;
use Plugin\t4it_category_image_generation\src\service\placementStrategy\rowCropped\flat\RowCroppedFlatOneProductImagePlacementStrategy;
use Plugin\t4it_category_image_generation\src\service\placementStrategy\rowCropped\flat\RowCroppedFlatThreeProductImagesPlacementStrategy;
use Plugin\t4it_category_image_generation\src\service\placementStrategy\rowCropped\flat\RowCroppedFlatTwoProductImagesPlacementStrategy;
use Plugin\t4it_category_image_generation\src\utils\CategoryImageGenerator;

/**
 * Class Bootstrap
 * @package Plugin\things4it_category_image_generation
 */
class Bootstrap extends Bootstrapper
{

    /**
     * @inheritdoc
     */
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        $this->setupContainer();
        $this->addListeners($dispatcher);
    }

    /**
     * @inheritdoc
     */
    public function installed()
    {
        parent::installed();

        $this->addCron();
    }

    /**
     * @inheritdoc
     */
    public function disabled()
    {
        parent::disabled();

        CategoryHelperDao::removeGeneratedImages($this->getDB());
        CategoryImageGenerator::removeGeneratedImages();
    }

    /**
     * @inheritdoc
     */
    public function uninstalled(bool $deleteData = true)
    {
        parent::uninstalled($deleteData);

        $this->removeCron();

        if ($deleteData) {
            CategoryHelperDao::removeGeneratedImages($this->getDB());
            CategoryImageGenerator::removeGeneratedImages();
        }
    }

    /**
     * @inheritdoc
     */
    public function renderAdminMenuTab(string $tabName, int $menuID, JTLSmarty $smarty): string
    {
        $plugin = $this->getPlugin();
        $smarty->assign('menuID', $menuID);

        if ($tabName === 'Bild neu generieren (einzeln)') {
            return RegenerateCategoryImageTab::handleRequest($plugin, $this->getDB(), $smarty);
        }

        return parent::renderAdminMenuTab($tabName, $menuID, $smarty);
    }

    private function setupContainer(): void
    {
        $container = Shop::Container();
        $container->setFactory(CategoryImageGenerationServiceInterface::class, function ($container) {
            return new CategoryImageGenerationService($this->getDB(), $this->getPlugin());
        });

        $this->provideImagePlacementStrategiesOffsetRatio1to1($container);
        $this->provideImagePlacementStrategiesOffsetRatio4to3($container);
        $this->provideImagePlacementStrategiesRowFlat($container);
        $this->provideImagePlacementStrategiesRowCroppedFlat($container);
    }

    /**
     * @param Dispatcher $dispatcher
     */
    private function addListeners(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(Event::MAP_CRONJOB_TYPE, static function (array $args) {
            if ($args['type'] === Constants::CRON_JOB_CATEGORY_IMAGE_GENERATION) {
                $args['mapping'] = CategoryImageGenerationCronJob::class;
            }
        });

        $dispatcher->listen(Event::GET_AVAILABLE_CRONJOBS, static function (array $args) {
            $jobs = &$args['jobs'];
            if (is_array($jobs)) {
                array_push($jobs, Constants::CRON_JOB_CATEGORY_IMAGE_GENERATION);
            }
        });

        $dispatcher->listen('shop.hook.' . \HOOK_PLUGIN_SAVE_OPTIONS, function (array $args) {
            $hasError = $args['hasError'];
            $savedPlugin = $args['plugin'];

            if ($savedPlugin->getID() == $this->getPlugin()->getID() && $hasError === false) {
                Shop::Container()->getAlertService()->addAlert(Alert::TYPE_SUCCESS, __('admin.settings.post-saved.success'), 'infoSettingsChanged');
                SettingsDao::updateChangedFlag(true, $this->getDB());
            }
        });
    }

    private function addCron(): void
    {
        $job = new \stdClass();
        $job->name = 'Kategorie-Bild generierung';
        $job->jobType = Constants::CRON_JOB_CATEGORY_IMAGE_GENERATION;
        $job->frequency = 24;
        $job->startDate = 'NOW()';
        $job->startTime = '00:00:00';

        $this->getDB()->insert('tcron', $job);
    }

    private function removeCron(): void
    {
        $this->getDB()->delete('tcron', 'jobType', Constants::CRON_JOB_CATEGORY_IMAGE_GENERATION);
    }

    private function provideImagePlacementStrategiesOffsetRatio1to1(\JTL\Services\DefaultServicesInterface $container): void
    {
        $container->setFactory(OffsetRatio1to1OneProductImagePlacementStrategy::getCode(), function ($container) {
            return new OffsetRatio1to1OneProductImagePlacementStrategy();
        });

        $container->setFactory(OffsetRatio1to1TwoProductImagesPlacementStrategy::getCode(), function ($container) {
            return new OffsetRatio1to1TwoProductImagesPlacementStrategy();
        });

        $container->setFactory(OffsetRatio1to1ThreeProductImagesPlacementStrategy::getCode(), function ($container) {
            return new OffsetRatio1to1ThreeProductImagesPlacementStrategy();
        });
    }

    private function provideImagePlacementStrategiesOffsetRatio4to3(\JTL\Services\DefaultServicesInterface $container): void
    {
        $container->setFactory(OffsetRatio4to3OneProductImagePlacementStrategy::getCode(), function ($container) {
            return new OffsetRatio4to3OneProductImagePlacementStrategy();
        });

        $container->setFactory(OffsetRatio4to3TwoProductImagesPlacementStrategy::getCode(), function ($container) {
            return new OffsetRatio4to3TwoProductImagesPlacementStrategy();
        });

        $container->setFactory(OffsetRatio4to3ThreeProductImagesPlacementStrategy::getCode(), function ($container) {
            return new OffsetRatio4to3ThreeProductImagesPlacementStrategy();
        });
    }

    private function provideImagePlacementStrategiesRowFlat(\JTL\Services\DefaultServicesInterface $container): void
    {
        $container->setFactory(RowFlatOneProductImagePlacementStrategy::getCode(), function ($container) {
            return new RowFlatOneProductImagePlacementStrategy();
        });

        $container->setFactory(RowFlatTwoProductImagesPlacementStrategy::getCode(), function ($container) {
            return new RowFlatTwoProductImagesPlacementStrategy();
        });

        $container->setFactory(RowFlatThreeProductImagesPlacementStrategy::getCode(), function ($container) {
            return new RowFlatThreeProductImagesPlacementStrategy();
        });
    }

    private function provideImagePlacementStrategiesRowCroppedFlat(\JTL\Services\DefaultServicesInterface $container): void
    {
        $container->setFactory(RowCroppedFlatOneProductImagePlacementStrategy::getCode(), function ($container) {
            return new RowCroppedFlatOneProductImagePlacementStrategy();
        });

        $container->setFactory(RowCroppedFlatTwoProductImagesPlacementStrategy::getCode(), function ($container) {
            return new RowCroppedFlatTwoProductImagesPlacementStrategy();
        });

        $container->setFactory(RowCroppedFlatThreeProductImagesPlacementStrategy::getCode(), function ($container) {
            return new RowCroppedFlatThreeProductImagesPlacementStrategy();
        });
    }

}
