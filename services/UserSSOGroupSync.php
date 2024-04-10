<?php

namespace YesWiki\LoginSso\Service;

use YesWiki\Core\Entity\User;
use YesWiki\Core\Service\UserManager;
use YesWiki\Wiki;

class UserSSOGroupSync
{
    protected $userManager;
    protected $wiki;

    public function __construct(UserManager $userManager, Wiki $wiki)
    {
        $this->userManager = $userManager;
        $this->wiki = $wiki;
    }

    /**
     * @param string|string[] $ssoGroups
     * @return void
     */
    public function syncSsoGroups(User $user, $ssoGroups, $ssoGroupMapping)
    {
        if(!is_array($ssoGroups)) { // Some providers return a string instead of an array if user has one group
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
            $groupAcl = $this->wiki->GetGroupACL($groupToAdd) ?? '';
            $groupAcl .= PHP_EOL . $user->getName();
            $this->updateGroupAcl($groupToAdd, trim($groupAcl));
        }

        foreach (array_diff($userGroups, $ssoGroups) as $groupToRemove) {
            $groupAcl = $this->wiki->GetGroupACL($groupToRemove) ?? '';
            $groupAcl = str_replace($user->getName(), '', $groupAcl);
            $groupAcl = str_replace(PHP_EOL . PHP_EOL, PHP_EOL, $groupAcl);
            $this->updateGroupAcl($groupToRemove, trim($groupAcl));
        }

    }

    private function updateGroupAcl(string $group, string $acl): bool
    {
        $result = $this->wiki->SetGroupACL($group, trim($acl));
        if ($result) {
            if ($result == 1000) {
                $this->wiki->SetMessage(_t('ERROR_RECURSIVE_GROUP') . ' !');
            } else {
                $this->wiki->SetMessage(_t('ERROR_WHILE_SAVING_GROUP') . ' ' . ucfirst($group) . ' (' . _t('ERROR_CODE') . ' ' . $result . ')');
            }
            return false;
        }

        return true;
    }


}
