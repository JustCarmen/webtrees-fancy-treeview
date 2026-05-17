<?php

declare(strict_types=1);

namespace JustCarmen\Webtrees\Module\FancyTreeview;

use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\RelationshipService;
use JustCarmen\Webtrees\Module\FancyTreeview\FancyTreeviewModule;
use JustCarmen\Webtrees\Helpers\Functions;

//Autoload the latest version of the common code library, which is shared between custom modules by JustCarmen.
require_once __DIR__ . '/vendor/justcarmen/jc-common-code/autoload.php';

require __DIR__ . '/FancyTreeviewModule.php';

$module_service = Functions::getClass(ModuleService::class);
$relationship_service = Functions::getClass(RelationshipService::class);

return new FancyTreeviewModule($module_service, $relationship_service);
