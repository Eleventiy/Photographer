<?php

class PexetoRecaptcha{
	
	protected static $initialized = false;
	
	public static function init(){
		if(!self::$initialized){
			add_action('wp_enqueue_scripts', array('PexetoRecaptcha', 'load_scripts'));
			add_action('admin_init', array('PexetoRecaptcha', 'show_notice'));
			self::$initialized = true;
		}
	}
	
	public static function is_enabled(){
		return get_opt('_captcha') == 'on' && self::are_api_keys_set();
	}
	
	public static function are_api_keys_set(){
		$keys = self::get_api_keys();
		if(empty($keys['site']) || empty($keys['secret'])){
			return false;
		}
		return true;
	}
	
	public static function get_api_keys(){
		return array(
			'site' => get_opt('_captcha_v2_site_key'),
			'secret' => get_opt('_captcha_v2_secret_key')
		);
	}
	
	public static function load_scripts(){
		if(is_page_template('template-contact.php') && self::is_enabled()){
			$url = 'https://www.google.com/recaptcha/api.js';
			$language = get_opt('_captcha_v2_language_code');
			if(!empty($language)){
				$url.='?hl='.$language;
			}
			
			wp_enqueue_script('pexeto_recaptcha', $url);
		}
	}
	
	/**
	 * Shows a notice to notify the users about the new version of captcha in case they
	 * are using captcha but don't have the v2 API keys enabled
	 */
	public static function show_notice(){
		if(get_opt('_captcha') == "on" && !self::are_api_keys_set()){
			$v1_public_key = get_opt('_captcha_public_key');
			if(!empty($v1_public_key)){
				$content = 'The contact form reCaptcha has been updated to version 2, which requires generating new API keys.
					Please go to <strong>'.PEXETO_THEMENAME.' Options &raquo; Page Settings 
					&raquo; Contact</strong> and enter your new reCaptcha version 2 API keys
					in order to continue using captcha in your contact form. 
					<br>For more information please refer to the <a href="http://pexetothemes.com/docs/photolux/#contact">Contact form section</a> of the documentation.';
			
				PexetoNotice::create('alert', $content, 'captchakeys');
				
			}
		}
	}
	
	public static function validate_response(){
		if (self::is_enabled() && !(isset($_POST['widget']) && $_POST['widget']=='true') && empty($_POST['g-recaptcha-response'])){
			return false;
		}
		return true;
	}

	
}

PexetoRecaptcha::init();