<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>.
 * on 20.02.16 at 14:39
 */
namespace samsonphp\less\tests;

use PHPUnit\Framework\TestCase;
use samsonphp\resource\FileManager;
use samsonphp\resource\FileManagerInterface;
use samsonphp\resource\ResourceManager;

class ResourceTest extends TestCase
{
    /** @var Resource */
    protected $resource;
    /** @var  FileManagerInterface */
    protected $fileManager;
    /** @var array Collection of assets */
    protected $files = [];

    public function setUp()
    {
        $this->fileManager = new FileManager();
        $this->resource = new ResourceManager($this->fileManager);

        // Switch paths to testing environment
        ResourceManager::$excludeFolders = ['*/tests/cache/*'];
        ResourceManager::$projectRoot = __DIR__ . '/';
        ResourceManager::$webRoot = __DIR__ . '/www/';
        ResourceManager::$cacheRoot = __DIR__ . '/cache/';

        // Remove cache folder
        if ($this->fileManager->exists(ResourceManager::$cacheRoot)) {
            $this->fileManager->remove(ResourceManager::$cacheRoot);
        }

        // Create files
        for ($i = 1; $i < 3; $i++) {
            $parent = implode('/', array_fill(0, $i, 'folder'));
            foreach (ResourceManager::TYPES as $type) {
                $file = $parent . '/test' . $i . '.' . $type;
                $this->fileManager->write(__DIR__ . '/' . $file, '/** TEST */');
                $this->files[] = $file;
            }
        }
    }

    public function testManage()
    {
        // Run first time to generate assets
        $this->resource->manage([__DIR__]);

        foreach ($this->files as $file) {
            $this->assertFileExists(ResourceManager::$cacheRoot . dirname($file) . '/'
                . pathinfo($file, PATHINFO_FILENAME) . '.' . $this->resource->convertType($file));
        }

        // Run second time to use cache
        $this->resource->manage([__DIR__]);
    }
}
