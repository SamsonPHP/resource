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
    }

    public function testManage()
    {
        // Run first time to generate assets
        $this->resource->manage([__DIR__]);
        // Run second time to use cache
        $this->resource->manage([__DIR__]);
    }
}
