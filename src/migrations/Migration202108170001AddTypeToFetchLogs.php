<?php

namespace flusio\migrations;

class Migration202108170001AddTypeToFetchLogs
{
    public function migrate()
    {
        $database = \Minz\Database::get();

        $database->exec(<<<'SQL'
            ALTER TABLE fetch_logs
            ADD COLUMN type TEXT NOT NULL DEFAULT 'link';
        SQL);

        return true;
    }

    public function rollback()
    {
        $database = \Minz\Database::get();

        $database->exec(<<<'SQL'
            ALTER TABLE fetch_logs
            DROP COLUMN type;
        SQL);

        return true;
    }
}