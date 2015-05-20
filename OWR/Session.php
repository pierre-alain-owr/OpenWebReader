<?php
/**
 * Session class
 * This class is the link to the DB for session managing
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
/**
 * This object is the session db handler
 * @uses Singleton implements the singleton pattern
 * @uses DB the database link
 * @uses Exception the exceptions handler
 * @package OWR
 */
class Session extends Singleton
{
    /**
    * @var mixed the DB instance
    * @access private
    */
    private $_db;
    
    /**
     * Inits the session
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param array $args optionnals arguments
     */
    public function init(array $args = array())
    {
        if(!empty($args))
        {
            session_set_cookie_params(
                $args['sessionLifeTime'],
                $args['path'],
                $args['domain'],
                $args['httpsecure'],
                true // only over http
            );
        }

        session_set_save_handler(
            array($this, '_open'),
            array($this, '_close'),
            array($this, '_read'),
            array($this, '_write'),
            array($this, '_destroy'),
            array($this, '_clean')
        );
        
        if(!session_start())
        {
            throw new Exception("Cant't start session", Exception::E_OWR_DIE);
        }
    }

    /**
     * Session opening handler
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function _open()
    {
        $this->_db = DB::iGet();
    
        return true;
    }
    
    /**
     * Session closing handler
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function _close()
    {
        $this->_db->commit();
        
        $this->_db = null;
    }
    
    /**
     * Session reading handler
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $id the id of the session to retrieve
     * @return mixed the datas or ''
     */
    public function _read($id)
    {
        $id = $this->_db->quote($id);
    
        $sql = "SELECT ip, data
                    FROM sessions
                    WHERE id = {$id}";
    
        $ret =  $this->_db->query($sql, \PDO::FETCH_ASSOC);
        
        if(FALSE === $ret)
        {
            throw new Exception("SQL Error ". DEBUG ? $this->_db->errorInfo() : '');
        }
        
        if($row = $ret->fetch())
        {
            if($row['ip'] === $_SERVER['REMOTE_ADDR'])
                return $row['data'];

            throw new Exception("Invalid IP", Exception::E_OWR_UNAUTHORIZED);
        }

        return '';
    }
    
    /**
     * Session writing handler
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $id the id of the session to store
     * @param string $data serialized datas to store to the session
     */
    public function _write($id, $data)
    {
        $id = $this->_db->quote($id);
        $access = $this->_db->quote(time());
        $data = $this->_db->quote($data);
        $ip = $this->_db->quote($_SERVER['REMOTE_ADDR']);
    
        $sql = "REPLACE
                    INTO sessions
                    VALUES ({$id}, {$access}, {$ip}, {$data})";
    
        $ret = $this->_db->exec($sql);

        return $ret;
    }
    
    /**
     * Session deleting handler
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $id the id of the session to delete
     */
    public function _destroy($id)
    {
        $id = $this->_db->quote($id);
    
        $sql = "DELETE
                    FROM sessions
                    WHERE id={$id}";
    
        $ret = $this->_db->exec($sql);

        return $ret;
    }
    
    /**
     * Session cleaning handler
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param int $max the max time that session can be inactive
     */
    public function _clean($max)
    {
        $old = time() - $max;
        $old = $this->_db->quote($old);
    
        $sql = "DELETE
                    FROM sessions
                    WHERE access < ".$old;
    
        $ret = $this->_db->exec($sql);

        return $ret;
    }

    /**
     * Session getter
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $var the name of the var to get
     * @return mixed the value if exists, or null
     */
    static public function get($var)
    {
        $var = (string) $var;

        if(false === strpos($var, '.'))
        {
            return isset($_SESSION[$var]) ? $_SESSION[$var] : null;
        }

        $var = explode('.', $var);

        $datas =& $_SESSION;

        foreach($var as $arr)
        {
            if(!isset($datas[$arr])) return null;
            $datas =& $datas[$arr];
        }

        return $datas;
    }

    /**
     * Session setter
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $var the name of the var to set
     * @param mixed $value the value to assign to $var
     * @return mixed the value on success, or false
     */
    static public function set($var, $value)
    {
        $var = (string)$var;

        if(false === strpos($var, '.'))
        {
            return ($_SESSION[$var] = $value);
        }

        $var = explode('.', $var);

        $datas =& $_SESSION;
        
        foreach($var as $arr)
        {
            if(!is_array($datas) || !isset($datas[(string)$arr])) return false;
            $datas =& $datas[$arr];
        }

        $datas = $value;

        return $value;
    }

    /**
     * Session ID regenerator
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param boolean $isLogged must-we delete previous session ?
     */
    public function regenerateSessionId($isLogged)
    {
        session_regenerate_id($isLogged);
        $newSession = session_id();
        session_write_close();
    
        session_id($newSession);

        $this->init();
    }
}
