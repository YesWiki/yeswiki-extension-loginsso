# yeswiki-extension-loginsso

Use SSO OIDC server authentification in YesWiki.

This extension replace defaut user/password login.


## Add configuration option
After installation, you must add the following configuration in your waka.config.php file and configure according to your needs

```php
'sso_config' => [
    /*
     * The form id for the bazar entry corresponding to the connected user
     * if defined, a link propose to show him his user information (profile)
     * don't declare it, if you don't need to have bazar entries related to users
    */
    'bazar_user_entry_id' => 1000,
    // if true, the display the initials of the user name instead of the full name on login modal
    'login_username_initials' => false,
    // each entry here is an array corresponding to a SSO provider
    'providers' => [
        [
            // the authentification auth type, two protocols are supported: 'oauth2' and 'cas'
            'auth_type' => 'oauth2',
            'auth_options' => [
                'clientId' => 'myclientid',
                'clientSecret' => 'mysecretclientkey',
                'urlAuthorize' => 'https:#myserver/auth/realms/master/protocol/openid-connect/auth',
                'urlAccessToken' => 'https:#myserver/auth/realms/master/protocol/openid-connect/token',
                'urlResourceOwnerDetails' => 'https:#myserver/auth/realms/master/protocol/openid-connect/userinfo',
                // optionnal set custom scope for openid token. Default to openid
                'scopes' => ['openid', 'custom_scope'],
                 // optionnal set custom scope seperator. Default to ' '
                'scopeSeparator' => ' ',
                // optionnal add a final equal to the redirect uri. Somme providers need it, some don't. Default to yes
                'addFinalEqual' => true,
            ],
            // sso server fieldname used for the user id, this field links an SSO user to a yeswiki user
            'id_sso_field' => 'id',
            // sso server fieldname used for the user email
            'email_sso_field' => 'email',
            // sso server fieldname used for the user groups
            'groups_sso_field' => 'groups',
            // map LDAP groups to YesWiki groups. Groups not listed here will be ignored
            'groups_sso_mapping' => [
                'group_ldap' => 'group_wiki'
            ],
            /*
            * if create_user_from is defined, an yeswiki user with a name and an email is created.
            * the username is an unique word (ID) generated from the format create_user_form by specifying #[field_name] to referring to a sso field
            * if not defined, the authentification module accepts only sso users which have an yeswiki user corresponding to this email
            * for example, '#[given_name] #[family_name]' sets the full name of a person assuming that the two fields have been defined in the sso user information
            * for 'Jean Dupond' it creates a 'JeanDupond' identifier, and if one already exists in the database, it sets 'JeanDupond2'
            */
            'create_user_from' => '#[given_name] #[family_name]',
            // style of the login button which corresponds to the provider
            'button_style' => [
                // name used for the login button
                'button_label' => 'My Auth Server',
                // class of this button
                'button_class' => 'btn btn-default btn-myauth',
                // icon used for this button (class of the <i>)
                'button_icon' => 'glyphicon glyphicon-log-in'
            ],
            /* you can also write a wiki page named 'ConnectionDetails' to inform the user before the buttons are displayed
            * if bazar_mapping is defined, the module will create an bazar entry for the user at his first connection
            * in this case, bazar_user_entry_id needs to be defined
            */
            'bazar_mapping' => [
                // the fields mapping between the yeswiki entry fields and the sso fields
                'fields' => [
                    /*
                    * mapping: 'yeswiki entry fieldname:  'SSO server fieldname'
                    * you can't define 'bf_title' because it will automatically value determined by the above 'create_user_from' but contrary to the username,
                    * the it's not an identifier create from this value
                    */
                    'bf_nom' => 'family_name',
                    'bf_prenom' => 'given_name',
                    'bf_email' => 'email'
                ],
                /*
                * if some fields need to be transformed in an other format, you can defined an associative array of each transformation
                * no transformation will be made if the user has chosen to anonymize its data
                * each element must have the 'yeswiki_entry_field', 'sso_field', 'pattern' and 'replacement' keys
                */
                'fields_transformed' => [
                    // the first transformation
                    [
                        /*
                        * this example transforms an address like 'myadress@myserver.com' to this address: 'myadress|myserver.com'
                        * the yeswiki entry fieldname which be the result of the transformation, it MUST BE already defined as a key in bazar_mapping
                        */
                        'yeswiki_entry_field' => 'bf_email',
                        /*
                        * the sso fieldname used for the transformation, it has to be defined in the sso user information but doesn't need to be declared
                        * as a value in 'bazar_mappings.fields'
                        */
                        'sso_field' => 'email',
                        /*
                        * regular expression pattern which match with the value of the sso_field, it has to extract some groups used in replacement
                        * see https:#www.php.net/manual/en/function.preg-replace.php for more details about the syntax
                        */
                        'pattern' => '/([ a-z\-_\. ]+)@([ a-z\-_\. ]+)/i',
                        /* '/\s*(\d+\.\d*)\s*,\s*(\d+\.\d*)\s/'
                        * the pattern which defines the final value set for the yeswiki entry field by referring the pattern groups
                        * see https:#www.php.net/manual/en/function.preg-replace.php for more details about the syntax
                        */
                        'replacement' => '$1|$2'
                    ],
                    // access defined to view the user entry ('+' by default)
                    'read_access_entry' => '+',
                    // access defined to modify the user entry ('%' by default)
                    'write_access_entry' => '%',
                    // message displayed before to create the user entry
                    'entry_creation_information' => "<p>C'est votre première connexion avec ce compte. Une fiche avec vos informations personnelles va être créée dans le but de faciliter la mise en
    lien entre les utilisateurs. Les données suivantes - Prénom, Nom, E-mail - vont êtres récupérées directement depuis le serveur d'authentification et pourront être modifiées
    ou supprimées plus tard à votre convenance dans 'Mes fiches'.</p>",
                    // if anonymize is defined, a question is asked before to know if the user wants to be anonymous
                    'anonymize' => [
                        //  consent question asked before the creation of the user entry (html tag are allowed), if the user responds 'no' his data will be transformed as below
                        'consent_question' => "<p>Acceptez-vous que ces informations personnelles soient utilisées sur ce site ?<br>
    Si oui, ces données seront sauvées et rendues visibles aux autres utilisateurs (sauf le mail).<br>
    Si vous refusez, seules vos initiales et votre pseudo de connexion seront inscrits dans votre fiche, et votre mail sera sauvegardé mais caché aux autres utilisateurs.</p>
<p>Nous rappelons aussi que nous ne faisons rien d'autre de ces données que de les afficher sur la fiche de votre profil (pas de revente, ni d'exploitation).</p>",
                        /*
                        * only the first character will be kept for the followed fields
                        * each field refers to yeswiki entry field and MUST BE already defined as a key in bazar_mapping.fields
                        */
                        'fields_to_anonymize' => [
                            'bf_nom',
                            'bf_prenom'
                        ],
                        /*
                         * all the content will be copied for the followed fields
                         * each field refers to the yeswiki entry field and MUST BE already defined as a key in bazar_mapping.fields
                         */
                        'fields_to_keep' => [
                            'bf_email'
                        ],
                        /*
                        * in case of an anonymize user, 'bf_titre' is replaced by this value
                        * the username will also be also with an unique word (id) according to this value. Per example, for the value 'Utilisateur anonyme',
                        * we will have the following ids: first 'UtilisateurAnonyme', then 'UtilisateurAnonyme2' for the second user, etc.
                        */
                        'bf_titre_value' => 'Utilisateur anonyme'
                    ]
                ]
            ]
        ]
    ]
]
```

## Configure the OIDC server

You must configure the OIDC server to accept the redirection from your YesWiki instance.
Add the following URL to the list of allowed redirections:

If `addFinalEqual` is set to true or not defined, the URL must be `https://[wiki]/?api/auth_sso/callback=` otherwise it must be
`https://[wiki]/?api/auth_sso/callback`

## TODO

- merge with yeswiki-extension-login-cas to permit the connection with a CAS server


