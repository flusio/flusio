<?php

namespace flusio\services;

/**
 * The Subscriptions service allows to get information about a user
 * subscription.
 *
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Subscriptions
{
    /** @var string */
    private $host;

    /** @var string */
    private $private_key;

    /**
     * @param string $host
     * @param string $private_key
     */
    public function __construct($host, $private_key)
    {
        $this->host = $host;
        $this->private_key = $private_key;

        $php_os = PHP_OS;
        $flusio_version = \Minz\Configuration::$application['version'];
        $this->http = new \SpiderBits\Http();
        $this->http->user_agent = "flusio/{$flusio_version} ({$php_os}; https://github.com/flusio/flusio)";
        $this->http->timeout = 5;
    }

    /**
     * Get account information for the given email. Please always make sure the
     * email has been validated first!
     *
     * @param string $email
     *
     * @return array|null
     */
    public function account($email)
    {
        $response = $this->http->get($this->host . '/api/account', [
            'email' => $email,
        ], [
            'auth_basic' => $this->private_key . ':',
        ]);
        if ($response->success) {
            $data = json_decode($response->data, true);
            $data['expired_at'] = date_create_from_format(
                \Minz\Model::DATETIME_FORMAT,
                $data['expired_at']
            );
            return $data;
        } else {
            return null;
        }
    }

    /**
     * Get a login URL for the given account.
     *
     * @param string $account_id
     *
     * @return string|null
     */
    public function loginUrl($account_id)
    {
        $response = $this->http->get($this->host . '/api/account/login-url', [
            'account_id' => $account_id,
            'service' => 'flusio',
        ], [
            'auth_basic' => $this->private_key . ':',
        ]);
        if ($response->success) {
            $data = json_decode($response->data, true);
            return $data['url'];
        } else {
            return null;
        }
    }

    /**
     * Get the expired_at value for the given account.
     *
     * @param string $account_id
     *
     * @return string|null
     */
    public function expiredAt($account_id)
    {
        $response = $this->http->get($this->host . '/api/account/expired-at', [
            'account_id' => $account_id,
        ], [
            'auth_basic' => $this->private_key . ':',
        ]);
        if ($response->success) {
            $data = json_decode($response->data, true);
            return date_create_from_format(
                \Minz\Model::DATETIME_FORMAT,
                $data['expired_at']
            );
        } else {
            return null;
        }
    }
}
