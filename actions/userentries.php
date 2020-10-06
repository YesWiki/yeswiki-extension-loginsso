<?php
/**
 * userentries : action which displays all the entries created by a user (by default the connected user)
 *
 * @param username   the name of the user whose we want to see the entries. Without this parameter, it will show the connected user's ones.
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

// js lib
$this->AddJavascriptFile('tools/bazar/libs/bazar.js');

$user = $this->GetUser();
// the action only works if a user is logged in
if (!empty($user)) {
    $username = $this->GetParameter('username');

    // if no user declared in the parameters, set the connected user
    if (!$username) {
        $username = $GLOBALS['wiki']->getUser()['name'];
    }

    if (!$this->LoadUser($username)) {
        // if user not found
        echo '<div class="alert alert-danger">' . _t('SSO_USER_NOT_FOUND') . $username . '.</div>' . "\n";
    } else {
        // we are looking for a custom template in the themes/tools/login-sso/templates directory
        $GLOBALS['_BAZAR_']['templates'] = $this->GetParameter("template");
        if (empty($GLOBALS['_BAZAR_']['templates'])) {
            $GLOBALS['_BAZAR_']['templates'] = $GLOBALS['wiki']->config['default_bazar_template'];
        }

        $tableau_dernieres_fiches = $GLOBALS['bazarFiche']->search(['user' => addslashes($username)]);
        // remove the user entry from the results if a bazar_user_entry_id is defined in the config
        if (!empty($this->config['sso_config']['bazar_user_entry_id'])) {
            foreach ($tableau_dernieres_fiches as $index => $fiche) {
                if (preg_match('/.*"id_typeannonce":"' . $this->config['sso_config']['bazar_user_entry_id'] . '".*/', $fiche['body']))
                    unset($tableau_dernieres_fiches[$index]);
            }
        }
        if (count($tableau_dernieres_fiches) > 0) {
            $params = getAllParameters($this);
            echo displayResultList($tableau_dernieres_fiches, $params, true);
        } else {
            echo '<div class="alert alert-info">' . _t('SSO_NO_USER_ENTRIES') . '</div>' . "\n";
        }
    }
}
