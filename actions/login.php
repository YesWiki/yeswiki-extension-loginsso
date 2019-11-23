<?php
/**
 * Login action for the SSO login extension
 *
 * @category YesWiki
 * @package  login-sso
 * @author   Florian Schmitt <mrflos@lilo.org>
 * @author   Adrien Cheype <adrien.cheype@gmail.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.en.html AGPL 3.0
 * @link     https://yeswiki.net
 */

if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

// Load the login-sso lib
require_once 'tools/login-sso/libs/login-sso.lib.php';

if (!empty($this->config['sso_config']) && !empty($this->config['sso_config']['hosts'])) {

    // Lecture des parametres de l'action
    // classe css pour l'action
    $class = $this->GetParameter("class");

    // classe css pour les boutons
    $btnclass = $this->GetParameter("btnclass");
    if (empty($btnclass)) {
        $btnclass = 'btn-default';
    }
    $nobtn = $this->GetParameter("nobtn");

    // template par défaut
    $template = $this->GetParameter("template");
    if (empty($template) || !file_exists('tools/login-sso/presentation/templates/' . $template)) {
        $template = "default.tpl.html";
    }

    $error = '';
    $PageMenuUser = '';
    $ConnectionDetails ='';

    // on initialise la valeur vide si elle n'existe pas
    if (!isset($_REQUEST["action"])) {
        $_REQUEST["action"] = '';
    }

    // sauvegarde de l'url d'où on vient
    $incomingurl = $this->GetParameter('incomingurl');
    if (empty($incomingurl)) {
        $incomingurl = 'http'.((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    }

    // cas de la déconnexion
    if (isset($_REQUEST['action']) && $_REQUEST["action"] == "logout") {
        $this->LogoutUser();
        $incomingurl = str_replace(array('wiki=', '&action=logout'), '', $incomingurl);
        $this->redirect($incomingurl);
    }

    // Verification si le fichier de conf est bien renseigné dans toutes les lignes du tableau
    $allGood = true;
    $error = [];
    foreach($this->config['sso_config']['hosts'] as $id => $confEntry) {
        if (strtolower($confEntry['auth_type']) == strtolower('oauth2')) {
            if (
                empty($confEntry['auth_options']['clientId']) ||
                empty($confEntry['auth_options']['clientSecret']) ||
                empty($confEntry['auth_options']['urlAuthorize']) ||
                empty($confEntry['auth_options']['urlAccessToken']) ||
                empty($confEntry['auth_options']['urlResourceOwnerDetails'])
            ) {
                $allGood = false;
                $error[] = 'Provider '.$id.' :' . _t('SSO_AUTH_OPTIONS_ERROR');
            }
        } else {
            $allGood = false;
            $error[] = 'Provider '.$id.' :' . _t('SSO_AUTH_TYPE_ERROR');
        }
    }
    if (!$allGood) {
        echo '<div class="alert alert-danger">' . _t('action {{login}}') . ': <ul class="error-list"><li>'
            . implode('</li><li>', $error) . '</li></ul></div>';
        return;
    }

    
    // demande de connexion
    if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'connectOAUTH' && isset($_GET['provider'])) {

        // remove the get parameters added by the auth server (the followed redirectUri must be the same
        $incomingurl = preg_replace(array('(&session_state=[^&]*)', '(&state=[^&]*)', '(&code=[^&]*)'), '', $incomingurl);

        // utilisation du provider générique Oauth2
        $provider = new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId'                => $confEntry['auth_options']['clientId'],    // The client ID assigned to you by the provider
            'clientSecret'            => $confEntry['auth_options']['clientSecret'],   // The client password assigned to you by the provider
            'redirectUri'             => $incomingurl,
            'urlAuthorize'            => $confEntry['auth_options']['urlAuthorize'],
            'urlAccessToken'          => $confEntry['auth_options']['urlAccessToken'],
            'urlResourceOwnerDetails' => $confEntry['auth_options']['urlResourceOwnerDetails']
        ]);

        // if we don't have an authorization code then get one
        if (!isset($_GET['code'])) {

            // fetch the authorization URL from the provider; this returns the urlAuthorize option and generates and applies any necessary parameters
            // (e.g. state)
            $authorizationUrl = $provider->getAuthorizationUrl();

            // get the state generated for you and store it to the session
            $_SESSION['oauth2state'] = $provider->getState();

            // redirect the user to the authorization URL
            header('Location: ' . $authorizationUrl);
            exit;

        // check given state against previously stored one to mitigate CSRF attack
        } elseif (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) {

            if (isset($_SESSION['oauth2state'])) {
                unset($_SESSION['oauth2state']);
            }

            exit(_t('SSO_ERROR'));

        } else {

            try {
                // try to get an access token using the authorization code grant
                $accessToken = $provider->getAccessToken('authorization_code', [
                    'code' => $_GET['code']
                ]);

                // using the access token, we may look up details about the resource owner
                $ssoUser = $provider->getResourceOwner($accessToken)->toArray();

                if ($ssoUser) {
                    $email = isset($ssoUser['email']) ? $ssoUser['email'] : '';
                    $nomwiki = isset($ssoUser['name']) ? $ssoUser['name'] : '';
                    $user = $this->LoadUser($nomwiki);
                    if (!$user) {
                        // création de l'utilisateur s'il n'existe pas dans yeswiki
                        $this->Query(
                            "insert into " . $this->config["table_prefix"] . "users set " .
                            "signuptime = now(), " .
                            "name = '" . mysqli_real_escape_string($this->dblink, $nomwiki) . "', " .
                            "email = '" . mysqli_real_escape_string($this->dblink, $email) . "', " .
                            "password = md5('" . mysqli_real_escape_string($this->dblink, uniqid('cas_')) . "')"
                        );
                        // log in
                        $user = $this->LoadUser($nomwiki);
                    }
                    $this->SetUser($user, 1);

                    // if bazarMapping is defined and the bazar user entry does't exist, create it
                    $bazarMapping = $this->config['sso_config']['hosts'][$_GET['provider']]['bazar_mapping'];
                    if (!empty($bazarMapping)) {
                        $entry = bazarEntryExists($this->config['sso_config']['bazar_user_entry_id'], $user['name']);
                        if (!$entry) {
                            $this->redirect($this->href('createentry', 'BazaR', 'provider=' . $_GET['provider'] . '&username=' . $user['name'] . '&attr=' . rawurlencode(serialize($ssoUser)), false));
                        } else {
                            // TODO penser à vérifier si les données de l'utilisateur ont changé et les mettre à jour le cas échéant
                            // $GLOBALS['wiki']->SetMessage('La fiche a été mise à jour');
                        }
                    }

                    // if the PageMenuUser page doesn't exist, create it with a default version
                    if (!$this->LoadPage('PageMenuUser')) {
                        $this->SavePage('PageMenuUser', "{{linktouserprofil dash=\"1\"}}\n - [[UserEntries " . _t('SSO_SEE_USER_ENTRIES') . ']]');
                    }
                    // if the UserEntries page doesn't exist, create it with a default version
                    if (!$this->LoadPage('UserEntries')) {
                        $this->SavePage('UserEntries', '===='._t('SSO_USER_ENTRIES') . '====' . "\n{{userentries}}");
                    }

                    // remove the get parameters used for the connection
                    $incomingurl = str_replace(array('wiki=', '&action=connectOAUTH'), '', $incomingurl);
                    $incomingurl = preg_replace('(&provider=[^&]*)', '', $incomingurl);

                    $this->redirect($incomingurl);
                }
            } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
                exit(_t('SSO_ERROR'). ". " . _t("SSO_ERROR_DETAIL") . $e->getMessage());
            }
        }
    }

    // cas d'une personne connectée déjà
    if ($user = $this->GetUser()) {    
        $connected = true;

        // Load the PageMenuUser page if it exists
        if ($this->LoadPage("PageMenuUser")) {
            $PageMenuUser .= $this->Format("{{include page=\"PageMenuUser\"}}");
        }

    } else {
        // cas d'une personne non connectée
        $connected = false;

        // Load the ConnectionDetails page if it exists
        if ($this->LoadPage('ConnectionDetails')){
            $ConnectionDetails .= $this->Format('{{include page="ConnectionDetails"}}');
        }
    }

    //
    // on affiche le template
    //
    include_once 'includes/squelettephp.class.php';
    try {
        $squel = new SquelettePhp($template, 'login-sso');
        $content = $squel->render(
            array(
                "connected" => $connected,
                "user" => ((isset($user["name"])) ? $user["name"] : ((isset($_POST["name"])) ? $_POST["name"] : '')),
                "email" => ((isset($user["email"])) ? $user["email"] : ((isset($_POST["email"])) ? $_POST["email"] : '')),
                "incomingurl" => $incomingurl,
                "PageMenuUser" => $PageMenuUser,
                "ConnectionDetails" => $ConnectionDetails,
                "ssoHosts" => $this->config['sso_config']['hosts'],
                "btnclass" => $btnclass,
                "nobtn" => $nobtn,
                "error" => $error
            )
        );
    } catch (Exception $e) {
        $content = '<div class="alert alert-danger">' . _t('SSO_ACTION_ERROR') .  $e->getMessage(). '</div>'."\n";
    }

    echo (!empty($class)) ? '<div class="'.$class.'">'."\n".$content."\n".'</div>'."\n" : $content;

} else {
    $content = '<div class="alert alert-danger">' . _t('$SSO_CONFIG_ERROR') . '</div>'."\n";
}