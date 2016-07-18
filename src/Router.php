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
    const E_RESOURCE_PRELOAD = ResourceManager::E_ANALYZE;
    /** Event for resources compiling */
    const E_RESOURCE_COMPILE = ResourceManager::E_COMPILE;
    /** Event when recourse management is finished */
    const E_FINISHED = 'resourcer.finished';

    /** @deprecated Identifier */
    protected $id = STATIC_RESOURCE_HANDLER;

    /** @var array Template markers for inserting assets */
    protected $templateMarkers = ['css' => '</head>', 'js' => '</body>'];

    /** @var array Collection of static resources */
    protected $resources = [];

    /** @var array Collection of static resource URLs */
    protected $resourceUrls = [];

    /**
     * Module initialization stage.
     *
     * @see ModuleConnector::init()
     *
     * @param array $params Initialization parameters
     *
     * @return bool True if resource successfully initialized
     */
    public function init(array $params = array())
    {
        // Subscribe for CSS handling
        Event::subscribe(self::E_RESOURCE_COMPILE, [new CSS(), 'compile']);

        // Subscribe to core template rendering event
        Event::subscribe('core.rendered', [$this, 'renderTemplate']);

        $resourceManager = new ResourceManager(new FileManager());
        $resourceManager::$cacheRoot = $this->cache_path;
        $resourceManager::$webRoot = getcwd();
        $resourceManager::$projectRoot = dirname($resourceManager::$webRoot) . '/';

        // Get loaded modules
        $moduleList = $this->system->module_stack;

        // Event for modification of resource list
        Event::fire(self::E_MODULES, [&$moduleList]);

        $appResourcePaths = $this->getAssets($moduleList);

        // Get assets
        $this->resources = $resourceManager->manage($appResourcePaths);

        // Fire completion event
        Event::fire(self::E_FINISHED, [&$this->resources]);

        // Get asset URLs
        $this->resourceUrls = array_map([$this, 'getAssetCachedUrl'], $this->resources);

        // Continue parent initialization
        return parent::init($params);
    }

    /**
     * Get asset files from modules.
     *
     * @param array $moduleList Collection of modules
     *
     * @return array Resources paths
     */
    public function getAssets($moduleList)
    {
        $projectRoot = dirname(getcwd()) . '/';

        // Add resource paths
        $paths = [];
        foreach ($moduleList as $module) {
            /**
             * We need to exclude project root because vendor folder will be scanned
             * and all assets from there would be added.
             */
            if ($module->path() !== $projectRoot) {
                $paths[] = $module->path();
            }
        }

        // Add web-root as last path
        $paths[] = getcwd();

        return $paths;
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
            if (array_key_exists($type, $this->templateMarkers)) {
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
        }

        return $view;
    }

    /**
     * Get cached asset URL.
     *
     * @param string $cachedAsset Full path to cached asset
     *
     * @return mixed Cached asset URL
     */
    private function getAssetCachedUrl($cachedAsset)
    {
        return str_replace(ResourceManager::$webRoot, '', $cachedAsset);
    }
}
