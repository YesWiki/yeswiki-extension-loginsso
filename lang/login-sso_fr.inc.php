<?php
/**
 * French translation
 * 
 * @category YesWiki
 * @package  login-sso
 * @author   Adrien Cheype <adrien.cheype@gmail.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.en.html AGPL 3.0
 * @link     https://yeswiki.net
 */

$GLOBALS['translations'] = array_merge(
    $GLOBALS['translations'],
    array(
        'SSO_CONNECT' => 'Connecter avec ce serveur d\'authentification',
        'SSO_ENTRY_CREATE' => 'Création de votre fiche',
        'SSO_YES_CONSENT' => 'OK, j\'accepte !',
        'SSO_NO_CONSENT' => 'Non merci',
        'SSO_OK_ENTRY_CREATION' => 'OK',
        'SSO_CONFIG_ERROR' => 'Une erreur de paramétrage du module de connexion SSO a été détectée dans le fichier de configuration. Veuillez vous référer à la documentation pour écrire les bons paramètres.',
        'SSO_ERROR' => 'Erreur detecté dans le module de connexion SSO, vous n\'êtes pas connecté',
        'SSO_ERROR_DETAIL' => 'Détail : ',
        'SSO_ACTION_ERROR' => 'Erreur détectée dans l\'action login du module login_sso : ',
        'SSO_AUTH_TYPE_ERROR' => 'Le type d\'auth défini dans le fichier de configuration n\'est pas supporté (\'cas\' ou \'oauth2\' disponible)',
        'SSO_AUTH_OPTIONS_ERROR' => 'les options d\'authentification dans \'auth_options\' doivent être renseignés : \'clientId\', \'clientSecret\', \'redirectUri\', \'urlAuthorize\', \'urlAccessToken\', \'urlResourceOwnerDetails\'',
        'SSO_USERFIELDS_REQUIRED' => 'Les paramètres \'user_fields\'/\'email\' et \'user_fields\'/\'user_title_format\' servant au mapping avec les utilisateurs de YesWiki doivent être renseignés dans le fichier de configuration',
        'SSO_CONNECT_REQUIRED' => 'Une connexion est requise pour voir la page.',
        'SSO_SEE_USER_PROFIL' => 'Consulter mon profil',
        'SSO_SEE_USER_ENTRIES' => 'Voir mes fiches',
        // same msg exists in bazar : BAZ_VOIR_VOS_FICHES
        'SSO_USER_ENTRIES' => 'Mes fiches',
        'SSO_USER_NOT_FOUND' => 'Erreur dans l\'affichage des fiches. L\'utilisateur suivant n\'existe pas : ',
        // same msg exists in bazar : BAZ_PAS_DE_FICHES_SAUVEES_EN_VOTRE_NOM
        'SSO_NO_USER_ENTRIES' => 'Vous n\'avez aucune fiche enregistr&eacute;e pour l\'instant.',
        'SSO_OLD_USER_UPDATED' => 'Votre compte utilisateur a été mis à jour suite à cette première connexion avec le serveur d\'authentification.<br/><br/><b>Attention</b> : vous n\'êtes dorénavant plus le propriétaire des anciennes fiches et pages que vous aviez. Contactez votre administrateur si vous avez besoin d\'être à nouveau propritétaire de celles-ci.'
    )
);