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

    /** Collection of registered resource types */
    protected $types = ['less', 'css', 'js', 'coffee', 'ts'];

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

    /** Identifier */
    protected $id = STATIC_RESOURCE_HANDLER;

    /** @see ModuleConnector::init() */
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

        // Subscribe to core template rendering event
        Event::subscribe('core.rendered', [$this, 'renderTemplate']);
    }

    private function getAssetPathData($resource, $extension = null)
    {
        $extension = $extension === null ? pathinfo($resource, PATHINFO_EXTENSION) : $extension;
        switch ($extension) {
            case 'css':
            case 'less':
            case 'scss':
            case 'sass': $extension = 'css'; break;
            case 'ts':
            case 'cofee': $extension = 'js'; break;
        }

        $wwwRoot = getcwd();
        $projectRoot = dirname($wwwRoot).'/';
        $relativePath = str_replace($projectRoot, '', $resource);

        $fileName = pathinfo($resource, PATHINFO_FILENAME);

        return dirname($this->cache_path.$relativePath).'/'.$fileName.'.'.$extension;
    }

    /**
     * Get path static resources list filtered by extensions.
     *
     * @param array $paths Paths for static resources scanning
     * @param array $extensions Resource type
     *
     * @return array Matched static resources collection with full paths
     */
    protected function scanFolderRecursively(array $paths, $extensions)
    {
        // TODO: Handle not supported cmd command(Windows)
        // TODO: Handle not supported exec()

        // Generate LINUX command to gather resources as this is 20 times faster
        $files = [];

        $excludeFolders = implode(' ', array_map(function ($value) {
            return '-not -path ' . $value.' ';
        }, self::EXCLUDING_FOLDERS));

        // Get first type
        $firstType = array_shift($extensions);

        // Generate other types
        $types = implode(' ', array_map(function ($value) use ($excludeFolders){
            return '-o -name "*.' . $value . '" '.$excludeFolders;
        }, $extensions));

        $command = 'find ' . implode(' ', $paths) . ' -type f -name "*.' . $firstType . '" '.$excludeFolders.$types;

        // Scan path excluding folder patterns
        exec($command, $files);

        // TODO: Why some paths have double slashes? Investigate speed of realpath, maybe // changing if quicker
        return array_map('realpath', $files);
    }

    /**
     * Create static assets.
     *
     * @param array  $files Collection of paths for gathering resources
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
