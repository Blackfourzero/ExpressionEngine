<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.2.4 or newer
 *
 * @package		CodeIgniter
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * Initialize the database
 *
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 */
function DB($params = '', $active_record_override = NULL)
{
	// Load the DB config file if a DSN string wasn't passed
	if (is_string($params) AND strpos($params, '://') === FALSE)
	{
		$params = ee('Database')->getConfig()->getGroupConfig();
	}
	elseif (is_string($params))
	{

		/* parse the URL from the DSN string
		 *  Database settings can be passed as discreet
		 *  parameters or as a data source name in the first
		 *  parameter. DSNs must have this prototype:
		 *  $dsn = 'driver://username:password@hostname/database';
		 */

		if (($dns = @parse_url($params)) === FALSE)
		{
			show_error('Invalid DB Connection String');
		}

		$params = array(
			'dbdriver' => $dns['scheme'],
			'hostname' => (isset($dns['host'])) ? rawurldecode($dns['host']) : '',
			'username' => (isset($dns['user'])) ? rawurldecode($dns['user']) : '',
			'password' => (isset($dns['pass'])) ? rawurldecode($dns['pass']) : '',
			'database' => (isset($dns['path'])) ? rawurldecode(substr($dns['path'], 1)) : ''
		);

		// were additional config items set?
		if (isset($dns['query']))
		{
			parse_str($dns['query'], $extra);

			foreach($extra as $key => $val)
			{
				// booleans please
				if (strtoupper($val) == "TRUE")
				{
					$val = TRUE;
				}
				elseif (strtoupper($val) == "FALSE")
				{
					$val = FALSE;
				}

				$params[$key] = $val;
			}
		}
	}

	$params['dbdriver'] = 'mysqli';

	// Load the DB classes.  Note: Since the active record class is optional
	// we need to dynamically create a class that extends proper parent class
	// based on whether we're using the active record class or not.

	if ($active_record_override !== NULL)
	{
		$active_record = $active_record_override;
	}

	$path = (defined('EE_APPPATH')) ? EE_APPPATH : APPPATH;
	require_once($path.'database/DB_driver.php');

	if ( ! isset($active_record) OR $active_record == TRUE)
	{
		require_once($path.'database/DB_active_rec.php');

		if ( ! class_exists('CI_DB'))
		{
			class_alias('CI_DB_active_record', 'CI_DB');
		}
	}
	else
	{
		if ( ! class_exists('CI_DB'))
		{
			class_alias('CI_DB_driver', 'CI_DB');
		}
	}

	require_once($path.'database/drivers/'.$params['dbdriver'].'/'.$params['dbdriver'].'_driver.php');

	// Instantiate the DB adapter
	$driver = 'CI_DB_'.$params['dbdriver'].'_driver';
	$DB = new $driver($params);

	if ($DB->autoinit == TRUE)
	{
		$DB->initialize();
	}

	if (isset($params['stricton']) && $params['stricton'] == TRUE)
	{
		$DB->query('SET SESSION sql_mode="STRICT_ALL_TABLES"');
	}

	return $DB;
}



/* End of file DB.php */
/* Location: ./system/database/DB.php */