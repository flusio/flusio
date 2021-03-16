<?php

namespace flusio\services;

use flusio\utils;

/**
 * This service helps to fetch links content. It's a wrapper around the
 * SpiderBits library.
 *
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class LinkFetcher
{
    /** @var \SpiderBits\Cache */
    private $cache;

    /** @var \SpiderBits\Http */
    private $http;

    public function __construct()
    {
        $cache_path = \Minz\Configuration::$application['cache_path'];
        $this->cache = new \SpiderBits\Cache($cache_path);

        $php_os = PHP_OS;
        $flusio_version = \Minz\Configuration::$application['version'];
        $this->http = new \SpiderBits\Http();
        $this->http->user_agent = "flusio/{$flusio_version} ({$php_os}; https://github.com/flusio/flusio)";
        $this->http->timeout = 5;
    }

    /**
     * Fetch a link and set its properties
     *
     * @param \flusio\models\Link
     */
    public function fetch($link)
    {
        $info = $this->fetchUrl($link->url);

        $link->fetched_at = \Minz\Time::now();
        $link->fetched_code = $info['status'];
        if (isset($info['error'])) {
            $link->fetched_error = $info['error'];
        }
        // we set the title only if it wasn't changed yet
        if ($link->title === $link->url && isset($info['title'])) {
            $link->title = $info['title'];
        }
        if (isset($info['reading_time'])) {
            $link->reading_time = $info['reading_time'];
        }
        if (isset($info['url_illustration'])) {
            $image_service = new Image();
            $image_filename = $image_service->generatePreviews($info['url_illustration']);
            $link->image_filename = $image_filename;
        }
    }

    /**
     * Fetch URL content and return information about the page
     *
     * @param string $url
     *
     * @return array Possible keys are:
     *     - status (always)
     *     - error
     *     - title
     *     - reading_time
     *     - url_illustration
     */
    public function fetchUrl($url)
    {
        // First, we "GET" the URL...
        $url_hash = \SpiderBits\Cache::hash($url);
        $cached_response = $this->cache->get($url_hash);
        if ($cached_response) {
            // ... via the cache
            $response = \SpiderBits\Response::fromText($cached_response);
        } else {
            // ... or via HTTP
            $options = [];
            if ($this->isTwitter($url)) {
                // If we fetch Twitter, we need to alter our user agent to get
                // server-side rendered content.
                // @see https://stackoverflow.com/a/64332370
                $options = [
                    'user_agent' => $this->http->user_agent . ' (compatible; Googlebot/2.1)',
                ];
            }
            $response = $this->http->get($url, [], $options);

            // that we add to cache
            $this->cache->save($url_hash, (string)$response);
        }

        $info = [
            'status' => $response->status,
        ];

        // It's dangerous out there. mb_convert_encoding makes sure data is a
        // valid UTF-8 string.
        $data = mb_convert_encoding($response->data, 'UTF-8', 'UTF-8');

        if (!$response->success) {
            // Okay, Houston, we've had a problem here. Return early, there's
            // nothing more to do.
            $info['error'] = $data;
            return $info;
        }

        $content_type = $response->header('content-type');
        if (!utils\Belt::contains($content_type, 'text/html')) {
            // We operate on HTML only
            return $info; // @codeCoverageIgnore
        }

        $dom = \SpiderBits\Dom::fromText($data);

        // Parse the title from the DOM
        $title = \SpiderBits\DomExtractor::title($dom);
        if ($title) {
            $info['title'] = $title;
        }

        if ($title && $this->isTwitter($url)) {
            // For Twitter, it's better to concatenate description. Title
            // should be "[account] on Twitter" while description is the tweet
            // content.
            $description = \SpiderBits\DomExtractor::description($dom);
            if ($description) {
                // we rebuild the title to translate it
                $twitter_account = utils\Belt::stripsEnd($title, ' on Twitter');
                $info['title'] = vsprintf(_('%s on Twitter: %s'), [$twitter_account, $description]);
            }
        }

        // And roughly estimate the reading time
        $content = \SpiderBits\DomExtractor::content($dom);
        $words = array_filter(explode(' ', $content));
        $info['reading_time'] = intval(count($words) / 200);

        // Get the illustration URL if any
        $url_illustration = \SpiderBits\DomExtractor::illustration($dom);
        $url_illustration = \SpiderBits\Url::sanitize($url_illustration);
        if ($url_illustration) {
            $info['url_illustration'] = $url_illustration;
        }

        return $info;
    }

    /**
     * Return true if the url is pointing to Twitter
     *
     * @param string $url
     *
     * @return boolean
     */
    private function isTwitter($url)
    {
        return (
            utils\Belt::startsWith($url, 'https://twitter.com/') ||
            utils\Belt::startsWith($url, 'https://mobile.twitter.com/')
        );
    }
}
