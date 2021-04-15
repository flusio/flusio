<?php

namespace flusio\auth;

/**
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class CollectionsAccess
{
    public static function canView($user, $collection)
    {
        if (!$collection) {
            return false;
        }

        if ($collection->is_public) {
            return true;
        }

        return $user && $user->id === $collection->user_id;
    }

    public static function canUpdate($user, $collection)
    {
        return (
            $user && $collection &&
            $user->id === $collection->user_id &&
            $collection->type === 'collection'
        );
    }

    public static function canDelete($user, $collection)
    {
        return (
            $user && $collection &&
            $user->id === $collection->user_id &&
            $collection->type === 'collection'
        );
    }
}