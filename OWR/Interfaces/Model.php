<?php
/**
 * Interface for all models
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
use OWR\Request;
/**
 * This class is used to declare public functions
 * @package OWR
 * @subpackage Interfaces
 */
interface Model
{
    /**
     * Returns the specified Model object
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $model the name of the Model
     * @return mixed the Model object
     */
    static public function getModel($model);

    /**
     * Returns the specified Model object from cache
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $model the name of the Model
     * @return mixed the Model object
     */
    static public function getCachedModel($model);

    /**
     * Adds/Edits an object
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $request the Request instance
     * @return $this
     */
    public function edit(Request $request);

    /**
     * Deletes object(s)
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $request the Request instance
     * @return $this
     */
    public function delete(Request $request);

    /**
     * Gets datas to render object(s)
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $request the Request instance
     * @param array $args additional arguments, optionnal
     * @param string $order the order clause
     * @param string $groupby the groupby clause
     * @param string $limit the limit clause
     * @return $this
     */
    public function view(Request $request, array $args = array(), $order = '', $groupby = '', $limit = '');
}