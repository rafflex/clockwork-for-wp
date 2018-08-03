<?php

namespace Clockwork_For_Wp\Data_Source;

use WP;
use WP_Query;
use Clockwork\Request\Log;
use Clockwork\Request\Request;
use Clockwork\Request\Timeline;
use Clockwork\DataSource\DataSource;

// @todo Inject globals.
class WordPress extends DataSource {
	protected $log;

	/**
	 * @var Timeline
	 */
	protected $timeline;
	protected $wp;
	protected $wp_query;

	/**
	 * @param Timeline|null $timeline
	 */
	public function __construct( WP $wp, WP_Query $wp_query, Log $log = null, Timeline $timeline = null ) {
		$this->wp = $wp;
		$this->wp_query = $wp_query;
		$this->log = $log ?: new Log();
		$this->timeline = $timeline ?: new Timeline();
	}

	/**
	 * @param  Request $request
	 * @return Request
	 */
	public function resolve( Request $request ) {
		// @todo Consider options for filling the "controller" slot.
		// @todo Consider configuring a custom error handler to save errors in the "log" slot.

		$request_table = $this->request_table();
		$has_request_data = 0 !== count( $request_table );
		$query_vars_table = $this->query_vars_table();
		$has_query_vars_data = 0 !== count( $query_vars_table );

		if ( $has_request_data || $has_query_vars_data ) {
			$panel = $request->userData( 'wordpress' )->title( 'WordPress' );

			if ( $has_request_data ) {
				$panel->table( 'Request', $request_table );
			}

			if ( $has_query_vars_data ) {
				$panel->table( 'Query Vars', $query_vars_table );
			}
		}

		$request->log = array_merge( $request->log, $this->log->toArray() );
		$request->timelineData = array_merge(
			$request->timelineData,
			$this->timeline->finalize( $request->time )
		);

		return $request;
	}

	/**
	 * @return void
	 */
	public function listen_to_events() {
		$this->timeline->startEvent( 'total', 'Total execution', 'start' );

		$this->timeline->addEvent(
			'core_timer',
			'Core timer start',
			$GLOBALS['timestart'],
			$GLOBALS['timestart']
		);

		// @todo Not sure if this is actually a good idea...
		$this->hijack_doing_it_wrong();
	}

	protected function hijack_doing_it_wrong() {
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );

		add_action( 'doing_it_wrong_run', function( $function, $message, $version ) {
			// @todo Translations!
			$context = [
				'link' => 'https://codex.wordpress.org/Debugging_in_WordPress',
			];

			if ( is_string( $version ) ) {
				$context['version'] = $version;
			}

			if ( is_string( $message ) ) {
				$context['message'] = $message;
			}

			// @todo What is appropriate level here? Core triggers an error.
			$this->log->warning( "_doing_it_wrong: {$function} was called incorrectly", $context );
		}, 10, 3 );
	}

	/**
	 * Adapted from Query Monitor QM_Collector_Request class.
	 */
	protected function query_vars() {
		$plugin_vars = apply_filters( 'query_vars', [] );

		$query_vars = array_filter(
			$this->wp_query->query_vars,
			function( $value, $key ) use ( $plugin_vars ) {
				return ( isset( $plugin_vars[ $key ] ) && '' !== $value ) || ! empty( $value );
			},
			ARRAY_FILTER_USE_BOTH
		);

		ksort( $query_vars );

		return $query_vars;
	}

	protected function query_vars_table() {
		$query_vars = $this->query_vars();

		return array_map( function( $value, $key ) {
			return [
				'Variable' => $key,
				'Value' => $value,
			];
		}, $query_vars, array_keys( $query_vars ) );
	}

	protected function request_table() {
		$table = array_map( function( $var ) {
			return [
				'Variable' => $var,
				'Value' => $this->wp->{$var} ? $this->wp->{$var} : false,
			];
		}, [ 'request', 'query_string', 'matched_rule', 'matched_query' ] );

		return array_filter( $table, function( $row ) {
			return false !== $row['Value'];
		} );
	}
}
