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
        $this->currentResource = $resource;

        // Rewrite Urls
        $content = preg_replace_callback(self::P_URL, [$this, 'rewriteUrls'], file_get_contents($resource));
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
            return 'url("/' . STATIC_RESOURCE_HANDLER . '/?p=' . $path . '")';
        }

        return $matches[0];
    }
}
