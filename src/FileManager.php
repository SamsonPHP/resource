<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>.
 * on 10.07.16 at 15:40
 */
namespace samsonphp\resource;

/**
 * File system management class.
 * @package samsonphp\resource
 */
class FileManager implements FileManagerInterface
{
    /**
     * Wrapper for reading file.
     *
     * @param string $file Full path to file
     *
     * @return string Asset content
     */
    public function read($file)
    {
        return file_get_contents($file);
    }

    /**
     * Wrapper for writing file.
     *
     * @param string $asset   Full path to file
     *
     * @param string $content Asset content
     */
    public function write($asset, $content)
    {
        $path = dirname($asset);
        if (!file_exists($path)) {
            $this->mkdir($path);
        }

        file_put_contents($asset, $content);
    }

    /**
     * Create folder.
     *
     * @param string $path Full path to asset
     */
    public function mkdir($path)
    {
        // Create cache path
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
    }

    /**
     * If path(file or folder) exists.
     *
     * @param string $path Path for validating existence
     *
     * @return bool True if path exists
     */
    public function exists($path)
    {
        return file_exists($path);
    }

    /**
     * Wrapper for touching file.
     *
     * @param string $asset     Full path to file
     * @param int    $timestamp Timestamp
     */
    public function touch($asset, $timestamp)
    {
        // Sync cached file with source file
        touch($asset, $timestamp);
    }

    /**
     * Remove path/file recursively.
     *
     * @param string $path Path to be removed
     */
    public function remove($path)
    {
        if (is_dir($path)) {
            // Get folder content
            foreach (glob($path . '*', GLOB_MARK) as $file) {
                // Recursion
                $this->remove($file);
            }

            // Remove folder after all internal sub-folders are clear
            rmdir($path);
        } elseif (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Get last file modification timestamp.
     *
     * @param string $file Path to file
     *
     * @return int File modification timestamp
     */
    public function lastModified($file)
    {
        return filemtime($file);
    }
}
