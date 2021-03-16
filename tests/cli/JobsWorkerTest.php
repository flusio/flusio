<?php

namespace flusio\cli;

use flusio\jobs;
use flusio\models;

class JobsWorkerTest extends \PHPUnit\Framework\TestCase
{
    use \tests\FakerHelper;
    use \Minz\Tests\FactoriesHelper;
    use \Minz\Tests\InitializerHelper;
    use \Minz\Tests\ApplicationHelper;
    use \Minz\Tests\TimeHelper;
    use \Minz\Tests\MailerAsserts;
    use \Minz\Tests\ResponseAsserts;

    /**
     * @beforeClass
     */
    public static function loadApplication()
    {
        self::$application = new \flusio\cli\Application();
    }

    public function testIndexRendersCorrectly()
    {
        $job_dao = new models\dao\Job();
        $job_perform_at_1 = substr(str_replace('T', ' ', $this->fake('iso8601')), 0, -2);
        $job_attempts_1 = $this->fake('numberBetween', 0, 100);
        $job_id_1 = $this->create('job', [
            'perform_at' => $job_perform_at_1,
            'locked_at' => $this->fake('iso8601'),
            'number_attempts' => $job_attempts_1,
            'handler' => json_encode([
                'job_class' => 'flusio\jobs\Mailer',
                'job_args' => [],
            ]),
        ]);
        $job_perform_at_2 = substr(str_replace('T', ' ', $this->fake('iso8601')), 0, -2);
        $job_attempts_2 = $this->fake('numberBetween', 0, 100);
        $job_id_2 = $this->create('job', [
            'perform_at' => $job_perform_at_2,
            'locked_at' => null,
            'number_attempts' => $job_attempts_2,
            'handler' => json_encode([
                'job_class' => 'flusio\jobs\Mailer',
                'job_args' => [],
            ]),
        ]);
        $job_perform_at_3 = substr(str_replace('T', ' ', $this->fake('iso8601')), 0, -2);
        $job_attempts_3 = $this->fake('numberBetween', 0, 100);
        $job_id_3 = $this->create('job', [
            'perform_at' => $job_perform_at_3,
            'locked_at' => null,
            'failed_at' => $this->fake('iso8601'),
            'number_attempts' => $job_attempts_3,
            'handler' => json_encode([
                'job_class' => 'flusio\jobs\Mailer',
                'job_args' => [],
            ]),
        ]);
        $job_perform_at_4 = substr(str_replace('T', ' ', $this->fake('iso8601')), 0, -2);
        $job_attempts_4 = $this->fake('numberBetween', 0, 100);
        $job_id_4 = $this->create('job', [
            'perform_at' => $job_perform_at_4,
            'locked_at' => $this->fake('iso8601'),
            'failed_at' => $this->fake('iso8601'),
            'number_attempts' => $job_attempts_4,
            'handler' => json_encode([
                'job_class' => 'flusio\jobs\Mailer',
                'job_args' => [],
            ]),
        ]);

        $response = $this->appRun('cli', '/jobs');

        $this->assertResponse($response, 200, <<<TEXT
        job#{$job_id_1} at {$job_perform_at_1} {$job_attempts_1} attempts (locked)
        job#{$job_id_2} at {$job_perform_at_2} {$job_attempts_2} attempts
        job#{$job_id_3} at {$job_perform_at_3} {$job_attempts_3} attempts (failed)
        job#{$job_id_4} at {$job_perform_at_4} {$job_attempts_4} attempts (locked) (failed)
        TEXT);
    }

    public function testClearDeleteTheJobsAndRendersCorrectly()
    {
        $job_dao = new models\dao\Job();
        $this->create('job', [
            'perform_at' => \Minz\Time::ago(1, 'hour')->format(\Minz\Model::DATETIME_FORMAT),
            'locked_at' => \Minz\Time::fromNow(30, 'minutes')->format(\Minz\Model::DATETIME_FORMAT),
            'handler' => json_encode([
                'job_class' => 'flusio\jobs\Mailer',
                'job_args' => [],
            ]),
        ]);
        $this->create('job', [
            'perform_at' => \Minz\Time::fromNow(1, 'hour')->format(\Minz\Model::DATETIME_FORMAT),
            'locked_at' => null,
            'handler' => json_encode([
                'job_class' => 'flusio\jobs\Mailer',
                'job_args' => [],
            ]),
        ]);

        $this->assertSame(2, $job_dao->count());

        $response = $this->appRun('cli', '/jobs/clear');

        $this->assertResponse($response, 200, '2 jobs deleted');
        $this->assertSame(0, $job_dao->count());
    }

    public function testRunExecutesAJobAndRendersCorrectly()
    {
        $job_dao = new models\dao\Job();
        $token = $this->create('token');
        $user_id = $this->create('user', [
            'validation_token' => $token,
        ]);
        $job_id = $this->create('job', [
            'perform_at' => \Minz\Time::ago(1, 'second')->format(\Minz\Model::DATETIME_FORMAT),
            'locked_at' => null,
            'number_attempts' => $this->fake('numberBetween', 0, 25),
            'handler' => json_encode([
                'job_class' => 'flusio\jobs\Mailer',
                'job_args' => ['Users', 'sendAccountValidationEmail', $user_id],
            ]),
        ]);

        $this->assertEmailsCount(0);
        $this->assertSame(1, $job_dao->count());

        $response = $this->appRun('cli', '/jobs/run');

        $this->assertResponse($response, 200, "job#{$job_id}: done");
        $this->assertEmailsCount(1);
        $this->assertSame(0, $job_dao->count());
    }

    public function testRunDoesNotSelectJobToBeExecutedLater()
    {
        $job_dao = new models\dao\Job();
        $token = $this->create('token');
        $user_id = $this->create('user', [
            'validation_token' => $token,
        ]);
        $job_id = $this->create('job', [
            'perform_at' => \Minz\Time::fromNow(1, 'minute')->format(\Minz\Model::DATETIME_FORMAT),
            'locked_at' => null,
            'number_attempts' => $this->fake('numberBetween', 0, 25),
            'handler' => json_encode([
                'job_class' => 'flusio\jobs\Mailer',
                'job_args' => ['Users', 'sendAccountValidationEmail', $user_id],
            ]),
        ]);

        $response = $this->appRun('cli', '/jobs/run');

        $this->assertResponse($response, 204);
        $this->assertEmailsCount(0);
        $this->assertSame(1, $job_dao->count());
    }

    public function testRunDoesNotSelectLockedJob()
    {
        $job_dao = new models\dao\Job();
        $token = $this->create('token');
        $user_id = $this->create('user', [
            'validation_token' => $token,
        ]);
        $job_id = $this->create('job', [
            'perform_at' => \Minz\Time::ago(1, 'second')->format(\Minz\Model::DATETIME_FORMAT),
            'locked_at' => $this->fake('iso8601'),
            'number_attempts' => $this->fake('numberBetween', 0, 25),
            'handler' => json_encode([
                'job_class' => 'flusio\jobs\Mailer',
                'job_args' => ['Users', 'sendAccountValidationEmail', $user_id],
            ]),
        ]);

        $response = $this->appRun('cli', '/jobs/run');

        $this->assertResponse($response, 204);
        $this->assertEmailsCount(0);
        $this->assertSame(1, $job_dao->count());
    }

    public function testRunDoesNotSelectJobWithTooManyAttempts()
    {
        $job_dao = new models\dao\Job();
        $token = $this->create('token');
        $user_id = $this->create('user', [
            'validation_token' => $token,
        ]);
        $job_id = $this->create('job', [
            'perform_at' => \Minz\Time::ago(1, 'second')->format(\Minz\Model::DATETIME_FORMAT),
            'locked_at' => null,
            'number_attempts' => $this->fake('numberBetween', 26, 100),
            'handler' => json_encode([
                'job_class' => 'flusio\jobs\Mailer',
                'job_args' => ['Users', 'sendAccountValidationEmail', $user_id],
            ]),
        ]);

        $response = $this->appRun('cli', '/jobs/run');

        $this->assertResponse($response, 204);
        $this->assertEmailsCount(0);
        $this->assertSame(1, $job_dao->count());
    }

    public function testRunKeepsFailingJobs()
    {
        $now = $this->fake('dateTime');
        $this->freeze($now);
        $job_dao = new models\dao\Job();
        $number_attempts = $this->fake('numberBetween', 0, 25);
        $job_id = $this->create('job', [
            'perform_at' => \Minz\Time::ago(1, 'second')->format(\Minz\Model::DATETIME_FORMAT),
            'locked_at' => null,
            'number_attempts' => $number_attempts,
            'handler' => json_encode([
                'job_class' => 'tests\jobs\MyFailingJob',
                'job_args' => [],
            ]),
        ]);

        $response = $this->appRun('cli', '/jobs/run');

        $this->assertResponse($response, 500, "job#{$job_id}: failed");
        $this->assertSame(1, $job_dao->count());
        $db_job = $job_dao->find($job_id);
        $expected_perform_at = \Minz\Time::fromNow(5 + pow($number_attempts + 1, 4), 'seconds');
        $perform_at = date_create_from_format(\Minz\Model::DATETIME_FORMAT, $db_job['perform_at']);
        $failed_at = date_create_from_format(\Minz\Model::DATETIME_FORMAT, $db_job['failed_at']);
        $this->assertNull($db_job['locked_at']);
        $this->assertSame($number_attempts + 1, $db_job['number_attempts']);
        $this->assertSame($expected_perform_at->getTimestamp(), $perform_at->getTimestamp());
        $this->assertStringContainsString('I failed you :(', $db_job['last_error']);
        $this->assertSame($now->getTimestamp(), $failed_at->getTimestamp());
    }

    public function testRunReschedulesJobWithFrequency()
    {
        $now = $this->fake('dateTime');
        $this->freeze($now);
        $job_dao = new models\dao\Job();
        $token = $this->create('token');
        $user_id = $this->create('user', [
            'validation_token' => $token,
        ]);
        $job_id = $this->create('job', [
            'perform_at' => $now->format(\Minz\Model::DATETIME_FORMAT),
            'frequency' => '+1 day',
            'locked_at' => null,
            'number_attempts' => 0,
            'handler' => json_encode([
                'job_class' => 'flusio\jobs\Mailer',
                'job_args' => ['Users', 'sendAccountValidationEmail', $user_id],
            ]),
        ]);

        $response = $this->appRun('cli', '/jobs/run');

        $this->assertResponse($response, 200, "job#{$job_id}: done");
        $this->assertSame(1, $job_dao->count());
        $db_job = $job_dao->find($job_id);
        $perform_at = date_create_from_format(\Minz\Model::DATETIME_FORMAT, $db_job['perform_at']);
        $this->assertSame(
            \Minz\Time::fromNow(1, 'day')->getTimestamp(),
            $perform_at->getTimestamp(),
        );
        $this->assertNull($db_job['locked_at']);
    }

    public function testRunReschedulesJobWithFrequencyEvenIfFailing()
    {
        $now = $this->fake('dateTime');
        $this->freeze($now);
        $job_dao = new models\dao\Job();
        $job_id = $this->create('job', [
            'perform_at' => $now->format(\Minz\Model::DATETIME_FORMAT),
            'frequency' => '+1 month',
            'locked_at' => null,
            'number_attempts' => 0,
            'handler' => json_encode([
                'job_class' => 'tests\jobs\MyFailingJob',
                'job_args' => [],
            ]),
        ]);

        $response = $this->appRun('cli', '/jobs/run');

        $this->assertResponse($response, 500, "job#{$job_id}: failed");
        $this->assertSame(1, $job_dao->count());
        $db_job = $job_dao->find($job_id);
        $perform_at = date_create_from_format(\Minz\Model::DATETIME_FORMAT, $db_job['perform_at']);
        $this->assertSame(
            \Minz\Time::fromNow(1, 'month')->getTimestamp(),
            $perform_at->getTimestamp(),
        );
        $this->assertNull($db_job['locked_at']);
    }

    public function testRunReschedulesJobWithTooManyAttempts()
    {
        $now = $this->fake('dateTime');
        $this->freeze($now);
        $job_dao = new models\dao\Job();
        $token = $this->create('token');
        $user_id = $this->create('user', [
            'validation_token' => $token,
        ]);
        $number_attempts = $this->fake('numberBetween', 26, 100);
        $job_id = $this->create('job', [
            'perform_at' => $now->format(\Minz\Model::DATETIME_FORMAT),
            'frequency' => '+1 day',
            'locked_at' => null,
            'number_attempts' => $number_attempts,
            'handler' => json_encode([
                'job_class' => 'flusio\jobs\Mailer',
                'job_args' => ['Users', 'sendAccountValidationEmail', $user_id],
            ]),
        ]);

        $response = $this->appRun('cli', '/jobs/run');

        $this->assertResponse($response, 200, "job#{$job_id}: done");
        $this->assertSame(1, $job_dao->count());
        $db_job = $job_dao->find($job_id);
        $perform_at = date_create_from_format(\Minz\Model::DATETIME_FORMAT, $db_job['perform_at']);
        $this->assertSame(
            \Minz\Time::fromNow(1, 'day')->getTimestamp(),
            $perform_at->getTimestamp(),
        );
        $this->assertSame($number_attempts + 1, $db_job['number_attempts']);
        $this->assertNull($db_job['locked_at']);
    }

    public function testWatchRendersCorrectly()
    {
        $job_dao = new models\dao\Job();
        $token = $this->create('token');
        $user_id = $this->create('user', [
            'validation_token' => $token,
        ]);
        $job_id = $this->create('job', [
            'perform_at' => \Minz\Time::ago(1, 'second')->format(\Minz\Model::DATETIME_FORMAT),
            'locked_at' => null,
            'number_attempts' => $this->fake('numberBetween', 0, 25),
            'handler' => json_encode([
                'job_class' => 'flusio\jobs\Mailer',
                'job_args' => ['Users', 'sendAccountValidationEmail', $user_id],
            ]),
        ]);

        \pcntl_alarm(3); // the worker will get a SIGALRM signal and stop in 3s
        $response_generator = $this->appRun('cli', '/jobs/watch');

        $response = $response_generator->current();
        $this->assertResponse($response, 200, '[Job worker started]');
        $response_generator->next();
        $response = $response_generator->current();
        $this->assertResponse($response, 200, "job#{$job_id}: done");
        $response_generator->next();
        $response = $response_generator->current();
        $this->assertResponse($response, 200, '[Job worker stopped]');
        $this->assertEmailsCount(1);
        $this->assertSame(0, $job_dao->count());
    }
}
