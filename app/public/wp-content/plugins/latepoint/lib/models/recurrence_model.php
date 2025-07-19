<?php

class OsRecurrenceModel extends OsModel{
  public $id,
      $rules,
      $overrides,
      $updated_at,
      $created_at;

  function __construct($id = false){
    parent::__construct();
    $this->table_name = LATEPOINT_TABLE_RECURRENCES;
    $this->nice_names = [];

    if($id){
      $this->load_by_id($id);
    }
  }

  public function get_rules() {
	  return json_decode( $this->rules, true );
  }

  public function get_overrides() {
	  return json_decode( $this->overrides, true );
  }

  protected function allowed_params($role = 'admin'){
    $allowed_params = array('id',
                            'rules',
                            'overrides');
    return $allowed_params;
  }


  protected function params_to_save($role = 'admin'){
    $params_to_save = array('id',
                            'rules',
                            'overrides');
    return $params_to_save;
  }


}