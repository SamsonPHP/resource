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
    /** @deprecated Use E_MODULES */
    const EVENT_START_GENERATE_RESOURCES = 'resourcer.modulelist';
    /** Event for modifying modules */
    const E_MODULES = 'resourcer.modulelist';
    /** Event for resources preloading */
    const E_RESOURCE_PRELOAD = 'resourcer.preload';
    /** Event for resources compiling */
    const E_RESOURCE_COMPILE = 'resourcer.compile';

    /** Collection of excluding scanning folder patterns */
    const EXCLUDING_FOLDERS = [
        '*/cache/*',
        '*/tests/*',
        '*/vendor/*/vendor/*'
    ];

    /** Collection of registered resource types */
    public $types = ['css', 'less', 'js', 'coffee', 'ts'];

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

    /** @see ModuleConnector::init() */
    public function init(array $params = array())
    {
        // Subscribe for CSS handling
        Event::subscribe(self::E_RESOURCE_COMPILE, [new CSS(), 'compile']);

        $moduleList = $this->system->module_stack;
        $projectRoot = dirname(getcwd()).'/';
        $paths = [$projectRoot.'www/'];

        // Event for modification of module list
        Event::fire(self::E_MODULES, array(&$moduleList));

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

        // Subscribe to core template rendering event
        Event::subscribe('core.rendered', [$this, 'renderTemplate']);
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
            $files = array_filter(array_merge($this->scanFolderRecursively($path, $type), $files));
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
}
