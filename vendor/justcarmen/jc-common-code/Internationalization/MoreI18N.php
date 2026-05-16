<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2025 webtrees development team
 *                    <http://webtrees.net>
 *
 * vesta-webtrees-2-custom-modules/vesta_common
 * Copyright (C) 2019 – 2024 Richard Cissée
 *                    <https://github.com/vesta-webtrees-2-custom-modules/vesta_common>
 *
 * Copyright (C) 2025 Markus Hemprich
 *                    <http://www.familienforschung-hemprich.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 *
 * Reuse of webtrees translations for custom modules
 *
 */

declare(strict_types=1);

namespace JustCarmen\Webtrees\Internationalization;

use Fisharebest\Webtrees\I18N;

class MoreI18N {

    //functionally same as I18N::translate,
    //different name prevents gettext from picking this up
    //(intention: use where already expected to be translated via main webtrees)
    public static function xlate(string $message, ...$args): string {
        return I18N::translate($message, ...$args);
    }

    //functionally same as I18N::translateContext,
    //different name prevents gettext from picking this up
    //(intention: use where already expected to be translated via main webtrees)
    public static function xlateContext(string $context, string $message, ...$args): string {
        return I18N::translateContext($context, $message, ...$args);
    }

    //functionally same as I18N::plural,
    //different name prevents gettext from picking this up
    //(intention: use where already expected to be translated via main webtrees)
    public static function plural(string $singular, string $plural, int $count, ...$args): string {
        return I18N::plural($singular, $plural, $count, ...$args);
    }

    /**
     * Translate a number into the local representation.
     * e.g. 12345.67 becomes
     * en: 12,345.67
     * fr: 12 345,67
     * de: 12.345,67
     *
     * @param float $n
     * @param int   $precision
     *
     * @return string
     */
    public static function number(float $n, int $precision = 0): string
    {
        return I18N::number($n, $precision);
    }
}
