<?php
/**
 * Interface for all ResponseModel
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
namespace OWR\Interfaces\Model;
/**
 * This class is used to declare public functions
 * @package OWR
 * @subpackage Interfaces
 */
interface Response
{
    /**
     * Public constructor
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param array $contents the contents of the response (status, do, errors, etc..)
     */
    public function __construct(array $contents);

    /**
     * Returns a JSON object of $this
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return string JSON equivalent of $this
     */
    public function __toString();

    /**
     * Returns $this->_do
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return string the action to do now
     */
    public function getNext();

    /**
     * Returns $this->_tpl
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return string the tpl to render
     */
    public function getTpl();

    /**
     * Returns $this->_status
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return int the status
     */
    public function getStatus();

    /**
     * Returns $this->_errors
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return array the errors if any
     */
    public function getErrors();

    /**
     * Returns $this->_error
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return string the error if any
     */
    public function getError();
}