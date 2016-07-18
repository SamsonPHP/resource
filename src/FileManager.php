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
     *
     * @throws \Exception
     */
    public function mkdir($path)
    {
        // Create cache path
        if (!$this->exists($path)) {
            try {
                mkdir($path, 0777, true);
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage() . ' ' . $path);
            }
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

    /**
     * Recursively scan collection of paths to find files with passed
     * extensions. Method is based on linux find command so this method
     * can face difficulties on other OS.
     *
     *
     * @param array $paths          Paths for files scanning
     * @param array $extensions     File extension filter
     * @param array $excludeFolders Path patterns for excluding
     *
     * @return array Found files
     */
    public function scan(array $paths, array $extensions, array $excludeFolders = [])
    {
        // Generate LINUX command to gather resources as this is 20 times faster
        $files = [];

        // Generate exclusion conditions
        $exclude = implode(' ', array_map(function ($value) {
            return '-not -path ' . $value . ' ';
        }, $excludeFolders));

        // Generate filters
        $filters = implode('-o ', array_map(function ($value) use ($exclude) {
            return '-name "*.' . $value . '" ' . $exclude;
        }, $extensions));

        // Scan path excluding folder patterns
        exec('find ' . implode(' ', $paths) . ' -type f ' . $filters, $files);

        // TODO: Why some paths have double slashes? Investigate speed of realpath, maybe // changing if quicker
        return array_map('realpath', $files);
    }
}
