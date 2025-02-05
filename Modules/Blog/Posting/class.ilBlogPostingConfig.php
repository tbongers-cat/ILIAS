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

/**
 * Blog posting page configuration
 * @author Alexander Killing <killing@leifos.de>
 */
class ilBlogPostingConfig extends ilPageConfig
{
    public function init(): void
    {
        global $DIC;

        $req = $DIC->blog()->internal()->gui()->standardRequest();

        $this->setEnablePCType("Map", true);
        $this->setEnableInternalLinks($req->getRefId() > 0); // #15668
        $this->setPreventHTMLUnmasking(false);
        $this->setEnableActivation(true);

        $blga_set = new ilSetting("blga");
        $this->setPreventHTMLUnmasking(!$blga_set->get("mask", "0"));
    }
}
