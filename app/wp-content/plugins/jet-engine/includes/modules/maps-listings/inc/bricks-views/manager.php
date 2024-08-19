<?php
namespace Jet_Engine\Modules\Maps_Listings\Bricks_Views;

use Bricks\Helpers;
use Jet_Engine\Modules\Maps_Listings\Preview_Trait;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Manager {

	use Preview_Trait;

	/**
	 * Elementor Frontend instance
	 *
	 * @var null
	 */
	public $frontend = null;

	/**
	 * Constructor for the class
	 */
	function __construct() {
		add_action( 'jet-engine/bricks-views/init', array( $this, 'init' ), 10 );
		add_action( 'jet-engine/bricks-views/listing/before-css-generation', array( $this, 'set_wp_doing_ajax' ), 10 );
		add_action( 'jet-engine/bricks-views/listing/after-css-generation', array( $this, 'reset_wp_doing_ajax' ), 10 );
	}

	public function init() {
		add_action( 'jet-engine/bricks-views/register-elements', array( $this, 'register_elements' ), 11 );

		if ( bricks_is_builder() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'preview_scripts' ) );
		}

		add_action( 'jet-engine/maps-listings/get-map-marker', array( $this, 'setup_bricks_query' ), 10, 3 );
	}

	public function register_elements() {
		\Bricks\Elements::register_element( $this->module_path( 'maps-listings.php' ) );
	}

	public function module_path( $relative_path = '' ) {
		return jet_engine()->plugin_path( 'includes/modules/maps-listings/inc/bricks-views/' . $relative_path );
	}

	public function setup_bricks_query( $listing_id, $page_id, $element_id ) {
		$settings = [];

		if ( $page_id && $element_id ) {
			$settings        = Helpers::get_element_settings( $page_id, $element_id );
			$settings['_id'] = $element_id;
		}

		jet_engine()->bricks_views->listing->render->set_bricks_query( $listing_id, $settings );
	}

	public function set_wp_doing_ajax() {
		if ( defined( 'REST_REQUEST' ) ) {
			add_filter( 'wp_doing_ajax', '__return_true');
		}
	}

	public function reset_wp_doing_ajax() {
		if ( defined( 'REST_REQUEST' ) ) {
			remove_filter( 'wp_doing_ajax', '__return_true');
		}
	}
}