<?php

namespace flusio\services;

use flusio\utils;

/**
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class FeedFetcher
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
     * Fetch a feed collection
     *
     * @param \flusio\models\Collection
     */
    public function fetch($collection)
    {
        $info = $this->fetchUrl($collection->feed_url);

        $collection->fetched_at = \Minz\Time::now();
        $collection->fetched_code = $info['status'];
        if (isset($info['error'])) {
            $collection->fetched_error = $info['error'];
        }

        // we set the info only if they weren't already changed
        if ($collection->name === $collection->feed_url && isset($info['title'])) {
            $collection->name = $info['title'];
        }

        if (!$collection->description && isset($info['description'])) {
            $collection->description = $info['description'];
        }
    }

    /**
     * Fetch URL content and return information about the feed
     *
     * @param string $url
     *
     * @return array Possible keys are:
     *     - status (always)
     *     - error
     *     - title
     *     - description
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
            $response = $this->http->get($url);

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

        // $content_type = $response->header('content-type');
        // if (!utils\Belt::contains($content_type, 'text/html')) {
        //     // We operate on HTML only
        //     return $info; // @codeCoverageIgnore
        // }

        $dom = \SpiderBits\Dom::fromText($data);

        return $info;
    }
}
