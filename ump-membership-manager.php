<?php
/**
 * Plugin Name: UMP Membership Manager
 * Plugin URI: https://github.com/gabrrrielll/ump-membership-manager
 * Description: Extensie pentru Indeed Ultimate Membership Pro care permite gestionarea userilor după membership și reguli automate de atribuire a membreships.
 * Version: 1.0.0
 * Author: Allmedia Creation
 * Author URI: https://allmediacreation.ro
 * Text Domain: ump-membership-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * 
 * @package UMP_Membership_Manager
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'UMP_MM_VERSION', '1.0.0' );
define( 'UMP_MM_PATH', plugin_dir_path( __FILE__ ) );
define( 'UMP_MM_URL', plugin_dir_url( __FILE__ ) );
define( 'UMP_MM_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if Indeed Ultimate Membership Pro is active
 */
function ump_mm_check_required_plugin() {
	if ( ! class_exists( 'Indeed\Ihc\Db\Memberships' ) ) {
		add_action( 'admin_notices', 'ump_mm_missing_plugin_notice' );
		return false;
	}
	return true;
}

/**
 * Display notice if required plugin is not active
 */
function ump_mm_missing_plugin_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'UMP Membership Manager necesită pluginul Indeed Ultimate Membership Pro să fie instalat și activat.', 'ump-membership-manager' ); ?></p>
	</div>
	<?php
}

/**
 * Main plugin class
 */
class UMP_Membership_Manager {
	
	/**
	 * Single instance of the class
	 */
	private static $instance = null;
	
	/**
	 * Get instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}
	
	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Check if required plugin is active
		add_action( 'plugins_loaded', array( $this, 'check_dependencies' ) );
		
		// Initialize plugin
		add_action( 'init', array( $this, 'init' ) );
		
		// Load text domain
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}
	
	/**
	 * Check dependencies
	 */
	public function check_dependencies() {
		if ( ! ump_mm_check_required_plugin() ) {
			deactivate_plugins( UMP_MM_BASENAME );
			return;
		}
		
		// Load plugin files
		$this->load_files();
		
		// Initialize admin
		if ( is_admin() ) {
			require_once UMP_MM_PATH . 'includes/class-admin.php';
			UMP_MM_Admin::get_instance();
		}
		
		// Initialize auto rules
		require_once UMP_MM_PATH . 'includes/class-auto-rules.php';
		UMP_MM_Auto_Rules::get_instance();
	}
	
	/**
	 * Load plugin files
	 */
	private function load_files() {
		require_once UMP_MM_PATH . 'includes/class-helper.php';
	}
	
	/**
	 * Initialize plugin
	 */
	public function init() {
		// Plugin initialization code
	}
	
	/**
	 * Load text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'ump-membership-manager',
			false,
			dirname( UMP_MM_BASENAME ) . '/languages'
		);
	}
}

/**
 * Initialize plugin
 */
function ump_mm_init() {
	return UMP_Membership_Manager::get_instance();
}

// Start the plugin
ump_mm_init();

