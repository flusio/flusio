<?php

namespace flusio\models;

use flusio\utils;

/**
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Link extends \Minz\Model
{
    use DaoConnector;

    public const PROPERTIES = [
        'id' => [
            'type' => 'string',
            'required' => true,
        ],

        'created_at' => 'datetime',

        'title' => [
            'type' => 'string',
            'required' => true,
        ],

        'url' => [
            'type' => 'string',
            'required' => true,
            'validator' => '\flusio\models\Link::validateUrl',
        ],

        'is_hidden' => [
            'type' => 'boolean',
            'required' => true,
        ],

        'reading_time' => [
            'type' => 'integer',
            'required' => true,
        ],

        'image_filename' => [
            'type' => 'string',
        ],

        'fetched_at' => [
            'type' => 'datetime',
        ],

        'fetched_code' => [
            'type' => 'integer',
        ],

        'fetched_error' => [
            'type' => 'string',
        ],

        'user_id' => [
            'type' => 'string',
            'required' => true,
        ],

        'feed_entry_id' => [
            'type' => 'string',
        ],

        'number_comments' => [
            'type' => 'integer',
            'computed' => true,
        ],

        'news_via_type' => [
            'type' => 'string',
            'computed' => true,
        ],

        'news_via_collection_id' => [
            'type' => 'string',
            'computed' => true,
        ],

        'news_value' => [
            'type' => 'integer',
            'computed' => true,
        ],
    ];

    /**
     * @param string $url
     * @param string $user_id
     * @param boolean|string $is_hidden
     *
     * @return \flusio\models\Link
     */
    public static function init($url, $user_id, $is_hidden)
    {
        $url = \SpiderBits\Url::sanitize($url);
        return new self([
            'id' => utils\Random::timebased(),
            'title' => $url,
            'url' => $url,
            'is_hidden' => filter_var($is_hidden, FILTER_VALIDATE_BOOLEAN),
            'user_id' => $user_id,
            'reading_time' => 0,
            'fetched_code' => 0,
        ]);
    }

    /**
     * @param \flusio\models\NewsLink $news_link
     * @param string $user_id
     *
     * @return \flusio\models\Link
     */
    public static function initFromNews($news_link, $user_id)
    {
        return new self([
            'id' => utils\Random::timebased(),
            'title' => $news_link->title,
            'url' => $news_link->url,
            'image_filename' => $news_link->image_filename,
            'is_hidden' => false,
            'reading_time' => $news_link->reading_time,
            'fetched_at' => \Minz\Time::now(),
            'fetched_code' => 200,
            'user_id' => $user_id,
        ]);
    }

    /**
     * Return the owner of the link.
     *
     * @return \flusio\models\User
     */
    public function owner()
    {
        return User::find($this->user_id);
    }

    /**
     * Return the collections attached to the current link
     *
     * @return \flusio\models\Collection[]
     */
    public function collections()
    {
        return Collection::daoToList('listByLinkId', $this->id);
    }

    /**
     * Return the messages attached to the current link
     *
     * @return \flusio\models\Message[]
     */
    public function messages()
    {
        return Message::listBy([
            'link_id' => $this->id,
        ]);
    }

    /**
     * @return string
     */
    public function host()
    {
        return \flusio\utils\Belt::host($this->url);
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

            if ($property === 'url' && $code === 'required') {
                $formatted_error = _('The link is required.');
            } elseif ($property === 'title' && $code === 'required') {
                $formatted_error = _('The title is required.');
            } else {
                $formatted_error = $error['description'];
            }

            $formatted_errors[$property] = $formatted_error;
        }

        return $formatted_errors;
    }

    /**
     * @param string $url
     * @return boolean
     */
    public static function validateUrl($url)
    {
        $validate = filter_var($url, FILTER_VALIDATE_URL) !== false;
        if (!$validate) {
            return _('The link is invalid.'); // @codeCoverageIgnore
        }

        $parsed_url = parse_url($url);
        if ($parsed_url['scheme'] !== 'http' && $parsed_url['scheme'] !== 'https') {
            return _('Link scheme must be either http or https.');
        }

        if (isset($parsed_url['pass'])) {
            return _('The link must not include a password as it’s sensitive data.'); // @codeCoverageIgnore
        }

        return true;
    }
}
