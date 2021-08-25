<?php

namespace flusio\jobs\scheduled;

use flusio\jobs;
use flusio\models;
use flusio\utils;

/**
 * Job to clean the system.
 *
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Cleaner extends jobs\Job
{
    /**
     * Install the job in database.
     */
    public static function install()
    {
        $job_dao = new \flusio\models\dao\Job();
        $cleaner_job = new Cleaner();

        if (!$job_dao->findBy(['name' => $cleaner_job->name])) {
            $cleaner_job->performLater();
        }
    }

    public function __construct()
    {
        parent::__construct();
        $this->perform_at = \Minz\Time::relative('tomorrow 1:00');
        $this->frequency = '+1 day';
    }

    public function perform()
    {
        $cache = new \SpiderBits\Cache(\Minz\Configuration::$application['cache_path']);
        $cache->clean();

        models\FetchLog::daoCall('deleteOlderThan', \Minz\Time::ago(3, 'days'));
        models\Token::daoCall('deleteExpired');
        models\Session::daoCall('deleteExpired');

        if (\Minz\Configuration::$application['demo']) {
            // with these two delete, the other tables should be deleted in cascade
            models\Token::deleteAll();
            models\User::deleteAll();

            $user = models\User::init('Abby', 'demo@flus.io', 'demo');
            $user->validated_at = \Minz\Time::now();
            $user->locale = utils\Locale::currentLocale();
            $user->save();

            $bookmarks_collection = models\Collection::initBookmarks($user->id);
            $bookmarks_collection->save();
        }
    }
}
