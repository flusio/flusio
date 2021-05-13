<?php

namespace flusio\controllers;

use flusio\auth;
use flusio\models;

class PasswordsTest extends \PHPUnit\Framework\TestCase
{
    use \tests\FakerHelper;
    use \Minz\Tests\FactoriesHelper;
    use \Minz\Tests\InitializerHelper;
    use \Minz\Tests\ApplicationHelper;
    use \Minz\Tests\ResponseAsserts;

    public function testEditRendersCorrectly()
    {
        $minutes = $this->fake('numberBetween', 1, 9000);
        $expired_at = \Minz\Time::fromNow($minutes, 'minutes');
        $token = $this->create('token', [
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $email = $this->fake('email');
        $this->create('user', [
            'email' => $email,
            'reset_token' => $token,
        ]);

        $response = $this->appRun('get', '/password/edit', [
            't' => $token,
        ]);

        $this->assertResponseCode($response, 200);
        $this->assertResponsePointer($response, 'passwords/edit.phtml');
        $this->assertResponseContains($response, "You’re changing the password of {$email}");
    }

    public function testEditFailsIfTokenIsNotPassed()
    {
        $email = $this->fake('email');
        $this->create('user', [
            'email' => $email,
        ]);

        $response = $this->appRun('get', '/password/edit');

        $this->assertResponseCode($response, 404);
        $this->assertResponsePointer($response, 'passwords/edit.phtml');
        $this->assertResponseContains($response, 'The token doesn’t exist.');
    }

    public function testEditFailsIfTokenIsInvalid()
    {
        $token = 'a fake token';
        $email = $this->fake('email');
        $this->create('user', [
            'email' => $email,
        ]);

        $response = $this->appRun('get', '/password/edit', [
            't' => $token,
        ]);

        $this->assertResponseCode($response, 404);
        $this->assertResponsePointer($response, 'passwords/edit.phtml');
        $this->assertResponseContains($response, 'The token doesn’t exist.');
    }

    public function testEditFailsIfTokenIsNotAttachedToUser()
    {
        $minutes = $this->fake('numberBetween', 1, 9000);
        $expired_at = \Minz\Time::fromNow($minutes, 'minutes');
        $token = $this->create('token', [
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $email = $this->fake('email');
        $this->create('user', [
            'email' => $email,
        ]);

        $response = $this->appRun('get', '/password/edit', [
            't' => $token,
        ]);

        $this->assertResponseCode($response, 404);
        $this->assertResponsePointer($response, 'passwords/edit.phtml');
        $this->assertResponseContains($response, 'The token doesn’t exist.');
    }

    public function testEditFailsIfTokenHasExpired()
    {
        $minutes = $this->fake('numberBetween', 0, 9000);
        $expired_at = \Minz\Time::ago($minutes, 'minutes');
        $token = $this->create('token', [
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $email = $this->fake('email');
        $this->create('user', [
            'email' => $email,
            'reset_token' => $token,
        ]);

        $response = $this->appRun('get', '/password/edit', [
            't' => $token,
        ]);

        $this->assertResponseCode($response, 400);
        $this->assertResponsePointer($response, 'passwords/edit.phtml');
        $this->assertResponseContains($response, 'The token has expired');
    }

    public function testEditFailsIfTokenIsInvalidated()
    {
        $minutes = $this->fake('numberBetween', 1, 9000);
        $expired_at = \Minz\Time::fromNow($minutes, 'minutes');
        $invalidated_at = $this->fake('dateTime');
        $token = $this->create('token', [
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
            'invalidated_at' => $invalidated_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $email = $this->fake('email');
        $this->create('user', [
            'email' => $email,
            'reset_token' => $token,
        ]);

        $response = $this->appRun('get', '/password/edit', [
            't' => $token,
        ]);

        $this->assertResponseCode($response, 400);
        $this->assertResponsePointer($response, 'passwords/edit.phtml');
        $this->assertResponseContains($response, 'The token has expired');
    }

    public function testUpdateChangesPasswordAndRedirectsCorrectly()
    {
        $csrf = (new \Minz\CSRF())->generateToken();
        $minutes = $this->fake('numberBetween', 1, 9000);
        $expired_at = \Minz\Time::fromNow($minutes, 'minutes');
        $token = $this->create('token', [
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $email = $this->fake('email');
        $old_password = $this->fakeUnique('password');
        $new_password = $this->fakeUnique('password');
        $user_id = $this->create('user', [
            'email' => $email,
            'reset_token' => $token,
            'password_hash' => password_hash($old_password, PASSWORD_BCRYPT),
        ]);

        $response = $this->appRun('post', '/password/edit', [
            'csrf' => $csrf,
            't' => $token,
            'password' => $new_password,
        ]);

        $this->assertResponseCode($response, 302, '/');
        $user = models\User::find($user_id);
        $this->assertTrue($user->verifyPassword($new_password));
    }

    public function testUpdateLogsIn()
    {
        $csrf = (new \Minz\CSRF())->generateToken();
        $minutes = $this->fake('numberBetween', 1, 9000);
        $expired_at = \Minz\Time::fromNow($minutes, 'minutes');
        $token = $this->create('token', [
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $email = $this->fake('email');
        $old_password = $this->fakeUnique('password');
        $new_password = $this->fakeUnique('password');
        $user_id = $this->create('user', [
            'email' => $email,
            'reset_token' => $token,
            'password_hash' => password_hash($old_password, PASSWORD_BCRYPT),
        ]);

        $user = auth\CurrentUser::get();
        $this->assertNull($user);
        $this->assertSame(0, models\Session::count());

        $response = $this->appRun('post', '/password/edit', [
            'csrf' => $csrf,
            't' => $token,
            'password' => $new_password,
        ]);

        $this->assertResponseCode($response, 302, '/');
        $user = auth\CurrentUser::get();
        $this->assertNotNull($user);
        $this->assertSame($email, $user->email);
        $this->assertSame(1, models\Session::count());
    }

    public function testUpdateDoesNotLogInIfAlreadyConnected()
    {
        $logged_in_email = $this->fakeUnique('email');
        $user = $this->login([
            'email' => $logged_in_email,
        ]);
        $csrf = $user->csrf;
        $minutes = $this->fake('numberBetween', 1, 9000);
        $expired_at = \Minz\Time::fromNow($minutes, 'minutes');
        $token = $this->create('token', [
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $email = $this->fakeUnique('email');
        $old_password = $this->fakeUnique('password');
        $new_password = $this->fakeUnique('password');
        $user_id = $this->create('user', [
            'email' => $email,
            'reset_token' => $token,
            'password_hash' => password_hash($old_password, PASSWORD_BCRYPT),
        ]);

        $user = auth\CurrentUser::get();
        $this->assertNotNull($user);
        $this->assertSame($logged_in_email, $user->email);
        $this->assertSame(1, models\Session::count());

        $response = $this->appRun('post', '/password/edit', [
            'csrf' => $csrf,
            't' => $token,
            'password' => $new_password,
        ]);

        $this->assertResponseCode($response, 302, '/');
        $user = auth\CurrentUser::get();
        $this->assertNotNull($user);
        $this->assertSame($logged_in_email, $user->email);
        $this->assertSame(1, models\Session::count());
    }

    public function testUpdateFailsIfTokenIsNotPassed()
    {
        $csrf = (new \Minz\CSRF())->generateToken();
        $minutes = $this->fake('numberBetween', 1, 9000);
        $expired_at = \Minz\Time::fromNow($minutes, 'minutes');
        $token = $this->create('token', [
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $email = $this->fake('email');
        $old_password = $this->fakeUnique('password');
        $new_password = $this->fakeUnique('password');
        $user_id = $this->create('user', [
            'email' => $email,
            'reset_token' => $token,
            'password_hash' => password_hash($old_password, PASSWORD_BCRYPT),
        ]);

        $response = $this->appRun('post', '/password/edit', [
            'csrf' => $csrf,
            'password' => $new_password,
        ]);

        $this->assertResponseCode($response, 404);
        $this->assertResponsePointer($response, 'passwords/edit.phtml');
        $this->assertResponseContains($response, 'The token doesn’t exist.');
        $user = models\User::find($user_id);
        $this->assertTrue($user->verifyPassword($old_password));
    }

    public function testUpdateFailsIfTokenIsInvalid()
    {
        $csrf = (new \Minz\CSRF())->generateToken();
        $minutes = $this->fake('numberBetween', 1, 9000);
        $expired_at = \Minz\Time::fromNow($minutes, 'minutes');
        $token = 'a fake token';
        $email = $this->fake('email');
        $old_password = $this->fakeUnique('password');
        $new_password = $this->fakeUnique('password');
        $user_id = $this->create('user', [
            'email' => $email,
            'password_hash' => password_hash($old_password, PASSWORD_BCRYPT),
        ]);

        $response = $this->appRun('post', '/password/edit', [
            'csrf' => $csrf,
            't' => $token,
            'password' => $new_password,
        ]);

        $this->assertResponseCode($response, 404);
        $this->assertResponsePointer($response, 'passwords/edit.phtml');
        $this->assertResponseContains($response, 'The token doesn’t exist.');
        $user = models\User::find($user_id);
        $this->assertTrue($user->verifyPassword($old_password));
    }

    public function testUpdateFailsIfTokenIsNotAttachedToUser()
    {
        $csrf = (new \Minz\CSRF())->generateToken();
        $minutes = $this->fake('numberBetween', 1, 9000);
        $expired_at = \Minz\Time::fromNow($minutes, 'minutes');
        $token = $this->create('token', [
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $email = $this->fake('email');
        $old_password = $this->fakeUnique('password');
        $new_password = $this->fakeUnique('password');
        $user_id = $this->create('user', [
            'email' => $email,
            'password_hash' => password_hash($old_password, PASSWORD_BCRYPT),
        ]);

        $response = $this->appRun('post', '/password/edit', [
            'csrf' => $csrf,
            't' => $token,
            'password' => $new_password,
        ]);

        $this->assertResponseCode($response, 404);
        $this->assertResponsePointer($response, 'passwords/edit.phtml');
        $this->assertResponseContains($response, 'The token doesn’t exist.');
        $user = models\User::find($user_id);
        $this->assertTrue($user->verifyPassword($old_password));
    }

    public function testUpdateFailsIfTokenHasExpired()
    {
        $csrf = (new \Minz\CSRF())->generateToken();
        $minutes = $this->fake('numberBetween', 0, 9000);
        $expired_at = \Minz\Time::ago($minutes, 'minutes');
        $token = $this->create('token', [
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $email = $this->fake('email');
        $old_password = $this->fakeUnique('password');
        $new_password = $this->fakeUnique('password');
        $user_id = $this->create('user', [
            'email' => $email,
            'reset_token' => $token,
            'password_hash' => password_hash($old_password, PASSWORD_BCRYPT),
        ]);

        $response = $this->appRun('post', '/password/edit', [
            'csrf' => $csrf,
            't' => $token,
            'password' => $new_password,
        ]);

        $this->assertResponseCode($response, 400);
        $this->assertResponsePointer($response, 'passwords/edit.phtml');
        $this->assertResponseContains($response, 'The token has expired');
        $user = models\User::find($user_id);
        $this->assertTrue($user->verifyPassword($old_password));
    }

    public function testUpdateFailsIfTokenIsInvalidated()
    {
        $csrf = (new \Minz\CSRF())->generateToken();
        $minutes = $this->fake('numberBetween', 1, 9000);
        $expired_at = \Minz\Time::fromNow($minutes, 'minutes');
        $invalidated_at = $this->fake('dateTime');
        $token = $this->create('token', [
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
            'invalidated_at' => $invalidated_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $email = $this->fake('email');
        $old_password = $this->fakeUnique('password');
        $new_password = $this->fakeUnique('password');
        $user_id = $this->create('user', [
            'email' => $email,
            'reset_token' => $token,
            'password_hash' => password_hash($old_password, PASSWORD_BCRYPT),
        ]);

        $response = $this->appRun('post', '/password/edit', [
            'csrf' => $csrf,
            't' => $token,
            'password' => $new_password,
        ]);

        $this->assertResponseCode($response, 400);
        $this->assertResponsePointer($response, 'passwords/edit.phtml');
        $this->assertResponseContains($response, 'The token has expired');
        $user = models\User::find($user_id);
        $this->assertTrue($user->verifyPassword($old_password));
    }

    public function testUpdateFailsIfPasswordIsEmpty()
    {
        $csrf = (new \Minz\CSRF())->generateToken();
        $minutes = $this->fake('numberBetween', 1, 9000);
        $expired_at = \Minz\Time::fromNow($minutes, 'minutes');
        $token = $this->create('token', [
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $email = $this->fake('email');
        $old_password = $this->fakeUnique('password');
        $new_password = '';
        $user_id = $this->create('user', [
            'email' => $email,
            'reset_token' => $token,
            'password_hash' => password_hash($old_password, PASSWORD_BCRYPT),
        ]);

        $response = $this->appRun('post', '/password/edit', [
            'csrf' => $csrf,
            't' => $token,
            'password' => $new_password,
        ]);

        $this->assertResponseCode($response, 400);
        $this->assertResponsePointer($response, 'passwords/edit.phtml');
        $this->assertResponseContains($response, 'The password is required');
        $user = models\User::find($user_id);
        $this->assertTrue($user->verifyPassword($old_password));
    }

    public function testUpdateFailsIfCsrfIsInvalid()
    {
        $csrf = (new \Minz\CSRF())->generateToken();
        $minutes = $this->fake('numberBetween', 1, 9000);
        $expired_at = \Minz\Time::fromNow($minutes, 'minutes');
        $token = $this->create('token', [
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $email = $this->fake('email');
        $old_password = $this->fakeUnique('password');
        $new_password = $this->fakeUnique('password');
        $user_id = $this->create('user', [
            'email' => $email,
            'reset_token' => $token,
            'password_hash' => password_hash($old_password, PASSWORD_BCRYPT),
        ]);

        $response = $this->appRun('post', '/password/edit', [
            'csrf' => 'not the token',
            't' => $token,
            'password' => $new_password,
        ]);

        $this->assertResponseCode($response, 400);
        $this->assertResponsePointer($response, 'passwords/edit.phtml');
        $this->assertResponseContains($response, 'A security verification failed');
        $user = models\User::find($user_id);
        $this->assertTrue($user->verifyPassword($old_password));
    }
}