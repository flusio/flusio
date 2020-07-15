<?php

namespace flusio\migrations;

class Migration202007100001AddDescriptionToCollections
{
    public function migrate()
    {
        $database = \Minz\Database::get();

        $sql = <<<'SQL'
            ALTER TABLE collections
            ADD COLUMN description TEXT NOT NULL DEFAULT '';
        SQL;

        $database->exec($sql);

        return true;
    }
}