<?php
/**
 * DAO Object representing the table news_contents
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
 * @subpackage DAO\news
 */
namespace OWR\DAO\news;
use OWR\DAO,
    OWR\DB\Request as DBRequest;
/**
 * This object represents the table news_contents
 * @uses DAO extends the base class
 * @uses DBRequest defines the fields type
 * @package OWR
 * @subpackage DAO\news
 */
class Contents extends DAO
{
    /**
     * @var int contents' id
     * @access public
     */
    public $id;

    /**
     * @var string the contents of the news
     * @access public
     */
    public $contents;

    /**
     * Constructor
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function __construct()
    {
        $this->_uniqueFields = array('id' => true);
        $this->_fields = array(
            'id'                =>  array('required' => true, 'type' => \PDO::PARAM_INT),
            'contents'          =>  array('required' => true, 'type' => \PDO::PARAM_STR)
        );
        $this->_userRelations = array(
            'news_relations'    => array('id'        => 'newsid')
        );
        $this->_relations = array(
            'news'              => array('id'        => 'id')
        );
        $this->_weight = 12;
        parent::__construct();
    }
}