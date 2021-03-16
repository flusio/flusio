<?php

namespace flusio\models;

use flusio\utils;

/**
 * Represent a user of flusio.
 *
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class User extends \Minz\Model
{
    use DaoConnector;

    public const PROPERTIES = [
        'id' => [
            'type' => 'string',
            'required' => true,
        ],

        'created_at' => 'datetime',

        'validated_at' => 'datetime',

        'validation_token' => 'string',

        'subscription_account_id' => [
            'type' => 'string',
        ],

        'subscription_expired_at' => [
            'type' => 'datetime',
            'required' => true,
        ],

        'email' => [
            'type' => 'string',
            'required' => true,
            'validator' => '\flusio\models\User::validateEmail',
        ],

        'username' => [
            'type' => 'string',
            'required' => true,
            'validator' => '\flusio\models\User::validateUsername',
        ],

        'password_hash' => [
            'type' => 'string',
            'required' => true,
        ],

        'locale' => [
            'type' => 'string',
            'required' => true,
            'validator' => '\flusio\models\User::validateLocale',
        ],

        'avatar_filename' => [
            'type' => 'string',
        ],

        'csrf' => [
            'type' => 'string',
            'required' => true,
        ],

        'news_preferences' => [
            'type' => 'string',
            'required' => true,
        ],

        'pocket_username' => [
            'type' => 'string',
        ],

        'pocket_access_token' => [
            'type' => 'string',
        ],

        'pocket_request_token' => [
            'type' => 'string',
        ],

        'pocket_error' => [
            'type' => 'integer',
        ],
    ];

    /**
     * Initialize the model with default values.
     *
     * @param mixed $values
     */
    public function __construct($values)
    {
        $expired_at = \Minz\Time::fromNow(1, 'month');
        parent::__construct(array_merge([
            'id' => utils\Random::hex(32),
            'subscription_expired_at' => $expired_at->format(\Minz\Model::DATETIME_FORMAT),
            'username' => '',
            'email' => '',
            'password_hash' => '',
            'locale' => \flusio\utils\Locale::DEFAULT_LOCALE,
            'csrf' => utils\Random::hex(64),
            'news_preferences' => '{}',
        ], $values));
    }

    /**
     * @param string $username
     * @param string $email
     * @param string $password
     *
     * @return \flusio\models\User
     */
    public static function init($username, $email, $password)
    {
        return new self([
            'username' => trim($username),
            'email' => utils\Email::sanitize($email),
            'password_hash' => $password ? password_hash($password, PASSWORD_BCRYPT) : '',
        ]);
    }

    /**
     * @return \flusio\models\User
     */
    public static function supportUser()
    {
        $support_email = \Minz\Configuration::$application['support_email'];
        $default_password = \flusio\utils\Random::hex(128);
        return self::findOrCreateBy([
            'email' => utils\Email::sanitize($support_email),
        ], [
            'username' => 'flusio',
            'password_hash' => password_hash($default_password, PASSWORD_BCRYPT),
            'validated_at' => \Minz\Time::now(),
        ]);
    }

    /**
     * Return the topics attached to the current user
     *
     * @return \flusio\models\Topic[]
     */
    public function topics()
    {
        return Topic::daoToList('listByUserId', $this->id);
    }

    /**
     * Return the given link if attached to the current user
     *
     * @param string $id
     *
     * @return \flusio\models\Link|null
     */
    public function link($link_id)
    {
        return Link::findBy([
            'id' => $link_id,
            'user_id' => $this->id,
        ]);
    }

    /**
     * Return the given link if attached to the current user
     *
     * @param string $url
     *
     * @return \flusio\models\Link|null
     */
    public function linkByUrl($link_url)
    {
        return Link::findBy([
            'url' => $link_url,
            'user_id' => $this->id,
        ]);
    }

    /**
     * Return the given news link if attached to the current user
     *
     * @return \flusio\models\NewsLink|null
     */
    public function newsLink($news_link_id)
    {
        return NewsLink::findBy([
            'id' => $news_link_id,
            'user_id' => $this->id,
        ]);
    }

    /**
     * Return the user' news links
     *
     * @return \flusio\models\NewsLink[]
     */
    public function newsLinks()
    {
        return NewsLink::daoToList('listComputedByUserId', $this->id);
    }

    /**
     * Return the given news link if attached to the current user
     *
     * @param string $url
     *
     * @return \flusio\models\NewsLink|null
     */
    public function newsLinkByUrl($url)
    {
        return NewsLink::findBy([
            'url' => $url,
            'user_id' => $this->id,
        ]);
    }

    /**
     * Return the user' bookmarks collection
     *
     * @return \flusio\models\Collection|null
     */
    public function bookmarks()
    {
        return Collection::findBy([
            'user_id' => $this->id,
            'type' => 'bookmarks',
        ]);
    }

    /**
     * Return the given collection if attached to the current user
     *
     * @return \flusio\models\Collection|null
     */
    public function collection($collection_id)
    {
        return Collection::findBy([
            'id' => $collection_id,
            'user_id' => $this->id,
            'type' => 'collection',
        ]);
    }

    /**
     * Return the list of collections created by the user
     *
     * @param boolean $exclude_bookmarks Default is false.
     *
     * @return \flusio\models\Collection[]
     */
    public function collections($exclude_bookmarks = false)
    {
        if ($exclude_bookmarks) {
            return Collection::listBy([
                'user_id' => $this->id,
                'type' => 'collection',
            ]);
        } else {
            return Collection::listBy([
                'user_id' => $this->id,
            ]);
        }
    }

    /**
     * Return the list of collections created by the user
     *
     * @return \flusio\models\Collection[]
     */
    public function collectionsWithNumberLinks()
    {
        return Collection::daoToList('listWithNumberLinksForUser', $this->id);
    }

    /**
     * Return the list of collections followed by the user
     *
     * @return \flusio\models\Collection[]
     */
    public function followedCollectionsWithNumberLinks()
    {
        return Collection::daoToList('listFollowedWithNumberLinksForUser', $this->id);
    }

    /**
     * @param string $collection_id
     *
     * @return boolean
     *     Return true if the current user is following the given collection.
     */
    public function isFollowing($collection_id)
    {
        $followed_collection_dao = new dao\FollowedCollection();
        $followed_collection = $followed_collection_dao->findBy([
            'user_id' => $this->id,
            'collection_id' => $collection_id,
        ]);
        return $followed_collection !== null;
    }

    /**
     * Make the current user following the given collection.
     *
     * Be careful to check isFollowing() is returning false before calling this
     * method.
     *
     * @param string $collection_id
     *
     * @param integer The id of the create FollowedCollection object in db
     */
    public function follow($collection_id)
    {
        $followed_collection_dao = new dao\FollowedCollection();
        return $followed_collection_dao->create([
            'created_at' => \Minz\Time::now()->format(\Minz\Model::DATETIME_FORMAT),
            'user_id' => $this->id,
            'collection_id' => $collection_id,
        ]);
    }

    /**
     * Make the current user unfollowing the given collection.
     *
     * Be careful to check isFollowing() is returning true before calling this
     * method.
     *
     * @param string $collection_id
     */
    public function unfollow($collection_id)
    {
        $followed_collection_dao = new dao\FollowedCollection();
        $followed_collection = $followed_collection_dao->findBy([
            'user_id' => $this->id,
            'collection_id' => $collection_id,
        ]);
        $followed_collection_dao->delete($followed_collection['id']);
    }

    /**
     * Set login credentials.
     *
     * The password is not changed if empty.
     *
     * @param string $email
     * @param string $password (default is null)
     */
    public function setLoginCredentials($email, $password = null)
    {
        $this->email = utils\Email::sanitize($email);
        if ($password) {
            $this->password_hash = password_hash($password, PASSWORD_BCRYPT);
        }
    }

    /**
     * Compare a password to the stored hash.
     *
     * @param string $password
     *
     * @return boolean Return true if the password matches the hash, else false
     */
    public function verifyPassword($password)
    {
        return password_verify($password, $this->password_hash);
    }

    /**
     * Return whether the user must validate its email or not.
     *
     * Note she has 1 day to test the application before being forced to
     * validate.
     *
     * @return boolean
     */
    public function mustValidateEmail()
    {
        return !$this->validated_at && $this->created_at < \Minz\Time::ago(1, 'day');
    }

    /**
     * Return wheter the user has a free subscription or not.
     *
     * @return boolean
     */
    public function isSubscriptionExempted()
    {
        return $this->subscription_expired_at->getTimestamp() === 0;
    }

    /**
     * Return wheter the user subscription is overdue or not.
     *
     * @return boolean
     */
    public function isSubscriptionOverdue()
    {
        return (
            !$this->isSubscriptionExempted() &&
            \Minz\Time::now() > $this->subscription_expired_at
        );
    }

    /**
     * Return whether the user should be blocked or not (email not validated or
     * subscription overdue)
     *
     * @return boolean
     */
    public function isBlocked()
    {
        $subscriptions_enabled = \Minz\Configuration::$application['subscriptions_enabled'];
        $must_validate = $this->mustValidateEmail();
        $must_renew = $subscriptions_enabled && $this->isSubscriptionOverdue();
        return $must_validate || $must_renew;
    }

    /**
     * @param string $email
     * @return boolean
     */
    public static function validateEmail($email)
    {
        return utils\Email::validate($email);
    }

    /**
     * @param string $username
     * @return boolean
     */
    public static function validateUsername($username)
    {
        return strlen($username) <= 50;
    }

    /**
     * @param string $locale
     * @return boolean
     */
    public static function validateLocale($locale)
    {
        $available_locales = \flusio\utils\Locale::availableLocales();
        return isset($available_locales[$locale]);
    }

    /**
     * Return a list of errors (if any). The array keys indicated the concerned
     * property.
     *
     * @return string[]
     */
    public function validate()
    {
        $formatted_errors = [];

        foreach (parent::validate() as $property => $error) {
            $code = $error['code'];

            if ($property === 'username' && $code === 'required') {
                $formatted_error = _('The username is required.');
            } elseif ($property === 'username') {
                $formatted_error = _('The username must be less than 50 characters.');
            } elseif ($property === 'email' && $code === 'required') {
                $formatted_error = _('The address email is required.');
            } elseif ($property === 'email') {
                $formatted_error = _('The address email is invalid.');
            } elseif ($property === 'password_hash') {
                $formatted_error = _('The password is required.');
            } elseif ($property === 'locale' && $code === 'required') {
                $formatted_error = _('The locale is required.');
            } elseif ($property === 'locale') {
                $formatted_error = _('The locale is invalid.');
            } else {
                $formatted_error = $error['description']; // @codeCoverageIgnore
            }

            $formatted_errors[$property] = $formatted_error;
        }

        return $formatted_errors;
    }
}
