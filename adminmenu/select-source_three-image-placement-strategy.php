<?php declare(strict_types=1);


// TODO: load the strategies dynamically - just check for classes which implements the interface

use Plugin\t4it_category_image_generation\src\service\placementStrategy\flippedOffset\FlippedOffsetTwoProductImagesPlacementStrategy;
use Plugin\t4it_category_image_generation\src\service\placementStrategy\offset\OffsetTwoProductImagesPlacementStrategy;
use Plugin\t4it_category_image_generation\src\service\placementStrategy\row\HorizontalTwoProductImagesPlacementStrategy;

$option = new stdClass();
$option->cWert = OffsetTwoProductImagesPlacementStrategy::getCode();
$option->cName = OffsetTwoProductImagesPlacementStrategy::getName();
$option->nSort = 1;
$options[] = $option;

$option = new stdClass();
$option->cWert = FlippedOffsetTwoProductImagesPlacementStrategy::getCode();
$option->cName = FlippedOffsetTwoProductImagesPlacementStrategy::getName();
$option->nSort = 2;
$options[] = $option;

$option = new stdClass();
$option->cWert = HorizontalTwoProductImagesPlacementStrategy::getCode();
$option->cName = HorizontalTwoProductImagesPlacementStrategy::getName();
$option->nSort = 3;
$options[] = $option;

return $options;
