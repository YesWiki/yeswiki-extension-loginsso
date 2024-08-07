<?php

namespace YesWiki\LoginSso;

use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\YesWikiAction;
use YesWiki\LoginSso\Service\OAuth2ProviderFactory;

class LoginAction extends YesWikiAction
{
    protected AuthController $authController;

    public function run()
    {
        $this->authController = $this->getService(AuthController::class);

        $action = $_REQUEST['action'] ?? '';
        switch ($action) {
            case 'connectOAUTH':
                $this->redirectOAuth((int)$_REQUEST['provider']);
                break;
            case 'logout':
                $this->logout();
                // no break
            default:
                return $this->renderDefault();
        }

        return null;
    }

    private function validateConfig()
    {
        // Verification si le fichier de conf est bien renseigné dans toutes les lignes du tableau
        $allGood = true;
        $error = [];
        foreach ($this->wiki->config['sso_config']['providers'] as $id => $confEntry) {
            if (strtolower($confEntry['auth_type']) == strtolower('oauth2')) {
                if (
                    empty($confEntry['auth_options']['clientId'])
                    || empty($confEntry['auth_options']['clientSecret'])
                    || empty($confEntry['auth_options']['urlAuthorize'])
                    || empty($confEntry['auth_options']['urlAccessToken'])
                    || empty($confEntry['auth_options']['urlResourceOwnerDetails'])
                ) {
                    $allGood = false;
                    $error[] = 'Provider No ' . ($id + 1) . ' : ' . _t('SSO_AUTH_OPTIONS_ERROR');
                }
            } else {
                $allGood = false;
                $error[] = 'Provider No ' . ($id + 1) . ' : ' . _t('SSO_AUTH_TYPE_ERROR');
            }

            if (!isset($confEntry['id_sso_field'])) {
                $allGood = false;
                $error[] = 'Provider No ' . ($id + 1) . ' : ' . _t('SSO_USER_ID_REQUIRED');
            }

            if (!isset($confEntry['email_sso_field'])) {
                $allGood = false;
                $error[] = 'Provider No ' . ($id + 1) . ' : ' . _t('SSO_USER_EMAIL_REQUIRED');
            }
        }
        if (!$allGood) {
            throw new \RuntimeException(_t('action {{login}}') . implode(',', $error));
        }
    }

    private function renderDefault(): string
    {
        $this->validateConfig();
        // classe css pour les boutons
        $btnclass = $this->wiki->GetParameter('btnclass');
        if (empty($btnclass)) {
            $btnclass = 'btn-default';
        }

        $user = $this->authController->getLoggedUser();
        $username = $user['name'] ?? '';
        if ($this->wiki->config['sso_config']['login_username_initials'] ?? false) {
            $username = $this->nameInitials($username);
        }

        return $this->render('@loginsso/modal.twig', [
            'connected' => !empty($user),
            'user' => $username,
            'email' => $user['email'] ?? '',
            'providers' => $this->wiki->config['sso_config']['providers'],
            'incomingUrl' => $this->wiki->request->getUri(),
            'btnClass' => $btnclass,
            'nobtn' => $this->wiki->GetParameter('nobtn'),
        ]);
    }

    private function nameInitials(string $name)
    {
        $nameExploded = explode(' ', $name);
        if (count($nameExploded) === 1) {
            $nameExploded = preg_split('/(?=[A-Z])/', $name);
        }
        if ($nameExploded === false) {
            return $name;
        }

        $initials = '';
        foreach ($nameExploded as $n) {
            $initials .= mb_strtoupper($n[0] ?? '') . ' ';
        }

        return trim($initials);
    }

    private function redirectOAuth(int $providerId)
    {
        $provider = $this->getService(OAuth2ProviderFactory::class)->createProvider($providerId);
        $authorizationUrl = $provider->getAuthorizationUrl();

        $_SESSION['oauth2state'] = $provider->getState();
        $_SESSION['oauth2previousUrl'] = $this->getIncominUriWithoutAction();
        $_SESSION['oauth2provider'] = $providerId;

        return $this->wiki->Redirect(
            $authorizationUrl
        );
    }

    private function logout()
    {
        $this->authController->logout();
        $this->wiki->SetMessage(_t('LOGIN_YOU_ARE_NOW_DISCONNECTED'));
        $this->wiki->Redirect($this->getIncominUriWithoutAction());
        $this->wiki->exit();
    }

    /**
     * Get current url but remove all extension specific actions
     * Used for post authentification redirection.
     */
    private function getIncominUriWithoutAction()
    {
        parse_str(parse_url($this->wiki->request->getUri(), PHP_URL_QUERY), $query);
        unset($query['action']);
        unset($query['provider']);

        return $this->wiki->request->getUriForPath($this->wiki->request->getPathInfo() . '?' . http_build_query($query));
    }
}
