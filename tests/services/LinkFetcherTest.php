<?php

namespace flusio\services;

use flusio\models;
use flusio\utils;
use tests\factories\LinkFactory;

class LinkFetcherTest extends \PHPUnit\Framework\TestCase
{
    use \tests\FakerHelper;
    use \tests\InitializerHelper;
    use \tests\MockHttpHelper;

    /**
     * @before
     */
    public function emptyCachePath(): void
    {
        /** @var string */
        $cache_path = \Minz\Configuration::$application['cache_path'];
        $files = glob($cache_path . '/*');

        assert($files !== false);

        foreach ($files as $file) {
            unlink($file);
        }
    }

    public function testFetchSavesNewLinkInfo(): void
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/carnet/';
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');
        $link = LinkFactory::create([
            'url' => $url,
            'title' => $url,
        ]);

        $link_fetcher_service->fetch($link);

        $link = $link->reload();
        $this->assertSame('Carnet de Flus', $link->title);
        $this->assertSame(200, $link->fetched_code);
    }

    public function testFetchSavesResponseInCache(): void
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/carnet/';
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');
        $link = LinkFactory::create([
            'url' => $url,
            'title' => $url,
        ]);

        $link_fetcher_service->fetch($link);

        $hash = \SpiderBits\Cache::hash($url);
        /** @var string */
        $cache_path = \Minz\Configuration::$application['cache_path'];
        $cache_filepath = $cache_path . '/' . $hash;
        $this->assertTrue(file_exists($cache_filepath));
    }

    public function testFetchUsesCache(): void
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://github.com/flusio/flusio';
        $link = LinkFactory::create([
            'url' => $url,
            'title' => $url,
        ]);
        /** @var string */
        $expected_title = $this->fake('sentence');
        $hash = \SpiderBits\Cache::hash($url);
        $raw_response = <<<TEXT
        HTTP/2 200 OK
        Content-Type: text/html

        <html>
            <head>
                <title>{$expected_title}</title>
            </head>
        </html>
        TEXT;
        /** @var string */
        $cache_path = \Minz\Configuration::$application['cache_path'];
        $cache = new \SpiderBits\Cache($cache_path);
        $cache->save($hash, $raw_response);

        $link_fetcher_service->fetch($link);

        $link = $link->reload();
        $this->assertSame($expected_title, $link->title);
    }

    public function testFetchHandlesIso8859(): void
    {
        $link_fetcher_service = new LinkFetcher();
        /** @var string */
        $url = $this->fake('url');
        $link = LinkFactory::create([
            'url' => $url,
            'title' => $url,
        ]);
        $hash = \SpiderBits\Cache::hash($url);
        $fixtures_path = \Minz\Configuration::$app_path . '/tests/fixtures';
        $raw_response = @file_get_contents($fixtures_path . '/responses/test_iso_8859_1');
        assert($raw_response !== false);
        /** @var string */
        $cache_path = \Minz\Configuration::$application['cache_path'];
        $cache = new \SpiderBits\Cache($cache_path);
        $cache->save($hash, $raw_response);

        $link_fetcher_service->fetch($link);

        $link = $link->reload();
        $this->assertSame('Test ëéàçï', $link->title);
    }

    public function testFetchHandlesBadEncoding(): void
    {
        $link_fetcher_service = new LinkFetcher();
        /** @var string */
        $url = $this->fake('url');
        $link = LinkFactory::create([
            'url' => $url,
            'title' => $url,
        ]);
        $hash = \SpiderBits\Cache::hash($url);
        $fixtures_path = \Minz\Configuration::$app_path . '/tests/fixtures';
        $raw_response = file_get_contents($fixtures_path . '/responses/test_bad_encoding');
        assert($raw_response !== false);
        /** @var string */
        $cache_path = \Minz\Configuration::$application['cache_path'];
        $cache = new \SpiderBits\Cache($cache_path);
        $cache->save($hash, $raw_response);

        $link_fetcher_service->fetch($link);

        $link = $link->reload();
        $this->assertSame(410, $link->fetched_code);
    }

    public function testFetchHandlesMissingContentType(): void
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/carnet/';
        // I wanted to test with no Content-type at all, but the mock_server
        // (via the PHP built-in server) returns a content-type by default.
        $this->mockHttpWithResponse($url, <<<TEXT
            HTTP/2 200
            server: nginx
            date: Fri, 21 Jan 2022 15:21:03 GMT
            content-type:

            <!DOCTYPE html>
            <html lang="fr">
                <head>
                    <meta charset="utf-8">
                    <title>Carnet de Flus</title>
                </head>

                <body></body>
            </html>
            TEXT
        );
        $link = LinkFactory::create([
            'url' => $url,
            'title' => $url,
        ]);

        $link_fetcher_service->fetch($link);

        $link = $link->reload();
        $this->assertSame('Carnet de Flus', $link->title);
        $this->assertSame(200, $link->fetched_code);
    }

    public function testFetchDownloadsOpenGraphIllustration(): void
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/carnet/index.html';
        $card_url = 'https://flus.fr/carnet/card.png';
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');
        $this->mockHttpWithFile($card_url, 'public/static/og-card.png');
        $link = LinkFactory::create([
            'url' => $url,
            'image_filename' => null,
        ]);

        $link_fetcher_service->fetch($link);

        $link = $link->reload();
        $image_filename = $link->image_filename;
        $this->assertNotEmpty($image_filename);
        /** @var string */
        $media_path = \Minz\Configuration::$application['media_path'];
        $subpath = utils\Belt::filenameToSubpath($image_filename);
        $card_filepath = "{$media_path}/cards/{$subpath}/{$image_filename}";
        $cover_filepath = "{$media_path}/covers/{$subpath}/{$image_filename}";
        $large_filepath = "{$media_path}/large/{$subpath}/{$image_filename}";
        $this->assertTrue(file_exists($card_filepath));
        $this->assertTrue(file_exists($cover_filepath));
        $this->assertTrue(file_exists($large_filepath));
    }

    public function testFetchChangesTitleIfAlreadySetAndForceSync(): void
    {
        $link_fetcher_service = new LinkFetcher([
            'force_sync' => true,
        ]);
        $url = 'https://flus.fr/carnet/index.html';
        /** @var string */
        $title = $this->fake('sentence');
        $link = LinkFactory::create([
            'url' => $url,
            'title' => $title,
        ]);
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');

        $link_fetcher_service->fetch($link);

        $link = $link->reload();
        $this->assertSame('Carnet de Flus', $link->title);
    }

    public function testFetchChangesReadingTimeIfAlreadySetAndForceSync(): void
    {
        $link_fetcher_service = new LinkFetcher([
            'force_sync' => true,
        ]);
        $url = 'https://flus.fr/carnet/index.html';
        $reading_time = 999999;
        $link = LinkFactory::create([
            'url' => $url,
            'reading_time' => $reading_time,
        ]);
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');

        $link_fetcher_service->fetch($link);

        $link = $link->reload();
        $this->assertSame(0, $link->reading_time);
    }

    public function testFetchChangesIllustrationIfAlreadySetAndForceSync(): void
    {
        $link_fetcher_service = new LinkFetcher([
            'force_sync' => true,
        ]);
        $url = 'https://flus.fr/carnet/index.html';
        $card_url = 'https://flus.fr/carnet/card.png';
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');
        $this->mockHttpWithFile($card_url, 'public/static/og-card.png');
        $link = LinkFactory::create([
            'url' => $url,
            'image_filename' => 'old.png',
        ]);

        $link_fetcher_service->fetch($link);

        $link = $link->reload();
        $image_filename = $link->image_filename;
        $this->assertNotNull($image_filename);
        $this->assertNotSame('old.png', $image_filename);
        /** @var string */
        $media_path = \Minz\Configuration::$application['media_path'];
        $subpath = utils\Belt::filenameToSubpath($image_filename);
        $card_filepath = "{$media_path}/cards/{$subpath}/{$image_filename}";
        $cover_filepath = "{$media_path}/covers/{$subpath}/{$image_filename}";
        $large_filepath = "{$media_path}/large/{$subpath}/{$image_filename}";
        $this->assertTrue(file_exists($card_filepath));
        $this->assertTrue(file_exists($cover_filepath));
        $this->assertTrue(file_exists($large_filepath));
    }

    public function testFetchDoesNotSaveResponseInCacheIfResponseIsInError(): void
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/does_not_exist.html';
        $this->mockHttpWithResponse($url, <<<TEXT
            HTTP/2 404
            content-type: text/plain

            Page not found
            TEXT
        );
        $link = LinkFactory::create([
            'url' => $url,
            'title' => $url,
        ]);

        $link_fetcher_service->fetch($link);

        $hash = \SpiderBits\Cache::hash($url);
        /** @var string */
        $cache_path = \Minz\Configuration::$application['cache_path'];
        $cache_filepath = $cache_path . '/' . $hash;
        $this->assertFalse(file_exists($cache_filepath));
    }

    public function testFetchDoesNotChangeTitleIfAlreadySet(): void
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/carnet/index.html';
        /** @var string */
        $title = $this->fake('sentence');
        $link = LinkFactory::create([
            'url' => $url,
            'title' => $title,
        ]);
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');

        $link_fetcher_service->fetch($link);

        $link = $link->reload();
        $this->assertSame($title, $link->title);
    }

    public function testFetchDoesNotChangeTitleIfUnreachable(): void
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/does_not_exist.html';
        $this->mockHttpWithResponse($url, <<<TEXT
            HTTP/2 404
            content-type: text/plain

            Page not found
            TEXT
        );
        $link = LinkFactory::create([
            'url' => $url,
            'title' => $url,
        ]);

        $link_fetcher_service->fetch($link);

        $link = $link->reload();
        $this->assertSame($url, $link->title);
        $this->assertSame(404, $link->fetched_code);
    }

    public function testFetchDoesNotChangeReadingTimeIfAlreadySet(): void
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/carnet/index.html';
        $reading_time = 999999;
        $link = LinkFactory::create([
            'url' => $url,
            'reading_time' => $reading_time,
        ]);
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');

        $link_fetcher_service->fetch($link);

        $link = $link->reload();
        $this->assertSame($reading_time, $link->reading_time);
    }

    public function testFetchDoesNotChangeIllustrationIfAlreadySet(): void
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/carnet/index.html';
        $card_url = 'https://flus.fr/carnet/card.png';
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');
        $this->mockHttpWithFile($card_url, 'public/static/og-card.png');
        $link = LinkFactory::create([
            'url' => $url,
            'image_filename' => 'old.png',
        ]);

        $link_fetcher_service->fetch($link);

        $link = $link->reload();
        $this->assertSame('old.png', $link->image_filename);
    }
}
