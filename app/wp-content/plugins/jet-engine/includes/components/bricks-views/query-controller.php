<?php

namespace Jet_Engine\Bricks_Views;

use Bricks\Api;
use Jet_Engine\Query_Builder\Manager as Query_Manager;


class Query_Controller {
	public $initial_object = null;

	function __construct() {
		add_filter( 'bricks/query/run', array( $this, 'run_query' ), 10, 2 );
		add_filter( 'bricks/query/result_count', array( $this, 'set_count' ), 10, 2 );
		add_filter( 'bricks/query/result_max_num_pages', array( $this, 'set_max_num_pages' ), 10, 2 );
		add_filter( 'bricks/query/loop_object', array( $this, 'set_loop_object' ), 10, 3 );
		add_action( 'bricks/query/after_loop', array( $this, 'reset_current_object' ), 10 );
	}

	public function run_query( $results, $query ) {
		if ( ! $this->is_query_valid( $query ) ) {
			return $results;
		}

		$query_id = apply_filters( 'jet-engine/query-builder/listings/query-id', $this->get_query_id( $query->settings ), 0, array() );

		// Return empty results if no query selected or Use Query is not checked
		if ( $query_id === 0 ) {
			return $results;
		}

		$widget_settings = $query->settings['listing_settings'] ?? [];

		$je_query = Query_Manager::instance()->listings->query->get_query_for_element( $query_id, $widget_settings );

		// Return empty results if query not found in JetEngine Query Builder
		if ( ! $je_query ) {
			return $results;
		}


		if ( Api::is_current_endpoint( 'load_query_page' ) ) {
			$paged = $query->query_vars['paged'] ?? 1;
			$je_query->set_filtered_prop( 'paged', $paged );
		}

		// Get current object for generating dynamic style in Listing grid
		if ( $query->element_id === 'jet-listing-el' ) {
			$this->initial_object = jet_engine()->listings->data->get_current_object();
		}

		// Get the results
		return $je_query->get_items();
	}

	public function set_count( $count, $query ) {
		if ( ! $this->is_query_valid( $query ) ) {
			return $count;
		}

		$je_query = $this->get_jet_engine_query( $query->settings );

		// Return empty results if query not found in JetEngine Query Builder
		if ( ! $je_query ) {
			return $count;
		}

		return $je_query->get_items_total_count();
	}

	public function set_max_num_pages( $max_num_pages, $query ) {
		if ( ! $this->is_query_valid( $query ) ) {
			return $max_num_pages;
		}

		$je_query = $this->get_jet_engine_query( $query->settings );

		// Return empty results if query not found in JetEngine Query Builder
		if ( ! $je_query ) {
			return $max_num_pages;
		}

		return $je_query->get_items_pages_count();
	}

	public function set_loop_object( $loop_object, $loop_key, $query ) {
		if ( ! $this->is_query_valid( $query ) ) {
			return $loop_object;
		}

		global $post;

		// I only tested on JetEngine Posts Query, Terms Query, Comments Query and WC Products Query
		// I didn't set WP_Term condition because it's not related to the $post global variable
		if ( is_a( $loop_object, 'WP_Post' ) ) {
			$post = $loop_object;
		} elseif ( is_a( $loop_object, 'WC_Product' ) ) {
			// $post should be a WP_Post object
			$post = get_post( $loop_object->get_id() );
		} elseif ( is_a( $loop_object, 'WP_Comment' ) ) {
			// A comment should refer to a post, so I set the $post global variable to the comment's post
			// You might want to change this to $loop_object->comment_ID
			$post = get_post( $loop_object->comment_post_ID );
		}

		setup_postdata( $post );

		$je_query = $this->get_jet_engine_query( $query->settings );

		// Return empty results if query not found in JetEngine Query Builder
		if ( ! $je_query ) {
			return $loop_object;
		}

		// Set current object for JetEngine
		jet_engine()->listings->data->set_current_object( $loop_object );

		// We still return the $loop_object so \Bricks\Query::get_loop_object() can use it
		return $loop_object;
	}

	public function reset_current_object( $query ) {
		if ( ! $this->is_query_valid( $query ) ) {
			return false;
		}

		$je_query = $this->get_jet_engine_query( $query->settings );

		if ( ! $je_query ) {
			return false;
		}

		// Set initial object for generating dynamic style in Listing grid
		if ( ! empty( $this->initial_object ) ) {
			jet_engine()->listings->data->set_current_object( $this->initial_object );
		} else {
			// Reset current object
			jet_engine()->listings->data->reset_current_object();
		}
	}

	/**
	 * Retrieve the JetEngine query object based on the provided settings.
	 *
	 * @param array $settings The settings array containing the query builder ID.
	 * @return mixed Returns the JetEngine query object if found, or false if no valid query ID.
	 */
	public function get_jet_engine_query( $settings ) {
		$query_id = $this->get_query_id( $settings );

		// Return empty results if no query selected or Use Query is not checked
		if ( $query_id === 0 ) {
			return false;
		}

		$query_builder = Query_Manager::instance();

		// Get the query object from JetEngine based on the query id
		return $query_builder->get_query_by_id( $query_id );
	}

	/**
	 * Retrieve the query ID from the given settings array.
	 *
	 * @param array $settings The settings array containing the query builder ID.
	 * @return int The query ID as an integer, or 0 if not found or empty.
	 */
	public function get_query_id( $settings ) {
		return ! empty( $settings['jet_engine_query_builder_id'] ) ? absint( $settings['jet_engine_query_builder_id'] ) : 0;
	}

	/**
	 * Check if the provided query object is valid.
	 *
	 * @param object $query The query object to validate.
	 * @return bool Returns true if the query is valid, false otherwise.
	 */
	public function is_query_valid( $query ) {
		if ( $query->object_type !== 'jet_engine_query_builder' || ! $query->settings['hasLoop'] ) {
			return false;
		}

		return true;
	}
}