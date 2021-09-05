<?php

declare(strict_types=1);

namespace Clockwork_For_Wp\Data_Source;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\Request;
use Clockwork_For_Wp\Event_Management\Event_Manager;
use Clockwork_For_Wp\Event_Management\Subscriber;
use Clockwork_For_Wp\Included_Files;

final class Theme extends DataSource implements Subscriber {
	private $body_classes = [];
	private $content_width;
	private $included_template = '';
	private $included_template_parts = [];
	private $is_child_theme = false;
	private $stylesheet;
	private $template;
	private $theme_root;

	public function configure_theme( $theme_root, $is_child_theme, $template, $stylesheet ) {
		$this->theme_root = $theme_root;
		$this->is_child_theme = $is_child_theme;
		$this->template = $template;
		$this->stylesheet = $stylesheet;

		return $this;
	}

	public function get_subscribed_events(): array {
		return [
			'cfw_pre_resolve' => function ( $content_width ): void {
				$this
					// @todo Constructor?
					->configure_theme(
						\get_theme_root(),
						\is_child_theme(),
						\get_template(),
						\get_stylesheet()
					)
					->set_content_width( $content_width )
					->set_included_template_parts(
						...\array_merge(
							Included_Files::template_parts_from_parent_theme(),
							Included_Files::template_parts_from_child_theme()
						)
					);
			},
			'body_class' => [ function ( $classes ) {
				$this->set_body_classes( \is_array( $classes ) ? $classes : [] );

				return $classes;
			}, Event_Manager::LATE_EVENT ],
			'template_include' => [ function ( $template ) {
				$this->set_included_template( $template );

				return $template;
			}, Event_Manager::LATE_EVENT ],
		];
	}

	public function resolve( Request $request ) {
		$panel = $request->userData( 'Theme' );

		$panel->table( 'Miscellaneous', $this->miscellaneous_table() );

		if ( '' !== $this->included_template ) {
			$panel->table( 'Included Template', $this->included_template_table() );
		}

		if ( 0 !== \count( $this->included_template_parts ) ) {
			$panel->table(
				'Template Parts',
				$this->template_parts_table()
			);
		}

		if ( 0 !== \count( $this->body_classes ) ) {
			$panel->table( 'Body Classes', $this->body_classes_table() );
		}

		return $request;
	}

	public function set_body_classes( array $body_classes ) {
		$this->body_classes = \array_values( \array_map( 'strval', $body_classes ) );

		return $this;
	}

	public function set_content_width( int $content_width ) {
		$this->content_width = $content_width;

		return $this;
	}

	public function set_included_template( string $included_template ) {
		$this->included_template = $included_template;

		return $this;
	}

	public function set_included_template_parts( string ...$template_parts ) {
		$this->included_template_parts = $template_parts;

		return $this;
	}

	private function body_classes_table() {
		return \array_map( static function ( $class ) {
			return [ 'Class' => $class ];
		}, $this->body_classes );
	}

	private function included_template_table() {
		$File = \pathinfo( $this->included_template, \PATHINFO_BASENAME );
		$Path = \ltrim( \str_replace( $this->theme_root, '', $this->included_template ), '/' );

		return [ \compact( 'File', 'Path' ) ];
	}

	private function miscellaneous_table() {
		return \array_filter( [
			[
				'Item' => 'Theme',
				'Value' => $this->is_child_theme ? $this->stylesheet : $this->template,
			],
			[
				'Item' => 'Parent Theme',
				'Value' => $this->is_child_theme ? $this->template : null,
			],
			[
				'Item' => 'Content Width',
				'Value' => $this->content_width,
			],
		], static function ( $row ) {
			return null !== $row['Value'];
		} );
	}

	// @todo Deferred set from provider?
	private function template_parts_table() {
		return \array_map( function ( $file_path ) {
			$File = \pathinfo( $file_path, \PATHINFO_BASENAME );
			$Path = \ltrim( \str_replace( $this->theme_root, '', $file_path ), '/' );

			return \compact( 'File', 'Path' );
		}, $this->included_template_parts );
	}
}
