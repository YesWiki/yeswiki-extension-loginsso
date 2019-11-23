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

$username = $this->GetParameter('username');

// if no user declared in the parameters, set the connected user
if (!$username){
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
    // TODO remove the user entry from the results
    $tableau_dernieres_fiches = baz_requete_recherche_fiches('', '', '', '', 1, addslashes($username));
    if (count($tableau_dernieres_fiches) > 0) {
        $params = getAllParameters($this);
        echo displayResultList($tableau_dernieres_fiches, $params, true);
    }
}