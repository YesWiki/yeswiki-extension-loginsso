<?php

namespace YesWiki\LoginSso\Handler\Page;

use YesWiki\Bazar\Service\EntryManager;
use function YesWiki\LoginSso\Lib\bazarUserEntryExists;
use function YesWiki\LoginSso\Lib\checkBazarMappingConfig;
use function YesWiki\LoginSso\Lib\createUserBazarEntry;
use function YesWiki\LoginSso\Lib\genere_nom_user;

/**
 * Handler for creating bazar entry based on SOS server informations
 * 
 * @category YesWiki
 * @package  loginsso
 * @author   Florian Schmitt <mrflos@lilo.org>
 * @author   Adrien Cheype <adrien.cheype@gmail.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.en.html AGPL 3.0
 * @link     https://yeswiki.net
 */

// security check
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$entryManager = $this->services->get(EntryManager::class);

require_once __DIR__ .'/../../libs/loginsso.lib.php';
ob_start();

if (isset($_GET['attr'])) {
    $ssoUser = unserialize(rawurldecode($_GET['attr']));
}
// check if all the parameters are well defined
if ($this->GetUser() && $ssoUser && isset($_GET['provider']) && isset($_GET['username'])) {

    $bazarMapping = $this->config['sso_config']['providers'][$_GET['provider']]['bazar_mapping'];

    if (!checkBazarMappingConfig($this->config, $_GET['provider'])){
        echo '<div class="alert alert-danger">' . _t('SSO_CONFIG_ERROR') . '</div>';
    } else {
        // if no entry of the 'id' type and with the 'username' owner, this is the first connexion and the entry have to be created
        if (!bazarUserEntryExists($this->config['sso_config']['bazar_user_entry_id'], $_GET['username'])) {

            // alert message if
            if (isset($_GET['old_user_updated']) && $_GET['old_user_updated'])
                echo '<div class="alert alert-warning">' . _t('SSO_OLD_USER_UPDATED') . '</div><br/>';

            if (!isset($_GET['choice'])) {
                // first display, inform the user and ask the consent question if the anonymize function is configured
                echo '<h2>' . _t('SSO_ENTRY_CREATE') . '</h2><br>';
                echo '<p class="entry_user_information">' . $bazarMapping['entry_creation_information'] . '</p>';
                if (!empty($bazarMapping['anonymize'])){
                    echo '<p><div class="user_consent_question">' . $bazarMapping['anonymize']['consent_question'] . '</div>';
                    echo '<br><a href="' . $this->href('createentry', '', 'choice=yes&' . 'provider=' . $_GET['provider'] . '&username=' . $_GET['username']
                            . '&attr=' . rawurlencode(serialize($ssoUser)), false) . '" class="btn btn-primary">' . _t('SSO_YES_CONSENT') . '</a> ou '
                        . '<a href="' . $this->href('createentry', '', 'choice=no&' . 'provider=' . $_GET['provider'] . '&username=' . $_GET['username']
                            . '&attr=' . rawurlencode(serialize($ssoUser)), false) . '" class="btn btn-default">' . _t('SSO_NO_CONSENT') . '</a>';
                    echo '</p><br><br>';
                } else {
                    echo '<br><a href="' . $this->href('createentry', '', 'choice=yes&' . 'provider=' . $_GET['provider'] . '&username=' . $_GET['username']
                            . '&attr=' . rawurlencode(serialize($ssoUser)), false) . '" class="btn btn-primary">' . _t('SSO_OK_ENTRY_CREATION') . '</a>';
                    echo '</p><br><br>';
                }
            } else {
                // if the user have already click on a button
                $anonymous = $_GET['choice']=='yes' ? false : true;
                $fiche = createUserBazarEntry($bazarMapping, $this->config['sso_config']['bazar_user_entry_id'],
                    $this->config['sso_config']['providers'][$_GET['provider']]['create_user_from'], $ssoUser, $anonymous);
                if (!empty($fiche)) {
                    include_once 'tools/bazar/libs/bazar.fonct.php';

                    if (!$anonymous) {
                        $fiche['id_fiche'] = $_GET['username'];
                    } else {
                        $entryId = genere_nom_user($fiche['bf_titre']);
                        // in case of an anonymized user, update the username with the entry id and save the entry with this user
                        $this->Query(
                            "UPDATE " . $this->config["table_prefix"] . "users SET " .
                            "name = '" . mysqli_real_escape_string($this->dblink,  $entryId) . "', " .
                            "password = 'sso' " .
                            "WHERE name = '" . mysqli_real_escape_string($this->dblink, $_GET['username']) . "'"
                        );

                        // refresh the user
                        $user = $this->LoadUser($entryId);
                        $this->SetUser($user, true);

                        $fiche['id_fiche'] = $entryId;
                    }

                    $fiche['antispam'] = 1;
                    $fiche = $entryManager->create($this->config['sso_config']['bazar_user_entry_id'], $fiche);

                    // set the read access of the entry ('+' by default)
                    $readAccess = isset($bazarMapping['read_access_entry']) ? $bazarMapping['read_access_entry'] : '+';
                    $GLOBALS['wiki']->SaveAcl($fiche['id_fiche'], 'read', $readAccess);
                    // set the write access of the entry ('%' by default)
                    $writeAccess = isset($bazarMapping['write_access_entry']) ? $bazarMapping['write_access_entry'] : '%';
                    $GLOBALS['wiki']->SaveAcl($fiche['id_fiche'], 'write', $writeAccess);

                    $this->redirect($this->href('', $fiche['id_fiche']));
                }
            }
        }
    }
} else {
    echo '<div class="alert alert-danger">' . _t('SSO_ERROR') . '</div>';
}

$content = ob_get_clean();
echo $this->Header();
echo $content;
echo $this->Footer();
