<?php

namespace flusio;

use Minz\Response;

/**
 * Handle the requests related to the current session.
 *
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Sessions
{
    /**
     * Show the login form.
     *
     * @request_param string redirect_to A URL to redirect to (optional, default is `/`)
     *
     * @response 302 :redirect_to if already connected
     * @response 200
     *
     * @param \Minz\Request $request
     *
     * @return \Minz\Response
     */
    public function new($request)
    {
        $redirect_to = $request->param('redirect_to', \Minz\Url::for('home'));
        if (utils\CurrentUser::get()) {
            return Response::found($redirect_to);
        }

        $email = '';
        $password = '';

        if (\Minz\Configuration::$application['demo']) {
            $email = 'demo@flus.io';
            $password = 'demo';
        }

        return Response::ok('sessions/new.phtml', [
            'email' => $email,
            'password' => $password,
            'redirect_to' => $redirect_to,
        ]);
    }

    /**
     * Login / create a Session for the user
     *
     * @request_param string csrf
     * @request_param string email
     * @request_param string password
     * @request_param string redirect_to A URL to redirect to (optional, default is `/`)
     *
     * @response 302 :redirect_to if already connected
     * @response 400 if CSRF is invalid, email doesn't match with a User or if
     *               password is wrong
     * @response 302 :redirect_to if logged in
     *
     * @param \Minz\Request $request
     *
     * @return \Minz\Response
     */
    public function create($request)
    {
        $redirect_to = $request->param('redirect_to', \Minz\Url::for('home'));
        if (utils\CurrentUser::get()) {
            return Response::found($redirect_to);
        }

        $email = $request->param('email');
        $password = $request->param('password');
        $csrf = new \Minz\CSRF();

        if (!$csrf->validateToken($request->param('csrf'))) {
            return Response::badRequest('sessions/new.phtml', [
                'email' => $email,
                'password' => $password,
                'redirect_to' => $redirect_to,
                'error' => _('A security verification failed: you should retry to submit the form.'),
            ]);
        }

        $user = models\User::findBy([
            'email' => utils\Email::sanitize($email),
        ]);
        if (!$user) {
            return Response::badRequest('sessions/new.phtml', [
                'email' => $email,
                'password' => $password,
                'redirect_to' => $redirect_to,
                'errors' => [
                    'email' => _('We can’t find any account with this email address.'),
                ],
            ]);
        }

        if (!$user->verifyPassword($password)) {
            return Response::badRequest('sessions/new.phtml', [
                'email' => $email,
                'password' => $password,
                'redirect_to' => $redirect_to,
                'errors' => [
                    'password_hash' => _('The password is incorrect.'),
                ],
            ]);
        }

        // The session cookie will probably expire before, but it's another
        // security barrier.
        $token = models\Token::init(1, 'month');
        $token->save();

        $session_name = utils\Browser::format($request->header('HTTP_USER_AGENT', ''));
        $ip = $request->header('REMOTE_ADDR', 'unknown');
        $session = models\Session::init($session_name, $ip);
        $session->user_id = $user->id;
        $session->token = $token->token;
        $session->save();

        utils\CurrentUser::setSessionToken($token->token);

        $response = Response::found($redirect_to);
        $response->setCookie('flusio_session_token', $token->token, [
            'expires' => $token->expired_at->getTimestamp(),
            'samesite' => 'Lax',
        ]);
        return $response;
    }

    /**
     * Change the current locale.
     *
     * @request_param string csrf
     * @request_param string locale
     * @request_param string redirect_to A URL to redirect to (optional, default is `/`)
     *
     * @response 302 :redirect_to
     *
     * @param \Minz\Request $request
     *
     * @return \Minz\Response
     */
    public function changeLocale($request)
    {
        $csrf = new \Minz\CSRF();
        $redirect_to = $request->param('redirect_to', \Minz\Url::for('home'));
        if (!$csrf->validateToken($request->param('csrf'))) {
            return Response::found($redirect_to);
        }

        $locale = $request->param('locale');
        $available_locales = utils\Locale::availableLocales();
        if (isset($available_locales[$locale])) {
            $_SESSION['locale'] = $locale;
        } else {
            \Minz\Log::warning(
                "[Sessions#changeLocale] Tried to set invalid `{$locale}` locale."
            );
        }

        return Response::found($redirect_to);
    }

    /**
     * Delete the current user session and logout the user.
     *
     * @request_param string csrf
     *
     * @response 302 /
     *
     * @param \Minz\Request $request
     *
     * @return \Minz\Response
     */
    public function delete($request)
    {
        $current_user = utils\CurrentUser::get();
        if (!$current_user) {
            return Response::redirect('home');
        }

        $csrf = new \Minz\CSRF();
        if (!$csrf->validateToken($request->param('csrf'))) {
            utils\Flash::set('error', _('A security verification failed.'));
            return Response::redirect('home');
        }

        $current_session_token = utils\CurrentUser::sessionToken();
        $session = models\Session::findBy(['token' => $current_session_token]);
        models\Session::delete($session->id);
        utils\CurrentUser::reset();

        $response = Response::redirect('home');
        $response->removeCookie('flusio_session_token');
        return $response;
    }
}
