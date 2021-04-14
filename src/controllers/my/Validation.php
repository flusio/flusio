<?php

namespace flusio\controllers\my;

use Minz\Response;
use flusio\jobs;
use flusio\models;
use flusio\services;
use flusio\utils;

/**
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Validation
{
    /**
     * Validate an account.
     *
     * @request_param string t The validation token
     *
     * @response 302 /login?redirect_to=/my/account/validation
     *     If no token and current user is not connected
     * @response 302 /
     *     If no token and current user is already validated
     * @response 200
     *     If no token
     *
     * @response 404
     *     If token is given but it doesn't exist for the given user
     * @response 400
     *     If token is given but has expired
     * @response 302 /
     *     If token is valid and the account is already validated
     * @response 200
     *     If token is given and correct (i.e. the account is validated)
     */
    public function show($request)
    {
        $token = $request->param('t');
        $current_user = utils\CurrentUser::get();
        if (!$token) {
            if (!$current_user) {
                return Response::redirect('login', [
                    'redirect_to' => \Minz\Url::for('account validation'),
                ]);
            } elseif ($current_user->validated_at) {
                return Response::redirect('home');
            } else {
                return Response::ok('my/validation/show.phtml');
            }
        }

        $token = models\Token::find($token);
        if (!$token) {
            return Response::notFound('my/validation/show.phtml', [
                'error' => _('The token doesn’t exist.'),
            ]);
        }

        if (!$token->isValid()) {
            return Response::badRequest('my/validation/show.phtml', [
                'error' => _('The token has expired or has been invalidated.'),
            ]);
        }

        $user = models\User::findBy(['validation_token' => $token->token]);
        if (!$user) {
            return Response::notFound('my/validation/show.phtml', [
                'error' => _('The token doesn’t exist.'),
            ]);
        }

        // No need to keep the token in database, whether or not the user is
        // already validated.
        models\Token::delete($token->token);
        $user->validation_token = null;

        if ($user->validated_at) {
            return Response::redirect('home');
        }

        $user->validated_at = \Minz\Time::now();

        $app_conf = \Minz\Configuration::$application;
        if ($app_conf['subscriptions_enabled']) {
            $subscriptions_service = new services\Subscriptions(
                $app_conf['subscriptions_host'],
                $app_conf['subscriptions_private_key']
            );
            $account = $subscriptions_service->account($user->email);
            if ($account) {
                $user->subscription_account_id = $account['id'];
                $user->subscription_expired_at = $account['expired_at'];
            } else {
                \Minz\Log::error("Can’t get a subscription account for user {$user->id}."); // @codeCoverageIgnore
            }
        }

        $user->save();

        return Response::ok('my/validation/show.phtml', [
            'success' => true,
        ]);
    }

    /**
     * Resend a validation email.
     *
     * A new token is generated if the current one expires soon (i.e. <= 30
     * minutes).
     *
     * @request_param string csrf
     * @request_param string from default: /
     *
     * @response 302 /login?redirect_to=:from
     *     If the user is not connected
     * @response 302 :from
     */
    public function resendEmail($request)
    {
        $from = $request->param('from', \Minz\Url::for('home'));
        $csrf = new \Minz\CSRF();
        $user = utils\CurrentUser::get();

        if (!$user) {
            return Response::redirect('login', ['redirect_to' => $from]);
        }

        if (!$csrf->validateToken($request->param('csrf'))) {
            utils\Flash::set('error', _('A security verification failed: you should retry to submit the form.'));
            return Response::found($from);
        }

        if ($user->validated_at) {
            // nothing to do, the user is already validated
            return Response::found($from);
        }

        if (!$user->validation_token) {
            // The user has no token? This should not happen, but maybe the
            // admin changed something in DB... who knows?
            $token = models\Token::init(1, 'day', 16);
            $token->save();
            $user->validation_token = $token->token;
            $user->save();
        }

        $token = models\Token::find($user->validation_token);
        if ($token->expiresIn(30, 'minutes') || $token->isInvalidated()) {
            // the token will expire soon, let's regenerate a new one
            $token = models\Token::init(1, 'day', 16);
            $token->save();
            $user->validation_token = $token->token;
            $user->save();
        }

        $mailer_job = new jobs\Mailer();
        $mailer_job->performLater('Users', 'sendAccountValidationEmail', $user->id);

        utils\Flash::set('status', 'validation_email_sent');
        return Response::found($from);
    }
}
