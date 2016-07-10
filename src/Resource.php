<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>.
 * on 15.05.16 at 10:52
 */
namespace samsonphp\resource;

use samsonphp\event\Event;
use samsonphp\resource\exception\ResourceNotFound;

/**
 * Resource assets management class.
 *
 * @package samsonphp\resource
 */
class Resource
{
    /** Event for resources analyzing */
    const E_ANALYZE = 'resource.analyze';
    /** Event for resources compiling */
    const E_COMPILE = 'resource.compile';

    /** Assets types */
    const T_CSS = 'css';
    const T_LESS = 'less';
    const T_SCSS = 'scss';
    const T_SASS = 'sass';
    const T_JS = 'js';
    const T_TS = 'ts';
    const T_COFFEE = 'coffee';
    const T_JPG = 'jpg';
    const T_JPEG = 'jpeg';
    const T_PNG = 'png';
    const T_GIF = 'gif';
    const T_SVG = 'svg';
    const T_TTF = 'ttf';
    const T_WOFF = 'woff';
    const T_WOFF2 = 'woff2';
    const T_EOT = 'eot';

    /** Assets types collection */
    const TYPES = [
        self::T_CSS,
        self::T_LESS,
        self::T_SCSS,
        self::T_SASS,
        self::T_JS,
        self::T_TS,
        self::T_COFFEE,
        self::T_JPG,
        self::T_JPEG,
        self::T_PNG,
        self::T_GIF,
        self::T_SVG,
        self::T_TTF,
        self::T_WOFF,
        self::T_WOFF2,
        self::T_EOT,
    ];

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

    /** Collection of excluding scanning folder patterns */
    public static $excludeFolders = [
        '*/cache/*',
        '*/tests/*',
        '*/vendor/*/vendor/*'
    ];

    /** @var string Full path to project web root directory */
    public static $webRoot;

    /** @var string Full path to project root directory */
    public static $projectRoot;

    /** @var string Full path to project cache root directory */
    public static $cacheRoot;

    /** @var array Cached assets */
    protected $cache = [];

    /** @var array Collection of assets */
    protected $assets = [];

    /**
     * Resource constructor.
     *
     * @param FileManagerInterface $fileManager File managing class
     */
    public function __construct(FileManagerInterface $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    /**
     * Build relative path to static resource relatively to web root path.
     *
     * @param string $relativePath Relative path to static resource
     * @param string $parentPath   Path to parent entity
     *
     * @return string Validated relative path to static resource relatively to web root path
     * @throws ResourceNotFound
     */
    public static function getWebRelativePath($relativePath, $parentPath = '')
    {
        return static::getRelativePath($relativePath, $parentPath, static::$webRoot);
    }

    /**
     * Build correct relative path to static resource using relative path and parent path.
     *
     * @param string $relativePath Relative path to static resource
     * @param string $parentPath   Path to parent entity
     * @param string $rootPath     Root path for relative path building
     *
     * @return string Validated relative path to static resource
     * @throws ResourceNotFound
     */
    public static function getRelativePath($relativePath, $parentPath = '', $rootPath = '')
    {
        // If parent path if not passed - use project root path
        $parentPath = $parentPath === '' ? static::$projectRoot : $parentPath;

        // Build full path to resource from given relative path
        $fullPath = rtrim($parentPath, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .ltrim($relativePath, DIRECTORY_SEPARATOR);

        // Make real path with out possible "../"
        $realPath = realpath($fullPath);

        // Output link to static resource handler with relative path to project root
        if ($realPath) {
            // Build relative path to static resource
            return str_replace($rootPath, '', $realPath);
        }

        throw new ResourceNotFound($fullPath);
    }

    /**
     * Build relative path to static resource relatively to project root path.
     *
     * @param string $relativePath Relative path to static resource
     * @param string $parentPath   Path to parent entity
     *
     * @return string Validated relative path to static resource relatively to project root path
     * @throws ResourceNotFound
     */
    public static function getProjectRelativePath($relativePath, $parentPath = '')
    {
        return static::getRelativePath($relativePath, $parentPath, static::$projectRoot);
    }

    /**
     * Create static assets.
     *
     * @param array $paths Collection of paths for gathering assets
     *
     * @return array Cached assets full paths collection
     */
    public function manage(array $paths)
    {
        $assets = $this->fileManager->scan($paths, self::TYPES);

        // Iterate all assets for analyzing
        $cache = [];
        foreach ($assets as $asset) {
            $cache[$asset] = $this->analyzeAsset($asset);
        }

        // Iterate invalid assets
        foreach ($cache as $file => $content) {
            $extension = pathinfo($file, PATHINFO_EXTENSION);

            // Compile content
            $compiled = $content;
            Event::fire(self::E_COMPILE, [$file, &$extension, &$compiled]);

            $asset = $this->getAssetCachedPath($file);
            $this->fileManager->write($asset, $compiled);
            $this->fileManager->touch($asset, $this->fileManager->lastModified($file));

            $this->assets[$extension][] = $asset;
        }

        return $this->assets;
    }

    /**
     * Analyze asset.
     *
     * @param string $asset Full path to asset
     *
     * @return string Analyzed asset content
     */
    protected function analyzeAsset($asset)
    {
        // Generate cached resource path with possible new extension after compiling
        $cachedAsset = $this->getAssetCachedPath($asset);

        $extension = pathinfo($asset, PATHINFO_EXTENSION);

        // If cached assets was modified or new
        if (!$this->isValid($asset, $cachedAsset)) {
            // Read asset content
            $content = $this->fileManager->read($asset);

            // Fire event for analyzing resource
            Event::fire(self::E_ANALYZE, [
                $asset,
                $extension,
                &$content
            ]);

            return $content;
        } else {
            // Add this resource to resource collection grouped by resource type
            $this->assets[$extension][] = $cachedAsset;
        }

        return '';
    }

    /**
     * Get asset cached path with extension conversion.
     *
     * @param string $asset Asset full path
     *
     * @return string Full path to cached asset
     */
    protected function getAssetCachedPath($asset)
    {
        // Convert input extension
        $extension = pathinfo($asset, PATHINFO_EXTENSION);
        $extension = array_key_exists($extension, self::CONVERTER) ? self::CONVERTER[$extension] : $extension;

        // Build asset project root relative path
        $relativePath = str_replace(self::$projectRoot, '', $asset);

        // Build full cached asset path
        return dirname(self::$cacheRoot . $relativePath) . '/' . pathinfo($asset, PATHINFO_FILENAME) . '.' . $extension;
    }

    /**
     * Define if asset is not valid.
     *
     * @param string $asset       Full path to asset
     *
     * @param string $cachedAsset Full path to cached asset
     *
     * @return bool True if cached asset is valid
     */
    protected function isValid($asset, $cachedAsset)
    {
        // If cached asset does not exists or is invalid
        return $this->fileManager->exists($cachedAsset) === false
        || $this->fileManager->lastModified($cachedAsset) !== $this->fileManager->lastModified($asset);
    }

    /**
     * Get cached asset URL.
     *
     * @param string $cachedAsset Full path to cached asset
     *
     * @return mixed Cached asset URL
     */
    protected function getAssetCachedUrl($cachedAsset)
    {
        return str_replace(self::$webRoot, '', $cachedAsset);
    }
}
