<?php

namespace YesWiki\LoginSso;

use DateInterval;
use DateTime;
use Throwable;
use YesWiki\Bazar\Field\MapField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Security\Controller\SecurityController;

class UpdateHandler__ extends YesWikiHandler
{
    protected $dbService;
    protected $entryManager;
    protected $formManager;
    protected $pageManager;
    protected $securityController;

    public function run()
    {
        $this->securityController = $this->getService(SecurityController::class);
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        };
        if (!$this->wiki->UserIsAdmin()) {
            return null;
        }

        $output = $this->addSsoFieldToUserTable();
        // set output
        $this->output = str_replace(
            '<!-- end handler /update -->',
            $output.'<!-- end handler /update -->',
            $this->output
        );
        return null;
    }

    private function addSsoFieldToUserTable()
    {
        $this->dbService = $this->getService(DbService::class);
        $tableName = $this->dbService->prefixTable('users');

        $columExist = $this->dbService->count(sprintf("SHOW COLUMNS FROM %s LIKE 'loginsso_id'", $tableName)) > 0;
        if(!$columExist){
            $this->dbService->query(sprintf("ALTER TABLE %s ADD COLUMN loginsso_id VARCHAR(255) DEFAULT NULL", $tableName));
            $this->dbService->query(sprintf("CREATE UNIQUE INDEX idx_loginsso_id ON %s(loginsso_id);", $tableName));

            // Retro compatibility from previous versions
            $this->dbService->query(sprintf("UPDATE %s SET loginsso_id=email", $tableName));
        }

        return $this->render('@loginsso/handlers/update/add_column.html.twig', [
            'tableName' => $tableName,
            'columnExist' => $columExist
        ]);
    }


}
