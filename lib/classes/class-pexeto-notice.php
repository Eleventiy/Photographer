<?php


class PexetoNotice{
	
	public $kind = 'info';
	public $content = null;
	public $id = 0;
	
	public function __construct($kind, $content, $id){
		$this->kind = $kind;
		$this->content = $content;
		$this->id = $id;
		
		$this->init();
	}
	
	
	protected function init(){
		if($this->should_display()){
			add_action( 'admin_notices', array($this, 'print_notice') );
			add_action( 'admin_enqueue_scripts', array($this, 'enqueue_scripts') );
		}
	}
	
	protected function should_display(){
		$dismissed = get_option(self::dismissed_key($this->id), false);
		return !$dismissed;
	}
	
	public function print_notice(){
		
		echo sprintf('<div class="notice is-dismissible pexeto-notice %s" data-notice_id="%s"><p>%s</p></div>',
			$this->get_notice_class(), $this->id, $this->content);
	}
	
	protected function get_notice_class(){
		$types = array(
			'success' => 'notice-success',
			'info' => 'notice-warning',
			'alert' => 'notice-error' 
		);
		return isset($types[$this->kind]) ? $types[$this->kind] : 'notice-info';
	}
	
	/**
	 * Enqueue the script to mark the notices as dismissed.
	 */
	public function enqueue_scripts(){
		wp_enqueue_script( 'pexeto-notices', PEXETO_LIB_URL.'js/notices.js', 
			array( 'jquery' ), PEXETO_VERSION , true );
	}
	
	
	// STATIC CLASS METHDOS

	/**
	 * Create helper method that creates an instance. Use this static method for
	 * better code readability.
	 */
	public static function create($kind, $content, $id){
		return new PexetoNotice($kind, $content, $id);
	}
	
	public static function mark_as_dismissed(){
		if(isset($_GET['notice_id'])){
			update_option(self::dismissed_key($_GET['notice_id']), true);
		}
		exit;
	}
	
	protected static function dismissed_key($id){
		return PEXETO_SHORTNAME.'-notice-dismissed-'.$id;
	}
	
}

add_action( 'wp_ajax_pexeto_mark_notice_as_dismissed', array('PexetoNotice', 'mark_as_dismissed') );
