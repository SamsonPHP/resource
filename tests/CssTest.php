<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>.
 * on 20.02.16 at 14:39
 */
namespace samsonphp\less\tests;

use samsonphp\resource\CSS;
use samsonphp\resource\ResourceValidator;

class CssTest extends \PHPUnit_Framework_TestCase
{
    /** @var CSS */
    protected $css;
    /** @var array Collection of assets */
    protected $files = [];

    public function setUp()
    {
        $this->css = new CSS();

        ResourceValidator::$projectRoot = __DIR__ . '/';
        ResourceValidator::$webRoot = __DIR__ . '/www/';

        $resourcePath = __DIR__ . '/test.jpg';
        file_put_contents($resourcePath, '/** TEST */');
    }

    public function testCompile()
    {
        $css = '.class { url("tests/test.jpg"); }';

        $this->css->compile('test.css', 'css', $css);

        $this->assertEquals('.class { url("/resourcer/?p=test.jpg"); }', $css);
    }

    public function testCompileWithGet()
    {
        $css = '.class { url("tests/test.jpg?v=1.0"); }';

        $this->css->compile('test.css', 'css', $css);

        $this->assertEquals('.class { url("/resourcer/?p=test.jpg"); }', $css);
    }

    public function testCompileWithHash()
    {
        $css = '.class { url("tests/test.jpg#v=1.0"); }';

        $this->css->compile('test.css', 'css', $css);

        $this->assertEquals('.class { url("/resourcer/?p=test.jpg"); }', $css);
    }

    public function testCompileWithDataUri()
    {
        $css = '.class { url("data/jpeg;base64 kdFSDfsdjfnskdnfksdnfksdf"); }';

        $this->css->compile('test.css', 'css', $css);

        $this->assertEquals('.class { url("data/jpeg;base64 kdFSDfsdjfnskdnfksdnfksdf"); }', $css);
    }

    public function testCompileWithResourceNotFound()
    {
        $this->setExpectedException(\samsonphp\resource\exception\ResourceNotFound::class);

        $css = '.class { url("tests/test-tset.jpg#v=1.0"); }';

        $this->css->compile('test.css', 'css', $css);

        $this->assertEquals('.class { url("/resourcer/?p=test.jpg"); }', $css);
    }
}
