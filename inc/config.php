<?php

class WP_DB_Driver_Config {
	static $charset = false;
	static $collate = false;

	/**
	 * Get an array of all the custom drivers this plugin provides
	 *
	 * @return array
	 */
	public static function get_drivers() {
		global $wp_custom_drivers;

		$driver_folder = dirname( dirname( __FILE__ ) ) . '/drivers';

		$drivers = array(
			'wpdb_driver_pdo_mysql' => $driver_folder . '/pdo_mysql.php',
			'wpdb_driver_mysqli'    => $driver_folder . '/mysqli.php',
			'wpdb_driver_mysql'     => $driver_folder . '/mysql.php',
			'wpdb_driver_pdo_pgsql' => $driver_folder . '/pdo_pgsql.php', // Uncommon to use so as last
		);

		if ( isset( $wp_custom_drivers ) && is_array( $wp_custom_drivers ) ) {
			$drivers = $wp_custom_drivers + $drivers;
		}

		return $drivers;
	}

	/**
	 * 
	 * Getting the driver that is the best possible option.
	 *
	 * @return bool|string The classname of the driver. False if no driver is available
	 */
	public static function get_current_driver() {
		$driver  = false;
		$drivers = self::get_drivers();

		if ( defined( 'WPDB_DRIVER' ) ) {
			$driver = WPDB_DRIVER;

			switch( $driver ) {
				case 'pdo_pgsql':
					$driver = 'wpdb_driver_pdo_pgsql';
					break;
				case 'pdo_mysql':
					$driver = 'wpdb_driver_pdo_mysql';
					break;
				case 'mysqli':
					$driver = 'wpdb_driver_mysqli';
					break;
				case 'mysql':
					$driver = 'wpdb_driver_mysql';
					break;
			}

			if ( isset( $drivers[ $driver ] ) ) {
				include_once $drivers[ $driver ];
			}

			if ( self::class_is_driver_and_supported( $driver ) ) {
				return $driver;
			}
		}

		if ( defined( 'WP_USE_EXT_MYSQL' ) && WP_USE_EXT_MYSQL ) {
			$drivers = array( 'wpdb_driver_mysql' => $drivers['wpdb_driver_mysql'] ) + $drivers;
		}

		foreach ( $drivers as $class => $file ) {
			include_once $file;

			if ( self::class_is_driver_and_supported( $class ) ) {
				return $class;
			}
		}

		return false;
	}

	/**
	 * Check if the requested class is loaded and supported on the server
	 * 
	 * @param string $class
	 *
	 * @return bool
	 */
	private static function class_is_driver_and_supported( $class ) {
		if ( class_exists( $class ) && call_user_func( array( $class, 'is_supported' ) ) ) {
			return true;
		}

		return false;
	}

}
