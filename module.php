<?php

declare(strict_types=1);

namespace JustCarmen\Webtrees\Module\FancyTreeview;

use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\RelationshipService;
use JustCarmen\Webtrees\Module\FancyTreeview\FancyTreeviewModule;

require __DIR__ . '/FancyTreeviewModule.php';
require __DIR__ . '/Service/CountryService.php';

$module_service = FancyTreeviewModule::getClass(ModuleService::class);
$relationship_service = FancyTreeviewModule::getClass(RelationshipService::class);

return new FancyTreeviewModule($module_service, $relationship_service);
