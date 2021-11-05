<?php

namespace flusio\services;

use flusio\models;

class NewsPickerTest extends \PHPUnit\Framework\TestCase
{
    use \tests\FakerHelper;
    use \tests\InitializerHelper;
    use \Minz\Tests\FactoriesHelper;
    use \Minz\Tests\TimeHelper;

    private $user;
    private $other_user;

    /**
     * @before
     */
    public function setUsers()
    {
        $user_id = $this->create('user');
        $this->user = models\User::find($user_id);

        $user_id = $this->create('user');
        $this->other_user = models\User::find($user_id);
    }

    public function testPickSelectsFromBookmarks()
    {
        $news_picker = new NewsPicker($this->user, [
            'from' => 'bookmarks',
        ]);
        $link_id = $this->create('link', [
            'user_id' => $this->user->id,
        ]);
        $bookmarks_id = $this->create('collection', [
            'user_id' => $this->user->id,
            'type' => 'bookmarks',
        ]);
        $this->create('link_to_collection', [
            'collection_id' => $bookmarks_id,
            'link_id' => $link_id,
        ]);

        $db_links = $news_picker->pick();

        $this->assertSame(1, count($db_links));
        $this->assertSame($link_id, $db_links[0]['id']);
        $this->assertSame('bookmarks', $db_links[0]['via_type']);
    }

    public function testPickSelectsFromFollowed()
    {
        $now = $this->fake('dateTime');
        $this->freeze($now);
        $days_ago = $this->fake('numberBetween', 0, 3);
        $published_at = \Minz\Time::ago($days_ago, 'days');
        $news_picker = new NewsPicker($this->user, [
            'from' => 'followed',
        ]);
        $link_id = $this->create('link', [
            'user_id' => $this->other_user->id,
            'is_hidden' => 0,
        ]);
        $collection_id = $this->create('collection', [
            'user_id' => $this->other_user->id,
            'type' => 'collection',
            'is_public' => 1,
        ]);
        $this->create('link_to_collection', [
            'created_at' => $published_at->format(\Minz\Model::DATETIME_FORMAT),
            'collection_id' => $collection_id,
            'link_id' => $link_id,
        ]);
        $this->create('followed_collection', [
            'user_id' => $this->user->id,
            'collection_id' => $collection_id,
        ]);

        $db_links = $news_picker->pick();

        $this->assertSame(1, count($db_links));
        $this->assertSame($link_id, $db_links[0]['id']);
        $this->assertSame('followed', $db_links[0]['via_type']);
        $this->assertSame($collection_id, $db_links[0]['via_collection_id']);
    }

    public function testPickRespectsMinDuration()
    {
        $duration = $this->fake('numberBetween', 0, 9000);
        $news_picker = new NewsPicker($this->user, [
            'from' => 'bookmarks',
            'min_duration' => $duration,
        ]);
        $link_id_1 = $this->create('link', [
            'user_id' => $this->user->id,
            'reading_time' => $duration,
        ]);
        $link_id_2 = $this->create('link', [
            'user_id' => $this->user->id,
            'reading_time' => $duration - 1,
        ]);
        $bookmarks_id = $this->create('collection', [
            'user_id' => $this->user->id,
            'type' => 'bookmarks',
        ]);
        $this->create('link_to_collection', [
            'collection_id' => $bookmarks_id,
            'link_id' => $link_id_1,
        ]);
        $this->create('link_to_collection', [
            'collection_id' => $bookmarks_id,
            'link_id' => $link_id_2,
        ]);

        $db_links = $news_picker->pick();

        $this->assertSame(1, count($db_links));
        $this->assertSame($link_id_1, $db_links[0]['id']);
    }

    public function testPickRespectsMaxDuration()
    {
        $duration = $this->fake('numberBetween', 0, 9000);
        $news_picker = new NewsPicker($this->user, [
            'from' => 'bookmarks',
            'max_duration' => $duration,
        ]);
        $link_id_1 = $this->create('link', [
            'user_id' => $this->user->id,
            'reading_time' => $duration,
        ]);
        $link_id_2 = $this->create('link', [
            'user_id' => $this->user->id,
            'reading_time' => $duration - 1,
        ]);
        $bookmarks_id = $this->create('collection', [
            'user_id' => $this->user->id,
            'type' => 'bookmarks',
        ]);
        $this->create('link_to_collection', [
            'collection_id' => $bookmarks_id,
            'link_id' => $link_id_1,
        ]);
        $this->create('link_to_collection', [
            'collection_id' => $bookmarks_id,
            'link_id' => $link_id_2,
        ]);

        $db_links = $news_picker->pick();

        $this->assertSame(1, count($db_links));
        $this->assertSame($link_id_2, $db_links[0]['id']);
    }

    public function testPickDoesNotSelectFromBookmarksIfNotSelected()
    {
        $now = $this->fake('dateTime');
        $this->freeze($now);
        $days_ago = $this->fake('numberBetween', 0, 3);
        $published_at = \Minz\Time::ago($days_ago, 'days');
        $news_picker = new NewsPicker($this->user, [
            'from' => 'followed',
        ]);
        $link_id = $this->create('link', [
            'user_id' => $this->user->id,
        ]);
        $bookmarks_id = $this->create('collection', [
            'user_id' => $this->user->id,
            'type' => 'bookmarks',
        ]);
        $this->create('link_to_collection', [
            'created_at' => $published_at->format(\Minz\Model::DATETIME_FORMAT),
            'collection_id' => $bookmarks_id,
            'link_id' => $link_id,
        ]);

        $db_links = $news_picker->pick();

        $this->assertSame(0, count($db_links));
    }

    public function testPickDoesNotSelectFromFollowedIfNotSelected()
    {
        $now = $this->fake('dateTime');
        $this->freeze($now);
        $days_ago = $this->fake('numberBetween', 0, 3);
        $published_at = \Minz\Time::ago($days_ago, 'days');
        $news_picker = new NewsPicker($this->user, [
            'from' => 'bookmarks',
        ]);
        $link_id = $this->create('link', [
            'user_id' => $this->other_user->id,
            'is_hidden' => 0,
        ]);
        $collection_id = $this->create('collection', [
            'user_id' => $this->other_user->id,
            'type' => 'collection',
            'is_public' => 1,
        ]);
        $this->create('link_to_collection', [
            'created_at' => $published_at->format(\Minz\Model::DATETIME_FORMAT),
            'collection_id' => $collection_id,
            'link_id' => $link_id,
        ]);
        $this->create('followed_collection', [
            'user_id' => $this->user->id,
            'collection_id' => $collection_id,
        ]);

        $db_links = $news_picker->pick();

        $this->assertSame(0, count($db_links));
    }

    public function testPickDoesNotPickFromFollowedIfTooOld()
    {
        $now = $this->fake('dateTime');
        $this->freeze($now);
        $days_ago = $this->fake('numberBetween', 4, 9999);
        $published_at = \Minz\Time::ago($days_ago, 'days');
        $news_picker = new NewsPicker($this->user, [
            'from' => 'followed',
        ]);
        $link_id = $this->create('link', [
            'user_id' => $this->other_user->id,
            'is_hidden' => 0,
        ]);
        $collection_id = $this->create('collection', [
            'user_id' => $this->other_user->id,
            'type' => 'collection',
            'is_public' => 1,
        ]);
        $this->create('link_to_collection', [
            'created_at' => $published_at->format(\Minz\Model::DATETIME_FORMAT),
            'collection_id' => $collection_id,
            'link_id' => $link_id,
        ]);
        $this->create('followed_collection', [
            'user_id' => $this->user->id,
            'collection_id' => $collection_id,
        ]);

        $db_links = $news_picker->pick();

        $this->assertSame(0, count($db_links));
    }

    public function testPickDoesNotSelectFromFollowedIfLinkIsHidden()
    {
        $now = $this->fake('dateTime');
        $this->freeze($now);
        $days_ago = $this->fake('numberBetween', 0, 3);
        $published_at = \Minz\Time::ago($days_ago, 'days');
        $news_picker = new NewsPicker($this->user, [
            'from' => 'followed',
        ]);
        $link_id = $this->create('link', [
            'user_id' => $this->other_user->id,
            'is_hidden' => 1,
        ]);
        $collection_id = $this->create('collection', [
            'user_id' => $this->other_user->id,
            'type' => 'collection',
            'is_public' => 1,
        ]);
        $this->create('link_to_collection', [
            'created_at' => $published_at->format(\Minz\Model::DATETIME_FORMAT),
            'collection_id' => $collection_id,
            'link_id' => $link_id,
        ]);
        $this->create('followed_collection', [
            'user_id' => $this->user->id,
            'collection_id' => $collection_id,
        ]);

        $db_links = $news_picker->pick();

        $this->assertSame(0, count($db_links));
    }

    public function testPickDoesNotSelectFromFollowedIfCollectionIsPrivate()
    {
        $now = $this->fake('dateTime');
        $this->freeze($now);
        $days_ago = $this->fake('numberBetween', 0, 3);
        $published_at = \Minz\Time::ago($days_ago, 'days');
        $news_picker = new NewsPicker($this->user, [
            'from' => 'followed',
        ]);
        $link_id = $this->create('link', [
            'user_id' => $this->other_user->id,
            'is_hidden' => 0,
        ]);
        $collection_id = $this->create('collection', [
            'user_id' => $this->other_user->id,
            'type' => 'collection',
            'is_public' => 0,
        ]);
        $this->create('link_to_collection', [
            'created_at' => $published_at->format(\Minz\Model::DATETIME_FORMAT),
            'collection_id' => $collection_id,
            'link_id' => $link_id,
        ]);
        $this->create('followed_collection', [
            'user_id' => $this->user->id,
            'collection_id' => $collection_id,
        ]);

        $db_links = $news_picker->pick();

        $this->assertSame(0, count($db_links));
    }

    public function testPickDoesNotSelectFromFollowedIfUrlInBookmarks()
    {
        $now = $this->fake('dateTime');
        $this->freeze($now);
        $days_ago = $this->fake('numberBetween', 0, 3);
        $published_at = \Minz\Time::ago($days_ago, 'days');
        $news_picker = new NewsPicker($this->user, [
            'from' => 'followed',
        ]);
        $bookmarks = $this->user->bookmarks();
        $url = $this->fake('url');
        $link_id = $this->create('link', [
            'user_id' => $this->other_user->id,
            'url' => $url,
            'is_hidden' => 0,
        ]);
        $owned_link_id = $this->create('link', [
            'user_id' => $this->user->id,
            'url' => $url,
        ]);
        $collection_id = $this->create('collection', [
            'user_id' => $this->other_user->id,
            'type' => 'collection',
            'is_public' => 1,
        ]);
        $this->create('link_to_collection', [
            'created_at' => $published_at->format(\Minz\Model::DATETIME_FORMAT),
            'collection_id' => $collection_id,
            'link_id' => $link_id,
        ]);
        $this->create('link_to_collection', [
            'collection_id' => $bookmarks->id,
            'link_id' => $owned_link_id,
        ]);
        $this->create('followed_collection', [
            'user_id' => $this->user->id,
            'collection_id' => $collection_id,
        ]);

        $db_links = $news_picker->pick();

        $this->assertSame(0, count($db_links));
    }

    public function testPickDoesNotSelectFromFollowedIfUrlInReadList()
    {
        $now = $this->fake('dateTime');
        $this->freeze($now);
        $days_ago = $this->fake('numberBetween', 0, 3);
        $published_at = \Minz\Time::ago($days_ago, 'days');
        $news_picker = new NewsPicker($this->user, [
            'from' => 'followed',
        ]);
        $read_list = $this->user->readList();
        $url = $this->fake('url');
        $link_id = $this->create('link', [
            'user_id' => $this->other_user->id,
            'url' => $url,
            'is_hidden' => 0,
        ]);
        $owned_link_id = $this->create('link', [
            'user_id' => $this->user->id,
            'url' => $url,
        ]);
        $collection_id = $this->create('collection', [
            'user_id' => $this->other_user->id,
            'type' => 'collection',
            'is_public' => 1,
        ]);
        $this->create('link_to_collection', [
            'created_at' => $published_at->format(\Minz\Model::DATETIME_FORMAT),
            'collection_id' => $collection_id,
            'link_id' => $link_id,
        ]);
        $this->create('link_to_collection', [
            'collection_id' => $read_list->id,
            'link_id' => $owned_link_id,
        ]);
        $this->create('followed_collection', [
            'user_id' => $this->user->id,
            'collection_id' => $collection_id,
        ]);

        $db_links = $news_picker->pick();

        $this->assertSame(0, count($db_links));
    }

    public function testPickDoesNotSelectFromFollowedIfUrlInNeverList()
    {
        $now = $this->fake('dateTime');
        $this->freeze($now);
        $days_ago = $this->fake('numberBetween', 0, 3);
        $published_at = \Minz\Time::ago($days_ago, 'days');
        $news_picker = new NewsPicker($this->user, [
            'from' => 'followed',
        ]);
        $never_list = $this->user->neverList();
        $url = $this->fake('url');
        $link_id = $this->create('link', [
            'user_id' => $this->other_user->id,
            'url' => $url,
            'is_hidden' => 0,
        ]);
        $owned_link_id = $this->create('link', [
            'user_id' => $this->user->id,
            'url' => $url,
        ]);
        $collection_id = $this->create('collection', [
            'user_id' => $this->other_user->id,
            'type' => 'collection',
            'is_public' => 1,
        ]);
        $this->create('link_to_collection', [
            'created_at' => $published_at->format(\Minz\Model::DATETIME_FORMAT),
            'collection_id' => $collection_id,
            'link_id' => $link_id,
        ]);
        $this->create('link_to_collection', [
            'collection_id' => $never_list->id,
            'link_id' => $owned_link_id,
        ]);
        $this->create('followed_collection', [
            'user_id' => $this->user->id,
            'collection_id' => $collection_id,
        ]);

        $db_links = $news_picker->pick();

        $this->assertSame(0, count($db_links));
    }
}
