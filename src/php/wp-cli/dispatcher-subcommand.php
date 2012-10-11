<?php

namespace WP_CLI\Dispatcher;

abstract class Subcommand {

	function __construct( $method ) {
		$this->method = $method;
	}

	abstract function show_usage();
	abstract function invoke( $args, $assoc_args );

	protected function check_args( $args, $assoc_args ) {
		$accepted_params = $this->parse_synopsis( $this->get_synopsis() );

		$mandatory_positinal = wp_list_filter( $accepted_params, array(
			'type' => 'positional',
			'optional' => false
		) );

		if ( count( $args ) < count( $mandatory_positinal ) ) {
			$this->show_usage();
			exit(1);
		}

		$mandatory_assoc = wp_list_pluck( wp_list_filter( $accepted_params, array(
			'type' => 'assoc',
			'optional' => false
		) ), 'name' );

		$errors = array();

		foreach ( $mandatory_assoc as $key ) {
			if ( !isset( $assoc_args[ $key ] ) )
				$errors[] = "missing --$key parameter";
			elseif ( true === $assoc_args[ $key ] )
				$errors[] = "--$key parameter needs a value";
		}

		if ( empty( $errors ) )
			return;

		$this->show_usage();
		exit(1);
	}

	protected function get_synopsis() {
		$comment = $this->method->getDocComment();

		if ( !preg_match( '/@synopsis\s+([^\n]+)/', $comment, $matches ) )
			return false;

		return $matches[1];
	}

	protected function parse_synopsis( $synopsis ) {
		$patterns = self::get_patterns();

		$tokens = preg_split( '/[\s\t]+/', $synopsis );

		$params = array();

		foreach ( $tokens as $token ) {
			foreach ( $patterns as $regex => $desc ) {
				if ( preg_match( $regex, $token, $matches ) ) {
					$params[] = array_merge( $matches, $desc );
					break;
				}
			}
		}

		return $params;
	}

	private static function get_patterns() {
		$p_name = '(?P<name>[a-z-]+)';
		$p_value = '<(?P<value>[a-z-|]+)>';

		$param_types = array(
			'positional' => $p_value,
			'assoc' => "--$p_name=$p_value",
			'flag' => "--$p_name"
		);

		$patterns = array();

		foreach ( $param_types as $type => $pattern ) {
			$patterns[ "/^$pattern$/" ] = array(
				'type' => $type,
				'optional' => false
			);

			$patterns[ "/^\[$pattern\]$/" ] = array(
				'type' => $type,
				'optional' => true
			);
		}

		return $patterns;
	}
}


class MethodSubcommand extends Subcommand {

	function __construct( $method, $parent ) {
		$this->parent = $parent;

		parent::__construct( $method );
	}

	function show_usage( $prefix = 'usage: ' ) {
		$command = $this->parent->name;
		$subcommand = $this->get_name();
		$synopsis = $this->get_synopsis();

		\WP_CLI::line( $prefix . "wp $command $subcommand $synopsis" );
	}

	function get_name() {
		$comment = $this->method->getDocComment();

		if ( preg_match( '/@subcommand\s+([a-z-]+)/', $comment, $matches ) )
			return $matches[1];

		return $this->method->name;
	}

	function invoke( $args, $assoc_args ) {
		$this->check_args( $args, $assoc_args );

		$class = $this->parent->class;
		$instance = new $class;

		$this->method->invoke( $instance, $args, $assoc_args );
	}
}

