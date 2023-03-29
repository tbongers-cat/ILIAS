<?php

declare(strict_types=1);

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

namespace ILIAS\UI\Component\Item;

use ILIAS\UI\Component\Symbol\Icon\Icon;
use ILIAS\UI\Component\Image\Image;
use ILIAS\UI\Component\Dropdown\Standard as DropdownStandard;

/**
 * Interface Repository Item
 * @package ILIAS\UI\Component\Panel\Listing
 */
interface Repository extends Item
{
    /**
     * Create a new appointment item with a set of actions to perform on it.
     */
    public function withActions(DropdownStandard $actions): Repository;

    /**
     * Get the actions of the item.
     */
    public function getActions(): ?DropdownStandard;
}
