<?php

namespace YesWiki\LoginSso\Service;

use YesWiki\Core\Entity\User;
use YesWiki\Core\Service\DbService;

class UserManager
{
    protected $dbService;

    public function __construct(
        DbService $dbService
    ) {
        $this->dbService = $dbService;
    }

    public function getOneById(string $id): ?User
    {
        $userAsArray = $this
            ->dbService
            ->loadSingle(
                'select * from' . $this->dbService->prefixTable('users') . " where loginsso_id = '" . $this->dbService->escape($id) . "' limit 1"
            );

        if (empty($userAsArray)) {
            return null;
        }

        return new User($userAsArray);
    }
}
