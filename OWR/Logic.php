<?php
/**
 * Logic Object base class 
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
use OWR\Interfaces\Logic as iLogic,
    OWR\User as User;
/**
 * This class is used as base class for all DAO objects and defines all usefull functions
 * Please ensure that all the non-static public functions returns $this
 *
 * @abstract
 * @uses OWR\Interfaces\Logic implements the Logic interface
 * @uses OWR\DB the database link
 * @uses OWR\Request a request sent to the database
 * @uses OWR\DB\Result a DB\Result from the database
 * @uses OWR\Exception the exceptions handler
 * @uses OWR\User the user
 * @package OWR
 */
abstract class Logic implements iLogic
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
    * @var array stored already processed logic names
    * @access private
    * @static
    */
    static private $_logics = array();

    /**
    * @var array stored already processed logics objects
    * @access private
    * @static
    */
    static private $_cachedLogics = array();

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
        $this->_name = str_replace('\\', '_', substr($this->_fullName, strlen(__NAMESPACE__.'_Logic_')));
        $this->_dao = DAO::getDAO($this->_name);
    }

    /**
     * Returns the specified Logic object
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $logic the name of the Logic
     * @return mixed the Logic object
     */
    static public function getLogic($logic)
    {
        if(!isset(self::$_logics[$logic]))
        {
            $c = __NAMESPACE__.'\Logic\\'.join('\\', array_map('ucfirst', explode('_', (string) $logic)));;
            self::$_logics[$logic] = $c;
        }
        return new self::$_logics[$logic];
    }

    /**
     * Returns the specified Logic object from cache
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $logic the name of the Logic
     * @return mixed the Logic object
     */
    static public function getCachedLogic($logic)
    {
        $logic = ucfirst((string) $logic);
        isset(self::$_cachedLogics[$logic]) || (self::$_cachedLogics[$logic] = self::getLogic($logic));

        return self::$_cachedLogics[$logic];
    }
}