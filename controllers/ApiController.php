<?php

namespace YesWiki\LoginSso\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;
use YesWiki\LoginSso\Service\OAuth2ProviderFactory;
use YesWiki\LoginSso\Service\UserSSOGroupSync;
use function YesWiki\LoginSso\Lib\bazarUserEntryExists;
use function YesWiki\LoginSso\Lib\genere_nom_user;

require_once __DIR__ . '/../libs/loginsso.lib.php';

class ApiController extends YesWikiController
{
    /**
     * @Route("/api/auth_sso/callback", methods={"GET"}, options={"acl":{"public"}})
     */
    public function authSsoCallback()
    {
        // check given state against previously stored one to mitigate CSRF attack
        if(!isset($_SESSION['oauth2state']) || $this->wiki->request->query->get('state') !== $_SESSION['oauth2state']) {
            unset($_SESSION['oauth2state']);
            exit(_t('SSO_ERROR'));
        }

       $providerId = $_SESSION['oauth2provider'];
       $provider = $this->getService(OAuth2ProviderFactory::class)->createProvider($providerId);
        $token = $provider->getAccessToken('authorization_code', [
            'code' => $this->wiki->request->query->get('code')
        ]);

        $ssoUser = $provider->getResourceOwner($token)->toArray();
        $incomingurl = $_SESSION['oauth2previousUrl'];

        $providerConf = $this->wiki->config['sso_config']['providers'][$_SESSION['oauth2provider']];

        $email = $ssoUser[$providerConf['email_sso_field']];

        // TODO add config parameter to choose if the user is load with its email or its id (in this case, loadUser($id) is called)

        $user = $this->wiki->services->get(UserManager::class)->getOneByEmail($email);

        // if the user creation is forbidden and the user doesn't exists in yeswiki, alert the user he's not allowed
        if (!isset($providerConf['create_user_from']) && !$user) {
            // TODO améliorer ce message box qui ne reste pas assez longtemps
            $this->wiki->SetMessage(_t('SSO_USER_NOT_ALLOWED'));
            // remove the get parameters used for the connection
            $this->wiki->redirect($incomingurl);
        }
        else {
            // if an user with the given email doesn't, create it
            if (!$user) {
                // the username will be an unique identifier created by genere_nom_wiki once the 'create_user_from' defined in the config
                // file is applied
                $userTitle = $providerConf['create_user_from'];
                foreach ($ssoUser as $ssoField => $ssoValue)
                    $userTitle = str_replace("#[$ssoField]", $ssoUser[$ssoField], $userTitle);
                $username = genere_nom_user($userTitle);

                // création de l'utilisateur s'il n'existe pas dans yeswiki
                $this->wiki->Query(
                    "INSERT INTO " . $this->wiki->config["table_prefix"] . "users SET " .
                    "signuptime = now(), " .
                    "name = '" . mysqli_real_escape_string($this->wiki->dblink, $username) . "', " .
                    "email = '" . mysqli_real_escape_string($this->wiki->dblink, $email) . "', " .
                    "password = 'sso'" . ", " .
                    "motto = ''"
                );
                // log in
                $user = $this->wiki->services->get(UserManager::class)->getOneByEmail($email);
            }

            $oldUserUpdated = false;
            // if the user exist already exists from a local account, replace its name and warn the user
            if ($user['password'] != 'sso'){
                // the username will be an unique identifier created by genere_nom_wiki once the 'create_user_from' defined in the config
                // file is applied
                $userTitle = $providerConf['create_user_from'];
                foreach ($ssoUser as $ssoField => $ssoValue)
                    $userTitle = str_replace("#[$ssoField]", $ssoUser[$ssoField], $userTitle);
                $username = genere_nom_user($userTitle);

                $this->wiki->Query(
                    "UPDATE " . $this->wiki->config["table_prefix"] . "users SET " .
                    "name = '" . mysqli_real_escape_string($this->wiki->dblink, $username) . "', " .
                    "password = 'sso' " .
                    "WHERE name = '" . mysqli_real_escape_string($this->wiki->dblink, $user['name']) . "'"
                );

                $oldUserUpdated = true;
                $user = $this->wiki->services->get(UserManager::class)->getOneByEmail($email);
            }

            $this->getService(UserSSOGroupSync::class)->syncSsoGroups(
                $user,
                $ssoUser[$providerConf['groups_sso_field']],
                $providerConf['groups_sso_mapping']
            );

            $this->wiki->services->get(AuthController::class)->login($user, true);

            $bazarMapping = $providerConf['bazar_mapping'];
            // if bazarMapping is defined and the bazar user entry does't exist, create it
            if (!empty($bazarMapping)) {
                $entry = bazarUserEntryExists($this->wiki->config['sso_config']['bazar_user_entry_id'], $user['name']);
                if (!$entry) {
                    $this->wiki->Redirect($this->wiki->href('createentry', 'BazaR', 'provider=' .$providerId. '&username=' . $user['name'] .
                        ($oldUserUpdated ? '&old_user_updated=yes' : '') . '&attr=' . urlencode(serialize($ssoUser)), false));
                } else {
                    // TODO voir si c'est nécessaire mais on peut ici vérifier si les données de la fiche bazar ont changées et les mettre à jour le cas échéant
                    // $GLOBALS['wiki']->SetMessage('La fiche a été mise à jour');
                }
            } else {
                // if no bazarMapping and an old user was updated, warn the user with a pop up message box
                if ($oldUserUpdated){
                    // TODO améliorer ce message box qui ne reste pas assez longtemps
                    // (soit en passant par une page de transition pour l'afficher, soit en laissant fermer la msg box par l'utilisateur)
                    $this->wiki->SetMessage(_t('SSO_OLD_USER_UPDATED'));
                }
            }

            // if the PageMenuUser page doesn't exist, create it with a default version
            if (!$this->wiki->LoadPage('PageMenuUser')) {
                $this->wiki->SavePage('PageMenuUser', "{{linktouserprofil dash=\"1\"}}\n - [[UserEntries " . _t('SSO_SEE_USER_ENTRIES') . ']]');
            }
            // if the UserEntries page doesn't exist, create it with a default version
            if (!$this->wiki->LoadPage('UserEntries')) {
                $this->wiki->SavePage('UserEntries', '===='._t('SSO_USER_ENTRIES') . '====' . "\n\n{{userentries}}");
            }

            $this->wiki->Redirect($incomingurl);
        }
    }


}
