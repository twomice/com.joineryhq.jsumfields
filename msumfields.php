<?php

require_once 'msumfields.civix.php';

/**
 * Implements hook_civicrm_sumfields_definitions()
 */
function msumfields_civicrm_sumfields_definitions(&$custom) {
  dsm($custom, 'custom');
  dsm(var_export($custom['fields']['contribution_total_this_year'],1),'contribution_total_this_year');
  
  $custom['fields']['contribution_total_this_calendar_year'] = array (
    'label' => 'Total Contributions this Calendar Year',
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => '(SELECT COALESCE(SUM(total_amount),0)
        FROM civicrm_contribution t1 WHERE YEAR(CAST(receive_date AS DATE)) = YEAR(CURDATE())
        AND t1.contact_id = NEW.contact_id AND
        t1.contribution_status_id = 1 AND t1.financial_type_id IN (%financial_type_ids))',
    'trigger_table' => 'civicrm_contribution',
    'optgroup' => 'fundraising',
  );
  
  $custom['fields']['hard_and_soft'] = array(
    'label' => 'All contributions + soft credits',
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => '(
      SELECT COALESCE(SUM(cont1.total_amount), 0)
      FROM civicrm_contribution cont1
      LEFT JOIN civicrm_contribution_soft soft
        ON soft.contribution_id = cont1.id
      WHERE (cont1.contact_id = NEW.contact_id OR soft.contact_id = NEW.contact_id)
        AND cont1.contribution_status_id = 1 AND cont1.financial_type_id IN (%financial_type_ids)
      )',
    'trigger_table' => 'civicrm_contribution',
    'optgroup' => 'mycustom', // could just add this to the existing "fundraising" optgroup
  );
  // If we don't want to add our fields to the existing optgroups or fieldsets on the admin form, we can make new ones
  $custom['optgroups']['mycustom'] = array(
    'title' => 'My group of checkboxes',
    'fieldset' => 'Custom summary fields', // Could add this to an existing fieldset by naming it here
    'component' => 'CiviContribute',
  );
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function msumfields_civicrm_config(&$config) {
  _msumfields_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function msumfields_civicrm_xmlMenu(&$files) {
  _msumfields_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function msumfields_civicrm_install() {
  _msumfields_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function msumfields_civicrm_postInstall() {
  _msumfields_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function msumfields_civicrm_uninstall() {
  _msumfields_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function msumfields_civicrm_enable() {
  _msumfields_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function msumfields_civicrm_disable() {
  _msumfields_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function msumfields_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _msumfields_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function msumfields_civicrm_managed(&$entities) {
  _msumfields_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function msumfields_civicrm_caseTypes(&$caseTypes) {
  _msumfields_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function msumfields_civicrm_angularModules(&$angularModules) {
  _msumfields_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function msumfields_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _msumfields_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function msumfields_civicrm_preProcess($formName, &$form) {

} // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
function msumfields_civicrm_navigationMenu(&$menu) {
  _msumfields_civix_insert_navigation_menu($menu, NULL, array(
    'label' => ts('The Page', array('domain' => 'com.joineryhq.msumfields')),
    'name' => 'the_page',
    'url' => 'civicrm/the-page',
    'permission' => 'access CiviReport,access CiviContribute',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _msumfields_civix_navigationMenu($menu);
} // */
