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
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\Refinery\Transformation;

class ilGlobalScreenSetupAgent implements Setup\Agent
{
    use Setup\Agent\HasNoNamedObjective;

    protected Refinery $refinery;

    public function __construct(Refinery $refinery)
    {
        $this->refinery = $refinery;
    }

    /**
     * @inheritdocs
     */
    public function hasConfig(): bool
    {
        return false;
    }

    /**
     * @inheritdocs
     */
    public function getArrayToConfigTransformation(): Transformation
    {
        throw new LogicException(self::class . " has no Config.");
    }

    /**
     * @inheritdocs
     */
    public function getInstallObjective(Setup\Config $config = null): Setup\Objective
    {
        return new Setup\Objective\NullObjective();
    }

    /**
     * @inheritdocs
     */
    public function getUpdateObjective(Setup\Config $config = null): Setup\Objective
    {
        return new Setup\Objective\NullObjective();
    }

    /**
     * @inheritdocs
     */
    public function getBuildObjective(): Setup\Objective
    {
        return new ilGlobalScreenBuildProviderMapObjective();
    }

    /**
     * @inheritdoc
     */
    public function getStatusObjective(Setup\Metrics\Storage $storage): Setup\Objective
    {
        return new Setup\Objective\NullObjective();
    }

    /**
     * @inheritDoc
     */
    public function getMigrations(): array
    {
        return [];
    }
}
