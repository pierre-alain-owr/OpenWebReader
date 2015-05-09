<?php
/**
 * Model Object base class
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
use OWR\Interfaces\Model as iModel,
    OWR\User;
/**
 * This class is used as base class for all DAO objects and defines all usefull functions
 * Please ensure that all the non-static public functions returns $this
 *
 * @abstract
 * @uses OWR\Interfaces\Model implements the Model interface
 * @uses OWR\DB the database link
 * @uses OWR\Request a request sent to the database
 * @uses OWR\DB\Result a DB\Result from the database
 * @uses OWR\Exception the exceptions handler
 * @uses OWR\User the user
 * @package OWR
 */
abstract class Model implements iModel
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
    * @var mixed the DAO instance for the current class
    * @access protected
    */
    protected $_dao;

    /**
    * @var array stored already processed model names
    * @access private
    * @static
    */
    static private $_models = array();

    /**
    * @var array stored already processed models objects
    * @access private
    * @static
    */
    static private $_cachedModels = array();

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
        $this->_name = str_replace('\\', '_', substr($this->_fullName, strlen(__NAMESPACE__.'_Model_')));
        $this->_dao = DAO::getDAO($this->_name);
    }

    /**
     * Returns the specified Model object
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $model the name of the Model
     * @return mixed the Model object
     */
    static public function getModel($model)
    {
        if(!isset(self::$_models[$model]))
        {
            $c = __NAMESPACE__.'\Model\\'.join('\\', array_map('ucfirst', explode('_', (string) $model)));;
            self::$_models[$model] = $c;
        }
        return new self::$_models[$model];
    }

    /**
     * Returns the specified Model object from cache
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $model the name of the Model
     * @return mixed the Model object
     */
    static public function getCachedModel($model)
    {
        $model = ucfirst((string) $model);
        isset(self::$_cachedModels[$model]) || (self::$_cachedModels[$model] = self::getModel($model));

        return self::$_cachedModels[$model];
    }

    /**
     * Sets the user's timestamp, which corresponds to the last http request timestamp for this user
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param array $datas the datas retrieved from DB
     */
    protected function _setUserTimestamp(array $datas)
    {
        if(isset($datas['id']))
            User::iGet()->setTimestamp($datas['id']);
        else
        {
            foreach($datas as $data)
                User::iGet()->setTimestamp($data['id']);
        }
    }
}
