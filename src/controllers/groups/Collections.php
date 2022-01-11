<?php

namespace flusio\controllers\groups;

use Minz\Response;
use flusio\auth;
use flusio\models;

/**
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Collections
{
    /**
     * List the collections of a group
     *
     * @request_param string id
     * @request_param string only One of: followed, owned or all (default)
     *
     * @response 302 /login?redirect_to=/groups/:id/collections
     *     If not connected
     * @response 404
     *     If the group doesn't exist or is inaccessible to the user
     * @response 200
     *     On success
     */
    public function index($request)
    {
        $user = auth\CurrentUser::get();
        $group_id = $request->param('id');
        $only = $request->param('only', 'all');

        if (!$user) {
            return Response::redirect('login', [
                'redirect_to' => \Minz\Url::for('group collections', ['id' => $group_id]),
            ]);
        }

        $group = models\Group::find($group_id);
        if (!auth\GroupsAccess::canView($user, $group)) {
            return Response::notFound('not_found.phtml');
        }

        if (!in_array($only, ['followed', 'owned', 'all'])) {
            $only = 'all';
        }

        $variables = [
            'group' => $group,
            'followed_collections' => [],
            'collections' => [],
        ];

        if ($only === 'owned' || $only === 'all') {
            $variables['collections'] = $group->collections();
        }

        if ($only === 'followed' || $only === 'all') {
            $variables['followed_collections'] = $group->followedCollections();
        }

        return Response::ok('groups/collections/index.phtml', $variables);
    }
}