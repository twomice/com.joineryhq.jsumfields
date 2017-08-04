<?php

class CRM_Msumfields_APIWrapperSumfieldsGendata {
  /**
   * the wrapper contains a method that allows you to alter the parameters of the api request (including the action and the entity)
   */
  public function fromApiInput($apiRequest) {
    return $apiRequest;
  }

  /**
   * alter the result before returning it to the caller.
   */
  public function toApiOutput($apiRequest, $result) {
    $status = (_msumfields_generate_data_based_on_current_data() ? 'TRUE' : 'FALSE');
    $result['values'][0] .= "; Update for com.joineryhq.msumfields returned: {$status}.";
    return $result;
  }
  
}
