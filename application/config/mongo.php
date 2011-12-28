<?php
/**
 * MongoDB Configuration
 *
 * We have one configuration file by default, but you can copy this file to
 * config/development, config/production, etc to have different configurations
 * for different enviorments.
 *
 * @package    FireLogs
 * @subpackage DB
**/

/**
 * Server Name
 *
 * @global     string
**/
$config['mongo_host'] = 'localhost';
$config['mongo_port'] = 27017;

/**
 * Server Username
 *
 * @global     string
**/
$config['mongo_user'] = null;

/**
 * Server Password
 *
 * @global     string
**/
$config['mongo_pass'] = null;

$config['mongo_db'] = 'firelogs';

$config['mongo_persist'] = TRUE;
$config['mongo_persist_key'] = 'ci_mongo_persist';
/* End of file mongo.php */