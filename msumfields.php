<?php

require_once 'msumfields.civix.php';

/**
 * Implements hook_civicrm_buildForm().
 */
function msumfields_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Sumfields_Form_SumFields') {
    $tpl = CRM_Core_Smarty::singleton();
    $fieldsets = $tpl->_tpl_vars['fieldsets'];

    // Get msumfields definitions, because we need the fieldset names as a target
    // for where to insert our option fields
    $custom = array();
    msumfields_civicrm_sumfields_definitions($custom);

    // Create a field for Financial Types on related contributions.
    $label = msumfields_ts('Financial Types');
    $form->add('select', 'msumfields_relatedcontrib_financial_type_ids', $label, sumfields_get_all_financial_types(), TRUE, array('multiple' => TRUE, 'class' => 'crm-select2 huge'));
    $fieldsets[$custom['optgroups']['relatedcontrib']['fieldset']]['msumfields_relatedcontrib_financial_type_ids'] = msumfields_ts('Financial types to be used when calculating Related Contribution summary fields.');

    // Create a field for Relationship Types on related contributions.
    $label = msumfields_ts('Relationship Types');
    $form->add('select', 'msumfields_relatedcontrib_relationship_type_ids', $label, _msumfields_get_all_relationship_types(), TRUE, array('multiple' => TRUE, 'class' => 'crm-select2 huge'));
    $fieldsets[$custom['optgroups']['relatedcontrib']['fieldset']]['msumfields_relatedcontrib_relationship_type_ids'] = msumfields_ts('Relationship types to be used when calculating Related Contribution summary fields.');

    // Set defaults.
    $form->setDefaults(array(
      'msumfields_relatedcontrib_financial_type_ids' => sumfields_get_setting('msumfields_relatedcontrib_financial_type_ids'),
      'msumfields_relatedcontrib_relationship_type_ids' => sumfields_get_setting('msumfields_relatedcontrib_relationship_type_ids'),
    ));

    $form->assign('fieldsets', $fieldsets);
  }
}

/**
 * Implements hook_civicrm_postProcess().
 */
function msumfields_civicrm_postProcess($formName, &$form) {
  if ($formName == 'CRM_Sumfields_Form_SumFields') {
    // Save option fields as submitted.
    sumfields_save_setting('msumfields_relatedcontrib_financial_type_ids', $form->_submitValues['msumfields_relatedcontrib_financial_type_ids']);
    sumfields_save_setting('msumfields_relatedcontrib_relationship_type_ids', $form->_submitValues['msumfields_relatedcontrib_relationship_type_ids']);

    // Define our own triggers, as needed.
    _msumfields_generate_data_based_on_current_data();
  }
}

/**
 * Implements hook_civicrm_sumfields_definitions().
 *
 * NOTE: Array properties in $custom named 'msumfields_*' will be used by
 * msumfields_civicrm_triggerInfo() to build the "real" trggers, and by
 * _msumfields_generate_data_based_on_current_data() to populate the "real"
 * values.
 */
function msumfields_civicrm_sumfields_definitions(&$custom) {
  // Adjust some labels in summary fields to be more explicit.
  $custom['fields']['contribution_total_this_year']['label'] = msumfields_ts('Total Contributions this Fiscal Year');
  $custom['fields']['contribution_total_last_year']['label'] = msumfields_ts('Total Contributions last Fiscal Year');
  $custom['fields']['contribution_total_year_before_last']['label'] = msumfields_ts('Total Contributions Fiscal Year Before Last');
  $custom['fields']['soft_total_this_year']['label'] = msumfields_ts('Total Soft Credits this Fiscal Year');

  $custom['fields']['contribution_total_this_calendar_year'] = array(
    'label' => msumfields_ts('Total Contributions this Calendar Year'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => '(
      SELECT COALESCE(SUM(total_amount),0)
      FROM civicrm_contribution t1
      WHERE
        YEAR(CAST(receive_date AS DATE)) = YEAR(CURDATE())
        AND t1.contact_id = NEW.contact_id
        AND t1.contribution_status_id = 1
        AND t1.financial_type_id IN (%financial_type_ids)
    )',
    'trigger_table' => 'civicrm_contribution',
    'optgroup' => 'fundraising',
  );

  $custom['fields']['contribution_total_last_calendar_year'] = array(
    'label' => msumfields_ts('Total Contributions last Calendar Year'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => '(
      SELECT COALESCE(SUM(total_amount),0)
      FROM civicrm_contribution t1
      WHERE YEAR(CAST(receive_date AS DATE)) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR))
        AND t1.contact_id = NEW.contact_id
        AND t1.contribution_status_id = 1
        AND t1.financial_type_id IN (%financial_type_ids)
    )',
    'trigger_table' => 'civicrm_contribution',
    'optgroup' => 'fundraising',
  );

  $custom['fields']['contribution_total_calendar_year_before_last'] = array(
    'label' => msumfields_ts('Total Contributions Calendar Year Before Last'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => '(
      SELECT COALESCE(SUM(total_amount),0)
      FROM civicrm_contribution t1
      WHERE YEAR(CAST(receive_date AS DATE)) = YEAR(DATE_SUB(CURDATE(), INTERVAL 2 YEAR))
        AND t1.contact_id = NEW.contact_id
        AND t1.contribution_status_id = 1
        AND t1.financial_type_id IN (%financial_type_ids)
    )',
    'trigger_table' => 'civicrm_contribution',
    'optgroup' => 'fundraising',
  );

  $custom['fields']['contribution_count_distinct_years'] = array(
    'label' => msumfields_ts('Number of Years of Contributions'),
    'data_type' => 'Integer',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => '(
      SELECT count(DISTINCT year(receive_date))
      FROM civicrm_contribution t1
      WHERE
        t1.contact_id = NEW.contact_id
        AND t1.contribution_status_id = 1
        AND t1.financial_type_id IN (%financial_type_ids)
    )',
    'trigger_table' => 'civicrm_contribution',
    'optgroup' => 'fundraising',
  );

  $custom['fields']['soft_total_this_calendar_year'] = array(
    'label' => msumfields_ts('Total Soft Credits this Calendar Year'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => '(
      SELECT COALESCE(SUM(amount),0)
      FROM civicrm_contribution_soft t1
      WHERE t1.contact_id = NEW.contact_id
      AND t1.contribution_id IN (
        SELECT id
        FROM civicrm_contribution
        WHERE contribution_status_id = 1
          AND financial_type_id IN (%financial_type_ids)
          AND YEAR(receive_date) = YEAR(CURDATE())
      )
    )',
    'trigger_table' => 'civicrm_contribution_soft',
    'optgroup' => 'soft',
  );

  $custom['fields']['soft_total_last_calendar_year'] = array(
    'label' => msumfields_ts('Total Soft Credits last Calendar Year'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => '(
      SELECT COALESCE(SUM(amount),0)
      FROM civicrm_contribution_soft t1
      WHERE t1.contact_id = NEW.contact_id
      AND t1.contribution_id IN (
        SELECT id
        FROM civicrm_contribution
        WHERE contribution_status_id = 1
          AND financial_type_id IN (%financial_type_ids)
          AND YEAR(receive_date) = (YEAR(CURDATE()) - 1)
      )
    )',
    'trigger_table' => 'civicrm_contribution_soft',
    'optgroup' => 'soft',
  );

  $custom['fields']['soft_total_last_year'] = array(
    'label' => msumfields_ts('Total Soft Credits last Fiscal Year'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => '(
      SELECT COALESCE(SUM(amount),0)
      FROM civicrm_contribution_soft t1
      WHERE t1.contact_id = NEW.contact_id
      AND t1.contribution_id IN (
        SELECT id
        FROM civicrm_contribution
        WHERE contribution_status_id = 1
          AND financial_type_id IN (%financial_type_ids)
          AND CAST(receive_date AS DATE) BETWEEN DATE_SUB("%current_fiscal_year_begin", INTERVAL 1 YEAR) AND DATE_SUB("%current_fiscal_year_end", INTERVAL 1 YEAR)
      )
    )',
    'trigger_table' => 'civicrm_contribution_soft',
    'optgroup' => 'soft',
  );

  $custom['fields']['hard_and_soft'] = array(
    'label' => msumfields_ts('Lifetime contributions + soft credits'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => '(
      SELECT COALESCE(SUM(cont1.total_amount), 0)
      FROM civicrm_contribution cont1
        LEFT JOIN civicrm_contribution_soft soft ON soft.contribution_id = cont1.id
      WHERE
        (cont1.contact_id = NEW.contact_id OR soft.contact_id = NEW.contact_id)
        AND cont1.contribution_status_id = 1
        AND cont1.financial_type_id IN (%financial_type_ids)
    )',
    'trigger_table' => 'civicrm_contribution',
    'optgroup' => 'fundraising', // could just add this to the existing "fundraising" optgroup
  );

  /* For the "Related Contributions" group of fields, we cannot make them work
   * as true sumfields fields, because of assumptions in sumfields
   * [https://github.com/progressivetech/net.ourpowerbase.sumfields/blob/master/sumfields.php#L476]:
   *  1. that every trigger table has a column named contact_id (which civicrm_relationship does not)
   *  2. that the contact_id column in the trigger table is the one for whom the custom field should be updated (which is not true for any realtionship-based sumfields).
   * So to make this work, we hijack and emulate select parts of sumfields logic:
   *  a. _msumfields_generate_data_based_on_current_data(), our own version of
   *    sumfields_generate_data_based_on_current_data()
   *  b. calling _msumfields_generate_data_based_on_current_data() via
   *    apiwrappers hook, so it always happens when the API gendata is called.
   *  c. calling _msumfields_generate_data_based_on_current_data() via
   *    postProcess hook, so it happens (as needed ) when the Sumfields form is
   *    submitted.
   * To make all this happen, we define special values in array properties named
   * 'msumfields_*', which are ignored by sumfields, but are specifically
   * handled by _msumfields_generate_data_based_on_current_data() and
   * msumfields_civicrm_triggerInfo().
   */

  $custom['fields']['relatedcontrib_this_fiscal_year'] = array(
    'label' => msumfields_ts('Related contact contributions this fiscal year'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' =>
    // NOTE: We want something as low-resource-usage as possible, since we'll
    // not be using this value at all. Array properties named 'msumfields_*'
    // will be used to define the "real" triggers. So just use 0 here.
    '0',
    'trigger_table' => 'civicrm_contribution',
    'msumfields_trigger_sql_base' => '
      (
      select contact_id_a as contact_id, coalesce(sum(total_amount),0) as total from
        (
          select
            contact_id_a, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contact_id as donor_contact_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_b
          UNION
          select
            contact_id_b, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contact_id as donor_contact_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_a
        ) t
        where
          t.relationship_type_id in (%msumfields_relatedcontrib_relationship_type_ids)
          and t.is_active
          and t.financial_type_id in (%msumfields_relatedcontrib_financial_type_ids)
          AND CAST(t.receive_date AS DATE) BETWEEN "%current_fiscal_year_begin" AND "%current_fiscal_year_end"
        group by contact_id_a
      )
    ',
    'msumfields_trigger_sql_base_alias' => 't',
    'msumfields_trigger_sql_entity_alias' => 'contact_id',
    'msumfields_trigger_sql_value_alias' => 'total',
    'msumfields_trigger_sql_limiter' => '
      INNER JOIN civicrm_relationship r
        ON (NEW.contact_id IN (r.contact_id_a, r.contact_id_b))
        AND if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a) = t.contact_id
    ',
    'msumfields_extra' => array(
      array(
        'trigger_table' => 'civicrm_relationship',
        'entity_column' => 'contact_id_a',
        'trigger_sql' => _msumfields_sql_rewrite('
          (
            SELECT
              coalesce(sum(cont1.total_amount), 0)
            FROM
              civicrm_relationship r
              INNER JOIN civicrm_contribution cont1
            WHERE
              r.is_active
              AND r.relationship_type_id in (%msumfields_relatedcontrib_relationship_type_ids)
              AND cont1.financial_type_id in (%msumfields_relatedcontrib_financial_type_ids)
              AND (
                (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_a)
                OR
                (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_a)
              )
              AND CAST(cont1.receive_date AS DATE) BETWEEN "%current_fiscal_year_begin" AND "%current_fiscal_year_end"
          )
        '),
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'entity_column' => 'contact_id_b',
        'trigger_sql' => _msumfields_sql_rewrite('
          (
            SELECT
              coalesce(sum(cont1.total_amount), 0)
            FROM
              civicrm_relationship r
              INNER JOIN civicrm_contribution cont1
            WHERE
              r.is_active
              AND r.relationship_type_id in (%msumfields_relatedcontrib_relationship_type_ids)
              AND cont1.financial_type_id in (%msumfields_relatedcontrib_financial_type_ids)
              AND (
                (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_b)
                OR
                (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_b)
              )
              AND CAST(cont1.receive_date AS DATE) BETWEEN "%current_fiscal_year_begin" AND "%current_fiscal_year_end"
            )
        '),
      ),
    ),
    'optgroup' => 'relatedcontrib', // could just add this to the existing "fundraising" optgroup
  );

  $custom['fields']['relatedcontrib_this_calendar_year'] = array(
    'label' => msumfields_ts('Related contact contributions this calendar year'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' =>
    // NOTE: We want something as low-resource-usage as possible, since we'll
    // not be using this value at all. Array properties named 'msumfields_*'
    // will be used to define the "real" triggers. So just use an empty string
    // here.
    '0',
    'trigger_table' => 'civicrm_contribution',
    'msumfields_trigger_sql_base' => '
      (
      select contact_id_a as contact_id, coalesce(sum(total_amount),0) as total from
        (
          select
            contact_id_a, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contact_id as donor_contact_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_b
          UNION
          select
            contact_id_b, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contact_id as donor_contact_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_a
        ) t
        where
          t.relationship_type_id in (%msumfields_relatedcontrib_relationship_type_ids)
          and t.is_active
          and t.financial_type_id in (%msumfields_relatedcontrib_financial_type_ids)
          AND YEAR(CAST(t.receive_date AS DATE)) = YEAR(CURDATE())
        group by contact_id_a
      )
    ',
    'msumfields_trigger_sql_base_alias' => 't',
    'msumfields_trigger_sql_entity_alias' => 'contact_id',
    'msumfields_trigger_sql_value_alias' => 'total',
    'msumfields_trigger_sql_limiter' => '
      INNER JOIN civicrm_relationship r
        ON (NEW.contact_id IN (r.contact_id_a, r.contact_id_b))
        AND if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a) = t.contact_id
    ',
    'msumfields_extra' => array(
      array(
        'trigger_table' => 'civicrm_relationship',
        'entity_column' => 'contact_id_a',
        'trigger_sql' => _msumfields_sql_rewrite('
          (
            SELECT
              coalesce(sum(cont1.total_amount), 0)
            FROM
              civicrm_relationship r
              INNER JOIN civicrm_contribution cont1
            WHERE
              r.is_active
              AND r.relationship_type_id in (%msumfields_relatedcontrib_relationship_type_ids)
              AND cont1.financial_type_id in (%msumfields_relatedcontrib_financial_type_ids)
              AND (
                (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_a)
                OR
                (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_a)
              )
              AND YEAR(CAST(cont1.receive_date AS DATE)) = YEAR(CURDATE())
          )
        '),
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'entity_column' => 'contact_id_b',
        'trigger_sql' => _msumfields_sql_rewrite('
          (
            SELECT
              coalesce(sum(cont1.total_amount), 0)
            FROM
              civicrm_relationship r
              INNER JOIN civicrm_contribution cont1
            WHERE
              r.is_active
              AND r.relationship_type_id in (%msumfields_relatedcontrib_relationship_type_ids)
              AND cont1.financial_type_id in (%msumfields_relatedcontrib_financial_type_ids)
              AND (
                (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_b)
                OR
                (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_b)
              )
              AND YEAR(CAST(cont1.receive_date AS DATE)) = YEAR(CURDATE())
            )
        '),
      ),
    ),
    'optgroup' => 'relatedcontrib', // could just add this to the existing "fundraising" optgroup
  );

  $custom['fields']['relatedcontrib_last_calendar_year'] = array(
    'label' => msumfields_ts('Related contact contributions last calendar year'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' =>
    // NOTE: We want something as low-resource-usage as possible, since we'll
    // not be using this value at all. Array properties named 'msumfields_*'
    // will be used to define the "real" triggers. So just use an empty string
    // here.
    '0',
    'trigger_table' => 'civicrm_contribution',
    'msumfields_trigger_sql_base' => '
      (
      select contact_id_a as contact_id, coalesce(sum(total_amount), 0) as total from
        (
          select
            contact_id_a, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contact_id as donor_contact_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_b
          UNION
          select
            contact_id_b, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contact_id as donor_contact_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_a
        ) t
        where
          t.relationship_type_id in (%msumfields_relatedcontrib_relationship_type_ids)
          and t.is_active
          and t.financial_type_id in (%msumfields_relatedcontrib_financial_type_ids)
          AND YEAR(CAST(t.receive_date AS DATE)) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR))
        group by contact_id_a
      )
    ',
    'msumfields_trigger_sql_base_alias' => 't',
    'msumfields_trigger_sql_entity_alias' => 'contact_id',
    'msumfields_trigger_sql_value_alias' => 'total',
    'msumfields_trigger_sql_limiter' => '
      INNER JOIN civicrm_relationship r
        ON (NEW.contact_id IN (r.contact_id_a, r.contact_id_b))
        AND if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a) = t.contact_id
    ',
    'msumfields_extra' => array(
      array(
        'trigger_table' => 'civicrm_relationship',
        'entity_column' => 'contact_id_a',
        'trigger_sql' => _msumfields_sql_rewrite('
          (
            SELECT
              coalesce(sum(cont1.total_amount), 0)
            FROM
              civicrm_relationship r
              INNER JOIN civicrm_contribution cont1
            WHERE
              r.is_active
              AND r.relationship_type_id in (%msumfields_relatedcontrib_relationship_type_ids)
              AND cont1.financial_type_id in (%msumfields_relatedcontrib_financial_type_ids)
              AND (
                (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_a)
                OR
                (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_a)
              )
              AND YEAR(CAST(receive_date AS DATE)) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR))
          )
        '),
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'entity_column' => 'contact_id_b',
        'trigger_sql' => _msumfields_sql_rewrite('
          (
            SELECT
              coalesce(sum(cont1.total_amount), 0)
            FROM
              civicrm_relationship r
              INNER JOIN civicrm_contribution cont1
            WHERE
              r.is_active
              AND r.relationship_type_id in (%msumfields_relatedcontrib_relationship_type_ids)
              AND cont1.financial_type_id in (%msumfields_relatedcontrib_financial_type_ids)
              AND (
                (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_b)
                OR
                (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_b)
              )
              AND YEAR(CAST(receive_date AS DATE)) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR))
            )
        '),
      ),
    ),
    'optgroup' => 'relatedcontrib', // could just add this to the existing "fundraising" optgroup
  );

  $custom['fields']['relatedcontrib_alltime'] = array(
    'label' => msumfields_ts('Related contact contributions all time'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' =>
    // NOTE: We want something as low-resource-usage as possible, since we'll
    // not be using this value at all. Array properties named 'msumfields_*'
    // will be used to define the "real" triggers. So just use an empty string
    // here.
    '0',
    'trigger_table' => 'civicrm_contribution',
    'msumfields_trigger_sql_base' => '
      (
      select contact_id_a as contact_id, coalesce(sum(total_amount),0) as total from
        (
          select
            contact_id_a, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contact_id as donor_contact_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_b
          UNION
          select
            contact_id_b, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contact_id as donor_contact_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_a
        ) t
        where
          t.relationship_type_id in (%msumfields_relatedcontrib_relationship_type_ids)
          and t.is_active
          and t.financial_type_id in (%msumfields_relatedcontrib_financial_type_ids)
        group by contact_id_a
      )
    ',
    'msumfields_trigger_sql_base_alias' => 't',
    'msumfields_trigger_sql_entity_alias' => 'contact_id',
    'msumfields_trigger_sql_value_alias' => 'total',
    'msumfields_trigger_sql_limiter' => '
      INNER JOIN civicrm_relationship r
        ON (NEW.contact_id IN (r.contact_id_a, r.contact_id_b))
        AND if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a) = t.contact_id
    ',
    'msumfields_extra' => array(
      array(
        'trigger_table' => 'civicrm_relationship',
        'entity_column' => 'contact_id_a',
        'trigger_sql' => _msumfields_sql_rewrite('
          (
            SELECT
              coalesce(sum(cont1.total_amount), 0)
            FROM
              civicrm_relationship r
              INNER JOIN civicrm_contribution cont1
            WHERE
              r.is_active
              AND r.relationship_type_id in (%msumfields_relatedcontrib_relationship_type_ids)
              AND cont1.financial_type_id in (%msumfields_relatedcontrib_financial_type_ids)
              AND (
                (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_a)
                OR
                (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_a)
              )
          )
        '),
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'entity_column' => 'contact_id_b',
        'trigger_sql' => _msumfields_sql_rewrite('
          (
            SELECT
              coalesce(sum(cont1.total_amount), 0)
            FROM
              civicrm_relationship r
              INNER JOIN civicrm_contribution cont1
            WHERE
              r.is_active
              AND r.relationship_type_id in (%msumfields_relatedcontrib_relationship_type_ids)
              AND cont1.financial_type_id in (%msumfields_relatedcontrib_financial_type_ids)
              AND (
                (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_b)
                OR
                (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_b)
              )
            )
        '),
      ),
    ),
    'optgroup' => 'relatedcontrib', // could just add this to the existing "fundraising" optgroup
  );

  $custom['fields']['relatedcontrib_plusme_this_fiscal_year'] = array(
    'label' => msumfields_ts('Combined contact & related contact contributions this fiscal year'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' =>
    // NOTE: We want something as low-resource-usage as possible, since we'll
    // not be using this value at all. Array properties named 'msumfields_*'
    // will be used to define the "real" triggers. So just use an empty string
    // here.
    '0',
    'trigger_table' => 'civicrm_contribution',
    'msumfields_trigger_sql_base' => '
      (
        select contact_id_a as contact_id, coalesce(sum(total_amount)) as total from
          (
            select
              contact_id_a, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount
            from
              civicrm_relationship r
              inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_b
            UNION
            select
              contact_id_b, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount
            from
              civicrm_relationship r
              inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_a
            UNION
            select ctrb.contact_id, 0 as relationship_type_id, 1 as is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount
              from civicrm_contribution ctrb
          ) t
          where
            t.relationship_type_id in (%msumfields_relatedcontrib_relationship_type_ids, 0)
            and t.is_active
            and t.financial_type_id in (%msumfields_relatedcontrib_financial_type_ids)
            AND CAST(t.receive_date AS DATE) BETWEEN "%current_fiscal_year_begin" AND "%current_fiscal_year_end"
          group by contact_id_a
      )
    ',
    'msumfields_trigger_sql_base_alias' => 't',
    'msumfields_trigger_sql_entity_alias' => 'contact_id',
    'msumfields_trigger_sql_value_alias' => 'total',
    'msumfields_trigger_sql_limiter' => '
      WHERE
        t.contact_id = NEW.contact_id OR t.contact_id in (
          SELECT
            if(contact_id_a = NEW.contact_id, contact_id_b, contact_id_a)
          FROM
            civicrm_relationship
          WHERE
            NEW.contact_id in (contact_id_a, contact_id_b)
            AND is_active
            AND relationship_type_id in (%msumfields_relatedcontrib_relationship_type_ids)
        )
    ',
    'msumfields_extra' => array(
      array(
        'trigger_table' => 'civicrm_relationship',
        'entity_column' => 'contact_id_a',
        'trigger_sql' => _msumfields_sql_rewrite('
          (
            SELECT
              coalesce(sum(total_amount), 0)
            FROM
            (
              select cont1.total_amount
              from
                civicrm_relationship r
                INNER JOIN civicrm_contribution cont1
              WHERE
                r.is_active
                AND r.relationship_type_id in (%msumfields_relatedcontrib_relationship_type_ids)
                AND cont1.financial_type_id in (%msumfields_relatedcontrib_financial_type_ids)
                AND (
                  (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_a)
                  OR
                  (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_a)
                )
                AND YEAR(CAST(cont1.receive_date AS DATE)) = YEAR(CURDATE())
              UNION
              SELECT
                total_amount
              FROM
                civicrm_contribution
              WHERE
                contact_id = NEW.contact_id_a
                AND financial_type_id in (%msumfields_relatedcontrib_financial_type_ids)
                AND YEAR(CAST(receive_date AS DATE)) = YEAR(CURDATE())
            ) t
          )
        '),
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'entity_column' => 'contact_id_b',
        'trigger_sql' => _msumfields_sql_rewrite('
          (
            SELECT
              coalesce(sum(total_amount), 0)
            FROM
            (
              select cont1.total_amount
              from
                civicrm_relationship r
                INNER JOIN civicrm_contribution cont1
              WHERE
                r.is_active
                AND r.relationship_type_id in (%msumfields_relatedcontrib_relationship_type_ids)
                AND cont1.financial_type_id in (%msumfields_relatedcontrib_financial_type_ids)
                AND (
                  (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_b)
                  OR
                  (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_b)
                )
                AND YEAR(CAST(cont1.receive_date AS DATE)) = YEAR(CURDATE())
              UNION
              SELECT
                total_amount
              FROM
                civicrm_contribution
              WHERE
                contact_id = NEW.contact_id_b
                AND financial_type_id in (%msumfields_relatedcontrib_financial_type_ids)
                AND YEAR(CAST(receive_date AS DATE)) = YEAR(CURDATE())
            ) t
          )
        '),
      ),
    ),
    'optgroup' => 'relatedcontrib', // could just add this to the existing "fundraising" optgroup
  );

  // Define a new optgroup fieldset, to contain our Related Contributions fields
  // and options.
  $custom['optgroups']['relatedcontrib'] = array(
    'title' => 'Related Contribution Fields',
    'fieldset' => 'Related Contributions',
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

/**
 * Wrapper for ts() to save me some typing.
 * @param string $text The text to translate.
 * @param array $params Any replacement parameters.
 * @return string The translated string.
 */
function msumfields_ts($text, $params = array()) {
  if (!array_key_exists('domain', $params)) {
    $params['domain'] = 'com.joineryhq.msumfields';
  }
  return ts($text, $params);
}

function msumfields_civicrm_triggerInfo(&$info, $tableName) {
  if (!CRM_Msumfields_Upgrader::checkDependency('net.ourpowerbase.sumfields')) {
    // If sumfields is not enabled, don't define any of our own triggers, since
    // they'll then rely on (non-existent) custom fields.
    return;
  }

  // If any enabled fields have 'msumfields_extra' defined, formulate
  // a trigger for them and add to $info.
  // Our triggers all use custom fields. CiviCRM, when generating
  // custom fields, sometimes gives them different names (appending
  // the id in most cases) to avoid name collisions.
  //
  // So, we have to retrieve the actual name of each field that is in
  // use.
  $table_name = _sumfields_get_custom_table_name();
  $custom_fields = _sumfields_get_custom_field_parameters();

  // Load the field and group definitions because we need the trigger
  // clause that is stored here.
  // Only get msumfields definitions.
  $custom = array();
  msumfields_civicrm_sumfields_definitions($custom);

  // We create a trigger sql statement for each table that should
  // have a trigger
  $tables = array();
  $generic_sql = "INSERT INTO `$table_name` SET ";
  $sql_field_parts = array();

  $active_fields = sumfields_get_setting('active_fields', array());

  $session = CRM_Core_Session::singleton();
  $triggers = array();
  // Iterate over all our fields, and build out a sql parts array
  foreach ($custom_fields as $base_column_name => $params) {
    if (!in_array($base_column_name, $active_fields)) {
      continue;
    }

    // Set up variables to add triggers for msumfields_extra.
    if (!empty($custom['fields'][$base_column_name]['msumfields_extra'])) {
      foreach ($custom['fields'][$base_column_name]['msumfields_extra'] as $extra) {
        $table = $extra['trigger_table'];
        if (empty($triggers[$table])) {
          $triggers[$table] = '';
        }

        if (!is_null($tableName) && $tableName != $table) {
          // if triggerInfo is called with a particular table name, we should
          // only respond if we are contributing triggers to that table.
          continue;
        }
        $trigger = sumfields_sql_rewrite($extra['trigger_sql']);
        // If we fail to properly rewrite the sql, don't set the trigger
        // to avoid sql exceptions.
        if (FALSE === $trigger) {
          $msg = sprintf(ts("Failed to rewrite sql for %s field."), $base_column_name);
          $session->setStatus($msg);
          continue;
        }
        $sql_field_parts[$table] = "`{$params['column_name']}` = {$trigger}";

        $parts[$table] = array($sql_field_parts[$table]);
        $parts[$table][] = 'entity_id = NEW.' . $extra['entity_column'];

        $extra_sql = implode(',', $parts[$table]);
        $triggers[$table] .= $generic_sql . $extra_sql . ' ON DUPLICATE KEY UPDATE ' . $extra_sql . ";\n";
      }
    }

    // Set up variables to add triggers for msumfields_trigger_sql_base.
    if (
      !empty($custom['fields'][$base_column_name]['msumfields_trigger_sql_base'])
      && !empty($custom['fields'][$base_column_name]['msumfields_trigger_sql_base_alias'])
      && !empty($custom['fields'][$base_column_name]['msumfields_trigger_sql_limiter'])
      && !empty($custom['fields'][$base_column_name]['msumfields_trigger_sql_entity_alias'])
      && !empty($custom['fields'][$base_column_name]['msumfields_trigger_sql_value_alias'])
    ) {
      $table = $custom['fields'][$base_column_name]['trigger_table'];

      if (empty($tableName) || $tableName == $table) {
        // if triggerInfo is called with a particular table name, we should
        // only respond if we are contributing triggers to that table.
        if (empty($triggers[$table])) {
          $triggers[$table] = '';
        }

        $baseAlias = $custom['fields'][$base_column_name]['msumfields_trigger_sql_base_alias'];
        $trigger = "
          INSERT INTO `$table_name` (entity_id, `{$params['column_name']}`)
          SELECT
            {$baseAlias}.{$custom['fields'][$base_column_name]['msumfields_trigger_sql_entity_alias']},
            {$baseAlias}.{$custom['fields'][$base_column_name]['msumfields_trigger_sql_value_alias']}
          FROM
            ({$custom['fields'][$base_column_name]['msumfields_trigger_sql_base']}) {$baseAlias}
          {$custom['fields'][$base_column_name]['msumfields_trigger_sql_limiter']}
          ON DUPLICATE KEY UPDATE `{$params['column_name']}` = {$baseAlias}.{$custom['fields'][$base_column_name]['msumfields_trigger_sql_value_alias']}
        ";

        $trigger = sumfields_sql_rewrite(_msumfields_sql_rewrite($trigger));

        // If we fail to properly rewrite the sql, don't set the trigger
        // to avoid sql exceptions.
        if (FALSE === $trigger) {
          $msg = sprintf(ts("Failed to rewrite sql for %s field."), $base_column_name);
          $session->setStatus($msg);
        }
        else {
          $triggers[$table] .= "{$trigger};\n";
        }
      }
    }
  }

  foreach ($triggers as $table => $sql) {
    // We want to fire this trigger on insert, update and delete.
    $info[] = array(
      'table' => $table,
      'when' => 'AFTER',
      'event' => 'INSERT',
      'sql' => $sql,
    );
    $info[] = array(
      'table' => $table,
      'when' => 'AFTER',
      'event' => 'UPDATE',
      'sql' => $sql,
    );
    // For delete, we reference OLD.field instead of NEW.field
    $sql = str_replace('NEW.', 'OLD.', $sql);
    $info[] = array(
      'table' => $table,
      'when' => 'AFTER',
      'event' => 'DELETE',
      'sql' => $sql,
    );
  }
}

/**
 * Get all available relationship types; a simple wrapper around the CiviCRM API.
 *
 * @return array Suitable for a select field.
 */
function _msumfields_get_all_relationship_types() {
  $relationshipTypes = array();
  $result = civicrm_api3('relationshipType', 'get', array(
    'options' => array(
      'limit' => 0,
    ),
  ));
  foreach ($result['values'] as $value) {
    if (empty($value['name_a_b'])) {
      continue;
    }
    $relationshipTypes[$value['id']] = "{$value['label_a_b']}/{$value['label_b_a']}";
  }
  return $relationshipTypes;
}

/**
 * Replace msumfields %variables with the appropriate values. NOTE: this function
 * does NOT call msumfields_sql_rewrite().
 *
 * @return string Modified $sql.
 */
function _msumfields_sql_rewrite($sql) {
  // Note: most of these token replacements fill in a sql IN statement,
  // e.g. field_name IN (%token). That means if the token is empty, we
  // get a SQL error. So... for each of these, if the token is empty,
  // we fill it with all possible values at the moment. If a new option
  // is added, summary fields will have to be re-configured.
  $ids = sumfields_get_setting('msumfields_relatedcontrib_relationship_type_ids', array());
  if (count($ids) == 0) {
    $ids = array_keys(_msumfields_get_all_relationship_types());
  }
  $str_ids = implode(',', $ids);
  $sql = str_replace('%msumfields_relatedcontrib_relationship_type_ids', $str_ids, $sql);

  $ids = sumfields_get_setting('msumfields_relatedcontrib_financial_type_ids', array());
  if (count($ids) == 0) {
    $ids = array_keys(sumfields_get_all_financial_types());
  }
  $str_ids = implode(',', $ids);
  $sql = str_replace('%msumfields_relatedcontrib_financial_type_ids', $str_ids, $sql);

  return $sql;
}

/**
 * Define our own triggers, as needed (some msumfields, such as the "Related
 * Contributions" group, aren't fully supported by sumfields, so we do the extra
 * work here.
 *
 * Copied and modified from sumfields_generate_data_based_on_current_data().
 *
 * Generate calculated fields for all contacts.
 * This function is designed to be run once when
 * the extension is installed or initialized.
 *
 * @param CRM_Core_Session $session
 * @return bool
 *   TRUE if successful, FALSE otherwise
 */
function _msumfields_generate_data_based_on_current_data($session = NULL) {
  // Get the actual table name for summary fields.
  $table_name = _sumfields_get_custom_table_name();

  // These are the summary field definitions as they have been instantiated
  // on this site (with actual column names, etc.)
  $custom_fields = _sumfields_get_custom_field_parameters();

  if (is_null($session)) {
    $session = CRM_Core_Session::singleton();
  }
  if (empty($table_name)) {
    $session::setStatus(ts("Your configuration may be corrupted. Please disable and renable this extension."), ts('Error'), 'error');
    return FALSE;
  }

  // Load the field and group definitions because we need the msumfields_trigger_sql_*
  // properties that are stored here.
  // Only get msumfields definitions.
  $custom = array();
  msumfields_civicrm_sumfields_definitions($custom);

  $active_fields = sumfields_get_setting('active_fields', array());

  // Variables used for building the temp tables and temp insert statement.
  $temp_sql = array();

  foreach ($custom_fields as $base_column_name => $params) {
    // For this to work, we need several specific configuraton bits, so just
    // skip to the next custom_field if they're not all defined.
    // NOTE: msumfields makes fewer assumptions about its trigger configurations
    // than sumfields, and so is less efficient; specifically, we don't assume
    // that every field amounts to one value for each entity. E.g., In the case
    // of Related Contributions, a change in one contribution can result in new
    // values for several related contacts. Thus, treating the trigger sql as
    // a sub-select column (which amounts to one row per entity) is insufficient.
    // Therefore, we lose the efficiency of using a single query to determine
    // all custom field values, and must calculate each of ours individually.
    if (
      !in_array($base_column_name, $active_fields)
      || empty($custom['fields'][$base_column_name]['msumfields_trigger_sql_base'])
      || empty($custom['fields'][$base_column_name]['msumfields_trigger_sql_entity_alias'])
      || empty($custom['fields'][$base_column_name]['msumfields_trigger_sql_value_alias'])
    ) {
      continue;
    }

    // Define shorthand variables for relevant values.
    $table = $custom['fields'][$base_column_name]['trigger_table'];
    $triggerBase = $custom['fields'][$base_column_name]['msumfields_trigger_sql_base'];

    $updateQuery = "
      INSERT INTO `$table_name` (entity_id, `{$params['column_name']}`)
      SELECT
        {$custom['fields'][$base_column_name]['msumfields_trigger_sql_entity_alias']},
        {$custom['fields'][$base_column_name]['msumfields_trigger_sql_value_alias']}
      FROM
        ({$custom['fields'][$base_column_name]['msumfields_trigger_sql_base']}) t
      ON DUPLICATE KEY UPDATE `{$params['column_name']}` = {$custom['fields'][$base_column_name]['msumfields_trigger_sql_value_alias']}
    ";

    $updateQuery = sumfields_sql_rewrite(_msumfields_sql_rewrite($updateQuery));

    if (FALSE === $updateQuery) {
      $msg = sprintf(ts("Failed to rewrite sql for %s field."), $base_column_name);
      $session->setStatus($msg);
      continue;
    }
    CRM_Core_DAO::executeQuery($updateQuery);
  }

  return TRUE;
}
