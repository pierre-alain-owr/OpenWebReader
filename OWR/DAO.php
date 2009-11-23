<?php
/**
 * DAO Object base class 
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
use OWR\DB\Result as DBResult,
    OWR\DB\Request as DBRequest,
    OWR\Object as Object,
    OWR\Interfaces\DAO as iDAO;
/**
 * This class is used as base class for all DAO objects and defines all usefull functions
 * @abstract
 * @uses OWR\DB the database link
 * @uses OWR\Request a request sent to the database
 * @uses OWR\DB\Result a DB\Result from the database
 * @uses OWR\Exception the exceptions handler
 * @uses OWR\Object transforms an object to an associative array
 * @package OWR
 */
abstract class DAO implements iDAO
{
    /**
    * @var string the table name
    * @access protected
    */
    protected $_name = '';

    /**
    * @var string the class name
    * @access protected
    */
    protected $_fullName = '';

    /**
    * @var mixed the DB instance
    * @access protected
    */
    protected $_db;

    /**
    * @var array the list of fields of the table
    * @access protected
    */
    protected $_fields = array();

    /**
    * @var array the list of unique fields of the table
    * @access protected
    */
    protected $_uniqueFields = array();
    
    /**
    * @var string the name of the field used to have a unique ID
    * @access protected
    */
    protected $_idField = '';

    /**
    * @var array stored already processed dao names
    * @access private
    * @static
    */
    static private $_daos = array();

    /**
    * @var array stored already processed dao objects
    * @access private
    * @static
    */
    static private $_cachedDaos = array();

    /**
     * Constructor, sets the name/fullname of the instance, and set the DB obj
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    protected function __construct()
    {
        $this->_db = DB::iGet(); // we assume here that the connexion has already been done, be carefull
        $this->_fullName = get_called_class();
        $this->_name = strtolower(str_replace('\\', '_', substr($this->_fullName, strlen(__NAMESPACE__.'_DAO_'))));
    }


    /**
     * Gets rows from the database
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $args parameters, can be a string (if an $_idField has been declared), or an array
     * @param string $select select fields, by default all
     * @param string $order the order clause
     * @param string $groupby the groupby clause
     * @param string $limit the limit clause
     * @return mixed null if any, an object of the current DAO name if only one DBResult, or an array if more
     */
    public function get($args = null, $select = '*', $order = '', $groupby = '', $limit = '')
    {
        $uid = false;

        if(is_object($args))
        {
            $args = Object::toArray($args);
        }

        $fetchType = 'object';

        $query = '
    SELECT '.(string) $select.' 
        FROM '.$this->_name;

        $wheres = $request = array();

        if(is_array($args))
        {
            isset($args['FETCH_TYPE']) && ('assoc' === $args['FETCH_TYPE'] || 'array' === $args['FETCH_TYPE']) && ($fetchType = $args['FETCH_TYPE']) ||
            $fetchType = 'object';
            unset($args['FETCH_TYPE']);
            if(isset($args['uid']))
            {
                $uid = $args['uid'];
                unset($args['uid']);
            }

            foreach($this->_fields as $key=>$val)
            {
                !isset($args[$key]) || (($wheres[] = $key.'=?') && ($request[$key] = $args[$key]));
            }
        }
        elseif(isset($args) && $this->_idField)
        {
            $wheres[] = $this->_idField.'=?';
            $request[$this->_idField] = $args;
        }


        if(isset($this->_fields['uid']))
        {
            $request['uid'] = $uid ?: User::iGet()->getUid();
            $wheres[] = 'uid=?';
        }

        if(!empty($wheres))
        {
            $query .= ' 
        WHERE '.join(' AND ', $wheres);
        }

        if(!empty($groupby))
        {
            $query .= ' 
        GROUP BY '.(string) $order;
        }

        if(!empty($order))
        {
            $query .= ' 
        ORDER BY '.(string) $order;
        }

        if(!empty($limit))
        {
            $query .= ' 
        LIMIT '.(string) $limit;
        }

        $DBResults = array();
        $DBResult = $this->_db->getP($query, new DBRequest($request, $this->_fields, true), !empty($wheres));

        if('assoc' === $fetchType)
        {
            $DBResults = $DBResult->fetchAll(\PDO::FETCH_ASSOC);
        }
        elseif('array' === $fetchType)
        {
            $DBResults = $DBResult->fetchAll(\PDO::FETCH_NUM);
        }
        else
        {
            $DBResults = $DBResult->fetchAll(\PDO::FETCH_CLASS, $this->_fullName);
        }
        unset($DBResult);

        if(empty($DBResults)) return array();
        else return count($DBResults) === 1 ? $DBResults[0] : $DBResults;
    }

    /**
     * Saves a row into the database
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param boolean $ignore if we must save with a INSERT IGNORE clause instead of REPLACE INTO if no $_idField has been declared
     * @return mixed if success true if no $_idField declared or the value of the $_idField
     */
    public function save($ignore = false)
    {
        $requestFields = array();
        
        foreach($this->_fields as $key=>$val)
        {
            (!isset($this->$key) && 'uid' !== $key) || (($fields[] = $key) && ($requestFields[$key] = $val));
        }

        if(empty($requestFields))
        {
            throw new Exception('Aborting '.$this->_fullName.'::save(): no values to save, you surely have an error in your code');
        }

        $wheres = array();

        $checkUnique = $insert = $update = false;

        if($this->_idField)
        {
            unset($requestFields[$this->_idField]);
            if(isset($this->{$this->_idField}) && $this->{$this->_idField} > 0)
            {
                $update = true;
                $query = '
    UPDATE ';
                $wheres[] = $this->_idField;
                if(isset($this->_fields['uid']))
                {
                    $this->uid = $this->uid ?: User::iGet()->getUid();
                    empty($wheres) || ($wheres[] = 'uid');
                }
            }
            else
            {
                $insert = true;
                $query = '
    INSERT INTO ';
            }
        }
        elseif($ignore)
        {
            $query = '
    INSERT IGNORE INTO ';
        }
        else
        {
            $query = '
    REPLACE INTO ';
        }

        $query .= $this->_name.' 
        SET '.join("=?,", array_keys($requestFields)).'=?';

        if(!empty($wheres))
        {
            $query .= ' 
        WHERE ';
            $whereFields = array();
            foreach($wheres as $val)
            {
                $whereFields[] = $val.'='.$this->_db->quote($this->$val);
            }
            $query .= join(' AND ', $whereFields);

            unset($whereFields);
        }

        unset($wheres);

        if(!empty($this->_uniqueFields))
        {
            $whereFields = $whereFieldsDecl = array();

            foreach($this->_uniqueFields as $uField=>$required)
            {
                empty($this->$uField) || (($whereFields[] = $uField) && ($whereFieldsDecl[$uField] = $this->_fields[$uField]));
            }

            if($whereFields)
            {
                $chkUniQuery = '
    SELECT COUNT('.($this->_idField ?: '*').') AS nb
        FROM '.$this->_name.'
        WHERE ';
                $chkUniQuery .= '('.join('=? OR ', $whereFields).'=?)';

                if(isset($this->_fields['uid']))
                {
                    $this->uid = $this->uid ?: User::iGet()->getUid();
                    $chkUniQuery .= ' AND uid='.$this->uid;
                }
    
                if($this->_idField)
                {
                    if(isset($this->{$this->_idField}) && $this->{$this->_idField} > 0)
                    {
                        $chkUniQuery .= ' AND '.$this->_idField.'!='.$this->_db->quote($this->{$this->_idField});
                    }
                    $exists = $this->_db->executeP($chkUniQuery, new DBRequest(Object::toArray($this), $whereFieldsDecl, true));
                    
                    if($exists->next() && $exists->nb > 0)
                    {
                        throw new Exception('Aborting '.$this->_fullName.'::save(): some values are not uniques', 409);
                    }
                    unset($exists);
                }
            }

            unset($whereFields, $whereFieldsDecl);
        }

        if($insert)
        {
            if('id' === $this->_idField)
            {
                $this->{$this->_idField} = DAO::getCachedDAO('objects')->getUniqueId($this->_name);
                $query .= ','.$this->_idField.'=?';
                $requestFields[$this->_idField] = $this->_fields[$this->_idField];
            }
            else
            {
                throw new Exception('Aborting '.$this->_fullName.'::save(): don\'t know what to save !', Exception::E_OWR_BAD_REQUEST);
            }
        }

        foreach($this->_fields as $field => $decl)
        {
            if(isset($requestFields[$field]))
            {
                if($decl['required'])
                {
                    if('uid' === $field)
                    {
                        $this->uid = User::iGet()->getUid();
                    }
                    else
                    {
                        if(!isset($this->$field))
                            throw new Exception('Aborting '.$this->_fullName.'::save(): missing value for required parameter : '.$field, Exception::E_OWR_BAD_REQUEST);

                        switch($decl['type'])
                        {
                            case DBRequest::PARAM_PASSWD:
                                if(!empty($this->{$this->_idField}))
                                    break;

                            case DBRequest::PARAM_RIGHTS:
                            case DBRequest::PARAM_LOGIN:
                            case DBRequest::PARAM_LANG:
                            case DBRequest::PARAM_EMAIL:
                            case DBRequest::PARAM_URL:
                            case DBRequest::PARAM_TIMEZONE:
                            case DBRequest::PARAM_HASH:
                            case DBRequest::PARAM_IP:
                            case \PDO::PARAM_STR:
                                if(empty($this->$field))
                                    throw new Exception('Aborting '.$this->_fullName.'::save(): missing value for required parameter : '.$field, Exception::E_OWR_BAd_REQUEST);

                            default:
                                break;
                        }
                    }
                }
                elseif(!isset($this->$field))
                {
                    unset($requestFields[$field]);
                }
            }
        }

        try
        {
            $this->_db->setP($query, new DBRequest(Object::toArray($this), $requestFields, $update));
        }
        catch(Exception $e)
        { // we catch here to rollback and throw another exception
            $this->_db->rollback();
            throw new Exception($e->getContent(), $e->getCode());
        }

        return $this->_idField ? $this->{$this->_idField} : true;
    }

    /**
     * Deletes row(s) from the database
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $args parameters, can be null (deletes himself if $_idField declared), a string (if an $_idField has been declared), or an array
     * @param string $limit the limit clause
     * @return boolean true on success
     */
    public function delete($args = null, $limit='')
    {
        $query = '
    DELETE FROM '.$this->_name;

        if(!isset($args))
        {
            if(!isset($this->_fields['uid']) && $this->_idField)
            {
                if(!isset($this->{$this->_idField}))
                    throw new Exception('Aborting '.$this->_fullName.'::delete(): can not delete nothing !', Exception::E_OWR_BAD_REQUEST);
                $args = $this->{$this->_idField};
            }
        }
        elseif(is_object($args))
        {
            $args = Object::toArray($args);
        }

        $uid = false;

        $wheres = $request = $requestFields = array();

        if(is_array($args))
        {
            if(isset($args['uid']))
            {
                $uid = $args['uid'];
                unset($args['uid']);
            }

            foreach($this->_fields as $key=>$val)
            {
                !isset($args[$key]) || (($wheres[] = $key.'=?') && ($request[$key] = $args[$key]) && ($requestFields[$key] = $this->_fields[$key]));
            }
            unset($args);
        }
        elseif(isset($args) && $this->_idField)
        {
            $wheres[] = $this->_idField.'=?';
            $request[$this->_idField] = $args;
            $requestFields[$this->_idField] = $this->_fields[$this->_idField];
        }

        if(isset($this->_fields['uid']))
        {
            $request['uid'] = $uid ?: ($this->uid ?: User::iGet()->getUid());
            $wheres[] = 'uid=?';
            $requestFields['uid'] = $this->_fields['uid'];
        }

        if($wheres)
        {
            $query .= '
    WHERE '.join(' AND ', $wheres);
        }

        if($limit)
        {
            $query .= '
    LIMIT '.$limit;
        }

        try
        {
            $this->_db->setP($query, new DBRequest($request, $requestFields), 'exec', !empty($request));
        }
        catch(Exception $e)
        { // we catch here to rollback and throw another exception
            $this->_db->rollback();
            throw new Exception($e->getContent(), $e->getCode());
        }

        return true;
    }

    /**
     * Populates values into this object
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $obj the values to populate
     * @return boolean true on success
     */
    public function populate($obj)
    {
        if(is_object($obj))
        {
            if($obj instanceof DBResult)
            {
                foreach($obj as $key => $val)
                {
                    empty($val) || $this->$key = $val;
                }
            }
            else
            {
                foreach($this->_fields as $field=>$values)
                {
                    (!isset($obj->$field) || (!empty($values) && $this->$field = $obj->$field));
                }
            }

            return true;
        }
        elseif(is_array($obj))
        {
            foreach($this->_fields as $field=>$values)
            {
                (!isset($obj[$field]) || (!empty($values) && $this->$field = $obj[$field]));
            }

            return true;
        }

        return false;
    }

    /**
     * Returns the unique fields
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return array the unique fields for this object
     */
    public function getUniqueFields()
    {
        return (array) $this->_uniqueFields;
    }

    /**
     * Returns the fields
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return array the fields for this object
     */
    public function getFields()
    {
        return (array) $this->_fields;
    }

    /**
     * Returns the id field
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return string the id field for this object
     */
    public function getIdField()
    {
        return (string) $this->_idField;
    }

    /**
     * Returns the specified DAO object
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $dao the name of the DAO
     * @return mixed the DAO object
     */
    static public function getDAO($dao)
    {
        if(!isset(self::$_daos[$dao]))
        {
            $c = __NAMESPACE__.'\DAO\\'.join('\\', array_map('ucfirst', explode('_', $dao)));
            self::$_daos[$dao] = $c;
        }
        return new self::$_daos[$dao];
    }

    /**
     * Returns the specified DAO object from cache
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $dao the name of the DAO
     * @return mixed the DAO object
     */
    static public function getCachedDAO($dao)
    {
        $dao = ucfirst((string) $dao);
        isset(self::$_cachedDaos[$dao]) || (self::$_cachedDaos[$dao] = self::getDAO($dao));

        return self::$_cachedDaos[$dao];
    }

    /**
     * Returns the specified DAO object
     * This function accepts an ID and will return the DAO for the corresponding type
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param int $id the id of the object
     * @return mixed the DAO object if found, else false
     */
    static public function getById($id)
    {
        $type = self::getType($id);
        return $type ? self::getCachedDAO($type->type)->get($id) : false;
    }

    /**
     * Returns the type relative to the specified id
     * This method also checks for user rights to read it
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param int $id the id to get type from
     * @return mixed false on error, or the type corresponding to the id
     * @access public
     * @static
     */
    static public function getType($id)
    {
        $id = (int)$id;

        $type = DB::iGet()->getOne('
    SELECT type
        FROM objects
        WHERE id='.$id);

        if(!$type->next()) return false;
        elseif(CLI) return $type->type;

        switch($type->type)
        {
            case 'streams':
                $field = 'rssid';
                $table = 'streams_relations';
                break;

            case 'streams_groups':
                $field = 'id';
                $table = $type->type;
                break;

            case 'news':
                $field = 'newsid';
                $table = 'news_relations';
                break;

            case 'users':
                if(User::iGet()->isAdmin()) return $type->type;
                throw new Exception('Invalid id', Exception::E_OWR_UNAUTHORIZED);
                break;

            default:
                throw new Exception('Invalid id', Exception::E_OWR_BAD_REQUEST);
                break;
        }

        $valid = self::getCachedDAO($table)->get(array($field => $id), $field);

        if(!$valid)
        {
            throw new Exception('Invalid id', Exception::E_OWR_BAD_REQUEST);
        }

        return $type->type;
    }
}