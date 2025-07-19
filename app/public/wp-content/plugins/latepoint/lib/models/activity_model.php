<?php

class OsActivityModel extends OsModel{
  public $id,
      $agent_id,
      $order_id,
      $order_item_id,
      $booking_id,
      $service_id,
      $customer_id,
	  $coupon_id,
      $code,
      $description,
      $initiated_by,
      $initiated_by_id,
      $updated_at,
      $created_at;
      
      
      
      
      
  protected $codes;

  function __construct($id = false){
    parent::__construct();
    $this->table_name = LATEPOINT_TABLE_ACTIVITIES;
    $this->nice_names = array();

    $this->codes = $this->get_codes();

    if($id){
      $this->load_by_id($id);
    }
  }

	protected function get_codes(){
		return OsActivitiesHelper::get_codes();
	}

  public function get_link_to_object($label = false){
		$label = ($label) ? $label : esc_html__('View', 'latepoint');
    $href = '#';
		$attrs = '';
    switch($this->code){
      case 'agent_updated':
      case 'agent_created':
        $href = OsRouterHelper::build_link(OsRouterHelper::build_route_name('agents', 'edit_form'), array('id' => $this->agent_id) );
      break;
      case 'service_updated':
      case 'service_created':
        $href = OsRouterHelper::build_link(OsRouterHelper::build_route_name('services', 'edit_form'), array('id' => $this->service_id) );
      break;
	    default:
				$attrs = 'data-os-params="' . esc_attr(http_build_query(['id' => $this->id])) . '" 
							    data-os-action="' . esc_attr(OsRouterHelper::build_route_name( 'activities', 'view' )) . '" 
							    data-os-lightbox-classes="width-800"
							    data-os-after-call="latepoint_init_json_view"
							    data-os-output-target="side-panel"';
			break;
    }
		$link = '<a class="view-activity-link" href="'.esc_url($href).'" '.$attrs.'>'.$label.'</a>';
		$link = apply_filters('latepoint_activity_link_to_object', $link, $this, $label);
		return $link;
  }


	public function get_user_link_with_avatar() {
		return $this->get_user_link( true );
	}


  public function get_user_link($show_avatar = false){
    $link = '#';
    $name = 'n/a';
	$attrs = '';
    $avatar_url = LATEPOINT_DEFAULT_AVATAR_URL;

    switch($this->initiated_by){
      case 'wp_user':
      case LATEPOINT_USER_TYPE_ADMIN:
      case LATEPOINT_USER_TYPE_CUSTOM:
        $link = get_edit_user_link($this->initiated_by_id);
        $userdata = get_userdata($this->initiated_by_id);
        $name = $userdata->display_name;
        $avatar_url = get_avatar_url($this->initiated_by_id, array('size' => 200));
      break;
      case LATEPOINT_USER_TYPE_AGENT:
        $agent = new OsAgentModel($this->initiated_by_id);
        $link = OsRouterHelper::build_link(OsRouterHelper::build_route_name('agents', 'edit_form'), array('id' => $this->initiated_by_id) );
        $name = $agent->full_name;
        $avatar_url = $agent->get_avatar_url();
      break;
      case LATEPOINT_USER_TYPE_CUSTOMER:
        $customer = new OsCustomerModel($this->initiated_by_id);
        $attrs = OsCustomerHelper::quick_customer_btn_html($this->initiated_by_id);
        $name = $customer->full_name;
        $avatar_url = $customer->get_avatar_url();
      break;
	    default:
			return esc_html($this->initiated_by ?? 'n/a');
    }
	$avatar_url = esc_url($avatar_url);
	$name = esc_html($name);
	$link = esc_url($link);
	$avatar = $show_avatar ? "<span class='ula-avatar' style='background-image: url({$avatar_url})'></span>" : "";

    return "<a class='user-link-with-avatar' target='_blank' href='{$link}' {$attrs}>{$avatar}<span class='ula-name'>{$name}</span><span class='latepoint-icon latepoint-icon-external-link'></span></a>";
  }

  public function get_description() {
	  if ($this->code == 'sms_sent') {
		  $this->description = json_decode($this->description, true);
	  }

	  return $this->description;
  }


  protected function get_nice_created_at($include_time = true){
	$format = $include_time ? OsSettingsHelper::get_readable_date_format() . ' ' . OsSettingsHelper::get_readable_time_format() : OsSettingsHelper::get_readable_date_format();
	$utc_date = date_create_from_format( LATEPOINT_DATETIME_DB_FORMAT, $this->created_at );
	$wp_timezone_date = $utc_date->setTimezone(OsTimeHelper::get_wp_timezone());

	return date_format( $wp_timezone_date, $format );
  }


  protected function get_name(){
    if($this->code && isset($this->codes[$this->code])){
      return $this->codes[$this->code];
    }else{
      return $this->code;
    }
  }

  protected function params_to_save($role = 'admin'){
    $params_to_save = array('id', 
                            'agent_id',
      'order_id',
      'order_item_id',
                            'booking_id',
                            'service_id',
                            'customer_id',
							'coupon_id',
                            'code',
                            'description',
                            'initiated_by',
                            'initiated_by_id');
    return $params_to_save;
  }

  protected function allowed_params($role = 'admin'){
    $allowed_params = array('id', 
                            'agent_id',
                            'booking_id',
      'order_id',
      'order_item_id',
                            'service_id',
                            'customer_id',
	                        'coupon_id',
                            'code',
                            'description',
                            'initiated_by',
                            'initiated_by_id');
    return $allowed_params;
  }


  protected function properties_to_validate(){
    $validations = array(
      'code' => array('presence')
    );
    return $validations;
  }
}