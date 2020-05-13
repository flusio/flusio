<?php

namespace flusio;

use Minz\Tests;

class UsersTest extends Tests\IntegrationTestCase
{
    /**
     * @after
     */
    public function logout()
    {
        \tests\Utils::logout();
    }

    public function testRegistrationRendersCorrectly()
    {
        $request = new \Minz\Request('get', '/registration');

        $response = self::$application->run($request);

        $this->assertResponse($response, 200);
    }

    public function testRegistrationRedirectsToHomeIfConnected()
    {
        \tests\Utils::login();

        $request = new \Minz\Request('get', '/registration');

        $response = self::$application->run($request);

        $this->assertResponse($response, 302, '/');
    }

    public function testCreateCreatesAUserAndRedirects()
    {
        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();
        $request = new \Minz\Request('post', '/registration', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'username' => $faker->name,
            'email' => $faker->email,
            'password' => $faker->password,
        ]);

        $this->assertSame(0, $user_dao->count());

        $response = self::$application->run($request);

        $this->assertSame(1, $user_dao->count());
        $this->assertResponse($response, 302, '/');
    }

    public function testCreateCreatesARegistrationValidationToken()
    {
        $faker = \Faker\Factory::create();
        \Minz\Time::freeze($faker->dateTime);

        $user_dao = new models\dao\User();
        $token_dao = new models\dao\Token();
        $request = new \Minz\Request('post', '/registration', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'username' => $faker->name,
            'email' => $faker->email,
            'password' => $faker->password,
        ]);

        $this->assertSame(0, $token_dao->count());

        $response = self::$application->run($request);

        $this->assertSame(1, $token_dao->count());

        $user = new Models\User($user_dao->listAll()[0]);
        $token = new Models\Token($token_dao->listAll()[0]);
        $this->assertSame($user->validation_token, $token->token);
        $this->assertEquals(\Minz\Time::fromNow(1, 'day'), $token->expired_at);

        \Minz\Time::unfreeze();
    }

    public function testCreateSendsAValidationEmail()
    {
        $faker = \Faker\Factory::create();
        $token_dao = new models\dao\Token();
        $email = $faker->email;
        $request = new \Minz\Request('post', '/registration', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'username' => $faker->name,
            'email' => $email,
            'password' => $faker->password,
        ]);

        $this->assertSame(0, count(Tests\Mailer::$emails));

        $response = self::$application->run($request);

        $this->assertSame(1, count(Tests\Mailer::$emails));

        $token = new Models\Token($token_dao->listAll()[0]);
        $phpmailer = Tests\Mailer::$emails[0];
        $this->assertSame('[flusio] Confirm your registration', $phpmailer->Subject);
        $this->assertContains($email, $phpmailer->getToAddresses()[0]);
        $this->assertStringContainsString($token->token, $phpmailer->Body);
    }

    public function testCreateLogsTheUserIn()
    {
        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();
        $email = $faker->email;
        $request = new \Minz\Request('post', '/registration', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'username' => $faker->name,
            'email' => $email,
            'password' => $faker->password,
        ]);

        $user = utils\CurrentUser::get();
        $this->assertNull($user);

        $response = self::$application->run($request);

        $user = utils\CurrentUser::get();
        $this->assertSame($email, $user->email);
    }

    public function testCreateRedirectsToHomeIfConnected()
    {
        \tests\Utils::login();

        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();
        $request = new \Minz\Request('post', '/registration', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'username' => $faker->name,
            'email' => $faker->email,
            'password' => $faker->password,
        ]);

        $this->assertSame(1, $user_dao->count());

        $response = self::$application->run($request);

        $this->assertSame(1, $user_dao->count());
        $this->assertResponse($response, 302, '/');
    }

    public function testCreateFailsIfCsrfIsWrong()
    {
        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();
        (new \Minz\CSRF())->generateToken();
        $request = new \Minz\Request('post', '/registration', [
            'csrf' => 'not the token',
            'username' => $faker->name,
            'email' => $faker->email,
            'password' => $faker->password,
        ]);

        $response = self::$application->run($request);

        $this->assertSame(0, $user_dao->count());
        $this->assertResponse($response, 400, 'A security verification failed');
    }

    public function testCreateFailsIfUsernameIsMissing()
    {
        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();
        $request = new \Minz\Request('post', '/registration', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'email' => $faker->email,
            'password' => $faker->password,
        ]);

        $response = self::$application->run($request);

        $this->assertSame(0, $user_dao->count());
        $this->assertResponse($response, 400, 'The username is required');
    }

    public function testCreateFailsIfUsernameIsTooLong()
    {
        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();
        $request = new \Minz\Request('post', '/registration', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'username' => $faker->sentence(50, false),
            'email' => $faker->email,
            'password' => $faker->password,
        ]);

        $response = self::$application->run($request);

        $this->assertSame(0, $user_dao->count());
        $this->assertResponse($response, 400, 'The username must be less than 50 characters');
    }

    public function testCreateFailsIfEmailIsMissing()
    {
        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();
        $request = new \Minz\Request('post', '/registration', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'username' => $faker->name,
        ]);

        $response = self::$application->run($request);

        $this->assertSame(0, $user_dao->count());
        $this->assertResponse($response, 400, 'The address email is required');
    }

    public function testCreateFailsIfEmailIsInvalid()
    {
        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();
        $request = new \Minz\Request('post', '/registration', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'username' => $faker->name,
            'email' => $faker->word,
            'password' => $faker->password,
        ]);

        $response = self::$application->run($request);

        $this->assertSame(0, $user_dao->count());
        $this->assertResponse($response, 400, 'The address email is invalid');
    }

    public function testCreateFailsIfEmailAlreadyExistsAndValidated()
    {
        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();

        $email = $faker->email;
        self::$factories['users']->create([
            'email' => $email,
            'validated_at' => $faker->iso8601(),
        ]);

        $request = new \Minz\Request('post', '/registration', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'username' => $faker->name,
            'email' => $email,
            'password' => $faker->password,
        ]);

        $response = self::$application->run($request);

        $this->assertSame(1, $user_dao->count());
        $this->assertResponse($response, 400, 'An account already exists with this email address');
    }

    public function testCreateFailsIfPasswordIsMissing()
    {
        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();
        $request = new \Minz\Request('post', '/registration', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'username' => $faker->name,
            'email' => $faker->email,
        ]);

        $response = self::$application->run($request);

        $this->assertSame(0, $user_dao->count());
        $this->assertResponse($response, 400, 'The password is required');
    }

    public function testValidationRendersCorrectlyAndValidatesRegistration()
    {
        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();
        \Minz\Time::freeze($faker->dateTime());

        $expired_at = \Minz\Time::fromNow($faker->numberBetween(1, 9000), 'minutes');
        $token = self::$factories['tokens']->create([
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $user_id = self::$factories['users']->create([
            'validated_at' => null,
            'validation_token' => $token,
        ]);

        $request = new \Minz\Request('get', '/registration/validation', [
            't' => $token,
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 200, 'Your registration is now validated');
        $user = new models\User($user_dao->find($user_id));
        $this->assertEquals(\Minz\Time::now(), $user->validated_at);
    }

    public function testValidationRedirectsIfRegistrationAlreadyValidated()
    {
        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();
        \Minz\Time::freeze($faker->dateTime());

        $expired_at = \Minz\Time::fromNow($faker->numberBetween(1, 9000), 'minutes');
        $token = self::$factories['tokens']->create([
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $user_id = self::$factories['users']->create([
            'validated_at' => $faker->iso8601,
            'validation_token' => $token,
        ]);

        $request = new \Minz\Request('get', '/registration/validation', [
            't' => $token,
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 302, '/');
    }

    public function testValidationFailsIfTokenHasExpired()
    {
        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();
        \Minz\Time::freeze($faker->dateTime());

        $expired_at = \Minz\Time::ago($faker->numberBetween(1, 9000), 'minutes');
        $token = self::$factories['tokens']->create([
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $user_id = self::$factories['users']->create([
            'validated_at' => null,
            'validation_token' => $token,
        ]);

        $request = new \Minz\Request('get', '/registration/validation', [
            't' => $token,
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 400, 'The token has expired');
        $user = new models\User($user_dao->find($user_id));
        $this->assertNull($user->validated_at);
    }

    public function testValidationFailsIfTokenDoesNotExist()
    {
        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();
        \Minz\Time::freeze($faker->dateTime());

        $expired_at = \Minz\Time::fromNow($faker->numberBetween(1, 9000), 'minutes');
        $token = self::$factories['tokens']->create([
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $user_id = self::$factories['users']->create([
            'validated_at' => null,
            'validation_token' => $token,
        ]);

        $request = new \Minz\Request('get', '/registration/validation', [
            't' => 'not the token',
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 404, 'The token doesn’t exist');
        $user = new models\User($user_dao->find($user_id));
        $this->assertNull($user->validated_at);
    }

    public function testResendValidationEmailSendsAnEmailAndRedirects()
    {
        $faker = \Faker\Factory::create();
        $email = $faker->email;
        $expired_at = \Minz\Time::fromNow($faker->numberBetween(1, 9000), 'minutes');
        $token = self::$factories['tokens']->create([
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        \tests\Utils::login([
            'email' => $email,
            'validated_at' => null,
            'validation_token' => $token,
        ]);
        $request = new \Minz\Request('post', '/registration/validation/email', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
        ]);

        $this->assertSame(0, count(Tests\Mailer::$emails));

        $response = self::$application->run($request);

        $this->assertResponse($response, 302, '/?status=validation_email_sent');
        $this->assertSame(1, count(Tests\Mailer::$emails));
        $phpmailer = Tests\Mailer::$emails[0];
        $this->assertSame('[flusio] Confirm your registration', $phpmailer->Subject);
        $this->assertContains($email, $phpmailer->getToAddresses()[0]);
        $this->assertStringContainsString($token, $phpmailer->Body);
    }

    public function testResendValidationEmailRedirectsToRedictTo()
    {
        $faker = \Faker\Factory::create();
        $expired_at = \Minz\Time::fromNow($faker->numberBetween(31, 9000), 'minutes');
        $token = self::$factories['tokens']->create([
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        \tests\Utils::login([
            'validated_at' => null,
            'validation_token' => $token,
        ]);
        $request = new \Minz\Request('post', '/registration/validation/email', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'redirect_to' => 'about',
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 302, '/about?status=validation_email_sent');
    }

    public function testResendValidationEmailCreatesANewTokenIfExpiresSoon()
    {
        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();
        $token_dao = new models\dao\Token();

        $expired_at = \Minz\Time::fromNow($faker->numberBetween(0, 30), 'minutes');
        $token = self::$factories['tokens']->create([
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        $user = \tests\Utils::login([
            'validated_at' => null,
            'validation_token' => $token,
        ]);
        $request = new \Minz\Request('post', '/registration/validation/email', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
        ]);

        $this->assertSame(1, $token_dao->count());

        $response = self::$application->run($request);

        $this->assertSame(2, $token_dao->count());
        $user = new models\User($user_dao->find($user->id)); // reload the user
        $this->assertNotSame($user->validation_token, $token);
    }

    public function testResendValidationEmailRedirectsSilentlyIfAlreadyValidated()
    {
        $faker = \Faker\Factory::create();
        \tests\Utils::login([
            'validated_at' => $faker->iso8601,
        ]);
        $request = new \Minz\Request('post', '/registration/validation/email', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 302, '/');
        $this->assertSame(0, count(Tests\Mailer::$emails));
    }

    public function testResendValidationEmailFailsIfCsrfIsInvalid()
    {
        $faker = \Faker\Factory::create();
        $expired_at = \Minz\Time::fromNow($faker->numberBetween(1, 9000), 'minutes');
        $token = self::$factories['tokens']->create([
            'expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
        ]);
        \tests\Utils::login([
            'validated_at' => null,
            'validation_token' => $token,
        ]);
        (new \Minz\CSRF())->generateToken();
        $request = new \Minz\Request('post', '/registration/validation/email', [
            'csrf' => 'not the token',
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 400, 'A security verification failed');
        $this->assertSame(0, count(Tests\Mailer::$emails));
    }

    public function testResendValidationEmailFailsIfUserNotConnected()
    {
        $request = new \Minz\Request('post', '/registration/validation/email', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 401, 'You must be connected to perform this action');
        $this->assertSame(0, count(Tests\Mailer::$emails));
    }

    public function testDeletionRendersCorrectly()
    {
        \tests\Utils::login();

        $request = new \Minz\Request('get', '/settings/deletion');

        $response = self::$application->run($request);

        $this->assertResponse($response, 200);
    }

    public function testDeletionRedirectsToLoginIfUserNotConnected()
    {
        $request = new \Minz\Request('get', '/settings/deletion');

        $response = self::$application->run($request);

        $this->assertResponse($response, 302, '/login?redirect_to=%2Fsettings%2Fdeletion');
    }
    public function testDeleteRedirectsToTheHomePageAndDeletesTheUser()
    {
        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();

        $password = $faker->password;
        $user = \tests\Utils::login([
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        ]);
        $request = new \Minz\Request('post', '/settings/deletion', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'password' => $password,
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 302, '/');
        $this->assertNull($user_dao->find($user->id));
        $this->assertNull(utils\CurrentUser::get());
    }

    public function testDeleteRedirectsToLoginIfUserIsNotConnected()
    {
        $faker = \Faker\Factory::create();
        $request = new \Minz\Request('post', '/settings/deletion', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'password' => $faker->password,
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 302, '/login?redirect_to=%2Fsettings%2Fdeletion');
    }

    public function testDeleteFailsIfPasswordIsIncorrect()
    {
        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();

        $password = $faker->password;
        $user = \tests\Utils::login([
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        ]);
        $request = new \Minz\Request('post', '/settings/deletion', [
            'csrf' => (new \Minz\CSRF())->generateToken(),
            'password' => 'not the password',
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 400, 'The password is incorrect.');
        $this->assertNotNull($user_dao->find($user->id));
    }

    public function testDeleteFailsIfCsrfIsInvalid()
    {
        $faker = \Faker\Factory::create();
        $user_dao = new models\dao\User();

        $password = $faker->password;
        $user = \tests\Utils::login([
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        ]);
        (new \Minz\CSRF())->generateToken();
        $request = new \Minz\Request('post', '/settings/deletion', [
            'csrf' => 'not the token',
            'password' => $password,
        ]);

        $response = self::$application->run($request);

        $this->assertResponse($response, 400, 'A security verification failed');
        $this->assertNotNull($user_dao->find($user->id));
    }
}
