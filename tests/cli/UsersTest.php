<?php

namespace flusio\cli;

use flusio\models;
use tests\factories\TokenFactory;
use tests\factories\UserFactory;

class UsersTest extends \PHPUnit\Framework\TestCase
{
    use \tests\FakerHelper;
    use \tests\InitializerHelper;
    use \tests\MockHttpHelper;
    use \Minz\Tests\ApplicationHelper;
    use \Minz\Tests\ResponseAsserts;
    use \Minz\Tests\TimeHelper;

    /**
     * @beforeClass
     */
    public static function loadApplication()
    {
        self::$application = new \flusio\cli\Application();
    }

    public function testIndexListsUsers()
    {
        $created_at_1 = $this->fake('dateTime');
        $validated_at_1 = $this->fake('dateTime');
        $email_1 = $this->fake('email');
        $user_1 = UserFactory::create([
            'created_at' => $created_at_1,
            'validated_at' => $validated_at_1,
            'email' => $email_1,
        ]);
        $created_at_2 = clone $created_at_1;
        $created_at_2->modify('+1 hour');
        $email_2 = $this->fake('email');
        $user_2 = UserFactory::create([
            'created_at' => $created_at_2,
            'validated_at' => null,
            'email' => $email_2,
        ]);

        $response = $this->appRun('CLI', '/users');

        $this->assertResponseCode($response, 200);
        $expected_output = <<<TEXT
        {$user_1->id} {$created_at_1->format('Y-m-d')} {$email_1}
        {$user_2->id} {$created_at_2->format('Y-m-d')} {$email_2} (not validated)
        TEXT;
        $this->assertResponseEquals($response, $expected_output);
    }

    public function testIndexShowsIfNoUsers()
    {
        $response = $this->appRun('CLI', '/users');

        $this->assertResponseCode($response, 200);
        $this->assertResponseEquals($response, 'No users');
    }

    public function testCreateCreatesAValidatedUser()
    {
        $username = $this->fake('name');
        $email = $this->fake('email');
        $password = $this->fake('password');

        $this->assertSame(0, models\User::count());

        $response = $this->appRun('CLI', '/users/create', [
            'username' => $username,
            'email' => $email,
            'password' => $password,
        ]);

        $this->assertResponseCode($response, 200);
        $this->assertResponseEquals($response, "User {$username} ({$email}) has been created.");
        $this->assertSame(1, models\User::count());
        $user = models\User::take();
        $this->assertSame($username, $user->username);
        $this->assertSame($email, $user->email);
        $this->assertNotNull($user->validated_at);
    }

    public function testCreateCreatesDefaultCollections()
    {
        $this->assertSame(0, models\Collection::count());

        $response = $this->appRun('CLI', '/users/create', [
            'username' => $this->fake('name'),
            'email' => $this->fake('email'),
            'password' => $this->fake('password'),
        ]);

        $this->assertGreaterThan(0, models\Collection::count());
        $user = models\User::take();
        $bookmarks = models\Collection::findBy([
            'user_id' => $user->id,
            'type' => 'bookmarks',
        ]);
        $news = models\Collection::findBy([
            'user_id' => $user->id,
            'type' => 'news',
        ]);
        $read_list = models\Collection::findBy([
            'user_id' => $user->id,
            'type' => 'read',
        ]);
        $never_list = models\Collection::findBy([
            'user_id' => $user->id,
            'type' => 'never',
        ]);
        $this->assertNotNull($bookmarks);
        $this->assertNotNull($news);
        $this->assertNotNull($read_list);
        $this->assertNotNull($never_list);
    }

    public function testCreateFailsIfAnArgumentIsInvalid()
    {
        $email = $this->fake('email');
        $password = $this->fake('password');

        $response = $this->appRun('CLI', '/users/create', [
            'email' => $email,
            'password' => $password,
        ]);

        $this->assertResponseCode($response, 400);
        $this->assertResponseEquals($response, 'User creation failed: The username is required.');
        $this->assertSame(0, models\User::count());
    }

    public function testExportCreatesTheDataFileAndRendersCorrectly()
    {
        $tmp_path = \Minz\Configuration::$tmp_path;
        $current_path = $tmp_path . '/' . md5(rand());
        @mkdir($current_path, 0777, true);
        @chdir($current_path);
        $user = UserFactory::create();

        $response = $this->appRun('CLI', '/users/export', [
            'id' => $user->id,
        ]);

        $this->assertResponseCode($response, 200);
        $this->assertResponseContains($response, 'User’s data have been exported successfully');
        $output = $response->render();
        $success = preg_match('/^.*\((?P<filepath>.*)\).$/', $output, $matches);
        $this->assertSame(1, $success, 'Output must match the regex');
        $this->assertTrue(file_exists($matches['filepath']), 'Data file must exist');
    }

    public function testExportFailsIfUserDoesNotExist()
    {
        $tmp_path = \Minz\Configuration::$tmp_path;
        $current_path = $tmp_path . '/' . md5(rand());
        @mkdir($current_path, 0777, true);
        @chdir($current_path);
        $user_id = \Minz\Random::hex(32);

        $response = $this->appRun('CLI', '/users/export', [
            'id' => $user_id,
        ]);

        $this->assertResponseCode($response, 404);
        $this->assertResponseEquals($response, "User {$user_id} doesn’t exist.");
    }

    public function testValidateValidatesUser()
    {
        $this->freeze($this->fake('dateTime'));
        $user = UserFactory::create([
            'validated_at' => null,
        ]);

        $response = $this->appRun('CLI', '/users/validate', [
            'id' => $user->id,
        ]);

        $this->assertResponseCode($response, 200);
        $this->assertResponseEquals($response, "User {$user->id} is now validated.");
        $user = $user->reload();
        $this->assertEquals(\Minz\Time::now(), $user->validated_at);
    }

    public function testValidateSetsSubscriptionAccountId()
    {
        \Minz\Configuration::$application['subscriptions_enabled'] = true;
        $subscriptions_host = \Minz\Configuration::$application['subscriptions_host'];
        $email = $this->fake('email');
        $account_id = $this->fake('uuid');
        $expired_at = $this->fake('dateTime');
        $url = "{$subscriptions_host}/api/account?email={$email}";
        $this->mockHttpWithResponse($url, <<<TEXT
            HTTP/2 200
            Content-type: application/json

            {
                "id": "{$account_id}",
                "expired_at": "{$expired_at->format(\Minz\Database\Column::DATETIME_FORMAT)}"
            }
            TEXT
        );
        $user = UserFactory::create([
            'email' => $email,
            'validated_at' => null,
            'subscription_account_id' => null,
        ]);

        $response = $this->appRun('CLI', '/users/validate', [
            'id' => $user->id,
        ]);

        \Minz\Configuration::$application['subscriptions_enabled'] = false;

        $user = $user->reload();
        $this->assertSame($account_id, $user->subscription_account_id);
        $this->assertEquals($expired_at, $user->subscription_expired_at);
    }

    public function testValidateDeletesToken()
    {
        $expired_at = \Minz\Time::fromNow($this->fake('numberBetween', 1, 9000), 'minutes');
        $token = TokenFactory::create([
            'expired_at' => $expired_at,
        ]);
        $user = UserFactory::create([
            'validated_at' => null,
            'validation_token' => $token->token,
        ]);

        $response = $this->appRun('CLI', '/users/validate', [
            'id' => $user->id,
        ]);

        $user = $user->reload();
        $this->assertFalse(models\Token::exists($token->token));
        $this->assertNull($user->validation_token);
    }

    public function testValidateFailsIfUserDoesNotExist()
    {
        $response = $this->appRun('CLI', '/users/validate', [
            'id' => 'not-an-id',
        ]);

        $this->assertResponseCode($response, 404);
        $this->assertResponseEquals($response, 'User not-an-id doesn’t exist.');
    }

    public function testValidateFailsIfAlreadyValidated()
    {
        $user = UserFactory::create([
            'validated_at' => $this->fake('dateTime'),
        ]);

        $response = $this->appRun('CLI', '/users/validate', [
            'id' => $user->id,
        ]);

        $this->assertResponseCode($response, 400);
        $this->assertResponseEquals($response, "User {$user->id} has already been validated.");
    }
}
