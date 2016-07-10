<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>.
 * on 26.04.16 at 10:43
 */

use samsonphp\resource\ResourceManager;

// Define this resource identifier
define('STATIC_RESOURCE_HANDLER', 'resourcer');

// Get current project web root directory
ResourceManager::$webRoot = getcwd();
// Get current project root directory
ResourceManager::$projectRoot = dirname(ResourceManager::$webRoot);

/**
 * Static resource path builder.
 *
 * @deprecated Moving to use new samsonphp/view resource an $this->src() should be used
 *             in view file for generating paths to static resources
 *
 * @param string $path   Relative static resource resource path
 * @param null   $module Entity for path building, if not passed current resource is used
 *
 * @return string Static resource path
 * @throws \samsonphp\resource\exception\ResourceNotFound
 */
function src($path, $module = null)
{
    // Define resource
    switch (gettype($module)) {
        case 'string': // Find resource by identifier
            $module = m($module);
            break;
        case 'object': // Do nothing
            break;
        default: // Get current resource
            $module = m();
    }

    echo '/' . STATIC_RESOURCE_HANDLER . '/?p=' . ResourceManager::getRelativePath($path, $module->path());
}

/** Collection of supported mime types */
$mimeTypes = array(
    'css' => 'text/css',
    'woff' => 'application/x-font-woff',
    'woff2' => 'application/x-font-woff2',
    'otf' => 'application/octet-stream',
    'ttf' => 'application/octet-stream',
    'eot' => 'application/vnd.ms-fontobject',
    'js' => 'application/x-javascript',
    'htm' => 'text/html;charset=utf-8',
    'htc' => 'text/x-component',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'jpg' => 'image/jpg',
    'gif' => 'image/gif',
    'txt' => 'text/plain',
    'pdf' => 'application/pdf',
    'rtf' => 'application/rtf',
    'doc' => 'application/msword',
    'xls' => 'application/msexcel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'svg' => 'image/svg+xml',
    'mp4' => 'video/mp4',
    'ogg' => 'video/ogg'
);

// Perform custom simple URL parsing to match needed URL for static resource serving
$url = array_key_exists('REQUEST_URI', $_SERVER) ? $_SERVER['REQUEST_URI'] : '';

// Get URL path from URL and split with "/"
$url = array_values(array_filter(explode('/', parse_url($url, PHP_URL_PATH))));

// Special hook to avoid further framework loading if this is static resource request
if (array_key_exists(0, $url) && $url[0] === STATIC_RESOURCE_HANDLER) {
    // Get full path to static resource
    $filename = realpath('../' . $_GET['p']);

    if (file_exists($filename)) {
        // Receive current ETag for resource(if present)
        $clientETag = array_key_exists('HTTP_IF_NONE_MATCH', $_SERVER) ? $_SERVER['HTTP_IF_NONE_MATCH'] : '';

        // Generate ETag for resource
        $serverETag = filemtime($filename);

        // Set old cache headers
        header('Cache-Control:max-age=1800');

        // Always set new ETag header independently on further actions
        header('ETag:' . $serverETag);

        // Get file extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        // If server and client ETags are equal
        if ($clientETag === $serverETag) {
            header('HTTP/1.1 304 Not Modified');
        } elseif (array_key_exists($extension, $mimeTypes)) {
            // Set mime type
            header('Content-type: ' . $mimeTypes[$extension]);

            // Output resource
            echo file_get_contents($filename);
        } else { // File type is not supported
            header('HTTP/1.0 404 Not Found');
        }
    } else { // File not found
        header('HTTP/1.0 404 Not Found');
    }

    // Avoid further request processing
    die();
}
