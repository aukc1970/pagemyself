<?php

namespace Framelix\Myself;

use Framelix\Framelix\Config;
use Framelix\Framelix\Utils\FileUtils;
use Framelix\Framelix\Utils\JsonUtils;
use Framelix\Framelix\Utils\Zip;

use function array_combine;
use function basename;
use function class_exists;
use function copy;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function is_string;
use function mkdir;
use function preg_match;
use function realpath;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function unlink;

/**
 * Console Runner
 */
class Console extends \Framelix\Framelix\Console
{
    /**
     * Create a new module with empty boilerplate
     * @return void
     */
    public static function createModule(): void
    {
        $moduleName = self::getParameter('module', 'string');
        if (preg_match("~[^a-z0-9]~i", $moduleName)) {
            echo "Modulename can only contain A-Z and 0-9 chars";
            return;
        }
        if (!preg_match("~^[A-Z]~", $moduleName)) {
            echo "Modulename must start with an uppercase character";
            return;
        }
        $moduleDir = __DIR__ . "/../../$moduleName";
        if (is_dir($moduleDir)) {
            echo "Module Directory " . realpath($moduleDir) . " already exists";
            return;
        }
        mkdir($moduleDir);
        mkdir($moduleDir . "/_meta");
        mkdir($moduleDir . "/config");
        mkdir($moduleDir . "/lang");
        mkdir($moduleDir . "/js", 0777, true);
        file_put_contents($moduleDir . "/lang/en.json", '{}');
        mkdir($moduleDir . "/public/dist/css", 0777, true);
        mkdir($moduleDir . "/public/dist/js", 0777, true);
        mkdir($moduleDir . "/scss", 0777, true);
        mkdir($moduleDir . "/src", 0777, true);
        Config::writetConfigToFile($moduleName, "config-module.php", [
            'compiler' => [
                $moduleName => [
                    'js' => [],
                    'scss' => [],
                ]
            ]
        ]);
        $myselfConfig = Config::getConfigFromFile("Myself", "config-editable.php");
        $myselfConfig['modules'][$moduleName] = $moduleName;
        Config::writetConfigToFile("Myself", "config-editable.php", $myselfConfig);
        JsonUtils::writeToFile($moduleDir . "/package.json", [
            "version" => "0.0.1",
            "name" => "pagemyself-" . strtolower($moduleName),
            "description" => "Your module description",
            "pagemyself" => [
                "module" => $moduleName
            ],
        ], true);
    }

    /**
     * Create a new pageblock with empty boilerplate
     * @return void
     */
    public static function createPageBlock(): void
    {
        $module = self::getParameter('module', 'string');
        $pageBlockName = self::getParameter('pageBlockName', 'string');
        $pageBlockNameLower = strtolower($pageBlockName);
        $moduleDir = FileUtils::getModuleRootPath($module);
        if (!is_dir($moduleDir)) {
            echo "Module '$module' not exist";
            return;
        }
        $blockClass = "\\Framelix\\$module\\PageBlocks\\$pageBlockName";
        if (class_exists($blockClass)) {
            echo "'$blockClass' already exists";
            return;
        }

        if (!is_dir($moduleDir . "/js/page-blocks")) {
            mkdir($moduleDir . "/js/page-blocks", 0777, true);
        }
        if (!is_dir($moduleDir . "/scss/page-blocks")) {
            mkdir($moduleDir . "/scss/page-blocks", 0777, true);
        }
        if (!is_dir($moduleDir . "/src/PageBlocks")) {
            mkdir($moduleDir . "/src/PageBlocks", 0777, true);
        }
        $templateContent = file_get_contents(__DIR__ . "/../templates/PageBlock.php");
        $templateContent = str_replace("__BLOCKNAME__", $pageBlockName, $templateContent);
        $templateContent = str_replace("__MODULE__", $module, $templateContent);
        $path = $moduleDir . "/src/PageBlocks/$pageBlockName.php";
        file_put_contents($path, $templateContent);

        $templateContent = file_get_contents(__DIR__ . "/../templates/PageBlock.js");
        $templateContent = str_replace("__BLOCKNAMEJS__", $module . "PageBlocks" . $pageBlockName, $templateContent);
        $path = $moduleDir . "/js/page-blocks/$pageBlockNameLower/script.js";
        mkdir(dirname($path));
        file_put_contents($path, $templateContent);

        $templateContent = file_get_contents(__DIR__ . "/../templates/PageBlock.scss");
        $templateContent = str_replace(
            "__BLOCKNAMESCSS__",
            strtolower($module) . "-pageblocks-" . $pageBlockNameLower,
            $templateContent
        );
        $path = $moduleDir . "/scss/page-blocks/$pageBlockNameLower/style.scss";
        mkdir(dirname($path));
        file_put_contents($path, $templateContent);
        self::updateCompilerConfig();
    }

    /**
     * Update/install module by given zip file
     * @param string|null $zipPath If set, this action will update without further security questions (is used from web based update)
     * @return void
     */
    public static function updateModuleByZip(?string $zipPath = null): void
    {
        $zipPath = 'D:\www\pagemyself\build\dist\Calendar-0.1.0.zip';
        if (!is_string($zipPath)) {
            $zipPath = trim(self::question('Path to module ZIP file'));
            // try relative path
            if (!is_file($zipPath)) {
                $zipPath = __DIR__ . "/" . $zipPath;
            }
        }
        if (!file_exists($zipPath)) {
            self::red("$zipPath does not exist");
            return;
        }
        $tmpPath = __DIR__ . "/../tmp/unzip";
        FileUtils::deleteDirectory($tmpPath);
        mkdir($tmpPath);
        Zip::unzip($zipPath, $tmpPath);
        $packageJson = JsonUtils::readFromFile($tmpPath . "/package.json");
        if (!isset($packageJson['pagemyself']['module']) && ($packageJson['name'] ?? null) !== 'framelix') {
            self::red("$zipPath is not a valid module archive");
            return;
        }
        $moduleDir = FileUtils::getModuleRootPath($packageJson['pagemyself']['module'] ?? 'Framelix');
        $filelistNew = JsonUtils::readFromFile($tmpPath . "/filelist.json");
        if (!$filelistNew) {
            self::red(
                "$zipPath has no filelist. Build the archive with the build tools that generate the filelist for you."
            );
            return;
        }
        $filelistNew = array_combine($filelistNew, $filelistNew);
        $filelistExist = file_exists($moduleDir . "/filelist.json") ? JsonUtils::readFromFile(
            $moduleDir . "/filelist.json"
        ) : [];
        $filelistExist = array_combine($filelistExist, $filelistExist);
        if (!is_dir($moduleDir)) {
            mkdir($moduleDir);
        }
        foreach ($filelistNew as $relativeFile) {
            $tmpFilePath = FileUtils::normalizePath(realpath($tmpPath . "/" . $relativeFile));
            $newPath = $moduleDir . "/" . $relativeFile;
            if (is_dir($tmpFilePath)) {
                if (!is_dir($newPath)) {
                    mkdir($newPath);
                    self::green('Directory "' . $tmpFilePath . '" created' . "\n");
                } else {
                    self::yellow('Directory "' . $tmpFilePath . '" already exist, skip' . "\n");
                }
            } elseif (is_file($tmpFilePath)) {
                copy($tmpFilePath, $newPath);
                if (file_exists($newPath)) {
                    self::green('File "' . $tmpFilePath . '" updated' . "\n");
                } else {
                    self::green('File "' . $tmpFilePath . '" created' . "\n");
                }
            }
            unset($filelistExist[$relativeFile]);
        }
        foreach ($filelistExist as $fileExist) {
            $newPath = $moduleDir . "/" . $fileExist;
            if (is_dir($newPath)) {
                self::green('Obsolete Directory "' . $newPath . '" removed' . "\n");
            } elseif (is_file($newPath)) {
                self::green('Obsolete File "' . $newPath . '" removed' . "\n");
                unlink($newPath);
            }
        }
    }


    /**
     * Update compiler config based on available page blocks and themes
     * @return void
     */
    public static function updateCompilerConfig(): void
    {
        $module = self::getParameter('module', 'string');
        $moduleDir = FileUtils::getModuleRootPath($module);
        if (!is_dir($moduleDir)) {
            echo "Module '$module' not exist";
            return;
        }
        $config = $configOriginal = Config::getConfigFromFile($module, "config-module.php");
        if (isset($config['compiler'][$module])) {
            foreach ($config['compiler'][$module] as $type => $rows) {
                foreach ($rows as $key => $row) {
                    if (str_starts_with($key, "pageblock-") || str_starts_with($key, "theme-")) {
                        unset($config['compiler'][$module][$type][$key]);
                    }
                }
            }
        }
        $pageBlockFiles = FileUtils::getFiles($moduleDir . "/src/PageBlocks", "~\.php$~", false);
        foreach ($pageBlockFiles as $pageBlockFile) {
            $basename = basename($pageBlockFile);
            if ($basename === "BlockBase.php") {
                continue;
            }
            $blockName = substr(strtolower($basename), 0, -4);
            $jsFolder = $moduleDir . "/js/page-blocks/$blockName";
            if (is_dir($jsFolder)) {
                $config['compiler'][$module]['js']["pageblock-$blockName"] = [
                    "files" => [
                        [
                            "type" => "folder",
                            "path" => "js/page-blocks/$blockName",
                            "recursive" => true
                        ]
                    ],
                    "options" => ["noInclude" => true]
                ];
            }
            $scssFolder = $moduleDir . "/scss/page-blocks/$blockName";
            if (is_dir($scssFolder)) {
                $config['compiler'][$module]['scss']["pageblock-$blockName"] = [
                    "files" => [
                        [
                            "type" => "folder",
                            "path" => "scss/page-blocks/$blockName",
                            "recursive" => true
                        ]
                    ],
                    "options" => ["noInclude" => true]
                ];
            }
        }
        $themeFiles = FileUtils::getFiles($moduleDir . "/src/Themes", "~\.php$~", false);
        foreach ($themeFiles as $themeFile) {
            $basename = basename($themeFile);
            if ($basename === "ThemeBase.php") {
                continue;
            }
            $blockName = substr(strtolower($basename), 0, -4);
            $jsFolder = $moduleDir . "/js/themes/$blockName";
            if (is_dir($jsFolder)) {
                $config['compiler'][$module]['js']["theme-$blockName"] = [
                    "files" => [
                        [
                            "type" => "folder",
                            "path" => "js/themes/$blockName",
                            "recursive" => true
                        ]
                    ],
                    "options" => ["noInclude" => true]
                ];
            }
            $scssFolder = $moduleDir . "/scss/themes/$blockName";
            if (is_dir($scssFolder)) {
                $config['compiler'][$module]['scss']["theme-$blockName"] = [
                    "files" => [
                        [
                            "type" => "folder",
                            "path" => "scss/themes/$blockName",
                            "recursive" => true
                        ]
                    ],
                    "options" => ["noInclude" => true]
                ];
            }
        }
        if ($config !== $configOriginal) {
            Config::writetConfigToFile($module, "config-module.php", $config);
            echo "Updated config for $module";
        } else {
            echo "Config is already Up2Date";
        }
    }
}