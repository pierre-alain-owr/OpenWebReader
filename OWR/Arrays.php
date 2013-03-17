<?php
/**
 * Arrays class
 * This class is usefull to manipulate arrays
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
/**
 * This object is used to manipulate arrays
 * @package OWR
 */
class Arrays
{
    /**
     * Transforms multi-dimensional array to indexed array
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @static
     * @param array $array the multi-dimensional array to transform
     * @return array indexed $array
     */
    static public function multiDimtoNumericalArray(array $array)
    {
        $arr = array();
        foreach(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array)) as $v)
            $arr[] = $v;

        return $arr;
    }
}