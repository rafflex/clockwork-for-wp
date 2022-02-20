<?php

declare(strict_types=1);

namespace Clockwork_For_Wp\Data_Source;

use Clockwork_For_Wp\Base_Provider;
use Clockwork_For_Wp\Event_Management\Subscriber;

final class Data_Source_Provider extends Base_Provider {
	public function register(): void {
		$this->plugin[ Data_Source_Factory::class ] = function () {
			return new Data_Source_Factory( $this->plugin );
		};
	}

	public function registered(): void {
		// We have registered our error handler as early as possible in order to collect as many
		// errors as possible. However our config is not available that early so let's apply our
		// configuration now.
		$errors = Errors::get_instance();

		if ( $this->plugin->is_feature_enabled( 'errors' ) ) {
			$config = $this->plugin->config( 'data_sources.errors.config', [] );

			$except_types = $config['except_types'] ?? false;
			$only_types = $config['only_types'] ?? false;

			// Filter errors by type.
			$errors->addFilter(
				static function ( $error ) use ( $except_types, $only_types ) {
					if ( \is_int( $only_types ) ) {
						return ( $error['type'] & $only_types ) > 0;
					}

					if ( \is_int( $except_types ) ) {
						return ( $error['type'] & $except_types ) < 1;
					}

					return true;
				}
			);

			// Filter errors by message pattern.
			$errors->addFilter(
				( new Filter() )
					->except( $config['except_messages'] ?? [] )
					->only( $config['only_messages'] ?? [] )
					->to_closure( 'message' )
			);

			// Filter errors by file pattern.
			$errors->addFilter(
				( new Filter() )
					->except( $config['except_files'] ?? [] )
					->only( $config['only_files'] ?? [] )
					->to_closure( 'file' )
			);

			// Filter suppressed errors.
			$include_suppressed = $config['include_suppressed_errors'] ?? false;

			$errors->addFilter(
				static function ( $error ) use ( $include_suppressed ) {
					return ! $error['suppressed'] || $include_suppressed;
				}
			);

			$errors->reapply_filters();
		} else {
			$errors->unregister();
		}
	}

	protected function subscribers(): array {
		return \array_filter(
			$this->plugin[ Data_Source_Factory::class ]->get_enabled_data_sources(),
			static function ( $data_source ) {
				return $data_source instanceof Subscriber;
			}
		);
	}
}
