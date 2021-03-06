<?php

namespace flusio\models;

use flusio\utils;

/**
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Message extends \Minz\Model
{
    use DaoConnector;

    public const PROPERTIES = [
        'id' => [
            'type' => 'string',
            'required' => true,
        ],

        'created_at' => 'datetime',

        'content' => [
            'type' => 'string',
            'required' => true,
        ],

        'link_id' => [
            'type' => 'string',
            'required' => true,
        ],

        'user_id' => [
            'type' => 'string',
            'required' => true,
        ],
    ];

    /**
     * @param string $user_id
     * @param string $link_id
     * @param string $content
     *
     * @return \flusio\models\Message
     */
    public static function init($user_id, $link_id, $content)
    {
        return new self([
            'id' => utils\Random::hex(32),
            'content' => trim($content),
            'link_id' => $link_id,
            'user_id' => $user_id,
        ]);
    }

    /**
     * Return the author of the message
     *
     * @return \flusio\models\User
     */
    public function user()
    {
        return User::find($this->user_id);
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

            if ($property === 'content' && $code === 'required') {
                $formatted_error = _('The message is required.');
            } else {
                $formatted_error = $error['description']; // @codeCoverageIgnore
            }

            $formatted_errors[$property] = $formatted_error;
        }

        return $formatted_errors;
    }
}
