<?php
/**
 * linktouserprofil : action which displays for a connected user a link to his profil entry
 * The user have to be connected and the 'bazar_user_entry_id' declared in the config file
 * If no 'bazar_user_entry_id' declared the action displays nothing
 *
 * @param dash    if dash is equal to  '1', a dash point will be insered before the link
 *
 * @category YesWiki
 * @package  login-sso
 * @author   Adrien Cheype <adrien.cheype@gmail.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.en.html AGPL 3.0
 * @link     https://yeswiki.net
 */

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// load the login-sso lib
require_once 'tools/login-sso/libs/login-sso.lib.php';

$user = $this->GetUser();

// test if the user is connected and if the 'bazar_user_entry_id' config key is declared
if (!empty($user) && !empty($this->config['sso_config']) && isset($this->config['sso_config']['bazar_user_entry_id'])){
    $entry = bazarEntryExists($this->config['sso_config']['bazar_user_entry_id'], $user['name']);
    if ($entry) {
        $content = '';
        if ($this->GetParameter('dash') == '1')
            $content .= ' - ';
        $content .= '[[' . $entry . ' ' . _t('SSO_SEE_USER_PROFIL') . ']]';
        echo $this->Format($content);
    }
}