<?php
/**
 * Basic config of the login-sso module
 *
 * @param
 *
 * @category YesWiki
 * @package  login-sso
 * @author   Adrien Cheype <adrien.cheype@gmail.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.en.html AGPL 3.0
 * @link     https://yeswiki.net
 */

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

require __DIR__ . '/vendor/autoload.php';

// using a table to enable multiple sso account
if (empty($wakkaConfig['sso_config'])) {
    $wakkaConfig['sso_config'] = [
        // the form id for the bazar entry corresponding to the connected user
        // if defined, a link will propose to show him his user information (profile)
        // don't declare it, if you don't need to have bazar entries related to users
        'bazar_user_entry_id' => '1',
        'hosts' => [
            // each entry here is an array corresponding to a SSO host
            [
                // the authentification auth type, two protocol/service are supported : 'oauth2' and 'cas'
                'auth_type'    => 'oauth2',
                'auth_options' => [
                    'clientId'                => 'myclientid',    // The client ID assigned to you by the provider
                    'clientSecret'            => 'mysecretclientkey',   // The client secret assigned to you by the provider
                    'urlAuthorize'            => 'https://myserver/auth/realms/master/protocol/openid-connect/auth',
                    'urlAccessToken'          => 'https://myserver/auth/realms/master/protocol/openid-connect/token',
                    'urlResourceOwnerDetails' => 'https://myserver/auth/realms/master/protocol/openid-connect/userinfo',
                ],
                'style' => [
                    // name used for the login button
                    'button_label' => 'Les communs',
                    // class of this button
                    'button_class' => 'btn btn-default btn-lescommuns',
                    // icon used for this button (class of the <i>)
                    'button_icon' => 'glyphicon glyphicon-log-in'
                ],
                // if bazar_mapping is defined, the module will create an bazar entry for the user at his first connection
                // in this case, bazar_user_entry_id needs also to be defined
                'bazar_mapping' => [
                    // the fields mapping between the yeswiki entry fields and the sso fields
                    'fields' => [
                        // array synthax : 'yeswiki entry field name' =>  'SSO server field name'
                        'bf_nom' => 'family_name',
                        'bf_prenom' => 'given_name',
                        'bf_email' => 'email'
                    ],
                    // if 'bf_titre' is not defined as a key in 'bazar_mapping.fields', you can define this property to format specially 'bf_titre' with
                    // other sso fields (defined in bazar_mapping.fields as values)
                    // the format is a string in which you can refer to others sso fields by specify #[field_name]. Per example this example of
                    // bf_titre_format : '#[given_name] #[family_name]' will set the full name of a person assuming that the two fields have been defined in the sso user information
                    // this format will not be applied if the user have choosen to anonymize its data
                    'bf_titre_format' => '#[given_name] #[family_name]',
                    // if some fields need to be transformed in an other format, you can defined an associative array of each transformation
                    // the will be no transformation when the user have choosen to anonymize its data
                    'fields_transformed' => [
                        // each element must defined 'yeswiki_entry_field', 'sso_field', 'pattern' and 'replacement' keys
                        [
                            // the yeswiki entry field name which be the result of the transformation, it MUST BE already defined as a key in bazar_mapping
                            'yeswiki_entry_field' => 'bf_email',
                            // the sso field name used for the transformation, it has to be defined in the sso user information but doesn't need to be declared
                            // as a value in 'bazar_mappings.fields'
                            'sso_field' => 'email',
                            // regular expression pattern which match with the value of the sso_field, it has to extract some groups used in replacement
                            // see https://www.php.net/manual/en/function.preg-replace.php for more details about the syntax
                            'pattern' => '/([a-z\-_\.]+)@([a-z\-_\.]+)/i',
                            // '/\s*(\d+\.\d*)\s*,\s*(\d+\.\d*)\s/'
                            // the pattern which defines the final value set for the yeswiki entry field by referring the pattern groups
                            // see https://www.php.net/manual/en/function.preg-replace.php for more details about the syntax
                            'replacement' => '$1|$2'
                        ]
                    ],
                    // access defined to view the user entry ('+' by default)
                    'read_access_entry' => '+',
                    // access defined to modify the user entry ('%' by default)
                    'write_access_entry' => '%',
                    // message displayed before to create the user entry
                    'entry_creation_information' => '<p>C\'est votre première connexion avec ce compte. Une fiche avec vos informations personnelles va être créée dans le but de faciliter la mise en
                        lien entre les utilisateurs. Les données suivantes - Prénom, Nom, E-mail, Adresse postale - vont êtres récupérées directement depuis le serveur d\'authentification et pourront 
                        être modifiées / supprimées plus tard à votre convenance dans "Mes fiches".</p>',
                    // if anonymize is defined, a question is asked before to know if the user wants to be anonymous
                    'anonymize' => [
                        // consent question asked before the creation of the user entry (html tag are allowed)
                        'consent_question' => '<p>Acceptez-vous que ces informations personnelles soient utilisées sur ce site ?<br>
                              Si oui, ces données seront sauvées et rendues visibles aux autres utilisateurs (sauf le mail).<br>
                              Si vous refusez, seules vos initiales et votre pseudo de connexion seront inscrits dans votre fiche, et votre mail sera sauvegardé mais caché aux autres utilisateurs.</p>
                              <p>Nous rappelons aussi que nous ne faisons rien d\'autre de ces données que de les afficher sur la fiche de votre profil (pas de revente, ni d\'exploitation).</p>',
                        // only the first character will be kept for the followed fields
                        // each field refers to yeswiki entry field and MUST BE already defined as a key in bazar_mapping.fields
                        'fields_to_anonymize' => ['bf_nom', 'bf_prenom'],
                        // all the content will be copied for the followed fields
                        // each field refers to the yeswiki entry field and MUST BE already defined as a key in bazar_mapping.fields
                        'fields_to_keep' => ['bf_email'],
                        // special value given for 'bf_titre' in case of an anonymized entry
                        'bf_titre_value' => 'Utilisateur anonyme'
                    ]
                ]
            ]
        ]
    ];
}