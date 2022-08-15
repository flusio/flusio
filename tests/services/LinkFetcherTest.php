<?php

namespace flusio\services;

use flusio\models;
use flusio\utils;

class LinkFetcherTest extends \PHPUnit\Framework\TestCase
{
    use \tests\FakerHelper;
    use \tests\InitializerHelper;
    use \tests\MockHttpHelper;
    use \Minz\Tests\FactoriesHelper;

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

    public function testFetchSavesNewLinkInfo()
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/carnet/';
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');
        $link_id = $this->create('link', [
            'url' => $url,
            'title' => $url,
        ]);
        $link = models\Link::find($link_id);

        $link_fetcher_service->fetch($link);

        $link = models\Link::find($link_id);
        $this->assertSame('Carnet de Flus', $link->title);
        $this->assertSame(200, $link->fetched_code);
    }

    public function testFetchSavesResponseInCache()
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/carnet/';
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');
        $link_id = $this->create('link', [
            'url' => $url,
            'title' => $url,
        ]);
        $link = models\Link::find($link_id);

        $link_fetcher_service->fetch($link);

        $hash = \SpiderBits\Cache::hash($url);
        $cache_filepath = \Minz\Configuration::$application['cache_path'] . '/' . $hash;
        $this->assertTrue(file_exists($cache_filepath));
    }

    public function testFetchUsesCache()
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://github.com/flusio/flusio';
        $link_id = $this->create('link', [
            'url' => $url,
            'title' => $url,
        ]);
        $link = models\Link::find($link_id);
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
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);

        $link_fetcher_service->fetch($link);

        $link = models\Link::find($link_id);
        $this->assertSame($expected_title, $link->title);
    }

    public function testFetchFetchesTwitterCorrectly()
    {
        // This test must not be mocked since we want to test the real response
        // of Twitter.
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://twitter.com/flus_fr/status/1272070701193797634';
        $link_id = $this->create('link', [
            'url' => $url,
            'title' => $url,
        ]);
        $link = models\Link::find($link_id);
        $expected_title = 'Flus on Twitter: “Parce que s’informer est un acte politique'
                        . ' essentiel, il est important de disposer des bons outils pour cela.'
                        . " Je développe #Flus, un média social citoyen.\nhttps://t.co/zDFwWVmaiD”";

        $link_fetcher_service->fetch($link);

        $link = models\Link::find($link_id);
        $this->assertSame($expected_title, $link->title);
        $this->assertSame(200, $link->fetched_code);
    }

    public function testFetchHandlesIso8859()
    {
        $link_fetcher_service = new LinkFetcher();
        $url = $this->fake('url');
        $link_id = $this->create('link', [
            'url' => $url,
            'title' => $url,
        ]);
        $link = models\Link::find($link_id);
        $hash = \SpiderBits\Cache::hash($url);
        $fixtures_path = \Minz\Configuration::$app_path . '/tests/fixtures';
        $raw_response = file_get_contents($fixtures_path . '/responses/test_iso_8859_1');
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);

        $link_fetcher_service->fetch($link);

        $link = models\Link::find($link_id);
        $this->assertSame('Test ëéàçï', $link->title);
    }

    public function testFetchHandlesBadEncoding()
    {
        $link_fetcher_service = new LinkFetcher();
        $url = $this->fake('url');
        $link_id = $this->create('link', [
            'url' => $url,
            'title' => $url,
        ]);
        $link = models\Link::find($link_id);
        $hash = \SpiderBits\Cache::hash($url);
        $fixtures_path = \Minz\Configuration::$app_path . '/tests/fixtures';
        $raw_response = file_get_contents($fixtures_path . '/responses/test_bad_encoding');
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);

        $link_fetcher_service->fetch($link);

        $link = models\Link::find($link_id);
        $this->assertSame(410, $link->fetched_code);
    }

    public function testFetchHandlesMissingContentType()
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
        $link_id = $this->create('link', [
            'url' => $url,
            'title' => $url,
        ]);
        $link = models\Link::find($link_id);

        $link_fetcher_service->fetch($link);

        $link = models\Link::find($link_id);
        $this->assertSame('Carnet de Flus', $link->title);
        $this->assertSame(200, $link->fetched_code);
    }

    public function testFetchDownloadsOpenGraphIllustration()
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/carnet/index.html';
        $card_url = 'https://flus.fr/carnet/card.png';
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');
        $this->mockHttpWithFile($card_url, 'public/static/og-card.png');
        $link_id = $this->create('link', [
            'url' => $url,
            'image_filename' => null,
        ]);
        $link = models\Link::find($link_id);

        $link_fetcher_service->fetch($link);

        $link = models\Link::find($link_id);
        $image_filename = $link->image_filename;
        $this->assertNotEmpty($image_filename);
        $media_path = \Minz\Configuration::$application['media_path'];
        $subpath = utils\Belt::filenameToSubpath($image_filename);
        $card_filepath = "{$media_path}/cards/{$subpath}/{$image_filename}";
        $cover_filepath = "{$media_path}/covers/{$subpath}/{$image_filename}";
        $large_filepath = "{$media_path}/large/{$subpath}/{$image_filename}";
        $this->assertTrue(file_exists($card_filepath));
        $this->assertTrue(file_exists($cover_filepath));
        $this->assertTrue(file_exists($large_filepath));
    }

    public function testFetchChangesTitleIfAlreadySetAndForceSync()
    {
        $link_fetcher_service = new LinkFetcher([
            'force_sync' => true,
        ]);
        $url = 'https://flus.fr/carnet/index.html';
        $title = $this->fake('sentence');
        $link_id = $this->create('link', [
            'url' => $url,
            'title' => $title,
        ]);
        $link = models\Link::find($link_id);
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');

        $link_fetcher_service->fetch($link);

        $link = models\Link::find($link_id);
        $this->assertSame('Carnet de Flus', $link->title);
    }

    public function testFetchChangesReadingTimeIfAlreadySetAndForceSync()
    {
        $link_fetcher_service = new LinkFetcher([
            'force_sync' => true,
        ]);
        $url = 'https://flus.fr/carnet/index.html';
        $reading_time = 999999;
        $link_id = $this->create('link', [
            'url' => $url,
            'reading_time' => $reading_time,
        ]);
        $link = models\Link::find($link_id);
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');

        $link_fetcher_service->fetch($link);

        $link = models\Link::find($link_id);
        $this->assertSame(0, $link->reading_time);
    }

    public function testFetchChangesIllustrationIfAlreadySetAndForceSync()
    {
        $link_fetcher_service = new LinkFetcher([
            'force_sync' => true,
        ]);
        $url = 'https://flus.fr/carnet/index.html';
        $card_url = 'https://flus.fr/carnet/card.png';
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');
        $this->mockHttpWithFile($card_url, 'public/static/og-card.png');
        $link_id = $this->create('link', [
            'url' => $url,
            'image_filename' => 'old.png',
        ]);
        $link = models\Link::find($link_id);

        $link_fetcher_service->fetch($link);

        $link = models\Link::find($link_id);
        $image_filename = $link->image_filename;
        $this->assertNotSame('old.png', $image_filename);
        $media_path = \Minz\Configuration::$application['media_path'];
        $subpath = utils\Belt::filenameToSubpath($image_filename);
        $card_filepath = "{$media_path}/cards/{$subpath}/{$image_filename}";
        $cover_filepath = "{$media_path}/covers/{$subpath}/{$image_filename}";
        $large_filepath = "{$media_path}/large/{$subpath}/{$image_filename}";
        $this->assertTrue(file_exists($card_filepath));
        $this->assertTrue(file_exists($cover_filepath));
        $this->assertTrue(file_exists($large_filepath));
    }

    public function testFetchDoesNotSaveResponseInCacheIfResponseIsInError()
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/does_not_exist.html';
        $this->mockHttpWithResponse($url, <<<TEXT
            HTTP/2 404
            content-type: text/plain

            Page not found
            TEXT
        );
        $link_id = $this->create('link', [
            'url' => $url,
            'title' => $url,
        ]);
        $link = models\Link::find($link_id);

        $link_fetcher_service->fetch($link);

        $hash = \SpiderBits\Cache::hash($url);
        $cache_filepath = \Minz\Configuration::$application['cache_path'] . '/' . $hash;
        $this->assertFalse(file_exists($cache_filepath));
    }

    public function testFetchDoesNotChangeTitleIfAlreadySet()
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/carnet/index.html';
        $title = $this->fake('sentence');
        $link_id = $this->create('link', [
            'url' => $url,
            'title' => $title,
        ]);
        $link = models\Link::find($link_id);
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');

        $link_fetcher_service->fetch($link);

        $link = models\Link::find($link_id);
        $this->assertSame($title, $link->title);
    }

    public function testFetchDoesNotChangeTitleIfUnreachable()
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/does_not_exist.html';
        $this->mockHttpWithResponse($url, <<<TEXT
            HTTP/2 404
            content-type: text/plain

            Page not found
            TEXT
        );
        $link_id = $this->create('link', [
            'url' => $url,
            'title' => $url,
        ]);
        $link = models\Link::find($link_id);

        $link_fetcher_service->fetch($link);

        $link = models\Link::find($link_id);
        $this->assertSame($url, $link->title);
        $this->assertSame(404, $link->fetched_code);
    }

    public function testFetchDoesNotChangeReadingTimeIfAlreadySet()
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/carnet/index.html';
        $reading_time = 999999;
        $link_id = $this->create('link', [
            'url' => $url,
            'reading_time' => $reading_time,
        ]);
        $link = models\Link::find($link_id);
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');

        $link_fetcher_service->fetch($link);

        $link = models\Link::find($link_id);
        $this->assertSame($reading_time, $link->reading_time);
    }

    public function testFetchDoesNotChangeIllustrationIfAlreadySet()
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/carnet/index.html';
        $card_url = 'https://flus.fr/carnet/card.png';
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');
        $this->mockHttpWithFile($card_url, 'public/static/og-card.png');
        $link_id = $this->create('link', [
            'url' => $url,
            'image_filename' => 'old.png',
        ]);
        $link = models\Link::find($link_id);

        $link_fetcher_service->fetch($link);

        $link = models\Link::find($link_id);
        $this->assertSame('old.png', $link->image_filename);
    }
}
