<?php

namespace flusio\jobs\scheduled;

use flusio\models;
use tests\factories\CollectionFactory;
use tests\factories\FetchLogFactory;
use tests\factories\FollowedCollectionFactory;
use tests\factories\LinkFactory;
use tests\factories\LinkToCollectionFactory;
use tests\factories\UserFactory;

class FeedsSyncTest extends \PHPUnit\Framework\TestCase
{
    use \tests\FakerHelper;
    use \tests\InitializerHelper;
    use \tests\MockHttpHelper;
    use \Minz\Tests\TimeHelper;

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

    public function testQueue()
    {
        $feeds_sync_job = new FeedsSync();

        $this->assertSame('fetchers', $feeds_sync_job->queue);
    }

    public function testSchedule()
    {
        $now = $this->fake('dateTime');
        $this->freeze($now);

        $feeds_sync_job = new FeedsSync();

        $this->assertSame('+15 seconds', $feeds_sync_job->frequency);
    }

    public function testInstallWithJobsToCreate()
    {
        \Minz\Configuration::$application['job_feeds_sync_count'] = 2;
        \Minz\Configuration::$jobs_adapter = 'database';

        $this->assertSame(0, \Minz\Job::count());

        FeedsSync::install();

        \Minz\Configuration::$application['job_feeds_sync_count'] = 1;
        \Minz\Configuration::$jobs_adapter = 'test';

        $this->assertSame(2, \Minz\Job::count());
    }

    public function testInstallWithJobsToDelete()
    {
        \Minz\Configuration::$jobs_adapter = 'database';
        $feeds_sync_job = new FeedsSync();
        $feeds_sync_job->performAsap();
        $feeds_sync_job = new FeedsSync();
        $feeds_sync_job->performAsap();

        $this->assertSame(2, \Minz\Job::count());

        FeedsSync::install();

        \Minz\Configuration::$jobs_adapter = 'test';

        $this->assertSame(1, \Minz\Job::count());
    }

    public function testPerform()
    {
        $url = 'https://flus.fr/carnet/';
        $card_url = 'https://flus.fr/carnet/card.png';
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $this->mockHttpWithFixture($url, 'responses/flus.fr_carnet_index.html');
        $this->mockHttpWithFile($card_url, 'public/static/og-card.png');
        $this->mockHttpWithFixture($feed_url, 'responses/flus.fr_carnet_feeds_all.atom.xml');
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'name' => $this->fake('sentence'),
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        $collection = $collection->reload();
        $this->assertSame('Carnet de Flus', $collection->name);
        $this->assertSame('atom', $collection->feed_type);
        $this->assertNotNull($collection->image_fetched_at);
        $this->assertNotEmpty($collection->image_filename);
        $this->assertNull($collection->locked_at);
        $links_number = count($collection->links());
        $this->assertSame(3, $links_number);
    }

    public function testPerformLogsFetch()
    {
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'name' => $this->fake('sentence'),
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $feeds_sync_job = new FeedsSync();

        $this->assertSame(0, models\FetchLog::count());

        $feeds_sync_job->perform();

        $this->assertSame(1, models\FetchLog::count());
        $fetch_log = models\FetchLog::take();
        $this->assertSame($feed_url, $fetch_log->url);
        $this->assertSame('flus.fr', $fetch_log->host);
    }

    public function testPerformSavesResponseInCache()
    {
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $this->mockHttpWithFixture($feed_url, 'responses/flus.fr_carnet_feeds_all.atom.xml');
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'name' => $this->fake('sentence'),
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        $hash = \SpiderBits\Cache::hash($feed_url);
        $cache_filepath = \Minz\Configuration::$application['cache_path'] . '/' . $hash;
        $this->assertTrue(file_exists($cache_filepath));
    }

    public function testPerformUsesCache()
    {
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $expected_name = $this->fakeUnique('sentence');
        $expected_title = $this->fakeUnique('sentence');
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'name' => $this->fakeUnique('sentence'),
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>{$expected_name}</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <link href="https://flus.fr/carnet/" rel="alternate" type="text/html" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
            <entry>
                <title>{$expected_title}</title>
                <id>urn:uuid:027e66f5-8137-5040-919d-6377c478ae9d</id>
                <author><name>Marien</name></author>
                <link href="https://flus.fr/carnet/nouveautes-mars-2021.html" rel="alternate" type="text/html" />
                <published>2021-03-30T11:26:00+02:00</published>
                <updated>2021-03-30T11:26:00+02:00</updated>
                <content type="html"></content>
            </entry>
        </feed>
        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        $collection = $collection->reload();
        $this->assertSame($expected_name, $collection->name);
        $link = $collection->links()[0];
        $this->assertSame($expected_title, $link->title);
    }

    public function testPerformSavesPublishedDate()
    {
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $expected_name = $this->fakeUnique('sentence');
        $expected_title = $this->fakeUnique('sentence');
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'name' => $this->fakeUnique('sentence'),
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>Carnet de Flus</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <link href="https://flus.fr/carnet/" rel="alternate" type="text/html" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
            <entry>
                <title>Les nouveautés de mars 2021</title>
                <id>urn:uuid:027e66f5-8137-5040-919d-6377c478ae9d</id>
                <author><name>Marien</name></author>
                <link href="https://flus.fr/carnet/nouveautes-mars-2021.html" rel="alternate" type="text/html" />
                <published>2021-03-30T11:26:00+02:00</published>
                <updated>2021-03-30T11:26:00+02:00</updated>
                <content type="html"></content>
            </entry>
        </feed>
        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        $link_to_collection = models\LinkToCollection::take();
        $this->assertSame(1617096360, $link_to_collection->created_at->getTimestamp());
    }

    public function testPerformIgnoresFeedFetchedLastHour()
    {
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $expected_name = $this->fake('sentence');
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'name' => $expected_name,
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(59, 'minutes'),
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        $collection = $collection->reload();
        $this->assertSame($expected_name, $collection->name);
        $links_number = count($collection->links());
        $this->assertSame(0, $links_number);
    }

    public function testPerformIgnoresFeedThatDidNotChange()
    {
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $expected_name = $this->fakeUnique('sentence');
        // The trick of this test is to create the collection with the hash of
        // the feed that will be fetched. In real life, the hash of the
        // collection would be different. To do so, the feed can’t contain
        // random content (or we would have to calcule the feed hash, which is
        // a bit tedious here).
        $feed_hash = '38f9e30ef7c4b63def59105bc58d363b5147373beeffd9677a0f9e9d22edaebd';
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'name' => $expected_name,
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
            'feed_last_hash' => $feed_hash,
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>carnet de flus</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <link href="https://flus.fr/carnet/" rel="alternate" type="text/html" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
            <entry>
                <title>Les nouveautés de mars 2021</title>
                <id>urn:uuid:027e66f5-8137-5040-919d-6377c478ae9d</id>
                <author><name>Marien</name></author>
                <link href="https://flus.fr/carnet/nouveautes-mars-2021.html" rel="alternate" type="text/html" />
                <published>2021-03-30T11:26:00+02:00</published>
                <updated>2021-03-30T11:26:00+02:00</updated>
                <content type="html"></content>
            </entry>
        </feed>
        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        $collection = $collection->reload();
        $this->assertSame($feed_hash, $collection->feed_last_hash);
        $this->assertSame($expected_name, $collection->name);
    }

    public function testPerformDuplicatesLinkUrlIfNotInCollection()
    {
        $support_user = models\User::supportUser();
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'user_id' => $support_user->id,
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $link_url = 'https://flus.fr/carnet/nouveautes-mars-2021.html';
        $link_entry_id = 'urn:uuid:027e66f5-8137-5040-919d-6377c478ae9d';
        $link_published = '2021-03-30T09:26:00+00:00';
        $original_link = LinkFactory::create([
            'url' => $link_url,
            'user_id' => $support_user->id,
            'feed_entry_id' => null,
            'created_at' => \Minz\Time::now(),
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>carnet de flus</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <link href="https://flus.fr/carnet/" rel="alternate" type="text/html" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
            <entry>
                <title>Les nouveautés de mars 2021</title>
                <id>{$link_entry_id}</id>
                <author><name>Marien</name></author>
                <link href="{$link_url}" rel="alternate" type="text/html" />
                <published>{$link_published}</published>
                <updated>2021-03-30T11:26:00+02:00</updated>
                <content type="html"></content>
            </entry>
        </feed>
        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $this->assertSame(1, models\Link::count());

        $feeds_sync_job->perform();

        $this->assertSame(2, models\Link::count());
        $link = models\Link::take(1);
        $this->assertNotSame($original_link->id, $link->id);
    }

    public function testPerformSkipsFetchIfReachedRateLimit()
    {
        $now = $this->fake('dateTime');
        $this->freeze($now);
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $host = 'flus.fr';
        foreach (range(1, 25) as $i) {
            $seconds = $this->fake('numberBetween', 0, 60);
            $created_at = \Minz\Time::ago($seconds, 'seconds');
            FetchLogFactory::create([
                'created_at' => $created_at,
                'url' => $feed_url,
                'host' => $host,
            ]);
        }
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'name' => $feed_url,
            'feed_url' => $feed_url,
            'feed_fetched_at' => null,
            'feed_fetched_code' => 0,
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        $collection = $collection->reload();
        $this->assertSame($feed_url, $collection->name);
        $this->assertSame(0, $collection->feed_fetched_code);
        $this->assertNull($collection->feed_fetched_at);
    }

    public function testPerformIgnoresEntriesWithNoLink()
    {
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $expected_name = $this->fakeUnique('sentence');
        $expected_title = $this->fakeUnique('sentence');
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'name' => $this->fakeUnique('sentence'),
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>carnet de flus</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <link href="https://flus.fr/carnet/" rel="alternate" type="text/html" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
            <entry>
                <title>Les nouveautés de mars 2021</title>
                <id>urn:uuid:027e66f5-8137-5040-919d-6377c478ae9d</id>
                <author><name>Marien</name></author>
                <published>2021-03-30T11:26:00+02:00</published>
                <updated>2021-03-30T11:26:00+02:00</updated>
                <content type="html"></content>
            </entry>
        </feed>
        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        $collection = $collection->reload();
        $this->assertEmpty($collection->links());
    }

    public function testPerformIgnoresEntriesIfUrlExistsInCollection()
    {
        $support_user = models\User::supportUser();
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'user_id' => $support_user->id,
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $link_url = 'https://flus.fr/carnet/nouveautes-mars-2021.html';
        $link_entry_id = 'urn:uuid:027e66f5-8137-5040-919d-6377c478ae9d';
        $link_published = '2021-03-30T09:26:00+00:00';
        $link = LinkFactory::create([
            'url' => $link_url,
            'user_id' => $support_user->id,
            'feed_entry_id' => null,
            'created_at' => \Minz\Time::now(),
        ]);
        LinkToCollectionFactory::create([
            'collection_id' => $collection->id,
            'link_id' => $link->id,
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>carnet de flus</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <link href="https://flus.fr/carnet/" rel="alternate" type="text/html" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
            <entry>
                <title>Les nouveautés de mars 2021</title>
                <id>{$link_entry_id}</id>
                <author><name>Marien</name></author>
                <link href="{$link_url}" rel="alternate" type="text/html" />
                <published>{$link_published}</published>
                <updated>2021-03-30T11:26:00+02:00</updated>
                <content type="html"></content>
            </entry>
        </feed>
        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        $link = $link->reload();
        $this->assertNull($link->feed_entry_id);
        $this->assertNotSame($link_published, $link->created_at->format(\DateTimeInterface::ATOM));
    }

    public function testPerformIgnoresEntriesIfOverKeepMaximum()
    {
        \Minz\Configuration::$application['feeds_links_keep_maximum'] = 1;

        $this->freeze($this->fake('dateTime'));
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $published_at_1 = \Minz\Time::ago(1, 'months');
        $published_at_2 = \Minz\Time::ago(2, 'months');
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>carnet de flus</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <link href="https://flus.fr/carnet/" rel="alternate" type="text/html" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
            <entry>
                <title>Les nouveautés de mars 2021</title>
                <id>urn:uuid:027e66f5-8137-5040-919d-6377c478ae9d</id>
                <author><name>Marien</name></author>
                <link href="https://flus.fr/carnet/nouveautes-mars-2021.html" rel="alternate" type="text/html"/>
                <published>{$published_at_1->format(DATE_ATOM)}</published>
                <updated>2021-03-30T11:26:00+02:00</updated>
                <content type="html"></content>
            </entry>
            <entry>
                <title>Bilan 2021</title>
                <id>urn:uuid:d4281ca0-f103-529b-9a47-adee05477c31</id>
                <author><name>Marien</name></author>
                <link href="https://flus.fr/carnet/bilan-2021.html" rel="alternate" type="text/html" />
                <published>{$published_at_2->format(DATE_ATOM)}</published>
                <updated>2022-01-05T17:30:00+01:00</updated>
                <content type="html"></content>
            </entry>
        </feed>
        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        \Minz\Configuration::$application['feeds_links_keep_maximum'] = 0;

        $this->assertSame(1, models\Link::count());
        $collection = $collection->reload();
        $links = $collection->links();
        $this->assertSame(1, count($links));
        $this->assertSame('Les nouveautés de mars 2021', $links[0]->title);
    }

    public function testPerformIgnoresEntriesIfOlderThanKeepPeriod()
    {
        \Minz\Configuration::$application['feeds_links_keep_period'] = 6;
        $this->freeze($this->fake('dateTime'));
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $months = $this->fake('numberBetween', 7, 100);
        $published_at = \Minz\Time::ago($months, 'months');
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>carnet de flus</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <link href="https://flus.fr/carnet/" rel="alternate" type="text/html" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
            <entry>
                <title>Les nouveautés de mars 2021</title>
                <id>urn:uuid:027e66f5-8137-5040-919d-6377c478ae9d</id>
                <author><name>Marien</name></author>
                <link href="https://flus.fr/carnet/nouveautes-mars-2021.html" rel="alternate" type="text/html"/>
                <published>{$published_at->format(DATE_ATOM)}</published>
                <updated>2021-03-30T11:26:00+02:00</updated>
                <content type="html"></content>
            </entry>
        </feed>
        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        \Minz\Configuration::$application['feeds_links_keep_period'] = 0;

        $this->assertSame(0, models\Link::count());
    }

    public function testPerformTakesEntriesIfRecentEnoughWhenKeepPeriodIsSet()
    {
        \Minz\Configuration::$application['feeds_links_keep_period'] = 6;
        $this->freeze($this->fake('dateTime'));
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $months = $this->fake('numberBetween', 0, 6);
        $published_at = \Minz\Time::ago($months, 'months');
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>carnet de flus</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <link href="https://flus.fr/carnet/" rel="alternate" type="text/html" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
            <entry>
                <title>Les nouveautés de mars 2021</title>
                <id>urn:uuid:027e66f5-8137-5040-919d-6377c478ae9d</id>
                <author><name>Marien</name></author>
                <link href="https://flus.fr/carnet/nouveautes-mars-2021.html" rel="alternate" type="text/html"/>
                <published>{$published_at->format(DATE_ATOM)}</published>
                <updated>2021-03-30T11:26:00+02:00</updated>
                <content type="html"></content>
            </entry>
        </feed>
        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        \Minz\Configuration::$application['feeds_links_keep_period'] = 0;

        $this->assertSame(1, models\Link::count());
        $collection = $collection->reload();
        $links = $collection->links();
        $this->assertSame(1, count($links));
        $this->assertSame('Les nouveautés de mars 2021', $links[0]->title);
    }

    public function testPerformTakesEntriesIfOlderThanKeepPeriodUntilMinimum()
    {
        \Minz\Configuration::$application['feeds_links_keep_period'] = 6;
        \Minz\Configuration::$application['feeds_links_keep_minimum'] = 1;

        $this->freeze($this->fake('dateTime'));
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $months = $this->fake('numberBetween', 7, 100);
        $published_at_old = \Minz\Time::ago($months, 'months');
        $published_at_older = \Minz\Time::ago($months + 1, 'months');
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>carnet de flus</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <link href="https://flus.fr/carnet/" rel="alternate" type="text/html" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
            <entry>
                <title>Les nouveautés de mars 2021</title>
                <id>urn:uuid:027e66f5-8137-5040-919d-6377c478ae9d</id>
                <author><name>Marien</name></author>
                <link href="https://flus.fr/carnet/nouveautes-mars-2021.html" rel="alternate" type="text/html"/>
                <published>{$published_at_old->format(DATE_ATOM)}</published>
                <updated>2021-03-30T11:26:00+02:00</updated>
                <content type="html"></content>
            </entry>
            <entry>
                <title>Bilan 2021</title>
                <id>urn:uuid:d4281ca0-f103-529b-9a47-adee05477c31</id>
                <author><name>Marien</name></author>
                <link href="https://flus.fr/carnet/bilan-2021.html" rel="alternate" type="text/html" />
                <published>{$published_at_older->format(DATE_ATOM)}</published>
                <updated>2022-01-05T17:30:00+01:00</updated>
                <content type="html"></content>
            </entry>
        </feed>
        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        \Minz\Configuration::$application['feeds_links_keep_period'] = 0;
        \Minz\Configuration::$application['feeds_links_keep_minimum'] = 0;

        $collection = $collection->reload();
        $links = $collection->links();
        $this->assertSame(1, count($links));
        $this->assertSame('Les nouveautés de mars 2021', $links[0]->title);
    }

    public function testPerformForcesEntryIdIfMissing()
    {
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $expected_name = $this->fakeUnique('sentence');
        $expected_title = $this->fakeUnique('sentence');
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'name' => $this->fakeUnique('sentence'),
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>carnet de flus</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <link href="https://flus.fr/carnet/" rel="alternate" type="text/html" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
            <entry>
                <title>Les nouveautés de mars 2021</title>
                <link href="https://flus.fr/carnet/nouveautes-mars-2021.html" rel="alternate" type="text/html" />
                <author><name>Marien</name></author>
                <published>2021-03-30T11:26:00+02:00</published>
                <updated>2021-03-30T11:26:00+02:00</updated>
                <content type="html"></content>
            </entry>
        </feed>
        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        $collection = $collection->reload();
        $link = $collection->links()[0];
        $this->assertSame($link->url, $link->feed_entry_id);
    }

    public function testPerformUpdatesUrlIfEntryIdIsIdentical()
    {
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $old_url = $this->fakeUnique('url');
        $new_url = $this->fakeUnique('url');
        $entry_id = 'urn:uuid: ' . $this->fake('uuid');
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        $link = LinkFactory::create([
            'url' => $old_url,
            'feed_entry_id' => $entry_id,
        ]);
        $link_to_collection = LinkToCollectionFactory::create([
            'link_id' => $link->id,
            'collection_id' => $collection->id,
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>carnet de flus</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <link href="https://flus.fr/carnet/" rel="alternate" type="text/html" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
            <entry>
                <title>Les nouveautés de mars 2021</title>
                <link href="{$new_url}" rel="alternate" type="text/html" />
                <id>{$entry_id}</id>
                <author><name>Marien</name></author>
                <published>2021-03-30T11:26:00+02:00</published>
                <updated>2021-03-30T11:26:00+02:00</updated>
                <content type="html"></content>
            </entry>
        </feed>
        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        $link = $link->reload();
        $this->assertSame($new_url, $link->url);
        $this->assertSame($new_url, $link->title);
        $this->assertNull($link->fetched_at);
        $link_to_collection = $link_to_collection->reload();
        $this->assertSame(1617096360, $link_to_collection->created_at->getTimestamp());
    }

    public function testPerformAbsolutizesLinks()
    {
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>carnet de flus</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <link href="/carnet/" rel="alternate" type="text/html" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
            <entry>
                <title>Les nouveautés de mars 2021</title>
                <id>urn:uuid:027e66f5-8137-5040-919d-6377c478ae9d</id>
                <link href="/carnet/nouveautes-mars-2021.html" rel="alternate" type="text/html" />
                <author><name>Marien</name></author>
                <published>2021-03-30T11:26:00+02:00</published>
                <updated>2021-03-30T11:26:00+02:00</updated>
                <content type="html"></content>
            </entry>
        </feed>

        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        $collection = $collection->reload();
        $this->assertSame('https://flus.fr/carnet/', $collection->feed_site_url);
        $link = $collection->links()[0];
        $this->assertSame('https://flus.fr/carnet/nouveautes-mars-2021.html', $link->url);
    }

    public function testPerformUsesFeedUrlIfSiteUrlIsMissing()
    {
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>carnet de flus</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
            <entry>
                <title>Les nouveautés de mars 2021</title>
                <id>urn:uuid:027e66f5-8137-5040-919d-6377c478ae9d</id>
                <link href="https://flus.fr/carnet/nouveautes-mars-2021.html" rel="alternate" type="text/html" />
                <author><name>Marien</name></author>
                <published>2021-03-30T11:26:00+02:00</published>
                <updated>2021-03-30T11:26:00+02:00</updated>
                <content type="html"></content>
            </entry>
        </feed>

        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        $collection = $collection->reload();
        $this->assertSame($feed_url, $collection->feed_site_url);
    }

    public function testPerformHandlesLongMultiByteFeedTitle()
    {
        // In a first version of the code, titles were trimed with `substr`
        // which should be used only on single-byte encodings. Otherwise, it
        // can cut the strings between the bytes. This led to the database
        // rejecting invalid strings.
        // In this example, Unicode codepoint U+0800 is encoded on 3-bytes, so
        // substr would cut between bytes (3 not being a multiple of 100),
        // while mb_substr handles the size correctly.
        $title = str_repeat("\u{0800}", models\Collection::NAME_MAX_LENGTH);
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>{$title}</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <link href="https://flus.fr/carnet/" rel="alternate" type="text/html" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
        </feed>

        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        $collection = $collection->reload();
        $this->assertSame($title, $collection->name);
    }

    public function testPerformSavesTheLinksUrlReplies()
    {
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>Carnet de Flus</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <link href="https://flus.fr/carnet/" rel="alternate" type="text/html" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
            <entry>
                <title>Les nouveautés de mars 2021</title>
                <id>urn:uuid:027e66f5-8137-5040-919d-6377c478ae9d</id>
                <author><name>Marien</name></author>
                <link href="https://flus.fr/carnet/nouveautes-mars-2021.html" rel="alternate" type="text/html" />
                <link href="https://flus.fr/carnet/nouveautes-mars-2021.html#comments" rel="replies" type="text/html" />
                <published>2021-03-30T11:26:00+02:00</published>
                <updated>2021-03-30T11:26:00+02:00</updated>
                <content type="html"></content>
            </entry>
        </feed>

        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        $collection = $collection->reload();
        $link = $collection->links()[0];
        $this->assertSame('https://flus.fr/carnet/nouveautes-mars-2021.html#comments', $link->url_replies);
    }

    public function testPerformDoesNotFetchFeedIfLockedDuringLastHour()
    {
        $this->freeze($this->fake('dateTime'));
        $minutes = $this->fake('numberBetween', 0, 59);
        $locked_at = \Minz\Time::ago($minutes, 'minutes');
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $name = $this->fakeUnique('sentence');
        $title = $this->fakeUnique('sentence');
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'name' => $this->fakeUnique('sentence'),
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
            'locked_at' => $locked_at,
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>{$name}</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <link href="https://flus.fr/carnet/" rel="alternate" type="text/html" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
            <entry>
                <title>{$title}</title>
                <id>urn:uuid:027e66f5-8137-5040-919d-6377c478ae9d</id>
                <author><name>Marien</name></author>
                <link href="https://flus.fr/carnet/nouveautes-mars-2021.html" rel="alternate" type="text/html" />
                <published>2021-03-30T11:26:00+02:00</published>
                <updated>2021-03-30T11:26:00+02:00</updated>
                <content type="html"></content>
            </entry>
        </feed>
        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        $collection = $collection->reload();
        $this->assertNotSame($name, $collection->name);
        $this->assertEmpty($collection->links());
    }

    public function testPerformFetchesFeedIfLockedAfterAnHour()
    {
        $this->freeze($this->fake('dateTime'));
        $minutes = $this->fake('numberBetween', 60, 1000);
        $locked_at = \Minz\Time::ago($minutes, 'minutes');
        $feed_url = 'https://flus.fr/carnet/feeds/all.atom.xml';
        $expected_name = $this->fakeUnique('sentence');
        $expected_title = $this->fakeUnique('sentence');
        $collection = CollectionFactory::create([
            'type' => 'feed',
            'name' => $this->fakeUnique('sentence'),
            'feed_url' => $feed_url,
            'feed_fetched_at' => \Minz\Time::ago(2, 'hours'),
            'locked_at' => $locked_at,
        ]);
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);
        FollowedCollectionFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        $hash = \SpiderBits\Cache::hash($feed_url);
        $raw_response = <<<XML
        HTTP/2 200 OK
        Content-Type: application/xml

        <?xml version='1.0' encoding='UTF-8'?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>{$expected_name}</title>
            <link href="https://flus.fr/carnet/feeds/all.atom.xml" rel="self" type="application/atom+xml" />
            <link href="https://flus.fr/carnet/" rel="alternate" type="text/html" />
            <id>urn:uuid:4c04fe8e-c966-5b7e-af89-74d092a6ccb0</id>
            <updated>2021-03-30T11:26:00+02:00</updated>
            <entry>
                <title>{$expected_title}</title>
                <id>urn:uuid:027e66f5-8137-5040-919d-6377c478ae9d</id>
                <author><name>Marien</name></author>
                <link href="https://flus.fr/carnet/nouveautes-mars-2021.html" rel="alternate" type="text/html" />
                <published>2021-03-30T11:26:00+02:00</published>
                <updated>2021-03-30T11:26:00+02:00</updated>
                <content type="html"></content>
            </entry>
        </feed>
        XML;
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->save($hash, $raw_response);
        $feeds_sync_job = new FeedsSync();

        $feeds_sync_job->perform();

        $collection = $collection->reload();
        $this->assertSame($expected_name, $collection->name);
        $link = $collection->links()[0];
        $this->assertSame($expected_title, $link->title);
    }
}
