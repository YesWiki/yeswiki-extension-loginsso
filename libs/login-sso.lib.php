<?php
/**
 * Library of SSO login users functions
 *
 * @category YesWiki
 * @package  login-sso
 * @author   Florian Schmitt <mrflos@lilo.org>
 * @author   Adrien Cheype <adrien.cheype@gmail.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.en.html AGPL 3.0
 * @link     https://yeswiki.net
 */

/**
 * Load the user by its mail
 * (a mail has to be associated to only one user)
 *
 * @param $name the user email
 * @return string[] Returns an associative array of strings representing the user row
 */
function loadUserByMail($mail)
{
    return $GLOBALS['wiki']->LoadSingle("select * from " . $GLOBALS['wiki']->config['table_prefix'] . "users where email = '" . mysqli_real_escape_string($GLOBALS['wiki']->dblink, $mail) . "' limit 1");
}

/**
 * Check if the bazar mapping in SSO config file is well defined for a specific host
 *
 * @param array $config the SSO config
 * @param int the host index
 * @return bool if the config is correct
 */
function checkBazarMappingConfig($config, $provider)
{
    if (!empty($config['sso_config']) && !empty($config['sso_config']['providers']) && !empty($config['sso_config']['providers'][$provider])){
        $bazar = $config['sso_config']['providers'][$provider]['bazar_mapping'];

        return (isset($bazar) && isset($config['sso_config']['bazar_user_entry_id']) &&
            !empty($bazar['fields']) && isset($bazar['entry_creation_information']) &&
                ( empty($bazar['anonymize']) ||
                    !empty($bazar['anonymize']) && isset($bazar['anonymize']['consent_question']) &&
                    ( !empty($bazar['anonymize']['fields_to_anonymize']) || !empty($bazar['anonymize']['fields_to_keep']) )
                ) &&
                ( empty($bazar['fields_transformed']) ||
                    !empty($bazar['fields_transformed']) &&
                        array_filter($bazar['fields_transformed'], function($ft) {
                            return isset($ft['yeswiki_entry_field']) &&
                                isset($ft['sso_field']) &&
                                isset($ft['pattern']) &&
                                isset($ft['replacement']);
                        })
                )
        );
    } else return false;
}

/**
 * Check if there is an entry of ficheId type created by the user and with the name of the user
 *
 * @param int $entryTypeId the bazar entry type to search
 * @param string $username name of the user
 *
 * @return boolean true if the entry is found, false otherwise
 */
function bazarUserEntryExists($entryTypeId, $username)
{
    include_once 'tools/bazar/libs/bazar.fonct.php';

    // first search the entries of $entryTypeId type which have the $username as owner
    $res = baz_requete_recherche_fiches('', '', $entryTypeId, '', 1, $username);
    // then check if there is an entry which have $username as tag
    return !empty(array_filter($res, function ($element) use ($username) {return ($element['tag'] == $username);}));
}

/**
 * Create a user bazar entry from selected SSO attributes
 *
 * @param array   $bazarMapping    yeswiki config for the bazar mapping
 * @param int     $bazarEntryId    the bazar entry id for the entry created
 * @param string  $user_title_format   the user title format used to create a 'bf_titre'
 * @param array   $ssoUser      authentified sso user fields
 * @param boolean $anonymous does the user want to be anonymous ?
 *
 * @return array bazar entry formatted values
 */
function createUserBazarEntry($bazarMapping, $bazarEntryId, $user_title_format, $ssoUser, $anonymous = false)
{
    $fiche = array();
    $fiche['id_typeannonce'] = $bazarEntryId;

    if ($anonymous && isset($bazarMapping['anonymize'])) {
        foreach ($bazarMapping['anonymize']['fields_to_anonymize'] as $yeswikiField){
            // for the fields to anonymize, set only the fist character of the content
            $fiche[$yeswikiField] = isset($ssoUser[$bazarMapping['fields'][$yeswikiField]]) ? substr(trim($ssoUser[$bazarMapping['fields'][$yeswikiField]]), 0, 1) : '';
        }
        foreach ($bazarMapping['anonymize']['fields_to_keep'] as $yeswikiField){
            // for the fields to keep, copy all the content
            $fiche[$yeswikiField] = isset($ssoUser[$bazarMapping['fields'][$yeswikiField]]) ? $ssoUser[$bazarMapping['fields'][$yeswikiField]] : '';
        }
        // set a fixed value defined in the config field for 'bf_titre'
        $fiche['bf_titre'] = $bazarMapping['anonymize']['bf_titre_value'];
    } else {
        foreach ($bazarMapping['fields'] as $yeswikiField => $ssoField) {
            if (!isset($ssoUser[$ssoField])) {
                $fiche[$yeswikiField] = '';
            } else {
                $transformDone = false;
                foreach ($bazarMapping['fields_transformed'] as $fieldTransformed) {
                    if ($fieldTransformed['field'] == $yeswikiField) {
                        // transform the sso field according to the pattern and replacement formats defined in the config file
                        $fiche[$yeswikiField] = preg_replace($fieldTransformed['pattern'], $fieldTransformed['replacement'], $ssoUser[$fieldTransformed['sso_field']]);
                        $transformDone = true;
                    }
                }
                if (!$transformDone){
                    // if the yeswiki field has not been found in the fields_transformed config, copy the content
                    $fiche[$yeswikiField] = $ssoUser[$ssoField];
                }
            }
        }

        // define the 'bf_titre' entry by replacing the '$user_title_format' field names by their values in the sso fields
        $bf_titre = $user_title_format;
        foreach ($ssoUser as $ssoField => $ssoValue)
            $bf_titre = str_replace("#[$ssoField]", $ssoUser[$ssoField], $bf_titre);
        $fiche['bf_titre'] = $bf_titre;
    }

    return $fiche;
}

/**
 *  genere_nom_user : fonction dérivé de genere_nom_wiki mais qui trouve un nom qui en plus n'est pas pris par un utilisateur
 *
 *  Prends une chaine de caracteres, et la tranforme en NomWiki unique, en la limitant
 *  a 50 caracteres et en mettant 2 majuscules
 *  Si le NomWiki existe deja ou s'il existe un utilisateur avec name = NomWiki, on propose recursivement NomWiki2, NomWiki3, etc..
 *
 *   @param  string  chaine de caracteres avec de potentiels accents a enlever
 *   @param int nombre d'iteration pour la fonction recursive (1 par defaut)
 *
 *
 *   return  string chaine de caracteres, en NomWiki unique
 */
function genere_nom_user($nom, $occurence = 1)
{
    // si la fonction est appelee pour la premiere fois, on nettoie le nom passe en parametre
    if ($occurence <= 1) {
        // les noms wiki ne doivent pas depasser les 50 caracteres, on coupe a 48
        // histoire de pouvoir ajouter un chiffre derriere si nom wiki deja existant
        // plus traitement des accents et ponctuation
        // plus on met des majuscules au debut de chaque mot et on fait sauter les espaces
        $temp = removeAccents(mb_substr(preg_replace('/[[:punct:]]/', ' ', $nom), 0, 47, YW_CHARSET));
        $temp = explode(' ', ucwords(strtolower($temp)));
        $nom = '';
        foreach ($temp as $mot) {
            // on vire d'eventuels autres caracteres speciaux
            $nom .= preg_replace('/[^a-zA-Z0-9]/', '', trim($mot));
        }

        // on verifie qu'il y a au moins 2 majuscules, sinon on en rajoute une a la fin
        $var = preg_replace('/[^A-Z]/', '', $nom);
        if (strlen($var) < 2) {
            $last = ucfirst(substr($nom, strlen($nom) - 1));
            $nom = substr($nom, 0, -1).$last;
        }

        $nom = '';
        foreach ($temp as $mot) {
            // on vire d'eventuels autres caracteres speciaux
            $nom .= preg_replace('/[^a-zA-Z0-9]/', '', trim($mot));
        }

        // on verifie qu'il y a au moins 2 majuscules, sinon on en rajoute une a la fin
        $var = preg_replace('/[^A-Z]/', '', $nom);
        if (strlen($var) < 2) {
            $last = ucfirst(substr($nom, strlen($nom) - 1));
            $nom = substr($nom, 0, -1).$last;
        }
    } elseif ($occurence > 2) {
        // si on en est a plus de 2 occurences, on supprime le chiffre precedent et on ajoute la nouvelle occurence
        $nb = -1 * strlen(strval($occurence - 1));
        $nom = substr($nom, 0, $nb).$occurence;
    } else {
        // cas ou l'occurence est la deuxieme : on reprend le NomWiki en y ajoutant le chiffre 2
        $nom = $nom.$occurence;
    }

    if ($occurence == 0) {
        // pour occurence = 0 on ne teste pas l'existance de la page
        return $nom;
    } elseif (!is_array($GLOBALS['wiki']->LoadPage($nom)) && !$GLOBALS['wiki']->LoadUser($nom)) {
        // on verifie que la page n'existe pas deja : si c'est le cas on le retourne
        return $nom;
    } else {
        // sinon, on rappele recursivement la fonction jusqu'a ce que le nom aille bien
        ++$occurence;

        return genere_nom_user($nom, $occurence);
    }
}