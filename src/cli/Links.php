<?php

namespace flusio\cli;

use Minz\Response;
use flusio\models;
use flusio\services;

/**
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Links
{
    /**
     * Refresh the oldest links illustration image
     *
     * @request_param integer number
     *
     * @response 200
     */
    public function refresh($request)
    {
        $number = $request->param('number', 10);

        $fetch_service = new services\LinkFetcher();
        $image_service = new services\Image();

        $links = models\Link::daoToList('listByOldestFetching', $number);
        $results = [];
        foreach ($links as $link) {
            $result = "Link #{$link->id} ({$link->url}): ";

            $info = $fetch_service->fetchUrl($link->url);
            $link->fetched_at = \Minz\Time::now();
            $link->fetched_code = $info['status'];
            if (isset($info['error'])) {
                $link->fetched_error = $info['error'];
                $result .= 'error (see fetched_error for details)';
            } elseif (isset($info['url_illustration'])) {
                $image_filename = $image_service->generatePreviews($info['url_illustration']);
                $link->image_filename = $image_filename;
                $result .= "fetched {$info['url_illustration']}";
            } else {
                $result .= "nothing";
            }

            $link->save();

            $results[] = $result;
        }

        return Response::text(200, implode("\n", $results));
    }
}
