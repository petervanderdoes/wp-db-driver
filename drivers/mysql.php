<?php

/**
 * Database driver, using the mysql extension.
 *
 * @link       http://php.net/manual/en/book.mysql.php
 *
 * @package    WordPress
 * @subpackage Database
 * @since      3.6.0
 */
class wpdb_driver_mysql extends wpdb_driver_mysql_shared {
	/**
	 * Database link
	 *
	 * @var resource
	 */
	private $dbh = null;
	/**
	 * Result set
	 *
	 * @var resource
	 */
	private $result = null;
	/**
	 * Cached column info
	 *
	 * @var array|null
	 */
	private $col_info = null;

	/**
	 * Return the name of the driver
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'MySQL';
	}
	/**
	 * Check if MySQL is supported on the server
	 *
	 * @return bool
	 */
	public static function is_supported() {
		return extension_loaded( 'mysql' );
	}

	/**
	 * Escape with mysql_real_escape_string()
	 *
	 * @see mysql_real_escape_string()
	 *
	 * @param  string $string to escape
	 *
	 * @return string escaped
	 */
	public function escape( $string ) {
		return mysql_real_escape_string( $string, $this->dbh );
	}

	/**
	 * Get the latest error message from the DB driver
	 *
	 * @return string
	 */
	public function get_error_message() {
		return mysql_error( $this->dbh );
	}

	/**
	 * Free memory associated with the resultset
	 *
	 * @return void
	 */
	public function flush() {
		if ( is_resource( $this->result ) ) {
			mysql_free_result( $this->result );
		}

		$this->result = null;
		$this->col_info = null;
	}

	/**
	 * Check if server is still connected
	 *
	 * @return bool
	 */
	public function is_connected() {
		if ( ! $this->dbh || 2006 == mysql_errno( $this->dbh ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Connect to database
	 *
	 * @param string      $host
	 * @param string      $dbname
	 * @param string      $user
	 * @param string      $pass
	 * @param integer|null  $port
	 * @param array $options
	 *
	 * @return bool
	 */
	public function connect( $host, $dbname, $user, $pass, $port = null, $options = array() ) {
		$server       = $host;
		$new_link     = defined( 'MYSQL_NEW_LINK' ) ? MYSQL_NEW_LINK : true;
		$client_flags = defined( 'MYSQL_CLIENT_FLAGS' ) ? MYSQL_CLIENT_FLAGS : 0;

		if ( $port ) {
			$server .= ':' . $port;
		}

		if ( WP_DEBUG ) {
			$this->dbh =  mysql_connect( $server, $user, $pass, $new_link, $client_flags );
		} else {
			$this->dbh = @mysql_connect( $server, $user, $pass, $new_link, $client_flags );
		}

		if ( false === $this->dbh ) {
			return false;
		}

		$this->set_charset();
		$this->set_sql_mode();

		return $this->select( $dbname );
	}

	/**
	 * Disconnect the database connection
	 */
	public function disconnect() {
		mysql_close( $this->dbh );
		$this->dbh = null;
	}

	/**
	 * Ping a server connection or reconnect if there is no connection
	 *
	 * @return bool
	 */
	public function ping() {
		return @ mysql_ping( $this->dbh );
	}

	/**
	 * Sets the connection's character set.
	 *
	 * @param string|null $charset
	 * @param string|null $collate
	 *
	 * @return bool
	 */
	public function set_charset( $charset = null, $collate = null ) {
		if ( ! isset( $charset ) ) {
			$charset = WP_DB_Driver_Config::$charset;
		}

		if ( ! isset( $collate ) ) {
			$collate = WP_DB_Driver_Config::$collate;
		}

		if ( $this->has_cap( 'collation' ) && ! empty( $charset ) ) {
			if ( function_exists( 'mysql_set_charset' ) && $this->has_cap( 'set_charset' ) ) {
				mysql_set_charset( $charset, $this->dbh );
			}
			else {
				$this->set_charset( $charset, $collate );
			}

			return true;
		}

		return false;
	}

	/**
	 * Get the name of the current character set.
	 *
	 * @return string Returns the name of the character set
	 */
	public function connection_charset() {
		return mysql_client_encoding( $this->dbh );
	}

	/**
	 * Select database
	 *
	 * @param string $db
	 *
	 * @return bool
	 */
	public function select( $db ) {
		if ( WP_DEBUG ) {
			return mysql_select_db( $db, $this->dbh );
		} else {
			return @mysql_select_db( $db, $this->dbh );
		}
	}

	/**
	 * Perform a MySQL database query, using current database connection.
	 *
	 * @param string $query Database query
	 *
	 * @return bool|int|resource Number of rows affected/selected or false on error
	 */
	public function query( $query ) {
		$return_val   = 0;
		$this->result = @mysql_query( $query, $this->dbh );

		if ( preg_match( '/^\s*(create|alter|truncate|drop)\s/i', $query ) ) {
			$return_val = $this->result;
		}
		elseif ( preg_match( '/^\s*(insert|delete|update|replace)\s/i', $query ) ) {
			$return_val = $this->affected_rows();
		}
		elseif ( preg_match( '/^\s*select\s/i', $query ) ) {
			return is_resource( $this->result ) ? mysql_num_rows( $this->result ) : false;
		}

		return $return_val;
	}

	/**
	 * Get result data.
	 *
	 * @param int $row   The row number from the result that's being retrieved. Row numbers start at 0.
	 * @param int $field The offset of the field being retrieved.
	 *
	 * @return string|false The contents of one cell from a MySQL result set on success, or false on failure.
	 */
	public function query_result( $row, $field = 0 ) {
		return mysql_result( $this->result, $row, $field );
	}

	/**
	 * Get number of rows affected
	 *
	 * @return int
	 */
	public function affected_rows() {
		return mysql_affected_rows( $this->dbh );
	}

	/**
	 * Get last insert id
	 *
	 * @return int
	 */
	public function insert_id() {
		return mysql_insert_id( $this->dbh );
	}

	/**
	 * Get results
	 *
	 * @return array
	 */
	public function get_results() {
		$ret = array();
		while ( $row = @mysql_fetch_object( $this->result ) ) {
			$ret[] = $row;
		}
		return $ret;
	}

	/**
	 * Load the column metadata from the last query.
	 *
	 * @return array
	 */
	public function load_col_info() {
		if ( $this->col_info ) {
			return $this->col_info;
		}

		$num_fields = @mysql_num_fields( $this->result );

		for ( $i = 0; $i < $num_fields; $i++ ) {
			$this->col_info[ $i ] = @mysql_fetch_field( $this->result, $i );
		}

		return $this->col_info;
	}

	/**
	 * The database version number.
	 *
	 * @return false|string false on failure, version number on success
	 */
	public function db_version() {
		return preg_replace( '/[^0-9.].*/', '', mysql_get_server_info( $this->dbh ) );
	}

	/**
	 * Determine if a database supports a particular feature.
	 *
	 * @param string $db_cap
	 *
	 * @return bool
	 */
	public function has_cap( $db_cap ) {
		$db_cap = strtolower( $db_cap );

		$version = parent::has_cap( $db_cap );

		if ( $version && 'utf8mb4' === $db_cap ) {
			return version_compare( mysql_get_client_info(), '5.5.3', '>=' );
		}

		return $version;
	}

}
