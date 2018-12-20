<?php
/**
 * Update notifier:
 * - adds notices to the menus and admin bar when an update is avialable
 * - creates an update page that contains update instructions, changelog and
 * option to automatically update the theme. The automatic theme update
 * uses the Envato Toolkit Library.
 * This class partially contains some code from the Joao Araujo's update
 * notifier : Profile: http://themeforest.net/user/unisphere
 */
class PexetoUpdateNotifier {

	protected $theme_name = '';
	protected $short_name = '';
	protected $xml_url = '';
	protected $cache_interval = 21600;
	protected $update_page_name = '';
	protected $options_url = '';
	protected $transient_id;
	protected $documentation_url;
	public $current_version = '1.0.0';
	const NONCE = 'pexeto_update_nonce';
	const UPDATE_ATTRIBUTE = 'pexeto_update';

	/**
	 * Main constructor.
	 *
	 * @param srting  $theme_name       the theme name
	 * @param srting  $short_name       short theme name
	 * @param srting  $xml_url          the URL of the XML file that will contain the latest version and changelog
	 * @param int     $cache_interval   cache interval in milliseconds - the time the XML data will be cached
	 * @param srting  $update_page_name the name of the update page
	 * @param string  $options_url      the URL of the theme options page
	 */
	function __construct( $theme_name, $short_name, $xml_url, $cache_interval, $update_page_name, $options_url, $documentation_url ) {
		$this->theme_name = $theme_name;
		$this->short_name = $short_name;
		$this->xml_url = $xml_url;
		$this->cache_interval = $cache_interval;
		$this->update_page_name = $update_page_name;
		$this->options_url = $options_url;
		$this->transient_id = $this->short_name.'_update_data';
		$this->documentation_url = $documentation_url;
	}

	/**
	 * Inits the main update functionality, adds the actions.
	 */
	public function init() {
		$theme_data = wp_get_theme($this->short_name);
		if(!$theme_data->Version){
			$theme_data = wp_get_theme();
		}
		$this->current_version = $theme_data->Version;

		//add the actions
		add_action( 'admin_menu', array( &$this, 'add_menu_noification' ) );
		add_action( 'admin_bar_menu', array( &$this, 'add_admin_bar_noification' ), 1000 );
	}

	/**
	 * Adds an update notification to the WordPress Dashboard menu.
	 */
	public function add_menu_noification() {
		if ( function_exists( 'simplexml_load_string' ) ) { // Stop if simplexml_load_string funtion isn't available
			if ( $this->is_new_version_available() ) {
				$count = ( isset( $_GET[self::UPDATE_ATTRIBUTE] ) &&  $_GET[self::UPDATE_ATTRIBUTE]=='true' )?'':'<span class="update-plugins count-1"><span class="update-count">1</span></span>';
				add_dashboard_page( $this->theme_name . ' Theme Updates', $this->theme_name . ' Update '.$count, 'administrator', $this->update_page_name, array( &$this, "print_update_page" ) );
			}
		}
	}

	/**
	 * Adds an update notification to the WordPress 3.1+ Admin Bar.
	 */
	public function add_admin_bar_noification() {
		if ( function_exists( 'simplexml_load_string' ) ) { // Stop if simplexml_load_string funtion isn't available
			global $wp_admin_bar, $wpdb;

			if ( !is_super_admin() || !is_admin_bar_showing() ) // Don't display notification in admin bar if it's disabled or the current user isn't an administrator
				return;

			if ( $this->is_new_version_available() ) { // Compare current theme version with the remote XML version
				$wp_admin_bar->add_menu( array( 'id' => 'pexeto_update_notifier', 'title' => '<span>' . $this->theme_name . ' <span id="ab-updates">New Updates</span></span>', 'href' => get_admin_url() . 'index.php?page='.$this->update_page_name ) );
			}
		}
	}

	/**
	 * Checks if a new version of the theme is available.
	 *
	 * @return boolean true if a new version is available and false if there isn't a new version available
	 */
	public function is_new_version_available() {
		if ( !isset( $this->new_available ) ) {
			$xml = $this->get_update_xml(); // Get the latest remote XML file on our server
			$this->new_available = $this->is_version_newer( $this->current_version, $xml->latest );
		}
		return $this->new_available;
	}

	/**
	 * Compares between two versions if one of them is newer.
	 *
	 * @param [type]  $current_ver the current version
	 * @param [type]  $latest_ver  the latest version
	 * @return boolean true if $latest_ver is newer than $current_ver and false in all other cases
	 */
	public function is_version_newer( $current_ver, $latest_ver ) {
		$latest = explode( '.', $latest_ver );
		$current= explode( '.', $current_ver );
		$new_available=false;
		for ( $i=0; $i<sizeof( $latest ); $i++ ) {
			if ( (int)$current[$i]<(int)$latest[$i] ) {
				$new_available=true;
				break;
			}elseif ( (int)$current[$i]>(int)$latest[$i] ) {
				$new_available=false;
				break;
			}
		}

		return $new_available;
	}

	/**
	 * Get the remote XML file contents and return its data (Version and Changelog).
	 * Uses the cached version if available and inside the time interval defined.
	 *
	 * @return XML object containing the parsed XML data
	 */
	public function get_update_xml() {
		if ( !isset( $this->xml ) ) {
			$cached_xml = get_transient( $this->transient_id );

			// check the cache
			if ( !$cached_xml ) {
				// cache doesn't exist, or is old, so refresh it

				$res = wp_remote_get( $this->xml_url );
				$cache_interval = $this->cache_interval;
				$notifier_data = '';

				$is_error = is_wp_error($res);
				if(!$is_error){
					$notifier_data = wp_remote_retrieve_body( $res );
				}

				if ($is_error || strpos( (string)$notifier_data, '<notifier>' ) === false ) {
					//the XML data could not be loaded, set the version back to 1.0 to prevent errors
					$notifier_data = '<?xml version="1.0" encoding="UTF-8"?><notifier><latest>1.0</latest><changelog></changelog></notifier>';
					$cache_interval = HOUR_IN_SECONDS; //set the transient to expire in an hour to try again
				}

				set_transient($this->transient_id, $notifier_data, $cache_interval);
			}else {
				// cache file is fresh enough, so read from it
				$notifier_data = $cached_xml;
			}

			// Load the remote XML data into a variable and return it
			$this->xml = simplexml_load_string( $notifier_data );
		}

		return $this->xml;
	}


	public function print_update_notification_message( $add_link = false ) {
		$xml = $this->get_update_xml();

		$message = '<div class="notice updated below-h2"><p><strong>'.wp_kses_post($xml->message)
			.'</strong> You have version '.$this->current_version.' installed. Please ';
		if ( $add_link ) {
			$message.= '<a href="'.admin_url( 'index.php?page='.$this->update_page_name ).'">';
		}
		$message .= 'update to version '. wp_kses_post($xml->latest);
		if ( $add_link ) {
			$message.= '</a>';
		}
		$message .='.</p></div>';
		echo $message;
	}


	/**
	 * Prints the update page containing instructions, update changelog and an option to update the theme automatically.
	 */
	public function print_update_page() {
		$xml = $this->get_update_xml();

		echo '<div class="wrap">
		<div id="icon-tools" class="icon32"></div>
		<h2 class="pexeto-updates-title">'.$this->theme_name.' Theme Updates</h2>';

		if ( !( isset( $_GET[self::UPDATE_ATTRIBUTE] ) &&  $_GET[self::UPDATE_ATTRIBUTE]=='true' ) ) {
			$this->print_update_notification_message();
			$this->print_instructions();
		}
		
		//print the changelog
		echo '<div class="icon32 icon32-posts-page" id="icon-edit-pages"><br></div>
		<h2 class="title" id="changes-title"><span class="pexeto-changes-icon dashicons dashicons-list-view"></span> Recent Update Changes</h2>
		<div id="changelog">'.wp_kses_post($xml->changelog).'</div></div>';

	}

	/**
	 * Prints the update instructions including the option links to update the theme automatically.
	 */
	protected function print_instructions() {
		$envato_market_state = '';
		
		if(function_exists('envato_market_github')){
			$envato_market_github = envato_market_github();
			if(method_exists($envato_market_github, 'state')){
				$envato_market_state = $envato_market_github->state();
			}
		}
		
		?>
	

		<div id="instructions">
			<div class="two-columns">
				<h3><span class="pexeto-update-icon dashicons dashicons-update"></span> Automatic Update Instructions</h3>
				
				
				You can use the <a href="https://github.com/envato/wp-envato-market">Envato Market Plugin</a> to install
				 updates of your ThemeForest and CodeCanyon items from your dashboard.
				 This is the new way to install updates from the dashboard since the retirement of the old Envato API.
				
				<ol>
				<?php 
					if($envato_market_state == 'deactivated'){
						$this->print_instructions_deactivated();
					}elseif($envato_market_state != 'activated'){
						$this->print_instructions_install_envato_market();
					}
					$this->print_instructions_after_activated();
				 ?>
				 </ol>
				
			</div>

			<div class="two-columns no-margin">
				<h3><span class="pexeto-update-icon dashicons dashicons-admin-generic"></span> Manual Update Instructions</h3>
				<p>You can find detailed instructions on how to install an update manually in the <a href="<?php echo $this->documentation_url; ?>">Updates</a> section of the documentation.</p>
				<p>You can download the documentation from the <a href="https://themeforest.net/downloads">Downloads</a> section of your ThemeForest profile or access it <a href="<?php echo $this->documentation_url; ?>">online</a>.
				</p>
			</div>
			<div class="clear"></div>
			<br>
			<div class="pexeto-update-notice notice-error"><p><b>If you have modified the theme's code: </b><i>your modifications will be lost when you install the update.
				Please create a backup of your code modifications and consider using a child theme instead of modifying the theme's code. More info <a href="http://pexetothemes.com/support/knowledgebase/updating-the-theme/">here.</a>
				 </i></p></div>
			<p>For more detailed instructions on installing the update, please refer to the <a href="<?php echo $this->documentation_url; ?>">Updates</a> section of the documentation.</p>
			<br />
		</div>
		
		<?php
	}
	
	protected function print_instructions_deactivated(){
		?>
		<li>Activate the <a href="<?php echo admin_url('plugins.php'); ?>">Envato Market Plugin</a></li>
		<?php
	}
	
	protected function print_instructions_install_envato_market(){
		$slug = 'envato-market';
		
		$install_url = add_query_arg( array(
			'action' => 'install-plugin',
			'plugin' => $slug,
		), self_admin_url( 'update.php' ) );

		$link = esc_url( wp_nonce_url( $install_url, 'install-plugin_' . $slug ) );
		?>
		<li><a href="<?php echo $link; ?>">Install the Envato Market Plugin</a></li>
		<?php 
	}
	
	protected function print_instructions_after_activated(){
		$em_page = admin_url('admin.php?page=envato-market');
		?>
		
		<li>Go to the <a href="<?php echo $em_page; ?>">Envato Market Page</a> of your admin dashboard.</li>
		<li>If you haven't setup your Enavto API token, follow the instructions on the page to add your token.</li>
		<li>Click on the Themes tab, find the <?php echo $this->theme_name; ?> theme in the list and click on the "Update Available" link to install the update.</li>
		
		<?php
	}

}
