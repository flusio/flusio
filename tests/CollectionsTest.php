<?php

namespace flusio;

class CollectionsTest extends \PHPUnit\Framework\TestCase
{
    use \tests\LoginHelper;
    use \tests\FakerHelper;
    use \Minz\Tests\FactoriesHelper;
    use \Minz\Tests\InitializerHelper;
    use \Minz\Tests\ApplicationHelper;
    use \Minz\Tests\ResponseAsserts;

    public function testShowBookmarksRendersCorrectly()
    {
        $user = $this->login();
        $link_title = $this->fake('words', 3, true);
        $collection_id = $this->create('collection', [
            'user_id' => $user->id,
            'type' => 'bookmarks',
        ]);
        $link_id = $this->create('link', [
            'user_id' => $user->id,
            'title' => $link_title,
        ]);
        $this->create('link_to_collection', [
            'link_id' => $link_id,
            'collection_id' => $collection_id,
        ]);

        $response = $this->appRun('get', '/bookmarks');

        $this->assertResponse($response, 200, $link_title);
        $this->assertPointer($response, 'collections/show_bookmarks.phtml');
    }

    public function testShowBookmarksRedirectsIfNotConnected()
    {
        $response = $this->appRun('get', '/bookmarks');

        $this->assertResponse($response, 302, '/login?redirect_to=%2Fbookmarks');
    }

    public function testShowBookmarksFailsIfCollectionDoesNotExist()
    {
        $this->login();

        $response = $this->appRun('get', '/bookmarks');

        $this->assertResponse($response, 404, 'It looks like you have no “Bookmarks” collection');
    }
}
