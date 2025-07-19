<?php
// legacy router, now it's moved to PRO, we had to leave this one be to not cause issues with third party addons
namespace LatePoint\Cerber;


if(!class_exists('LatePoint\Cerber\Router')){
	class Router{

	  public static function init_addon(){
		  if(class_exists('LatePoint\Cerber\RouterPro')) RouterPro::init_addon();
	  }

	}
}
