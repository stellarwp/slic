<?php

namespace StellarWP\Slic;

class Callback_Stack {
	/**
	 * A set of callbacks accumulated in the stack so far, by
	 * priority.
	 *
	 * @var array<string,callable>
	 */
	private $callbacks = [];

	public function call(): void {
		foreach ( $this->callbacks as $callback_id => $callback ) {
			$callback();

			// Remove the callback from the stack after it has been called.
			$this->remove( $callback_id );
		}
	}

	/**
	 * Add a callback to the stack, with a specified priority.
	 *
	 * @param string   $callback_id The callback ID.
	 * @param callable $callback    The callback to add.
	 *
	 * @return void The callback is added to the stack.
	 */
	public function add( string $callback_id, callable $callback ): void {
		$this->callbacks[ $callback_id ] = $callback;
	}

	/**
	 * Remove a callback from the stack.
	 *
	 * @param string $callback_id The callback ID.
	 *
	 * @return void The callback is removed from the stack.
	 */
	private function remove( $callback_id ) {
		unset( $this->callbacks[ $callback_id ] );
	}
}
