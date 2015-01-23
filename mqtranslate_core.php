<?php // encoding: utf-8

/*  Copyright 2008  Qian Qin  (email : mail@qianqin.de)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/* mqTranslate Core Functions */

function qtrans_init() {
	global $q_config;
	// check if it isn't already initialized
	if(defined('QTRANS_INIT')) return;
	define('QTRANS_INIT',true);
	// load configuration if not beeing reseted
	if (defined('WP_ADMIN') && current_user_can('manage_options') && isset($_POST['mqtranslate_reset']) && isset($_POST['mqtranslate_reset2'])) {
		// reset all settings
		delete_option('mqtranslate_language_names');
		delete_option('mqtranslate_enabled_languages');
		delete_option('mqtranslate_default_language');
		delete_option('mqtranslate_flag_location');
		delete_option('mqtranslate_flags');
		delete_option('mqtranslate_locales');
		delete_option('mqtranslate_na_messages');
		delete_option('mqtranslate_date_formats');
		delete_option('mqtranslate_time_formats');
		delete_option('mqtranslate_use_strftime');
		delete_option('mqtranslate_ignore_file_types');
		delete_option('mqtranslate_url_mode');
		delete_option('mqtranslate_detect_browser_language');
		delete_option('mqtranslate_hide_untranslated');
		delete_option('mqtranslate_show_displayed_language_prefix');
		delete_option('mqtranslate_auto_update_mo');
		delete_option('mqtranslate_next_update_mo');
		delete_option('mqtranslate_hide_default_language');
		delete_option('mqtranslate_ul_lang_protection');
		delete_option('mqtranslate_allowed_custom_post_types');
		delete_option('mqtranslate_disable_header_css');
		delete_option('mqtranslate_disable_client_cookies');
		delete_option('mqtranslate_use_secure_cookie');
		delete_option('mqtranslate_filter_all_options');
		if(isset($_POST['mqtranslate_reset3'])) {
			delete_option('mqtranslate_term_name');
		}
	}
	
	// Settings migration
	if (isset($_POST['mqtranslate_migration']) && 'none' !== $_POST['mqtranslate_migration'])
	{
		switch ($_POST['mqtranslate_migration'])
		{
			case 'import':
				mqtrans_import_settings_from_qtrans();
				break;
				
			case 'export':
				mqtrans_export_setting_to_qtrans(!empty($_POST['mqtranslate_export_migration_option']));
				break;
		}
	}
	
	qtrans_loadConfig();
	if(isset($_COOKIE['qtrans_cookie_test'])) {
		$q_config['cookie_enabled'] = true;
	} else  {
		$q_config['cookie_enabled'] = false;
	}
	
	// update Gettext Databases if on Backend
	if(defined('WP_ADMIN') && $q_config['auto_update_mo']) qtrans_updateGettextDatabases();
	
	// extract url information
	$q_config['url_info'] = qtrans_extractURL($_SERVER['REQUEST_URI'], $_SERVER["HTTP_HOST"], isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
	
	// set test cookie
	if (empty($q_config['disable_client_cookies']) || defined('WP_ADMIN'))
		setcookie('qtrans_cookie_test', 1, 0, $q_config['url_info']['home'], $q_config['url_info']['host_wo_port'], !empty($q_config['use_secure_cookie']));
	// check cookies for admin
	if(defined('WP_ADMIN')) {
		if(isset($_GET['lang']) && qtrans_isEnabled($_GET['lang'])) {
			qtrans_setLanguage($q_config['url_info']['language']);
			setcookie('qtrans_admin_language', $q_config['language'], time()+60*60*24*30, NULL, NULL, !empty($q_config['use_secure_cookie']));
		}
		elseif (isset($_COOKIE['qtrans_admin_language']) && qtrans_isEnabled($_COOKIE['qtrans_admin_language']))
			qtrans_setLanguage($_COOKIE['qtrans_admin_language']);
		else
			qtrans_setLanguage($q_config['default_language']);
	}
	else {
		qtrans_setLanguage($q_config['url_info']['language']);
		/*
		if (!isset($_COOKIE['qtrans_client_language']) || !qtrans_isEnabled($_COOKIE['qtrans_client_language'])
				|| (($q_config['url_info']['language'] != $q_config['default_language'] || $q_config['url_info']['explicit_default_language']) && $q_config['url_info']['language'] != $_COOKIE['qtrans_client_language'])) {
			qtrans_setLanguage($q_config['url_info']['language']);
			if (empty($q_config['disable_client_cookies']))
				setcookie('qtrans_client_language', $q_config['language'], time() + 86400 * 30, $q_config['url_info']['home'], $q_config['url_info']['host_wo_port'], !empty($q_config['use_secure_cookie']));
		}
		else
			qtrans_setLanguage($_COOKIE['qtrans_client_language']);
		*/
	}
	
	// detect language and forward if needed
	if($q_config['detect_browser_language'] && $q_config['url_info']['redirect'] && !isset($_COOKIE['qtrans_cookie_test']) && $q_config['url_info']['language'] == $q_config['default_language']) {
		$target = false;
		$prefered_languages = array();
		if(isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) && preg_match_all("#([^;,]+)(;[^,0-9]*([0-9\.]+)[^,]*)?#i",$_SERVER["HTTP_ACCEPT_LANGUAGE"], $matches, PREG_SET_ORDER)) {
			$priority = 1.0;
			foreach($matches as $match) {
				if(!isset($match[3])) {
					$pr = $priority;
					$priority -= 0.001;
				} else {
					$pr = floatval($match[3]);
				}
				$prefered_languages[$match[1]] = $pr;
			}
			arsort($prefered_languages, SORT_NUMERIC);
			foreach($prefered_languages as $language => $priority) {
				if(strlen($language)>2) $language = substr($language,0,2);
				if(qtrans_isEnabled($language)) {
					if($q_config['hide_default_language'] && $language == $q_config['default_language']) break;
					$target = qtrans_convertURL(qtrans_getHome(),$language);
					break;
				}
			}
		}
		$target = apply_filters("mqtranslate_language_detect_redirect", $target);
		if($target !== false) {
			wp_redirect($target);
			exit();
		}
	}
	
	// Filter all options for language tags
	if(!defined('WP_ADMIN') && !empty($q_config['filter_all_options'])) {
		$alloptions = wp_load_alloptions();
		foreach($alloptions as $option => $value) {
			add_filter('option_'.$option, 'qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage',0);
		}
	}
	
	// Disable CSS in head if applying
	if ($q_config['disable_header_css'])
		add_filter('mqtranslate_header_css', create_function('$a', "return '';"));
	
	// load plugin translations
	load_plugin_textdomain('mqtranslate', false, dirname(plugin_basename( __FILE__ )).'/lang');
	
	// fix url to prevent xss
	$q_config['url_info']['url'] = qtrans_convertURL(add_query_arg('lang',$q_config['default_language'],$q_config['url_info']['url']));
}

function qtrans_setLanguage($lang) {
	global $q_config;
	
	$q_config['language'] = apply_filters('mqtranslate_language', $lang);
}

// returns the home in HTTP or HTTPS depending on the request
function qtrans_getHome() {
       $home = get_option('home');
       if(is_ssl()) {
               $home = str_replace('http://', 'https://', $home);
       } else {
               $home = str_replace('https://', 'http://', $home);
       }
       return $home;
}

function qtrans_postInit() {
	// update definitions if neccesary
	if(defined('WP_ADMIN') && current_user_can('manage_categories')) qtrans_updateTermLibrary();
}

function qtrans_resolveLangCase($lang,&$caseredirect)
{
	if(qtrans_isEnabled($lang)) return $lang;
	$lng=strtolower($lang);
	if(qtrans_isEnabled($lng)){
		$caseredirect=true;
		return $lng;
	}
	$lng=strtoupper($lang);
	if(qtrans_isEnabled($lng)){
		$caseredirect=true;
		return $lng;
	}
	return false;
}

// returns cleaned string and language information
function qtrans_extractURL($url, $host = '', $referer = '') {
	global $q_config;
	$home = qtrans_parseURL(qtrans_getHome());
	$home['path'] = trailingslashit($home['path']);
	$referer = qtrans_parseURL($referer);
	
	$result = array();
	$result['language'] = $q_config['default_language'];
	$result['url'] = $url;
	$result['original_url'] = $url;
	$result['host'] = $host;
	$result['redirect'] = false;
	$result['internal_referer'] = false;
	$result['home'] = $home['path'];
	$result['explicit_default_language'] = false;
	$caseredirect = false;
	
	switch($q_config['url_mode']) {
		case QT_URL_QUERY:
			$result['explicit_default_language'] = (!empty($_GET['lang']) && $_GET['lang'] == $q_config['default_language']);
			break;
		case QT_URL_PATH:
			// pre url
			$url = substr($url, strlen($home['path']));
			if($url) {
				// might have language information
				if(preg_match("#^([a-z]{2})(/.*)?$#i",$url,$match)) {
					$lang = qtrans_resolveLangCase($match[1], $caseredirect);
					if ($lang) {
						// found language information
						$result['language'] = $lang;
						$result['explicit_default_language'] = ($lang == $q_config['default_language']);
						$result['url'] = $home['path'].substr($url, 3);
					}
				}
			}
			break;
		case QT_URL_DOMAIN:
			// pre domain
			if($host) {
				if(preg_match("#^([a-z]{2}).#i",$host,$match)) {
					$lang = qtrans_resolveLangCase($match[1], $caseredirect);
					if ($lang) {
						// found language information
						$result['language'] = $lang;
						$result['explicit_default_language'] = ($lang == $q_config['default_language']);
						$result['host'] = substr($host, 3);
					}
				}
			}
			break;
	}
	
	// check if referer is internal
	if($referer['host']==$result['host'] && qtrans_startsWith($referer['path'], $home['path'])) {
		// user coming from internal link
		$result['internal_referer'] = true;
	}
	
	if (isset($_GET['lang'])) {
		$lang = qtrans_resolveLangCase($_GET['lang'], $caseredirect);
		if ($lang) {
			// language override given
			$result['language'] = $lang;
			$result['url'] = preg_replace("#(&|\?)lang=".$lang."&?#i","$1",$result['url']);
			$result['url'] = preg_replace("#[\?\&]+$#i","",$result['url']);
		}
		elseif ($home['host'] == $result['host'] && $home['path'] == $result['url']) {
			if (empty($referer['host']) || !$q_config['hide_default_language'])
				$result['redirect'] = true;
			else {
				// check if activating language detection is possible
				if (preg_match("#^([a-z]{2}).#i",$referer['host'],$match)) {
					$lang = qtrans_resolveLangCase($match[1], $cs);
					if ($lang) {
						// found language information
						$referer['host'] = substr($referer['host'], 3);
					}
				}
				if (!$result['internal_referer']) {
					// user coming from external link
					$result['redirect'] = true;
				}
			}
		}
	}
	
	$result['host_wo_port'] = preg_replace('#:.+$#', '', $result['host']);
	
	if ($caseredirect) {
		$url = $result['host'].$result['url'];
		if (isset($_SERVER['HTTPS']))
			$url = 'https://'.$url;
		else
			$url = 'http://'.$url;
		$url = qtrans_convertURL($url, $lang, false, true);
		header('Location: '.$url);
		exit();
	}
	
	return $result;
}

function add_language_menu( $wp_admin_bar )
{
	global $q_config;
	if ( !is_admin() || !is_admin_bar_showing() )
		return;

	$wp_admin_bar->add_menu( array(
			'id'   => 'language',
			'parent' => 'top-secondary',
			'title' => $q_config['language_name'][$q_config['language']]
	) );

	foreach ($q_config['enabled_languages'] as $language)
	{
		$wp_admin_bar->add_menu( array(
			'id'		=> $language,
			'parent'	=> 'language',
			'title'		=> $q_config['language_name'][$language],
			'href'		=> add_query_arg('lang', $language)
		) );
	}
}

function qtrans_validateBool($var, $default) {
	if($var==='0') return false; elseif($var==='1') return true; else return $default;
}

// loads config via get_option and defaults to values set on top
function qtrans_loadConfig() {
	global $q_config;
	
	// Load everything
	$language_names = get_option('mqtranslate_language_names');
	$enabled_languages = get_option('mqtranslate_enabled_languages');
	$default_language = get_option('mqtranslate_default_language');
	$flag_location = get_option('mqtranslate_flag_location');
	$flags = get_option('mqtranslate_flags');
	$locales = get_option('mqtranslate_locales');
	$na_messages = get_option('mqtranslate_na_messages');
	$date_formats = get_option('mqtranslate_date_formats');
	$time_formats = get_option('mqtranslate_time_formats');
	$use_strftime = get_option('mqtranslate_use_strftime');
	$ignore_file_types = get_option('mqtranslate_ignore_file_types');
	$url_mode = get_option('mqtranslate_url_mode');
	$detect_browser_language = get_option('mqtranslate_detect_browser_language');
	$hide_untranslated = get_option('mqtranslate_hide_untranslated');
	$show_displayed_language_prefix = get_option('mqtranslate_show_displayed_language_prefix');
	$auto_update_mo = get_option('mqtranslate_auto_update_mo');
	$term_name = get_option('mqtranslate_term_name');
	$hide_default_language = get_option('mqtranslate_hide_default_language');
	$allowed_custom_post_types = get_option('mqtranslate_allowed_custom_post_types');
	$disable_header_css = get_option('mqtranslate_disable_header_css');
	$disable_client_cookies = get_option('mqtranslate_disable_client_cookies');
	$use_secure_cookie = get_option('mqtranslate_use_secure_cookie');
	$filter_all_options = get_option('mqtranslate_filter_all_options');
	
	// default if not set
	if(!is_array($date_formats)) $date_formats = $q_config['date_format'];
	if(!is_array($time_formats)) $time_formats = $q_config['time_format'];
	if(!is_array($na_messages)) $na_messages = $q_config['not_available'];
	if(!is_array($locales)) $locales = $q_config['locale'];
	if(!is_array($flags)) $flags = $q_config['flag'];
	if(!is_array($language_names)) $language_names = $q_config['language_name'];
	if(!is_array($enabled_languages)) $enabled_languages = $q_config['enabled_languages'];
	if(!is_array($term_name)) $term_name = $q_config['term_name'];
	if(empty($ignore_file_types)) $ignore_file_types = $q_config['ignore_file_types'];
	if(empty($default_language)) $default_language = $q_config['default_language'];
	if(empty($use_strftime)) $use_strftime = $q_config['use_strftime'];
	if(empty($url_mode)) $url_mode = $q_config['url_mode'];
	if(empty($allowed_custom_post_types))
	{
		if (is_array($q_config['allowed_custom_post_types']))
			$allowed_custom_post_types = $q_config['allowed_custom_post_types'];
		else
			$allowed_custom_post_types = array();
	}
	else if (!is_array($allowed_custom_post_types))
		$allowed_custom_post_types = explode(',', $allowed_custom_post_types); 
	if(!is_string($flag_location) || $flag_location==='') $flag_location = $q_config['flag_location'];
	$detect_browser_language = qtrans_validateBool($detect_browser_language, $q_config['detect_browser_language']);
	$hide_untranslated = qtrans_validateBool($hide_untranslated, $q_config['hide_untranslated']);
	$show_displayed_language_prefix = qtrans_validateBool($show_displayed_language_prefix, $q_config['show_displayed_language_prefix']);
	$auto_update_mo = qtrans_validateBool($auto_update_mo, $q_config['auto_update_mo']);
	$hide_default_language = qtrans_validateBool($hide_default_language, $q_config['hide_default_language']);
	$disable_header_css = qtrans_validateBool($disable_header_css, $q_config['disable_header_css']);
	$disable_client_cookies = qtrans_validateBool($disable_client_cookies, $q_config['disable_client_cookies']);
	$use_secure_cookie = qtrans_validateBool($use_secure_cookie, $q_config['use_secure_cookie']);
	$filter_all_options = qtrans_validateBool($filter_all_options, $q_config['filter_all_options']);
	
	// url fix for upgrading users
	$flag_location = trailingslashit(preg_replace('#^wp-content/#','',$flag_location));
	
	// check for invalid permalink/url mode combinations
	$permalink_structure = get_option('permalink_structure');
	if($permalink_structure===""||strpos($permalink_structure,'?')!==false||strpos($permalink_structure,'index.php')!==false) $url_mode = QT_URL_QUERY;
	
	// overwrite default values with loaded values
	$q_config['date_format'] = $date_formats;
	$q_config['time_format'] = $time_formats;
	$q_config['not_available'] = $na_messages;
	$q_config['locale'] = $locales;
	$q_config['flag'] = array_merge($q_config['flag'], $flags);
	$q_config['language_name'] = $language_names;
	$q_config['enabled_languages'] = $enabled_languages;
	$q_config['default_language'] = $default_language;
	$q_config['flag_location'] = $flag_location;
	$q_config['use_strftime'] = $use_strftime;
	$q_config['ignore_file_types'] = $ignore_file_types;
	$q_config['url_mode'] = $url_mode;
	$q_config['detect_browser_language'] = $detect_browser_language;
	$q_config['hide_untranslated'] = $hide_untranslated;
	$q_config['auto_update_mo'] = $auto_update_mo;
	$q_config['hide_default_language'] = $hide_default_language;
	$q_config['show_displayed_language_prefix'] = $show_displayed_language_prefix;
	$q_config['term_name'] = $term_name;
	$q_config['allowed_custom_post_types'] = $allowed_custom_post_types;
	$q_config['disable_header_css'] = $disable_header_css;
	$q_config['disable_client_cookies'] = $disable_client_cookies;
	$q_config['use_secure_cookie'] = $use_secure_cookie;
	$q_config['filter_all_options'] = $filter_all_options;
	
	do_action('mqtranslate_loadConfig');
}

// saves entire configuration
function qtrans_saveConfig() {
	global $q_config;
	
	// save everything
	update_option('mqtranslate_language_names', $q_config['language_name']);
	update_option('mqtranslate_enabled_languages', $q_config['enabled_languages']);
	update_option('mqtranslate_default_language', $q_config['default_language']);
	update_option('mqtranslate_flag_location', $q_config['flag_location']);
	update_option('mqtranslate_flags', $q_config['flag']);
	update_option('mqtranslate_locales', $q_config['locale']);
	update_option('mqtranslate_na_messages', $q_config['not_available']);
	update_option('mqtranslate_date_formats', $q_config['date_format']);
	update_option('mqtranslate_time_formats', $q_config['time_format']);
	update_option('mqtranslate_ignore_file_types', $q_config['ignore_file_types']);
	update_option('mqtranslate_url_mode', $q_config['url_mode']);
	update_option('mqtranslate_term_name', $q_config['term_name']);
	update_option('mqtranslate_use_strftime', $q_config['use_strftime']);
	if($q_config['detect_browser_language'])
		update_option('mqtranslate_detect_browser_language', '1');
	else
		update_option('mqtranslate_detect_browser_language', '0');
	if($q_config['hide_untranslated'])
		update_option('mqtranslate_hide_untranslated', '1');
	else
		update_option('mqtranslate_hide_untranslated', '0');
	if ($q_config['show_displayed_language_prefix'])
		update_option('mqtranslate_show_displayed_language_prefix', '1');
	else
		update_option('mqtranslate_show_displayed_language_prefix', '0');
	if($q_config['auto_update_mo'])
		update_option('mqtranslate_auto_update_mo', '1');
	else
		update_option('mqtranslate_auto_update_mo', '0');
	if($q_config['hide_default_language'])
		update_option('mqtranslate_hide_default_language', '1');
	else
		update_option('mqtranslate_hide_default_language', '0');
	
	update_option('mqtranslate_allowed_custom_post_types', implode(',', $q_config['allowed_custom_post_types']));
	update_option('mqtranslate_disable_header_css', $q_config['disable_header_css'] ? '1' : '0');
	update_option('mqtranslate_disable_client_cookies', $q_config['disable_client_cookies'] ? '1' : '0');
	update_option('mqtranslate_use_secure_cookie', $q_config['use_secure_cookie'] ? '1' : '0');
	update_option('mqtranslate_filter_all_options', $q_config['filter_all_options'] ? '1' : '0');
		
	do_action('mqtranslate_saveConfig');
}

function qtrans_updateGettextDatabases($force = false, $only_for_language = '') {
	global $q_config, $wp_version;
	if(!is_dir(WP_LANG_DIR)) {
		if(!@mkdir(WP_LANG_DIR))
			return false;
	}
	
	// Building major WP version
	$patterns = array('/(_|\-|\+)/', '/(\D+)/', '/\.{2,}/');
	$replacements = array('.', '.$1', '.');
	$wp = preg_replace($patterns, $replacements, $wp_version);
	$wp = array_slice(explode('.', $wp), 0, 2);
	$major_wp_version = implode('.', $wp);
	
	$next_update = get_option('mqtranslate_next_update_mo');
	if(time() < $next_update && !$force) return true;
	update_option('mqtranslate_next_update_mo', time() + 7*24*60*60);
	foreach($q_config['locale'] as $lang => $locale) {
		if(qtrans_isEnabled($only_for_language) && $lang != $only_for_language) continue;
		if(!qtrans_isEnabled($lang)) continue;
		if($ll = @fopen(trailingslashit(WP_LANG_DIR).$locale.'.mo.filepart','a')) {
			// can access .mo file
			fclose($ll);
			// try to find a .mo file
			if(!($locale == 'en_US' && $lcr = @fopen('http://www.qianqin.de/wp-content/languages/'.$locale.'.mo','r')))
			if(!$lcr = @fopen('http://svn.automattic.com/wordpress-i18n/'.$locale.'/tags/'.$wp_version.'/messages/'.$locale.'.mo','r'))
			if(!$lcr = @fopen('http://svn.automattic.com/wordpress-i18n/'.$locale.'/tags/'.$major_wp_version.'/messages/'.$locale.'.mo','r'))
			if(!$lcr = @fopen('http://svn.automattic.com/wordpress-i18n/'.substr($locale,0,2).'/tags/'.$wp_version.'/messages/'.$locale.'.mo','r'))
			if(!$lcr = @fopen('http://svn.automattic.com/wordpress-i18n/'.substr($locale,0,2).'/tags/'.$major_wp_version.'/messages/'.$locale.'.mo','r'))
			if(!$lcr = @fopen('http://svn.automattic.com/wordpress-i18n/'.$locale.'/branches/'.$wp_version.'/messages/'.$locale.'.mo','r'))
			if(!$lcr = @fopen('http://svn.automattic.com/wordpress-i18n/'.$locale.'/branches/'.$major_wp_version.'/messages/'.$locale.'.mo','r'))
			if(!$lcr = @fopen('http://svn.automattic.com/wordpress-i18n/'.substr($locale,0,2).'/branches/'.$wp_version.'/messages/'.$locale.'.mo','r'))
			if(!$lcr = @fopen('http://svn.automattic.com/wordpress-i18n/'.substr($locale,0,2).'/branches/'.$major_wp_version.'/messages/'.$locale.'.mo','r'))
			if(!$lcr = @fopen('http://svn.automattic.com/wordpress-i18n/'.$locale.'/branches/'.$wp_version.'/'.$locale.'.mo','r'))
			if(!$lcr = @fopen('http://svn.automattic.com/wordpress-i18n/'.$locale.'/branches/'.$major_wp_version.'/'.$locale.'.mo','r'))
			if(!$lcr = @fopen('http://svn.automattic.com/wordpress-i18n/'.substr($locale,0,2).'/branches/'.$wp_version.'/'.$locale.'.mo','r'))
			if(!$lcr = @fopen('http://svn.automattic.com/wordpress-i18n/'.substr($locale,0,2).'/branches/'.$major_wp_version.'/'.$locale.'.mo','r'))
			if(!$lcr = @fopen('http://svn.automattic.com/wordpress-i18n/'.$locale.'/trunk/messages/'.$locale.'.mo','r')) 
			if(!$lcr = @fopen('http://svn.automattic.com/wordpress-i18n/'.substr($locale,0,2).'/trunk/messages/'.$locale.'.mo','r')) {
				// couldn't find a .mo file
				if(filesize(trailingslashit(WP_LANG_DIR).$locale.'.mo.filepart')==0) unlink(trailingslashit(WP_LANG_DIR).$locale.'.mo.filepart');
				continue;
			}
			// found a .mo file, update local .mo
			$ll = fopen(trailingslashit(WP_LANG_DIR).$locale.'.mo.filepart','w');
			while(!feof($lcr)) {
				// try to get some more time
				@set_time_limit(30);
				$lc = fread($lcr, 8192);
				fwrite($ll,$lc);
			}
			fclose($lcr);
			fclose($ll);
			// only use completely download .mo files
			rename(trailingslashit(WP_LANG_DIR).$locale.'.mo.filepart',trailingslashit(WP_LANG_DIR).$locale.'.mo');
		}
	}
	return true;
}

function qtrans_updateTermLibrary() {
	global $q_config;
	if(!isset($_POST['action'])) return;
	switch($_POST['action']) {
		case 'editedtag':
		case 'addtag':
		case 'editedcat':
		case 'addcat':
		case 'add-cat':
		case 'add-tag':
		case 'add-link-cat':
			if(isset($_POST['qtrans_term_'.$q_config['default_language']]) && $_POST['qtrans_term_'.$q_config['default_language']]!='') {
				$default = htmlspecialchars(qtrans_stripSlashesIfNecessary($_POST['qtrans_term_'.$q_config['default_language']]), ENT_NOQUOTES);
				if(!isset($q_config['term_name'][$default]) || !is_array($q_config['term_name'][$default])) $q_config['term_name'][$default] = array();
				foreach($q_config['enabled_languages'] as $lang) {
					$_POST['qtrans_term_'.$lang] = qtrans_stripSlashesIfNecessary($_POST['qtrans_term_'.$lang]);
					if($_POST['qtrans_term_'.$lang]!='') {
						$q_config['term_name'][$default][$lang] = htmlspecialchars($_POST['qtrans_term_'.$lang], ENT_NOQUOTES);
					} else {
						$q_config['term_name'][$default][$lang] = $default;
					}
				}
				update_option('mqtranslate_term_name',$q_config['term_name']);
			}
		break;
	}
}

/* BEGIN DATE TIME FUNCTIONS */

function qtrans_strftime($format, $date, $default = '', $before = '', $after = '') {
	// don't do anything if format is not given
	if($format=='') return $default;
	// add date suffix ability (%q) to strftime
	$day = intval(ltrim(strftime("%d",$date),'0'));
	$search = array();
	$replace = array();
	
	// date S
	$search[] = '/(([^%])%q|^%q)/';
	if($day==1||$day==21||$day==31) { 
		$replace[] = '$2st';
	} elseif($day==2||$day==22) {
		$replace[] = '$2nd';
	} elseif($day==3||$day==23) {
		$replace[] = '$2rd';
	} else {
		$replace[] = '$2th';
	}
	
	$search[] = '/(([^%])%E|^%E)/'; $replace[] = '${2}'.$day; // date j
	$search[] = '/(([^%])%f|^%f)/'; $replace[] = '${2}'.date('w',$date); // date w
	$search[] = '/(([^%])%F|^%F)/'; $replace[] = '${2}'.date('z',$date); // date z
	$search[] = '/(([^%])%i|^%i)/'; $replace[] = '${2}'.date('n',$date); // date i
	$search[] = '/(([^%])%J|^%J)/'; $replace[] = '${2}'.date('t',$date); // date t
	$search[] = '/(([^%])%k|^%k)/'; $replace[] = '${2}'.date('L',$date); // date L
	$search[] = '/(([^%])%K|^%K)/'; $replace[] = '${2}'.date('B',$date); // date B
	$search[] = '/(([^%])%l|^%l)/'; $replace[] = '${2}'.date('g',$date); // date g
	$search[] = '/(([^%])%L|^%L)/'; $replace[] = '${2}'.date('G',$date); // date G
	$search[] = '/(([^%])%N|^%N)/'; $replace[] = '${2}'.date('u',$date); // date u
	$search[] = '/(([^%])%Q|^%Q)/'; $replace[] = '${2}'.date('e',$date); // date e
	$search[] = '/(([^%])%o|^%o)/'; $replace[] = '${2}'.date('I',$date); // date I
	$search[] = '/(([^%])%O|^%O)/'; $replace[] = '${2}'.date('O',$date); // date O
	$search[] = '/(([^%])%v|^%v)/'; $replace[] = '${2}'.date('T',$date); // date T
	$search[] = '/(([^%])%1|^%1)/'; $replace[] = '${2}'.date('Z',$date); // date Z
	$search[] = '/(([^%])%2|^%2)/'; $replace[] = '${2}'.date('c',$date); // date c
	$search[] = '/(([^%])%3|^%3)/'; $replace[] = '${2}'.date('r',$date); // date r
	$search[] = '/(([^%])%4|^%4)/'; $replace[] = '${2}'.date('P',$date); // date P
	$format = preg_replace($search,$replace,$format);
	return $before.strftime($format, $date).$after;
}

function qtrans_dateFromPostForCurrentLanguage($old_date, $format ='', $before = '', $after = '') {
	global $post, $q_config;
	$ts = mysql2date('U', $post->post_date);
	if ($format == 'U')
		return $ts;
	$format = qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage($format);
	if (!empty($format) && $q_config['use_strftime'] == QT_STRFTIME)
		$format = qtrans_convertDateFormatToStrftimeFormat($format);
	return qtrans_strftime(qtrans_convertDateFormat($format), $ts, $old_date, $before, $after);
}

function qtrans_dateModifiedFromPostForCurrentLanguage($old_date, $format ='') {
	global $post, $q_config;
	$ts = mysql2date('U', $post->post_modified);
	if ($format == 'U')
		return $ts;
	$format = qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage($format);
	if (!empty($format) && $q_config['use_strftime'] == QT_STRFTIME)
		$format = qtrans_convertDateFormatToStrftimeFormat($format);
	return qtrans_strftime(qtrans_convertDateFormat($format), $ts, $old_date);
}

function qtrans_timeFromPostForCurrentLanguage($old_date, $format = '', $post = null, $gmt = false) {
	global $q_config;
	$post = get_post($post);
	$post_date = $gmt? $post->post_date_gmt : $post->post_date;
	$ts = mysql2date('U',$post_date);
	if ($format == 'U')
		return $ts;
	$format = qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage($format);
	if (!empty($format) && $q_config['use_strftime'] == QT_STRFTIME)
		$format = qtrans_convertDateFormatToStrftimeFormat($format);
	return qtrans_strftime(qtrans_convertTimeFormat($format), $ts, $old_date);
}

function qtrans_timeModifiedFromPostForCurrentLanguage($old_date, $format = '', $gmt = false) {
	global $post, $q_config;
	$post_date = $gmt ? $post->post_modified_gmt : $post->post_modified;
	$ts = mysql2date('U',$post_date);
	if ($format == 'U')
		return $ts;
	$format = qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage($format);
	if (!empty($format) && $q_config['use_strftime'] == QT_STRFTIME)
		$format = qtrans_convertDateFormatToStrftimeFormat($format);
	return qtrans_strftime(qtrans_convertTimeFormat($format), $ts, $old_date);
}

function qtrans_dateFromCommentForCurrentLanguage($old_date, $format ='') {
	global $comment, $q_config;
	$ts = mysql2date('U',$comment->comment_date);
	if ($format == 'U')
		return $ts;
	$format = qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage($format);
	if (!empty($format) && $q_config['use_strftime'] == QT_STRFTIME)
		$format = qtrans_convertDateFormatToStrftimeFormat($format);
	return qtrans_strftime(qtrans_convertDateFormat($format), $ts, $old_date);
}

function qtrans_timeFromCommentForCurrentLanguage($old_date, $format = '', $gmt = false, $translate = true) {
	if(!$translate) return $old_date;
	global $comment, $q_config;
	$comment_date = $gmt? $comment->comment_date_gmt : $comment->comment_date;
	$ts = mysql2date('U',$comment_date);
	if ($format == 'U')
		return $ts;
	$format = qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage($format);
	if (!empty($format) && $q_config['use_strftime'] == QT_STRFTIME)
		$format = qtrans_convertDateFormatToStrftimeFormat($format);
	return qtrans_strftime(qtrans_convertTimeFormat($format), $ts, $old_date);
}

/* END DATE TIME FUNCTIONS */

function qtrans_useTermLib($obj) {
	global $q_config;
	if(is_array($obj)) {
		// handle arrays recursively
		foreach($obj as $key => $t) {
			$obj[$key] = qtrans_useTermLib($obj[$key]);
		}
		return $obj;
	}
	if(is_object($obj)) {
		// object conversion
		if(isset($q_config['term_name'][$obj->name][$q_config['language']])) {
			$obj->name = $q_config['term_name'][$obj->name][$q_config['language']];
		} 
	} elseif(isset($q_config['term_name'][$obj][$q_config['language']])) {
		$obj = $q_config['term_name'][$obj][$q_config['language']];
	}
	return $obj;
}

function qtrans_useAdminTermLib($obj) {
	if ($_SERVER["SCRIPT_NAME"]==="/wp-admin/edit-tags.php" && strstr($_SERVER["QUERY_STRING"], "action=edit" ))
		return $obj;
	else
		return qtrans_useTermLib($obj);
}

function qtrans_convertBlogInfoURL($url, $what) {
	if($what=='stylesheet_url') return $url;
	if($what=='template_url') return $url;
	if($what=='template_directory') return $url;
	if($what=='stylesheet_directory') return $url;
	return qtrans_convertURL($url);
}

function qtrans_convertURL($url='', $lang='', $forceadmin = false, $showDefaultLanguage = false) {
	global $q_config;
	
	// invalid language
	if($url=='') $url = esc_url($q_config['url_info']['url']);
	if($lang=='') $lang = $q_config['language'];
	if(defined('WP_ADMIN')&&!$forceadmin) return $url;
	if(!qtrans_isEnabled($lang)) return "";
	
	// & workaround
	$url = str_replace('&amp;','&',$url);
	$url = str_replace('&#038;','&',$url);
	
	// check for trailing slash
	$nottrailing = (strpos($url,'?')===false && strpos($url,'#')===false && substr($url,-1,1)!='/');
	
	// check if it's an external link
	$urlinfo = qtrans_parseURL($url);
	$home = rtrim(qtrans_getHome(),"/");
	if($urlinfo['host']!='') {
		// check for already existing pre-domain language information
		if($q_config['url_mode'] == QT_URL_DOMAIN && preg_match("#^([a-z]{2}).#i",$urlinfo['host'],$match)) {
			if(qtrans_isEnabled($match[1])) {
				// found language information, remove it
				$url = preg_replace("/".$match[1]."\./i","",$url, 1);
				// reparse url
				$urlinfo = qtrans_parseURL($url);
			}
		}
		if(substr($url,0,strlen($home))!=$home) {
			return $url;
		}
		// strip home path
		$url = substr($url,strlen($home));
		if ($url === false)
			$url = '';
	} else {
		// relative url, strip home path
		$homeinfo = qtrans_parseURL($home);
		if($homeinfo['path']==substr($url,0,strlen($homeinfo['path']))) {
			$url = substr($url,strlen($homeinfo['path']));
		}
	}
	
	// check for query language information and remove if found
	if (preg_match("#(&|\?)lang=([^&\#]+)#i",$url,$match) && qtrans_isEnabled($match[2]))
		$url = preg_replace("#(&|\?)lang=".$match[2]."&?#i","$1",$url);
	
	// remove any slashes out front
	$url = ltrim($url,"/");
	
	// remove any useless trailing characters
	$url = rtrim($url,"?&");
	
	// reparse url without home path
	$urlinfo = qtrans_parseURL($url);
	
	// check if its a link to an ignored file type
	$ignore_file_types = preg_split('/\s*,\s*/', strtolower($q_config['ignore_file_types']));
	$pathinfo = pathinfo($urlinfo['path']);
	if(isset($pathinfo['extension']) && in_array(strtolower($pathinfo['extension']), $ignore_file_types)) {
		return $home."/".$url;
	}
	
	// ignore wp internal links
	if(preg_match("#^(wp-login.php|wp-signup.php|wp-register.php|wp-admin/)#", $url)) {
		return $home."/".$url;
	}
	
	switch($q_config['url_mode']) {
		case QT_URL_PATH:	// pre url
			// might already have language information
			if(preg_match("#^([a-z]{2})/#i",$url,$_match)) {
				if(qtrans_isEnabled($_match[1])) {
					// found language information, remove it
					$url = substr($url, 3);
				}
			}
			if(!$q_config['hide_default_language']||$lang!=$q_config['default_language']||$showDefaultLanguage)
				$url = $lang."/".$url;
			break;
		case QT_URL_DOMAIN:	// pre domain
			// might already have language information
			if (preg_match('#//([a-z]{2})\.#i', $url, $_match)) {
				if (qtrans_isEnabled($_match[1]))
					$url = preg_replace("#//{$_match[1]}\.#i", '//', $url);
			} 
			if (!$q_config['hide_default_language']||$lang!=$q_config['default_language']||$showDefaultLanguage)
				$home = preg_replace("#//#","//{$lang}.",$home,1);
			break;
		default: // query
			// might already have language information
			if (preg_match('#(&|\?)lang=([a-zA-Z]{2})&?#', $url, $_match)) {
				if (qtrans_isEnabled($_match[1]))
					$url = preg_replace("#(&|/?)lang={$_match[1]}&?#", "$1", $url);
			}
			if(!$q_config['hide_default_language']||$lang!=$q_config['default_language']||$showDefaultLanguage){
				if (strpos($url,'?') === false)
					$url .= '?';
				else
					$url .= '&';
				$url .= "lang=".$lang;
			}
	}
	
	// see if cookies are activated
	if(!$q_config['cookie_enabled'] && !$q_config['url_info']['internal_referer'] && $urlinfo['path'] == '' && $lang == $q_config['default_language'] && $q_config['language'] != $q_config['default_language'] && $q_config['hide_default_language'] && !empty($match[2])) {
		// :( now we have to make unpretty URLs
		$url = preg_replace("#(&|\?)lang=[^&]+&?#i","$1",$url);
		if(strpos($url,'?')===false) {
			$url .= '?';
		} else {
			$url .= '&';
		}
		$url .= "lang=".$lang;
	}
	
	// &amp; workaround
	$complete = str_replace('&','&amp;',$home."/".$url);
	
	// remove trailing slash if there wasn't one to begin with
	if($nottrailing && strpos($complete,'?')===false && strpos($complete,'#')===false && substr($complete,-1,1)=='/')
		$complete = substr($complete,0,-1);
	
	return $complete;
}

// splits text with language tags into array
function qtrans_split($text, $quicktags = true, array &$languageMap = NULL) {
	global $q_config;
	
	//init vars
	$split_regex = "#(<!--[^-]*-->|\[:[a-z]{2}\])#ism";
	$current_language = "";
	$result = array();
	foreach($q_config['enabled_languages'] as $language)
		$result[$language] = "";
	
	// split text at all xml comments
	$blocks = preg_split($split_regex, $text, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);
	foreach ($blocks as $block) {
		# detect language tags
		if (preg_match("#^<!--:([a-z]{2})-->$#ismS", $block, $matches)) {
			if (qtrans_isEnabled($matches[1])) {
				$current_language = $matches[1];
				$languageMap[$current_language] = false;
			} else
				$current_language = "invalid";
			continue;
		// detect quicktags
		} elseif ($quicktags && preg_match("#^\[:([a-z]{2})\]$#ismS", $block, $matches)) {
			if (qtrans_isEnabled($matches[1])) {
				$current_language = $matches[1];
				$languageMap[$current_language] = true;
			}
			else
				$current_language = "invalid";
			continue;
		// detect ending tags
		} elseif ($block == '<!--:-->') {
			$current_language = "";
			continue;
		// detect defective more tag
		} elseif ($block == '<!--more-->') {
			foreach ($q_config['enabled_languages'] as $language)
				$result[$language] .= $block;
			continue;
		}
		
		// correctly categorize text block
		if ($current_language == "") {
			// general block, add to all languages
			foreach ($q_config['enabled_languages'] as $language)
				$result[$language] .= $block;
		} elseif($current_language != "invalid") {
			// specific block, only add to active language
			$result[$current_language] .= $block;
		}
	}
	
	foreach ($result as $lang => $lang_content)
		$result[$lang] = preg_replace("#(<!--more-->|<!--nextpage-->)+$#ismS","",$lang_content);
	
	return $result;
}

function qtrans_join($texts, array $tagTypeMap = array()) {
	global $q_config;
	if(!is_array($texts)) $texts = qtrans_split($texts, false);
	$split_regex = "#<!--more-->#ismS";
	$max = 0;
	$text = "";
	
	foreach($q_config['enabled_languages'] as $language) {
		if (!empty($texts[$language]))
		{
			$texts[$language] = preg_split($split_regex, $texts[$language]);
			if(sizeof($texts[$language]) > $max) $max = sizeof($texts[$language]);
		}
	}
	for($i=0;$i<$max;$i++) {
		if($i>=1) {
			$text .= '<!--more-->';
		}
		foreach($q_config['enabled_languages'] as $language) {
			if (isset($texts[$language][$i]) && $texts[$language][$i] !== '') {
				if (empty($tagTypeMap[$language]))
					$text .= '<!--:'.$language.'-->'.$texts[$language][$i].'<!--:-->';
				else
					$text .= "[:{$language}]{$texts[$language][$i]}";
			}
		}
	}
	return $text;
}

function qtrans_disableLanguage($lang) {
	global $q_config;
	if(qtrans_isEnabled($lang)) {
		$new_enabled = array();
		for($i = 0; $i < sizeof($q_config['enabled_languages']); $i++) {
			if($q_config['enabled_languages'][$i] != $lang) {
				$new_enabled[] = $q_config['enabled_languages'][$i];
			}
		}
		$q_config['enabled_languages'] = $new_enabled;
		return true;
	}
	return false;
}

function qtrans_enableLanguage($lang) {
	global $q_config;
	if(qtrans_isEnabled($lang) || !isset($q_config['language_name'][$lang])) {
		return false;
	}
	$q_config['enabled_languages'][] = $lang;
	// force update of .mo files
	if ($q_config['auto_update_mo']) qtrans_updateGettextDatabases(true, $lang);
	return true;
}

function qtrans_use($lang, $text, $show_available=false) {
	global $q_config;
	
	// return full string if language is not enabled
	if (!qtrans_isEnabled($lang) || (is_string($text) && !preg_match('/(<!--:[a-z]{2}-->|\[:[a-z]{2}\])/', $text))) 
		return $text;
	
	if (is_array($text)) {
		// handle arrays recursively
		foreach ($text as &$t)
			$t = qtrans_use($lang, $t, $show_available);
		return $text;
	}
	
	if (is_object($text) || $text instanceof __PHP_Incomplete_Class) {
		foreach ($text as &$t)
			$t = qtrans_use($lang, $t, $show_available);
		return $text;
	}
	
	// prevent filtering weird data types and save some resources
	if (!is_string($text) || $text == '')
		return $text;
	
	// get content
	$content = qtrans_split($text);
	// find available languages
	$available_languages = array();
	foreach ($content as $language => &$lang_text) {
		$lang_text = trim($lang_text);
		if (!empty($lang_text))
			$available_languages[] = $language;
	}
	unset($lang_text);
	
	// if no languages available show full text
	if (empty($available_languages))
		return $text;
	
	// if content is available show the content in the requested language
	if (!empty($content[$lang]))
		return $content[$lang];
	
	// content not available in requested language (bad!!) what now?
	if (!$show_available) { 
		// check if content is available in default language, if not return first language found. (prevent empty result)
		if ($lang != $q_config['default_language'] && !empty($content[$q_config['default_language']])) {
			$str = $content[$q_config['default_language']];
			if ($q_config['show_displayed_language_prefix'])
				$str = "(".$q_config['language_name'][$q_config['default_language']].") " . $str;
			return $str;
		}
		
		foreach ($content as $language => $lang_text) {
			if (!empty($lang_text)) {
				$str = $lang_text;
				if ($q_config['show_displayed_language_prefix'])
					$str = "(".$q_config['language_name'][$language].") " . $str;
				return $str;
			}
		}
	}
	
	// display selection for available languages
	$available_languages = array_unique($available_languages);
	$language_list = "";
	if (preg_match('/%LANG:([^:]*):([^%]*)%/S', $q_config['not_available'][$lang], $match)) {
		$normal_seperator = $match[1];
		$end_seperator = $match[2];
		// build available languages string backward
		foreach ($available_languages as $k => $language) {
			if ($k == 1)
				$language_list = $end_seperator.$language_list;
			else if ($k > 1)
				$language_list = $normal_seperator.$language_list;
			$language_list = "<a href=\"".qtrans_convertURL('', $language)."\">".$q_config['language_name'][$language]."</a>".$language_list;
		}
	}
	return "<p>".preg_replace('/%LANG:([^:]*):([^%]*)%/S', $language_list, $q_config['not_available'][$lang])."</p>";
}

function qtrans_showAllSeperated($text) {
	if(empty($text)) return $text;
	global $q_config;
	$result = "";
	foreach(qtrans_getSortedLanguages() as $language) {
		$result .= $q_config['language_name'][$language].":\n".qtrans_use($language, $text)."\n\n";
	}
	return $result;
}

function qtrans_add_js ()
{
	wp_enqueue_script( 'qtranslate-script', plugins_url( 'mqtranslate.js', __FILE__ ) );
}

function qtrans_add_css ()
{
	wp_enqueue_style( 'qtranslate-style', plugins_url( 'mqtranslate.css', __FILE__) );
}

function qtrans_add_config ()
{
	global $q_config;
?>
<script type="text/javascript">
/* <![CDATA[ */
var qTranslateConfig = <?php echo json_encode($q_config); ?>;       
/* ]]> */
</script>
<?php 
}
