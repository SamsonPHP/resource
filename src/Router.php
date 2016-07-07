<?php
namespace samsonphp\resource;

use Aws\CloudFront\Exception\Exception;
use samson\core\ExternalModule;
use samson\core\Module;
use samsonframework\resource\ResourceMap;
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
    /** Event for resources preloading */
    const E_RESOURCE_PRELOAD = 'resourcer.preload';
    /** Event for resources compiling */
    const E_RESOURCE_COMPILE = 'resourcer.compile';

    /** Collection of excluding scanning folder patterns */
    const EXCLUDING_FOLDERS = [
        '*/cache/*',
        '*/vendor/*/vendor/*'
    ];

    /** @var string Marker for inserting generated JS link in template */
    public $jsMarker = '</body>';
    /** @var string Marker for inserting generated CSS link in template */
    public $cssMarker = '</head>';
    /** Cached resources path collection */
    public $cached = array();
    /** Collection of updated cached resources for notification of changes */
    public $updated = array();

    /** Collection of registered resource types */
    public $types = [];

    /** @var array Template markers for inserting assets */
    protected $templateMarkers = [
        'css' => '</head>',
        'js' => '</body>'
    ];

    /** @var array Collection of static resources */
    protected $resources = [];
    /** @var array Collection of static resource URLs */
    protected $resourceUrls = [];

    /** Identifier */
    protected $id = STATIC_RESOURCE_HANDLER;

    /** Pointer to processing module */
    private $currentModule;
    /** @var string Current processed resource */
    private $currentResource;

    /**
     * Register resource extension handling.
     *
     * @param string $type Resource extension
     */
    public function registerResourceType($type)
    {
        $this->types[] = $type;
    }

    /**
     * Parse URL to get module name and relative path to resource
     *
     * @param string $url String for parsing
     * @deprecated
     *
     * @return array Array [0] => module name, [1]=>relative_path
     */
    public static function parseURL($url, &$module = null, &$path = null)
    {
        // If we have URL to resource router
        if (preg_match('/'.STATIC_RESOURCE_HANDLER.'\/\?p=(((\/src\/|\/vendor\/samson[^\/]+\/)(?<module>[^\/]+)(?<path>.+))|((?<local>.+)))/ui', $url, $matches)) {
            if (array_key_exists('local', $matches)) {
                $module = 'local';
                $path = $matches['local'];
            } else {
                $module = $matches['module'];
                $path = $matches['path'];
            }
            return true;
        } else {
            return false;
        }
    }

    /** @see ModuleConnector::init() */
    public function init(array $params = array())
    {
        $this->registerResourceType('css');
        $this->registerResourceType('js');

        // Event for modification of module list
        Event::fire(self::EVENT_START_GENERATE_RESOURCES, array(&$moduleList));

        $moduleList = $this->system->module_stack;
        $projectRoot = dirname(getcwd()).'/';
        $paths = [$projectRoot.'www/'];

        // Add module paths
        foreach ($moduleList as $module) {
            if ($module->path() !== $projectRoot) {
                $paths[] = $module->path();
            }
        }

        // Iterate all types of assets
        foreach ($this->types as $type) {
            $this->createAssets($paths, $type);
        }

        //$this->generateResources($moduleList);

        // Subscribe to core template rendering event
        Event::subscribe('core.rendered', [$this, 'renderTemplate']);

        // Subscribe to core rendered event
        //$this->system->subscribe('core.rendered', array($this, 'renderer'));
    }

    /**
     * Get path static resources list filtered by extensions.
     *
     * @param string $path Path for static resources scanning
     * @param string $extension Resource type
     *
     * @return array Matched static resources collection with full paths
     */
    protected function scanFolderRecursively($path, $extension)
    {
        // TODO: Handle not supported cmd command(Windows)
        // TODO: Handle not supported exec()

        // Generate LINUX command to gather resources as this is 20 times faster
        $files = [];

        // Scan path excluding folder patterns
        exec(
            'find ' . $path . ' -type f -name "*.' . $extension . '" '.implode(' ', array_map(function ($value) {
                return '-not -path ' . $value;
            }, self::EXCLUDING_FOLDERS)),
            $files
        );

        // TODO: Why some paths have double slashes? Investigate speed of realpath, maybe // changing if quicker
        return array_map('realpath', $files);
    }

    /**
     * Create static assets.
     *
     * @param array  $paths Collection of paths for gatherin resources
     * @param string $type Resource extension
     */
    public function createAssets(array $paths, $type)
    {
        // Gather all resource files for this type
        $files = [];
        foreach ($paths as $path) {
            $files = array_filter(array_merge($this->scanFolderRecursively($path, 'less'), $files));
        }

        // Create resources timestamps
        $timeStamps = [];
        foreach ($files as $file) {
            $timeStamps[$file] = filemtime($file);
        }

        // Generate resources cache stamp by hashing combined files modification timestamp
        $cacheStamp = md5(implode('', $timeStamps));

        // TODO: We need cache for list of files to check if we need to preload them by storing modified date

        // Here we need to prepare resource - gather LESS variables for example
        foreach ($files as $file) {
            // Fire event for preloading resource
            Event::fire(self::E_RESOURCE_PRELOAD, [$file, pathinfo($file, PATHINFO_EXTENSION)]);
        }

        $wwwRoot = getcwd();
        $projectRoot = dirname($wwwRoot).'/';

        // Here we can compile resources
        foreach ($files as $file) {
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            $fileName = pathinfo($file, PATHINFO_FILENAME);
            $relativePath = str_replace($projectRoot, '', $file);

            // Compiled resource
            $compiled = '';
            Event::fire(self::E_RESOURCE_COMPILE, [$file, &$extension, &$compiled]);

            // Generate cached resource path with possible new extension after compiling
            $resource = dirname($this->cache_path.$relativePath).'/'.$fileName.'.'.$extension;

            // Create folder structure and file only if it is not empty
            if (strlen($compiled)) {
                // Create cache path
                $path = dirname($resource);
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }
                file_put_contents($resource, $compiled);

                // Add this resource to resource collection grouped by resource type
                $this->resources[$extension][] = $resource;
                $this->resourceUrls[$extension][] = str_replace($wwwRoot, '', $resource);
            }
        }
    }

    /**
     * Template rendering handler by injecting static assets url
     * in appropriate.
     *
     * @param $view
     *
     * @return mixed
     */
    public function renderTemplate(&$view)
    {
        foreach ($this->resourceUrls as $type => $urls) {
            // Replace template marker by type with collection of links to resources of this type
            $view = str_ireplace(
                $this->templateMarkers[$type],
                implode("\n", array_map(function($value) use ($type) {
                    if ($type === 'css') {
                        return '<link type="text/css" rel="stylesheet" property="stylesheet" href="' . $value . '">';
                    } elseif ($type === 'js') {
                        return '<script async type="text/javascript" src="' . $value . '"></script>';
                    }
                }, $urls)) . "\n" . $this->templateMarkers[$type],
                $view
            );
        }

        return $view;
    }


    public function generateResources($moduleList, $templatePath = 'default')
    {
        $dir = str_replace(array('/', '.'), '_', $templatePath);

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

            // If cached file does not exists
            if ($this->cache_refresh($file, true, $dir)) {
                // Read content of resource files
                $content = '';
                foreach ($moduleList as $id => $module) {
                    $this->currentModule = $module;
                    // If this ns has resources of specified type
                    foreach ($rts as $_rt) {
                        if (isset($module->resourceMap->$_rt)) {
                            foreach ($module->resourceMap->$_rt as $resource) {
                                // Store current processing resource
                                $this->currentResource = $resource;
                                // Read resource file
                                $c = file_get_contents($resource);
                                // Rewrite url in css
                                if ($rt === 'css') {
                                    $c = preg_replace_callback('/url\s*\(\s*(\'|\")?([^\)\s\'\"]+)(\'|\")?\s*\)/i',
                                        array($this, 'replaceUrlCallback'), $c);
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

                // Create cache file
                file_put_contents($file, $content);
            }

            // Save path to resource cache
            $this->cached[$rt][$templatePath] = __SAMSON_CACHE_PATH . $this->id . '/' . $dir . '/' . $hash_name;
        }
    }



    /**
     * Callback for CSS url(...) rewriting.
     *
     * @param array $matches Regular expression matches collection
     *
     * @return string Rewritten url(..) with static resource handler url
     * @throws ResourceNotFound
     */
    public function replaceUrlCallback($matches)
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
            if (($getStart = strpos($url, '?')) !== false) {
                $url = substr($url, 0, $getStart);
            }

            // Remove possible HASH parameters from resource path
            if (($getStart = strpos($url, '#')) !== false) {
                $url = substr($url, 0, $getStart);
            }

            // Try to find resource and output full error
            try {
                $path = Resource::getProjectRelativePath($url, dirname($this->currentResource));
            } catch (ResourceNotFound $e) {
                throw new ResourceNotFound('Cannot find resource "'.$url.'" in "'.$this->currentResource.'"');
            }

            // Build path to static resource handler
            return 'url("/' . $this->id . '/?p=' . $path . '")';
        }

        return $matches[0];
    }
}
