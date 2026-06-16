<?php
/**
 * PSR-4 style autoloader for BAM namespace.
 *
 * @package BulkActionsManager
 */

namespace BAM;

defined( 'ABSPATH' ) || exit;

/**
 * Class Autoloader
 */
class Autoloader {

	/**
	 * Base directory for includes.
	 *
	 * @var string
	 */
	private static $base_dir = '';

	/**
	 * Namespace prefix.
	 *
	 * @var string
	 */
	private static $prefix = '';

	/**
	 * Register the autoloader.
	 *
	 * @param string $base_dir Base directory path.
	 * @param string $prefix   Namespace prefix.
	 */
	public static function register( $base_dir, $prefix ) {
		self::$base_dir = trailingslashit( $base_dir );
		self::$prefix   = $prefix;
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload callback.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function autoload( $class ) {
		if ( 0 !== strpos( $class, self::$prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( self::$prefix ) );
		$relative = str_replace( '\\', '/', $relative );
		$parts    = explode( '/', $relative );
		$class_name = array_pop( $parts );

		$path_parts = array();
		foreach ( $parts as $part ) {
			$path_parts[] = strtolower( str_replace( '_', '-', $part ) );
		}

		$dir = ! empty( $path_parts ) ? self::$base_dir . implode( '/', $path_parts ) . '/' : self::$base_dir;

		$candidates = array( $dir . 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php' );

		if ( substr( $class_name, -10 ) === '_Interface' ) {
			$short = substr( $class_name, 0, -10 );
			$candidates[] = $dir . 'interface-' . strtolower( str_replace( '_', '-', $short ) ) . '.php';
		}

		if ( 0 === strpos( $class_name, 'Abstract_' ) ) {
			$short = substr( $class_name, 9 );
			$candidates[] = $dir . 'abstract-' . strtolower( str_replace( '_', '-', $short ) ) . '.php';
		}

		foreach ( $candidates as $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
}
