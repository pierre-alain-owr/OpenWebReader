<?php
/**
 * Interface for all DAOs
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
use OWR\Request as Request;
/**
 * This class is used to declare public functions
 * @package OWR
 * @subpackage Interfaces
 */
interface DAO
{
    /**
     * Returns the specified DAO object
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $dao the name of the DAO
     * @return mixed the DAO object
     */
    static public function getDAO($dao);

    /**
     * Returns the specified DAO object from cache
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $dao the name of the DAO
     * @return mixed the DAO object
     */
    static public function getCachedDAO($dao);

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
    public function get($args = null, $select = '*', $order = '', $groupby = '', $limit = '');

    /**
     * Saves a row into the database
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param boolean $ignore if we must save with a INSERT IGNORE clause instead of REPLACE INTO if no $_idField has been declared
     * @return mixed if success true if no $_idField declared or the value of the $_idField
     */
    public function save($ignore = false);

    /**
     * Deletes row(s) from the database
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $args parameters, can be null (deletes himself if $_idField declared), a string (if an $_idField has been declared), or an array
     * @param string $limit the limit clause
     * @return boolean true on success
     */
    public function delete($args = null, $limit='');

    /**
     * Populates values into this object
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $obj the values to populate
     * @return boolean true on success
     */
    public function populate($obj);

    /**
     * Returns the unique fields
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return array the unique fields for this object
     */
    public function getUniqueFields();

    /**
     * Returns the fields
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return array the fields for this object
     */
    public function getFields();

    /**
     * Returns the id field
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return string the id field for this object
     */
    public function getIdField();

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
    static public function getById($id);

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
    static public function getType($id);
}