<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>.
 * on 20.02.16 at 14:39
 */
namespace samsonphp\less\tests;

use samsonphp\resource\FileManager;
use samsonphp\resource\Resource;

class ResourceTest extends \PHPUnit_Framework_TestCase
{
    /** @var Resource */
    protected $module;

    public function setUp()
    {
        $fileManager = new FileManager();
        $this->module = new Resource($fileManager);
        Resource::$excludeFolders = ['*/tests/cache/*'];
        Resource::$projectRoot = __DIR__ . '/';
        Resource::$webRoot = __DIR__ . '/www/';
        Resource::$cacheRoot = __DIR__ . '/cache/';

        if ($fileManager->exists(Resource::$cacheRoot)) {
            $fileManager->remove(Resource::$cacheRoot);
        }
    }

    public function testManage()
    {
        // Run first time to generate assets
        $this->module->manage([__DIR__]);
        // Run second time to use cache
        $this->module->manage([__DIR__]);
    }
}
