<?php

namespace YesWiki\LoginSso\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;
use YesWiki\LoginSso\Service\OAuth2ProviderFactory;
use YesWiki\LoginSso\Service\UserSSOGroupSync;
use function YesWiki\LoginSso\Lib\bazarUserEntryExists;
use function YesWiki\LoginSso\Lib\genere_nom_user;
use YesWiki\LoginSso\Service\UserManager as LoginSsoUserManager;

require_once __DIR__ . '/../libs/loginsso.lib.php';

class ApiController extends YesWikiController
{
    private LoginSsoUserManager $loginSsoUserManager;
    private UserManager $userManager;
    private DbService $dbService;

    private function initServices(
    ) {
        $this->loginSsoUserManager = $this->getService(LoginSsoUserManager::class);
        $this->userManager = $this->getService(UserManager::class);
        $this->dbService = $this->getService(DbService::class);
    }

    /**
     * Due do diffrent URL encoding from server and processing done in YesWiki::RunSpecialPages
     * The two URLs can match so we bind on two
     *
     * @Route("/api/auth_sso/callback", methods={"GET"}, options={"acl":{"public"}})
     * @Route("/api/auth_sso", methods={"GET"}, options={"acl":{"public"}})
     */
    public function authSsoCallback()
    {
        // Yeswiki does not support controller instanciation as service for Symfony Routing
        $this->initServices();

        $incomingurl = $_SESSION['oauth2previousUrl'] ?? '/';

        // check given state against previously stored one to mitigate CSRF attack
        if(!isset($_SESSION['oauth2state']) || $this->wiki->request->query->get('state') !== $_SESSION['oauth2state']) {
            unset($_SESSION['oauth2state']);
            Flash::error(_t('SSO_ERROR'));
            $this->wiki->redirect($incomingurl);
            return;
        }

       $providerId = $_SESSION['oauth2provider'];

       try {
           $provider = $this->getService(OAuth2ProviderFactory::class)->createProvider($providerId);
           $token = $provider->getAccessToken('authorization_code', [
               'code' => $this->wiki->request->query->get('code')
           ]);

           $ssoUser = $provider->getResourceOwner($token)->toArray();
       } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
           Flash::error(_t('SSO_ERROR'). ". " . _t("SSO_ERROR_DETAIL") . join(', ', $e->getResponseBody()));
           $this->wiki->redirect($incomingurl);
           return;
       }


        $providerConf = $this->wiki->config['sso_config']['providers'][$_SESSION['oauth2provider']];

        $ssoUserId = $this->getFieldFromTokenOrExit($ssoUser, $providerConf['id_sso_field'], $incomingurl);
        $ssoUserEmail = $this->getFieldFromTokenOrExit($ssoUser, $providerConf['email_sso_field'], $incomingurl);

        $user = $this->loginSsoUserManager->getOneById($ssoUserId);


        // if the user creation is forbidden and the user doesn't exists in yeswiki, alert the user he's not allowed
        if (!isset($providerConf['create_user_from']) && !$user) {
            Flash::error(_t('SSO_USER_NOT_ALLOWED'));
            // remove the get parameters used for the connection
            $this->wiki->redirect($incomingurl);
            return;
        }

        $oldUserUpdated = false;
        // if an user with the given id doesn't, create it
        if ($user === null) {
            $exisingUserWithSameEmail = $this->userManager->getOneByEmail($ssoUserEmail);
            if($exisingUserWithSameEmail === null) {
                $user = $this->createUser($ssoUserId, $ssoUser, $providerConf);
            } else {
                // Two users cannot exist with same email, so we attach accounts in this case on SSO user
                $user = $this->attachUserToSsoUserSameEmail($exisingUserWithSameEmail, $ssoUserId);
                $oldUserUpdated = true;
            }
        }

        // Update old user email if has changed on SSO side
        if($user->getEmail() !== $ssoUserEmail) {
            $this->dbService->query(
                sprintf('UPDATE %s SET email = \'%s\' WHERE loginsso_id = \'%s\'',
                    $this->dbService->prefixTable('users'),
                    $this->dbService->escape($ssoUserEmail),
                    $this->dbService->escape($ssoUserId)
                )
            );
        }

        $this->getService(UserSSOGroupSync::class)->syncSsoGroups(
            $user,
            $ssoUser[$providerConf['groups_sso_field']] ?? [],
            $providerConf['groups_sso_mapping'] ?? []
        );

        $this->wiki->services->get(AuthController::class)->login($user, true);

        $this->postAuthRedirection($providerConf, $providerId, $ssoUser, $user, $incomingurl, $oldUserUpdated);

    }

    private function getFieldFromTokenOrExit(array $token, string $field, string $incomingUrl): string
    {
        if(!isset($token[$field])) {
            Flash::error(_t('SSO_ERROR_FIELD_NOT_FOUND', ['needle' => $field, 'fields' => implode(', ', array_keys($token))]));
            $this->wiki->redirect($incomingUrl); // Redirect force exit the script
        }
        return $token[$field];
    }

    private function createUser(string $userId, array $ssoUser, array $providerConf): User
    {
        // the username will be an unique identifier created by genere_nom_wiki once the 'create_user_from' defined in the config
        // file is applied
        $userTitle = $providerConf['create_user_from'];
        foreach ($ssoUser as $ssoField => $ssoValue) {
            if(\is_string($ssoValue)) {
                $userTitle = str_replace("#[$ssoField]", $ssoUser[$ssoField], $userTitle);
            }
        }
        $username = genere_nom_user($userTitle);

        $this->userManager->create([
            'name' => $username,
            'email' => $ssoUser[$providerConf['email_sso_field']],
            'password' => 'sso',
        ]);

        // Add extension specific information
        $this->queryAddSsoIdToUser($username, $userId);

        return $this->loginSsoUserManager->getOneById($userId);
    }

    private function attachUserToSsoUserSameEmail(User $user, string $ssoUserId): User
    {
        $this->queryAddSsoIdToUser($user->getName(), $ssoUserId);
        return $this->loginSsoUserManager->getOneById($ssoUserId);
    }

    private function queryAddSsoIdToUser(string $username, string $ssoId)
    {
        $this->dbService->query(
            sprintf('UPDATE %s SET loginsso_id = \'%s\', password=\'sso\' WHERE name = \'%s\'',
                $this->dbService->prefixTable('users'),
                $this->dbService->escape($ssoId),
                $this->dbService->escape($username)
            )
        );
    }

    private function postAuthRedirection(array $providerConf, string $providerId, array $ssoUser, User $user, string $incomingurl, bool $oldUserUpdated = false)
    {
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
                Flash::info(_t('SSO_OLD_USER_UPDATED'));
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
