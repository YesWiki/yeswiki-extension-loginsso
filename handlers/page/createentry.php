<?php
/**
 * Handler for creating bazar entry based on SOS server informations
 * 
 * @category YesWiki
 * @package  login-sso
 * @author   Florian Schmitt <mrflos@lilo.org>
 * @author   Adrien Cheype <adrien.cheype@gmail.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.en.html AGPL 3.0
 * @link     https://yeswiki.net
 */

// security check
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

require_once 'tools/login-sso/libs/login-sso.lib.php';
ob_start();

if (isset($_GET['attr'])) {
    $auth = unserialize(rawurldecode($_GET['attr']));
}
// check if all the parameters are well defined
if ($this->GetUser() && $auth && isset($_GET['provider']) && isset($_GET['username'])) {

    $bazar = $this->config['sso_config']['hosts'][$_GET['provider']]['bazar_mapping'];

    if (!checkBazarMappingConfig($this->config, $_GET['provider'])){
        echo '<div class="alert alert-danger">' . _t('SSO_CONFIG_ERROR') . '</div>';
    } else {
        // if no entry of the 'id' type and with the 'username' owner, this is the first connexion and the entry have to be created
        if (!bazarEntryExists($this->config['sso_config']['bazar_user_entry_id'], $_GET['username'])) {

            if (!isset($_GET['choice'])) {
                // first display, inform the user and ask the consent question if the anonymize function is configured
                echo '<h2>' . _t('SSO_ENTRY_CREATE') . '</h2><br>';
                echo '<p class="entry_user_information">' . $bazar['entry_creation_information'] . '</p>';
                if (!empty($bazar['anonymize'])){
                    echo '<p><div class="user_consent_question">' . $bazar['anonymize']['consent_question'] . '</div>';
                    echo '<br><a href="' . $this->href('createentry', '', 'choice=yes&' . 'provider=' . $_GET['provider'] . '&username=' . $_GET['username']
                            . '&attr=' . rawurlencode(serialize($auth)), false) . '" class="btn btn-primary">' . _t('SSO_YES_CONSENT') . '</a> ou '
                        . '<a href="' . $this->href('createentry', '', 'choice=no&' . 'provider=' . $_GET['provider'] . '&username=' . $_GET['username']
                            . '&attr=' . rawurlencode(serialize($auth)), false) . '" class="btn btn-default">' . _t('SSO_NO_CONSENT') . '</a>';
                    echo '</p><br><br>';
                } else {
                    echo '<br><a href="' . $this->href('createentry', '', 'choice=yes&' . 'provider=' . $_GET['provider'] . '&username=' . $_GET['username']
                            . '&attr=' . rawurlencode(serialize($auth)), false) . '" class="btn btn-primary">' . _t('SSO_OK_ENTRY_CREATION') . '</a>';
                    echo '</p><br><br>';
                }
            } else {
                // if the user have already click on a button
                $anonymous = $_GET['choice']=='yes' ? false : true;
                $fiche = createBazarEntry($bazar, $this->config['sso_config']['bazar_user_entry_id'], $auth, $anonymous);
                if (!empty($fiche)) {
                    include_once 'tools/bazar/libs/bazar.fonct.php';
                    $fiche = baz_insertion_fiche($fiche);

                    // set the read access of the entry ('+' by default)
                    $readAccess = isset($bazar['read_access_entry']) ? $bazar['read_access_entry'] : '+';
                    $GLOBALS['wiki']->SaveAcl($fiche['id_fiche'], 'read', $readAccess);
                    // set the write access of the entry ('%' by default)
                    $writeAccess = isset($bazar['write_access_entry']) ? $bazar['write_access_entry'] : '%';
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
