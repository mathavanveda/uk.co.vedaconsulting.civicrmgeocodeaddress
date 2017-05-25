<?php

require_once 'civicrmgeocodeaddress.civix.php';

define( 'THROTTLE_REQUESTS', 0 );

// Geocoding providers
// FIXME: Move this list to option values
$GLOBALS["geocoding_providers"] = array(
  'civipostcode' => 'CiviPostcode',
);

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function civicrmgeocodeaddress_civicrm_config(&$config) {
  _civicrmgeocodeaddress_civix_civicrm_config($config);		
	//$config->geocodeMethod = 'CRM_Utils_Geocode_Civipostcode';
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function civicrmgeocodeaddress_civicrm_xmlMenu(&$files) {
  _civicrmgeocodeaddress_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function civicrmgeocodeaddress_civicrm_install() {
  _civicrmgeocodeaddress_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function civicrmgeocodeaddress_civicrm_uninstall() {
  _civicrmgeocodeaddress_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function civicrmgeocodeaddress_civicrm_enable() {
  _civicrmgeocodeaddress_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function civicrmgeocodeaddress_civicrm_disable() {
  _civicrmgeocodeaddress_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function civicrmgeocodeaddress_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _civicrmgeocodeaddress_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function civicrmgeocodeaddress_civicrm_managed(&$entities) {
  _civicrmgeocodeaddress_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_caseTypes
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function civicrmgeocodeaddress_civicrm_caseTypes(&$caseTypes) {
  _civicrmgeocodeaddress_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implementation of hook_civicrm_alterSettingsFolders
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function civicrmgeocodeaddress_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _civicrmgeocodeaddress_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Add navigation for Geocoding Settings under "Administer" menu
 *
 * @param $params associated array of navigation menus
 */
function civicrmgeocodeaddress_civicrm_navigationMenu( &$params ) {
  // get the id of Administer Menu
  $administerMenuId = CRM_Core_DAO::getFieldValue('CRM_Core_BAO_Navigation', 'Administer', 'id', 'name');

  // skip adding menu if there is no administer menu
  if ($administerMenuId) {
    // get the maximum key under adminster menu
    $maxKey = max( array_keys($params[$administerMenuId]['child']));
    $params[$administerMenuId]['child'][$maxKey+1] =  array (
      'attributes' => array (
        'label'      => 'Geocoding Settings',
        'name'       => 'Geocoding Settings',
        'url'        => 'civicrm/civicrmgeocodeaddress/settings?reset=1',
        'permission' => 'administer CiviCRM',
        'operator'   => NULL,
        'separator'  => FALSE,
        'parentID'   => $administerMenuId,
        'navID'      => $maxKey+1,
        'active'     => 1
      )
    );
  }
}

/**
 * Implementation of hook_civicrm_post
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_post
 * To Unset the old address result which sitting on geocode result table. 
 * 
 * Geocode update works fine when change to new post code.. But its not picking up if we put back the old post code. because API skips those record since those already sitting on result table. 
 */
function civicrmgeocodeaddress_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  if ($op == 'edit' && $objectName == 'Address') {
    $newPostalCode = $objectRef->postal_code;

    //check new postal code already exist in geocode result table. if it is, then remove the old record and let API to Update geocode location for new postal address by schedule job.
    $sqlParams = array(
      1 => array( $objectId, 'Integer' ),
      2 => array( strtoupper($newPostalCode), 'String' ),
    );
    $sql = "SELECT id FROM civicrm_address_geocoding_result WHERE address_id = %1 AND UPPER(postal_code) = %2";

    //Some cases may no changes in Postal code but geocode is not matched since it might old record need to do data fix?. then reset it. Let API to pick up and update the geocode.
    if ($objectRef->geo_code_1) {
      $sql .= " AND geocode_1 != %3";
      $sqlParams[3] = array( $objectRef->geo_code_1, 'String' );
    }    
    if ($objectRef->geo_code_2) {
      $sql .= " AND geocode_2 != %4";
      $sqlParams[4] = array( $objectRef->geo_code_2, 'String' );
    }

    $newPostalCodeExist = CRM_Core_DAO::singleValueQuery($sql, $sqlParams);
    //Unset the existing postal record from geocode result table
    if ($newPostalCodeExist) {
      $sql = "DELETE FROM civicrm_address_geocoding_result WHERE id = %1";
      CRM_Core_DAO::executeQuery($sql, array(1=>array($newPostalCodeExist , 'Integer')));
    }
  }
}
