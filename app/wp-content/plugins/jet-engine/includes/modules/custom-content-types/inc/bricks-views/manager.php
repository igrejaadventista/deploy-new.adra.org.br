<?php

namespace Jet_Engine\Modules\Custom_Content_Types\Bricks_Views;

use Jet_Engine\Modules\Custom_Content_Types\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( '\Jet_Engine\Bricks_Views\Query_Controller' ) ) {
	require_once jet_engine()->plugin_path( 'includes/components/bricks-views/query-controller.php' );
}

class Manager {
	public function __construct() {
		if ( ! $this->has_bricks() ) {
			return;
		}

		add_action( 'init', array( $this, 'add_dynamic_data_provider' ), 10 );
		add_filter( 'bricks/query/loop_object_id', array( $this, 'set_loop_object_id' ), 10, 2 );
	}

	public function add_dynamic_data_provider() {
		require_once Module::instance()->module_path( 'bricks-views/dynamic-data/provider.php' );

		add_filter( 'jet-engine/bricks-views/dynamic_data/register_providers', array( $this, 'add_provider_to_list' ), 10, 2 );
	}

	public function add_provider_to_list( $providers ) {
		$providers['content-types'] = 'Jet_Engine\Modules\Custom_Content_Types\Bricks_Views\Dynamic_Data';

		return $providers;
	}

	/**
	 * Set loop object id for generating dynamic css in Listing grid
	 *
	 * @param int    $object_id The original object ID.
	 * @param object $object    The object being checked.
	 * @return int The determined loop object ID.
	 */
	public function set_loop_object_id( $object_id, $object ) {
		if ( isset( $object->cct_slug ) || isset( $object->_ID ) ) {
			return $object->_ID;
		}

		return $object_id;
	}

	public function has_bricks() {
		return ( defined( 'BRICKS_VERSION' ) && \Jet_Engine\Modules\Performance\Module::instance()->is_tweak_active( 'enable_bricks_views' ) );
	}
}