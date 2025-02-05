<?php

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

declare(strict_types=1);

namespace ILIAS\ResourceStorage\Consumer;

use ILIAS\ResourceStorage\Flavour\Flavour;
use ILIAS\ResourceStorage\Revision\Revision;

/**
 * @author Fabian Schmid <fabian@sr.solutions>
 */
interface SrcBuilder
{
    /**
     * @param string|null $filename
     * @throw \RuntimeException if signing is not possible or failed, but was requested with $signed = true
     */
    public function getRevisionURL(
        Revision $revision,
        bool $signed = true,
        float $valid_for_at_least_minutes = 60.0,
        string $filename = null
    ): string;

    /**
     * @throw \RuntimeException if signing is not possible or failed, but was requested with $signed = true
     */
    public function getFlavourURLs(Flavour $flavour, bool $signed = true): \Generator;
}
