<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>.
 * on 07.07.16 at 18:19
 */
namespace samsonphp\resource;

use samsonphp\resource\exception\ResourceNotFound;

/**
 * CSS assets handling class
 *
 * @author Vitaly Iegorov <egorov@samsonos.com>
 * @package samsonphp\resource
 */
class CSS
{
    /** Pattern for matching CSS url */
    const P_URL = '/url\s*\(\s*(\'|\")?([^\)\s\'\"]+)(\'|\")?\s*\)/i';

    /** @var string Path to current resource file */
    protected $currentResource;

    /**
     * LESS resource compiler.
     *
     * @param string $resource  Resource full path
     * @param string $extension Resource extension
     * @param string $content   Compiled output resource content
     */
    public function compile($resource, $extension, &$content)
    {
        if ($extension === 'css') {
            $this->currentResource = $resource;

            // Rewrite Urls
            $content = preg_replace_callback(self::P_URL, [$this, 'rewriteUrls'], $content);
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
    public function rewriteUrls($matches)
    {
        // Store static resource path
        $url = $matches[2];

        if (strpos($url, 'data/') === false) {
            // Remove possible GET parameters from resource path
            $url = $this->getOnlyUrl($url, '?');

            // Remove possible HASH parameters from resource path
            $url = $this->getOnlyUrl($url, '#');

            // Try to find resource and output full error
            try {
                $path = ResourceValidator::getProjectRelativePath($url, dirname($this->currentResource));
            } catch (ResourceNotFound $e) {
                throw new ResourceNotFound('Cannot find resource "' . $url . '" in "' . $this->currentResource . '"');
            }

            // Build path to static resource handler
            return 'url("/' . STATIC_RESOURCE_HANDLER . '/?p=' . $path . '")';
        }

        return $matches[0];
    }

    /**
     * Get only path or URL before marker.
     *
     * @param string $path   Full URL with possible unneeded data
     * @param string $marker Marker for separation
     *
     * @return string Filtered asset URL
     */
    protected function getOnlyUrl($path, $marker)
    {
        // Remove possible GET parameters from resource path
        if (($getStart = strpos($path, $marker)) !== false) {
            return substr($path, 0, $getStart);
        }

        return $path;
    }
}
