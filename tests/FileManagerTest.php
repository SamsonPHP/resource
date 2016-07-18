<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>.
 * on 20.02.16 at 14:39
 */
namespace samsonphp\less\tests;

use PHPUnit\Framework\TestCase;
use samsonphp\resource\FileManager;

class FileManagerTest extends TestCase
{
    /** @var FileManager */
    protected $manager;
    /** @var array Collection of assets */
    protected $files = [];

    public function setUp()
    {
        $this->manager = new FileManager();
    }

    public function testReadWriteMkDirWithRemove()
    {
        $content = 'test';
        $path = __DIR__ . '/test/';
        $filePath = $path . 'test.txt';
        $this->manager->write($filePath, $content);

        $this->assertEquals($content, $this->manager->read($filePath));

        $this->manager->remove($path);
        $this->assertEquals(false, $this->manager->exists($path));
    }

    public function testMkDirWithException()
    {
        $this->setExpectedException(\Exception::class);

        $path = '/test/test.txt';
        $this->manager->mkdir($path);
    }

    public function testMkDirWhenExists()
    {
        $path = __DIR__;
        $this->manager->mkdir($path);
    }

    public function testTouchLastmodified()
    {
        $content = 'test';
        $path = __DIR__ . '/test/';
        $filePath = $path . 'test.txt';
        $this->manager->write($filePath, $content);

        $timestamp = time();
        $this->manager->touch($filePath, $timestamp);

        $this->assertEquals($this->manager->lastModified($filePath), $timestamp);
    }

    public function testScan()
    {
        $content = 'test';
        $path = __DIR__ . '/test/';
        $filePath = $path . 'test.txt';

        $this->manager->write($filePath, $content);

        $this->assertEquals($filePath, $this->manager->scan([$path], ['txt'], [__DIR__ . '/folder/'])[0]);
    }

    /**
     * public function mkdir($path);
     *
     * public function exists($path);
     *
     * public function touch($asset, $timestamp);
     *
     * public function remove($path);
     *
     * public function lastModified($file);
     *
     *
     * public function scan(array $paths, array $extensions, array $excludeFolders = []);
     * */
}
