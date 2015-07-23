<?php

class WP_DB_Driver_Config {

	public static function get_drivers() {
		$driver_folder = dirname( dirname( __FILE__ ) ) . '/drivers';

		$drivers = array(
			'wpdb_driver_pdo_mysql' => $driver_folder . '/pdo_mysql.php',
			'wpdb_driver_mysqli'    => $driver_folder . '/mysqli.php',
			'wpdb_driver_mysql'     => $driver_folder . '/mysql.php',
		);

		if ( defined( 'WPDB_DRIVERS' ) && is_array( WPDB_DRIVERS ) ) {
			$drivers = WPDB_DRIVERS + $drivers;
		}

		return $drivers;
	}

	/**
	 * Getting the driver that is the best possible option.
	 *
	 * @return string The classname of the driver
	 */
	public static function get_current_driver() {
		$driver = false;

		if ( defined( 'WPDB_DRIVER' ) && self::class_is_driver_and_supported( WPDB_DRIVER ) ) {
			return WPDB_DRIVER;
		}

		$drivers = self::get_drivers();

		if ( defined( 'WP_USE_EXT_MYSQL' ) && WP_USE_EXT_MYSQL ) {
			$drivers = array( 'wpdb_driver_mysql' => $drivers['wpdb_driver_mysql'] ) + $drivers;
		}

		foreach ( $drivers as $class => $file ) {
			include_once $file;

			if ( ! self::class_is_driver_and_supported( $class ) ) {
				return $class;
			}
		}

		return false;
	}

	private static function class_is_driver_and_supported( $class ) {
		if ( $class instanceof wpdb_driver && call_user_func( array( $class, 'is_supported' ) ) ) {
			return true;
		}

		return false;
	}

}