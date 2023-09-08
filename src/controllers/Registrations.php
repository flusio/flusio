<?php

namespace flusio\controllers;

use Minz\Request;
use Minz\Response;
use flusio\auth;
use flusio\jobs;
use flusio\models;
use flusio\services;
use flusio\utils;

/**
 * Handle the requests related to the registrations.
 *
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Registrations
{
    /**
     * Show the registration form.
     *
     * @response 302 / if connected
     * @response 302 /login if registrations are closed
     * @response 200
     *
     * @return \Minz\Response
     */
    public function new(): Response
    {
        if (auth\CurrentUser::get()) {
            return Response::redirect('home');
        }

        $app_conf = \Minz\Configuration::$application;
        if (!$app_conf['registrations_opened']) {
            return Response::redirect('login');
        }

        $app_path = \Minz\Configuration::$app_path;
        $terms_path = $app_path . '/policies/terms.html';
        $has_terms = file_exists($terms_path);

        return Response::ok('registrations/new.phtml', [
            'has_terms' => $has_terms,
            'username' => '',
            'email' => '',
            'password' => '',
            'subscriptions_enabled' => $app_conf['subscriptions_enabled'],
            'subscriptions_host' => $app_conf['subscriptions_host'],
        ]);
    }

    /**
     * Create a user.
     *
     * @request_param string csrf
     * @request_param string email
     * @request_param string username
     * @request_param string password
     * @request_param string accept_terms
     *
     * @response 302 / if already connected
     * @response 302 /login if registrations are closed
     * @response 400 if CSRF token is wrong
     * @response 400 if email, username or password is missing/invalid
     * @response 400 if the service has terms of service and accept_terms is false
     * @response 400 if email already exists
     * @response 302 /onboarding
     *
     * @param \Minz\Request $request
     *
     * @return \Minz\Response
     */
    public function create(Request $request): Response
    {
        if (auth\CurrentUser::get()) {
            return Response::redirect('home');
        }

        $app_conf = \Minz\Configuration::$application;
        if (!$app_conf['registrations_opened']) {
            return Response::redirect('login');
        }

        $app_path = \Minz\Configuration::$app_path;
        $terms_path = $app_path . '/policies/terms.html';
        $has_terms = file_exists($terms_path);

        $username = $request->param('username', '');
        $email = $request->param('email', '');
        $password = $request->param('password', '');
        $accept_terms = $request->param('accept_terms', false);
        $csrf = $request->param('csrf', '');

        if (!\Minz\Csrf::validate($csrf)) {
            return Response::badRequest('registrations/new.phtml', [
                'has_terms' => $has_terms,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'subscriptions_enabled' => $app_conf['subscriptions_enabled'],
                'subscriptions_host' => $app_conf['subscriptions_host'],
                'error' => _('A security verification failed: you should retry to submit the form.'),
            ]);
        }

        if ($has_terms && !$accept_terms) {
            return Response::badRequest('registrations/new.phtml', [
                'has_terms' => $has_terms,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'subscriptions_enabled' => $app_conf['subscriptions_enabled'],
                'subscriptions_host' => $app_conf['subscriptions_host'],
                'errors' => [
                    'accept_terms' => _('You must accept the terms of service.'),
                ],
            ]);
        }

        try {
            $user = services\UserCreator::create($username, $email, $password);
        } catch (services\UserCreatorError $e) {
            return Response::badRequest('registrations/new.phtml', [
                'has_terms' => $has_terms,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'subscriptions_enabled' => $app_conf['subscriptions_enabled'],
                'subscriptions_host' => $app_conf['subscriptions_host'],
                'errors' => $e->errors(),
            ]);
        }

        // Initialize the validation token
        $validation_token = new models\Token(1, 'day', 16);
        $validation_token->save();

        $user->validation_token = $validation_token->token;
        $user->save();

        // Initialize the current session
        $session_token = new models\Token(1, 'month');
        $session_token->save();

        /** @var string */
        $user_agent = $request->header('HTTP_USER_AGENT', '');
        $session_name = utils\Browser::format($user_agent);
        /** @var string */
        $ip = $request->header('REMOTE_ADDR', 'unknown');
        $ip = utils\Ip::mask($ip);
        $session = new models\Session($session_name, $ip);
        $session->user_id = $user->id;
        $session->token = $session_token->token;
        $session->save();

        auth\CurrentUser::setSessionToken($session_token->token);

        $mailer_job = new jobs\Mailer();
        $mailer_job->performAsap('Users', 'sendAccountValidationEmail', $user->id);

        $response = Response::redirect('onboarding');
        $response->setCookie('flusio_session_token', $session_token->token, [
            'expires' => $session_token->expired_at->getTimestamp(),
            'samesite' => 'Lax',
        ]);
        return $response;
    }
}
