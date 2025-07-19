<?php 

class OsLicenseHelper {

  public static function get_license_key(){
    $license_info = self::get_license_info();
    return $license_info['license_key'];
  }

  public static function clear_license(){
    OsSettingsHelper::save_setting_by_name('is_active_license', 'no');
    OsSettingsHelper::save_setting_by_name('license_status_message', '');
    OsSettingsHelper::save_setting_by_name('license', '');
  }

  public static function get_license_info(){
    $license_info = OsSettingsHelper::get_settings_value('license');
    $license = array('full_name' => '', 'email' => '', 'license_key' => '');

    if($license_info){
      $license_arr = explode('*|||*', $license_info);
      $license['full_name'] = isset($license_arr[0]) ? $license_arr[0] : '';
      $license['email'] = isset($license_arr[1]) ? $license_arr[1] : '';
      $license['license_key'] = isset($license_arr[2]) ? $license_arr[2] : '';
    }

    $license['is_active'] = OsSettingsHelper::get_settings_value('is_active_license', 'no');
    $license['status_message'] = OsSettingsHelper::get_settings_value('license_status_message', false);

    return $license;
  }

  public static function is_license_active(){
  	return (OsSettingsHelper::get_settings_value('is_active_license', 'no') == 'yes');
  }


}