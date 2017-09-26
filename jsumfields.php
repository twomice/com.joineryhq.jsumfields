<?php

require_once 'jsumfields.civix.php';

/**
 * Implements hook_civicrm_apiWrappers().
 */
function jsumfields_civicrm_apiWrappers(&$wrappers, $apiRequest) {
  if (strtolower($apiRequest['entity']) == 'sumfields' && strtolower($apiRequest['action']) == 'gendata') {
    $wrappers[] = new CRM_Jsumfields_APIWrapperSumfieldsGendata();
  }
}

/**
 * Implements hook_civicrm_buildForm().
 */
function jsumfields_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Sumfields_Form_SumFields') {
    $tpl = CRM_Core_Smarty::singleton();
    $fieldsets = $tpl->_tpl_vars['fieldsets'];

    // Get jsumfields definitions, because we need the fieldset names as a target
    // for where to insert our option fields
    $custom = array();
    jsumfields_civicrm_sumfields_definitions($custom);

    // Create a field for Financial Types on related contributions.
    $label = jsumfields_ts('Financial Types');
    $form->add('select', 'jsumfields_relatedcontrib_financial_type_ids', $label, sumfields_get_all_financial_types(), FALSE, array('multiple' => TRUE, 'class' => 'crm-select2 huge'));
    $fieldsets[$custom['optgroups']['relatedcontrib']['fieldset']]['jsumfields_relatedcontrib_financial_type_ids'] = jsumfields_ts('Financial types to be used when calculating Related Contribution summary fields.');

    // Create a field for Relationship Types on related contributions.
    $label = jsumfields_ts('Relationship Types');
    $form->add('select', 'jsumfields_relatedcontrib_relationship_type_ids', $label, _jsumfields_get_all_relationship_types(), FALSE, array('multiple' => TRUE, 'class' => 'crm-select2 huge'));
    $fieldsets[$custom['optgroups']['relatedcontrib']['fieldset']]['jsumfields_relatedcontrib_relationship_type_ids'] = jsumfields_ts('Relationship types to be used when calculating Related Contribution summary fields.');

    // Create a field for Grant Status on grant fields.
    $label = jsumfields_ts('Grant Statuses');
    $form->add('select', 'jsumfields_grant_status_ids', $label, _jsumfields_get_all_grant_statuses(), FALSE, array('multiple' => TRUE, 'class' => 'crm-select2 huge'));
    $fieldsets[$custom['optgroups']['civigrant']['fieldset']]['jsumfields_grant_status_ids'] = jsumfields_ts('Grant statuses to be used when calculating Grant fields.');

    // Create a field for Grant Type on grant fields.
    $label = jsumfields_ts('Grant Types');
    $form->add('select', 'jsumfields_grant_type_ids', $label, _jsumfields_get_all_grant_types(), FALSE, array('multiple' => TRUE, 'class' => 'crm-select2 huge'));
    $fieldsets[$custom['optgroups']['civigrant']['fieldset']]['jsumfields_grant_type_ids'] = jsumfields_ts('Grant types to be used when calculating Grant fields.');

    // Set defaults.
    $form->setDefaults(array(
      'jsumfields_relatedcontrib_financial_type_ids' => sumfields_get_setting('jsumfields_relatedcontrib_financial_type_ids'),
      'jsumfields_relatedcontrib_relationship_type_ids' => sumfields_get_setting('jsumfields_relatedcontrib_relationship_type_ids'),
      'jsumfields_grant_status_ids' => sumfields_get_setting('jsumfields_grant_status_ids'),
      'jsumfields_grant_type_ids' => sumfields_get_setting('jsumfields_grant_type_ids'),
    ));

    $form->assign('fieldsets', $fieldsets);
  }
}

/**
 * Implements hook_civicrm_postProcess().
 */
function jsumfields_civicrm_postProcess($formName, &$form) {
  if ($formName == 'CRM_Sumfields_Form_SumFields') {
    // Save option fields as submitted.
    sumfields_save_setting('jsumfields_relatedcontrib_financial_type_ids', CRM_Utils_Array::value('jsumfields_relatedcontrib_financial_type_ids', $form->_submitValues));
    sumfields_save_setting('jsumfields_relatedcontrib_relationship_type_ids', CRM_Utils_Array::value('jsumfields_relatedcontrib_relationship_type_ids', $form->_submitValues));
    sumfields_save_setting('jsumfields_grant_status_ids', CRM_Utils_Array::value('jsumfields_grant_status_ids', $form->_submitValues));
    sumfields_save_setting('jsumfields_grant_type_ids', CRM_Utils_Array::value('jsumfields_grant_type_ids', $form->_submitValues));

    if ($form->_submitValues['when_to_apply_change'] == 'on_submit') {
      // Update our own trigger data, as needed.
      _jsumfields_generate_data_based_on_current_data();
    }
  }
}

/**
 * Implements hook_civicrm_sumfields_definitions().
 *
 * NOTE: Array properties in $custom named 'jsumfields_*' will be used by
 * jsumfields_civicrm_triggerInfo() to build triggers, and by
 * _jsumfields_generate_data_based_on_current_data() to populate field values.
 *
 * See DEVNOTES.md for supported 'jsumfields_*' properties.
 */
function jsumfields_civicrm_sumfields_definitions(&$custom) {
  // Adjust some labels in summary fields to be more explicit.
  $custom['fields']['contribution_total_this_year']['label'] = jsumfields_ts('Total Contributions this Fiscal Year');
  $custom['fields']['contribution_total_last_year']['label'] = jsumfields_ts('Total Contributions last Fiscal Year');
  $custom['fields']['contribution_total_year_before_last']['label'] = jsumfields_ts('Total Contributions Fiscal Year Before Last');
  $custom['fields']['soft_total_this_year']['label'] = jsumfields_ts('Total Soft Credits this Fiscal Year');

  $custom['fields']['event_first_attended_date'] = array(
    'label' => jsumfields_ts('Date of the first attended event'),
    'data_type' => 'Date',
    'html_type' => 'Select Date',
    'weight' => '71',
    'text_length' => '32',
    'trigger_sql' => '(
      SELECT
        e.start_date AS summary_value
      FROM civicrm_participant t1
        JOIN civicrm_event e ON t1.event_id = e.id
      WHERE
        t1.contact_id = NEW.contact_id
        AND t1.status_id IN (%participant_status_ids)
        AND e.event_type_id IN (%event_type_ids)
      ORDER BY start_date ASC LIMIT 1
    )',
    'trigger_table' => 'civicrm_participant',
    'optgroup' => 'event_standard',
  );

  $custom['fields']['grant_count_received'] = array(
    'label' => jsumfields_ts('Total number of grants received'),
    'data_type' => 'Int',
    'html_type' => 'Text',
    'weight' => '71',
    'text_length' => '255',
    'trigger_sql' => _jsumfields_sql_rewrite('
      (
        SELECT
          count(*)
        FROM
          civicrm_grant g
        WHERE
          g.contact_id = NEW.contact_id
          AND g.status_id in (%jsumfields_grant_status_ids)
          AND g.grant_type_id in (%jsumfields_grant_type_ids)
      )
    '),
    'trigger_table' => 'civicrm_grant',
    'optgroup' => 'civigrant',
  );

  $custom['fields']['grant_total_received'] = array(
    'label' => jsumfields_ts('Total amount in grants received'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => _jsumfields_sql_rewrite('
      (
        SELECT
          coalesce(sum(g.amount_granted), 0)
        FROM
          civicrm_grant g
        WHERE
          g.contact_id = NEW.contact_id
          AND g.status_id in (%jsumfields_grant_status_ids)
          AND g.grant_type_id in (%jsumfields_grant_type_ids)
      )
    '),
    'trigger_table' => 'civicrm_grant',
    'optgroup' => 'civigrant',
  );

  $custom['fields']['grant_types_received'] = array(
    'label' => jsumfields_ts('Grant types received'),
    'data_type' => 'String',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '255',
    'trigger_sql' => _jsumfields_sql_rewrite('
      (
        SELECT
          GROUP_CONCAT(
            DISTINCT ov.label
            ORDER BY ov.label
            SEPARATOR ", "
          )
        FROM
          civicrm_grant g
          INNER JOIN civicrm_option_value ov ON ov.value = g.grant_type_id
          INNER JOIN civicrm_option_group og
            ON og.id = ov.option_group_id
            AND og.name = "grant_type"
        WHERE
          g.contact_id = NEW.contact_id
          AND g.status_id in (%jsumfields_grant_status_ids)
          AND g.grant_type_id in (%jsumfields_grant_type_ids)
      )
    '),
    'trigger_table' => 'civicrm_grant',
    'optgroup' => 'civigrant',
  );

  $custom['fields']['mail_openrate_alltime'] = array(
    'label' => jsumfields_ts('Open rate rate all time'),
    'data_type' => 'Float',
    'html_type' => 'Text',
    'weight' => '71',
    'text_length' => '255',
    'trigger_table' => 'civicrm_jsumfields_placeholder',
    'trigger_sql' => '""',
    'jsumfields_update_sql' => '
      INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
        SELECT t.contact_id, t.rate
        FROM
          (
          SELECT
            s.contact_id, ROUND(coalesce(coalesce(o.opened, 0) / (coalesce(s.sent, 0) - coalesce(b.bounced, 0)), 0) * 100, 2) as rate
          FROM
          (
            -- total mailings sent to contact
            SELECT q.contact_id, count(*) as sent
            FROM
              civicrm_mailing_event_queue q
              INNER JOIN civicrm_mailing_event_delivered d ON d.event_queue_id = q.id
            WHERE
              1
            GROUP BY
              q.contact_id
          ) s
          LEFT JOIN (
            -- total mailings opened
            SELECT q.contact_id, count(*) as opened
            FROM
              civicrm_mailing_event_queue q
              INNER JOIN civicrm_mailing_event_opened o ON o.event_queue_id = q.id
            WHERE
              1
            GROUP BY
              q.contact_id
          ) o ON o.contact_id = s.contact_id
          LEFT JOIN (
            -- total mailings bounced
            SELECT q.contact_id, count(*) as bounced
            FROM
              civicrm_mailing_event_queue q
              INNER JOIN civicrm_mailing_event_bounce b ON b.event_queue_id = q.id
            WHERE
              1
            GROUP BY
              q.contact_id
          ) b ON b.contact_id = s.contact_id
        ) t
      ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.rate;
    ',
    'jsumfields_extra' => array(
      array(
        'trigger_table' => 'civicrm_mailing_event_delivered',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
              SELECT t.contact_id, t.rate
              FROM
                (
                SELECT
                  s.contact_id, ROUND(coalesce(coalesce(o.opened, 0) / (coalesce(s.sent, 0) - coalesce(b.bounced, 0)), 0) * 100, 2) as rate
                FROM
                (
                  -- total mailings sent to contact
                  SELECT q2.contact_id, count(*) as sent
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_delivered d ON d.event_queue_id = q2.id
                  WHERE
                    q1.id = NEW.event_queue_id
                  GROUP BY
                    q2.contact_id
                ) s
                LEFT JOIN (
                  -- total mailings opened
                  SELECT q2.contact_id, count(*) as opened
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_opened o ON o.event_queue_id = q2.id
                  WHERE
                    q1.id = NEW.event_queue_id
                  GROUP BY
                    q2.contact_id
                ) o ON o.contact_id = s.contact_id
                LEFT JOIN (
                  -- total mailings bounced
                  SELECT q2.contact_id, count(*) as bounced
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_bounce b ON b.event_queue_id = q2.id
                  WHERE
                    q1.id = NEW.event_queue_id
                  GROUP BY
                    q2.contact_id
                ) b ON b.contact_id = s.contact_id
              ) t
            ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.rate;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_mailing_event_bounce',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
              SELECT t.contact_id, t.rate
              FROM
                (
                SELECT
                  s.contact_id, ROUND(coalesce(coalesce(o.opened, 0) / (coalesce(s.sent, 0) - coalesce(b.bounced, 0)), 0) * 100, 2) as rate
                FROM
                (
                  -- total mailings sent to contact
                  SELECT q2.contact_id, count(*) as sent
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_delivered d ON d.event_queue_id = q2.id
                  WHERE
                    q1.id = NEW.event_queue_id
                  GROUP BY
                    q2.contact_id
                ) s
                LEFT JOIN (
                  -- total mailings opened
                  SELECT q2.contact_id, count(*) as opened
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_opened o ON o.event_queue_id = q2.id
                  WHERE
                    q1.id = NEW.event_queue_id
                  GROUP BY
                    q2.contact_id
                ) o ON o.contact_id = s.contact_id
                LEFT JOIN (
                  -- total mailings bounced
                  SELECT q2.contact_id, count(*) as bounced
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_bounce b ON b.event_queue_id = q2.id
                  WHERE
                    q1.id = NEW.event_queue_id
                  GROUP BY
                    q2.contact_id
                ) b ON b.contact_id = s.contact_id
              ) t
            ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.rate;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_mailing_event_opened',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
              SELECT t.contact_id, t.rate
              FROM
                (
                SELECT
                  s.contact_id, ROUND(coalesce(coalesce(o.opened, 0) / (coalesce(s.sent, 0) - coalesce(b.bounced, 0)), 0) * 100, 2) as rate
                FROM
                (
                  -- total mailings sent to contact
                  SELECT q2.contact_id, count(*) as sent
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_delivered d ON d.event_queue_id = q2.id
                  WHERE
                    q1.id = NEW.event_queue_id
                  GROUP BY
                    q2.contact_id
                ) s
                LEFT JOIN (
                  -- total mailings opened
                  SELECT q2.contact_id, count(*) as opened
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_opened o ON o.event_queue_id = q2.id
                  WHERE
                    q1.id = NEW.event_queue_id
                  GROUP BY
                    q2.contact_id
                ) o ON o.contact_id = s.contact_id
                LEFT JOIN (
                  -- total mailings bounced
                  SELECT q2.contact_id, count(*) as bounced
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_bounce b ON b.event_queue_id = q2.id
                  WHERE
                    q1.id = NEW.event_queue_id
                  GROUP BY
                    q2.contact_id
                ) b ON b.contact_id = s.contact_id
              ) t
            ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.rate;
        ',
      ),
    ),
    'optgroup' => 'civimail',
  );

  $custom['fields']['mail_openrate_last12months'] = array(
    'label' => jsumfields_ts('Open rate rate last 12 months'),
    'data_type' => 'Float',
    'html_type' => 'Text',
    'weight' => '71',
    'text_length' => '255',
    'trigger_table' => 'civicrm_jsumfields_placeholder',
    'trigger_sql' => '""',
    'jsumfields_update_sql' => '
      INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
        SELECT t.contact_id, t.rate
        FROM
          (
          SELECT
            s.contact_id, ROUND(coalesce(coalesce(o.opened, 0) / (coalesce(s.sent, 0) - coalesce(b.bounced, 0)), 0) * 100, 2) as rate
          FROM
          (
            -- total mailings sent to contact
            SELECT q2.contact_id, count(*) as sent
            FROM
              civicrm_mailing_event_queue q1
              INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
              INNER JOIN civicrm_mailing_event_delivered d ON d.event_queue_id = q2.id
              INNER JOIN civicrm_mailing_job j ON j.id = q2.job_id
            WHERE
              1
              AND j.start_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
            GROUP BY
              q2.contact_id
          ) s
          LEFT JOIN (
            -- total mailings opened
            SELECT q2.contact_id, count(*) as opened
            FROM
              civicrm_mailing_event_queue q1
              INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
              INNER JOIN civicrm_mailing_event_opened o ON o.event_queue_id = q2.id
              INNER JOIN civicrm_mailing_job j ON j.id = q2.job_id
            WHERE
              1
              AND j.start_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
            GROUP BY
              q2.contact_id
          ) o ON o.contact_id = s.contact_id
          LEFT JOIN (
            -- total mailings bounced
            SELECT q2.contact_id, count(*) as bounced
            FROM
              civicrm_mailing_event_queue q1
              INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
              INNER JOIN civicrm_mailing_event_bounce b ON b.event_queue_id = q2.id
              INNER JOIN civicrm_mailing_job j ON j.id = q2.job_id
            WHERE
              1
              AND j.start_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
            GROUP BY
              q2.contact_id
          ) b ON b.contact_id = s.contact_id
        ) t
      ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.rate;
    ',
    'jsumfields_extra' => array(
      array(
        'trigger_table' => 'civicrm_mailing_event_delivered',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
              SELECT t.contact_id, t.rate
              FROM
                (
                SELECT
                  s.contact_id, ROUND(coalesce(coalesce(o.opened, 0) / (coalesce(s.sent, 0) - coalesce(b.bounced, 0)), 0) * 100, 2) as rate
                FROM
                (
                  -- total mailings sent to contact
                  SELECT q2.contact_id, count(*) as sent
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_delivered d ON d.event_queue_id = q2.id
                    INNER JOIN civicrm_mailing_job j ON j.id = q2.job_id
                  WHERE
                    q1.id = NEW.event_queue_id
                    AND j.start_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                  GROUP BY
                    q2.contact_id
                ) s
                LEFT JOIN (
                  -- total mailings opened
                  SELECT q2.contact_id, count(*) as opened
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_opened o ON o.event_queue_id = q2.id
                    INNER JOIN civicrm_mailing_job j ON j.id = q2.job_id
                  WHERE
                    q1.id = NEW.event_queue_id
                    AND j.start_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                  GROUP BY
                    q2.contact_id
                ) o ON o.contact_id = s.contact_id
                LEFT JOIN (
                  -- total mailings bounced
                  SELECT q2.contact_id, count(*) as bounced
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_bounce b ON b.event_queue_id = q2.id
                    INNER JOIN civicrm_mailing_job j ON j.id = q2.job_id
                  WHERE
                    q1.id = NEW.event_queue_id
                    AND j.start_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                  GROUP BY
                    q2.contact_id
                ) b ON b.contact_id = s.contact_id
              ) t
            ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.rate;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_mailing_event_bounce',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
              SELECT t.contact_id, t.rate
              FROM
                (
                SELECT
                  s.contact_id, ROUND(coalesce(coalesce(o.opened, 0) / (coalesce(s.sent, 0) - coalesce(b.bounced, 0)), 0) * 100, 2) as rate
                FROM
                (
                  -- total mailings sent to contact
                  SELECT q2.contact_id, count(*) as sent
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_delivered d ON d.event_queue_id = q2.id
                  WHERE
                    q1.id = NEW.event_queue_id
                    AND j.start_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                  GROUP BY
                    q2.contact_id
                ) s
                LEFT JOIN (
                  -- total mailings opened
                  SELECT q2.contact_id, count(*) as opened
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_opened o ON o.event_queue_id = q2.id
                  WHERE
                    q1.id = NEW.event_queue_id
                    AND j.start_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                  GROUP BY
                    q2.contact_id
                ) o ON o.contact_id = s.contact_id
                LEFT JOIN (
                  -- total mailings bounced
                  SELECT q2.contact_id, count(*) as bounced
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_bounce b ON b.event_queue_id = q2.id
                  WHERE
                    q1.id = NEW.event_queue_id
                    AND j.start_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                  GROUP BY
                    q2.contact_id
                ) b ON b.contact_id = s.contact_id
              ) t
            ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.rate;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_mailing_event_opened',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
              SELECT t.contact_id, t.rate
              FROM
                (
                SELECT
                  s.contact_id, ROUND(coalesce(coalesce(o.opened, 0) / (coalesce(s.sent, 0) - coalesce(b.bounced, 0)), 0) * 100, 2) as rate
                FROM
                (
                  -- total mailings sent to contact
                  SELECT q2.contact_id, count(*) as sent
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_delivered d ON d.event_queue_id = q2.id
                  WHERE
                    q1.id = NEW.event_queue_id
                    AND j.start_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                  GROUP BY
                    q2.contact_id
                ) s
                LEFT JOIN (
                  -- total mailings opened
                  SELECT q2.contact_id, count(*) as opened
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_opened o ON o.event_queue_id = q2.id
                  WHERE
                    q1.id = NEW.event_queue_id
                    AND j.start_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                  GROUP BY
                    q2.contact_id
                ) o ON o.contact_id = s.contact_id
                LEFT JOIN (
                  -- total mailings bounced
                  SELECT q2.contact_id, count(*) as bounced
                  FROM
                    civicrm_mailing_event_queue q1
                    INNER JOIN civicrm_mailing_event_queue q2 ON q1.contact_id = q2.contact_id
                    INNER JOIN civicrm_mailing_event_bounce b ON b.event_queue_id = q2.id
                  WHERE
                    q1.id = NEW.event_queue_id
                    AND j.start_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                  GROUP BY
                    q2.contact_id
                ) b ON b.contact_id = s.contact_id
              ) t
            ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.rate;
        ',
      ),
    ),
    'optgroup' => 'civimail',
  );

  $mail_clickrate_alltime_jsumfields_extra_trigger_sql = '

    -- Of all emails in which there is a link, which this contact opened:
    -- in how many of those emails did this contact click a link?
    -- In other words:
    -- Click-through rate  = U / (S - B)
    --   U = number of distinct trackable URLs opened by this contact.
    --   S = number of emails-with-trackable-links sent to this contact.
    --   B = number of bounced emails-with-trackable-links sent to this contact.

    INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
    SELECT t.contact_id, t.rate
    FROM (
      SELECT
        s.contact_id, ROUND(coalesce(coalesce(o.opened, 0) / (coalesce(s.sent, 0) - coalesce(b.bounced, 0)), 0) * 100, 2) as rate
      FROM
      (
        -- total mailings-with-trackable-links sent to contact
        SELECT q.contact_id, count(*) as sent
        FROM
          civicrm_mailing_event_queue q
          INNER JOIN civicrm_mailing_event_delivered d ON d.event_queue_id = q.id
          INNER JOIN civicrm_mailing_job j ON q.job_id = j.id
          INNER JOIN civicrm_mailing_trackable_url u ON u.mailing_id = j.mailing_id
        WHERE
          q.id = NEW.event_queue_id
        GROUP BY
          q.contact_id
      ) s
      LEFT JOIN (
        -- total trackable urls opened
        SELECT q.contact_id, count(*) as opened
        FROM
          civicrm_mailing_event_queue q
          INNER JOIN civicrm_mailing_event_trackable_url_open o ON o.event_queue_id = q.id
        WHERE
          q.id = NEW.event_queue_id
        GROUP BY
          q.contact_id
      ) o ON o.contact_id = s.contact_id
      LEFT JOIN (
        -- total mailings-with-trackable-links bounced
        SELECT q.contact_id, count(*) as bounced
        FROM
          civicrm_mailing_event_queue q
          INNER JOIN civicrm_mailing_job j ON q.job_id = j.id
          INNER JOIN civicrm_mailing_trackable_url u ON u.mailing_id = j.mailing_id
          INNER JOIN civicrm_mailing_event_bounce b ON b.event_queue_id = q.id
        WHERE
          q.id = NEW.event_queue_id
        GROUP BY
          q.contact_id
      ) b ON b.contact_id = s.contact_id
    ) t
    ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.rate;
  ';
  $custom['fields']['mail_clickrate_alltime'] = array(
    'label' => jsumfields_ts('Click-through rate all time'),
    'data_type' => 'Float',
    'html_type' => 'Text',
    'weight' => '71',
    'text_length' => '255',
    'trigger_table' => 'civicrm_jsumfields_placeholder',
    'trigger_sql' => '""',
    'jsumfields_update_sql' => '

      -- Of all emails in which there is a link, which this contact opened:
      -- in how many of those emails did this contact click a link?
      -- In other words:
      -- Click-through rate  = U / (S - B)
      --   U = number of distinct trackable URLs opened by this contact.
      --   S = number of emails-with-trackable-links sent to this contact.
      --   B = number of bounced emails-with-trackable-links sent to this contact.

      INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
        SELECT t.contact_id, t.rate
        FROM
          (
          SELECT
            s.contact_id, ROUND(coalesce(coalesce(o.opened, 0) / (coalesce(s.sent, 0) - coalesce(b.bounced, 0)), 0) * 100, 2) as rate
          FROM
          (
            -- total mailings-with-trackable-links sent to contact
            SELECT q.contact_id, count(*) as sent
            FROM
              civicrm_mailing_event_queue q
              INNER JOIN civicrm_mailing_event_delivered d ON d.event_queue_id = q.id
              INNER JOIN civicrm_mailing_job j ON q.job_id = j.id
              INNER JOIN civicrm_mailing_trackable_url u ON u.mailing_id = j.mailing_id
            WHERE
              1
            GROUP BY
              q.contact_id
          ) s
          LEFT JOIN (
            -- total trackable urls opened
            SELECT q.contact_id, count(*) as opened
            FROM
              civicrm_mailing_event_queue q
              INNER JOIN civicrm_mailing_event_trackable_url_open o ON o.event_queue_id = q.id
            WHERE
              1
            GROUP BY
              q.contact_id
          ) o ON o.contact_id = s.contact_id
          LEFT JOIN (
            -- total mailings-with-trackable-links bounced
            SELECT q.contact_id, count(*) as bounced
            FROM
              civicrm_mailing_event_queue q
              INNER JOIN civicrm_mailing_job j ON q.job_id = j.id
              INNER JOIN civicrm_mailing_trackable_url u ON u.mailing_id = j.mailing_id
              INNER JOIN civicrm_mailing_event_bounce b ON b.event_queue_id = q.id
            WHERE
              1
            GROUP BY
              q.contact_id
          ) b ON b.contact_id = s.contact_id
        ) t
      ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.rate;
    ',
    'jsumfields_extra' => array(
      array(
        'trigger_table' => 'civicrm_mailing_event_delivered',
        'trigger_sql' => $mail_clickrate_alltime_jsumfields_extra_trigger_sql,
      ),
      array(
        'trigger_table' => 'civicrm_mailing_event_bounce',
        'trigger_sql' => $mail_clickrate_alltime_jsumfields_extra_trigger_sql,
      ),
      array(
        'trigger_table' => 'civicrm_mailing_event_trackable_url_open',
        'trigger_sql' => $mail_clickrate_alltime_jsumfields_extra_trigger_sql,
      ),
    ),
    'optgroup' => 'civimail',
  );

  $custom['fields']['contribution_total_this_calendar_year'] = array(
    'label' => jsumfields_ts('Total Contributions this Calendar Year'),
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
    'label' => jsumfields_ts('Total Contributions last Calendar Year'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => '(
      SELECT COALESCE(SUM(total_amount),0)
      FROM civicrm_contribution t1
      WHERE YEAR(CAST(receive_date AS DATE)) = (YEAR(CURDATE()) - 1)
        AND t1.contact_id = NEW.contact_id
        AND t1.contribution_status_id = 1
        AND t1.financial_type_id IN (%financial_type_ids)
    )',
    'trigger_table' => 'civicrm_contribution',
    'optgroup' => 'fundraising',
  );

  $custom['fields']['contribution_total_calendar_year_before_last'] = array(
    'label' => jsumfields_ts('Total Contributions Calendar Year Before Last'),
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
    'label' => jsumfields_ts('Number of Years of Contributions'),
    'data_type' => 'Integer',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => '(
      SELECT count(DISTINCT YEAR(CAST(receive_date AS DATE)))
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
    'label' => jsumfields_ts('Total Soft Credits this Calendar Year'),
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
          AND YEAR(CAST(receive_date AS DATE)) = YEAR(CURDATE())
      )
    )',
    'trigger_table' => 'civicrm_contribution_soft',
    'optgroup' => 'soft',
  );

  $custom['fields']['soft_total_last_calendar_year'] = array(
    'label' => jsumfields_ts('Total Soft Credits last Calendar Year'),
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
          AND YEAR(CAST(receive_date AS DATE)) = (YEAR(CURDATE()) - 1)
      )
    )',
    'trigger_table' => 'civicrm_contribution_soft',
    'optgroup' => 'soft',
  );

  $custom['fields']['soft_total_last_fiscal_year'] = array(
    'label' => jsumfields_ts('Total Soft Credits last Fiscal Year'),
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
    'label' => jsumfields_ts('Lifetime contributions + soft credits'),
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
   *  a. _jsumfields_generate_data_based_on_current_data(), our own version of
   *    sumfields_generate_data_based_on_current_data()
   *  b. calling _jsumfields_generate_data_based_on_current_data() via
   *    apiwrappers hook, so it always happens when the API gendata is called.
   *  c. calling _jsumfields_generate_data_based_on_current_data() via
   *    postProcess hook, so it happens (as needed ) when the Sumfields form is
   *    submitted.
   * To make all this happen, we define special values in array properties named
   * 'jsumfields_*', which are ignored by sumfields, but are specifically
   * handled by _jsumfields_generate_data_based_on_current_data() and
   * jsumfields_civicrm_triggerInfo().
   */

  $custom['fields']['relatedcontrib_this_fiscal_year'] = array(
    'label' => jsumfields_ts('Related contact contributions this fiscal year'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => _jsumfields_sql_rewrite('
    (
      select coalesce(sum(total_amount),0) as total from
        (
          select
            contact_id_a, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_b
          UNION ALL
          select
            contact_id_b, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_a
        ) t
        where
          t.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
          and t.is_active
          and t.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
          AND CAST(t.receive_date AS DATE) BETWEEN "%current_fiscal_year_begin" AND "%current_fiscal_year_end"
          AND t.contribution_status_id = 1
          AND contact_id_a = NEW.contact_id
        group by contact_id_a
      )
    '),
    'trigger_table' => 'civicrm_contribution',
    'jsumfields_extra' => array(
      array(
        'trigger_table' => 'civicrm_contribution',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT t.related_contact_id, t.total
            FROM
            (
              SELECT
                t.related_contact_id, if(t.related_contact_id = r.contact_id_b, r.contact_id_a, r.contact_id_b) as donor_contact_id, coalesce(sum(ctrb.total_amount), 0) as total
              FROM
                (
                  select DISTINCT
                    NEW.contact_id, if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a) as related_contact_id
                  from
                    civicrm_relationship r
                  WHERE
                    NEW.contact_id IN (r.contact_id_a, r.contact_id_b)
                    AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                    AND r.is_active
                ) t
                INNER JOIN civicrm_relationship r ON t.related_contact_id in (r.contact_id_b, r.contact_id_a)
                  AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                  AND r.is_active
                LEFT JOIN civicrm_contribution ctrb ON ctrb.contact_id = if(t.related_contact_id = r.contact_id_b, r.contact_id_a, r.contact_id_b)
                  and ctrb.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND CAST(ctrb.receive_date AS DATE) BETWEEN "%current_fiscal_year_begin" AND "%current_fiscal_year_end"
                  AND ctrb.contribution_status_id = 1
              GROUP BY
                t.related_contact_id
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_a, t.total
            FROM
            (
              SELECT
                coalesce(sum(cont1.total_amount), 0) as total
              FROM
                civicrm_relationship r
                INNER JOIN civicrm_contribution cont1
              WHERE
                r.is_active
                AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                AND (
                  (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_a)
                  OR
                  (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_a)
                )
                AND CAST(cont1.receive_date AS DATE) BETWEEN "%current_fiscal_year_begin" AND "%current_fiscal_year_end"
                AND cont1.contribution_status_id = 1
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_b, t.total
            FROM
            (
              SELECT
                coalesce(sum(cont1.total_amount), 0) as total
              FROM
                civicrm_relationship r
                INNER JOIN civicrm_contribution cont1
              WHERE
                r.is_active
                AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                AND (
                  (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_b)
                  OR
                  (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_b)
                )
                AND CAST(cont1.receive_date AS DATE) BETWEEN "%current_fiscal_year_begin" AND "%current_fiscal_year_end"
                AND cont1.contribution_status_id = 1
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
       ',
      ),
    ),
    'optgroup' => 'relatedcontrib', // could just add this to the existing "fundraising" optgroup
  );

  $custom['fields']['relatedcontrib_this_calendar_year'] = array(
    'label' => jsumfields_ts('Related contact contributions this calendar year'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => _jsumfields_sql_rewrite('
    (
      select coalesce(sum(total_amount),0) as total from
        (
          select
            contact_id_a, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_b
          UNION ALL
          select
            contact_id_b, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_a
        ) t
        where
          t.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
          and t.is_active
          and t.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
          AND YEAR(CAST(t.receive_date AS DATE)) = YEAR(CURDATE())
          AND t.contribution_status_id = 1
          AND contact_id_a = NEW.contact_id
        group by contact_id_a
      )
    '),
    'trigger_table' => 'civicrm_contribution',
    'jsumfields_extra' => array(
      array(
        'trigger_table' => 'civicrm_contribution',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT t.related_contact_id, t.total
            FROM
            (
              SELECT
                t.related_contact_id, if(t.related_contact_id = r.contact_id_b, r.contact_id_a, r.contact_id_b) as donor_contact_id, coalesce(sum(ctrb.total_amount), 0) as total
              FROM
                (
                  select DISTINCT
                    NEW.contact_id, if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a) as related_contact_id
                  from
                    civicrm_relationship r
                  WHERE
                    NEW.contact_id IN (r.contact_id_a, r.contact_id_b)
                    AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                    AND r.is_active
                ) t
                INNER JOIN civicrm_relationship r ON t.related_contact_id in (r.contact_id_b, r.contact_id_a)
                  AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                  AND r.is_active
                LEFT JOIN civicrm_contribution ctrb ON ctrb.contact_id = if(t.related_contact_id = r.contact_id_b, r.contact_id_a, r.contact_id_b)
                  and ctrb.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND YEAR(CAST(ctrb.receive_date AS DATE)) = YEAR(CURDATE())
                  AND ctrb.contribution_status_id = 1
              GROUP BY
                t.related_contact_id
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_a, t.total
            FROM
            (
              SELECT
                coalesce(sum(cont1.total_amount), 0) as total
              FROM
                civicrm_relationship r
                INNER JOIN civicrm_contribution cont1
              WHERE
                r.is_active
                AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                AND (
                  (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_a)
                  OR
                  (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_a)
                )
                AND YEAR(CAST(cont1.receive_date AS DATE)) = YEAR(CURDATE())
                AND cont1.contribution_status_id = 1
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_b, t.total
            FROM
            (
              SELECT
                coalesce(sum(cont1.total_amount), 0) as total
              FROM
                civicrm_relationship r
                INNER JOIN civicrm_contribution cont1
              WHERE
                r.is_active
                AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                AND (
                  (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_b)
                  OR
                  (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_b)
                )
                AND YEAR(CAST(cont1.receive_date AS DATE)) = YEAR(CURDATE())
                AND cont1.contribution_status_id = 1
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
    ),
    'optgroup' => 'relatedcontrib', // could just add this to the existing "fundraising" optgroup
  );

  $custom['fields']['relatedcontrib_last_calendar_year'] = array(
    'label' => jsumfields_ts('Related contact contributions last calendar year'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => _jsumfields_sql_rewrite('
    (
      select coalesce(sum(total_amount),0) as total from
        (
          select
            contact_id_a, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_b
          UNION ALL
          select
            contact_id_b, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_a
        ) t
        where
          t.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
          and t.is_active
          and t.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
          AND YEAR(CAST(t.receive_date AS DATE)) = (YEAR(CURDATE()) - 1)
          AND t.contribution_status_id = 1
          AND contact_id_a = NEW.contact_id
        group by contact_id_a
      )
    '),
    'trigger_table' => 'civicrm_contribution',
    'jsumfields_extra' => array(
      array(
        'trigger_table' => 'civicrm_contribution',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT t.related_contact_id, t.total
            FROM
            (
              SELECT
                t.related_contact_id, if(t.related_contact_id = r.contact_id_b, r.contact_id_a, r.contact_id_b) as donor_contact_id, coalesce(sum(ctrb.total_amount), 0) as total
              FROM
                (
                  select DISTINCT
                    NEW.contact_id, if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a) as related_contact_id
                  from
                    civicrm_relationship r
                  WHERE
                    NEW.contact_id IN (r.contact_id_a, r.contact_id_b)
                    AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                    AND r.is_active
                ) t
                INNER JOIN civicrm_relationship r ON t.related_contact_id in (r.contact_id_b, r.contact_id_a)
                  AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                  AND r.is_active
                LEFT JOIN civicrm_contribution ctrb ON ctrb.contact_id = if(t.related_contact_id = r.contact_id_b, r.contact_id_a, r.contact_id_b)
                  and ctrb.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND YEAR(CAST(ctrb.receive_date AS DATE)) = (YEAR(CURDATE()) - 1)
                  AND ctrb.contribution_status_id = 1
              GROUP BY
                t.related_contact_id
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_a, t.total
            FROM
            (
              SELECT
                coalesce(sum(cont1.total_amount), 0) as total
              FROM
                civicrm_relationship r
                INNER JOIN civicrm_contribution cont1
              WHERE
                r.is_active
                AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                AND (
                  (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_a)
                  OR
                  (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_a)
                )
                AND YEAR(CAST(cont1.receive_date AS DATE)) = (YEAR(CURDATE()) - 1)
                AND cont1.contribution_status_id = 1
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_b, t.total
            FROM
            (
              SELECT
                coalesce(sum(cont1.total_amount), 0) as total
              FROM
                civicrm_relationship r
                INNER JOIN civicrm_contribution cont1
              WHERE
                r.is_active
                AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                AND (
                  (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_b)
                  OR
                  (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_b)
                )
                AND YEAR(CAST(cont1.receive_date AS DATE)) = (YEAR(CURDATE()) - 1)
                AND cont1.contribution_status_id = 1
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
    ),
    'optgroup' => 'relatedcontrib', // could just add this to the existing "fundraising" optgroup
  );

  $custom['fields']['relatedcontrib_alltime'] = array(
    'label' => jsumfields_ts('Related contact contributions all time'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => _jsumfields_sql_rewrite('
    (
      select coalesce(sum(total_amount),0) as total from
        (
          select
            contact_id_a, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_b
          UNION ALL
          select
            contact_id_b, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_a
        ) t
        where
          t.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
          and t.is_active
          and t.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
          AND t.contribution_status_id = 1
          AND contact_id_a = NEW.contact_id
        group by contact_id_a
      )
    '),
    'trigger_table' => 'civicrm_contribution',
    'jsumfields_extra' => array(
      array(
        'trigger_table' => 'civicrm_contribution',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT t.related_contact_id, t.total
            FROM
            (
              SELECT
                t.related_contact_id, if(t.related_contact_id = r.contact_id_b, r.contact_id_a, r.contact_id_b) as donor_contact_id, coalesce(sum(ctrb.total_amount), 0) as total
              FROM
                (
                  select DISTINCT
                    NEW.contact_id, if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a) as related_contact_id
                  from
                    civicrm_relationship r
                  WHERE
                    NEW.contact_id IN (r.contact_id_a, r.contact_id_b)
                    AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                    AND r.is_active
                ) t
                INNER JOIN civicrm_relationship r ON t.related_contact_id in (r.contact_id_b, r.contact_id_a)
                  AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                  AND r.is_active
                LEFT JOIN civicrm_contribution ctrb ON ctrb.contact_id = if(t.related_contact_id = r.contact_id_b, r.contact_id_a, r.contact_id_b)
                  and ctrb.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND ctrb.contribution_status_id = 1
              GROUP BY
                t.related_contact_id
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_a, t.total
            FROM
            (
              SELECT
                coalesce(sum(cont1.total_amount), 0) total
              FROM
                civicrm_relationship r
                INNER JOIN civicrm_contribution cont1
              WHERE
                r.is_active
                AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                AND (
                  (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_a)
                  OR
                  (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_a)
                )
                AND cont1.contribution_status_id = 1
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_b, t.total
            FROM
            (
              SELECT
                coalesce(sum(cont1.total_amount), 0) as total
              FROM
                civicrm_relationship r
                INNER JOIN civicrm_contribution cont1
              WHERE
                r.is_active
                AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                AND (
                  (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_b)
                  OR
                  (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_b)
                )
                AND cont1.contribution_status_id = 1
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
    ),
    'optgroup' => 'relatedcontrib', // could just add this to the existing "fundraising" optgroup
  );

  $custom['fields']['relatedcontrib_plusme_this_fiscal_year'] = array(
    'label' => jsumfields_ts('Combined contact & related contact contributions this fiscal year'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => _jsumfields_sql_rewrite('
    (
      select coalesce(sum(total_amount)) as total from
        (
          select
            contact_id_a, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_b
          UNION ALL
          select
            contact_id_b, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_a
          UNION ALL
          select ctrb.contact_id, 0 as relationship_type_id, 1 as is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
            from civicrm_contribution ctrb
        ) t
        where
          t.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids, 0)
          and t.is_active
          and t.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
          AND CAST(t.receive_date AS DATE) BETWEEN "%current_fiscal_year_begin" AND "%current_fiscal_year_end"
          AND t.contribution_status_id = 1
          AND contact_id_a = NEW.contact_id
        group by contact_id_a
      )
    '),
    'trigger_table' => 'civicrm_contribution',
    'jsumfields_extra' => array(
      array(
        'trigger_table' => 'civicrm_contribution',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
          SELECT t.related_contact_id, t.total
          FROM
            (
              SELECT
              donors.related_contact_id, coalesce(sum(ctrb.total_amount), 0) as total
              FROM
                (
                  SELECT DISTINCT
                      -- Everyone related to everyone related to NEW.contact_id
                    t.related_contact_id, if(t.related_contact_id = r.contact_id_b, r.contact_id_a, r.contact_id_b) as donor_contact_id
                  FROM
                    (
                      -- Everyone related to NEW.contact_id
                      select DISTINCT
                        if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a) as related_contact_id
                      from
                        civicrm_relationship r
                      WHERE
                        NEW.contact_id IN (r.contact_id_a, r.contact_id_b)
                        AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                        AND r.is_active
                    ) t
                    INNER JOIN civicrm_relationship r ON t.related_contact_id in (r.contact_id_b, r.contact_id_a)
                      AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                      AND r.is_active
                  UNION ALL
                  -- Repeat one row for each person related to NEW.contact_id (we want to count them too)
                  SELECT DISTINCT
                    if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a), if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a)
                  from
                    civicrm_relationship r
                  WHERE
                    NEW.contact_id IN (r.contact_id_a, r.contact_id_b)
                    AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                    AND r.is_active
                ) donors
                LEFT JOIN civicrm_contribution ctrb
                  ON (
                    ctrb.contact_id = donors.donor_contact_id
                  )
                  and ctrb.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND CAST(ctrb.receive_date AS DATE) BETWEEN "%current_fiscal_year_begin" AND "%current_fiscal_year_end"
                  AND ctrb.contribution_status_id = 1
              GROUP BY
                donors.related_contact_id
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_a, t.total
            FROM
            (
              SELECT
                coalesce(sum(total_amount), 0) as total
              FROM
              (
                select cont1.total_amount
                from
                  civicrm_relationship r
                  INNER JOIN civicrm_contribution cont1
                WHERE
                  r.is_active
                  AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                  AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND (
                    (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_a)
                    OR
                    (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_a)
                  )
                  AND CAST(cont1.receive_date AS DATE) BETWEEN "%current_fiscal_year_begin" AND "%current_fiscal_year_end"
                  AND cont1.contribution_status_id = 1
                UNION ALL
                SELECT
                  total_amount
                FROM
                  civicrm_contribution
                WHERE
                  contact_id = NEW.contact_id_a
                  AND financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND CAST(receive_date AS DATE) BETWEEN "%current_fiscal_year_begin" AND "%current_fiscal_year_end"
                  AND contribution_status_id = 1
              ) t
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_b, t.total
            FROM
            (
              SELECT
                coalesce(sum(total_amount), 0) as total
              FROM
              (
                select cont1.total_amount
                from
                  civicrm_relationship r
                  INNER JOIN civicrm_contribution cont1
                WHERE
                  r.is_active
                  AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                  AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND (
                    (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_b)
                    OR
                    (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_b)
                  )
                  AND CAST(cont1.receive_date AS DATE) BETWEEN "%current_fiscal_year_begin" AND "%current_fiscal_year_end"
                  AND cont1.contribution_status_id = 1
                UNION ALL
                SELECT
                  total_amount
                FROM
                  civicrm_contribution
                WHERE
                  contact_id = NEW.contact_id_b
                  AND financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND CAST(receive_date AS DATE) BETWEEN "%current_fiscal_year_begin" AND "%current_fiscal_year_end"
                  AND contribution_status_id = 1
              ) t
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
    ),
    'optgroup' => 'relatedcontrib', // could just add this to the existing "fundraising" optgroup
  );

  $custom['fields']['relatedcontrib_plusme_this_calendar_year'] = array(
    'label' => jsumfields_ts('Combined contact & related contact contributions this calendar year'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => _jsumfields_sql_rewrite('
    (
      select coalesce(sum(total_amount)) as total from
        (
          select
            contact_id_a, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_b
          UNION ALL
          select
            contact_id_b, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_a
          UNION ALL
          select ctrb.contact_id, 0 as relationship_type_id, 1 as is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
            from civicrm_contribution ctrb
        ) t
        where
          t.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids, 0)
          and t.is_active
          and t.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
          AND YEAR(CAST(receive_date AS DATE)) = YEAR(CURDATE())
          AND t.contribution_status_id = 1
          AND contact_id_a = NEW.contact_id
        group by contact_id_a
      )
    '),
    'trigger_table' => 'civicrm_contribution',
    'jsumfields_extra' => array(
      array(
        'trigger_table' => 'civicrm_contribution',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
          SELECT t.related_contact_id, t.total
          FROM
            (
              SELECT
              donors.related_contact_id, coalesce(sum(ctrb.total_amount), 0) as total
              FROM
                (
                  SELECT DISTINCT
                      -- Everyone related to everyone related to NEW.contact_id
                    t.related_contact_id, if(t.related_contact_id = r.contact_id_b, r.contact_id_a, r.contact_id_b) as donor_contact_id
                  FROM
                    (
                      -- Everyone related to NEW.contact_id
                      select DISTINCT
                        if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a) as related_contact_id
                      from
                        civicrm_relationship r
                      WHERE
                        NEW.contact_id IN (r.contact_id_a, r.contact_id_b)
                        AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                        AND r.is_active
                    ) t
                    INNER JOIN civicrm_relationship r ON t.related_contact_id in (r.contact_id_b, r.contact_id_a)
                      AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                      AND r.is_active
                  UNION ALL
                  -- Repeat one row for each person related to NEW.contact_id (we want to count them too)
                  SELECT DISTINCT
                    if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a), if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a)
                  from
                    civicrm_relationship r
                  WHERE
                    NEW.contact_id IN (r.contact_id_a, r.contact_id_b)
                    AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                    AND r.is_active
                ) donors
                LEFT JOIN civicrm_contribution ctrb
                  ON (
                    ctrb.contact_id = donors.donor_contact_id
                  )
                  and ctrb.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND YEAR(CAST(ctrb.receive_date AS DATE)) = YEAR(CURDATE())
                  AND ctrb.contribution_status_id = 1
              GROUP BY
                donors.related_contact_id
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_a, t.total
            FROM
            (
              SELECT
                coalesce(sum(total_amount), 0) as total
              FROM
              (
                select cont1.total_amount
                from
                  civicrm_relationship r
                  INNER JOIN civicrm_contribution cont1
                WHERE
                  r.is_active
                  AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                  AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND (
                    (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_a)
                    OR
                    (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_a)
                  )
                  AND YEAR(CAST(cont1.receive_date AS DATE)) = YEAR(CURDATE())
                  AND cont1.contribution_status_id = 1
                UNION ALL
                SELECT
                  total_amount
                FROM
                  civicrm_contribution
                WHERE
                  contact_id = NEW.contact_id_a
                  AND financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND YEAR(CAST(receive_date AS DATE)) = YEAR(CURDATE())
                  AND contribution_status_id = 1
              ) t
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_b, t.total
            FROM
            (
              SELECT
                coalesce(sum(total_amount), 0) as total
              FROM
              (
                select cont1.total_amount
                from
                  civicrm_relationship r
                  INNER JOIN civicrm_contribution cont1
                WHERE
                  r.is_active
                  AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                  AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND (
                    (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_b)
                    OR
                    (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_b)
                  )
                  AND YEAR(CAST(cont1.receive_date AS DATE)) = YEAR(CURDATE())
                  AND cont1.contribution_status_id = 1
                UNION ALL
                SELECT
                  total_amount
                FROM
                  civicrm_contribution
                WHERE
                  contact_id = NEW.contact_id_b
                  AND financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND YEAR(CAST(receive_date AS DATE)) = YEAR(CURDATE())
                  AND contribution_status_id = 1
              ) t
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
    ),
    'optgroup' => 'relatedcontrib', // could just add this to the existing "fundraising" optgroup
  );

  $custom['fields']['relatedcontrib_plusme_last_fiscal_year'] = array(
    'label' => jsumfields_ts('Combined contact & related contact contributions last fiscal year'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => _jsumfields_sql_rewrite('
    (
      select coalesce(sum(total_amount)) as total from
        (
          select
            contact_id_a, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_b
          UNION ALL
          select
            contact_id_b, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_a
          UNION ALL
          select ctrb.contact_id, 0 as relationship_type_id, 1 as is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
            from civicrm_contribution ctrb
        ) t
        where
          t.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids, 0)
          and t.is_active
          and t.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
          AND CAST(receive_date AS DATE) BETWEEN DATE_SUB("%current_fiscal_year_begin", INTERVAL 1 YEAR) AND DATE_SUB("%current_fiscal_year_end", INTERVAL 1 YEAR)
          AND t.contribution_status_id = 1
          AND contact_id_a = NEW.contact_id
        group by contact_id_a
      )
    '),
    'trigger_table' => 'civicrm_contribution',
    'jsumfields_extra' => array(
      array(
        'trigger_table' => 'civicrm_contribution',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
          SELECT t.related_contact_id, t.total
          FROM
            (
              SELECT
              donors.related_contact_id, coalesce(sum(ctrb.total_amount), 0) as total
              FROM
                (
                  SELECT DISTINCT
                      -- Everyone related to everyone related to NEW.contact_id
                    t.related_contact_id, if(t.related_contact_id = r.contact_id_b, r.contact_id_a, r.contact_id_b) as donor_contact_id
                  FROM
                    (
                      -- Everyone related to NEW.contact_id
                      select DISTINCT
                        if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a) as related_contact_id
                      from
                        civicrm_relationship r
                      WHERE
                        NEW.contact_id IN (r.contact_id_a, r.contact_id_b)
                        AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                        AND r.is_active
                    ) t
                    INNER JOIN civicrm_relationship r ON t.related_contact_id in (r.contact_id_b, r.contact_id_a)
                      AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                      AND r.is_active
                  UNION ALL
                  -- Repeat one row for each person related to NEW.contact_id (we want to count them too)
                  SELECT DISTINCT
                    if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a), if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a)
                  from
                    civicrm_relationship r
                  WHERE
                    NEW.contact_id IN (r.contact_id_a, r.contact_id_b)
                    AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                    AND r.is_active
                ) donors
                LEFT JOIN civicrm_contribution ctrb
                  ON (
                    ctrb.contact_id = donors.donor_contact_id
                  )
                  and ctrb.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND CAST(ctrb.receive_date AS DATE) BETWEEN DATE_SUB("%current_fiscal_year_begin", INTERVAL 1 YEAR) AND DATE_SUB("%current_fiscal_year_end", INTERVAL 1 YEAR)
                  AND ctrb.contribution_status_id = 1
              GROUP BY
                donors.related_contact_id
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_a, t.total
            FROM
            (
              SELECT
                coalesce(sum(total_amount), 0) as total
              FROM
              (
                select cont1.total_amount
                from
                  civicrm_relationship r
                  INNER JOIN civicrm_contribution cont1
                WHERE
                  r.is_active
                  AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                  AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND (
                    (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_a)
                    OR
                    (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_a)
                  )
                  AND CAST(cont1.receive_date AS DATE) BETWEEN DATE_SUB("%current_fiscal_year_begin", INTERVAL 1 YEAR) AND DATE_SUB("%current_fiscal_year_end", INTERVAL 1 YEAR)
                  AND cont1.contribution_status_id = 1
                UNION ALL
                SELECT
                  total_amount
                FROM
                  civicrm_contribution
                WHERE
                  contact_id = NEW.contact_id_a
                  AND financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND CAST(receive_date AS DATE) BETWEEN DATE_SUB("%current_fiscal_year_begin", INTERVAL 1 YEAR) AND DATE_SUB("%current_fiscal_year_end", INTERVAL 1 YEAR)
                  AND contribution_status_id = 1
              ) t
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_b, t.total
            FROM
            (
              SELECT
                coalesce(sum(total_amount), 0) as total
              FROM
              (
                select cont1.total_amount
                from
                  civicrm_relationship r
                  INNER JOIN civicrm_contribution cont1
                WHERE
                  r.is_active
                  AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                  AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND (
                    (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_b)
                    OR
                    (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_b)
                  )
                  AND CAST(cont1.receive_date AS DATE) BETWEEN DATE_SUB("%current_fiscal_year_begin", INTERVAL 1 YEAR) AND DATE_SUB("%current_fiscal_year_end", INTERVAL 1 YEAR)
                  AND cont1.contribution_status_id = 1
                UNION ALL
                SELECT
                  total_amount
                FROM
                  civicrm_contribution
                WHERE
                  contact_id = NEW.contact_id_b
                  AND financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND CAST(receive_date AS DATE) BETWEEN DATE_SUB("%current_fiscal_year_begin", INTERVAL 1 YEAR) AND DATE_SUB("%current_fiscal_year_end", INTERVAL 1 YEAR)
                  AND contribution_status_id = 1
              ) t
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
    ),
    'optgroup' => 'relatedcontrib', // could just add this to the existing "fundraising" optgroup
  );

  $custom['fields']['relatedcontrib_plusme_last_calendar_year'] = array(
    'label' => jsumfields_ts('Combined contact & related contact contributions last calendar year'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => _jsumfields_sql_rewrite('
    (
      select coalesce(sum(total_amount)) as total from
        (
          select
            contact_id_a, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_b
          UNION ALL
          select
            contact_id_b, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_a
          UNION ALL
          select ctrb.contact_id, 0 as relationship_type_id, 1 as is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
            from civicrm_contribution ctrb
        ) t
        where
          t.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids, 0)
          and t.is_active
          and t.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
          AND YEAR(CAST(receive_date AS DATE)) = (YEAR(CURDATE()) - 1)
          AND t.contribution_status_id = 1
          AND contact_id_a = NEW.contact_id
        group by contact_id_a
      )
    '),
    'trigger_table' => 'civicrm_contribution',
    'jsumfields_extra' => array(
      array(
        'trigger_table' => 'civicrm_contribution',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
          SELECT t.related_contact_id, t.total
          FROM
            (
              SELECT
              donors.related_contact_id, coalesce(sum(ctrb.total_amount), 0) as total
              FROM
                (
                  SELECT DISTINCT
                      -- Everyone related to everyone related to NEW.contact_id
                    t.related_contact_id, if(t.related_contact_id = r.contact_id_b, r.contact_id_a, r.contact_id_b) as donor_contact_id
                  FROM
                    (
                      -- Everyone related to NEW.contact_id
                      select DISTINCT
                        if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a) as related_contact_id
                      from
                        civicrm_relationship r
                      WHERE
                        NEW.contact_id IN (r.contact_id_a, r.contact_id_b)
                        AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                        AND r.is_active
                    ) t
                    INNER JOIN civicrm_relationship r ON t.related_contact_id in (r.contact_id_b, r.contact_id_a)
                      AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                      AND r.is_active
                  UNION ALL
                  -- Repeat one row for each person related to NEW.contact_id (we want to count them too)
                  SELECT DISTINCT
                    if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a), if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a)
                  from
                    civicrm_relationship r
                  WHERE
                    NEW.contact_id IN (r.contact_id_a, r.contact_id_b)
                    AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                    AND r.is_active
                ) donors
                LEFT JOIN civicrm_contribution ctrb
                  ON (
                    ctrb.contact_id = donors.donor_contact_id
                  )
                  and ctrb.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND YEAR(CAST(ctrb.receive_date AS DATE)) = (YEAR(CURDATE()) - 1)
                  AND ctrb.contribution_status_id = 1
              GROUP BY
                donors.related_contact_id
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_a, t.total
            FROM
            (
              SELECT
                coalesce(sum(total_amount), 0) as total
              FROM
              (
                select cont1.total_amount
                from
                  civicrm_relationship r
                  INNER JOIN civicrm_contribution cont1
                WHERE
                  r.is_active
                  AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                  AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND (
                    (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_a)
                    OR
                    (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_a)
                  )
                  AND YEAR(CAST(cont1.receive_date AS DATE)) = (YEAR(CURDATE()) - 1)
                  AND cont1.contribution_status_id = 1
                UNION ALL
                SELECT
                  total_amount
                FROM
                  civicrm_contribution
                WHERE
                  contact_id = NEW.contact_id_a
                  AND financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND YEAR(CAST(receive_date AS DATE)) = (YEAR(CURDATE()) - 1)
                  AND contribution_status_id = 1
              ) t
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_b, t.total
            FROM
            (
              SELECT
                coalesce(sum(total_amount), 0) as total
              FROM
              (
                select cont1.total_amount
                from
                  civicrm_relationship r
                  INNER JOIN civicrm_contribution cont1
                WHERE
                  r.is_active
                  AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                  AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND (
                    (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_b)
                    OR
                    (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_b)
                  )
                  AND YEAR(CAST(cont1.receive_date AS DATE)) = (YEAR(CURDATE()) - 1)
                  AND cont1.contribution_status_id = 1
                UNION ALL
                SELECT
                  total_amount
                FROM
                  civicrm_contribution
                WHERE
                  contact_id = NEW.contact_id_b
                  AND financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND YEAR(CAST(receive_date AS DATE)) = (YEAR(CURDATE()) - 1)
                  AND contribution_status_id = 1
              ) t
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
    ),
    'optgroup' => 'relatedcontrib', // could just add this to the existing "fundraising" optgroup
  );

  $custom['fields']['relatedcontrib_plusme_alltime'] = array(
    'label' => jsumfields_ts('Combined contact & related contact contributions all time'),
    'data_type' => 'Money',
    'html_type' => 'Text',
    'weight' => '15',
    'text_length' => '32',
    'trigger_sql' => _jsumfields_sql_rewrite('
    (
      select coalesce(sum(total_amount)) as total from
        (
          select
            contact_id_a, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_b
          UNION ALL
          select
            contact_id_b, r.relationship_type_id, r.is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
          from
            civicrm_relationship r
            inner join civicrm_contribution ctrb ON ctrb.contact_id = r.contact_id_a
          UNION ALL
          select ctrb.contact_id, 0 as relationship_type_id, 1 as is_active, ctrb.financial_type_id, ctrb.receive_date, ctrb.total_amount, ctrb.contribution_status_id
            from civicrm_contribution ctrb
        ) t
        where
          t.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids, 0)
          and t.is_active
          and t.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
          AND t.contribution_status_id = 1
          AND contact_id_a = NEW.contact_id
        group by contact_id_a
      )
    '),
    'trigger_table' => 'civicrm_contribution',
    'jsumfields_extra' => array(
      array(
        'trigger_table' => 'civicrm_contribution',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
          SELECT t.related_contact_id, t.total
          FROM
            (
              SELECT
              donors.related_contact_id, coalesce(sum(ctrb.total_amount), 0) as total
              FROM
                (
                  SELECT DISTINCT
                      -- Everyone related to everyone related to NEW.contact_id
                    t.related_contact_id, if(t.related_contact_id = r.contact_id_b, r.contact_id_a, r.contact_id_b) as donor_contact_id
                  FROM
                    (
                      -- Everyone related to NEW.contact_id
                      select DISTINCT
                        if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a) as related_contact_id
                      from
                        civicrm_relationship r
                      WHERE
                        NEW.contact_id IN (r.contact_id_a, r.contact_id_b)
                        AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                        AND r.is_active
                    ) t
                    INNER JOIN civicrm_relationship r ON t.related_contact_id in (r.contact_id_b, r.contact_id_a)
                      AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                      AND r.is_active
                  UNION ALL
                  -- Repeat one row for each person related to NEW.contact_id (we want to count them too)
                  SELECT DISTINCT
                    if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a), if(r.contact_id_a = NEW.contact_id, r.contact_id_b, r.contact_id_a)
                  from
                    civicrm_relationship r
                  WHERE
                    NEW.contact_id IN (r.contact_id_a, r.contact_id_b)
                    AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                    AND r.is_active
                ) donors
                LEFT JOIN civicrm_contribution ctrb
                  ON (
                    ctrb.contact_id = donors.donor_contact_id
                  )
                  and ctrb.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND ctrb.contribution_status_id = 1
              GROUP BY
                donors.related_contact_id
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_a, t.total
            FROM
            (
              SELECT
                coalesce(sum(total_amount), 0) as total
              FROM
              (
                select cont1.total_amount
                from
                  civicrm_relationship r
                  INNER JOIN civicrm_contribution cont1
                WHERE
                  r.is_active
                  AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                  AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND (
                    (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_a)
                    OR
                    (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_a)
                  )
                  AND cont1.contribution_status_id = 1
                UNION ALL
                SELECT
                  total_amount
                FROM
                  civicrm_contribution
                WHERE
                  contact_id = NEW.contact_id_a
                  AND financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND contribution_status_id = 1
              ) t
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
      ),
      array(
        'trigger_table' => 'civicrm_relationship',
        'trigger_sql' => '
          INSERT INTO %%jsumfields_custom_table_name (entity_id, %%jsumfields_custom_column_name)
            SELECT NEW.contact_id_b, t.total
            FROM
            (
              SELECT
                coalesce(sum(total_amount), 0) as total
              FROM
              (
                select cont1.total_amount
                from
                  civicrm_relationship r
                  INNER JOIN civicrm_contribution cont1
                WHERE
                  r.is_active
                  AND r.relationship_type_id in (%jsumfields_relatedcontrib_relationship_type_ids)
                  AND cont1.financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND (
                    (cont1.contact_id = r.contact_id_b AND r.contact_id_a = NEW.contact_id_b)
                    OR
                    (cont1.contact_id = r.contact_id_a AND r.contact_id_b = NEW.contact_id_b)
                  )
                  AND cont1.contribution_status_id = 1
                UNION ALL
                SELECT
                  total_amount
                FROM
                  civicrm_contribution
                WHERE
                  contact_id = NEW.contact_id_b
                  AND financial_type_id in (%jsumfields_relatedcontrib_financial_type_ids)
                  AND contribution_status_id = 1
              ) t
            ) t
          ON DUPLICATE KEY UPDATE %%jsumfields_custom_column_name = t.total;
        ',
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

  // Define a new optgroup fieldset, to contain our Related Contributions fields
  // and options.
  $custom['optgroups']['civimail'] = array(
    'title' => 'CiviMail Fields',
    'fieldset' => 'CiviMail',
    'component' => 'CiviMail',
  );

  // Define a new optgroup fieldset, to contain our Related Contributions fields
  // and options.
  $custom['optgroups']['civigrant'] = array(
    'title' => 'CiviGrant Fields',
    'fieldset' => 'CiviGrant',
    'component' => 'CiviGrant',
  );
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function jsumfields_civicrm_config(&$config) {
  _jsumfields_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function jsumfields_civicrm_xmlMenu(&$files) {
  _jsumfields_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function jsumfields_civicrm_install() {
  _jsumfields_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function jsumfields_civicrm_postInstall() {
  _jsumfields_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function jsumfields_civicrm_uninstall() {
  _jsumfields_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function jsumfields_civicrm_enable() {
  _jsumfields_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function jsumfields_civicrm_disable() {
  _jsumfields_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function jsumfields_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _jsumfields_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function jsumfields_civicrm_managed(&$entities) {
  _jsumfields_civix_civicrm_managed($entities);
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
function jsumfields_civicrm_caseTypes(&$caseTypes) {
  _jsumfields_civix_civicrm_caseTypes($caseTypes);
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
function jsumfields_civicrm_angularModules(&$angularModules) {
  _jsumfields_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function jsumfields_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _jsumfields_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
  function jsumfields_civicrm_preProcess($formName, &$form) {

  } // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
  function jsumfields_civicrm_navigationMenu(&$menu) {
  _jsumfields_civix_insert_navigation_menu($menu, NULL, array(
  'label' => ts('The Page', array('domain' => 'com.joineryhq.jsumfields')),
  'name' => 'the_page',
  'url' => 'civicrm/the-page',
  'permission' => 'access CiviReport,access CiviContribute',
  'operator' => 'OR',
  'separator' => 0,
  ));
  _jsumfields_civix_navigationMenu($menu);
  } // */

/**
 * Wrapper for ts() to save me some typing.
 * @param string $text The text to translate.
 * @param array $params Any replacement parameters.
 * @return string The translated string.
 */
function jsumfields_ts($text, $params = array()) {
  if (!array_key_exists('domain', $params)) {
    $params['domain'] = 'com.joineryhq.jsumfields';
  }
  return ts($text, $params);
}

/**
 * Implements hook_civicrm_triggerInfo().
 */
function jsumfields_civicrm_triggerInfo(&$info, $triggerTableName) {
  if (!CRM_Jsumfields_Upgrader::checkDependency('net.ourpowerbase.sumfields')) {
    // If sumfields is not enabled, don't define any of our own triggers, since
    // any custom fields they point at are now non-existent.
    return;
  }

  // If any enabled fields have 'jsumfields_extra' defined, formulate
  // a trigger for them and add to $info.
  // Our triggers all use custom fields. CiviCRM, when generating
  // custom fields, sometimes gives them different names (appending
  // the id in most cases) to avoid name collisions.
  //
  // So, we have to retrieve the actual name of each field that is in
  // use.
  $sumfieldsCustomTableName = _sumfields_get_custom_table_name();
  $custom_fields = _sumfields_get_custom_field_parameters();

  // Load the field and group definitions because we need the trigger
  // clause that is stored here.
  // Only get jsumfields definitions.
  $custom = array();
  jsumfields_civicrm_sumfields_definitions($custom);

  // We create a trigger sql statement for each table that should
  // have a trigger
  $tables = array();
  $generic_sql = "INSERT INTO `$sumfieldsCustomTableName` SET ";
  $sql_field_parts = array();

  $active_fields = sumfields_get_setting('active_fields', array());

  $session = CRM_Core_Session::singleton();
  $triggers = array();
  // Iterate over all our fields, and build out a sql parts array
  foreach ($custom_fields as $base_column_name => $params) {
    if (!in_array($base_column_name, $active_fields)) {
      continue;
    }

    // Set up variables to add triggers for jsumfields_extra.
    if (!empty($custom['fields'][$base_column_name]['jsumfields_extra'])) {
      foreach ($custom['fields'][$base_column_name]['jsumfields_extra'] as $extra) {

        $triggerSql = CRM_Utils_Array::value('trigger_sql', $extra);
        $customTriggerTableName = CRM_Utils_Array::value('trigger_table', $extra);

        if (empty($triggerSql) || empty($customTriggerTableName)) {
          // This extra trigger is not fully defined, so just skip it.
          continue;
        }

        if (empty($triggers[$customTriggerTableName])) {
          $triggers[$customTriggerTableName] = '';
        }

        if (!is_null($triggerTableName) && $triggerTableName != $customTriggerTableName) {
          // if triggerInfo is called with a particular table name, we should
          // only respond if we are contributing triggers to that table.
          continue;
        }

        $trigger = _jsumfields_sql_rewrite_with_custom_params($triggerSql, $params['column_name'], $sumfieldsCustomTableName);
        $trigger = sumfields_sql_rewrite(_jsumfields_sql_rewrite($trigger));

        // If we fail to properly rewrite the sql, don't set the trigger
        // to avoid sql exceptions.
        if (FALSE === $trigger) {
          $msg = sprintf(ts("Failed to rewrite sql for %s field."), $base_column_name);
          $session->setStatus($msg);
          continue;
        }

        $trigger = rtrim(rtrim($trigger), ';');
        $triggers[$customTriggerTableName] .= $trigger . ";\n";

      }
    }
  }

  foreach ($triggers as $customTriggerTableName => $sql) {
    // We want to fire this trigger on insert, update and delete.
    $info[] = array(
      'table' => $customTriggerTableName,
      'when' => 'AFTER',
      'event' => 'INSERT',
      'sql' => $sql,
    );
    $info[] = array(
      'table' => $customTriggerTableName,
      'when' => 'AFTER',
      'event' => 'UPDATE',
      'sql' => $sql,
    );
    // For delete, we reference OLD.field instead of NEW.field
    $sql = str_replace('NEW.', 'OLD.', $sql);
    $info[] = array(
      'table' => $customTriggerTableName,
      'when' => 'AFTER',
      'event' => 'DELETE',
      'sql' => $sql,
    );
  }

  foreach ($info as $id => $triggerInfo) {
    /*
     * This table exists as a dummy, for complicated reasons. See comments in
     * sql/auto_install. In short we need it as a dummy, but we don't actually
     * want any triggers on it. So remove any triggers that may have been
     * defined for it.
     */
    if ($triggerInfo['table'] == 'civicrm_jsumfields_placeholder') {
      unset($info[$id]);
    }
  }
}

/**
 * Get all available relationship types; a simple wrapper around the CiviCRM API.
 *
 * @return array Suitable for a select field.
 */
function _jsumfields_get_all_relationship_types() {
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
 * Get all available grant statuses; a simple wrapper around the CiviCRM API.
 *
 * @return array Suitable for a select field.
 */
function _jsumfields_get_all_grant_statuses() {
  $grantStatuses = array();
  $result = civicrm_api3('OptionValue', 'get', array(
    'sequential' => 1,
    'option_group_id' => "grant_status",
  ));
  foreach ($result['values'] as $value) {
    $grantStatuses[$value['value']] = $value['label'];
  }
  return $grantStatuses;
}

/**
 * Get all available grant types; a simple wrapper around the CiviCRM API.
 *
 * @return array Suitable for a select field.
 */
function _jsumfields_get_all_grant_types() {
  $grantStatuses = array();
  $result = civicrm_api3('OptionValue', 'get', array(
    'sequential' => 1,
    'option_group_id' => "grant_type",
  ));
  foreach ($result['values'] as $value) {
    $grantStatuses[$value['value']] = $value['label'];
  }
  return $grantStatuses;
}

/**
 * Replace jsumfields %variables with the appropriate values. NOTE: this function
 * does NOT call jsumfields_sql_rewrite().
 *
 * @return string Modified $sql.
 */
function _jsumfields_sql_rewrite($sql) {
  // Note: most of these token replacements fill in a sql IN statement,
  // e.g. field_name IN (%token). That means if the token is empty, we
  // get a SQL error. So... for each of these, if the token is empty,
  // we fill it with all possible values at the moment. If a new option
  // is added, summary fields will have to be re-configured.

  // Replace %jsumfields_relatedcontrib_relationship_type_ids
  $ids = sumfields_get_setting('jsumfields_relatedcontrib_relationship_type_ids', array());
  if (count($ids) == 0) {
    $ids = array_keys(_jsumfields_get_all_relationship_types());
  }
  $str_ids = implode(',', $ids);
  $sql = str_replace('%jsumfields_relatedcontrib_relationship_type_ids', $str_ids, $sql);

  // Replace %jsumfields_relatedcontrib_financial_type_ids
  $ids = sumfields_get_setting('jsumfields_relatedcontrib_financial_type_ids', array());
  if (count($ids) == 0) {
    $ids = array_keys(sumfields_get_all_financial_types());
  }
  $str_ids = implode(',', $ids);
  $sql = str_replace('%jsumfields_relatedcontrib_financial_type_ids', $str_ids, $sql);

  // Replace %jsumfields_grant_status_ids
  $ids = sumfields_get_setting('jsumfields_grant_status_ids', array());
  if (count($ids) == 0) {
    $ids = array_keys(_jsumfields_get_all_grant_statuses());
  }
  $str_ids = implode(',', $ids);
  $sql = str_replace('%jsumfields_grant_status_ids', $str_ids, $sql);

  // Replace %jsumfields_grant_type_ids
  $ids = sumfields_get_setting('jsumfields_grant_type_ids', array());
  if (count($ids) == 0) {
    $ids = array_keys(_jsumfields_get_all_grant_types());
  }
  $str_ids = implode(',', $ids);
  $sql = str_replace('%jsumfields_grant_type_ids', $str_ids, $sql);

  return $sql;
}

/**
 * Update our own trigger data, as needed (some jsumfields, such as the "Related
 * Contributions" group, aren't fully supported by sumfields, so we do the extra
 * work here).
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
function _jsumfields_generate_data_based_on_current_data($session = NULL) {
  // Get the actual table name for summary fields.
  $sumfieldsCustomTableName = _sumfields_get_custom_table_name();

  // These are the summary field definitions as they have been instantiated
  // on this site (with actual column names, etc.)
  $custom_fields = _sumfields_get_custom_field_parameters();

  if (is_null($session)) {
    $session = CRM_Core_Session::singleton();
  }
  if (empty($sumfieldsCustomTableName)) {
    $session::setStatus(ts("Your configuration may be corrupted. Please disable and renable this extension."), ts('Error'), 'error');
    return FALSE;
  }

  // Load the field and group definitions because we need the jsumfields_trigger_sql_*
  // properties that are stored here.
  // Only get jsumfields definitions.
  $custom = array();
  jsumfields_civicrm_sumfields_definitions($custom);

  $active_fields = sumfields_get_setting('active_fields', array());

  // Variables used for building the temp tables and temp insert statement.
  $temp_sql = array();

  foreach ($custom_fields as $base_column_name => $params) {
    if (
      // If the field is not enabled.
      !in_array($base_column_name, $active_fields)
      ||
      // The full update query is not defined.
      empty($custom['fields'][$base_column_name]['jsumfields_update_sql'])
    ) {
      continue;
    }

    $updateQuery = _jsumfields_sql_rewrite_with_custom_params($custom['fields'][$base_column_name]['jsumfields_update_sql'], $params['column_name'], $sumfieldsCustomTableName);
    $updateQuery = _jsumfields_sql_rewrite($updateQuery);
    $updateQuery = sumfields_sql_rewrite($updateQuery);

    if (FALSE === $updateQuery) {
      $msg = sprintf(ts("Failed to rewrite sql for %s field."), $base_column_name);
      $session->setStatus($msg);
      continue;
    }
    CRM_Core_DAO::executeQuery($updateQuery);
  }

  return TRUE;
}

/**
 * Replace custom table name and column name tokens with actual values in the
 * given SQL string.
 *
 * @return string Modified $sql.
 */
function _jsumfields_sql_rewrite_with_custom_params($sql, $columnName, $tableName) {
  $sql = str_replace('%%jsumfields_custom_table_name', $tableName, $sql);
  $sql = str_replace('%%jsumfields_custom_column_name', $columnName, $sql);
  return $sql;
}
