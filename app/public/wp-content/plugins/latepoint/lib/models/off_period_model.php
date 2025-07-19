<?php

class OsOffPeriodModel extends OsModel{
  var $id,
	$summary,
	$start_date,
	$end_date,
	$start_time,
	$end_time,
	$start_datetime_utc,
	$end_datetime_utc,
	$service_id,
	$agent_id,
	$location_id,
	  $server_timezone,
	$created_at,
	$updated_at;

  function __construct($id = false){
    parent::__construct();
    $this->table_name = LATEPOINT_TABLE_BLOCKED_PERIODS;
    $this->nice_names = array();

    if($id){
      $this->load_by_id($id);
    }
  }

	public function set_utc_datetimes(bool $save = false) {
		if ( empty( $this->start_date ) || empty( $this->end_date ) || empty( $this->start_time ) || empty( $this->end_time ) ) {
			return;
		}
		$this->start_datetime_utc = $this->get_start_datetime('UTC')->format(LATEPOINT_DATETIME_DB_FORMAT);
		$this->end_datetime_utc   = $this->get_end_datetime('UTC')->format(LATEPOINT_DATETIME_DB_FORMAT);
		if ( $save ) {
			$this->update_attributes(['start_datetime_utc' => $this->start_datetime_utc, 'end_datetime_utc' => $this->end_datetime_utc]);
		}
	}


	protected function before_save() {
	  $this->set_utc_datetimes();
	  $this->server_timezone = OsTimeHelper::get_wp_timezone();
	}

	public function get_start_datetime( string $set_timezone = 'UTC') : OsWpDateTime{
		try{
			// start_time and start_date is legacy stored in wordpress timezone
			$dateTime = new OsWpDateTime( $this->start_date . ' 00:00:00', OsTimeHelper::get_wp_timezone() );
			if($this->start_time > 0){
				$dateTime->modify( '+' . $this->start_time . ' minutes' );
			}
			if($set_timezone) $dateTime->setTimezone( new DateTimeZone( $set_timezone ) );
			return $dateTime;
		}catch(Exception $e){
			return new OsWpDateTime('now');
		}
	}

	public function get_end_datetime( string $set_timezone = 'UTC') : OsWpDateTime{
		try{
			// start_time and start_date is legacy stored in wordpress timezone
			$dateTime = new OsWpDateTime( $this->end_date . ' 00:00:00', OsTimeHelper::get_wp_timezone() );
			if($this->end_time > 0){
				$dateTime->modify( '+' . $this->end_time . ' minutes' );
			}
			if($set_timezone) $dateTime->setTimezone( new DateTimeZone( $set_timezone ) );
			return $dateTime;
		}catch(Exception $e){
			return new OsWpDateTime('now');
		}
	}


  protected function allowed_params($role = 'admin'){
    $allowed_params = array(  'id',
	    'summary',
'start_date',
'end_date',
'start_time',
'end_time',
'start_datetime_utc',
'end_datetime_utc',
'service_id',
'agent_id',
'location_id',
'server_timezone',
'created_at',
'updated_at');
    return $allowed_params;
  }

  protected function params_to_save($role = 'admin'){
    $params_to_save = array('id',
	    'summary',
'start_date',
'end_date',
'start_time',
'end_time',
'start_datetime_utc',
'end_datetime_utc',
'service_id',
'agent_id',
'location_id',
'server_timezone',
'created_at',
'updated_at');
    return $params_to_save;
  }



  protected function properties_to_validate(){
    $validations = array(
      'start_date' => array('presence'),
      'end_date' => array('presence')
    );
    return $validations;
  }

}