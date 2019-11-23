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
 * Check if the bazar mapping in SSO config file is well defined for a specific host
 *
 * @param array $config the SSO config
 * @param int the host index
 * @return bool if the config is correct
 */
function checkBazarMappingConfig($config, $provider)
{
    if (!empty($config['sso_config']) && !empty($config['sso_config']['hosts']) && !empty($config['sso_config']['hosts'][$provider])){
        $bazar = $config['sso_config']['hosts'][$provider]['bazar_mapping'];

        return (isset($bazar) && isset($config['sso_config']['bazar_user_entry_id']) &&
            !empty($bazar['fields']) && isset($bazar['entry_creation_information']) &&
                ( array_key_exists('bf_titre', $bazar['fields']) ||
                    !array_key_exists('bf_titre', $bazar['fields']) && isset($bazar['bf_titre_format'])) &&
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
 * Check if there is an entry of ficheId type created by the user
 *
 * @param int $ficheId the bazar entry type to search
 * @param string $user name of the user
 *
 * @return string page tag for entry
 */
function bazarEntryExists($ficheId, $user)
{
    include_once 'tools/bazar/libs/bazar.fonct.php';
    $res = baz_requete_recherche_fiches('', '', $ficheId, '', 1, $user);
    return isset($res[0]['tag']) ? $res[0]['tag'] : false;
}

/**
 * Create a bazar entry from selected SSO attributes
 *
 * @param array   $bazarMapping    yeswiki config for the bazar mapping
 * @param int     $bazarEntryId    the bazar entry id for the entry created
 * @param array   $user      authentified sso user fields
 * @param boolean $anonymous does the user want to be anonymous ?
 *
 * @return array bazar entry formatted values
 */
function createBazarEntry($bazarMapping, $bazarEntryId, $user, $anonymous = false)
{
    $fiche = array();
    $fiche['id_typeannonce'] = $bazarEntryId;

    if ($anonymous && isset($bazarMapping['anonymize'])) {
        foreach ($bazarMapping['anonymize']['fields_to_anonymize'] as $yeswikiField){
            // for the fields to anonymize, set only the fist character of the content
            $fiche[$yeswikiField] = isset($user[$bazarMapping['fields'][$yeswikiField]]) ? substr(trim($user[$bazarMapping['fields'][$yeswikiField]]), 0, 1) : '';
        }
        foreach ($bazarMapping['anonymize']['fields_to_keep'] as $yeswikiField){
            // for the fields to keep, copy all the content
            $fiche[$yeswikiField] = isset($user[$bazarMapping['fields'][$yeswikiField]]) ? $user[$bazarMapping['fields'][$yeswikiField]] : '';
        }
        // set a fixed value defined in the config field for 'bf_titre'
        $fiche['bf_titre'] = $bazarMapping['anonymize']['bf_titre_value'];
    } else {
        foreach ($bazarMapping['fields'] as $yeswikiField => $ssoField) {
            if (!isset($user[$ssoField])) {
                $fiche[$yeswikiField] = '';
            } else {
                $transformDone = false;
                foreach ($bazarMapping['fields_transformed'] as $fieldTransformed) {
                    if ($fieldTransformed['field'] == $yeswikiField) {
                        // transform the sso field according to the pattern and replacement formats defined in the config file
                        $fiche[$yeswikiField] = preg_replace($fieldTransformed['pattern'], $fieldTransformed['replacement'], $user[$fieldTransformed['sso_field']]);
                        $transformDone = true;
                    }
                }
                if (!$transformDone){
                        // if the yeswiki field has not been found in the fields_transformed config, copy the content
                        $fiche[$yeswikiField] = $user[$ssoField];
                }
            }
        }
        // if a special format is defined for 'bf_titre', replace the field names by their values in $fiche
        if (isset($bazarMapping['bf_titre_format'])){
            $bf_titre = $bazarMapping['bf_titre_format'];
            foreach (array_values($bazarMapping['fields']) as $ssoField)
                $bf_titre = str_replace("#[$ssoField]", $user[$ssoField], $bf_titre);
            $fiche['bf_titre'] = $bf_titre;
        }
    }

    // TODO old code to remove
    //            $key = explode('.', $key);
    //            if (!empty($val[1])) {
    //                $jsonval = json_decode($user[$key[0]], true);
    //                // hack for geoloc in bazar..
    //                if ($key[0] == 'field_lat_lon' && $key[1] == 'latlon') {
    //                    $jsonval[$key[1]] = isset($jsonval[$key[1]]) ? str_replace(',', '|', $jsonval[$key[1]]) : '';
    //                }
    //                $fiche[$val] = isset($jsonval[$key[1]]) ? (string)$jsonval[$key[1]] : '';
    //            } else {
    //                $fiche[$val] = isset($user[$key[0]]) ? (string)$user[$key[0]] : '';
    //            }

    return $fiche;
}