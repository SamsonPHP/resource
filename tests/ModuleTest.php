<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>.
 * on 20.02.16 at 14:39
 */
namespace samsonphp\less\tests;

use samson\core\Core;
use samsonframework\resource\ResourceMap;
use samsonphp\event\Event;
use samsonphp\less\Module;
use samsonphp\resource\ResourceManager;
use samsonphp\resource\Router;

// Include framework constants
require('vendor/samsonos/php_core/src/constants.php');
require('vendor/samsonos/php_core/src/Utils2.php');

class ModuleTest extends \PHPUnit_Framework_TestCase
{
    /** @var Router */
    protected $module;

    public function setUp()
    {
        $map = new ResourceMap(__DIR__);
        $core = new Core($map);
        $this->module = new Router(__DIR__, $map, $core);
        $this->module->prepare();
    }

    public function testInitAndTemplateRender()
    {
        $files = [
            ResourceManager::T_JS => [__DIR__ . '/test.js'],
            ResourceManager::T_CSS => [__DIR__ . '/test.css'],
        ];

        Event::subscribe(Router::E_FINISHED, function (&$resources) use ($files) {
            $resources = $files;
        });

        $this->module->init([]);

        $view = '<body><head></head></body>';
        $this->module->renderTemplate($view);
        $this->assertEquals('<body><head><link type="text/css" rel="stylesheet" property="stylesheet" href="/tests/test.css">
</head><script type="text/javascript" src="/tests/test.js"></script>
</body>', $view);
    }
}
