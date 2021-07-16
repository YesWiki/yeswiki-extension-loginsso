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
namespace YesWiki;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\TemplateEngine;

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

        $entries = $GLOBALS['wiki']->services->get(EntryManager::class)->search(['user' => addslashes($username)]);
        // remove the user entry from the results if a bazar_user_entry_id is defined in the config
        if (!empty($this->config['sso_config']['bazar_user_entry_id'])) {
            foreach ($entries as $index => $fiche) {
                if (intval($fiche["id_typeannonce"]) == $this->config['sso_config']['bazar_user_entry_id'])
                    unset($entries[$index]);
            }
        }
        if (count($entries) > 0) {
            $data = [];
            $data['fiches'] = $entries;
            $data['info_res'] = '<div class="alert alert-info">' . _t('BAZ_IL_Y_A') . ' '.count($data['fiches'])
                . ' ' . (count($data['fiches']) <= 1 ? _t('BAZ_FICHE') : _t('BAZ_FICHES')) . '</div>';
            $twig = $GLOBALS['wiki']->services->get(TemplateEngine::class);
            echo '<div id="bazar-list-1" class="bazar-list" data-template="liste_accordeon.tpl.html">
                    <div class="list">';
            echo $twig->render("@bazar/liste_accordeon.tpl.html", $data);
            echo '</div></div>';
        } else {
            echo '<div class="alert alert-info">' . _t('SSO_NO_USER_ENTRIES') . '</div>' . "\n";
        }
    }
}
