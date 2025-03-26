# Changelog

## V1.1.7

Fix migration to Yeswiki 4.5
- Fix desc.xml file syntax

## V1.1.5

add custom scope option

## V1.1.4

use new conventions from YesWiki

## V1.1.3

Fix typo in documentation
Improve error handling on 

## V1.1.2

Allow initial for username generation when username in PascalCase or camelCase

## V1.1.1

Allow display initials instead of username on login modal
Fix group config invalid on user group suppression

## V1.1.0

This extension is a replacement for the yeswiki-extension-login-sso,
due to an incompatibility between dash in the extension name and the new autoloading YesWiki system.
It also refactor the code to use the new YesWiki class based action system and twig templates.

Then it add a few new features for better SSO integration :

- Add a new configuration option 'id_sso_field' to link the user between the SSO server and the YesWiki user and avoid using email as primary key if wanted
- Support for email update on SSO server side if another key is used to link the user
- Use a fixed URL for callback from server, increasing compatibility with LemonLDAP::NG
- Allow group mapping from SSO groups to YesWiki groups
