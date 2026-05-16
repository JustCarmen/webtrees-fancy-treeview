<?php

declare(strict_types=1);

namespace JustCarmen\Webtrees\Module\FancyTreeview;

use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\RelationshipService;
use JustCarmen\Webtrees\Module\FancyTreeview\FancyTreeviewModule;

require __DIR__ . '/FancyTreeviewModule.php';

//Autoload the latest version of the common code library, which is shared between webtrees custom modules
require_once __DIR__ . '/vendor/justcarmen/jc-common-code/autoload.php';

$module_service = FancyTreeviewModule::getClass(ModuleService::class);
$relationship_service = FancyTreeviewModule::getClass(RelationshipService::class);

return new FancyTreeviewModule($module_service, $relationship_service);
