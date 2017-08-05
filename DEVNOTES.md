# Joinery's More Summary Fields
## Developer Notes

This extension's `hook_civicrm_sumfields_definitions()` implementation, 
`jsumfields_civicrm_sumfields_definitions()`, supports some additional parameters
in the $custom array which are used to extend the functionality of Summary Fields
(and work around some of its assumptions).  This allows us to:

* Create summary fields on CiviCRM entities that have no clear "contact_id" field,
  or on complex CiviCRM data beyond a single table, such as "Contributions by
  related contacts."
* Create multiple triggers that update a single Summary Field; this is useful
  when the Summary Field reflects data taken from several tables, such as 
  "Contributions by related contacts" or "Email open rate".

## Supported properties in $custom
`$custom`, the single parameter in a `hook_civicrm_sumfields_definitions()` 
implementation, expects the implementation to add to it array elements which are
themselves arrays representing additional Summary Fields and a standard set of 
properties for those fields. This extension adds more properties to those field
arrays; these properties are ignored by Summary Fields, but are used by this 
extension as follows:

* `jsumfields_extra` (Optional) An array of arrays, each one representing an 
  additional trigger to be created on this Summary Field. Each such array must 
  have the following properties:
  * `trigger_table` (Required) The name of the database table to which the 
    trigger will be associated.
  * `trigger_sql` (Required) A complete SQL query (typically, `INSERT ... ON 
    DUPLICATE KEY UPDATE ...`) to be run as part of the trigger. This string can
    include any of the variables described under **Supported query variables**,
    below.
* `jsumfields_update_sql` (Optional) A complete SQL query which should be run to
  initialize the Summary Field. This query will be run, in addition to a similar
  query which Summary Fields will create based on its supported `trigger_sql`
  property, at every invocation of the SumFields.GenData API, and when the 
  Summary Fields configuration form is submitted with the "When I submit this 
  form" option.

## Supported query variables
The `trigger_table` and `jsumfields_update_sql` properties described above may
include any of the usual %variables defined by Summary Fields (see 
`sumfields_sql_rewrite()`) and by this extension (see `_jsumfields_sql_rewrite()`). 
They may also contain the special variables `%%jsumfields_custom_table_name` and 
`%%jsumfields_custom_column_name`, which will be replaced with the name of the 
Summary Field custom table and of the column for that Summary Field, respectively.