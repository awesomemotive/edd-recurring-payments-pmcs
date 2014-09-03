<?php
/*
Plugin Name: Easy Digital Downloads - Recurring Payments - Prevent Multiple Cart Subscriptions
Plugin URI: https://easydigitaldownloads.com/extensions/recurring-payments/?ref=166
Description: Prevent multiple subscriptions from being added to the cart
Version: 1.0
Author: Andrew Munro, Sumobi
Author URI: http://sumobi.com/
License: GPL-2.0+
License URI: http://www.opensource.org/licenses/gpl-license.php
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'EDD_Recurring_Payments_PMCS' ) ) {

	final class EDD_Recurring_Payments_PMCS {

		/**
		 * Holds the instance
		 *
		 * Ensures that only one instance of EDD_Recurring_Payments_PMCS exists in memory at any one
		 * time and it also prevents needing to define globals all over the place.
		 *
		 * TL;DR This is a static property property that holds the singleton instance.
		 *
		 * @var object
		 * @static
		 * @since 1.0
		 */
		private static $instance;

		/**
		 * Plugin Version
		 */
		private $version = '1.0';

		/**
		 * Plugin Title
		 */
		public $title = 'EDD Recurring Payments - Prevent Multiple Cart Subscriptions';


		/**
		 * Main Instance
		 *
		 * Ensures that only one instance exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @since 1.0
		 *
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof EDD_Recurring_Payments_PMCS ) ) {
				self::$instance = new EDD_Recurring_Payments_PMCS;
				self::$instance->setup_constants();
				self::$instance->hooks();
			}

			return self::$instance;
		}

		/**
		 * Constructor Function
		 *
		 * @since 1.0
		 * @access private
		 */
		private function __construct() {
			self::$instance = $this;
		}

		/**
		 * Reset the instance of the class
		 *
		 * @since 1.0
		 * @access public
		 * @static
		 */
		public static function reset() {
			self::$instance = null;
		}

		/**
		 * Setup plugin constants
		 *
		 * @access private
		 * @since 1.0
		 * @return void
		 */
		private function setup_constants() {

			// Plugin version
			if ( ! defined( 'EDDRP_PMCS_VERSION' ) ) {
				define( 'EDDRP_PMCS_VERSION', $this->version );
			}

		}

		/**
		 * Setup the default hooks and actions
		 *
		 * @since 1.0
		 *
		 * @return void
		 */
		private function hooks() {
			add_action( 'admin_init', array( $this, 'activation' ) );
			add_action( 'after_setup_theme', array( $this, 'load_textdomain' ) );
			add_action( 'wp_footer', array( $this, 'js' ) );
			add_action( 'edd_pre_add_to_cart', array( $this, 'pre_add_to_cart' ) );

			add_filter( 'plugin_row_meta', array( $this, 'plugin_meta' ), 10, 2 );
			add_filter( 'edd_purchase_link_defaults', array( $this, 'filter_purchase_link' ) );
			
			do_action( 'eddrp_pmcs_setup_actions' );
		}

		/**
		 * Activation function fires when the plugin is activated.
		 *
		 * This function is fired when the activation hook is called by WordPress,
		 * it flushes the rewrite rules and disables the plugin if EDD isn't active
		 * and throws an error.
		 *
		 * @since 1.0
		 * @access public
		 *
		 * @return void
		 */
		public function activation() {
			if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
				// is this plugin active?
				if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
					// deactivate the plugin
			 		deactivate_plugins( plugin_basename( __FILE__ ) );
			 		// unset activation notice
			 		unset( $_GET[ 'activate' ] );
			 		// display notice
			 		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
				}

			}
		}

		/**
		 * Admin notices
		 *
		 * @since 1.0
		*/
		public function admin_notices() {
			$edd_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/easy-digital-downloads/easy-digital-downloads.php', false, false );

			if ( ! is_plugin_active('easy-digital-downloads/easy-digital-downloads.php') ) {
				echo '<div class="error"><p>' . sprintf( __( 'You must install %sEasy Digital Downloads%s to use %s.', 'eddrp_pmcs' ), '<a href="http://easydigitaldownloads.com" title="Easy Digital Downloads" target="_blank">', '</a>', $this->title ) . '</p></div>';
			}

			if ( $edd_plugin_data['Version'] < '1.9' ) {
				echo '<div class="error"><p>' . sprintf( __( '%s requires Easy Digital Downloads Version 1.9 or greater. Please update Easy Digital Downloads.', 'eddrp_pmcs' ), $this->title ) . '</p></div>';
			}
		}

		/**
		 * Loads the plugin language files
		 *
		 * @access public
		 * @since 1.0
		 * @return void
		 */
		public function load_textdomain() {
			// Set filter for plugin's languages directory
			$lang_dir = dirname( plugin_basename( plugin_dir_path( __FILE__ ) ) ) . '/languages/';
			$lang_dir = apply_filters( 'edd_purchase_rewards_languages_directory', $lang_dir );

			// Traditional WordPress plugin locale filter
			$locale        = apply_filters( 'plugin_locale',  get_locale(), 'eddrp_pmcs' );
			$mofile        = sprintf( '%1$s-%2$s.mo', 'eddrp_pmcs', $locale );

			// Setup paths to current locale file
			$mofile_local  = $lang_dir . $mofile;
			$mofile_global = WP_LANG_DIR . '/eddrp-pmcs/' . $mofile;

			if ( file_exists( $mofile_global ) ) {
				// Look in global /wp-content/languages/edd-purchase-rewards folder
				load_textdomain( 'eddrp_pmcs', $mofile_global );
			} elseif ( file_exists( $mofile_local ) ) {
				// Look in local /wp-content/plugins/edd-purchase-rewards/languages/ folder
				load_textdomain( 'eddrp_pmcs', $mofile_local );
			} else {
				// Load the default language files
				load_plugin_textdomain( 'eddrp_pmcs', false, $lang_dir );
			}
		}

		/**
		 * Modify plugin metalinks
		 *
		 * @access      public
		 * @since       1.0.0
		 * @param       array $links The current links array
		 * @param       string $file A specific plugin table entry
		 * @return      array $links The modified links array
		 */
		public function plugin_meta( $links, $file ) {
		    if ( $file == plugin_basename( __FILE__ ) ) {
		        $plugins_link = array(
		            '<a title="'. __( 'View more plugins for Easy Digital Downloads by Sumobi', 'eddrp_pmcs' ) .'" href="https://easydigitaldownloads.com/blog/author/andrewmunro/?ref=166" target="_blank">' . __( 'Author\'s EDD plugins', 'eddrp_pmcs' ) . '</a>'
		        );

		        $links = array_merge( $links, $plugins_link );
		    }

		    return $links;
		}

		/**
		 * Filter the purchase link to include our new classes
		 *
		 * @param $args download arguments
		 * @since 1.0
		*/
		public function filter_purchase_link( $args ) {

			$download_id       = $args['download_id'];
			$cart_download_ids = $this->get_cart_ids();

			// is a recurring product
			if ( 'yes' === get_post_meta( $download_id, 'edd_recurring', true ) ) {
				
				// add a recurring CSS class to all downloads that are recurring
				$args['class'] = 'edd-submit recurring';

				if ( $cart_download_ids ) {
					// check cart to see if another recurring download exists
					foreach ( $cart_download_ids as $id ) {
						
						if ( 'yes' === get_post_meta( $id, 'edd_recurring', true ) && ! in_array( $download_id, $cart_download_ids ) ) {
							
							// adds a "disabled" class to all other recurring product buttons, except the one thats in the cart
							$args['class'] = 'edd-submit recurring disabled';

						}
					}
				}
			}

			return $args;
		}

		/**
		 * Get the download IDs that exist in the cart
		 * @return array download IDs
		 * @since  1.0
		 */
		public function get_cart_ids() {
			$cart_items        = edd_get_cart_contents();
			$cart_download_ids = $cart_items ? wp_list_pluck( $cart_items, 'id' ) : null;

			return $cart_download_ids;
		}

		public function notice() {
			return apply_filters( 'eddrp_pmcs_notice', __( 'Sorry, you can only purchase one subscription at a time. One already exists in the shopping cart.', 'eddrp_pmcs' ) );
		}

		/**
		 * Show customer a standard JS notice when they try to add another subscription
		 * 
		 * @since 1.0
		*/
		public function js() { ?>
			<script>
				jQuery(document).ready(function($) {

					// when recurring button is clicked, run some checks and prevent it from being added to cart
					$('.edd-submit.recurring').click(function(e) {

						// recurring product added to cart so add a disabled class to all other recurring buttons, and prevent them from being added to cart
						if ( ! $(this).hasClass('disabled') ) {
							$('.edd-submit.recurring').not(this).not('.edd_go_to_checkout').addClass('disabled').removeClass('edd-add-to-cart').find('.edd-loading').remove();
						}

						// when page is refreshed, this prevents them from being clicked and added to the cart.
						if ( $(this).hasClass('disabled') ) {
							e.preventDefault();

							// show a message to the user
							alert( "<?php echo $this->notice(); ?>" );
						}
					});

					// on page load, find all disabled recurring buttons and prevent the add to cart functionality
					$('.edd-submit.disabled.recurring').removeClass('edd-add-to-cart').find('.edd-loading').remove();

				});
			</script>
		<?php }

		/**
		 * Prevent recurring download from being added to cart with ?edd_action=add_to_cart&download_id=XXX
		 *
		 * @param int	$download_id Download Post ID
		 *
		 * @since 1.0
		 */
		public function pre_add_to_cart( $download_id ) {
			$cart_download_ids = $this->get_cart_ids();
			
			if ( $cart_download_ids ) {
				// check cart to see if another recurring download exists
				foreach ( $cart_download_ids as $id ) {
					if ( 'yes' === get_post_meta( $id, 'edd_recurring', true ) && ! in_array( $download_id, $cart_download_ids ) ) {
						wp_die( $this->notice(), '', array( 'back_link' => true ) );
					}
				}
			}
		}

	}
}

/**
 * Loads a single instance of EDD_Recurring_Payments_PMCS
 *
 * This follows the PHP singleton design pattern.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * @example <?php $eddrp_pmcs = eddrp_pmcs(); ?>
 *
 * @since 1.0
 *
 * @see EDD_Recurring_Payments_PMCS::get_instance()
 *
 * @return object Returns an instance of the EDD_Recurring_Payments_PMCS class
 */
function eddrp_pmcs() {
	return EDD_Recurring_Payments_PMCS::get_instance();
}

/**
 * Loads plugin after all the others have loaded and have registered their hooks and filters
 *
 * @since 1.0
*/
add_action( 'plugins_loaded', 'eddrp_pmcs', apply_filters( 'eddrp_pmcs_action_priority', 10 ) );