<?php
// Handler /createentry : crée la fiche bazar d'une personne authentifiée par SSO,
// après lui avoir demandé son consentement.

namespace YesWiki\LoginSso\Handler\Page;

use YesWiki\Bazar\Service\EntryManager;

use function YesWiki\LoginSso\Lib\bazarUserEntryExists;
use function YesWiki\LoginSso\Lib\checkBazarMappingConfig;
use function YesWiki\LoginSso\Lib\createUserBazarEntry;
use function YesWiki\LoginSso\Lib\decodeSsoAttributes;
use function YesWiki\LoginSso\Lib\encodeSsoAttributes;
use function YesWiki\LoginSso\Lib\genere_nom_user;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$entryManager = $this->services->get(EntryManager::class);

require_once __DIR__ . '/../../libs/loginsso.lib.php';
ob_start();

$request = $this->request;
$ssoUser = decodeSsoAttributes($request->query->get('attr'));
$provider = $request->query->get('provider');
$username = $request->query->get('username');

if ($this->GetUser() && $ssoUser && $provider !== null && $username !== null
    && isset($this->config['sso_config']['providers'][$provider])) {
    $bazarMapping = $this->config['sso_config']['providers'][$provider]['bazar_mapping'];

    if (!checkBazarMappingConfig($this->config, $provider)) {
        echo '<div class="alert alert-danger">' . _t('SSO_CONFIG_ERROR') . '</div>';
    } else {
        if (!bazarUserEntryExists($this->config['sso_config']['bazar_user_entry_id'], $username)) {
            if ($request->query->get('old_user_updated')) {
                echo '<div class="alert alert-warning">' . _t('SSO_OLD_USER_UPDATED') . '</div><br/>';
            }

            if (!$request->query->has('choice')) {
                $consentParams = [
                    'provider' => $provider,
                    'username' => $username,
                    'attr' => encodeSsoAttributes($ssoUser),
                ];
                $yesLink = $this->href('createentry', '', http_build_query(['choice' => 'yes'] + $consentParams), false);
                $noLink = $this->href('createentry', '', http_build_query(['choice' => 'no'] + $consentParams), false);

                echo '<h2>' . _t('SSO_ENTRY_CREATE') . '</h2><br>';
                echo '<p class="entry_user_information">' . $bazarMapping['entry_creation_information'] . '</p>';
                if (!empty($bazarMapping['anonymize'])) {
                    echo '<p><div class="user_consent_question">' . $bazarMapping['anonymize']['consent_question'] . '</div>';
                    echo '<br><a href="' . $yesLink . '" class="btn btn-primary">' . _t('SSO_YES_CONSENT') . '</a> ou '
                        . '<a href="' . $noLink . '" class="btn btn-default">' . _t('SSO_NO_CONSENT') . '</a>';
                    echo '</p><br><br>';
                } else {
                    echo '<br><a href="' . $yesLink . '" class="btn btn-primary">' . _t('SSO_OK_ENTRY_CREATION') . '</a>';
                    echo '</p><br><br>';
                }
            } else {
                $anonymous = $request->query->get('choice') !== 'yes';
                $fiche = createUserBazarEntry(
                    $bazarMapping,
                    $this->config['sso_config']['bazar_user_entry_id'],
                    $this->config['sso_config']['providers'][$provider]['create_user_from'],
                    $ssoUser,
                    $anonymous
                );
                if (!empty($fiche)) {
                    include_once 'tools/bazar/libs/bazar.fonct.php';

                    if (!$anonymous) {
                        $fiche['id_fiche'] = $username;
                    } else {
                        $entryId = genere_nom_user($fiche['bf_titre']);
                        $this->Query(
                            'UPDATE ' . $this->config['table_prefix'] . 'users SET ' .
                            "name = '" . mysqli_real_escape_string($this->dblink, $entryId) . "', " .
                            "password = 'sso' " .
                            "WHERE name = '" . mysqli_real_escape_string($this->dblink, $username) . "'"
                        );

                        $user = $this->LoadUser($entryId);
                        $this->SetUser($user, true);

                        $fiche['id_fiche'] = $entryId;
                    }

                    $fiche['antispam'] = 1;
                    $fiche = $entryManager->create($this->config['sso_config']['bazar_user_entry_id'], $fiche);

                    $readAccess = isset($bazarMapping['read_access_entry']) ? $bazarMapping['read_access_entry'] : '+';
                    $this->SaveAcl($fiche['id_fiche'], 'read', $readAccess);
                    $writeAccess = isset($bazarMapping['write_access_entry']) ? $bazarMapping['write_access_entry'] : '%';
                    $this->SaveAcl($fiche['id_fiche'], 'write', $writeAccess);

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
