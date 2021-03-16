<?php

namespace flusio\cli;

use Minz\Response;
use flusio\models;
use flusio\utils;

/**
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Feeds
{
    /**
     * Add a feed to the system.
     *
     * @request_param url
     *
     * @response 400 if url param is missing or invalid
     * @response 200
     */
    public function add($request)
    {
        $feed_url = $request->param('url');
        $user = models\User::supportUser();
        $collection = models\Collection::initFeed($user->id, $feed_url);

        $errors = $collection->validate();
        if ($errors) {
            $errors = implode(' ', $errors);
            return Response::text(400, "Collection creation failed: {$errors}");
        }

        // create the collection before fetching it
        $collection->save();

        $fetch_service = new services\FeedFetcher();
        $fetch_service->fetch($collection);
        $collection->save();

        $links = $fetch_service->fetchLinks($collection);
        models\Link::bulkInsertInCollection($links);

        return Response::text(200, "Feed {$collection->feed_url} ({$collection->name}) has been added.");
    }
}
