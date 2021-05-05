<?php

namespace flusio\services;

use flusio\models;

class LinkFetcherTest extends \PHPUnit\Framework\TestCase
{
    use \tests\FakerHelper;
    use \Minz\Tests\FactoriesHelper;
    use \Minz\Tests\InitializerHelper;

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
        $url = 'https://github.com/flusio/flusio';
        $link_id = $this->create('link', [
            'url' => $url,
            'title' => $url,
        ]);
        $link = models\Link::find($link_id);

        $link_fetcher_service->fetch($link);

        $link = models\Link::find($link_id);
        $this->assertSame('flusio/flusio', $link->title);
        $this->assertSame(200, $link->fetched_code);
    }

    public function testFetchSavesResponseInCache()
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://github.com/flusio/flusio';
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

    public function testFetchDownloadsOpenGraphIllustration()
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/carnet/flus-media-social-citoyen.html';
        $link_id = $this->create('link', [
            'url' => $url,
            'image_filename' => null,
        ]);
        $link = models\Link::find($link_id);

        $link_fetcher_service->fetch($link);

        $link = models\Link::find($link_id);
        $image_filename = $link->image_filename;
        $this->assertNotNull($image_filename);
        $media_path = \Minz\Configuration::$application['media_path'];
        $card_filepath = "{$media_path}/cards/{$image_filename}";
        $large_filepath = "{$media_path}/large/{$image_filename}";
        $this->assertTrue(file_exists($card_filepath));
        $this->assertTrue(file_exists($large_filepath));
    }

    public function testFetchDoesNotChangeTitleIfAlreadySet()
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://github.com/flusio/flusio';
        $title = $this->fake('sentence');
        $link_id = $this->create('link', [
            'url' => $url,
            'title' => $title,
        ]);
        $link = models\Link::find($link_id);

        $link_fetcher_service->fetch($link);

        $link = models\Link::find($link_id);
        $this->assertSame($title, $link->title);
    }

    public function testFetchDoesNotChangeTitleIfUnreachable()
    {
        $link_fetcher_service = new LinkFetcher();
        $url = 'https://flus.fr/does_not_exist.html';
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
}