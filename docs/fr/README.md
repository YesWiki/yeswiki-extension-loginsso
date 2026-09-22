# Extension loginsso

Remplace l'identification par nom et mot de passe de YesWiki par une authentification
sur un serveur OAUTH, avec support OIDC et synchronisation des groupes.

## Configuration

Après installation, ajouter le bloc suivant à `wakka.config.php` et l'ajuster à vos
besoins.

```php
'sso_config' => [
    /*
     * Identifiant du formulaire bazar correspondant à la personne connectée.
     * S'il est défini, un lien lui propose de voir sa fiche de profil.
     * À ne pas déclarer si vous ne voulez pas de fiche liée aux comptes.
     */
    'bazar_user_entry_id' => 1000,
    // true affiche les initiales plutôt que le nom complet dans la fenêtre de connexion
    'login_username_initials' => false,
    // chaque entrée est un tableau décrivant un fournisseur d'identité
    'providers' => [
        [
            // protocole d'authentification : 'oauth2' ou 'cas'
            'auth_type' => 'oauth2',
            'auth_options' => [
                'clientId' => 'myclientid',
                'clientSecret' => 'mysecretclientkey',
                'urlAuthorize' => 'https://monserveur/auth/realms/master/protocol/openid-connect/auth',
                'urlAccessToken' => 'https://monserveur/auth/realms/master/protocol/openid-connect/token',
                'urlResourceOwnerDetails' => 'https://monserveur/auth/realms/master/protocol/openid-connect/userinfo',
                // facultatif : portée du jeton openid, 'openid' par défaut
                'scopes' => ['openid', 'custom_scope'],
                // facultatif : séparateur de portées, espace par défaut
                'scopeSeparator' => ' ',
                // facultatif : ajoute un '=' final à l'URL de retour, requis par certains fournisseurs, true par défaut
                'addFinalEqual' => true,
            ],
            // champ du serveur SSO portant l'identifiant, il relie un compte SSO à un compte YesWiki
            'id_sso_field' => 'id',
            // champ du serveur SSO portant l'adresse de courriel
            'email_sso_field' => 'email',
            // champ du serveur SSO portant les groupes
            'groups_sso_field' => 'groups',
            // correspondance des groupes LDAP vers les groupes YesWiki, les autres sont ignorés
            'groups_sso_mapping' => [
                'group_ldap' => 'group_wiki',
            ],
            /*
             * Si 'create_user_from' est défini, un compte YesWiki avec un nom et un courriel est créé.
             * Le nom est un identifiant unique fabriqué depuis ce format, où #[champ] renvoie à un champ SSO.
             * S'il n'est pas défini, seuls les comptes SSO ayant déjà un compte YesWiki avec la même
             * adresse sont acceptés.
             * Avec '#[given_name] #[family_name]', « Jean Dupond » donne l'identifiant 'JeanDupond',
             * puis 'JeanDupond2' si le premier existe déjà.
             */
            'create_user_from' => '#[given_name] #[family_name]',
            // apparence du bouton de connexion de ce fournisseur
            'button_style' => [
                'button_label' => 'Mon serveur d\'authentification',
                'button_class' => 'btn btn-default btn-myauth',
                'button_icon' => 'glyphicon glyphicon-log-in',
            ],
            /*
             * Une page wiki nommée 'ConnectionDetails' est affichée au-dessus des boutons, si elle existe.
             * Si 'bazar_mapping' est défini, une fiche est créée à la première connexion.
             * Dans ce cas 'bazar_user_entry_id' doit être défini.
             */
            'bazar_mapping' => [
                'fields' => [
                    /*
                     * correspondance « champ de la fiche » => « champ du serveur SSO »
                     * 'bf_titre' ne peut pas être défini ici : il vient de 'create_user_from',
                     * mais contrairement au nom de compte ce n'est pas un identifiant.
                     */
                    'bf_nom' => 'family_name',
                    'bf_prenom' => 'given_name',
                    'bf_email' => 'email',
                ],
                /*
                 * Transformations de format, appliquées champ par champ.
                 * Rien n'est transformé si la personne a choisi l'anonymat.
                 * Chaque élément porte les clés 'yeswiki_entry_field', 'sso_field', 'pattern' et 'replacement'.
                 */
                'fields_transformed' => [
                    [
                        // cet exemple transforme 'adresse@serveur.com' en 'adresse|serveur.com'
                        // le champ de destination doit déjà être une clé de 'fields'
                        'yeswiki_entry_field' => 'bf_email',
                        // le champ SSO source doit exister côté serveur, sans devoir figurer dans 'fields'
                        'sso_field' => 'email',
                        // expression régulière appliquée à la valeur du champ SSO
                        'pattern' => '/([ a-z\-_\. ]+)@([ a-z\-_\. ]+)/i',
                        // valeur finale, construite depuis les groupes capturés
                        'replacement' => '$1|$2',
                    ],
                ],
                // droit de lecture de la fiche, '+' par défaut
                'read_access_entry' => '+',
                // droit d'écriture de la fiche, '%' par défaut
                'write_access_entry' => '%',
                // message affiché avant la création de la fiche
                'entry_creation_information' => "<p>C'est votre première connexion avec ce compte. Une fiche avec vos informations personnelles va être créée dans le but de faciliter la mise en lien entre les utilisateurs. Les données suivantes - Prénom, Nom, E-mail - vont être récupérées directement depuis le serveur d'authentification et pourront être modifiées ou supprimées plus tard à votre convenance dans 'Mes fiches'.</p>",
                // si 'anonymize' est défini, une question de consentement est posée avant la création
                'anonymize' => [
                    'consent_question' => "<p>Acceptez-vous que ces informations personnelles soient utilisées sur ce site ?<br>Si oui, ces données seront sauvées et rendues visibles aux autres utilisateurs (sauf le mail).<br>Si vous refusez, seules vos initiales et votre pseudo de connexion seront inscrits dans votre fiche, et votre mail sera sauvegardé mais caché aux autres utilisateurs.</p><p>Nous rappelons aussi que nous ne faisons rien d'autre de ces données que de les afficher sur la fiche de votre profil (pas de revente, ni d'exploitation).</p>",
                    // seule la première lettre de ces champs est conservée, ils doivent être des clés de 'fields'
                    'fields_to_anonymize' => [
                        'bf_nom',
                        'bf_prenom',
                    ],
                    // ces champs sont copiés en entier, ils doivent être des clés de 'fields'
                    'fields_to_keep' => [
                        'bf_email',
                    ],
                    /*
                     * Pour une personne anonyme, 'bf_titre' prend cette valeur, et le nom de compte
                     * en est dérivé : 'UtilisateurAnonyme', puis 'UtilisateurAnonyme2', etc.
                     */
                    'bf_titre_value' => 'Utilisateur anonyme',
                ],
            ],
        ],
    ],
]
```

## Configurer le serveur OIDC

Le serveur OIDC doit accepter la redirection venant de votre YesWiki. Ajouter l'URL
correspondante à la liste des redirections autorisées.

| `addFinalEqual` | URL de retour à déclarer |
|---|---|
| `true` ou absent | `https://[wiki]/?api/auth_sso/callback=` |
| `false` | `https://[wiki]/?api/auth_sso/callback` |

## Extensions voisines

`logincas` fait le même travail contre un serveur CAS, et `loginldap` contre un annuaire
LDAP. Une seule extension d'authentification peut être active à la fois : YesWiki charge
les extensions par ordre alphabétique et la dernière chargée l'emporte.
