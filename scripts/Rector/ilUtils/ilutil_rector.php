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

use ILIAS\scripts\Rector\ilUtils\ReplaceUtilSendMessageRector;
use Rector\Core\Configuration\Option;
use ILIAS\scripts\Rector\DIC\DICMemberResolver;
use ILIAS\scripts\Rector\DIC\DICDependencyManipulator;
use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\PhpVersion;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->disableParallel();
    $rectorConfig->parameters()->set(Option::SKIP, [
        // there a several classes which make Rector break (multiple classes
        // in one file, wrong declarations in inheritance, ...)
        "components/ILIAS/LTIConsumer",
        "components/ILIAS/LTIProvider",
        "components/ILIAS/SOAPAuth/include"
    ]);
    $rectorConfig->parameters()->set(Option::DEBUG, false);

    $rectorConfig->phpVersion(PhpVersion::PHP_80);

    $rectorConfig->services()->set(DICMemberResolver::class)->autowire();
    $rectorConfig->services()->set(DICDependencyManipulator::class)->autowire();
    $rectorConfig->services()->set(ReplaceUtilSendMessageRector::class);
};
