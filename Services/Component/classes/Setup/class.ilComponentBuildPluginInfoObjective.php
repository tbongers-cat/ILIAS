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
 ********************************************************************
 */

use ILIAS\Setup;

class ilComponentBuildPluginInfoObjective extends Setup\Artifact\BuildArtifactObjective
{
    protected const BASE_PATH = "./Customizing/global/plugins/";
    protected const BASE_PATH_FINDER = "./global/plugins/";
    protected const PLUGIN_PHP = "plugin.php";
    protected const PLUGIN_CLASS_FILE = "classes/class.il%sPlugin.php";
    protected ?\ILIAS\Filesystem\Finder\Finder $finder = null;

    public function getArtifactPath(): string
    {
        return \ilArtifactComponentRepository::PLUGIN_DATA_PATH;
    }


    public function build(): Setup\Artifact
    {
        $data = [];
        foreach (["Modules", "Services"] as $type) {
            $components = $this->scanDir($type);
            foreach ($components as $component) {
                $slots = $this->scanDir("$type/$component");
                foreach ($slots as $slot) {
                    $plugins = $this->scanDir("$type/$component/$slot");
                    foreach ($plugins as $plugin) {
                        $this->addPlugin($data, $type, $component, $slot, $plugin);
                    }
                }
            }
        }
        return new Setup\Artifact\ArrayArtifact($data);
    }

    protected function getFinder()
    {
        if ($this->finder) return $this->finder;

        // This is not functional without $DIC
        $filesystem = $DIC->filesystem()->customizing();
        $this->finder = $filesystem->finder()->ignoreVCS(true); // also ignores dot files by default
    }

    protected function addPlugin(array &$data, string $type, string $component, string $slot, string $plugin): void
    {
        $plugin_path = $this->buildPluginPath($type, $component, $slot, $plugin);
        $plugin_php = $plugin_path . static::PLUGIN_PHP;
        if (!$this->fileExists($plugin_php)) {
            throw new \RuntimeException(
                "Cannot read $plugin_php."
            );
        }

        $plugin_class = $plugin_path . sprintf(static::PLUGIN_CLASS_FILE, $plugin);
        if (!$this->fileExists($plugin_class)) {
            throw new \RuntimeException(
                "Cannot read $plugin_class."
            );
        }

        require_once($plugin_php);
        if (!isset($id)) {
            throw new \InvalidArgumentException("$plugin_class does not define \$id");
        }
        if (!isset($version)) {
            throw new \InvalidArgumentException("$plugin_class does not define \$version");
        }
        if (!isset($ilias_min_version)) {
            throw new \InvalidArgumentException("$plugin_class does not define \$ilias_min_version");
        }
        if (!isset($ilias_max_version)) {
            throw new \InvalidArgumentException("$plugin_class does not define \$ilias_max_version");
        }

        if (isset($data[$id])) {
            throw new \RuntimeException(
                "Plugin with id $id already exists."
            );
        }

        $data[$id] = [
            $type,
            $component,
            $slot,
            $plugin,
            $version,
            $ilias_min_version,
            $ilias_max_version,
            $responsible ?? "",
            $responsible_mail ?? "",
            $learning_progress ?? null,
            $supports_export ?? null,
            $supports_cli_setup ?? null
        ];
    }

    /**
     * @return string[]
     */
    protected function scanDir(string $dir): array
    {
        if ($this->getFinder()->in([static::BASE_PATH_FINDER . $dir])->count() === 0) {
            return [];
        }

        $directory_names = [];
        $dirs = $this->getFinder()->in([static::BASE_PATH_FINDER . $dir])->directories()->getIterator();
        foreach ($dirs as $dir) {
            $directory_names[] = basename($dir);
        }
        return $directory_names;
    }

    protected function fileExists(string $path): bool
    {
        return file_exists($path) && is_file($path);
    }

    protected function buildPluginPath(string $type, string $component, string $slot, string $plugin): string
    {
        return static::BASE_PATH . "$type/$component/$slot/$plugin/";
    }
}
