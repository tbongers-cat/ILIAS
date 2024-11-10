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

namespace ILIAS\COPage\IntLink\Setup;

use ILIAS\Setup;
use ILIAS\Setup\Objective;
use ILIAS\Setup\Metrics;

/**
 * @author Alexander Killing <killing@leifos.de>
 */
class Agent extends Setup\Agent\NullAgent
{
    public function getUpdateObjective(Setup\Config $config = null): Setup\Objective
    {
<<<<<<< HEAD:components/ILIAS/COPage/IntLink/Setup/class.Agent.php
        return new \ilDatabaseUpdateStepsExecutedObjective(new LinkDBUpdateSteps());
    }

    public function getStatusObjective(Metrics\Storage $storage): Objective
    {
        return new \ilDatabaseUpdateStepsMetricsCollectedObjective($storage, new LinkDBUpdateSteps());
=======
        return new Setup\ObjectiveCollection(
            'Booking Manager Update',
            true,
            new \ilDatabaseUpdateStepsExecutedObjective(new ilBookingManagerDBUpdateSteps()),
            new \ilDatabaseUpdateStepsExecutedObjective(new ilBookingManager8HotfixDBUpdateSteps())
        );
>>>>>>> 6760c1bc365 (42614: BookingPool: Cloning of schedules does not work correctly, ID 1 seems to be stored for the booking objectt):Modules/BookingManager/classes/Setup/class.Agent.php
    }
}
