<?php

declare(strict_types=1);

namespace Clockwork_For_Wp\Wp_Cli;

use Clockwork\Storage\StorageInterface;
use Clockwork_For_Wp\Config;
use WP_CLI;

final class Clean_Command extends Base_Command {
	protected $description = 'Cleans Clockwork request metadata.';
	protected $name = 'clean';
	protected $options = [
		'[--all]' => 'Cleans all data.',
		'[--expiration=<expiration>]' => 'Cleans data older than specified value in minutes. Does nothing if "--all" is also set.',
	];

	public function __invoke( Config $config, $all = false, $expiration = null ): void {
		if ( $all ) {
			$config->set( 'storage.expiration', 0 );
		} elseif ( null !== $expiration ) {
			// @todo Should we allow float?
			$config->set( 'storage.expiration', \abs( (int) $expiration ) );
		}

		\_cfw_instance()[ StorageInterface::class ]->cleanup( $force = true );

		// See https://github.com/itsgoingd/clockwork/issues/510
		// @todo Revisit after the release of Clockwork v6.
		if ( $all && 'file' === $config->get( 'storage.driver', 'file' ) ) {
			$path = $config->get( 'storage.drivers.file.config.path' );

			foreach ( \glob( $path . '/*.json' ) as $file ) {
				\unlink( $file );
			}
		}

		WP_CLI::success( 'Metadata cleaned successfully.' );
	}
}
