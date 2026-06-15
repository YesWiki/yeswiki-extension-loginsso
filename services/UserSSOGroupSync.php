<?php

namespace YesWiki\LoginSso\Service;

use YesWiki\Core\Entity\User;
use YesWiki\Core\Service\GroupManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Wiki;

class UserSSOGroupSync
{
    protected $userManager;
    protected $wiki;
    protected $groupManager;

    public function __construct(UserManager $userManager, Wiki $wiki, GroupManager $groupManager)
    {
        $this->userManager = $userManager;
        $this->wiki = $wiki;
        $this->groupManager = $groupManager;
    }

    /**
     * @param string|string[] $ssoGroups
     *
     * @return void
     */
    public function syncSsoGroups(User $user, $ssoGroups, $ssoGroupMapping)
    {
        if (!is_array($ssoGroups)) { // Some providers return a string instead of an array if user has one group
            $ssoGroups = [$ssoGroups];
        }

        // Map SSO groups to local groups with config
        $ssoGroups = array_map(function ($ssoGroup) use ($ssoGroupMapping) {
            return $ssoGroupMapping[$ssoGroup] ?? $ssoGroup;
        }, $ssoGroups);
        $ssoGroups = array_unique(array_filter($ssoGroups, function ($group) use ($ssoGroupMapping) {
            return in_array($group, $ssoGroupMapping);
        }));

        $userGroups = $this->userManager->groupsWhereIsMember($user);
        $userGroups = array_filter($userGroups, function ($group) use ($ssoGroupMapping) {
            return in_array($group, $ssoGroupMapping);
        });

        foreach (array_diff($ssoGroups, $userGroups) as $groupToAdd) {
            $members = $this->groupManager->getMembers($groupToAdd);
            $members[] = $user->getName();
            $this->updateGroupAcl($groupToAdd, $members);
        }

        foreach (array_diff($userGroups, $ssoGroups) as $groupToRemove) {
            $members = array_diff($this->groupManager->getMembers($groupToRemove), [$user->getName()]);
            $this->updateGroupAcl($groupToRemove, $members);
        }
    }

    private function updateGroupAcl(string $group, array $members): bool
    {
        $members = array_values(array_filter(array_map('trim', $members)));

        try {
            if ($this->groupManager->groupExists($group)) {
                $this->groupManager->updateMembers($group, $members);
            } else {
                $errorCode = $this->groupManager->create($group, $members);
                if ($errorCode === 1) {
                    $this->wiki->SetMessage(_t('ERROR_WHILE_SAVING_GROUP') . ' ' . ucfirst($group) . ' (' . _t('ERROR_CODE') . ' ' . $errorCode . ')');

                    return false;
                }
            }
        } catch (\Throwable $th) {
            $this->wiki->SetMessage(_t('ERROR_WHILE_SAVING_GROUP') . ' ' . ucfirst($group) . ' (' . $th->getMessage() . ')');

            return false;
        }

        return true;
    }
}
