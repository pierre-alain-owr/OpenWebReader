<?php
/**
 * Interface for DB object
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
 * @license http://www.gnu.org/copyleft/gpl.html
 * @package OWR
 * @subpackage Interfaces
 */
namespace OWR\Interfaces;
use OWR\DB\Request,
    OWR\DB\Result;
/**
 * This class is used to declare public functions
 * @package OWR
 * @subpackage Interfaces
 */
interface DB
{
    /**
     * Constructor
     * It will try to connect to the database and set utf8 character set
     * @ and set the default cache life time
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function __construct();

    /**
     * Singleton pattern getter
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return mixed instance of this object
     */
    static public function iGet();

    /**
     * Sets the cache lifetime
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param int $cacheTime the cache time, in seconds
     */
    public function setCacheTime($cacheTime);

    /**
     * Commits to the database
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $stmt the PDOStatement to unset, optionnal
     */
    public function commit($stmt = null);

    /**
     * Changes database
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $db the database name to connect
     */
    public function setDB($db);

    /**
     * Starts transaction mode
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function beginTransaction();

    /**
     * Executes a prepared query (cached)
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param OWR\Request $datas the request
     * @return OWR\DB\Result the result
     */
    public function cExecuteP($sql, Request $datas);

    /**
     * Executes a query (cached)
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param OWR\Request $datas the request
     * @param boolean $prepare prepare or not the query
     * @return OWR\DB\Result the result
     */
    public function cExecute($sql, Request $datas = null, $prepare = false);

    /**
     * Executes a prepared query
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param OWR\Request $datas the request
     * @return mixed an Result
     */
    public function executeP($sql, Request $datas = null);

    /**
     * Executes a query
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param OWR\Request $datas the request
     * @param boolean $prepare prepare or not the query
     * @return OWR\DB\Result the result
     */
    public function execute($sql, Request $datas = null, $prepare = false);

    /**
     * Executes a query (cached) and returns all the rows
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param string $action the action
     * @return OWR\DB\Result the result
     */
    public function cGetAll($sql, $action = "query");

    /**
     * Executes a prepared query (cached) and returns all the rows
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param OWR\Request $datas the request
     * @param string $action the action
     * @param boolean $prepare prepare or not the query
     * @return OWR\DB\Result the result
     */
    public function cGetAllP($sql, Request $datas = null, $action = "query", $prepare = true);

    /**
     * Executes a query (cached) and returns the first row
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @return OWR\DB\Result the result
     */
    public function cGetRow($sql);

    /**
     * Executes a prepared query (cached) and returns the first row
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param OWR\Request $datas the request
     * @param string $prepared prepare or not the query
     * @return OWR\DB\Result the result
     */
    public function cGetRowP($sql, Request $datas = null, $prepared = true);

    /**
     * Executes a prepared query (cached) and returns the first field from the first row
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param OWR\Request $datas the request
     * @param string $prepared prepare or not the query
     * @return OWR\DB\Result the result
     */
    public function cGetOneP($sql, Request $datas = null, $prepared = true);

    /**
     * Executes a query (cached) and returns the first field from the first row
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @return OWR\DB\Result the result
     */
    public function cGetOne($sql);

    /**
     * Executes a query and returns all the rows
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param string $action the action
     * @return OWR\DB\Result the result
     */
    public function getAll($sql, $action = 'query');

    /**
     * Executes a prepared query and returns all the rows
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param OWR\Request $datas the request
     * @param string $action the action
     * @param string $prepare prepare or not the query
     * @return OWR\DB\Result the result
     */
    public function getAllP($sql, Request $datas = null, $action = "query", $prepare = true);

    /**
     * Executes a query and returns the result
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param OWR\Request $datas the request
     * @param string $action the action
     * @param boolean $returnID shall we return ID of the inserted row
     * @return mixed a PDOStatement or the inserted ID
     */
    public function get($sql, Request $datas = null, $action = "query", $returnId = false);

    /**
     * Executes a prepared query and returns the result
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param OWR\Request $datas the request
     * @param string $action the action
     * @param string $prepare prepare or not the query
     * @param boolean $returnID shall we return ID of the inserted row
     * @return mixed a PDOStatement or the inserted ID
     */
    public function getP($sql, Request $datas = null, $action = "query", $prepare = true, $returnId = false);

    /**
     * Executes a query and returns the first row of the result
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @return OWR\DB\Result the result
     */
    public function getRow($sql);

    /**
     * Executes a prepared query and returns the first row of the result
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param OWR\Request $datas the request
     * @param boolean $prepared prepare the query or not
     * @return OWR\DB\Result the result
     */
    public function getRowP($sql, Request $datas = null, $prepared = true);

    /**
     * Executes a prepared query and returns the first field of the first row of the result
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param OWR\Request $datas the request
     * @param boolean $prepared prepare the query or not
     * @return OWR\DB\Result the result
     */
    public function getOneP($sql, Request $datas = null, $prepared = true);

    /**
     * Executes a query and returns the first field of the first row of the result
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @return OWR\DB\Result the result
     */
    public function getOne($sql);

    /**
     * Executes a query and returns the result
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param OWR\Request $datas the request
     * @param string $action the action
     * @param boolean $returnId returns the id of the inserted row
     * @return mixed the result/statement/id
     */
    public function set($sql, Request $datas = null, $action = "exec", $returnId = false);

    /**
     * Executes a prepared query and returns the result
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param OWR\Request $datas the request
     * @param string $action the action
     * @param string $prepare prepare the query or not
     * @param boolean $returnId returns the id of the inserted row
     * @return mixed the result/statement/id
     */
    public function setP($sql, Request $datas = null, $action = "exec", $prepare = true, $returnId = false);

    /**
     * Rollback function for transaction mode
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function rollback();

    /**
     * Function to quote values (sanitize)
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed string or array to sanitize
     */
    public function sanitize(&$datas);

    /**
     * Returns the added microtime of all SQL queries
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return float $_queryTime
     */
    static public function getTime();
}
