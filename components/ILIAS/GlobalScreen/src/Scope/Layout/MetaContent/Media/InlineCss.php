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

namespace ILIAS\GlobalScreen\Scope\Layout\MetaContent\Media;

/**
 * @author Fabian Schmid <fabian@sr.solutions>
 */
class InlineCss extends AbstractMedia
{
    public const MEDIA_SCREEN = "screen";


    public function __construct(string $content, string $version, private string $media = self::MEDIA_SCREEN)
    {
        parent::__construct($content, $version);
    }

    public function getMedia(): string
    {
        return $this->media;
    }
}
