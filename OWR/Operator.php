<?php
/**
 * Operator class
 * This class is used to generate an url related for the specified operator
 * that will be used to redirect the user for bookmarking
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
 * @deprecated replaced by addthis, just keep in case
 */
namespace OWR;
use OWR\DAO\News as News;
/**
 * This object is used to generate an url related for the specified operator
 * @todo use DB instead of hard-coded datas
 * @package OWR
 * @uses OWR\DAO\News 
 */
class Operator
{
    /**
     * @var array associative array of list of operators => link
     * @access protected
     */
    protected $_operators = array(
        'delicious'     => 'http://delicious.com/save?url=%s&title=%s',
        'digg'          => 'http://digg.com/submit/?url=%s&title=%s',
        'wikio'         => 'http://www.wikio.fr/vote?url=%s',
        'facebook'      => 'http://www.facebook.com/sharer.php?u=%s',
        'twitter'       => 'http://twitter.com/home?status=%s',
        'myspace'       => 'http://www.myspace.com/Modules/PostTo/Pages/?u=%s',
        'yahoobm'       => 'http://bookmarks.yahoo.com/toolbar/savebm?opener=tb&u=%s&t=%s',
        'yahoomw'       => 'http://myweb.yahoo.com/myresults/bookmarklet?ei=UTF-8&u=%s&t=%s',
        'google'        => 'http://www.google.com/bookmarks/mark?op=add&bkmk=%u&title=%s&nui=1&service=bookmarks',
        'blogmarks'     => 'http://blogmarks.net/my/new.php?mini=1&simple=1&url=%s&title=%s',
        'technorati'    => 'http://technorati.com/faves?add=%s',
        'misterwong'    => 'http://www.mister-wong.com/index.php?action=addurl&bm_url=%s&bm_description=%s',
        'newsvine'      => 'http://www.newsvine.com/_tools/seed&save?u=%s&h=%s',
        'reddit'        => 'http://reddit.com/submit?url=%s&title=%s',
        'viadeo'        => 'http://www.viadeo.com/shareit/share/?url=%s&title=%s',
        'netvibes'      => 'http://netvibes.com/share?url=%s&title=%s',
        'identica'      => 'http://identi.ca/?action=newnotice&url=%s&status_textarea=%s',
        'fark'          => 'http://cgi.fark.com/cgi/fark/submit.pl?new_url=%s&new_title=%s',
        'slashdot'      => 'http://slashdot.org/bookmark.pl?url=%s',
        'propeller'     => 'http://www.propeller.com/story/submit/?U=%s&T=%s',
        'mixx'          => 'http://www.mixx.com/submit?page_url=%s',
        'multiply'      => 'http://multiply.com/gus/journal/compose/?body=&url=%s&subject=%s',
        'simpy'         => 'http://www.simpy.com/simpy/LinkAdd.do?href=%s&title=%s',
        'diigo'         => 'http://diigo.com/post?url=%s&title=%s',
        'faves'         => 'http://faves.com/Authoring.aspx?u=%s',
        'spurl'         => 'http://www.spurl.net/spurl.php?url=%s&title=%s',
        'linkagogo'     => 'http://www.linkagogo.com/go/AddNoPopup?url=%s&title=%s',
        'feedmelinks'   => 'http://feedmelinks.com/categorize?loggedIn=wasnt&from=toolbar&op=submit&url=%s&name=%s',
        'segnalo'       => 'http://segnalo.virgilio.it/post.html.php?url=%s&title=%s',
        'netvouz'       => 'http://netvouz.com/action/submitBookmark?popup=no&url=%s&title=%s',
        'stumbleupon'   => 'http://stumbleupon.com/submit?url=%s&title=%s',
        'dotnetkicks'   => 'http://www.dotnetkicks.com/submit/?url=%s&title=%s',
        'sync2it'       => 'http://www.sync2it.com/addbm.php?url=%s',
        'meneame'       => 'http://www.meneame.net/submit.php?url=%s'
    );

    /**
     * @var string the link of the current operator
     * @access protected
     */
    protected $_operator;
    
    /**
     * Constructor
     * Set the current operator
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param string $operator the name of the operator
     */
    public function __construct($operator)
    {
        $operator = mb_strtolower((string) $operator, 'UTF-8');
        if(!isset($this->_operators[$operator]))
            throw new Exception('Invalid operator', Exception::E_OWR_BAD_REQUEST);

        $this->_operator = $this->_operators[$operator];
    }

    /**
     * Redirects the user to the current operator
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     */
    public function redirect(News $new)
    {
        if(empty($this->_operator))
            throw new Exception('Not yet available', Exception::E_OWR_DIE);

        $location = sprintf($this->_operator, urlencode($new->link), urlencode($new->title));
        if(!headers_sent())
            header('Location: '.$location);
        else
        {
            $page = '<a href="'.$location.'">Redirection</a>';
            $page .= '<script type="text/javascript">';
            $page .= 'window.location.href="'.$location.'";';
            $page .= '</script>';
            $page .= '<noscript>';
            $page .= '<meta http-equiv="refresh" content="0;url='.$location.'" />';
            $page .= '</noscript>';
            echo $page;
        }

        flush();
        exit;
    }
}