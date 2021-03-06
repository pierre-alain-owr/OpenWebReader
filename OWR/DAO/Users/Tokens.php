<?php
/**
 * DAO Object representing the table users_tokens
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
 * @subpackage DAO\users
 */
namespace OWR\DAO\users;
use OWR\DAO,
    OWR\DB\Request as DBRequest;
/**
 * This object represents the table users_tokens
 * @uses DAO extends the base class
 * @uses DBRequest defines the fields type
 * @package OWR
 * @subpackage DAO\users
 */
class Tokens extends DAO
{
    /**
     * @var int user's id
     * @access public
     */
    public $uid;

    /**
     * @var string the token
     * @access public
     */
    public $token;

    /**
     * @var string the action
     * @access public
     */
    public $action;

    /**
     * @var string the key of the token
     * @access public
     */
    public $token_key;

    /**
     * Constructor
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function __construct()
    {
        $this->_idField = 'action';
        $this->_fields = array(
            'token'      => array('required' => true, 'type' => \PDO::PARAM_STR),
            'action'     => array('required' => true, 'type' => \PDO::PARAM_STR),
            'uid'        => array('required' => true, 'type' => \PDO::PARAM_INT),
            'token_key'  => array('required' => true, 'type' => \PDO::PARAM_STR)
        );
        $this->_relations = array(
            'users'  => array('uid'   => 'id')
        );
        parent::__construct();
    }
}