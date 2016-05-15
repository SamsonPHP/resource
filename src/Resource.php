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
    /** @var string Full path to project web root directory */
    public static $webRoot;

    /** @var string Full path to project root directory */
    public static $projectRoot;

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
