<?php

declare(strict_types=1);

namespace JustCarmen\Webtrees\Module\FancyTreeview;

use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\RelationshipService;
use JustCarmen\Webtrees\Module\FancyTreeview\FancyTreeviewModule;
use JustCarmen\Webtrees\Helpers\Functions;

require_once __DIR__ . '/autoload.php';

$module_service = Functions::getClass(ModuleService::class);
$relationship_service = Functions::getClass(RelationshipService::class);

return new FancyTreeviewModule($module_service, $relationship_service);
