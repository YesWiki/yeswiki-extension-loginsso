<?php

namespace YesWiki\LoginSso;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\LoginSso\Service\OAuth2ProviderFactory;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Login\Exception\LoginException;
use YesWiki\Core\YesWikiAction;

class LoginAction extends YesWikiAction
{
    protected AuthController $authController;

    public function run()
    {
        $this->authController = $this->getService(AuthController::class);

        $action = $_REQUEST["action"] ?? '';
        switch ($action) {
            case "connectOAUTH":
                $this->redirectOAuth((int) $_REQUEST["provider"]);
                break;
            case "logout":
               $this->logout();
            default:
                return $this->renderDefault();
        }
        return null;
    }

    private function renderDefault(): string
    {
        // classe css pour les boutons
        $btnclass = $this->wiki->GetParameter("btnclass");
        if (empty($btnclass)) {
            $btnclass = 'btn-default';
        }

        $user = $this->authController->getLoggedUser();
        return $this->render('@loginsso/modal.twig', [
            "connected" => !empty($user),
            "user" => $user["name"] ?? '',
            "email" => $user["email"] ?? '',
            "providers" => $this->wiki->config['sso_config']['providers'],
            "incomingUrl" => $this->wiki->request->getUri(),
            "btnClass" => $btnclass,
            "nobtn" => $this->wiki->GetParameter("nobtn")
        ]);
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
     * Used for post authentification redirection
     */
    private function getIncominUriWithoutAction()
    {
        parse_str(parse_url($this->wiki->request->getUri(), PHP_URL_QUERY), $query);
        unset($query['action']);
        unset($query['provider']);

        return $this->wiki->request->getUriForPath($this->wiki->request->getPathInfo() . '?' . http_build_query($query));
    }

}
