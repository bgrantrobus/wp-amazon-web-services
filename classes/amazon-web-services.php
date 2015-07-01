<?php
use Aws\Common\Aws;
use Aws\Common\Exception\AwsExceptionInterface;
use Aws\Iam\Exception\IamException;
use Aws\Common\Exception\InstanceProfileCredentialsException;
use Doctrine\Common\Cache\FilesystemCache;
use Guzzle\Cache\DoctrineCacheAdapter;

class Amazon_Web_Services extends AWS_Plugin_Base {

	private $plugin_title, $plugin_menu_title, $plugin_permission, $client;
	private $errors;

	const SETTINGS_KEY = 'aws_settings';

	function __construct( $plugin_file_path ) {
		$this->plugin_slug = 'amazon-web-services';

		parent::__construct( $plugin_file_path );

		do_action( 'aws_init', $this );

		if ( is_admin() ) {
			do_action( 'aws_admin_init', $this );
		}

		if ( is_multisite() ) {
			add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );
			$this->plugin_permission = 'manage_network_options';
		} else {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			$this->plugin_permission = 'manage_options';
		}

		$this->plugin_title      = __( 'Amazon Web Services', 'amazon-web-services' );
		$this->plugin_menu_title = __( 'AWS', 'amazon-web-services' );

		if ( ! is_wp_error($this->errors) ) {
			$this->errors = new WP_Error();
		}

		load_plugin_textdomain( 'amazon-web-services', false, dirname( plugin_basename( $plugin_file_path ) ) . '/languages/' );
	}

	function get_error_message($code) {
		return $this->errors->get_error_message($code);
	}

	function get_error_data($code) {
		return $this->errors->get_error_data($code);
	}

	/**
	 * Add the AWS menu item and sub pages
	 */
	function admin_menu() {
		if ( version_compare( $GLOBALS['wp_version'], '3.8', '<' ) ) {
			$icon_url = plugins_url( 'assets/img/icon16.png', $this->plugin_file_path );
		} else {
			$icon_url = false;
		}

		$hook_suffixes = array();
		$hook_suffixes[] = add_menu_page( $this->plugin_title, $this->plugin_menu_title, $this->plugin_permission, $this->plugin_slug, array(
				$this,
				'render_page',
			), $icon_url );

		$title           = __( 'Addons', 'amazon-web-services' );
		$hook_suffixes[] = $this->add_page( $title, $title, $this->plugin_permission, 'aws-addons', array(
				$this,
				'render_page',
			) );

		global $submenu;
		if ( isset( $submenu[ $this->plugin_slug ][0][0] ) ) {
			$submenu[ $this->plugin_slug ][0][0] = __( 'Settings', 'amazon-web-services' );
		}

		do_action( 'aws_admin_menu', $this );

		foreach ( $hook_suffixes as $hook_suffix ) {
			add_action( 'load-' . $hook_suffix, array( $this, 'plugin_load' ) );
		}

		if ( $icon_url === false ) {
			add_action( 'admin_print_styles', array( $this, 'enqueue_menu_styles' ) );
		}
	}

	/**
	 * Add sub page to the AWS menu item
	 *
	 * @param        $page_title
	 * @param        $menu_title
	 * @param        $capability
	 * @param        $menu_slug
	 * @param string $function
	 *
	 * @return bool|string
	 */
	function add_page( $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
		return add_submenu_page( $this->plugin_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
	}

	/**
	 * Load styles for the AWS menu item
	 */
	function enqueue_menu_styles() {
		$version = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? time() : $this->plugin_version;
		$src     = plugins_url( 'assets/css/global.css', $this->plugin_file_path );
		wp_enqueue_style( 'aws-global-styles', $src, array(), $version );
	}

	/**
	 * Plugin loading enqueue scripts and styles
	 */
	function plugin_load() {
		$version = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? time() : $this->plugin_version;

		$src = plugins_url( 'assets/css/styles.css', $this->plugin_file_path );
		wp_enqueue_style( 'aws-styles', $src, array(), $version );

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		$src = plugins_url( 'assets/js/script' . $suffix . '.js', $this->plugin_file_path );
		wp_enqueue_script( 'aws-script', $src, array( 'jquery' ), $version, true );

		if ( isset( $_GET['page'] ) && 'aws-addons' == sanitize_key( $_GET['page'] ) ) { // input var okay
			add_filter( 'admin_body_class', array( $this, 'admin_plugin_body_class' ) );
			wp_enqueue_script( 'plugin-install' );
			add_thickbox();
		}

		$this->handle_post_request();

		do_action( 'aws_plugin_load', $this );
	}

	/**
	 * Process the saving of the settings form
	 */
	function handle_post_request() {
		if ( empty( $_POST['action'] )
				|| ( 'save' != sanitize_key( $_POST['action'] )
					&& 'test_credentials' != sanitize_key( $_POST['action'] ) ) ) { // input var okay
			return;
		}

		if ( empty( $_POST['_wpnonce'] )
				|| ( ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'aws-save-settings' )
					&& ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'aws-test-settings' ) ) ) { // input var okay
			die( __( "Cheatin' eh?", 'amazon-web-services' ) );
		}

		// Make sure $this->settings has been loaded
		$this->get_settings();

		// Save Changes
		if ( 'save' == sanitize_key( $_POST['action'] ) ) {
			$post_vars = array( 'access_key_id', 'secret_access_key', 'credentials_source' );
			foreach ( $post_vars as $var ) {
				if ( ! isset( $_POST[ $var ] ) ) { // input var okay
					continue;
				}

				$value = sanitize_text_field( $_POST[ $var ] ); // input var okay

				if ( 'secret_access_key' == $var && '-- not shown --' == $value ) {
					continue;
				}

				$this->set_setting( $var, $value );
			}

			$this->save_settings();
		}
		// Test Credentials
		else if ( 'test_credentials' == sanitize_key( $_POST['action'] ) ) {
			try {
				$response = $this->get_client()->get( 'iam' )->getUser( );

				// Success - Authenticated and received User response; get ARN from response.
				$auth_user = __( '[UNKNOWN]', 'amazon-web-services' );
				if ( $response && $response['User'] && $response['User']['Arn'] ) {
					$auth_user = $response['User']['Arn'];
				}

				// FIXME - This is not an error; there MUST be a better way to do this...
				$this->errors->add( 'test_credentials_success', $auth_user );
			}
			// Tried to get credentials from environment or instance metadata, but failed...
			catch ( InstanceProfileCredentialsException $ex ) {
				$msg = 'Failed to obtain AWS credentials from environment.';
				$this->errors->add( 'test_credentials_failed', $msg, $ex );
				return;
			}
			catch ( IamException $ex ) {
				// Authenticated, but not authorized to perform IAM.getUser().
				if ( 'AccessDenied' == $ex->getExceptionCode() ) {
					// Luckily, the error message contains the ARN!
					preg_match( '/^User: (.*) is not authorized to perform.*$/', $ex->getMessage(), $match );
					$auth_user = __( '[UNKNOWN]', 'amazon-web-services' );
					if ( 2 <= count( $match ) ) {
						$auth_user = $match[1];
					}

					// FIXME - This is not an error; there MUST be a better way to do this...
					$this->errors->add( 'test_credentials_success', $auth_user );
				}
				// All other IamException cases are failures.
				else {
					$msg = __( 'Failed to connect to AWS with configured credentials.', 'amazon-web-services' );
					$this->errors->add( 'test_credentials_failed', $msg, $ex );
				}
				return;
			}
			// Everything else is a failure.
			catch ( AwsExceptionInterface $ex ) {
				$msg = __( 'Failed to connect to AWS with configured credentials.', 'amazon-web-services' );
				$this->errors->add( 'test_credentials_failed', $msg, $ex );
				return;
			}
		}
	}

	/**
	 * Adds a class to admin page to style thickbox the same as the plugin directory pages.
	 *
	 * @param $classes
	 *
	 * @return string
	 */
	function admin_plugin_body_class( $classes ) {
		$classes .= 'plugin-install-php';

		return $classes;
	}

	/**
	 * Render the output of a page
	 */
	function render_page() {
		if ( empty( $_GET['page'] ) ) { // input var okay
			// Not sure why we'd ever end up here, but just in case
			wp_die( 'What the heck are we doin here?' );
		}
		$view = 'settings';
		if ( preg_match( '@^aws-(.*)$@', $_GET['page'], $matches ) ) {
			$allowed = array( 'addons' );
			if ( in_array( $matches[1], $allowed ) ) {
				$view = $matches[1];
			}
		}

		$this->render_view( 'header' );
		$this->render_view( $view );
		$this->render_view( 'footer' );
	}

	/**
	 * Check if settings for the AWS access credentials are defined in the database
	 *
	 * @return bool
	 */
	function are_db_keys_set() {
		$key_id = $this->get_db_access_key_id();
		$secret_key = $this->get_db_secret_access_key();

		return ! empty( $key_id ) && ! empty( $secret_key );
	}

	/**
	 * Get the AWS key from the settings
	 *
	 * @return string
	 */
	function get_db_access_key_id() {
		return $this->get_setting( 'access_key_id' );
	}

	/**
	 * Get the AWS secret from the settings
	 *
	 * @return string
	 */
	function get_db_secret_access_key() {
		return $this->get_setting( 'secret_access_key' );
	}

	/**
	 * Check if constants for the AWS access credentials are defined
	 *
	 * @return bool
	 */
	function are_key_constants_set() {
		return defined( 'AWS_ACCESS_KEY_ID' ) && defined( 'AWS_SECRET_ACCESS_KEY' );
	}

	/**
	 * Get the AWS key from a constant or the settings
	 *
	 * @return string
	 */
	function get_access_key_id() {
		if ( $this->are_key_constants_set() ) {
			return AWS_ACCESS_KEY_ID;
		}

		return $this->get_db_access_key_id();
	}

	/**
	 * Get the AWS secret from a constant or the settings
	 *
	 * @return string
	 */
	function get_secret_access_key() {
		if ( $this->are_key_constants_set() ) {
			return AWS_SECRET_ACCESS_KEY;
		}

		return $this->get_db_secret_access_key();
	}

	/**
	 * Get the source of AWS credentials
	 *
	 * @return string
	 */
	function get_credentials_source() {
		return $this->get_setting( 'credentials_source' );
	}

	/**
	 * Instantiate a new AWS service client for the AWS SDK
	 * using the defined AWS key and secret
	 *
	 * @return Aws|WP_Error
	 */
	function get_client() {
		if ( is_null( $this->client ) ) {

			$args = array( );

			$credentials_source = $this->get_credentials_source();
			if ( empty( $credentials_source ) ) {
				// NOOP - AWS PHP SDK will handle everything for us!
			}
			else if ( 'aws-instance-roles' == $credentials_source ) {
				// FIXME -- There must be a better way to define this...
				$cache_dir = '/tmp/cache';

				$fs_cache = new FilesystemCache($cache_dir);
				$cache_adapter = new DoctrineCacheAdapter($fs_cache);
				$args['credentials.cache'] = $cache_adapter;
			}
			else if ( 'wp-config' == $credentials_source ) {
				$args['key'] = AWS_ACCESS_KEY_ID;
				$args['secret'] = AWS_SECRET_ACCESS_KEY;
			}
			else if ( 'wp-database' == $credentials_source ) {
				$args['key'] = $this->get_db_access_key_id();
				$args['secret'] = $this->get_db_secret_access_key();
			}
			else {
				die( sprintf( _( 'Unsupported AWS credentials source: %s',
								'amazon-web-services' ),
							$credentials_source ) );
			}

			$args         = apply_filters( 'aws_get_client_args', $args );
			$this->client = Aws::factory( $args );
		}

		return $this->client;
	}

	/*
	function get_tabs() {
		$tabs = array( 'addons' => 'Addons', 'settings' => 'Settings', 'about' => 'About' );
		return apply_filters( 'aws_get_tabs', $tabs, $this );
	}

	function get_active_tab() {
		if ( isset( $_GET['tab'] ) ) {
			$tab = $_GET['tab'];
			$tabs = $this->get_tabs();
			if ( isset( $tabs[$tab] ) ) {
				return $tab;
			}
		}

		if ( !$this->get_access_key_id() ) {
			return 'settings';
		}

		return 'addons'; // Default
	}
	*/

	/**
	 * Get a nonced, network safe install URL for a plugin
	 *
	 * @param $slug Plugin slug
	 *
	 * @return string
	 */
	function get_plugin_install_url( $slug ) {
		return wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . $slug ), 'install-plugin_' . $slug );
	}
}
