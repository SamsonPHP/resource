<?php
namespace samsonphp\resource;

use samson\core\ExternalModule;
use samsonphp\event\Event;

/**
 * Resource router for serving static resource from unreachable web-root paths.
 *
 * TODO: Validate old files that do not exists anymore to remove them
 *
 * @author Vitaly Iegorov <egorov@samsonos.com>
 */
class Router extends ExternalModule
{
    /** @deprecated Use E_MODULES */
    const EVENT_START_GENERATE_RESOURCES = 'resourcer.modulelist';
    /** Event for modifying modules */
    const E_MODULES = 'resourcer.modulelist';
    /** Event for resources preloading */
    const E_RESOURCE_PRELOAD = 'resourcer.preload';
    /** Event for resources compiling */
    const E_RESOURCE_COMPILE = 'resourcer.compile';
    /** Event when recourse management is finished */
    const E_FINISHED = 'resourcer.finished';

    /** Assets types */
    const T_CSS = 'css';
    const T_LESS = 'less';
    const T_SCSS = 'scss';
    const T_SASS = 'sass';
    const T_JS = 'js';
    const T_TS = 'ts';
    const T_COFFEE = 'coffee';

    /** Assets converter */
    const CONVERTER = [
        self::T_JS => self::T_JS,
        self::T_TS => self::T_JS,
        self::T_COFFEE => self::T_JS,
        self::T_CSS => self::T_CSS,
        self::T_LESS => self::T_CSS,
        self::T_SCSS => self::T_CSS,
        self::T_SASS => self::T_CSS,
    ];

    /** @deprecated Identifier */
    protected $id = STATIC_RESOURCE_HANDLER;

    /** Collection of registered resource types */
    protected $types = [
        self::T_CSS,
        self::T_JS,
        self::T_LESS,
        self::T_SCSS,
        self::T_SASS,
        self::T_COFFEE,
        self::T_TS
    ];

    /** @var array Assets cache */
    protected $cache = [];

    /** @var array Template markers for inserting assets */
    protected $templateMarkers = [
        'css' => '</head>',
        'js' => '</body>'
    ];
    /** @var array Collection of static resources */
    protected $resources = [];

    /** @var array Collection of static resource URLs */
    protected $resourceUrls = [];

    /**
     * @see ModuleConnector::init()
     *
     * @param array $params Initialization parameters
     *
     * @return bool True if module successfully initialized
     */
    public function init(array $params = array())
    {
        // Subscribe for CSS handling
        Event::subscribe(self::E_RESOURCE_COMPILE, [new CSS(), 'compile']);

        $moduleList = $this->system->module_stack;
        $paths = [];

        // Event for modification of module list
        Event::fire(self::E_MODULES, array(&$moduleList));

        $projectRoot = dirname(getcwd()).'/';

        // Add module paths
        foreach ($moduleList as $module) {
            if ($module->path() !== $projectRoot) {
                $paths[] = $module->path();
            }
        }
        $paths[] = getcwd();

        $files = Resource::scan($paths, $this->types);

        $this->createAssets($files);

        // Fire completion event
        Event::fire(self::E_FINISHED);

        // Subscribe to core template rendering event
        Event::subscribe('core.rendered', [$this, 'renderTemplate']);

        return parent::init($params);
    }

    /**
     * Create static assets.
     *
     * @param array $files Collection of paths for gathering resources
     */
    public function createAssets(array $files)
    {
        $wwwRoot = getcwd();

        $assets = [];

        // Scan folder and gather
        foreach ($files as $file) {
            // Generate cached resource path with possible new extension after compiling
            $assets[$file] = $this->getAssetPathData($file);
            $extension = pathinfo($assets[$file], PATHINFO_EXTENSION);

            // If cached assets was modified or new
            if (!file_exists($assets[$file]) || filemtime($file) !== filemtime($assets[$file])) {
                // Read asset content
                $this->cache[$file] = file_get_contents($file);

                // Fire event for analyzing resource
                Event::fire(self::E_RESOURCE_PRELOAD, [$file, pathinfo($file, PATHINFO_EXTENSION), &$this->cache[$file]]);
            } else {
                // Add this resource to resource collection grouped by resource type
                $this->resources[$extension][] = $assets[$file];
                $this->resourceUrls[$extension][] = str_replace($wwwRoot, '', $assets[$file]);
            }
        }

        $wwwRoot = getcwd();
        foreach ($this->cache as $file => $content) {
            $extension = pathinfo($file, PATHINFO_EXTENSION);

            $compiled = $content;
            Event::fire(self::E_RESOURCE_COMPILE, [$file, &$extension, &$compiled]);

            // Create folder structure and file only if it is not empty
            $resource = $this->getAssetPathData($file, $extension);

            // Create cache path
            $path = dirname($resource);
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }

            file_put_contents($resource, $compiled);

            // Sync cached file with source file
            touch($resource, filemtime($file));

            // Add this resource to resource collection grouped by resource type
            $this->resources[$extension][] = $resource;
            $this->resourceUrls[$extension][] = str_replace($wwwRoot, '', $resource);
        }
    }

    private function getAssetPathData($resource, $extension = null)
    {
        // Convert input extension
        $extension = self::CONVERTER[$extension === null
            ? pathinfo($resource, PATHINFO_EXTENSION)
            : $extension];

        $wwwRoot = getcwd();
        $projectRoot = dirname($wwwRoot).'/';
        $relativePath = str_replace($projectRoot, '', $resource);

        $fileName = pathinfo($resource, PATHINFO_FILENAME);

        return dirname($this->cache_path.$relativePath).'/'.$fileName.'.'.$extension;
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
                implode("\n", array_map(function ($value) use ($type) {
                    if ($type === 'css') {
                        return '<link type="text/css" rel="stylesheet" property="stylesheet" href="' . $value . '">';
                    } elseif ($type === 'js') {
                        return '<script type="text/javascript" src="' . $value . '"></script>';
                    }
                }, $urls)) . "\n" . $this->templateMarkers[$type],
                $view
            );
        }

        return $view;
    }
}
