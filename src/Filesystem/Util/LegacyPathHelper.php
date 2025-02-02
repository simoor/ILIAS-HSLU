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

namespace ILIAS\Filesystem\Util;

use ILIAS\Filesystem\Filesystem;
use ILIAS\Filesystem\FilesystemsAware;
use ILIAS\FileUpload\Location;

/**
 * Class LegacyPathHelper
 *
 * The legacy path helper provides convenient functions for the integration of the filesystem service within legacy components.
 * This class should be deprecated with ILIAS 5.5 or earlier.
 *
 * @author  Nicolas Schäfli <ns@studer-raimann.ch>
 * @since   5.3
 * @version 1.0.0
 */
final class LegacyPathHelper
{
    use FilesystemsAware;

    public static function deriveLocationFrom(string $absolute_path): int
    {
        [
            $web,
            $webRelativeWithLeadingDot,
            $webRelativeWithoutLeadingDot,
            $storage,
            $customizing,
            $customizingRelativeWithLeadingDot,
            $libs,
            $libsRelativeWithLeadingDot,
            $temp,
            $nodeModules,
            $nodeModulesWithLeadingDot
            ] = self::listPaths();

        switch (true) {
            case self::checkPossiblePath($temp, $absolute_path):
                return Location::TEMPORARY;
            case self::checkPossiblePath($web, $absolute_path):
            case self::checkPossiblePath($webRelativeWithLeadingDot, $absolute_path):
            case self::checkPossiblePath($webRelativeWithoutLeadingDot, $absolute_path):
                return Location::WEB;
            case self::checkPossiblePath($storage, $absolute_path):
                return Location::STORAGE;
            case self::checkPossiblePath($customizing, $absolute_path):
            case self::checkPossiblePath($customizingRelativeWithLeadingDot, $absolute_path):
                return Location::CUSTOMIZING;
            default:
                throw new \InvalidArgumentException("Invalid path supplied. Path must start with the web, storage, temp, customizing or libs storage location. Path given: '{$absolute_path}'");
        }
    }

    /**
     * Tries to fetch the filesystem responsible for the absolute path.
     * Please note that the function is case sensitive.
     *
     * Relative paths are also detected for the ILIAS web storage like './data/default'
     *
     *
     * @param string $absolute_path The absolute used for the filesystem search.
     *
     * @return Filesystem                   The responsible filesystem for the given path.
     *
     * @throws \InvalidArgumentException    Thrown if no filesystem is responsible for the given path.
     */
    public static function deriveFilesystemFrom(string $absolute_path): Filesystem
    {
        [
            $web,
            $webRelativeWithLeadingDot,
            $webRelativeWithoutLeadingDot,
            $storage,
            $customizing,
            $customizingRelativeWithLeadingDot,
            $libs,
            $libsRelativeWithLeadingDot,
            $temp,
            $nodeModules,
            $nodeModulesWithLeadingDot
            ] = self::listPaths();

        switch (true) {

            case self::checkPossiblePath($webRelativeWithLeadingDot, $absolute_path):
                return self::filesystems()->web();
            case self::checkPossiblePath($webRelativeWithoutLeadingDot, $absolute_path):
                return self::filesystems()->web();
            case self::checkPossiblePath($temp, $absolute_path):
                return self::filesystems()->temp();
            case self::checkPossiblePath($web, $absolute_path):
                return self::filesystems()->web();
            case self::checkPossiblePath($storage, $absolute_path):
                return self::filesystems()->storage();
            case self::checkPossiblePath($customizing, $absolute_path):
                return self::filesystems()->customizing();
            case self::checkPossiblePath($customizingRelativeWithLeadingDot, $absolute_path):
                return self::filesystems()->customizing();
            case self::checkPossiblePath($libs, $absolute_path):
                return self::filesystems()->libs();
            case self::checkPossiblePath($libsRelativeWithLeadingDot, $absolute_path):
                return self::filesystems()->libs();
            case self::checkPossiblePath($nodeModules, $absolute_path):
                return self::filesystems()->nodeModules();
            case self::checkPossiblePath($nodeModulesWithLeadingDot, $absolute_path):
                return self::filesystems()->nodeModules();
            default:
                throw new \InvalidArgumentException("Invalid path supplied. Path must start with the web, storage, temp, customizing or libs storage location. Path given: '{$absolute_path}'");
        }
    }


    /**
     * Creates a relative path from an absolute path which starts with a valid storage location.
     * The primary use case for this method is to trim the path after the filesystem was fetch via the deriveFilesystemFrom method.
     *
     * @param string $absolute_path The path which should be trimmed.
     *
     * @return string                       The trimmed relative path.
     *
     * @throws \InvalidArgumentException    Thrown if the path does not start with a valid storage location.
     *
     * @see LegacyPathHelper::deriveFilesystemFrom()
     */
    public static function createRelativePath(string $absolute_path): string
    {
        [
            $web,
            $webRelativeWithLeadingDot,
            $webRelativeWithoutLeadingDot,
            $storage,
            $customizing,
            $customizingRelativeWithLeadingDot,
            $libs,
            $libsRelativeWithLeadingDot,
            $temp,
            $nodeModules,
            $nodeModulesWithLeadingDot
            ] = self::listPaths();

        switch (true) {
            // web without ./
            case self::checkPossiblePath($webRelativeWithoutLeadingDot, $absolute_path):
                return self::resolveRelativePath($webRelativeWithoutLeadingDot, $absolute_path);
            // web with ./
            case self::checkPossiblePath($webRelativeWithLeadingDot, $absolute_path):
                return self::resolveRelativePath($webRelativeWithLeadingDot, $absolute_path);
            // web/
            case self::checkPossiblePath($web, $absolute_path):
                return self::resolveRelativePath($web, $absolute_path);
            // temp/
            case self::checkPossiblePath($temp, $absolute_path):
                return self::resolveRelativePath($temp, $absolute_path);
            // iliasdata/
            case self::checkPossiblePath($storage, $absolute_path):
                return self::resolveRelativePath($storage, $absolute_path);
            // Customizing/
            case self::checkPossiblePath($customizing, $absolute_path):
                return self::resolveRelativePath($customizing, $absolute_path);
            // ./Customizing/
            case self::checkPossiblePath($customizingRelativeWithLeadingDot, $absolute_path):
                return self::resolveRelativePath($customizingRelativeWithLeadingDot, $absolute_path);
            // libs/
            case self::checkPossiblePath($libs, $absolute_path):
                // ./libs
            case self::checkPossiblePath($libsRelativeWithLeadingDot, $absolute_path):
                return self::resolveRelativePath($libsRelativeWithLeadingDot, $absolute_path);
            // node_modules/
            case self::checkPossiblePath($nodeModules, $absolute_path):
                // ./node_modules
            case self::checkPossiblePath($nodeModulesWithLeadingDot, $absolute_path):
                return self::resolveRelativePath($nodeModulesWithLeadingDot, $absolute_path);
            default:
                throw new \InvalidArgumentException("Invalid path supplied. Path must start with the web, storage, temp, customizing or libs storage location. Path given: '{$absolute_path}'");
        }
    }


    private static function resolveRelativePath(string $possible_path, string $absolute_path): string
    {
        $real_possible_path = realpath($possible_path);

        switch (true) {
            case $possible_path === $absolute_path:
            case $real_possible_path === $absolute_path:
                return "";
            case strpos($absolute_path, $possible_path) === 0:
                return substr(
                    $absolute_path,
                    strlen($possible_path) + 1
                );                             //also remove the trailing slash
            case strpos($absolute_path, $real_possible_path) === 0:
                return substr(
                    $absolute_path,
                    strlen($real_possible_path) + 1
                );                             //also remove the trailing slash
            default:
                throw new \InvalidArgumentException("Invalid path supplied. Path must start with the web, storage, temp, customizing or libs storage location. Path given: '{$absolute_path}'");
        }
    }


    /**
     * @param string $possible_path
     * @param string $absolute_path
     *
     * @return bool
     */
    private static function checkPossiblePath(string $possible_path, string $absolute_path): bool
    {
        $real_possible_path = realpath($possible_path);

        switch (true) {
            case $possible_path === $absolute_path:
                return true;
            case $real_possible_path === $absolute_path:
                return true;
            case is_string($possible_path) && strpos($absolute_path, $possible_path) === 0:
                return true;
            case is_string($real_possible_path) && strpos($absolute_path, $real_possible_path) === 0:
                return true;
            default:
                return false;
        }
    }


    /**
     * @return array
     */
    private static function listPaths(): array
    {
        $web = CLIENT_WEB_DIR;
        $webRelativeWithLeadingDot = './' . ILIAS_WEB_DIR . '/' . CLIENT_ID;
        $webRelativeWithoutLeadingDot = ILIAS_WEB_DIR . '/' . CLIENT_ID;
        $storage = CLIENT_DATA_DIR;
        $customizing = ILIAS_ABSOLUTE_PATH . '/Customizing';
        $customizingRelativeWithLeadingDot = './Customizing';
        $libs = ILIAS_ABSOLUTE_PATH . '/libs';
        $libsRelativeWithLeadingDot = "./libs";
        $temp = CLIENT_DATA_DIR . "/temp";
        $nodeModules = ILIAS_ABSOLUTE_PATH . '/node_modules';
        $nodeModulesWithLeadingDot = './node_modules';

        return array($web,
                     $webRelativeWithLeadingDot,
                     $webRelativeWithoutLeadingDot,
                     $storage,
                     $customizing,
                     $customizingRelativeWithLeadingDot,
                     $libs,
                     $libsRelativeWithLeadingDot,
                     $temp,
                     $nodeModules,
                     $nodeModulesWithLeadingDot
        );
    }
}
