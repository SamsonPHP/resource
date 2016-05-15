<?php
namespace samsonphp\resource;

use samson\core\ExternalModule;
use samsonphp\event\Event;
use samsonphp\resource\exception\ResourceNotFound;

/**
 * Resource router for serving static resource from unreachable web-root paths.
 *
 * @author Vitaly Iegorov <egorov@samsonos.com>
 * @author Nikita Kotenko <kotenko@samsonos.com>
 */
class Router extends ExternalModule
{
    /** Event showing that new gather resource file was created */
    const EVENT_CREATED = 'resource.created';
    /** Event showing that new gather resource file was created */
    const EVENT_START_GENERATE_RESOURCES = 'resource.start.generate.resources';

    /** @var string Marker for inserting generated JS link in template */
    public $jsMarker = '</body>';
    /** @var string Marker for inserting generated CSS link in template */
    public $cssMarker = '</head>';
    /** Cached resources path collection */
    public $cached = array();
    /** Collection of updated cached resources for notification of changes */
    public $updated = array();

    /** Identifier */
    protected $id = STATIC_RESOURCE_HANDLER;

    /** Pointer to processing module */
    private $currentModule;
    /** @var string Current processed resource */
    private $currentResource;

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
                    $this->currentModule = $module;
                    // If this ns has resources of specified type
                    foreach ($rts as $_rt) {
                        if (isset($module->resourceMap->$_rt)) {
                            //TODO: If you will remove & from iterator - system will fail at last element
                            foreach ($module->resourceMap->$_rt as $resource) {
                                // Store current processing resource
                                $this->currentResource = $resource;
                                // Read resource file
                                $c = file_get_contents($resource);
                                // Rewrite url in css
                                if ($rt === 'css') {
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
     * @param string $view   View content
     * @param array  $data   View data
     * @param null   $module Module instance
     *
     * @return string Processed view content
     */
    public function renderer(&$view, $data = array(), $module = null)
    {
        $templateId = isset($this->cached['css'][$this->system->template()])
            ? $this->system->template()
            : 'default';

        // Define resource urls
        $css = Resource::getWebRelativePath($this->cached['css'][$templateId]);
        $js = Resource::getWebRelativePath($this->cached['js'][$templateId]);

        // TODO: Прорисовка зависит от текущего модуля, сделать єто через параметр прорисовщика
        // If called from compressor
        if ($module->id() === 'compressor') {
            $templateId = isset($this->cached['css'][$data['file']]) ? $data['file'] : 'default';
            $css = url()->base() . basename($this->cached['css'][$templateId]);
            $js = url()->base() . basename($this->cached['js'][$templateId]);
        }

        // Inject resource links
        return $this->injectCSS($this->injectJS($view, $js), $css);
    }

    /**
     * Inject CSS link into view.
     *
     * @param string $view View code
     * @param string $path Resource path
     *
     * @return string Modified view
     */
    protected function injectCSS($view, $path)
    {
        // Put css link at the end of <head> page block
        return str_ireplace(
            $this->cssMarker,
            "\n" . '<link type="text/css" rel="stylesheet" href="' . $path . '">' . "\n" . $this->cssMarker,
            $view
        );
    }

    /**
     * Inject JS link into view.
     *
     * @param string $view View code
     * @param string $path Resource path
     *
     * @return string Modified view
     */
    protected function injectJS($view, $path)
    {
        // Put javascript link in the end of the document
        return str_ireplace(
            $this->jsMarker,
            "\n" . '<script async type="text/javascript" src="' . $path . '"></script>' . "\n" . $this->jsMarker,
            $view
        );
    }

    /**
     * Callback for CSS url(...) rewriting.
     *
     * @param array $matches Regular expression matches collection
     *
     * @return string Rewritten url(..) with static resource handler url
     * @throws ResourceNotFound
     */
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

            // Build path to static resource handler
            return 'url("/' . $this->id . '/?p='
            . Resource::getProjectRelativePath($url, dirname($this->currentResource))
            . '")';
        }

        return $matches[0];
    }
}
