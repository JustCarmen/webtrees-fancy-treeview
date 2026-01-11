<?php

declare(strict_types=1);

namespace JustCarmen\Webtrees\Module\FancyTreeview;

use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\RelationshipService;
use Fisharebest\Webtrees\Statistics\Service\CountryService;
use JustCarmen\Webtrees\Module\FancyTreeview\FancyTreeviewModule;

require __DIR__ . '/FancyTreeviewModule.php';

$country_service = FancyTreeviewModule::getClass(CountryService::class);
$module_service = FancyTreeviewModule::getClass(ModuleService::class);
$relationship_service = FancyTreeviewModule::getClass(RelationshipService::class);

return new FancyTreeviewModule($country_service, $module_service, $relationship_service);
