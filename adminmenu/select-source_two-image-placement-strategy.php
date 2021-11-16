<?php declare(strict_types=1);


use Plugin\t4it_category_image_generation\src\service\placementStrategy\ThreeProductImagePlacementStrategyInterface;
use Plugin\t4it_category_image_generation\src\utils\PhpUtils;

$implementsTwoProductImagePlacementStrategy = PhpUtils::getImplementations(ThreeProductImagePlacementStrategyInterface::class);

$sort = 1;
foreach ($implementsTwoProductImagePlacementStrategy as $strategy){
    $option = new stdClass();
    $option->cWert = $strategy::getCode();
    $option->cName = $strategy::getName();
    $option->nSort = $sort++;
    $options[] = $option;
}

return $options;
