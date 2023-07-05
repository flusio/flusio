<?php

namespace flusio\controllers\collections;

use Minz\Request;
use Minz\Response;
use flusio\auth;
use flusio\models;
use flusio\utils;

class Shares
{
    /**
     * @request_param string id
     * @request_param string from
     *
     * @response 302 /login?redirect_to=:from
     *     If not connected
     * @response 404
     *     If the collection doesn’t exist or is inaccessible
     * @response 200
     *     On success
     */
    public function index(Request $request): Response
    {
        $current_user = auth\CurrentUser::get();
        $from = $request->param('from', '');
        if (!$current_user) {
            return Response::redirect('login', [
                'redirect_to' => $from,
            ]);
        }

        $collection_id = $request->param('id', '');
        $collection = models\Collection::find($collection_id);

        $can_update = $collection && auth\CollectionsAccess::canUpdate($current_user, $collection);
        if (!$can_update) {
            return Response::notFound('not_found.phtml');
        }

        return Response::ok('collections/shares/index.phtml', [
            'collection' => $collection,
            'from' => $from,
            'type' => 'read',
            'user_id' => '',
        ]);
    }

    /**
     * @request_param string id
     * @request_param string user_id
     * @request_param string type
     * @request_param string csrf
     * @request_param string from
     *
     * @response 302 /login?redirect_to=:from
     *     If not connected
     * @response 404
     *     If the collection doesn’t exist or is inaccessible
     * @response 400
     *     If user_id is the same as the current user id, or user_id doesn't
     *     exist, or collection is already shared with user_id, or type or CSRF
     *     is invalid
     * @response 200
     *     On success
     */
    public function create(Request $request): Response
    {
        $current_user = auth\CurrentUser::get();
        $from = $request->param('from', '');
        if (!$current_user) {
            return Response::redirect('login', [
                'redirect_to' => $from,
            ]);
        }

        $collection_id = $request->param('id', '');
        $user_id = $request->param('user_id', '');
        $type = $request->param('type', '');
        $csrf = $request->param('csrf', '');

        // We also accept profiles URLs
        $user_id = $this->extractUserId($user_id);

        $collection = models\Collection::find($collection_id);
        $can_update = $collection && auth\CollectionsAccess::canUpdate($current_user, $collection);
        if (!$can_update) {
            return Response::notFound('not_found.phtml');
        }

        if (!\Minz\Csrf::validate($csrf)) {
            return Response::badRequest('collections/shares/index.phtml', [
                'collection' => $collection,
                'from' => $from,
                'type' => $type,
                'user_id' => $user_id,
                'error' => _('A security verification failed: you should retry to submit the form.'),
            ]);
        }

        if ($current_user->id === $user_id) {
            return Response::badRequest('collections/shares/index.phtml', [
                'collection' => $collection,
                'from' => $from,
                'type' => $type,
                'user_id' => $user_id,
                'errors' => [
                    'user_id' => _('You can’t share access with yourself.'),
                ],
            ]);
        }

        $support_user = models\User::supportUser();
        if (
            !models\User::exists($user_id) ||
            $support_user->id === $user_id
        ) {
            return Response::badRequest('collections/shares/index.phtml', [
                'collection' => $collection,
                'from' => $from,
                'type' => $type,
                'user_id' => $user_id,
                'errors' => [
                    'user_id' => _('This user doesn’t exist.'),
                ],
            ]);
        }

        $existing_collection_share = models\CollectionShare::findBy([
            'collection_id' => $collection->id,
            'user_id' => $user_id,
        ]);
        if ($existing_collection_share) {
            return Response::badRequest('collections/shares/index.phtml', [
                'collection' => $collection,
                'from' => $from,
                'type' => $type,
                'user_id' => $user_id,
                'errors' => [
                    'user_id' => _('The collection is already shared with this user.'),
                ],
            ]);
        }

        $collection_share = new models\CollectionShare($user_id, $collection->id, $type);
        $errors = $collection_share->validate();
        if ($errors) {
            return Response::badRequest('collections/shares/index.phtml', [
                'collection' => $collection,
                'from' => $from,
                'type' => $type,
                'user_id' => $user_id,
                'errors' => $errors,
            ]);
        }

        $collection_share->save();

        return Response::ok('collections/shares/index.phtml', [
            'collection' => $collection,
            'from' => $from,
            'type' => 'read',
            'user_id' => '',
        ]);
    }

    /**
     * @request_param string id
     * @request_param string from
     * @request_param string csrf
     *
     * @response 302 /login?redirect_to=:from
     *     If not connected
     * @response 404
     *     If the shared collection doesn’t exist or is inaccessible
     * @response 400
     *     If CSRF is invalid
     * @response 200
     *     On success
     */
    public function delete(Request $request): Response
    {
        $current_user = auth\CurrentUser::get();
        $from = $request->param('from', '');
        if (!$current_user) {
            return Response::redirect('login', [
                'redirect_to' => $from,
            ]);
        }

        $csrf = $request->param('csrf', '');
        $collection_share_id = $request->paramInteger('id', -1);
        $collection_share = models\CollectionShare::find($collection_share_id);
        if (!$collection_share) {
            return Response::notFound('not_found.phtml');
        }

        $collection = models\Collection::find($collection_share->collection_id);
        $can_update = $collection && auth\CollectionsAccess::canUpdate($current_user, $collection);
        if (!$can_update) {
            return Response::notFound('not_found.phtml');
        }

        if (!\Minz\Csrf::validate($csrf)) {
            return Response::badRequest('collections/shares/index.phtml', [
                'collection' => $collection,
                'from' => $from,
                'type' => 'read',
                'user_id' => '',
                'error' => _('A security verification failed: you should retry to submit the form.'),
            ]);
        }

        models\CollectionShare::delete($collection_share->id);

        return Response::ok('collections/shares/index.phtml', [
            'collection' => $collection,
            'from' => $from,
            'type' => 'read',
            'user_id' => '',
        ]);
    }

    /**
     * Extract a user_id from a string.
     *
     * The string can be the user_id, or the URL to the profile of a user.
     * This method doesn't check if the id exists or is valid.
     */
    private function extractUserId(string $string): string
    {
        $string = trim($string);
        $url = \SpiderBits\Url::sanitize($string);
        $base_url = \Minz\Url::baseUrl();

        if (!str_starts_with($url, $base_url)) {
            return $string;
        }

        $parsed_url = parse_url($url);
        $path = $parsed_url['path'] ?? '/';

        $result = preg_match('#^/p/(?P<id>\d+)$#', $path, $matches);
        if ($result !== 1) {
            return $string;
        }

        $user_id = $matches['id'] ?? '';

        if (!models\User::exists($user_id)) {
            return $string;
        }

        return $user_id;
    }
}
