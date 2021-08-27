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

	public function __construct( $display = false, $error_reporting = E_ALL ) {
		$this->error_reporting = $error_reporting;
	}

	public function resolve( Request $request ) {
		$this->append_errors_log( $request );

		return $request;
	}

	public function register() {
		if ( $this->registered ) {
			return;
		}

		// If there was an error before this plugin is loaded we will record it.
		$error = error_get_last();

		if ( $error ) {
			$this->handler( $error['type'], $error['message'], $error['file'], $error['line'] );
		}

		$this->original_handler = set_error_handler( [ $this, 'handler' ] );

		$this->registered = true;
	}

	public function handler( $no, $str, $file = null, $line = null ) {
		$this->record_error( $no, $str, $file, $line );

		if ( is_callable( $this->original_handler ) ) {
			return call_user_func( $this->original_handler, $no, $str, $file, $line );
		}

		return false;
	}

	public function record_error( $no, $str, $file = null, $line = null ) {
		if ( ! $this->should_log( $no ) ) {
			return;
		}

		// MD5 is used in an attempt to prevent duplicates when recording last error on shutdown.
		$this->errors[ hash( 'md5', serialize( [ $no, $str, $file, $line ] ) ) ] = [
			'type' => $no,
			'message' => $str,
			'file' => $file,
			'line' => $line,
		];
	}

	protected function append_errors_log( $request ) {
		$log = new Log();

		foreach ( $this->errors as $error ) {
			// @todo Logging by actual type instead of debug?
			$log->debug( $error['message'], [
				'type' => $this->friendly_type( $error['type'] ),
				'file' => $error['file'],
				'line' => $error['line'],
			] );
		}

		$request->log()->merge( $log );
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

	protected function should_log( $type ) {
		return (bool) ( $this->error_reporting & $type );
	}
}
