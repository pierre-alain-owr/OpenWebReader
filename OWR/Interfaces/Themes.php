<?php
/**
 * Interface for themes
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
 * This class is used to declare public functions of themes
 * @package OWR
 * @subpackage Interfaces
 */
interface Themes
{
    /**
     * Instance getter
     * This function can NOT be overloaded
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return mixed the instance
     */
    static public function iGet();

    /**
     * Generates index template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of index template 
     */
    public function index(array $datas, array $noCacheDatas);

    /**
     * Generates login template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of login template
     */
    public function login(array $datas, array $noCacheDatas);

    /**
     * Generates opensearch template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of opensearch template
     */
    public function opensearch(array $datas, array $noCacheDatas);

    /**
     * Generates opml template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of opml template
     */
    public function opml(array $datas, array $noCacheDatas);

    /**
     * Generates rss template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of rss template
     */
    public function rss(array $datas, array $noCacheDatas);

    /**
     * Generates categories block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of categories block template
     */
    public function categories(array $datas, array $noCacheDatas);

    /**
     * Generates category block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of category block template
     */
    public function category(array $datas, array $noCacheDatas);

    /**
     * Generates posts block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of posts block template
     */
    public function posts(array $datas, array $noCacheDatas);

    /**
     * Generates post block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of post block template
     */
    public function post(array $datas, array $noCacheDatas);

    /**
     * Generates post_details block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of post_details block template
     */
    public function post_details(array $datas, array $noCacheDatas);

    /**
     * Generates post_content block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of post_content block template
     */
    public function post_content(array $datas, array $noCacheDatas);

    /**
     * Generates post_tags block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of post_tags block template
     */
    public function post_tags(array $datas, array $noCacheDatas);

    /**
     * Generates tags block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of tags block template
     */
    public function tags(array $datas, array $noCacheDatas);

    /**
     * Generates tag block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of tag block template
     */
    public function tag(array $datas, array $noCacheDatas);

    /**
     * Generates users block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of users block template
     */
    public function users(array $datas, array $noCacheDatas);

    /**
     * Generates user template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of user template
     */
    public function user(array $datas, array $noCacheDatas);

    /**
     * Generates stats block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of stats block template
     */
    public function stats(array $datas, array $noCacheDatas);
}