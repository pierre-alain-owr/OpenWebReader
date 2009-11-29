<?php
/**
 * OWRDB class
 * This class extends PDO, and can be used as a singleton (or not !)
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
 */
namespace OWR;
use \PDO as PDO,
    OWR\DB\Request as DBRequest,
    OWR\DB\Result as Result,
    OWR\Interfaces\DB as iDB;
/**
 * This object is the link to the database
 * It can be used as a singleton (see function self::iGet())
 * @uses Request a request sent to the database
 * @uses Result a result from database
 * @uses Exception the exceptions handler
 * @uses Cache get/save results from/to cache
 * @package OWR
 */
class DB extends PDO implements iDB
{
    /**
    * @var mixed an instance of this class to be used as a singleton
    * @access protected
    * @static
    */
    static protected $_instance;
    
    /**
    * @var array stored prepared statements
    * @access protected
    */
    protected $_stmts = array();
    
    /**
    * @var boolean have we made modifications in the database ?
    * @access protected
    */
    protected $_hasSet = false;
    
    /**
    * @var int are we in transaction mode ?
    * @access protected
    */
    protected $_transaction = 0;
    
    /**
    * @var int cache time
    * @access protected
    */
    protected $_cacheTime = 0;

    /**
    * @var float total time of all executed queries
    * @access protected
    * @static
    */
    static protected $_queryTime = 0;
    
    /**
     * Constructor
     * It will try to connect to the database and set utf8 character set
     * @ and set the default cache life time
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function __construct() 
    {
        $cfg = Config::iGet();
        try
        {
            parent::__construct($cfg->get('dsn'), $cfg->get('dbuser'), $cfg->get('dbpasswd'));
            $this->setAttribute(self::ATTR_ERRMODE, self::ERRMODE_EXCEPTION);
            $this->setAttribute(self::ATTR_ORACLE_NULLS, self::NULL_TO_STRING);
            $this->exec("SET NAMES UTF8");
        }
        catch(\Exception $e)
        {
            $error = 'SQL Error: Q="CONSTRUCT + UTF8 SUPPORT", R="'.$e->getMessage().'"';

            if(!DEBUG && !User::iGet()->isAdmin())
            {
                Logs::iGet()->log($error, Exception::E_OWR_DIE);
            }
            else
            {
                $error = 'SQL error';
            }

            throw new Exception($error);
        }
        $this->setCacheTime($cfg->get('dbCacheTime'));
    }

    /**
     * Singleton pattern getter
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return mixed instance of this object
     */
    static public function iGet()
    {
        if(!isset(self::$_instance))
        {
            $c = get_called_class();
            self::$_instance = new $c();
        }
        return self::$_instance;
    }
    
    /**
     * Sets the cache lifetime
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param int $cachetime the cache time, in seconds
     */
    public function setCacheTime($cacheTime)
    {
        $this->_cacheTime = (int) $cacheTime;
    }
    
    /**
     * Commits to the database
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $stmt the PDOStatement to unset, optionnal
     */
    public function commit($stmt = null)
    {
        if($this->_hasSet && 1 <= $this->_transaction)
        {
            try
            {
                parent::commit();
            }
            catch(\Exception $e)
            {
                $error = 'SQL Error: Q="COMMIT", R="'.$e->getMessage().'"';
    
                if(!DEBUG && !User::iGet()->isAdmin())
                {
                    Logs::iGet()->log($error, Exception::E_OWR_DIE);
                }
                else
                {
                    $error = 'SQL error';
                }
    
                throw new Exception($error);
            }

            $this->_transaction = 0;
            $this->_hasSet = false;
        }
            
        if(!isset($stmt)) return;

        if($stmt instanceof \PDOStatement)
        {
            try
            {
                $stmt->closeCursor();
                unset($stmt);
            }
            catch(\Exception $e)
            {
                $error = 'SQL Error: Q="COMMIT::CLOSECURSOR", R="'.$e->getMessage().'"';
    
                if(!DEBUG && !User::iGet()->isAdmin())
                {
                    Logs::iGet()->log($error, Exception::E_OWR_DIE);
                }
                else
                {
                    $error = 'SQL error';
                }
    
                throw new Exception($error);
            }
        }
        elseif(is_string($stmt) && isset($this->_stmts[$stmt]) && $this->_stmts[$stmt] instanceof \PDOStatement)
        {
            try
            {
                $this->_stmts[$stmt]->closeCursor();
                unset($this->_stmts[$stmt]);
            }
            catch(\Exception $e)
            {
                $error = 'SQL Error: Q="COMMIT::CLOSECURSOR", R="'.$e->getMessage().'"';
    
                if(!DEBUG && !User::iGet()->isAdmin())
                {
                    Logs::iGet()->log($error, Exception::E_OWR_DIE);
                }
                else
                {
                    $error = 'SQL error';
                }
    
                throw new Exception($error);
            }
        }
    }
    
    /**
     * Changes database
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $db the database name to connect
     */
    public function setDB($db) 
    {
        try
        {
            $this->exec("USE ".(string) $db);
        }
        catch(\Exception $e)
        {
            $error = 'SQL Error: Q="selectDB", R="'.$e->getMessage().'"';

            if(!DEBUG && !User::iGet()->isAdmin())
            {
                Logs::iGet()->log($error, Exception::E_OWR_DIE);
            }
            else
            {
                $error = 'SQL error';
            }

            throw new Exception($error);
        }
    }
    
    /**
     * Starts transaction mode
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function beginTransaction()
    {
        if(0 === $this->_transaction)
        {
            try
            {
                parent::beginTransaction();
            }
            catch(\Exception $e)
            {
                $error = 'SQL Error: Q="BEGINTRANSACTION", R="'.$e->getMessage().'"';
    
                if(!DEBUG && !User::iGet()->isAdmin())
                {
                    Logs::iGet()->log($error, Exception::E_OWR_DIE);
                }
                else
                {
                    $error = 'SQL error';
                }
    
                throw new Exception($error);
            }
            $this->_transaction = 1;
        }
        else
        {
            ++$this->_transaction;
        }
    }

    /**
     * Executes a prepared query (cached)
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param mixed $datas the request
     * @return mixed a Result
     */
    public function cExecuteP($sql, DBRequest $datas)
    {
        return $this->cExecute($sql, $datas, true);
    }

    /**
     * Executes a query (cached)
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param mixed $datas the request
     * @param boolean $prepare prepare or not the query
     * @return mixed a Result
     */
    public function cExecute($sql, DBRequest $datas = null, $prepare = false)
    {
        if(!$this->_cacheTime) return $this->execute($sql, $datas, $prepare);
        
        $filename = md5(serialize(func_get_args()));
        if($contents = Cache::get('db'.DIRECTORY_SEPARATOR.$filename, $this->_cacheTime)) return $contents;

        $result = $this->execute($sql, $datas, $prepare);
        if($result)
            Cache::write('db'.DIRECTORY_SEPARATOR.$filename, $result);
        return $result;
    }

    /**
     * Executes a prepared query
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param mixed $datas the request
     * @return mixed an Result
     */
    public function executeP($sql, DBRequest $datas = null)
    {
        return $this->execute($sql, $datas, true);
    }

    /**
     * Executes a query
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param mixed $datas the request
     * @param boolean $prepare prepare or not the query
     * @return mixed a Result
     */
    public function execute($sql, DBRequest $datas = null, $prepare = false)
    {
        return new Result($this->_executeSQL($sql, $datas, 'query', $prepare) ?: null);
    }

    /**
     * Main execute function
     * All public functions call this function
     *
     * @access protected
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param mixed $datas the request
     * @param string $action the action, if not prepared (can be 'query' or 'exec')
     * @param boolean $prepare prepare or not the query
     * @param boolean $returnId if a row is inserted, returns the id
     * @return mixed the result/statement/id
     */
    protected function _executeSQL($sql, DBRequest $datas = null, $action = "exec", 
                        $prepare = true, $returnId = false)
    {
        $sql = (string)$sql;
        $sqlTime = microtime(true);

        if($prepare)
        {
            try
            {
                if(!isset($this->_stmts[$sql])) {
                    $this->_stmts[$sql] = $this->prepare($sql);
                }
                $num = 0;
                foreach($datas as $k=>$v)
                {
                    0 !== $k || $num = 1;

                    if(is_array($v))
                    {
                        $this->_stmts[$sql]->bindValue($k+$num, $v['value'], $v['type']);
                    }
                    else
                    {
                        $this->_stmts[$sql]->bindValue($k+$num, $v);
                    }
                }
            }
            catch(\Exception $e)
            {
                $error = 'SQL Error: Q="PREPARE+BIND '.$sql.'", R="'.
                    $e->getMessage().'", D='.var_export($datas, true);

                if(!DEBUG && !User::iGet()->isAdmin())
                {
                    Logs::iGet()->log($error, Exception::E_OWR_DIE);
                    $error = 'SQL error';
                }

                throw new Exception($error);
            }

            try
            {
                if(!$this->_stmts[$sql]->execute() && '00000' !== (string)$this->_stmts[$sql]->errorCode())
                {
                    $error = 'SQL Error: Q="'.trim($sql).'", R="'.
                            var_export($this->_stmts[$sql]->errorInfo(), true).'", D='.var_export($datas, true);
    
                    if(!DEBUG && !User::iGet()->isAdmin())
                    {
                        Logs::iGet()->log($error, Exception::E_OWR_DIE);
                        $error = 'SQL error';
                    }
    
                    throw new Exception($error);
                }
            }
            catch(\Exception $e)
            {
                $error = 'SQL Error: Q="'.trim($sql).'", R="'.
                            var_export($e->getMessage(), true).'", D='.var_export($datas, true);

                if(!DEBUG && !User::iGet()->isAdmin())
                {
                    Logs::iGet()->log($error, Exception::E_OWR_DIE);
                    $error = 'SQL error';
                }

                throw new Exception($error);
            }

            if($returnId)
            {
                try
                {
                    $ret = $this->lastInsertId();
                    $this->_stmts[$sql]->closeCursor();
                }
                catch(\Exception $e)
                {
                    $error = 'SQL Error: Q="RETURNID '.$sql.'", R="'.
                        $e->getMessage().'", D='.var_export($datas, true);

                    if(!DEBUG && !User::iGet()->isAdmin())
                    {
                        Logs::iGet()->log($error, Exception::E_OWR_DIE);
                        $error = 'SQL error';
                    }
    
                    throw new Exception($error);
                }
            }
            else $ret = $this->_stmts[$sql];
        }
        else
        {
            $action = (string)$action;

            if('query' !== $action && 'exec' !== $action)
            {
                $error = 'SQL Error: Q="'.trim($sql).'", R="Unknown DB Action '.$action.'"';

                if(!DEBUG && !User::iGet()->isAdmin())
                {
                    Logs::iGet()->log($error, Exception::E_OWR_DIE);
                    $error = 'SQL error';
                }

                throw new Exception($error);
            }
            
            try 
            {
                $ret = $this->$action($sql);
            } 
            catch (\Exception $e) 
            {
                $error = 'SQL Error: Q="'.trim($sql).'", R="'.$e->getMessage().'"';

                if(!DEBUG && !User::iGet()->isAdmin())
                {
                    Logs::iGet()->log($error, Exception::E_OWR_DIE);
                    $error = 'SQL error';
                }

                throw new Exception($error);
            }

            if(false === $ret)
            {
                $error = 'SQL Error: Q="'.trim($sql).'", R="'.$this->errorInfo().'"';

                if(!DEBUG && !User::iGet()->isAdmin())
                {
                    Logs::iGet()->log($error, Exception::E_OWR_DIE);
                    $error = 'SQL error';
                }

                throw new Exception($error);
            }
            
            if($returnId) 
            {
                try
                {
                    $ret = $this->lastInsertId();
                }
                catch(\Exception $e)
                {
                    $error = 'SQL Error: Q="RETURNID '.$sql.'", R="'.$e->getMessage().'"';
    
                    if(!DEBUG && !User::iGet()->isAdmin())
                    {
                        Logs::iGet()->log($error, Exception::E_OWR_DIE);
                        $error = 'SQL error';
                    }
    
                    throw new Exception($error);
                }
            }
        }

        self::$_queryTime += (float)(microtime(true) - $sqlTime);

        return $ret;
    }

    /**
     * Executes a query (cached) and returns all the rows
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param string $action the action
     * @return mixed a Result
     */
    public function cGetAll($sql, $action = "query")
    {
        return $this->cGetAllP($sql, null, $action, false);
    }

    /**
     * Executes a prepared query (cached) and returns all the rows
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param mixed $datas the request
     * @param string $action the action
     * @param boolean $prepare prepare or not the query
     * @return mixed a Result
     */
    public function cGetAllP($sql, DBRequest $datas = null, $action = "query", $prepare = true)
    {
        if(!$this->_cacheTime) return $this->getAllP($sql, $datas, $action);
        
        $filename = md5(serialize(func_get_args()));
        if($contents = Cache::get('db'.DIRECTORY_SEPARATOR.$filename, $this->_cacheTime)) return $contents;
        
        $result = new Result($this->_executeSQL($sql, $datas, $action, $prepare) ?: null);
        Cache::write('db'.DIRECTORY_SEPARATOR.$filename, $result);
        return $result;
    }

    /**
     * Executes a query (cached) and returns the first row
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @return mixed a Result
     */
    public function cGetRow($sql)
    {
        return $this->cGetRowP($sql, null, false);
    }

    /**
     * Executes a prepared query (cached) and returns the first row
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param mixed $datas the request
     * @param string $prepared prepare or not the query
     * @return mixed a Result
     */
    public function cGetRowP($sql, DBRequest $datas = null, $prepared = true)
    {
        if(!$this->_cacheTime) return $this->getRowP($sql, $datas, $prepared);
        
        $filename = md5(serialize(func_get_args()));
        if($contents = Cache::get('db'.DIRECTORY_SEPARATOR.$filename, $this->_cacheTime)) return $contents;
        
        $result = new Result($this->_executeSQL($sql, $datas, 'query', $prepared) ?: null, Result::FETCH_ROW);
        Cache::write('db'.DIRECTORY_SEPARATOR.$filename, $result);
        return $result;
    }

    /**
     * Executes a prepared query (cached) and returns the first field from the first row
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param mixed $datas the request
     * @param string $prepared prepare or not the query
     * @return mixed a Result
     */
    public function cGetOneP($sql, DBRequest $datas = null, $prepared = true)
    {
        if(!$this->_cacheTime) return $this->getOneP($sql, $datas, false);
        
        $filename = md5(serialize(func_get_args()));
        if($contents = Cache::get('db'.DIRECTORY_SEPARATOR.$filename, $this->_cacheTime)) return $contents;
        
        $result = new Result($this->_executeSQL($sql, $datas, 'query', $prepared) ?: null, Result::FETCH_ONE);
        Cache::write('db'.DIRECTORY_SEPARATOR.$filename, $result);
        return $result;
    }

    /**
     * Executes a query (cached) and returns the first field from the first row
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param mixed $datas the request
     * @param string $prepared prepare or not the query
     * @return mixed a Result
     */
    public function cGetOne($sql)
    {
        return $this->cGetOneP($sql, null, false);
    }

    /**
     * Executes a query and returns all the rows
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param string $action the action
     * @return mixed a Result
     */
    public function getAll($sql, $action = 'query')
    {
        return $this->getAllP($sql, null, $action, false);
    }

    /**
     * Executes a prepared query and returns all the rows
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param mixed $datas the request
     * @param string $action the action
     * @param string $prepared prepare or not the query
     * @return mixed a Result
     */
    public function getAllP($sql, DBRequest $datas = null, $action = "query", $prepare = true)
    {
        $result = new Result($this->_executeSQL($sql, $datas, $action, $prepare, false) ?: null);
        return $result;
    }

    /**
     * Executes a query and returns the result
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param mixed $datas the request
     * @param string $action the action
     * @param string $prepared prepare or not the query
     * @return mixed a PDOStatement or the inserted ID
     */
    public function get($sql, DBRequest $datas = null, $action = "query", $returnId = false) 
    {
        return $this->getP($sql, $datas, $action, false, $returnId);
    }

    /**
     * Executes a prepared query and returns the result
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param mixed $datas the request
     * @param string $action the action
     * @param string $prepared prepare or not the query
     * @return mixed a PDOStatement or the inserted ID
     */
    public function getP($sql, DBRequest $datas = null, $action = "query", $prepare = true, $returnId = false) 
    {
        return $this->_executeSQL($sql, $datas, $action, $prepare, $returnId);
    }

    /**
     * Executes a query and returns the first row of the result
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @return mixed a Result
     */
    public function getRow($sql)
    {
        return $this->getRowP($sql, null, false);
    }

    /**
     * Executes a prepared query and returns the first row of the result
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param mixed $datas the request
     * @param boolean $prepared prepare the query or not
     * @return mixed a Result
     */
    public function getRowP($sql, DBRequest $datas = null, $prepared = true)
    {
        return new Result($this->_executeSQL($sql, $datas, 'query', $prepared) ?: null, Result::FETCH_ROW);
    }

    /**
     * Executes a prepared query and returns the first field of the first row of the result
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param mixed $datas the request
     * @param boolean $prepared prepare the query or not
     * @return mixed a Result
     */
    public function getOneP($sql, DBRequest $datas = null, $prepared = true)
    {
        return new Result($this->_executeSQL($sql, $datas, 'query', $prepared) ?: null, Result::FETCH_ONE);
    }

    /**
     * Executes a query and returns the first field of the first row of the result
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param mixed $datas the request
     * @param boolean $prepared prepare the query or not
     * @return mixed a Result
     */
    public function getOne($sql)
    {
        return $this->getOneP($sql, null, false);
    }

    /**
     * Executes a query and returns the result
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param mixed $datas the request
     * @param string $action the action
     * @param boolean $returnId returns the id of the inserted row
     * @return mixed the result/statement/id
     */
    public function set($sql, DBRequest $datas = null, $action = "exec", 
                        $returnId = false)
    {
        return $this->_executeSQL($sql, $datas, $action, false, $returnId);
    }

    /**
     * Executes a prepared query and returns the result
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $sql the query
     * @param mixed $datas the request
     * @param string $action the action
     * @param string $prepare prepare the query or not
     * @param boolean $returnId returns the id of the inserted row
     * @return mixed the result/statement/id
     */
    public function setP($sql, DBRequest $datas = null, $action = "exec", $prepare = true, $returnId = false)
    {
        $this->_hasSet = true;
        return $this->_executeSQL($sql, $datas, $action, true, $returnId);
    }
    
    /**
     * Rollback function for transaction mode
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function rollback()
    {
        if($this->_transaction && $this->_hasSet)
        {
            parent::rollback();
            $this->_transaction = 0;
        }
    }
    
    /**
     * Function to quote values (sanitize)
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed string or array to sanitize
     */
    public function sanitize(&$datas)
    {
        foreach($datas as $k => $v)
        {
            if(is_array($v))
                $this->sanitize($datas[$k]);
            else
                $datas[$k] = $this->quote($v);
        }
    }

    /**
     * Returns the added microtime of all SQL queries
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed string or array to sanitize
     * @return float $_queryTime
     */
    static public function getTime()
    {
        return (float) self::$_queryTime;
    }
}