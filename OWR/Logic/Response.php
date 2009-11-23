<?php
/**
 * Class representing a response from every call to a Logic
 * If we a logic returns this object, it means that no blocking errors have been encountered
 * Else the Logic will throw an Exception
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
 * @subpackage Logic
 */
namespace OWR\Logic;
use OWR\Interfaces\Logic\Response as iResponse,
    OWR\Exception as Exception,
    OWR\View\Utilities as ViewUtilities;
/**
 * This class is an object response of every logic call
 * @package OWR
 * @subpackage Logic
 */
class Response implements iResponse
{
    /**
     * @var int Status of the the response
     * This code is based on HTTP status code BUT can be different
     * @access protected
     */
    protected $_status = 200;

    /**
     * @var array list of eventual errors
     * Used for displaying errors to user
     * @access protected
     */
    protected $_errors = array();

    /**
     * @var string what do we do next ?
     * Can be ok, error, redirect
     * @access protected
     */
    protected $_do = 'ok';

    /**
     * @var string tpl name of the template if we must render one
     * @access protected
     */
    protected $_tpl = '';

    /**
     * @var string if $this->_do is set to redirect, then we can give an url to location
     * @access protected
     */
    protected $_location = '';

    /**
     * @var string an error to log
     * @access protected
     */
    protected $_error = '';

    /**
     * @var array datas for page rendering, only for do=(ok|error)
     * @access protected
     */
    protected $_datas = array();

    /**
     * Public constructor
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param array $contents the contents of the response (status, do, errors, etc..)
     */
    public function __construct(array $contents = array())
    {
        if(isset($contents['do']))
        {
            switch($contents['do'])
            {
                case 'ok': // ok
                    break;

                case 'redirect': // redirection
                    $this->_do = (string) $contents['do'];
                    !isset($contents['location']) || $this->_location = (string) $contents['location'];
                    break;

                case 'error': // error
                    $this->_do = (string) $contents['do'];
                    if(isset($contents['errors']))
                    {
                        $this->_errors = array_map(array(ViewUtilities::iGet(), '_'), (array) $contents['errors']);
                        $datas['errors'] = $this->_errors;
                    }

                    if(isset($contents['error']))
                    {
                        $this->_error = ViewUtilities::iGet()->_((string) $contents['error']);
                        $datas['error'] = $this->_error;
                    }
                    break;

                default:
                    throw new Exception('Invalid return from Logic', Exception::E_OWR_DIE);
                    break;
            }
        }

        if(('ok' === $this->_do || 'error' === $this->_do))
        {
            !isset($contents['tpl']) || $this->_tpl = (string) $contents['tpl'];
            !isset($contents['datas']) || $this->_datas = (array) $contents['datas'];
        }

        if(empty($this->_datas['error']) && !empty($this->_error))
            $this->_datas['error'] = $this->_error;

        if(empty($this->_datas['errors']) && !empty($this->_errors))
            $this->_datas['errors'] = $this->_errors;

        !isset($contents['status']) || $this->_status = (int) $contents['status'];
    }

    /**
     * Returns a JSON object of $this
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return string JSON equivalent of $this
     */
    public function __toString()
    {
        return json_encode($this);
    }

    /**
     * Returns $this->_do
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return string the action to do now
     */
    public function getNext()
    {
        return (string) $this->_do;
    }

    /**
     * Returns $this->_tpl
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return string the tpl to render
     */
    public function getTpl()
    {
        return (string) $this->_tpl;
    }

    /**
     * Returns $this->_status
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return int the status
     */
    public function getStatus()
    {
        return (int) $this->_status;
    }

    /**
     * Returns $this->_errors
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return array the errors if any
     */
    public function getErrors()
    {
        return (array) $this->_errors;
    }

    /**
     * Returns $this->_error
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return string the error if any
     */
    public function getError()
    {
        return (string) $this->_error;
    }

    /**
     * Returns $this->_datas
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return array the datas if any
     */
    public function getDatas()
    {
        return (array) $this->_datas;
    }

    /**
     * Returns $this->_location
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return string the location to redirect to
     */
    public function getLocation()
    {
        return (string) $this->_location;
    }
}