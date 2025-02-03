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

use ILIAS\BackgroundTasks\Implementation\Values\AggregationValues\ListValue;
use ILIAS\BackgroundTasks\Implementation\Values\ScalarValues\IntegerValue;
use ILIAS\BackgroundTasks\Implementation\Values\ScalarValues\StringValue;
use ILIAS\BackgroundTasks\Implementation\Values\ScalarValues\ScalarValue;
use ILIAS\BackgroundTasks\Types\ListType;
use ILIAS\BackgroundTasks\Types\SingleType;
use PHPUnit\Framework\TestCase;

require_once("vendor/composer/vendor/autoload.php");

/**
 * Class BackgroundTaskTest
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState    disabled
 * @backupGlobals          disabled
 * @backupStaticAttributes disabled
 *
 * @author Oskar Truffer <ot@studer-raimann.ch>
 */
class ValueTest extends TestCase
{
    public function testIntegerValue()
    {
        $integer = new IntegerValue();
        $integer->setValue(3);
        $integer2 = new IntegerValue(3);
        $integer2->setValue(3);
        $integer3 = new IntegerValue(4);
        $integer3->setValue(4);

        $this->assertEquals($integer->getValue(), 3);
        $this->assertTrue($integer->equals($integer2));
        $this->assertEquals($integer->getHash(), $integer2->getHash());
        $this->assertNotEquals($integer->getHash(), $integer3->getHash());
        $integer3->unserialize($integer->serialize());
        $this->assertTrue($integer->equals($integer3));
        $this->assertTrue($integer->getType()->equals(new SingleType(IntegerValue::class)));
    }

    public function testListValue()
    {
        $list = new ListValue();
        $list->setValue([1, 2, 3]);

        $this->assertTrue($list->getType()->equals(new ListType(IntegerValue::class)));

        $list2 = new ListValue();
        $integer1 = new IntegerValue();
        $integer1->setValue(1);
        $string = new StringValue();
        $string->setValue("1");
        $list2->setValue([$integer1, $string]);

        $this->assertTrue($list2->getType()->equals(new ListType(ScalarValue::class)));
    }
}
