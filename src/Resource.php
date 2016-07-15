<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>.
 * on 15.05.16 at 10:52
 */
namespace samsonphp\resource;

use samsonphp\resource\exception\ResourceNotFound;

/**
 * Static resource entity class.
 *
 * @package samsonphp\resource
 */
class Resource
{
    /** Collection of excluding scanning folder patterns */
    const EXCLUDING_FOLDERS = [
        '*/cache/*',
        '*/tests/*',
        '*/vendor/*/vendor/*'
    ];

    /** @var string Full path to project web root directory */
    public static $webRoot;

    /** @var string Full path to project root directory */
    public static $projectRoot;

    /**
     * Recursively scan collection of paths to find assets with passed
     * extensions. Method is based on linux find command so this method
     * can face difficulties on other OS.
     *
     * TODO: Add windows support
     * TODO: Check if CMD commands can be executed
     *
     * @param array $paths      Paths for files scanning
     * @param array $extensions File extension filter
     * @param array $excludeFolders Path patterns for excluding
     *
     * @return array Found files
     */
    public static function scan(array $paths, array $extensions, array $excludeFolders = self::EXCLUDING_FOLDERS)
    {
        // Generate LINUX command to gather resources as this is 20 times faster
        $files = [];

        // Generate exclusion conditions
        $exclude = implode(' ', array_map(function ($value) {
            return '-not -path ' . $value.' ';
        }, $excludeFolders));

        // Generate other types
        $filters = implode('-o ', array_map(function ($value) use ($exclude) {
            return '-name "*.' . $value . '" '.$exclude;
        }, $extensions));

        // Scan path excluding folder patterns
        exec('find ' . implode(' ', $paths) . ' -type f '.$filters, $files);

        // Sort files alphabeticall
        usort ($files, function($a, $b) {
            if (strpos($a, 'vendor/') !== false && strpos($b, 'vendor/') === false) {
                return -1;
            } elseif (strpos($b, 'vendor/') !== false && strpos($a, 'vendor/') === false) {
                return 1;
            } elseif ($a === $b) {
                return 0;
            } else {
                return strcmp($a, $b);
            }
        });

        // TODO: Why some paths have double slashes? Investigate speed of realpath, maybe // changing if quicker
        return array_map('realpath', $files);
    }

    /**
     * Build relative path to static resource relatively to web root path.
     *
     * @param string $relativePath Relative path to static resource
     * @param string $parentPath   Path to parent entity
     *
     * @return string Validated relative path to static resource relatively to web root path
     * @throws ResourceNotFound
     */
    public static function getWebRelativePath($relativePath, $parentPath = '')
    {
        return static::getRelativePath($relativePath, $parentPath, static::$webRoot);
    }

    /**
     * Build correct relative path to static resource using relative path and parent path.
     *
     * @param string $relativePath Relative path to static resource
     * @param string $parentPath   Path to parent entity
     * @param string $rootPath     Root path for relative path building
     *
     * @return string Validated relative path to static resource
     * @throws ResourceNotFound
     */
    public static function getRelativePath($relativePath, $parentPath = '', $rootPath = '')
    {
        // If parent path if not passed - use project root path
        $parentPath = $parentPath === '' ? static::$projectRoot : $parentPath;

        // Build full path to resource from given relative path
        $fullPath = rtrim($parentPath, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .ltrim($relativePath, DIRECTORY_SEPARATOR);

        // Make real path with out possible "../"
        $realPath = realpath($fullPath);

        // Output link to static resource handler with relative path to project root
        if ($realPath) {
            // Build relative path to static resource
            return str_replace($rootPath, '', $realPath);
        }

        throw new ResourceNotFound($fullPath);
    }

    /**
     * Build relative path to static resource relatively to project root path.
     *
     * @param string $relativePath Relative path to static resource
     * @param string $parentPath   Path to parent entity
     *
     * @return string Validated relative path to static resource relatively to project root path
     * @throws ResourceNotFound
     */
    public static function getProjectRelativePath($relativePath, $parentPath = '')
    {
        return static::getRelativePath($relativePath, $parentPath, static::$projectRoot);
    }
}
