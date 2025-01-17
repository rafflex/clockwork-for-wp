<?php

namespace Clockwork_For_Wp\Tests\Clockwork\Data_Source;

use Clockwork\Request\Request;
use Clockwork_For_Wp\Data_Source\Xdebug;
use PHPUnit\Framework\TestCase;

class Xdebug_Test extends TestCase {
	/** @test */
	public function it_correctly_records_profiler_data() {
		$data_source = new Xdebug();
		$request = new Request();

		$file = realpath( __DIR__ . '/../fixtures/profile-stand-in.txt' );
		$contents = file_get_contents( $file );

		$data_source->set_profiler_filename( $file );

		$data_source->resolve( $request );

		$this->assertEquals( [
			'profile' => $file,
		], $request->xdebug );

		$data_source->extend( $request );

		$this->assertEquals( [
			'profile' => $file,
			'profileData' => $contents,
		], $request->xdebug );
	}
}
