<?php declare(strict_types=1);
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>.
 * on 15.05.16 at 10:52
 */
namespace samsonphp\resource;

use samsonframework\filemanager\FileManagerInterface;
use samsonphp\event\Event;

/**
 * Resource assets management class.
 *
 * @author Vitaly Egorov <egorov@samsonos.com>
 */
class ResourceManager
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
        /*self::T_JPG,
        self::T_JPEG,
        self::T_PNG,
        self::T_GIF,
        self::T_SVG,
        self::T_TTF,
        self::T_WOFF,
        self::T_WOFF2,
        self::T_EOT,*/
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
        '*/vendor/*/vendor/*',
        '*/node_modules/*'
    ];

    /** @var string Full path to project web root directory */
    public static $webRoot;

    /** @var string Full path to project root directory */
    public static $projectRoot;

    /** @var string Full path to project cache root directory */
    public static $cacheRoot;

    /** @var array Collection of assets */
    protected $assets = [];

    /** @var FileManagerInterface */
    protected $fileManager;

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
     * Recursively process asset
     * @param array $dependencies Collection of assets for compilation
     */
    protected function processAsset($dependencies)
    {
        foreach ($dependencies as $source => $nothing) {
            // Read asset content
            $content = $this->fileManager->read($source);

            $extension = pathinfo($source, PATHINFO_EXTENSION);

            // Resource dependant resources
            $innerDependencies = [];

            // Compile content
            $compiled = $content;
            Event::fire(self::E_COMPILE, [$source, &$extension, &$compiled, &$innerDependencies]);

            // Write compiled asset
            $target = $this->getAssetProcessedPath($source);
            $this->fileManager->write($target, $compiled);
            $this->fileManager->touch($target, $this->fileManager->lastModified($source));

            // Go deeper in recursion
            $this->processAsset($innerDependencies);
        }
    }

    /**
     * Create static assets.
     *
     * @param string[] $paths Collection of paths for gathering assets
     *
     * @return string[] Cached assets full paths collection
     */
    public function manage(array $paths)
    {
        $this->assets = [];
        // Iterate all assets for analyzing
        foreach ($this->fileManager->scan($paths, self::TYPES, self::$excludeFolders) as $asset) {
            // Build path to processed asset
            $cachedAsset = $this->getAssetProcessedPath($asset);

            // If cached assets was modified or new
            if (!$this->isValid($asset, $cachedAsset)) {
                // Recursively process asset and possible dependencies
                $this->processAsset([$asset => []]);
            }

            // Store processed asset
            $this->assets[pathinfo($cachedAsset, PATHINFO_EXTENSION)][] = $cachedAsset;
        }

        return $this->assets;
    }

    /**
     * Get asset cached path with extension conversion.
     *
     * @param string $asset Asset full path
     *
     * @return string Full path to cached asset
     */
    protected function getAssetProcessedPath($asset)
    {
        // Build asset project root relative path
        $relativePath = str_replace(self::$projectRoot, '', $asset);

        // Build full cached asset path
        return dirname(self::$cacheRoot . $relativePath) . '/'
        . pathinfo($asset, PATHINFO_FILENAME) . '.' . $this->convertType($asset);
    }

    /**
     * Get asset final type.
     *
     * @param string $asset Full asset path
     *
     * @return string Asset final type
     */
    public function convertType($asset)
    {
        // Convert input extension
        $extension = pathinfo($asset, PATHINFO_EXTENSION);

        return array_key_exists($extension, self::CONVERTER)
            ? self::CONVERTER[$extension]
            : $extension;
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
        return $this->fileManager->exists($cachedAsset) !== false
        && $this->fileManager->lastModified($cachedAsset) === $this->fileManager->lastModified($asset);
    }
}
