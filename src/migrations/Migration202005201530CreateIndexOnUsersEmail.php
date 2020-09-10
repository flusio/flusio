<?php

namespace flusio\migrations;

class Migration202005201530CreateIndexOnUsersEmail
{
    public function migrate()
    {
        $database = \Minz\Database::get();

        $sql = <<<'SQL'
            CREATE INDEX idx_users_email ON users(email);
        SQL;

        $database->exec($sql);

        return true;
    }

    public function rollback()
    {
        $database = \Minz\Database::get();

        $sql = <<<'SQL'
            DROP INDEX idx_users_email;
        SQL;

        $database->exec($sql);

        return true;
    }
}
