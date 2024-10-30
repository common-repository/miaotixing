<?php
/*
  Plugin Name: Miaotixing
  Plugin URI: https://miaotixing.com/plugins/wordpress
  Description: 喵提醒，将博客动态发送提醒通知到手机上（微信，短信或语音电话方式）。
  Version: 1.0.0
  Author: 喵叔
 */

define('MIAOTIXING_VERSION', '1.0.0');
define('MIAOTIXING_URL', plugins_url('', __FILE__));
define('MIAOTIXING_PATH', dirname( __FILE__ ));

/**
 * 加载函数
 */
require_once(MIAOTIXING_PATH . '/functions.php');
miaotixing_load();

function miaotixing_settings_link($action_links,$plugin_file){
	if($plugin_file==plugin_basename(__FILE__)){
		$wcu_settings_link = '<a href="options-general.php?page=' . dirname(plugin_basename(__FILE__)) . '/miaotixing_admin.php">' . __('Settings') . '</a>';
		array_unshift($action_links,$wcu_settings_link);
	}
	return $action_links;
}
add_filter('plugin_action_links','miaotixing_settings_link',10,2);
if(is_admin()){require_once('miaotixing_admin.php');}