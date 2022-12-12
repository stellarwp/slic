<?php

namespace StellarWP\Slic\Tests;

trait Uopz_Functions {
	/**
	 * @var array
	 */
	private array $set_uopz_fn_returns = [];

	private function uopz_set_fn_return( string $function, $value, bool $execute = false ): void {
		uopz_set_return( $function, $value, $execute );

		$this->set_uopz_fn_returns[] = $function;
	}

	/**
	 * @after
	 */
	public function unset_uopz_fn_returns(): void {
		foreach ( $this->set_uopz_fn_returns as $function ) {
			uopz_unset_return( $function );
		}
		$this->set_uopz_fn_returns = [];
	}
}
