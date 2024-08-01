<?php

use YesWiki\Core\YesWikiMigration;

class LoginssoAddSsoFieldToUserTable extends YesWikiMigration
{
    public function run()
    {
        $tableName = $this->dbService->prefixTable('users');

        $columExist = $this->dbService->count(sprintf("SHOW COLUMNS FROM %s LIKE 'loginsso_id'", $tableName)) > 0;
        if (!$columExist) {
            $this->dbService->query(sprintf('ALTER TABLE %s ADD COLUMN loginsso_id VARCHAR(255) DEFAULT NULL', $tableName));
            $this->dbService->query(sprintf('CREATE UNIQUE INDEX idx_loginsso_id ON %s(loginsso_id);', $tableName));

            // Retro compatibility from previous versions
            $this->dbService->query(sprintf('UPDATE %s SET loginsso_id=email', $tableName));
        }

        return $this->wiki->render('@loginsso/handlers/update/add_column.html.twig', [
            'tableName' => $tableName,
            'columnExist' => $columExist,
        ]);
    }
}
