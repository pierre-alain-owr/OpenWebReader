<?php
/**
 * User class
 * This class represents a user
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
use OWR\DB\Request as DBRequest;
/**
 * This object represents the user running the script
 * @uses Singleton implements the singleton pattern
 * @uses Exception the exceptions handler
 * @uses DBRequest a request sent to the database
 * @uses DB the link database
 * @package OWR
 */
class User extends Singleton
{
    /**
    * @var int not logged-in user
    */
    const LEVEL_VISITOR = 0;

    /**
    * @var int simple use
    */
    const LEVEL_USER = 1;

    /**
    * @var int administrator (and CLI user)
    */
    const LEVEL_ADMIN = 2;

    /**
    * @var int user id
    * @access private
    */
    private $_uid = 0;

    /**
    * @var int user rights
    * @access private
    */
    private $_rights = 0;

    /**
    * @var string user login
    * @access private
    */
    private $_login = '';

    /**
    * @var string user lang
    * @access private
    */
    private $_lang;

    /**
    * @var string user lang (xml format)
    * @access private
    */
    private $_xmlLang;

    /**
    * @var string user timezone
    * @access private
    */
    private $_timezone;

    /**
    * @var string user token
    * @access private
    */
    private $_token;

    /**
    * @var string user browser agent
    * @access private
    */
    private $_agent;

    /**
    * @var array list of timezones
    * @access private
    */
    private $_tz;

    /**
     * Constructor
     *
     * @access protected
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param array $user the user values
     */
    protected function __construct(array $user)
    {
        if(CLI)
        { // automatic
            $this->_rights = self::LEVEL_ADMIN;
            $this->_timezone = Config::iGet()->get('date_default_timezone');
            $this->_lang = Config::iGet()->get('default_language');
            $this->_xmlLang = str_replace('_', '-', $this->_lang);
        }

        $this->setTimezone();
    }

    /**
     * Executed when deserializing this object
     * Register $this to PrivateSingleton, set the date_timezone and lang
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     */
    public function __wakeUp()
    {
        parent::__wakeUp();
        $this->setLang();
        date_default_timezone_set($this->_timezone);
    }

    /**
     * Set the values for the current user
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @param array $user the values to set
     */
    protected function _setUser(array $user)
    {
        if(isset($user['id']))
        {
            $this->_setUid($user['id']);
        }

        if(isset($user['rights']))
        {
            $this->_setRights($user['rights']);
        }

        if(isset($user['login']))
        {
            $this->_setLogin($user['login']);
        }

        if(isset($user['lang']))
        {
            $this->setLang($user['lang']);
        }

        if(isset($user['timezone']))
        {
            $this->setTimezone($user['timezone']);
        } else $this->setTimezone();
    }

    /**
     * Returns true if logged-in as an admin
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return boolean true if admin
     */
    public function isAdmin()
    {
        return (bool) ($this->_rights >= self::LEVEL_ADMIN);
    }

    /**
     * Returns true if logged-in
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return boolean true if logged-in at least as a user
     */
    public function isLogged()
    {
        return (bool) ($this->_rights > self::LEVEL_VISITOR);
    }

    /**
     * Set the login
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @param string $login the login
     * @return boolean true if admin
     */
    protected function _setLogin($login = '')
    {
        $this->_login = (string) $login;
    }

    /**
     * Set the rights
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @param int $rights the rights
     * @return int the rights
     */
    protected function _setRights($rights = self::LEVEL_VISITOR)
    {
        $rights = (int) $rights;
        switch($rights)
        {
            case self::LEVEL_VISITOR:
            case self::LEVEL_USER:
            case self::LEVEL_ADMIN:
                $this->_rights = $rights;
                break;
            default:
                $this->_rights = self::LEVEL_VISITOR;
                break;
        }

        return $this->_rights;
    }

    /**
     * Set the lang and variables for gettext
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param string $lang the lang
     * @return string the lang
     */
    public function setLang($lang=null)
    {
        if(isset($lang) || empty($this->_lang))
        {
            $this->_lang = $this->_xmlLang = null;

            if(isset($lang))
            {
                $langs = explode(',', $lang);
                $locales = array();
                foreach($langs as $lang)
                {
                    $lang = explode(';', $lang);
                    $lang = trim(str_replace("\n", '', $lang[0]));
                    if(!$lang || !preg_match("/^([a-z]{2})[-_]([a-z]{2})$/i", $lang, $m)) continue;
                    $locales[] = $m[1].'_'.strtoupper($m[2]);
                }

                if(empty($locales))
                {
                    $lang = Config::iGet()->get('default_language');
                    if(!@setlocale(LC_ALL, $lang.'.UTF8') && !@setlocale(LC_ALL, $lang.'.UTF-8'))
                    {
                        throw new Exception('Missing locale ! ('.$lang.'.UTF8)');
                    }
                    $this->_lang = $lang;
                }
                else
                {
                    foreach($locales as $locale)
                    {
                        if(@setlocale(LC_ALL, $locale.'.UTF8') || @setlocale(LC_ALL, $locale.'.UTF-8'))
                        {
                            $this->_lang = $locale;
                            break;
                        }
                    }
                    
                    if(empty($this->_lang))
                    {
                        $lang = Config::iGet()->get('default_language');
                        if(!@setlocale(LC_ALL, $lang.'.UTF8') && !@setlocale(LC_ALL, $lang.'.UTF-8'))
                        {
                            throw new Exception('Missing locale ! ('.$lang.'.UTF8)');
                        }
                        $this->_lang = $lang;
                    }
                }
            }
            else
            {
                $lang = Config::iGet()->get('default_language');
                if(!@setlocale(LC_ALL, $lang.'.UTF8') && !@setlocale(LC_ALL, $lang.'.UTF-8'))
                {
                    throw new Exception('Missing locale ! ('.$lang.'.UTF8)');
                }
                $this->_lang = $lang;
            }
        }
        else
        {
            if(!@setlocale(LC_ALL, $this->_lang.'.UTF8') && !@setlocale(LC_ALL, $this->_lang.'.UTF-8'))
            {
                throw new Exception('Missing locale ! ('.$this->_lang.'.UTF8)');
            }
        }
        
        putenv('LANG='.$this->_lang);
        bindtextdomain('messages', HOME_PATH.'locale');
        textdomain('messages');
        bind_textdomain_codeset('messages', 'UTF-8');

        $this->_xmlLang = str_replace('_', '-', $this->_lang);

        return $this->_lang;
    }

    /**
     * Resets the user to default values
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function reset()
    {
        $this->_rights = self::LEVEL_VISITOR;
        $this->setTimezone();
        $this->setLang();
        $this->_setRights();
        $this->_setLogin();
        $this->_setUid();
        $this->regenerateToken();
    }

    /**
     * Sets the user's id
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @param int $uid the id
     * @return int the id
     */
    private function _setUid($uid = 0)
    {
        $this->_uid = (int) $uid;
        return $this->_uid;
    }

    /**
     * Sets the timezone
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param string $timezone the timezone, optionnal
     * @return string the timezone
     */
    public function setTimezone($timezone=null)
    {
        if(isset($timezone))
        {
            $this->getTimeZones();

            if(!isset($this->_tz[$timezone]))
            {
                $timezone = Config::iGet()->get('date_default_timezone');
            }
        }
        else
        {
            $timezone = Config::iGet()->get('date_default_timezone');
        }

        $this->_timezone = $timezone;
        date_default_timezone_set($this->_timezone);
        return $timezone;
    }

    /**
     * Gets the timezone(s)
     * If a timezone is passed by argument, we will check for this validity
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param string $timezone the timezone, optionnal
     * @return string the timezone
     */
    public function getTimeZones($timezone='')
    {
        if(!isset($this->_tz))
        {
            $this->_tz = array();
            $iterator1 = new \ArrayIterator(\DateTimeZone::listAbbreviations());
            foreach($iterator1 as $k1=>$v1)
            {
                $iterator2 = new \ArrayIterator($v1);
                foreach($iterator2 as $k2=>$v2)
                {
                    $this->_tz[] = $v2['timezone_id'];
                }
            }
            $this->_tz = array_unique($this->_tz);
            sort($this->_tz);
            unset($this->_tz[0]);
            $this->_tz = array_flip($this->_tz);
        }

        if($timezone)
        {
            return isset($this->_tz[$timezone]) ? $timezone : Config::iGet()->get('date_default_timezone');
        }

        return $this->_tz;
    }

    /**
     * Regenerate a token for the current user
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     */
    public function regenerateToken()
    {
        $this->_token = md5($this->_uid.uniqid(mt_rand(), true));
        $this->_agent = md5($this->_token.(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'X'));
    }

    /**
     * Checks the validity of the token
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param boolean $auto automatic check
     * @param int $uid the user's id
     * @param string &$login the user's token
     * @param string &$key the token's key
     * @param string $action the related token's action
     * @return boolean true on success
     */
    public function checkToken($auto = false, $uid=0, &$login='', &$key='', $action='')
    {
        if($auto)
        {
            if(!$uid || !$login || !$key || !$action)
            {
                $login = $key = null;
                $this->reset();
                return false;
            }

            $login = (string) $login;
            $key = (string) $key;

            $query = '
        SELECT id, login, rights, lang, email, openid, timezone, token, token_key
            FROM users_tokens ut
            JOIN users u ON (u.id=ut.uid)
            WHERE uid=? AND action=?';

            $user = DB::iGet()->getRowP($query, new DBRequest(array($uid, $action)));
            
            if(!$user->next() || $user->token !== $login || $user->token_key !== $key)
            {
                $user = $login = $key = null;
                return false;
            }
            else
            {
                $this->_setUser((array)$user);
                $user = null;
                return true;
            }
        }
        elseif(!REST)
        {
            if(!isset($_POST['token']) || !isset($this->_token) || !isset($this->_agent) ||
                $_POST['token'] !== $this->_token || 
                $this->_agent !== md5($this->_token.(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'X')))
            {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Unregisters the user from the session
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     */
    public function unregister()
    {
        $_SESSION['User'] = null;
    }

    /**
     * Registers the user into the session
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     */
    public function reg()
    {
        $_SESSION['User'] = $this;
    }

    /**
     * Auth a user using OpenID
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param string $openid the user's openid
     * @return boolean true on success
     */
    public function openIdAuth($openid)
    {
        $row = DB::iGet()->getRowP('
    SELECT id, login, rights, lang, email, openid, timezone
        FROM users
        WHERE openid=?', new DBRequest((array)$openid));
        if(!$row->next())
        {
            $this->reset();
            return false;
        }

        $this->_setUser((array)$row);
        $row = null;
        return true;
    }

    /**
     * Auth a user using his login and password
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param string $login the login
     * @param string $pass the password
     * @return boolean true if admin
     */
    public function auth($login, $pass)
    {
        $login = (string) $login;
        $pass = (string) $pass;

        $row = DB::iGet()->getRowP('
    SELECT *
        FROM users
        WHERE login=?', new DBRequest((array)$login));
        if(!$row->next() || $pass !== $row->passwd)
        {
            $pass = $row = null;
            $this->reset();
            return false;
        }
        
        $this->_setUser((array)$row);
        $row = $pass = null;
        return true;
    }

    /**
     * Returns the user's login
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return string the user's login
     */
    public function getLogin()
    {
        return (string) $this->_login;
    }

    /**
     * Returns the user's rights
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return int the user's rights
     */
    public function getRights()
    {
        return (int) $this->_rights;
    }

    /**
     * Returns the user's lang
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return string the user's lang
     */
    public function getLang()
    {
        return (string) $this->_lang;
    }

    /**
     * Returns the user's lang in XML format
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return string the user's lang in XML format
     */
    public function getXMLLang()
    {
        return (string) $this->_xmlLang;
    }

    /**
     * Returns the user's id
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return int the user's id
     */
    public function getUid()
    {
        return (int) $this->_uid;
    }

    /**
     * Returns the user's token
     * It will generate one if any
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return string the user's token
     */
    public function getToken()
    {
        if(!$this->_token)
        {
            $this->regenerateToken();
        }
        return (string) $this->_token;
    }

    /**
     * Returns the user's browser agent
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return string the user's browser agent
     */
    public function getAgent()
    {
        return (string) $this->_agent;
    }

    /**
     * Sets the user ID
     * Only when in CLI mode
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param int $uid the user's id
     */
    public function setUid($id)
    {
        if(!CLI) return false;

        $this->_uid = (int) $id;
    }

    /**
     * Returns the user's token related to the given action
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param string $action the action
     * @return string the user's token
     */
    public function regenerateActionToken($action)
    {
        $action = (string) $action;

        $tokens = array();

        $tokens['tlogin'] = 
                md5($this->_uid.$action.uniqid(mt_rand(), true)) .
                md5($this->_uid.$action.uniqid(mt_rand(), true)) .
                md5($this->_uid.$action.uniqid(mt_rand(), true));
        
        $tokens['tlogin_key'] = substr(md5($tokens['tlogin'].uniqid(mt_rand(), true)), 0, 5);
        
        $query = '
        REPLACE INTO users_tokens
            SET action=?, uid=?, token=?, token_key=?';
        DB::iGet()->setP($query, new DBRequest(array($action, $this->_uid, $tokens['tlogin'], $tokens['tlogin_key'])));
        
        return $tokens;
    }
}