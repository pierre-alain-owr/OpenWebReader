<?php
/**
 * Interface for Cache object
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
/**
 * This class is used to declare public functions
 * @package OWR
 * @subpackage Interfaces
 */
interface Cache
{
    /**
     * Delete every files found in cache directory
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @static
     * @param string $dir a directory in cache/
     * @param boolean $maintenance must-we just check for the lastmtime ?
     * @return int number of deleted files
     */
    static public function clear($dir = '', $maintenance = false);
    
    /**
     * Delete every files found in DB cache directory
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @static
     */
    static public function clearDB();

    /**
     * Delete every files found in HTML cache directories
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @static
     */
    static public function clearHTML();

    /**
     * Try to get serialized datas from cache
     * This function uses file locking
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @static
     * @param string $filename cache file name
     * @param int $cacheTime cache life time
     * @return mixed
     * @access public
     */
    static public function get($filename, $cacheTime=0);

    /**
     * Try to write serialized datas into cache
     * This function uses file locking
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @static
     * @param string $filename cache file name
     * @param string $datas datas to store in cache
     * @return mixed
     * @access public
     */
    static public function write($filename, $datas);

    /**
     * Check that the directory is writeable
     * It will try to create it if it does not exists
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @static
     * @param string $dir the directory's name
     * @return mixed
     * @access public
     */
    static public function checkDir($dir);

    /**
     * Returns a unique filename
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param boolean $tmp if we must use default tmp dir, default to false
     * @return string the file name
     * @access public
     */
    static public function getRandomFilename($tmp = false);
}
