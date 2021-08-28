<?php

namespace Clockwork_For_Wp\Data_Source;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\Log;
use Clockwork\Request\Request;

// @todo Also handle exceptions?
class Errors extends DataSource {
	protected $error_reporting;
	protected $errors = [];
	protected $original_handler;
	protected $registered = false;

	protected static $instance;

	public function __construct() {
		$this->error_reporting = error_reporting();
	}

	public static function get_instance(){
		if ( ! static::$instance instanceof Errors ) {
			static::$instance = new Errors();
		}

		return static::$instance;
	}

	public static function set_instance( Errors $instance ) {
		static::$instance = $instance;
	}

	public function resolve( Request $request ) {
		$this->append_errors_log( $request );

		return $request;
	}

	public function reapply_filters() {
		$this->errors = array_filter( $this->errors, function( $error ) {
			return $this->passesFilters( [ $error ] );
		} );
	}

	public function register() {
		if ( $this->registered ) {
			return;
		}

		// If there was an error before this plugin is loaded we will record it.
		$error = error_get_last();

		if ( $error ) {
			$this->record_error( $error['type'], $error['message'], $error['file'], $error['line'] );
		}

		$this->original_handler = set_error_handler( [ $this, 'handler' ] );

		$this->registered = true;
	}

	public function unregister() {
		if ( ! $this->registered ) {
			return;
		}

		restore_error_handler();
		$this->errors = [];
		$this->original_handler = null;
		$this->registered = false;
	}

	public function handler( $no, $str, $file = null, $line = null ) {
		$this->record_error( $no, $str, $file, $line );

		if ( is_callable( $this->original_handler ) ) {
			return call_user_func( $this->original_handler, $no, $str, $file, $line );
		}

		return false;
	}

	public function record_error( $no, $str, $file = null, $line = null ) {
		$probably_suppressed = error_reporting() !== $this->error_reporting;

		if ( $probably_suppressed ) {
			$should_log = $this->error_reporting & $no;
		} else {
			$should_log = error_reporting() & $no;
		}

		if ( ! (bool) $should_log ) {
			return;
		}

		$error_array = [
			'type' => $no,
			'message' => $str,
			'file' => $file,
			'line' => $line,
			'suppressed' => $probably_suppressed,
		];

		// MD5 is used in an attempt to prevent duplicates when recording last error on shutdown.
		$this->errors[ hash( 'md5', serialize( [ $no, $str, $file, $line ] ) ) ] = $error_array;
	}

	protected function append_errors_log( $request ) {
		$log = new Log();

		foreach ( $this->errors as $error ) {
			$message = $error['message'];

			if ( $error['suppressed'] ) {
				$message .= ' (@-suppressed)';
			}

			$log_method = $this->log_method( $error['type'] );

			call_user_func( [ $log, $log_method ], $message, [
				'type' => $this->friendly_type( $error['type'] ),
				'file' => $error['file'],
				'line' => $error['line'],
			] );
		}

		$request->log()->merge( $log );
	}

	protected function log_method( $type ) {
		// Best guess based on rough cross reference of predefined constants docs and PSR abstract
		// logger docs. May want to revisit eventually.
		switch ( $type ) {
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
			case E_RECOVERABLE_ERROR:
				return 'error';

			case E_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
			case E_USER_WARNING:
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				return 'warning';

			default:
				return 'notice';
		}
	}

	protected function friendly_label( $type ) {
		// @todo Need to verify if this is thorough enough...
		switch ( $type ) {
			case E_COMPILE_WARNING:
			case E_CORE_WARNING:
			case E_USER_WARNING:
			case E_WARNING:
				return 'Warning';

			case E_NOTICE:
			case E_USER_NOTICE:
				return 'Notice';

			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				return 'Deprecated';

			case E_RECOVERABLE_ERROR:
				return 'Catchable fatal error';

			default:
				return 'Fatal error';
		}
	}

	protected function friendly_type( $type ) {
		foreach ( [
			'E_ERROR',
			'E_WARNING',
			'E_PARSE',
			'E_NOTICE',
			'E_CORE_ERROR',
			'E_CORE_WARNING',
			'E_COMPILE_ERROR',
			'E_COMPILE_WARNING',
			'E_USER_ERROR',
			'E_USER_WARNING',
			'E_USER_NOTICE',
			'E_STRICT',
			'E_RECOVERABLE_ERROR',
			'E_DEPRECATED',
			'E_USER_DEPRECATED',
			'E_ALL',
		] as $constant ) {
			if ( defined( $constant ) && $type === constant( $constant ) ) {
				return $constant;
			}
		}

		return (string) $type;
	}
}
