<?php

declare(strict_types=1);

require_once(__DIR__ . "/../../../../libs/composer/vendor/autoload.php");
require_once(__DIR__ . "/../../Base.php");

use ILIAS\UI\Component\Item as I;

/**
 * Test on repository item implementation.
 */
class ItemRepositoryTest extends ILIAS_UI_TestBase
{
    public function test_get_title()
    {
        $f = new \ILIAS\UI\Implementation\Component\Item\Factory();
        $demo = $f->repository("Demo Implementation!");

        $this->assertEquals("Demo Implementation!", $demo->getTitle());
    }
}
