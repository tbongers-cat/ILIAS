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

namespace ILIAS\GlobalScreen\Scope\Layout;

use PHPUnit\Framework\TestCase;
use ILIAS\GlobalScreen\Scope\Layout\MetaContent\MetaContent;
use ILIAS\GlobalScreen\Scope\Layout\MetaContent\Media\Css;
use ILIAS\GlobalScreen\Scope\Layout\MetaContent\Media\InlineCss;
use ILIAS\GlobalScreen\Scope\Layout\MetaContent\Media\Js;

require_once('./vendor/composer/vendor/autoload.php');

/**
 * Class MediaTest
 *
 * @author                 Fabian Schmid <fs@studer-raimann.ch>
 */
class MediaTest extends TestCase
{
    public string $version;
    public MetaContent $meta_content;

    protected function setUp(): void
    {
        parent::setUp();
        $this->version = '1.2.3.4.5.6.7.8.9';
        $this->meta_content = new MetaContent(
            $this->version,
            true,
            false,
            true,
            true
        );
    }

    public function testAddCssFile(): void
    {
        $path = '/path/to/file.css';
        $this->meta_content->addCss($path);
        $collection = $this->meta_content->getCss();

        $iterator_to_array = iterator_to_array($collection->getItems());
        $first_item = array_shift($iterator_to_array);
        $this->assertInstanceOf(Css::class, $first_item);
        $this->assertEquals($path . '?version=' . $this->version, $first_item->getContent());
        $this->assertEquals(MetaContent::MEDIA_SCREEN, $first_item->getMedia());
    }

    public function testAddCssFileWithQuery(): void
    {
        $path = '/path/to/file.css?my=query';
        $this->meta_content->addCss($path);
        $collection = $this->meta_content->getCss();

        $iterator_to_array = iterator_to_array($collection->getItems());
        $first_item = array_shift($iterator_to_array);
        $this->assertInstanceOf(Css::class, $first_item);
        $this->assertEquals($path . '&version=' . $this->version, $first_item->getContent());
        $this->assertEquals(MetaContent::MEDIA_SCREEN, $first_item->getMedia());
    }

    public function testAddInlineCss(): void
    {
        $css = 'body {background-color:red;}';
        $this->meta_content->addInlineCss($css);
        $collection = $this->meta_content->getInlineCss();

        $first_item = iterator_to_array($collection->getItems())[0];
        $this->assertInstanceOf(InlineCss::class, $first_item);
        $this->assertEquals($css, $first_item->getContent());
        $this->assertEquals(MetaContent::MEDIA_SCREEN, $first_item->getMedia());
    }

    public function testAddJsFile(): void
    {
        $path = '/path/to/file.js';
        $this->meta_content->addJs($path);
        $collection = $this->meta_content->getJs();

        $iterator_to_array = iterator_to_array($collection->getItems());
        $first_item = $iterator_to_array[$path];
        $this->assertInstanceOf(Js::class, $first_item);
        $this->assertEquals($path . '?version=' . $this->version, $first_item->getContent());
        $this->assertEquals(2, $first_item->getBatch());
    }

    public function testAddJsFileWithQuery(): void
    {
        $path = '/path/to/file.js';
        $path_with_query = $path . '?my=query';
        $this->meta_content->addJs($path_with_query);
        $collection = $this->meta_content->getJs();

        $first_item = iterator_to_array($collection->getItems())[$path];
        $this->assertInstanceOf(Js::class, $first_item);
        $this->assertEquals($path_with_query . '&version=' . $this->version, $first_item->getContent());
        $this->assertEquals(2, $first_item->getBatch());
    }
}
