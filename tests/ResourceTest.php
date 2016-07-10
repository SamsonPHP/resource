<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>.
 * on 20.02.16 at 14:39
 */
namespace samsonphp\less\tests;

use samsonphp\resource\FileManager;
use samsonphp\resource\FileManagerInterface;
use samsonphp\resource\Resource;

class ResourceTest extends \PHPUnit_Framework_TestCase
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
        $this->resource = new Resource($this->fileManager);

        // Switch paths to testing environment
        Resource::$excludeFolders = ['*/tests/cache/*'];
        Resource::$projectRoot = __DIR__ . '/';
        Resource::$webRoot = __DIR__ . '/www/';
        Resource::$cacheRoot = __DIR__ . '/cache/';

        // Remove cache folder
        if ($this->fileManager->exists(Resource::$cacheRoot)) {
            $this->fileManager->remove(Resource::$cacheRoot);
        }

        // Create files
        for ($i = 1; $i < 3; $i++) {
            $parent = implode('/', array_fill(0, $i, 'folder'));
            foreach (Resource::TYPES as $type) {
                $this->fileManager->write(__DIR__ . '/' . $parent . '/test' . $i . '.' . $type, '');
            }
        }
    }

    public function testManage()
    {
        // Run first time to generate assets
        $this->resource->manage([__DIR__]);

        $this->assertFileExists(Resource::$cacheRoot . '/folder1/test.css');
        $this->assertFileExists(Resource::$cacheRoot . '/folder1/folder2/test2.css');
        $this->assertFileExists(Resource::$cacheRoot . '/folder1/folder2/test.png');

        // Run second time to use cache
        $this->resource->manage([__DIR__]);
    }
}
