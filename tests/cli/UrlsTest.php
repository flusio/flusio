<?php

namespace flusio\cli;

class UrlsTest extends \PHPUnit\Framework\TestCase
{
    use \tests\FakerHelper;
    use \tests\MockHttpHelper;
    use \Minz\Tests\ApplicationHelper;
    use \Minz\Tests\ResponseAsserts;

    /**
     * @beforeClass
     */
    public static function loadApplication()
    {
        self::$application = new \flusio\cli\Application();
    }

    /**
     * @before
     */
    public function emptyCachePath()
    {
        $files = glob(\Minz\Configuration::$application['cache_path'] . '/*');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    public function testShowRendersCorrectly()
    {
        $url = $this->fake('url');
        $this->mockHttpWithResponse($url, <<<TEXT
            HTTP/2 200
            Content-type: text/plain

            Hello World!
            TEXT
        );

        $response = $this->appRun('CLI', '/urls/show', [
            'url' => $url,
        ]);

        $this->assertResponseCode($response, 200);
        $this->assertResponseContainsIgnoringCase($response, 'Content-type: text/plain');
        $this->assertResponseContains($response, 'Hello World!');
    }

    public function testShowFailsWithInvalidUrl()
    {
        $url = 'not an url';

        $response = $this->appRun('CLI', '/urls/show', [
            'url' => $url,
        ]);

        $this->assertResponseCode($response, 400);
        $this->assertResponseEquals($response, '`not an url` is not a valid URL.');
    }

    public function testShowFailsWithUnresolvableUrl()
    {
        $url = 'http://unresolvable-url';

        $response = $this->appRun('CLI', '/urls/show', [
            'url' => $url,
        ]);

        $this->assertResponseCode($response, 500);
        $this->assertResponseEquals($response, 'Could not resolve host: unresolvable-url');
    }

    public function testUncacheClearsCacheOfGivenUrl()
    {
        $cache_path = \Minz\Configuration::$application['cache_path'];
        $cache = new \SpiderBits\Cache($cache_path);
        $url = $this->fake('url');
        $url_hash = \SpiderBits\Cache::hash($url);
        $raw_response = <<<TEXT
            HTTP/2 200
            Content-type: text/plain

            Hello World!
            TEXT;
        $cache->save($url_hash, $raw_response);

        $this->assertTrue(file_exists("{$cache_path}/{$url_hash}"));

        $response = $this->appRun('CLI', '/urls/uncache', [
            'url' => $url,
        ]);

        $this->assertResponseCode($response, 200);
        $this->assertFalse(file_exists("{$cache_path}/{$url_hash}"));
        $this->assertResponseEquals($response, "Cache for {$url} ({$url_hash}) has been cleared.");
    }

    public function testUncacheFailsWithInvalidUrl()
    {
        $url = 'not an url';

        $response = $this->appRun('CLI', '/urls/uncache', [
            'url' => $url,
        ]);

        $this->assertResponseCode($response, 400);
        $this->assertResponseEquals($response, "`{$url}` is not a valid URL.");
    }

    public function testUncacheFailsIfCachePathCannotBeWritten()
    {
        $cache_path = \Minz\Configuration::$application['cache_path'];
        $cache = new \SpiderBits\Cache($cache_path);
        $url = $this->fake('url');
        $url_hash = \SpiderBits\Cache::hash($url);
        $raw_response = <<<TEXT
            HTTP/2 200
            Content-type: text/plain

            Hello World!
            TEXT;
        $cache->save($url_hash, $raw_response);
        chmod($cache_path, 0500);

        $this->assertTrue(file_exists("{$cache_path}/{$url_hash}"));

        $response = $this->appRun('CLI', '/urls/uncache', [
            'url' => $url,
        ]);

        chmod($cache_path, 0775);
        $this->assertResponseCode($response, 500);
        $this->assertTrue(file_exists("{$cache_path}/{$url_hash}"));
        $this->assertResponseEquals($response, "Cache for {$url} ({$url_hash}) cannot be cleared.");
    }
}
