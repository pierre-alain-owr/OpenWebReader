<?php
/**
 * Interface for DB\Result object
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
namespace OWR\Interfaces\DB;
/**
 * This class is used to declare public functions
 * @package OWR
 * @subpackage Interfaces
 */
interface Result
{
    /**
     * Constructor
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param PDOStatement $stmt the PDOStatement
     * @param int $type the fetch type
     */
    public function __construct(\PDOStatement $stmt=null, $type = self::FETCH_ALL);

    /**
     * Returns the number of rows
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return int the number of rows
     */
    public function count();

    /**
     * Sets the next row into $this
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return boolean true on success
     */
    public function next();

    /**
     * Returns all the next rows
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return array the next rows
     */
    public function getAllNext();
}
