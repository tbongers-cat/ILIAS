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

use ILIAS\Setup;
use ILIAS\DI;

class ilDatabaseResetStepsObjective extends Setup\Objective\AdminConfirmedObjective
{
    public function __construct()
    {
        parent::__construct(
            "This will reset failing steps in the setup progress. However -\n" .
            "those steps failed for a reason!\n" .
            "A step may fail due to a programming error, or, more likely, to some\n" .
            "circumstances in your environment, e.g. inconsistent data in the DB,\n" .
            "missing or unexpected files, etc.. Please double-check for the cause\n" .
            "and only continue if you are certain about and fine with the consequences.\n" .
            "Continue?"
        );
    }

    public function getHash(): string
    {
        return hash("sha256", self::class);
    }

    public function getLabel(): string
    {
        return "failing update steps are removed.";
    }

    public function isNotable(): bool
    {
        return true;
    }

    /**
     * @return \ilDatabaseInitializedObjective[]|\ILIAS\Setup\Objective\ClientIdReadObjective[]|\ilIniFilesPopulatedObjective[]
     */
    public function getPreconditions(Setup\Environment $environment): array
    {
        $preconditions = [];
        $preconditions[] = new Setup\Objective\ClientIdReadObjective();
        $preconditions[] = new ilIniFilesPopulatedObjective();
        $preconditions[] = new ilDatabaseInitializedObjective();

        return $preconditions;
    }

    public function achieve(Setup\Environment $environment): Setup\Environment
    {
        $environment = parent::achieve($environment);
        $db = $environment->getResource(Setup\Environment::RESOURCE_DATABASE);
        $db->manipulate('DELETE FROM il_db_steps WHERE finished IS NULL');
        return $environment;
    }

    /**
     * @inheritDoc
     */
    public function isApplicable(Setup\Environment $environment): bool
    {
        $db = $environment->getResource(Setup\Environment::RESOURCE_DATABASE);
        $query = 'SELECT class FROM il_db_steps WHERE finished IS NULL';
        return $db->numRows($db->query($query)) > 0;
    }
}
