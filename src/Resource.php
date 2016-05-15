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
    /**
     * Build correct relative path to static resource using relative path and parent path.
     *
     * @param string $relativePath Relative path to static resource
     * @param string $parentPath Path to parent entity
     *
     * @return string Validated relative path to static resource
     * @throws ResourceNotFound
     */
    public static function getRelativePath($relativePath, $parentPath = '')
    {
        // Build full path to resource from given relative path
        $fullPath = rtrim($parentPath, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .ltrim($relativePath, DIRECTORY_SEPARATOR);

        // Make real path with out possible "../"
        $realPath = realpath($fullPath);

        // Output link to static resource handler with relative path to project root
        if ($realPath) {
            return str_replace(dirname(getcwd()), '', $realPath);
        }

        throw new ResourceNotFound($fullPath);
    }
}
