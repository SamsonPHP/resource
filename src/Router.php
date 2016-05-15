<?php
namespace samsonphp\resource;

use samson\core\ExternalModule;
use samson\core\iModule;
use samsonphp\event\Event;
use samsonphp\resource\exception\ResourceNotFound;

/**
 * Класс для определения, построения и поиска путей к ресурсам
 * системы. Класс предназначен для формирования УНИКАЛЬНЫХ URL
 * описывающих путь к ресурсу веб-приложения/модуля независимо
 * от его расположения на HDD.
 *
 * Создавая возможность один рас описать путь вида:
 *    ИМЯ_РЕСУРСА - ИМЯ_ВЕБПРИЛОЖЕНИЯ - ИМЯ_МОДУЛЯ
 *
 * И больше не задумываться об реальном(физическом) местоположении
 * ресурса
 *
 * @package SamsonPHP
 * @author Vitaly Iegorov <vitalyiegorov@gmail.com>
 * @author Nikita Kotenko <nick.w2r@gmail.com>
 * @version 1.0
 */
class Router extends ExternalModule
{
    /** Event showing that new gather resource file was created */
    const EVENT_CREATED = 'resource.created';

    /** Event showing that new gather resource file was created */
    const EVENT_START_GENERATE_RESOURCES = 'resource.start.generate.resources';

    /** Identifier */
    protected $id = STATIC_RESOURCE_HANDLER;

    /** @var string Marker for inserting generated javascript link */
    public $javascriptMarker = '</body>';

    /** Cached resources path collection */
    public $cached = array();

    /** Collection of updated cached resources for notification of changes */
    public $updated = array();

    /** Pointer to processing module */
    private $c_module;

    /** @var string Current processed resource */
    private $cResource;


    /** @see ModuleConnector::init() */
    public function init(array $params = array())
    {
        parent::init($params);

        $moduleList = $this->system->module_stack;

        Event::fire(self::EVENT_START_GENERATE_RESOURCES, array(&$moduleList));

        $this->generateResources($moduleList);

        // Subscribe to core rendered event
        $this->system->subscribe('core.rendered', array($this, 'renderer'));
    }

    public function generateResources($moduleList, $templatePath = 'default')
    {
        // Cache main web resources
        foreach (array(array('js'), array('css', 'less'), array('coffee')) as $rts) {
            // Get first resource type as extension
            $rt = $rts[0];

            $hash_name = '';

            // Iterate gathered namespaces for their resources
            /** @var Module $module */
            foreach ($moduleList as $id => $module) {
                // If necessary resources has been collected
                foreach ($rts as $_rt) {
                    if (isset($module->resourceMap->$_rt)) {
                        foreach ($module->resourceMap->$_rt as $resource) {
                            // Created string with last resource modification time
                            $hash_name .= filemtime($resource);
                        }
                    }
                }
            }

            // Get hash that's describes resource status
            $hash_name = md5($hash_name) . '.' . $rt;

            $file = $hash_name;

            $dir = str_replace(array('/', '.'), '_', $templatePath);

            // If cached file does not exists
            if ($this->cache_refresh($file, true, $dir)) {
                // Read content of resource files
                $content = '';
                foreach ($moduleList as $id => $module) {
                    $this->c_module = $module;
                    // If this ns has resources of specified type
                    foreach ($rts as $_rt) {
                        if (isset($module->resourceMap->$_rt)) {
                            //TODO: If you will remove & from iterator - system will fail at last element
                            foreach ($module->resourceMap->$_rt as $resource) {
                                // Store current processing resource
                                $this->cResource = $resource;
                                // Read resource file
                                $c = file_get_contents($resource);
                                // Rewrite url in css
                                if ($rt == 'css') {
                                    $c = preg_replace_callback('/url\s*\(\s*(\'|\")?([^\)\s\'\"]+)(\'|\")?\s*\)/i',
                                        array($this, 'src_replace_callback'), $c);
                                }
                                // Gather processed resource text together
                                $content .= "\n\r" . $c;
                            }
                        }
                    }
                }

                // Fire event that new resource has been generated
                Event::fire(self::EVENT_CREATED, array($rt, &$content, &$file, &$this));

                // Fix updated resource file with new path to it
                $this->updated[$rt] = $file;

                // Запишем содержание нового "собранного" ресурса
                file_put_contents($file, $content);
            }

            // Save path to resource cache
            $this->cached[$rt][$templatePath] = __SAMSON_CACHE_PATH . $this->id . '/' . $dir . '/' . $hash_name;
        }
    }

    /**
     * Core render handler for including CSS and JS resources to html
     *
     * @param sting $view View content
     * @param array $data View data
     *
     * @return string Processed view content
     */
    public function renderer(&$view, $data = array(), iModule $m = null)
    {
        $tempateId = isset($this->cached['css'][$this->system->template()]) ? $this->system->template() : 'default';

        // Define resource urls
        $css = url()->base() . str_replace(__SAMSON_PUBLIC_PATH, '', $this->cached['css'][$tempateId]);
        $js = url()->base() . str_replace(__SAMSON_PUBLIC_PATH, '', $this->cached['js'][$tempateId]);

        // TODO: Прорисовка зависит от текущего модуля, сделать єто через параметр прорисовщика
        // If called from compressor
        if ($m->id() == 'compressor') {
            $tempateId = isset($this->cached['css'][$data['file']]) ? $data['file'] : 'default';
            $css = url()->base() . basename($this->cached['css'][$tempateId]);
            $js = url()->base() . basename($this->cached['js'][$tempateId]);
        }

        // Put css link at the end of <head> page block
        $view = str_ireplace('</head>',
            "\n" . '<link type="text/css" rel="stylesheet" href="' . $css . '">' . "\n" . '</head>', $view);

        // Put javascript link in the end of the document
        $view = str_ireplace($this->javascriptMarker,
            "\n" . '<script type="text/javascript" src="' . $js . '"></script>' . "\n" . $this->javascriptMarker,
            $view);

        //elapsed('Rendering view =)');

        return $view;
    }

    /** Callback for CSS url rewriting */
    public function src_replace_callback($matches)
    {
        // If we have found static resource path definition and its not inline
        if (array_key_exists(2, $matches) && strpos($matches[2], 'data:') === false) {
            // Store static resource path
            $url = $matches[2];

            // Ignore preprocessor vars
            // TODO: This is totally wrong need to come up with decision
            if (strpos($url, '@') !== false) {
                return $matches[0];
            }

            // Remove possible GET parameters from resource path
            if (($getStart = stripos($url, '?')) !== false) {
                $url = substr($url, 0, $getStart);
            }

            // Remove possible HASH parameters from resource path
            if (($getStart = stripos($url, '#')) !== false) {
                $url = substr($url, 0, $getStart);
            }

            // Build path to static resource relatively to current resource file
            $buildPath = dirname($this->cResource) . DIRECTORY_SEPARATOR . $url;

            $realPath = realpath($buildPath);

            // We have found static resource path
            if ($realPath !== false) {
                // Make static resource path relative to web-project root
                $url = str_replace(dirname(getcwd()), '', $realPath);

                // Build path to static resource handler
                return 'url("/' . $this->id . '/?p=' . $url . '")';
            } else {
                throw new ResourceNotFound($buildPath . ' in ' . $this->cResource);
            }
        }

        return $matches[0];
    }
}
