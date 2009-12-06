<?php
/**
 * Object representing a request sent to the controller, from the REST api
 * This object is NOT designed to store objects, they will be automaticly transtyped to Request class
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
 * @subpackage Rest
 */
namespace OWR\REST;
use OWR\Request as R, 
    OWR\Config as Config,
    OWR\Object as Object,
    OWR\XML as XML;
/**
 * This object is sent to the Controller to be executed
 * @uses OWR\String convert M$ bad chars
 * @uses OWR\Request the main class
 * @uses OWR\Config the config object
 * @uses OWR\XML the (un)serializer
 * @package OWR
 * @subpackage Rest
 */
class Request extends R
{
    /**
    * @var string the method (PUT|GET|POST|DELETE)
    * @access private
    */
    private $_method;

    /**
    * @var string the HTTP-Accept, can be json/html/xml, default as json
    * @access private
    */
    private $_httpAccept = 'json';

    /**
    * @var string the Content-Type of the request, default as json
    * @access private
    */
    private $_contentType = 'json';

    /**
     * Constructor
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param array $datas the datas
     */
    public function __construct()
    {
        $datas = array();

        $this->_method = strtolower($_SERVER['REQUEST_METHOD']);

        if(!isset($_SERVER['HTTP_ACCEPT']) || (false !== strpos($_SERVER['HTTP_ACCEPT'], 'application/json')))
        {
            $this->_httpAccept = 'json';
        }
        elseif((false !== strpos($_SERVER['HTTP_ACCEPT'], 'text/xml')) || (false !== strpos($_SERVER['HTTP_ACCEPT'], 'application/xml')))
        {
            $this->_httpAccept = 'xml';
        }
        elseif((false !== strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))
        {
            $this->_httpAccept = 'html';
        }

        if(!isset($_SERVER['CONTENT_TYPE']) || 'application/json' === $_SERVER['CONTENT_TYPE'])
        {
            $this->_contentType = 'json';
        }
        elseif('text/xml' === $_SERVER['CONTENT_TYPE'] || 'application/xml' === $_SERVER['CONTENT_TYPE'])
        {
            $this->_contentType = 'xml';
        }

        $path = explode('/', $_SERVER["PATH_INFO"]);
        array_shift($path);
        if(!empty($path[1]))
        {
            $this->do = mb_strtolower((string) array_shift($path), 'UTF-8');
            $datas['id'] = (int) array_shift($path);
        }
        elseif(!empty($path[0]))
        {
            $this->do = mb_strtolower((string) array_shift($path), 'UTF-8');
        }
        else
        {
            $this->do = 'index';
        }

        switch($this->_method)
        {
            case 'get':
                switch($this->do)
                {
//                     case 'getopml':
//                         if(isset($path[0]))
//                             $datas['dl'] = $path[0];
// 
//                         break;

                    case 'getstream':
                        if(isset($path[1]))
                            $datas['offset'] = $path[1];
                        if(isset($path[2]))
                            $datas['sort'] = $path[2];
                        if(isset($path[3]))
                            $datas['dir'] = $path[3];
                        break;

                    case 'search':
                        if(isset($path[0]))
                            $datas['keywords'] = $path[0];
                        if(isset($path[1]))
                            $datas['offset'] = $path[1];
                        if(isset($path[2]))
                            $datas['sort'] = $path[2];
                        if(isset($path[3]))
                            $datas['dir'] = $path[3];
                        break;

                    default:
                        break;
                }
                break;

            case 'post':
                switch($this->do)
                {
                    case 'login':
                        !isset($_POST['tlogin']) || 
                        ($datas['tlogin'] = (string) $_POST['tlogin']);
                        !isset($_POST['key']) || 
                        ($datas['key'] = (string) $_POST['key']);
                        !isset($_POST['key']) ||
                        ($datas['uid'] = $_POST['uid']);
                        break;

                    case 'editopml':
                        !isset($_POST['url']) ||
                        ($datas['url'] = (string) $_POST['url']);
                        !isset($_POST['gid']) ||
                        ($datas['gid'] = $_POST['gid']);
                        break;

                    case 'editstream':
                        !isset($_POST['url']) ||
                        ($datas['url'] = (string) $_POST['url']);
                        !isset($_POST['name']) ||
                        ($datas['name'] = (string) $_POST['name']);
                        break;

                    case 'editstreamgroup':
                        !isset($_POST['name']) ||
                        ($datas['name'] = (string) $_POST['name']);
                        break;

                    case 'edituser':
                        !isset($_POST['login']) ||
                        ($datas['login'] = (string) $_POST['login']);
                        !isset($_POST['rights']) ||
                        ($datas['rights'] = (int) $_POST['rights']);
                        !isset($_POST['lang']) ||
                        ($datas['lang'] = (string) $_POST['lang']);
                        !isset($_POST['email']) ||
                        ($datas['email'] = (string) $_POST['email']);
                        !isset($_POST['openid']) ||
                        ($datas['openid'] = (string) $_POST['openid']);
                        !isset($_POST['timezone']) ||
                        ($datas['timezone'] = (string) $_POST['timezone']);
                        if(isset($_POST['passwd']))
                        {
                            $datas['passwd'] = (string) $_POST['passwd'];
                            !isset($_POST['confirmpasswd']) ||
                            ($datas['confirmpasswd'] = (string) $_POST['confirmpasswd']);
                        }
                        break;

                    default:
                        break;
                }
                break;

            case 'put':
                parse_str(file_get_contents('php://input'), $input);
                if(empty($input['datas'])) break;

                $data = $input['datas'];
                unset($input);

                if(!is_array($data))
                {
                    if('json' === $this->_contentType)
                    {
                        $data = @json_decode($data);
                        if($data) $data = Object::toArray($data);
                        else $data = null;
                    }
                    elseif('xml' === $this->_contentType)
                    {
                        $data = XML::unserialize($data);
                        if(!empty($data['datas'])) $data = $data['datas'];
                        else $data = null;
                    }

                    if(!is_array($data))
                    {
                        unset($data);
                        break;
                    }
                }

                if(empty($data)) break;

                switch($this->do)
                {
                    case 'rename':
                        !isset($data['name']) ||
                        ($datas['name'] = (string) $data['name']);
                        break;
                    case 'move':
                        !isset($data['gid']) ||
                        ($datas['gid'] = $data['gid']);
                        break;
                    case 'edituser':
                        !isset($data['login']) ||
                        ($datas['login'] = (string) $data['login']);
                        !isset($data['rights']) ||
                        ($datas['rights'] = (int) $data['rights']);
                        !isset($data['lang']) ||
                        ($datas['lang'] = (string) $data['lang']);
                        !isset($data['email']) ||
                        ($datas['email'] = (string) $data['email']);
                        !isset($data['openid']) ||
                        ($datas['openid'] = (string) $data['openid']);
                        !isset($data['timezone']) ||
                        ($datas['timezone'] = (string) $data['timezone']);
                        if(isset($data['passwd']))
                        {
                            $datas['passwd'] = (string) $data['passwd'];
                            !isset($data['confirmpasswd']) ||
                            ($datas['confirmpasswd'] = (string) $data['confirmpasswd']);
                        }
                        break;
                }

                unset($data);
                break;

            case 'delete':
            default: 
                break;
        }

        foreach(array('id', 'gid', 'currentid', 'uid', 'offset', 'timestamp', 'live', 'dl') as $k)
        {
            (isset($datas[$k]) && $this->$k = (int) $datas[$k]) || $this->$k = 0;
            unset($datas[$k]);
        }

        $this->ids = array();

        if(isset($datas['ids']))
        {
            if(is_array($datas['ids']))
            {
                foreach($datas['ids'] as $k=>$id)
                    $this->ids[$k] = (int) $id;
            }

            unset($datas['ids']);
        }

        if(isset($datas['sort']))
        {
            $datas['sort'] = (string) $datas['sort'];
            if($datas['sort'] === 'pubdate') $datas['sort'] = 'pubDate';

            $authorized = array('title'=>'news', 'pubDate'=>'news', 'status'=>'news_relations');
            if(isset($authorized[$datas['sort']]))
            {
                $this->sort = $authorized[$datas['sort']].'.'.$datas['sort'];
                $this->order = $datas['sort'];
            }
            unset($datas['sort']);

            if(isset($datas['dir']) && $this->sort)
            {
                $datas['dir'] = (string) $datas['dir'];
                (($datas['dir'] === 'desc' || $datas['dir'] === 'asc') && $this->dir = $datas['dir']) || $this->dir = 'DESC';
            }
            else $this->dir = 'DESC';
            unset($datas['dir']);
        }

        $this->page = '';

        $this->_setDatas($datas);

        unset($datas);

        (!empty($this->lang) && $this->lang = (string) $this->lang) ||
        ($this->lang = Config::iGet()->get('default_language'));
    }

    /**
     * Returns the method used to call the api
     *
     * @access public
     * @return string method
     */
    public function getMethod()
    {
        return (string) $this->_method;
    }

    /**
     * Returns the HTTP_ACCEPT used to call the api
     *
     * @access public
     * @return string method
     */
    public function getHTTPAccept()
    {
        return (string) $this->_httpAccept;
    }
}
