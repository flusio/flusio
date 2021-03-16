<?php

namespace flusio;

/**
 * @author Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Routes
{
    /**
     * Load the routes into the router (doesn't contain CLI routes)
     *
     * @param \Minz\Router $router
     */
    public static function load($router)
    {
        $router->addRoute('get', '/', 'Pages#home', 'home');
        $router->addRoute('get', '/terms', 'Pages#terms', 'terms');
        $router->addRoute('get', '/app.webmanifest', 'Pages#webmanifest', 'webmanifest');

        // Registration
        $router->addRoute('get', '/registration', 'Registrations#new', 'registration');
        $router->addRoute('post', '/registration', 'Registrations#create', 'create user');

        // Sessions
        $router->addRoute('get', '/login', 'Sessions#new', 'login');
        $router->addRoute('post', '/login', 'Sessions#create', 'create session');
        $router->addRoute('post', '/logout', 'Sessions#delete', 'logout');
        $router->addRoute('post', '/sessions/locale', 'Sessions#changeLocale', 'change locale');

        // Onboarding
        $router->addRoute('get', '/onboarding', 'Onboarding#show', 'onboarding');
        $router->addRoute('post', '/onboarding/locale', 'Onboarding#updateLocale', 'onboarding update locale');
        $router->addRoute('post', '/onboarding/topics', 'Onboarding#updateTopics', 'onboarding update topics');

        // "My" section
        $router->addRoute('get', '/my/profile', 'my/Profile#show', 'profile');
        $router->addRoute('post', '/my/profile', 'my/Profile#update', 'update profile');
        $router->addRoute('post', '/my/profile/avatar', 'my/Profile#updateAvatar', 'update avatar');

        $router->addRoute('get', '/my/info.json', 'my/Profile#info', 'profile info');

        $router->addRoute('get', '/my/security', 'my/Security#show', 'security');
        $router->addRoute('post', '/my/security', 'my/Security#update', 'update security');
        $router->addRoute('post', '/my/security/confirm', 'my/Security#confirmPassword', 'confirm password');

        $router->addRoute('get', '/my/account', 'my/Account#show', 'account');
        $router->addRoute('get', '/my/account/validation', 'my/Account#validation', 'account validation');
        $router->addRoute(
            'post',
            '/my/account/validation/email',
            'my/Account#resendValidationEmail',
            'resend validation email'
        );
        $router->addRoute('get', '/my/account/deletion', 'my/Account#deletion', 'account deletion');
        $router->addRoute('post', '/my/account/deletion', 'my/Account#delete', 'delete account');

        $router->addRoute('get', '/my/account/subscription', 'my/Subscription#redirect', 'subscription');
        $router->addRoute('post', '/my/account/subscription', 'my/Subscription#create', 'create subscription account');

        // Importations
        $router->addRoute('post', '/importations/:id/delete', 'importations/Importations#delete', 'delete importation');

        $router->addRoute('get', '/pocket', 'importations/Pocket#show', 'pocket');
        $router->addRoute('post', '/pocket', 'importations/Pocket#import', 'import pocket');
        $router->addRoute('post', '/pocket/request', 'importations/Pocket#requestAccess', 'request pocket access');
        $router->addRoute('get', '/pocket/auth', 'importations/Pocket#authorization', 'pocket auth');
        $router->addRoute('post', '/pocket/auth', 'importations/Pocket#authorize', 'authorize pocket');

        // News page
        $router->addRoute('get', '/news', 'NewsLinks#index', 'news');
        $router->addRoute('post', '/news', 'NewsLinks#fill', 'fill news');
        $router->addRoute('get', '/news/preferences', 'NewsLinks#preferences', 'news preferences');
        $router->addRoute('post', '/news/preferences', 'NewsLinks#updatePreferences', 'update news preferences');
        $router->addRoute('get', '/news/:id/add', 'NewsLinkRemovals#adding', 'adding news');
        $router->addRoute('post', '/news/:id/add', 'NewsLinkRemovals#add', 'add news');
        $router->addRoute('post', '/news/:id/mark-as-read', 'NewsLinkRemovals#markAsRead', 'mark news as read');
        $router->addRoute('post', '/news/:id/read-later', 'NewsLinkRemovals#readLater', 'read news later');
        $router->addRoute('post', '/news/:id/remove', 'NewsLinkRemovals#remove', 'remove news');

        // Collections
        $router->addRoute('get', '/collections', 'Collections#index', 'collections');
        $router->addRoute('get', '/collections/new', 'Collections#new', 'new collection');
        $router->addRoute('post', '/collections/new', 'Collections#create', 'create collection');
        $router->addRoute('get', '/collections/discover', 'Collections#discover', 'discover collections');
        $router->addRoute('get', '/collections/:id', 'Collections#show', 'collection');
        $router->addRoute('get', '/collections/:id/edit', 'Collections#edit', 'edit collection');
        $router->addRoute('post', '/collections/:id/edit', 'Collections#update', 'update collection');
        $router->addRoute('post', '/collections/:id/delete', 'Collections#delete', 'delete collection');
        $router->addRoute('post', '/collections/:id/follow', 'Collections#follow', 'follow collection');
        $router->addRoute('post', '/collections/:id/unfollow', 'Collections#unfollow', 'unfollow collection');

        $router->addRoute('get', '/bookmarks', 'Collections#showBookmarks', 'bookmarks');

        // Links
        $router->addRoute('get', '/links/new', 'Links#new', 'new link');
        $router->addRoute('post', '/links/new', 'Links#create', 'create link');
        $router->addRoute('get', '/links/:id', 'Links#show', 'link');
        $router->addRoute('get', '/links/:id/edit', 'Links#edit', 'edit link');
        $router->addRoute('post', '/links/:id/edit', 'Links#update', 'update link');
        $router->addRoute('post', '/links/:id/delete', 'Links#delete', 'delete link');
        $router->addRoute('get', '/links/:id/fetch', 'Links#showFetch', 'show fetch link');
        $router->addRoute('post', '/links/:id/fetch', 'Links#fetch', 'fetch link');
        $router->addRoute('post', '/links/:id/mark-as-read', 'Links#markAsRead', 'mark link as read');

        // Link collections
        $router->addRoute('get', '/links/:id/collections', 'LinkCollections#index', 'link collections');
        $router->addRoute('post', '/links/:id/collections', 'LinkCollections#update', 'update link collections');

        // Messages
        $router->addRoute('get', '/links/:link_id/messages', 'LinkMessages#index', 'links/messages');
        $router->addRoute('post', '/links/:link_id/messages', 'LinkMessages#create', 'links/create message');
        $router->addRoute('post', '/messages/:id/delete', 'Messages#delete', 'delete message');

        // This should be used only for source mapping
        $router->addRoute('get', '/src/assets/*', 'Assets#show');

        $router->addRoute('get', '/design', 'Pages#design', 'design');
    }

    /**
     * Load the CLI routes into the router (contain app routes)
     *
     * @param \Minz\Router $router
     */
    public static function loadCli($router)
    {
        $router->addRoute('cli', '/', 'cli/System#usage');

        $router->addRoute('cli', '/system/secret', 'cli/System#secret');
        $router->addRoute('cli', '/system/setup', 'cli/System#setup');
        $router->addRoute('cli', '/system/rollback', 'cli/System#rollback');
        $router->addRoute('cli', '/database/status', 'cli/Database#status');

        $router->addRoute('cli', '/users/create', 'cli/Users#create');
        $router->addRoute('cli', '/users/clean', 'cli/Users#clean');

        $router->addRoute('cli', '/feeds/add', 'cli/Feeds#add');

        $router->addRoute('cli', '/topics', 'cli/Topics#index');
        $router->addRoute('cli', '/topics/create', 'cli/Topics#create');
        $router->addRoute('cli', '/topics/delete', 'cli/Topics#delete');

        $router->addRoute('cli', '/links/refresh', 'cli/Links#refresh');

        $router->addRoute('cli', '/jobs', 'cli/JobsWorker#index');
        $router->addRoute('cli', '/jobs/run', 'cli/JobsWorker#run');
        $router->addRoute('cli', '/jobs/watch', 'cli/JobsWorker#watch');
        $router->addRoute('cli', '/jobs/clear', 'cli/JobsWorker#clear');

        self::load($router);
    }
}
