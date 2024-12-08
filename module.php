<?php

declare(strict_types=1);

namespace JustCarmen\Webtrees\Module\FancyTreeview;

use Fisharebest\Webtrees\Registry;
use JustCarmen\Webtrees\Module\FancyTreeview\FancyTreeviewModule;

require __DIR__ . '/FancyTreeviewModule.php';

return Registry::container()->get(FancyTreeviewModule::class);
