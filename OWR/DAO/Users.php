<?php
/**
 * DAO Object representing the table users
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
 * @subpackage DAO
 */
namespace OWR\DAO;
use OWR\DAO,
    OWR\DB\Request as DBRequest;
/**
 * This object represents the table users
 * @uses DAO extends the base class
 * @uses DBRequest defines the fields type
 * @package OWR
 * @subpackage DAO
 */
class Users extends DAO
{
    /**
     * @var int user's id
     * @access public
     */
    public $id;

    /**
     * @var int user's login
     * @access public
     */
    public $login;

    /**
     * @var int user's password
     * @access public
     */
    public $passwd;

    /**
     * @var int user's rights
     * @access public
     */
    public $rights;

    /**
     * @var int user's lang
     * @access public
     */
    public $lang;

    /**
     * @var int user's email
     * @access public
     */
    public $email;

    /**
     * @var int user's OpenID
     * @access public
     */
    public $openid;

    /**
     * @var int user's timezone
     * @access public
     */
    public $timezone;

    /**
     * @var array configuration per user
     * @access public
     */
    public $config;

    /**
     * Constructor
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function __construct()
    {
        $this->_idField = 'id';
        $this->_uniqueFields = array('login' => true, 'email' => true, 'openid' => false);
        $this->_fields = array(
            'id'        => array('required' => true, 'type' => \PDO::PARAM_INT),
            'login'     => array('required' => true, 'type' => DBRequest::PARAM_LOGIN),
            'passwd'    => array('required' => false, 'type' => DBRequest::PARAM_PASSWD),
            'rights'    => array('required' => true, 'type' => DBRequest::PARAM_RIGHTS),
            'lang'      => array('required' => true, 'type' => DBRequest::PARAM_LANG),
            'email'     => array('required' => true, 'type' => DBRequest::PARAM_EMAIL),
            'openid'    => array('required' => false, 'type' => DBRequest::PARAM_URL),
            'timezone'  => array('required' => true, 'type' => DBRequest::PARAM_TIMEZONE),
            'config'    => array('required' => true, 'type' => DBRequest::PARAM_SERIALIZED),
        );
        $this->_relations = array(
            'users_tokens'  => array('id'   => 'uid')
        );
        parent::__construct();
    }
}