<?php

namespace flusio\jobs;

use flusio\models;
use flusio\services;
use flusio\utils;

/**
 * Fetch links that weren't fetched yet, for a given user.
 *
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class UserLinksFetcher extends Job
{
    /**
     * @param string $user_id
     */
    public function perform($user_id)
    {
        $user = models\User::find($user_id);
        if (!$user) {
            \Minz\Log::warning("User {$user_id} no longer exists, skipping fetching links");
            return;
        }
        utils\Locale::setCurrentLocale($user->locale);

        $links = models\Link::daoToList('listToFetch', $user->id, 10);
        if (!$links) {
            // nope, nothing to do
            return;
        }

        // we fetch the links and save them one by one
        $fetch_service = new services\LinkFetcher();
        foreach ($links as $link) {
            $fetch_service->fetch($link);
            $link->save();
        }

        // we got 10 links max, maybe there's more links to fetch? If yes, we
        // register the job again.
        $count_to_fetch = models\Link::daoCall('countToFetch', $user->id);
        if ($count_to_fetch > 0) {
            $this->performLater($user->id);
        }
    }
}
