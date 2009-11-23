<?php
/**
 * Object representing a SQL result
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
 * @subpackage DB
 */
namespace OWR\DB;
use OWR\Object as Object,
    OWR\Exception as Exception,
    OWR\Interfaces\DB\Result as iResult;
/**
 * This object represents a result from DB
 * @uses Object convert everything to an object
 * @uses Exception the exceptions handler
 * @package OWR
 * @subpackage DB
 */
class Result extends Object implements iResult
{
    /**
    * @var int fetch type (all results)
    */
    const FETCH_ALL = 0;

    /**
    * @var int fetch type (rows)
    */
    const FETCH_ROW = 1;

    /**
    * @var int fetch type (first row)
    */
    const FETCH_ONE = 2;

    /**
    * @var array store the results
    * @access private
    */
    private $_rows;

    /**
     * Constructor
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $stmt the PDOStatement
     * @param int $type the fetch type
     */
    public function __construct(\PDOStatement $stmt=null, $type = self::FETCH_ALL)
    {
        if($stmt)
        {
            if($type === self::FETCH_ALL)
            {
                $this->_rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
            elseif($type === self::FETCH_ROW)
            {
                $this->_rows = $stmt->fetch(\PDO::FETCH_ASSOC);
                if($this->_rows)
                {
                    $this->_rows = array($this->_rows);
                }
            }
            elseif($type === self::FETCH_ONE)
            {
                $this->_rows = $stmt->fetch(\PDO::FETCH_ASSOC);
                if($this->_rows)
                {
                    $keys = array_keys($this->_rows);
                    $values = array_values($this->_rows);
                    $this->_rows = array(array($keys[0]=>$values[0]));
                    unset($keys,$values);
                }
            }
            else throw new Exception('Unknown fetch type');

            $stmt->closeCursor();
            $stmt = null;
        }
        parent::__construct();
    }

    /**
     * Returns the number of rows
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return int the number of rows
     */
    public function count()
    {
        return is_array($this->_rows) ? count($this->_rows) : 0;
    }

    /**
     * Sets the next row into $this
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return boolean true on success
     */
    public function next()
    {
        if(empty($this->_rows)) return false;
        parent::_setDatas(array_shift($this->_rows));
        return true;
    }

    /**
     * Returns all the next rows
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return array the next rows
     */
    public function getAllNext()
    {
        $array = $this->_rows;
        $this->_rows = array();
        return $array;
    }
}