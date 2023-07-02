<?php

namespace flusio\controllers\links;

use flusio\models;
use tests\factories\CollectionFactory;
use tests\factories\CollectionShareFactory;
use tests\factories\LinkFactory;
use tests\factories\LinkToCollectionFactory;
use tests\factories\UserFactory;

class MessagesTest extends \PHPUnit\Framework\TestCase
{
    use \tests\FakerHelper;
    use \tests\InitializerHelper;
    use \tests\LoginHelper;
    use \Minz\Tests\ApplicationHelper;
    use \Minz\Tests\ResponseAsserts;

    public function testIndexRedirects()
    {
        $response = $this->appRun('GET', '/links/an_id/messages');

        $this->assertResponseCode($response, 302, '/links/an_id');
    }

    public function testCreateCreatesMessageAndRedirects()
    {
        $user = $this->login();
        $link = LinkFactory::create([
            'user_id' => $user->id,
        ]);
        $content = $this->fake('paragraphs', 3, true);

        $this->assertSame(0, models\Message::count());

        $response = $this->appRun('POST', "/links/{$link->id}/messages", [
            'csrf' => $user->csrf,
            'content' => $content,
        ]);

        $this->assertResponseCode($response, 302, "/links/{$link->id}");
        $this->assertSame(1, models\Message::count());
        $message = models\Message::take();
        $this->assertSame($content, $message->content);
        $this->assertSame($user->id, $message->user_id);
        $this->assertSame($link->id, $message->link_id);
    }

    public function testCreateWorksIfLinkIsInCollectionSharedWithWriteAccess()
    {
        $user = $this->login();
        $other_user = UserFactory::create();
        $link = LinkFactory::create([
            'user_id' => $other_user->id,
        ]);
        $collection = CollectionFactory::create([
            'user_id' => $other_user->id,
        ]);
        LinkToCollectionFactory::create([
            'link_id' => $link->id,
            'collection_id' => $collection->id,
        ]);
        CollectionShareFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
            'type' => 'write',
        ]);
        $content = $this->fake('paragraphs', 3, true);

        $this->assertSame(0, models\Message::count());

        $response = $this->appRun('POST', "/links/{$link->id}/messages", [
            'csrf' => $user->csrf,
            'content' => $content,
        ]);

        $this->assertResponseCode($response, 302, "/links/{$link->id}");
        $this->assertSame(1, models\Message::count());
        $message = models\Message::take();
        $this->assertSame($content, $message->content);
        $this->assertSame($user->id, $message->user_id);
        $this->assertSame($link->id, $message->link_id);
    }

    public function testCreateRedirectsIfNotConnected()
    {
        $user = UserFactory::create([
            'csrf' => 'the token',
        ]);
        $link = LinkFactory::create([
            'user_id' => $user->id,
        ]);
        $content = $this->fake('paragraphs', 3, true);

        $response = $this->appRun('POST', "/links/{$link->id}/messages", [
            'csrf' => $user->csrf,
            'content' => $content,
        ]);

        $this->assertResponseCode($response, 302, "/login?redirect_to=%2Flinks%2F{$link->id}");
        $this->assertSame(0, models\Message::count());
    }

    public function testCreateFailsIfLinkIsNotOwned()
    {
        $user = $this->login();
        $other_user = UserFactory::create();
        $link = LinkFactory::create([
            'user_id' => $other_user->id,
        ]);
        $content = $this->fake('paragraphs', 3, true);

        $this->assertSame(0, models\Message::count());

        $response = $this->appRun('POST', "/links/{$link->id}/messages", [
            'csrf' => $user->csrf,
            'content' => $content,
        ]);

        $this->assertResponseCode($response, 404);
        $this->assertSame(0, models\Message::count());
    }

    public function testCreateFailsIfLinkIsInCollectionSharedWithReadAccess()
    {
        $user = $this->login();
        $other_user = UserFactory::create();
        $link = LinkFactory::create([
            'user_id' => $other_user->id,
        ]);
        $collection = CollectionFactory::create([
            'user_id' => $other_user->id,
        ]);
        LinkToCollectionFactory::create([
            'link_id' => $link->id,
            'collection_id' => $collection->id,
        ]);
        CollectionShareFactory::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
            'type' => 'read',
        ]);
        $content = $this->fake('paragraphs', 3, true);

        $this->assertSame(0, models\Message::count());

        $response = $this->appRun('POST', "/links/{$link->id}/messages", [
            'csrf' => $user->csrf,
            'content' => $content,
        ]);

        $this->assertResponseCode($response, 404);
        $this->assertSame(0, models\Message::count());
    }

    public function testCreateFailsIfCsrfIsInvalid()
    {
        $user = $this->login();
        $link = LinkFactory::create([
            'user_id' => $user->id,
        ]);
        $content = $this->fake('paragraphs', 3, true);

        $this->assertSame(0, models\Message::count());

        $response = $this->appRun('POST', "/links/{$link->id}/messages", [
            'csrf' => 'not the token',
            'content' => $content,
        ]);

        $this->assertResponseCode($response, 400);
        $this->assertResponseContains($response, 'A security verification failed');
        $this->assertSame(0, models\Message::count());
    }

    public function testCreateFailsIfContentIsEmpty()
    {
        $user = $this->login();
        $link = LinkFactory::create([
            'user_id' => $user->id,
        ]);
        $content = '';

        $this->assertSame(0, models\Message::count());

        $response = $this->appRun('POST', "/links/{$link->id}/messages", [
            'csrf' => $user->csrf,
            'content' => $content,
        ]);

        $this->assertResponseCode($response, 400);
        $this->assertResponseContains($response, 'The message is required');
        $this->assertSame(0, models\Message::count());
    }
}
