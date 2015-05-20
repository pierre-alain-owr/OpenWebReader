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
    OWR\Interfaces\DAO as iDAO,
    OWR\View\Utilities;
/**
 * This class is used as base class for all DAO objects and defines all usefull functions
 * @abstract
 * @uses OWR\DB the database link
 * @uses OWR\Request a request sent to the database
 * @uses OWR\DB\Result a DB\Result from the database
 * @uses OWR\Exception the exceptions handler
 * @uses OWR\Object transforms an object to an associative array
 * @uses OWR\View\Utilities translate errors
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
     * @var array associative array of name => field for each relations table
     * @access protected
     */
    protected $_relations = array();

    /**
     * @var array associative array of name => field for each user's relations table
     * @access protected
     */
    protected $_userRelations = array();

    /**
     * @var int weight of the table in the query, used to optimize joins
     * @access protected
     */
    protected $_weight = 1;

    /**
     * @var array associative array representing the SQL schema
     * @access protected
     */
    static protected $_tableFields = array();

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
     * @var array stored results of already processed queries about id=>type
     * @access protected
     * @static
     */
    static protected $_types = array();

    /**
     * @var mixed the DB instance
     * @access protected
     * @static
     */
    static protected $_db;

    /**
     * Constructor, sets the name/fullname of the instance, and set the DB obj
     * Also it will try to fetch tablefields from cache, or generate what we need and cache it
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    protected function __construct()
    {
        isset(self::$_db) || (self::$_db  = DB::iGet()); // we assume here that the connexion has already been done, be carefull
        $this->_fullName = get_called_class();
        $this->_name = strtolower(str_replace('\\', '_', substr($this->_fullName, strlen(__NAMESPACE__.'_DAO_'))));

        !empty(self::$_tableFields) || (self::$_tableFields = Cache::get('tablefields'));
        if(!self::$_tableFields || empty(self::$_tableFields[$this->_name]))
        {
            self::$_tableFields[$this->_name] = array();
            foreach($this->_fields as $name => $def)
            {
                self::$_tableFields[$this->_name.'.'.$name] = $def;
            }

            if(!empty($this->_relations))
            {
                $node =& self::$_tableFields[$this->_name]['relations'];
                foreach($this->_relations as $table => $rel)
                {
                    $node[$table] = $rel;
                    if(!isset(self::$_tableFields[$table]))
                    {
                        self::getCachedDAO($table); // will init
                    }
                }
            }

            if(!empty($this->_userRelations))
            {
                $node =& self::$_tableFields[$this->_name]['userRelations'];
                foreach($this->_userRelations as $table => $rel)
                {
                    $node[$table] = $rel;
                    if(!isset(self::$_tableFields[$table]))
                    {
                        self::getCachedDAO($table); // will init
                    }
                }
            }

            Cache::write('tablefields', self::$_tableFields);
        }
    }

    /**
     * Gets rows from the database
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $args parameters, can be a string (if an $_idField has been declared), an object or an array, optionnal
     * @param string $select select fields, by default all
     * @param string $order the order clause
     * @param string $groupby the groupby clause
     * @param string $limit the limit clause
     * @return mixed null if any, an object of the current DAO name if only one DBResult, or an array if more
     */
    public function get($args = null, $select = '*', $order = '', $groupby = '', $limit = '')
    {
        if(is_object($args))
        {
            $args = Object::toArray($args);
        }

        $fetchType = 'object';
        $wheres = $request = $fields = $joins = array();

        $query = '
    SELECT '.$this->_prepareSelect($select, $joins, $wheres).'
        FROM '.$this->_name;

        if(is_array($args))
        {
            if(isset($args['FETCH_TYPE']) && ('assoc' === $args['FETCH_TYPE'] || 'array' === $args['FETCH_TYPE']))
                $fetchType = $args['FETCH_TYPE'];
            unset($args['FETCH_TYPE']);

            if(isset($args['uid']))
                unset($args['uid']);

            $this->_prepareWhere($args, $request, $fields, $wheres, $joins);
        }
        elseif(isset($args) && $this->_idField)
        {
            $wheres[] = $this->_idField.'=?';
            $request[$this->_idField] = $args;
        }

        if(isset($this->_fields['uid']))
        {
            $wheres[] = $this->_name.'.uid='.User::iGet()->getUid();
        }

        return $this->_fetch($this->_prepareQuery($query, $wheres, $joins, $groupby, $order, $limit),
                    new DBRequest($request, $fields, true), $fetchType, !empty($wheres));
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
            throw new Exception('No values to save, you surely have an error in your code');
        }

        $wheres = array();

        $checkUnique = $insert = $update = false;

        if(isset($this->_fields['uid']))
            $this->uid = $this->uid ?: User::iGet()->getUid();

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
                    empty($wheres) || ($wheres[] = 'uid');
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
                $whereFields[] = $val.'='.self::$_db->quote($this->$val);
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
                if(!empty($this->$uField))
                {
                    $whereFields[] = $uField;
                    $whereFieldsDecl[$uField] = $this->_fields[$uField];
                }
            }

            if(!empty($whereFields) && ($this->_idField || isset($this->_fields['uid'])))
            {
                $chkUniQuery = '
    SELECT COUNT('.($this->_idField ?: '*').') AS nb
        FROM '.$this->_name.'
        WHERE ';

                if($this->_idField)
                {
                    $chkUniQuery .= '('.join('=? OR ', $whereFields).'=?)';
                    
                    if(isset($this->{$this->_idField}) && $this->{$this->_idField} > 0)
                        $chkUniQuery .= ' AND '.$this->_idField.'!='.self::$_db->quote($this->{$this->_idField});
                        
                    if(isset($this->_fields['uid']))
                        $chkUniQuery .= ' AND uid='.$this->uid;
                }
                else
                {
                // there is no key id field
                // and $this->_fields['uid'] is set so we assume we are checking for relations between object and user
                    $tmpChkUniQuery = array();
                    foreach($whereFields as $field)
                    {
                        foreach($this->_relations as $krel => $vrel)
                        {
                            if(isset($vrel[$field]))
                            {
                                unset($whereFieldsDecl[$field]);
                                continue 2; // it's an id relation field
                            }
                        }
                        foreach($this->_userRelations as $krel => $vrel)
                        {
                            if(isset($vrel[$field]))
                            {
                                unset($whereFieldsDecl[$field]);
                                continue 2; // it's an id relation field
                            }
                        }
                        $tmpChkUniQuery[] = '(' . $field . '=? AND uid=' . $this->uid . ')';
                    }
                    if(empty($tmpChkUniQuery)) $skip = 1;
                    $chkUniQuery .= '(' . join(') OR (', $tmpChkUniQuery) . ')';
                }            

                if(!isset($skip))
                {
                    $exists = self::$_db->executeP($chkUniQuery, new DBRequest(Object::toArray($this), $whereFieldsDecl, true));

                    if($exists->next() && $exists->nb > 0)
                        throw new Exception('Some values are not uniques', 409);

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
                throw new Exception("Don't know what to save", Exception::E_OWR_BAD_REQUEST);
            }
        }

        foreach($this->_fields as $field => $decl)
        {
            if(isset($requestFields[$field]))
            {
                if($decl['required'])
                {
                    if(!isset($this->$field))
                        throw new Exception(sprintf(Utilities::iGet()->_('Missing value for required parameter "%s"'), $field), Exception::E_OWR_BAD_REQUEST);

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
                                throw new Exception(sprintf(Utilities::iGet()->_('Missing value for required parameter "%s"'), $field), Exception::E_OWR_BAD_REQUEST);

                        default:
                            break;
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
            self::$_db->setP($query, new DBRequest(Object::toArray($this), $requestFields, $update));
        }
        catch(Exception $e)
        { // we catch here to rollback and throw another exception
            self::$_db->rollback();
            throw new Exception($e->getContent(), $e->getCode());
        }

        return $this->_idField ? $this->{$this->_idField} : true;
    }

    /**
     * Deletes row(s) from the database
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $args parameters, can be a string (if an $_idField has been declared), an object or an array, optionnal
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
                    throw new Exception('Nothing to delete', Exception::E_OWR_BAD_REQUEST);
                $args = $this->{$this->_idField};
            }
        }
        elseif(is_object($args))
        {
            $args = Object::toArray($args);
        }

        $uid = false;

        $wheres = $joins = $request = $requestFields = $fields = array();

        if(is_array($args))
        {
            if(isset($args['uid']))
            {
                $uid = $args['uid'];
                unset($args['uid']);
            }

            $this->_prepareWhere($args, $request, $fields, $wheres, $joins);
        }
        elseif(isset($args) && $this->_idField)
        {
            $wheres[] = $this->_idField.'=?';
            $request[$this->_idField] = $args;
            $requestFields[$this->_idField] = $this->_fields[$this->_idField];
        }

        if(isset($this->_fields['uid']))
        {
            $wheres[] = 'uid='.($uid ?: ($this->uid ?: User::iGet()->getUid()));
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
            self::$_db->setP($query, new DBRequest($request, $requestFields), 'exec', !empty($request));
        }
        catch(Exception $e)
        { // we catch here to rollback and throw another exception
            self::$_db->rollback();
            throw new Exception($e->getContent(), $e->getCode());
        }

        return true;
    }

    /**
     * Counts row(s) from the database
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $args parameters, can be null, a string (if an $_idField has been declared), or an array
     * @param string $select select fields, by default all
     * @param string $groupby the groupby clause
     * @param string $selectAdd additional fields to fetch, optionnal
     * @return mixed null if any, an object of the current DAO name if only one DBResult, or an array if more
     */
    public function count($args = null, $select = '*', $groupby='', $selectAdd = '')
    {
        if(is_object($args))
        {
            $args = Object::toArray($args);
        }
        $fetchType = 'object';
        $wheres = $request = $fields = $joins = array();

        $query = '
    SELECT COUNT('.$this->_prepareSelect($select, $joins, $wheres).') AS nb'.(!empty($selectAdd) ? ', '.$this->_prepareSelect($selectAdd, $joins, $wheres) : '').'
        FROM '.$this->_name;

        if(is_array($args))
        {
            if(isset($args['FETCH_TYPE']) && ('assoc' === $args['FETCH_TYPE'] || 'array' === $args['FETCH_TYPE']))
                $fetchType = $args['FETCH_TYPE'];
            unset($args['FETCH_TYPE']);

            if(isset($args['uid']))
                unset($args['uid']);

            $this->_prepareWhere($args, $request, $fields, $wheres, $joins);
        }
        elseif(isset($args) && $this->_idField)
        {
            $wheres[] = $this->_idField.'=?';
            $request[$this->_idField] = $args;
        }

        if(isset($this->_fields['uid']))
        {
            $fields['uid'] = $this->_fields['uid'];
            $wheres[] = $this->_name.'.uid='.User::iGet()->getUid();
        }

        return $this->_fetch($this->_prepareQuery($query, $wheres, $joins, $groupby, null, null),
                    new DBRequest($request, $fields, true), $fetchType, !empty($wheres));
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
     * Returns the name of the table for $this
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return string the name of the table relative to $this
     */
    public function getTableName()
    {
        return (string) $this->_name;
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
     * Returns the weight
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return int the weight for this table
     */
    public function getWeight()
    {
        return (int) $this->_weight;
    }

    /**
     * Returns the relations
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return array the relations for this table
     */
    public function getRelations()
    {
        return (array) $this->_relations;
    }

    /**
     * Returns the relations related to the current user
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return array the relations for the current user for this table
     */
    public function getUserRelations()
    {
        return (array) $this->_userRelations;
    }

    /**
     * Returns all the relations
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return array all the relations for this table
     */
    public function getAllRelations()
    {
        return (array) array_merge($this->getRelations(), $this->getUserRelations());
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

        if(!isset(self::$_types[$id]))
        {
            $type = DB::iGet()->cGetOne('
    SELECT type
        FROM objects
        WHERE id='.$id);
            if(!$type->next()) return false;
            elseif(CLI) self::$_types[$id] = $type->type;
            else
            {
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

                    case 'news_tags':
                        $field = 'id';
                        $table = $type->type;
                        break;

                    case 'users':
                        if(User::iGet()->isAdmin())
                        {
                            self::$_types[$id] = $type->type;
                            return $type->type;
                        }
                        throw new Exception('Invalid id', Exception::E_OWR_UNAUTHORIZED);
                        break;

                    default:
                        throw new Exception('Invalid id', Exception::E_OWR_BAD_REQUEST);
                        break;
                }

                if(!self::getCachedDAO($table)->get(array($field => $id), $field))
                {
                    throw new Exception('Invalid id', Exception::E_OWR_BAD_REQUEST);
                }

                self::$_types[$id] = $type->type;
            }
        }

        return self::$_types[$id];
    }

    /**
     * Returns the JOIN conditions
     * This method tries to order the JOIN conditions to get better performance results
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param array $joins the tables to join
     * @return array the JOIN conditions
     * @access protected
     */
    protected function _optimizeJoins(array $joins)
    {
        $tables = array();
        foreach(array_keys($joins) as $table)
        {
            $tables[self::getCachedDAO($table)->getWeight()][] = $table;
        }
        krsort($tables);

        $joins = array();
        $tables = Arrays::multiDimtoNumericalArray($tables);

        $tableName = static::getTableName();

        foreach($tables as $k => $table)
        {
            $tableRel = $k === 0 ? $tableName : $tables[$k - 1]; // the table we need to LEFT JOIN
            $relations = self::getCachedDAO($table)->getAllRelations();
            foreach($relations as $t => $relation)
            {
                if($t !== $tableRel) continue;

                $join = array();
                foreach($relation as $myfield => $itsfield)
                {
                    $join[] = $t.'.'.$itsfield.'='.$table.'.'.$myfield;
                }
                $joins[] = ' LEFT JOIN '.$table.' ON ('.join(' AND ', $join).')';
                continue 2;
            }

            // can't find relations with previous table, defaulting to INNER JOIN
            foreach($relations as $t => $relation)
            {
                $join = array();
                foreach($relation as $myfield => $itsfield)
                {
                    $join[] = $t.'.'.$itsfield.'='.$table.'.'.$myfield;
                }
                $joins[] = ' JOIN '.$table.' ON ('.join(' AND ', $join).')';
                continue 2;
            }

            throw new Exception("Can't find a relation table for table ".$table, Exception::E_OWR_DIE);
        }

        return (array) $joins;
    }

    /**
     * Prepares the WHERE clauses for a query
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param array $args parameters
     * @param array &$request the fields to SELECT
     * @param array &$fields the fields definition
     * @param array &$wheres the WHERE fields
     * @param array &$joins the JOIN conditions
     * @access protected
     * @todo clean up the code
     */
    protected function _prepareWhere(array $args, array &$request, array &$fields, array &$wheres, array &$joins)
    {
        foreach($args as $key=>$values)
        {
            if(isset($this->_fields[$key]))
            {
                if(is_array($values))
                {
                    $request[$key] = array();
                    $where = array();
                    foreach($values as $k=>$value)
                    {
                        $where[] = '?';
                        $request[$key][] =& $args[$key][$k];
                        $fields[$key][] = $this->_fields[$key];
                    }

                    empty($where) || ($wheres[] = $this->_name.'.'.$key.' IN ('.join(',', $where).')');
                }
                else
                {
                    $wheres[] = $this->_name.'.'.$key.'=?';
                    $fields[$key] = $this->_fields[$key];
                    $request[$key] =& $args[$key];
                }
            }
            elseif(false !== strpos($key, '.'))
            {
                list($table, $field) = array_map('trim', explode('.', $key));
                if($table === $this->_name)
                {
                    if(isset($this->_fields[$field]))
                    {
                        if(is_array($values))
                        {
                            $request[$key] = array();
                            $where = array();
                            foreach($values as $k=>$value)
                            {
                                $where[] = '?';
                                $request[$key][] =& $args[$key][$k];
                                $fields[$key][] = $this->_fields[$key];
                            }
                            empty($where) || ($wheres[] = $this->_name.'.'.$key.' IN ('.join(',', $where).')');
                        }
                        else
                        {
                            $wheres[] = $table.'.'.$field.'=?';
                            $fields[$key] = $this->_fields[$field];
                            $request[$key] =& $args[$key];
                        }
                    }
                    continue;
                }

                if(isset($this->_userRelations[$table]))
                {
                    if(isset(self::$_tableFields[$table.'.'.$field]))
                    {
                        if(is_array($values))
                        {
                            $request[$key] = array();
                            $where = array();
                            foreach($values as $k=>$value)
                            {
                                $where[] = '?';
                                $request[$key][] =& $args[$key][$k];
                                $fields[$key][] = self::$_tableFields[$table.'.'.$field];
                            }
                            empty($where) || ($wheres[] = $table.'.'.$field.' IN ('.join(',', $where).')');
                        }
                        else
                        {
                            $wheres[] = $table.'.'.$field.'=?';
                            $fields[$key] = self::$_tableFields[$table.'.'.$field];
                            $request[$key] =& $args[$key];
                        }

                        if(!isset($joins[$table]))
                        {
                            $joins[$table] = true;
                            $wheres[] = $table.'.uid='.User::iGet()->getUid();
                        }
                        continue;
                    }
                }

                if(isset($this->_relations[$table]))
                {
                    if(isset(self::$_tableFields[$table.'.'.$field]))
                    {
                        if(is_array($values))
                        {
                            $request[$key] = array();
                            $where = array();
                            foreach($values as $k=>$value)
                            {
                                $where[] = '?';
                                $request[$key][] =& $args[$key][$k];
                                $fields[$key][] = self::$_tableFields[$table.'.'.$field];
                            }
                            empty($where) || ($wheres[] = $table.'.'.$field.' IN ('.join(',', $where).')');
                        }
                        else
                        {
                            $wheres[] = $table.'.'.$field.'=?';
                            $fields[$key] = self::$_tableFields[$table.'.'.$field];
                            $request[$key] =& $args[$key];
                        }

                        if(!isset($joins[$table]))
                            $joins[$table] = true;

                        continue;
                    }
                }

                if(isset(self::$_tableFields[$table]))
                {
                    $relations = self::$_tableFields[$table];
                    if(isset($relations['userRelations']))
                    {
                        foreach($relations['userRelations'] as $relTable => $rel)
                        {
                            if(isset($this->_userRelations[$relTable]))
                            {
                                if(!isset($joins[$relTable]))
                                {
                                    $joins[$relTable] = true;
                                    $wheres[] = $relTable.'.uid='.User::iGet()->getUid();
                                }

                                if(!isset($joins[$table]))
                                {
                                    $joins[$table] = true;
                                    $wheres[] = $table.'.uid='.User::iGet()->getUid();
                                }

                                if(is_array($values))
                                {
                                    $request[$key] = array();
                                    $where = array();
                                    foreach($values as $k=>$value)
                                    {
                                        $where[] = '?';
                                        $request[$key][] =& $args[$key][$k];
                                        $fields[$key][] = self::$_tableFields[$table.'.'.$field];
                                    }
                                    empty($where) || ($wheres[] = $table.'.'.$field.' IN ('.join(',', $where).')');
                                }
                                else
                                {
                                    $wheres[] = $table.'.'.$field.'=?';
                                    $fields[$key] = self::$_tableFields[$table.'.'.$field];
                                    $request[$key] =& $args[$key];
                                }
                                continue 2;
                            }

                            if(isset($this->_relations[$relTable]))
                            {
                                if(is_array($values))
                                {
                                    $request[$key] = array();
                                    $where = array();
                                    foreach($values as $k=>$value)
                                    {
                                        $where[] = '?';
                                        $request[$key][] =& $args[$key][$k];
                                        $fields[$key][] = self::$_tableFields[$table.'.'.$field];
                                    }
                                    empty($where) || ($wheres[] = $table.'.'.$field.' IN ('.join(',', $where).')');
                                }
                                else
                                {
                                    $wheres[] = $table.'.'.$field.'=?';
                                    $fields[$key] = self::$_tableFields[$table.'.'.$field];
                                    $request[$key] =& $args[$key];
                                }

                                if(!isset($joins[$relTable]))
                                    $joins[$relTable] = true;

                                if(!isset($joins[$table]))
                                {
                                    $joins[$table] = true;
                                    if(!empty(self::$_tableFields[$relTable]['userRelations']) && !empty(self::$_tableFields[$relTable]['userRelations'][$table]))
                                        continue 2;

                                    if(!empty($relations['relations']) && !empty($relations['relations'][$table]))
                                        continue 2;
                                }
                            }
                        }
                    }

                    if(isset($relations['relations']))
                    {
                        foreach($relations['relations'] as $relTable => $rel)
                        {
                            if(isset($this->_userRelations[$relTable]))
                            {
                                if(!isset($joins[$relTable]))
                                {
                                    $joins[$relTable] = true;
                                    $wheres[] = $relTable.'.uid='.User::iGet()->getUid();
                                }

                                if(!isset($joins[$table]))
                                {
                                    $joins[$table] = true;
                                    $wheres[] = $table.'.uid='.User::iGet()->getUid();
                                }

                                if(is_array($values))
                                {
                                    $request[$key] = array();
                                    $where = array();
                                    foreach($values as $k=>$value)
                                    {
                                        $where[] = '?';
                                        $request[$key][] =& $args[$key][$k];
                                        $fields[$key][] = self::$_tableFields[$table.'.'.$field];
                                    }
                                    empty($where) || ($wheres[] = $table.'.'.$field.' IN ('.join(',', $where).')');
                                }
                                else
                                {
                                    $wheres[] = $table.'.'.$field.'=?';
                                    $fields[$key] = self::$_tableFields[$table.'.'.$field];
                                    $request[$key] =& $args[$key];
                                }
                                continue 2;
                            }

                            if(isset($this->_relations[$relTable]))
                            {
                                if(is_array($values))
                                {
                                    $request[$key] = array();
                                    $where = array();
                                    foreach($values as $k=>$value)
                                    {
                                        $where[] = '?';
                                        $request[$key][] =& $args[$key][$k];
                                        $fields[$key][] = self::$_tableFields[$table.'.'.$field];
                                    }
                                    empty($where) || ($wheres[] = $table.'.'.$field.' IN ('.join(',', $where).')');
                                }
                                else
                                {
                                    $wheres[] = $table.'.'.$field.'=?';
                                    $fields[$key] = self::$_tableFields[$table.'.'.$field];
                                    $request[$key] =& $args[$key];
                                }

                                if(!isset($joins[$relTable]))
                                    $joins[$relTable] = true;

                                if(!isset($joins[$table]))
                                {
                                    $joins[$table] = true;
                                    if(!empty(self::$_tableFields[$relTable]['userRelations']) && !empty(self::$_tableFields[$relTable]['userRelations'][$table]))
                                        continue 2;

                                    if(!empty(self::$_tableFields[$relTable]['relations']) && !empty(self::$_tableFields[$relTable]['relations'][$table]))
                                        continue 2;
                                }
                            }
                        }
                    }
                }
            }
            else
            {
                if(!empty($this->_userRelations))
                {
                    foreach($this->_userRelations as $table => $relFields)
                    {
                        $tables[] = $table;
                        if(isset(self::$_tableFields[$table.'.'.$key]))
                        {
                            if(is_array($values))
                            {
                                $request[$key] = array();
                                $where = array();
                                foreach($values as $k=>$value)
                                {
                                    $where[] = '?';
                                    $request[$key][] =& $args[$key][$k];
                                    $fields[$key][] = self::$_tableFields[$table.'.'.$key];
                                }
                                $wheres[] = $table.'.'.$key.' IN ('.join(',', $where).')';
                            }
                            else
                            {
                                $wheres[] = $table.'.'.$key.'=?';
                                $fields[$key] = self::$_tableFields[$table.'.'.$key];
                                $request[$key] =& $args[$key];
                            }

                            if(!isset($joins[$table]))
                            {
                                $joins[$table] = true;
                                $wheres[] = $table.'.uid='.User::iGet()->getUid();
                            }
                            continue 2; // OK found the field, continue to wheres iteration
                        }
                    }
                }

                if(!empty($this->_relations))
                {
                    foreach($this->_relations as $table => $relFields)
                    {
                        $tables[] = $table;
                        if(isset(self::$_tableFields[$table.'.'.$key]))
                        {
                            if(is_array($values))
                            {
                                $request[$key] = array();
                                $where = array();
                                foreach($values as $k=>$value)
                                {
                                    $where[] = '?';
                                    $request[$key][] =& $args[$key][$k];
                                    $fields[$key][] = self::$_tableFields[$table.'.'.$key];
                                }
                                $wheres[] = $table.'.'.$key.' IN ('.join(',', $where).')';
                            }
                            else
                            {
                                $wheres[] = $table.'.'.$key.'=?';
                                $fields[$key] = self::$_tableFields[$table.'.'.$key];
                                $request[$key] =& $args[$key];
                            }

                            if(!isset($joins[$table]))
                                $joins[$table] = true;

                            continue 2; // OK found the field, continue to wheres iteration
                        }
                    }
                }
            }
        }
    }

    /**
     * Fetches all results from DB, either in numerical array, associative array or object
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $query the SQL query
     * @param OWR\DB\Request $request DBRequest the request
     * @param string $fetchType can be assoc, array or object
     * @param boolean $force used to say to the DB object that we must use prepared query
     * @access protected
     * @return mixed empty array or one result or an array of results
     */
    protected function _fetch($query, DBRequest $request, $fetchType, $force)
    {
        $DBResult = self::$_db->getP((string) $query, $request, (bool) $force);

        $DBResults = array();
        $fetchType = (string) $fetchType;

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
            // very handsome, thanks PDO for that
            $DBResults = $DBResult->fetchAll(\PDO::FETCH_CLASS, $this->_fullName);
        }
        unset($DBResult);

        if(empty($DBResults)) return array();
        else return count($DBResults) === 1 ? $DBResults[0] : $DBResults;
    }

    /**
     * Finalize the building of the SQL query
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $query the sql query
     * @param array $wheres the WHERE clause fields
     * @param array $joins the JOIN clause fields
     * @param string $groupby the GROUP BY clause
     * @param string $order the ORDER clause
     * @param string $limit the LIMIT clause
     * @access protected
     * @todo clean up the code
     */
    protected function _prepareQuery($query, array $wheres, array $joins, $groupby, $order, $limit)
    {
        if(!empty($joins))
        {
            $query .= '
        '.join("\n        ", $this->_optimizeJoins($joins));
        }

        if(!empty($wheres))
        {
            $query .= '
        WHERE '.join(' AND ', $wheres);
        }

        if(!empty($groupby))
        {
            $query .= '
        GROUP BY '.(string) $groupby;
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

        return $query;
    }

    /**
     * Prepares the SELECT clauses for a query
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $selects select fields, by default all
     * @param array &$joins the JOIN clause fields
     * @param array &$wheres the WHERE clause fields
     * @access protected
     * @todo clean up the code
     */
    protected function _prepareSelect($selects, array &$joins, array &$wheres)
    {
        $selects = (string) $selects;
        if(empty($selects) || '*' === $selects) return '*';

        $select = array_map('trim', explode(',', $selects));
        $selects = array();

        foreach($select as $field)
        {
            $alias = '';
            if(false !== stripos($field, ' AS '))
            {
                list($field, $alias) = explode(' AS ', $field);
                $alias = ' AS '.$alias;
            }

            if(isset($this->_fields[$field]))
            {
                $selects[] = $this->_name.'.'.$field.$alias;
            }
            elseif(false !== strpos($field, '.'))
            {
                list($table, $field) = explode('.', $field);
                if($table === $this->_name)
                {
                    if(isset($this->_fields[$field]))
                    {
                        $selects[] = $this->_name.'.'.$field.$alias;
                    }
                    continue;
                }

                if(isset($this->_userRelations[$table]))
                {
                    if(isset(self::$_tableFields[$table.'.'.$field]))
                    {
                        $selects[] = $table.'.'.$field.$alias;
                        if(!isset($joins[$table]))
                        {
                            $joins[$table] = true;
                            $wheres[] = $table.'.uid='.User::iGet()->getUid();
                        }
                        continue;
                    }
                }

                if(isset($this->_relations[$table]))
                {
                    if(isset(self::$_tableFields[$table.'.'.$field]))
                    {
                        $selects[] = $table.'.'.$field.$alias;
                        if(!isset($joins[$table]))
                            $joins[$table] = true;

                        continue;
                    }
                }

                if(isset(self::$_tableFields[$table]))
                {
                    $relations = self::$_tableFields[$table];
                    if(isset($relations['relations']))
                    {
                        foreach($relations['relations'] as $relTable => $rel)
                        {
                            if(isset($this->_userRelations[$relTable]) && isset(self::$_tableFields[$table.'.'.$field]))
                            {
                                $selects[] = $table.'.'.$field.$alias;
                                if(!isset($joins[$relTable]))
                                {
                                    $joins[$relTable] = true;
                                    $wheres[] = $relTable.'.uid='.User::iGet()->getUid();
                                }

                                if(!isset($joins[$table]))
                                {
                                    $joins[$table] = true;
                                    $wheres[] = $table.'.uid='.User::iGet()->getUid();
                                }
                                continue 2;
                            }

                            if(isset($this->_relations[$relTable]) && isset(self::$_tableFields[$table.'.'.$field]))
                            {
                                $selects[] = $table.'.'.$field.$alias;
                                if(!isset($joins[$relTable]))
                                    $joins[$relTable] = true;

                                if(!isset($joins[$table]))
                                {
                                    $joins[$table] = true;
                                    $relations = self::$_tableFields[$relTable];
                                    if(!empty($relations['userRelations']) && !empty($relations['userRelations'][$table]))
                                        continue 2;

                                    if(!empty($relations['relations']) && !empty($relations['relations'][$table]))
                                        continue 2;
                                }
                            }
                        }
                    }

                    if(isset($relations['userRelations']))
                    {
                        foreach($relations['userRelations'] as $relTable => $rel)
                        {
                            if(isset($this->_userRelations[$relTable]) && isset(self::$_tableFields[$table.'.'.$field]))
                            {
                                $selects[] = $table.'.'.$field.$alias;
                                if(!isset($joins[$relTable]))
                                {
                                    $joins[$relTable] = true;
                                    $wheres[] = $relTable.'.uid='.User::iGet()->getUid();
                                }

                                if(!isset($joins[$table]))
                                {
                                    $joins[$table] = true;
                                    $wheres[] = $table.'.uid='.User::iGet()->getUid();
                                }
                                continue 2;
                            }

                            if(isset($this->_relations[$relTable]) && isset(self::$_tableFields[$table.'.'.$field]))
                            {
                                $selects[] = $table.'.'.$field.$alias;
                                if(!isset($joins[$relTable]))
                                    $joins[$relTable] = true;

                                if(!isset($joins[$table]))
                                {
                                    $joins[$table] = true;
                                    $relations = self::$_tableFields[$relTable];
                                    if(!empty($relations['userRelations']) && !empty($relations['userRelations'][$table]))
                                        continue 2;

                                    if(!empty($relations['relations']) && !empty($relations['relations'][$table]))
                                        continue 2;
                                }
                            }
                        }
                    }
                }
            }
            else
            {
                if(!empty($this->_userRelations))
                {
                    foreach($this->_userRelations as $table => $relFields)
                    {
                        if(isset(self::$_tableFields[$table.'.'.$field]))
                        {
                            $selects[] = $table.'.'.$field.$alias;
                            if(!isset($joins[$table]))
                            {
                                $joins[$table] = true;
                                $wheres[] = $table.'.uid='.User::iGet()->getUid();
                            }
                            continue 2; // OK found the field, continue to select iteration
                        }
                    }
                }

                if(!empty($this->_relations))
                {
                    foreach($this->_relations as $table => $relFields)
                    {
                        if(isset(self::$_tableFields[$table.'.'.$field]))
                        {
                            $selects[] = $table.'.'.$field.$alias;
                            if(!isset($joins[$table]))
                                $joins[$table] = true;

                            continue 2; // OK found the field, continue to select iteration
                        }
                    }
                }


            }
        }

        return (string) join(',', $selects); // we are done, recompoze the select string
    }
}
