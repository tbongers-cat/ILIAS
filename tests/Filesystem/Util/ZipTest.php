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

namespace ILIAS\Filesystem\Util;

use ILIAS\Filesystem\Stream\Streams;
use ILIAS\Filesystem\Util\Archive\LegacyArchives;
use ILIAS\Filesystem\Util\Archive\Unzip;
use ILIAS\Filesystem\Util\Archive\UnzipOptions;
use PHPUnit\Framework\TestCase;
use ILIAS\Filesystem\Util\Archive\Zip;
use ILIAS\Filesystem\Util\Archive\ZipOptions;

/**
 * @author Fabian Schmid <fabian@sr.solutions>
 *
 * @runTestsInSeparateProcesses // This is required for the test to work since we define some constants in the test
 * @preserveGlobalState    disabled
 * @backupGlobals          disabled
 * @backupStaticAttributes disabled
 */
class ZipTest extends TestCase
{
    public const ZIPPED_ZIP = 'zipped.zip';
    protected string $zips_dir = __DIR__ . '/zips/';
    protected string $unzips_dir = __DIR__ . '/unzips/';

    protected function setUp(): void
    {
        if (file_exists($this->unzips_dir . self::ZIPPED_ZIP)) {
            unlink($this->unzips_dir . self::ZIPPED_ZIP);
        }
        if (!file_exists($this->unzips_dir)) {
            mkdir($this->unzips_dir);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->unzips_dir)) {
            $this->recurseRmdir($this->unzips_dir);
        }
    }

    public function testZip(): void
    {
        $zip_options = new ZipOptions();
        $streams = [
            Streams::ofResource(fopen($this->zips_dir . '1_folder_1_file_mac.zip', 'r')),
            Streams::ofResource(fopen($this->zips_dir . '1_folder_win.zip', 'r')),
        ];
        $zip = new Zip($zip_options, ...$streams);
        $zip_stream = $zip->get();
        $this->assertGreaterThan(0, $zip_stream->getSize());

        $unzip_again = new Unzip(new UnzipOptions(), $zip_stream);
        $this->assertEquals(2, $unzip_again->getAmountOfFiles());
    }

    public function testLegacyZip(): void
    {
        $legacy = new LegacyArchives();

        define('CLIENT_WEB_DIR', __DIR__);
        define('ILIAS_WEB_DIR', 'public/data');
        define('CLIENT_ID', 'test');
        define('CLIENT_DATA_DIR', __DIR__);
        define('ILIAS_ABSOLUTE_PATH', __DIR__);

        $legacy->zip($this->zips_dir, $this->unzips_dir . self::ZIPPED_ZIP, false);
        $this->assertFileExists($this->unzips_dir . self::ZIPPED_ZIP);

        $unzip_again = new Unzip(new UnzipOptions(), Streams::ofResource(fopen($this->unzips_dir . self::ZIPPED_ZIP, 'r')));
        $this->assertEquals(5, $unzip_again->getAmountOfFiles());

        $depth = 0;
        foreach ($unzip_again->getPaths() as $path) {
            $parts = explode('/', $path);
            $depth = max($depth, count($parts));
        }
        $this->assertEquals(2, $depth);
        $this->recurseRmdir($this->unzips_dir);
    }

    public function LegacyZipWithTop(): void
    {
        $legacy = new LegacyArchives();

        define('CLIENT_WEB_DIR', __DIR__);
        define('ILIAS_WEB_DIR', 'public/data');
        define('CLIENT_ID', 'test');
        define('CLIENT_DATA_DIR', __DIR__);
        define('ILIAS_ABSOLUTE_PATH', __DIR__);

        mkdir($this->unzips_dir);
        $legacy->zip($this->zips_dir, $this->unzips_dir . self::ZIPPED_ZIP, true);
        $this->assertFileExists($this->unzips_dir . self::ZIPPED_ZIP);

        $unzip_again = new Unzip(new UnzipOptions(), Streams::ofResource(fopen($this->unzips_dir . self::ZIPPED_ZIP, 'r')));
        $this->assertEquals(5, $unzip_again->getAmountOfFiles());

        $depth = 0;
        foreach ($unzip_again->getPaths() as $path) {
            $parts = explode('/', $path);
            $depth = max($depth, count($parts));
        }
        $this->assertEquals(2, $depth);
        $this->recurseRmdir($this->unzips_dir);
    }

    /**
     * @dataProvider getZips
     * @param mixed[] $expected_directories
     * @param mixed[] $expected_files
     */
    public function testUnzip(
        string $zip,
        bool $has_multiple_root_entries,
        int $expected_amount_directories,
        array $expected_directories,
        int $expected_amount_files,
        array $expected_files
    ): void {
        $this->assertStringContainsString('.zip', $zip);
        $zip_path = $this->zips_dir . $zip;
        $this->assertFileExists($zip_path);

        $stream = Streams::ofResource(fopen($zip_path, 'rb'));
        $options = new UnzipOptions();
        $unzip = new Unzip($options, $stream);

        $this->assertFalse($unzip->hasZipReadingError());
        $this->assertEquals($has_multiple_root_entries, $unzip->hasMultipleRootEntriesInZip());
        $this->assertEquals($expected_amount_directories, $unzip->getAmountOfDirectories());
        $this->assertEquals($expected_directories, iterator_to_array($unzip->getDirectories()));
        $this->assertEquals($expected_amount_files, $unzip->getAmountOfFiles());
        $this->assertEquals($expected_files, iterator_to_array($unzip->getFiles()));
    }

    public function testWrongZip(): void
    {
        $stream = Streams::ofResource(fopen(__FILE__, 'rb'));
        $options = new UnzipOptions();
        $unzip = new Unzip($options, $stream);
        $this->assertTrue($unzip->hasZipReadingError());
        $this->assertFalse($unzip->hasMultipleRootEntriesInZip());
        $this->assertEquals(0, iterator_count($unzip->getFiles()));
        $this->assertEquals(0, iterator_count($unzip->getDirectories()));
        $this->assertEquals(0, iterator_count($unzip->getPaths()));
        $this->assertEquals([], iterator_to_array($unzip->getDirectories()));
        $this->assertEquals([], iterator_to_array($unzip->getFiles()));
    }


    public function testLargeZIPs(): void
    {
        // get ulimit
        $ulimit = (int) shell_exec('ulimit -n');
        $limit = 2500;
        if ($ulimit >= $limit) {
            $this->markTestSkipped('ulimit is too high and would take too much resources');
        }
        $this->assertLessThan($limit, $ulimit);

        $zip = new Zip(new ZipOptions());

        $file_names = [];

        for ($i = 0; $i < $ulimit * 2; $i++) {
            $path_inside_zip = $file_names[] = 'test' . $i;
            $zip->addStream(Streams::ofString('-'), $path_inside_zip);
        }
        $this->assertTrue(true); // no warning or error

        // check if the zip now contains all files
        $unzip = new Unzip(new UnzipOptions(), $zip->get());
        $file_names_in_zip = iterator_to_array($unzip->getFiles());
        sort($file_names);
        sort($file_names_in_zip);
        $this->assertEquals($file_names, $file_names_in_zip);
    }

    /**
     * @dataProvider getZips
     * @param mixed[] $expected_directories
     * @param mixed[] $expected_files
     */
    public function testLegacyUnzip(
        string $zip,
        bool $has_multiple_root_entries,
        int $expected_amount_directories,
        array $expected_directories,
        int $expected_amount_files,
        array $expected_files
    ): void {
        $legacy = new LegacyArchives();

        $this->assertStringContainsString('.zip', $zip);
        $zip_path = $this->zips_dir . $zip;
        $this->assertFileExists($zip_path);

        $temp_unzip_path = $this->unzips_dir . uniqid('unzip', true);

        $return = $legacy->unzip(
            $zip_path,
            $temp_unzip_path
        );

        $this->assertTrue($return);

        $unzipped_files = $this->directoryToArray($temp_unzip_path);
        $expected_paths = array_merge($expected_directories, $expected_files);
        sort($expected_paths);
        $this->assertEquals($expected_paths, $unzipped_files);
        $this->assertTrue($this->recurseRmdir($temp_unzip_path));
    }

    private function recurseRmdir(string $path_to_directory): bool
    {
        $files = array_diff(scandir($path_to_directory), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$path_to_directory/$file") && !is_link("$path_to_directory/$file")) ? $this->recurseRmdir(
                "$path_to_directory/$file"
            ) : unlink("$path_to_directory/$file");
        }
        return rmdir($path_to_directory);
    }

    /**
     * @return string[]|string[][]
     */
    private function directoryToArray(string $path_to_directory): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path_to_directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
            \RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        $paths = [];
        foreach ($iterator as $item) {
            $relative_path = str_replace($path_to_directory . '/', '', $item->getPathname());
            $paths[] = $item->isDir() ? $relative_path . '/' : $relative_path;
        }

        sort($paths);

        return $paths;
    }

    // PROVIDERS

    public static function getZips(): array
    {
        return [
            ['1_folder_mac.zip', false, 10, self::$directories_one, 15, self::$files_one],
            ['1_folder_win.zip', false, 10, self::$directories_one, 15, self::$files_one],
            ['3_folders_mac.zip', true, 9, self::$directories_three, 12, self::$files_three],
            ['3_folders_win.zip', true, 9, self::$directories_three, 12, self::$files_three],
            ['1_folder_1_file_mac.zip', true, 3, self::$directories_mixed, 5, self::$files_mixed]
        ];
    }

    protected static array $files_mixed = [
        0 => '03_Test.pdf',
        1 => 'Ordner A/01_Test.pdf',
        2 => 'Ordner A/02_Test.pdf',
        3 => 'Ordner A/Ordner A_2/07_Test.pdf',
        4 => 'Ordner A/Ordner A_2/08_Test.pdf'
    ];

    protected static array $directories_mixed = [
        0 => 'Ordner A/',
        1 => 'Ordner A/Ordner A_1/',
        2 => 'Ordner A/Ordner A_2/'
    ];

    protected static array $directories_one = [
        0 => 'Ordner 0/',
        1 => 'Ordner 0/Ordner A/',
        2 => 'Ordner 0/Ordner A/Ordner A_1/',
        3 => 'Ordner 0/Ordner A/Ordner A_2/',
        4 => 'Ordner 0/Ordner B/',
        5 => 'Ordner 0/Ordner B/Ordner B_1/',
        6 => 'Ordner 0/Ordner B/Ordner B_2/',
        7 => 'Ordner 0/Ordner C/',
        8 => 'Ordner 0/Ordner C/Ordner C_1/',
        9 => 'Ordner 0/Ordner C/Ordner C_2/'
    ];
    protected static array $directories_three = [
        0 => 'Ordner A/',
        1 => 'Ordner A/Ordner A_1/',
        2 => 'Ordner A/Ordner A_2/',
        3 => 'Ordner B/',
        4 => 'Ordner B/Ordner B_1/',
        5 => 'Ordner B/Ordner B_2/',
        6 => 'Ordner C/',
        7 => 'Ordner C/Ordner C_1/',
        8 => 'Ordner C/Ordner C_2/'
    ];

    protected static array $files_one = [
        0 => 'Ordner 0/13_Test.pdf',
        1 => 'Ordner 0/14_Test.pdf',
        2 => 'Ordner 0/15_Test.pdf',
        3 => 'Ordner 0/Ordner A/01_Test.pdf',
        4 => 'Ordner 0/Ordner A/02_Test.pdf',
        5 => 'Ordner 0/Ordner A/Ordner A_2/07_Test.pdf',
        6 => 'Ordner 0/Ordner A/Ordner A_2/08_Test.pdf',
        7 => 'Ordner 0/Ordner B/03_Test.pdf',
        8 => 'Ordner 0/Ordner B/04_Test.pdf',
        9 => 'Ordner 0/Ordner B/Ordner B_2/09_Test.pdf',
        10 => 'Ordner 0/Ordner B/Ordner B_2/10_Test.pdf',
        11 => 'Ordner 0/Ordner C/05_Test.pdf',
        12 => 'Ordner 0/Ordner C/06_Test.pdf',
        13 => 'Ordner 0/Ordner C/Ordner C_2/11_Test.pdf',
        14 => 'Ordner 0/Ordner C/Ordner C_2/12_Test.pdf'
    ];

    protected static array $files_three = [
        0 => 'Ordner A/01_Test.pdf',
        1 => 'Ordner A/02_Test.pdf',
        2 => 'Ordner A/Ordner A_2/07_Test.pdf',
        3 => 'Ordner A/Ordner A_2/08_Test.pdf',
        4 => 'Ordner B/03_Test.pdf',
        5 => 'Ordner B/04_Test.pdf',
        6 => 'Ordner B/Ordner B_2/09_Test.pdf',
        7 => 'Ordner B/Ordner B_2/10_Test.pdf',
        8 => 'Ordner C/05_Test.pdf',
        9 => 'Ordner C/06_Test.pdf',
        10 => 'Ordner C/Ordner C_2/11_Test.pdf',
        11 => 'Ordner C/Ordner C_2/12_Test.pdf',
    ];
}
