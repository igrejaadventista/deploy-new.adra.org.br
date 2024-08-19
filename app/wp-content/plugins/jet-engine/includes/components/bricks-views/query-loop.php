<?php

namespace Jet_Engine\Bricks_Views;

use Bricks\Api;
use Bricks\Query;
use Bricks\Database;
use Jet_Engine\Bricks_Views\Helpers\Options_Converter;

class Query_Loop {
	public $innitial_object = null;

	function __construct() {
		add_action( 'init', array( $this, 'add_control_to_elements' ), 40 );
		add_filter( 'bricks/setup/control_options', array( $this, 'setup_query_controls' ) );
		add_filter( 'bricks/element/settings', array( $this, 'add_render_filter_for_bricks_element' ), 10, 2 );
		add_filter( 'jet-engine/listings/data/the-post/is-main-query', array( $this, 'maybe_modify_is_main_query' ) );

		add_action( 'bricks/query/before_loop', array( $this, 'set_initial_object_before_render' ), 10 );
		add_action( 'bricks/query/after_loop', array( $this, 'reset_object_after_render' ), 10 );

		add_action( 'bricks/frontend/before_render_data', array( $this, 'set_object_before_popup_rendering' ), 10, 2 );
		add_action( 'bricks/frontend/after_render_data', array( $this, 'reset_object_after_popup_rendering' ), 10, 2);
	}

	public function setup_query_controls( $control_options ) {
		// Add a new query loop type
		$control_options['queryTypes']['jet_engine_query_builder'] = esc_html__( 'JetEngine Query Builder', 'jet-engine' );

		return $control_options;
	}

	public function add_control_to_elements() {
		// Only container, block and div element have query controls
		$elements = [ 'container', 'block', 'div' ];

		foreach ( $elements as $name ) {
			add_filter( "bricks/elements/{$name}/controls", [ $this, 'add_jet_engine_controls' ], 40 );
		}
	}

	public function add_jet_engine_controls( $controls ) {
		$options = \Jet_Engine\Query_Builder\Manager::instance()->get_queries_for_options();

		// jet_engine_query_builder_id will be my option key
		$jet_engine_control['jet_engine_query_builder_id'] = [
			'tab'         => 'content',
			'label'       => esc_html__( 'JetEngine Queries', 'jet-engine' ),
			'type'        => 'select',
			'options'     => Options_Converter::remove_empty_key_in_options( $options ),
			'placeholder' => esc_html__( 'Choose a query', 'jet-engine' ),
			'required'    => array(
				[ 'query.objectType', '=', 'jet_engine_query_builder' ],
				[ 'hasLoop', '!=', false ]
			),
			'rerender'    => true,
			'description' => esc_html__( 'Please create a query in JetEngine Query Builder First', 'jet-engine' ),
			'searchable'  => true,
			'multiple'    => false,
		];

		// Below 2 lines is just some php array functions to force my new control located after the query control
		$query_key_index = absint( array_search( 'query', array_keys( $controls ) ) );
		$new_controls    = array_slice( $controls, 0, $query_key_index + 1, true ) + $jet_engine_control + array_slice( $controls, $query_key_index + 1, null, true );

		return $new_controls;
	}

	/**
	 * Modify the main query under certain conditions.
	 *
	 * @param bool   $is_main_query  Whether the query is the main query.
	 * @param object $post           The current post object.
	 * @param object $query          The current WP_Query object.
	 *
	 * @return bool  Modified value for $is_main_query.
	 */
	public function maybe_modify_is_main_query( $is_main_query ) {
		$content_type = Database::$active_templates['content_type'] ?? '';

		if ( $is_main_query && $content_type === 'archive' ) {
			return ! $is_main_query;
		}

		return $is_main_query;
	}

	public function add_render_filter_for_bricks_element( $settings, $element ) {
		if ( ! isset( $settings['hasLoop'] ) ) {
			return $settings;
		}

		$object_type = $this->get_object_type( $settings );
		$jet_engine_query_builder_id = ! empty( $settings['jet_engine_query_builder_id'] ) ? absint( $settings['jet_engine_query_builder_id'] ) : 0;

		if ( $object_type === 'jet_engine_query_builder' && $jet_engine_query_builder_id !== 0 ) {
			add_filter( 'bricks/query/loop_object', array( $this, 'add_query_builder_object_to_stack' ) );
		} elseif( $object_type === 'post' ) {
			add_action( 'the_post', array( $this, 'add_bricks_loop_object_to_stack' ) );
		} else {
			return $settings;
		}

		add_filter( 'bricks/dynamic_data/render_content', array( $this, 'remove_object_from_stack' ), 10, 2 );

		return $settings;
	}

	/**
	 * Add object to the stack
	 *
	 * @param  [type] $object [description]
	 * @return [type]         [description]
	 */
	public function add_to_stack( $object ) {
		do_action( 'jet-engine/object-stack/increase', $object );
	}

	public function add_query_builder_object_to_stack( $object ) {
		$this->add_to_stack( $object );

		return $object;
	}

	public function add_bricks_loop_object_to_stack( $object ) {
		if ( Query::is_looping() ) {
			$this->add_to_stack( $object );
		}
	}

	/**
	 * Remove object from the stack
	 *
	 * @param  [type] $object [description]
	 * @return [type]         [description]
	 */
	public function remove_from_stack( $object ) {
		do_action( 'jet-engine/object-stack/decrease', $object );
	}

	public function remove_object_from_stack( $content, $object ) {
		$this->remove_from_stack( $object );

		return $content;
	}

	public function get_object_type( $settings ) {
		return ! empty( $settings['query']['objectType'] ) ? $settings['query']['objectType'] : 'post';
	}

	public function set_initial_object_before_render( $query ) {
		if ( ! in_array( $query->object_type, [ 'user', 'term' ] ) ) {
			return;
		}

		$this->innitial_object = jet_engine()->listings->data->get_current_object();

		add_action( 'jet-engine/listing-element/before-render', array( $this, 'set_current_object' ) );
	}

	public function reset_object_after_render( $query ) {
		if ( ! in_array( $query->object_type, [ 'user', 'term' ] ) ) {
			return;
		}

		remove_action( 'jet-engine/listing-element/before-render', array( $this, 'set_current_object' ) );

		jet_engine()->listings->data->set_current_object( $this->innitial_object );
	}

	// Set current User or Term object to dynamic widgets in a bricks loop
	public function set_current_object() {
		jet_engine()->listings->data->set_current_object( Query::get_loop_object() );
	}

	/**
	 * Sets the queried object for rendering JetEngine dynamic widgets in a popup.
	 *
	 * @param array  $elements Array of elements to be rendered.
	 * @param string $area     The area where the elements will be rendered.
	 *
	 * @return void
	 */
	public function set_object_before_popup_rendering( $elements, $area ) {
		if ( $area !== 'popup' || ! $this->is_ajax_popup_looping() ) {
			return;
		}

		$this->innitial_object = jet_engine()->listings->data->get_current_object();

		jet_engine()->listings->data->set_current_object( get_queried_object() );
	}

	/**
	 * Sets the initial object after popup rendering.
	 *
	 * @param array  $elements Array of elements to be rendered.
	 * @param string $area     The area where the elements will be rendered.
	 *
	 * @return void
	 */
	public function reset_object_after_popup_rendering( $elements, $area ) {
		if ( $area !== 'popup' || ! $this->is_ajax_popup_looping() ) {
			return;
		}

		// Set initial object for generating dynamic style in Listing grid
		if ( ! empty( $this->initial_object ) ) {
			jet_engine()->listings->data->set_current_object( $this->initial_object );
		}
	}

	/**
	 * Checks if the AJAX popup is currently in a looping state.
	 *
	 * @return bool Returns true if the popup is in a looping state, false otherwise.
	 */
	public function is_ajax_popup_looping() {
		if ( ! Api::is_current_endpoint( 'load_popup_content' ) ) {
			return false;
		}

		$request_data     = $this->jet_get_request_data();
		$is_looping       = $request_data['isLooping'] ?? '';
		$popup_context_id = $request_data['popupContextId'] ?? '';

		if ( empty( $popup_context_id ) || empty( $is_looping ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Retrieves the request data for the current context.
	 *
	 * @return array|false The request data as an associative array, or false if no data is found.
	 */
	public function jet_get_request_data() {
		$data = false;

		if ( bricks_is_rest_call() ) {
			$data = file_get_contents( 'php://input' );
		} elseif ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'bricks_render_element' === $_REQUEST['action'] ) {
			$data = $_REQUEST;
		}

		if ( ! $data ) {
			return false;
		}

		if ( is_string( $data ) ) {
			$data = json_decode( $data, true );
		}

		return $data;
	}
}