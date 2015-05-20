<?php
/**
 * Config file for OpenWebReader installation
 *
 * PHP 5
 *
 * OWR - OpenWebReader
 *
 * Copyright (c) 2009, Pierre-Alain Mignot
 *
 * Home page: http://openwebreader.org
 *
 * E-Mail: contact@openwebreader.org
 *
 * All Rights Reserved
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 * @author Pierre-Alain Mignot <contact@openwebreader.org>
 * @copyright Copyright (c) 2009, Pierre-Alain Mignot
 * @licence http://www.gnu.org/copyleft/gpl.html
 * @package OWR
 */
namespace OWR;
if(defined('INC_CONFIG'))
    die("can't include config file more than one time");
if(!defined('PATH') || !defined('HOME_PATH'))
    die('please define constants HOME and HOME_PATH before including the config file');

define('OWR_THEMES_PATH', HOME_PATH . 'Includes' . DIRECTORY_SEPARATOR . 'Themes' . DIRECTORY_SEPARATOR);
define('OWR_PLUGINS_PATH', HOME_PATH . 'Includes' . DIRECTORY_SEPARATOR . 'Plugins' . DIRECTORY_SEPARATOR);

// init UTF8 support
if(!function_exists('mb_internal_encoding') || !mb_internal_encoding('UTF-8'))
    die("Can not set internal encoding to utf8");

ob_start();
set_time_limit(0);
gc_enabled() || gc_enable();
$cfg = array();
$cfg['begintime'] = microtime(true);

/**************************************** START EDITING HERE ****************************************/
/**
 * Please have a look at http://trac.openwebreader.org/wiki/Configuration
 */
/* db config */
$cfg['dbname']                  = 'openwebreader';
$cfg['dbhost']                  = 'localhost';
$cfg['dbport']                  = '3306';
$cfg['dbdriver']                = 'mysql';
$cfg['dbuser']                  = 'openwebreader';
$cfg['dbpasswd']                = 'openwebreader';
// generally /var/run/mysqld.sock or /tmp/mysql.sock
$cfg['dbsocket']                = '/var/run/mysqld/mysqld.sock';
$cfg['dsn']                     = $cfg['dbdriver'].':dbname='.$cfg['dbname'] .
                                    ';host='.$cfg['dbhost'].';port='.$cfg['dbport'] .
                                    ';unix_socket='.$cfg['dbsocket'];
$cfg['dbCacheTime']             = 86400 * 7; // 24h * 7

/* host config */
$cfg['url']                     = 'openwebreader.mydomain.tld';
$cfg['path']                    = '/'; // MUST ends with '/'
$cfg['httpsecure']              = false; // set to true if owr will be used over SSL (HTTPS)
$cfg['surl']                    = 'http'.($cfg['httpsecure'] ? 's' : '').'://'.$cfg['url'].$cfg['path'];

/* templates cache time, default to 24h * 7 */
$cfg['cacheTime']               = 86400 * 7;
/* use file or memcache as cache */
$cfg['cacheType']               = 'file';
/* memcache hosts, for multiples server add comma-separated values */
$cfg['memcache']                = '127.0.0.1:11211';
/* session life time, default to 24h */
$cfg['sessionLifeTime']         = 86400;

/* server config */
// default temporary directory
// please keep this OUT of public web server access
$cfg['defaultTmpDir']           = sys_get_temp_dir().DIRECTORY_SEPARATOR;
// max upload file size, in octets, default to 5mo
$cfg['maxUploadFileSize']       = 5120000;
// path to php executable
// please let empty open_basedir
$cfg['phpbin']                  = '/usr/bin/php -d open_basedir= ';
// default ttl, in minutes, used if no ttl is found while parsing a stream
$cfg['defaultStreamRefreshTime'] = 60;
// default minimum ttl, in minutes, used for checking ttl found while parsing a stream
$cfg['defaultMinStreamRefreshTime'] = 30;
// set to "index" for uri like /index.php?do=action, "action" for uri like /action?
// look at file HOME/.htaccess for url-rewriting
$cfg['uriStyle']                = 'index';
// max log file size, in octets, default to 5mo
$cfg['maxLogFileSize']          = 5120000;
// maximum number of PHP threads that OWR can launch simultaneously
// if you have an old server, maybe set this to 2, else 5 is not bad
$cfg['maxThreads']              = 5;
// nice command used to lower process priority, default "nice -n 10 "
// (MUST ends with a space if you use it)
$cfg['nicecmd']                 = 'nice -n 10 ';
// command used to get the number of process used by OWR, default 'ps aux | grep "%s" | grep -v grep'
$currentUser                    = @posix_getpwuid(posix_getuid());
if(!empty($currentUser['name']))
    $cfg['grepcmd']                 = 'pgrep -u '.$currentUser['name'].' -f "%s"';
else
    $cfg['grepcmd']                 = 'ps aux | grep "%s" | grep -v grep';
unset($currentUser);

/* default intl config */
$cfg['date_default_timezone']   = 'Europe/Paris';
$cfg['default_language']        = 'fr_FR';

// activate debug mode here
define('DEBUG', true);
/**************************************** STOP EDITING HERE ****************************************/

// version
$cfg['version']                 = '0.2.1';

// CLI call ?
define('CLI', 'cli' === PHP_SAPI);

// REST call ?
define('REST', isset($_SERVER['SCRIPT_FILENAME']) && basename($_SERVER['SCRIPT_FILENAME']) === 'rest.php');

// AJAX call ?
define('AJAX', isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');

/**
 * Defines classes autoloading
 *
 * @author Pierre-Alain Mignot <contact@openwebreader.org>
 * @param string $c The classname to load
 */
spl_autoload_register(function($c)
{
    if(class_exists($c, false)) return true;

    $ext = false;
    $internal = false;

    if(false === ($ns = strpos($c, '\\')) && false === ($ext = strpos($c, '_'))) return false;

    $f = false;

    if(false !== $ext)
    { // external libraries
        $f = HOME_PATH."libs".DIRECTORY_SEPARATOR.str_replace('_', DIRECTORY_SEPARATOR, $c).'.php';
        if(!file_exists($f))
            return false;
    }
    elseif(false !== $ns)
    {
        $f = PATH.str_replace('\\', DIRECTORY_SEPARATOR, str_replace('_', DIRECTORY_SEPARATOR, $c)).".php";
    }
    else
    {
        $f = HOME_PATH."libs".DIRECTORY_SEPARATOR.strtolower($c).".class.php";
    }

    if(!file_exists($f))
    {
        return false;
    }

    if(!include($f))
    {
        return false;
    }

    return class_exists($c, false);
});

Config::iGet($cfg); // init the config object
// reset values from memory
$cfg = null;
unset($cfg);

// we are set !
define('INC_CONFIG', true);
