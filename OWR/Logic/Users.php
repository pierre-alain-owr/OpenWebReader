<?php
/**
 * Logic for 'users' object
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
use OWR\Logic as Logic,
    OWR\Request as Request,
    OWR\Exception as Exception,
    OWR\DAO as DAO,
    OWR\User as User,
    OWR\DB\Request as DBRequest,
    OWR\Object as Object;
/**
 * This class is used to add/edit/delete users and his related tables ()
 * @package OWR
 * @uses OWR\Logic extends the base class
 * @uses OWR\Request the request
 * @uses OWR\Exception the exception handler
 * @uses OWR\DAO the DAO
 * @uses OWR\User the user
 * @uses OWR\DB\Request a request sent to DB
 * @uses OWR\Object transforms an object to an array
 * @subpackage Logic
 */
class Users extends Logic
{
    /**
     * Adds/Edits a user
     *
     * @access public
     * @param mixed $request the Request instance
     * @return $this
     */
    public function edit(Request $request)
    {
        $query = '
    SELECT COUNT(id) AS nb
        FROM users';
        $exists = $this->_db->getOne($query);

        if($exists->next() && $exists->nb && ($request->id !== User::iGet()->getUid() && !User::iGet()->isAdmin()))
        {
            $request->setResponse(new Response(array(
                'do'        => 'redirect',
                'location'  => 'login',
                'status'    => Exception::E_OWR_UNAUTHORIZED
            )));
            return $this;
        }

        $nb = $exists->nb;

        $datas = array('id'=>$request->id);

        if(empty($_POST))
        {
            $request->setResponse(new Response(array(
                'tpl'       => 'edituser',
                'datas'     => $datas
            )));
            return $this;
        }
        
        if(!User::iGet()->checkToken())
        {
            $request->setResponse(new Response(array(
                'do'        => 'redirect',
                'location'  => 'logout',
                'status'    => Exception::E_OWR_UNAUTHORIZED
            )));
            return $this;
        }
        
        if(empty($request->login) || (!$request->id && empty($request->passwd)) || empty($request->email))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'tpl'       => 'edituser',
                'error'     => 'Please fill all the fields.',
                'datas'     => $datas,
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        if(mb_strlen($request->login, 'UTF-8') > 55)
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'tpl'       => 'edituser',
                'error'     => 'Login too long, please limit it to 55 chars.',
                'datas'     => $datas,
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        if((!$request->id && (empty($request->passwd) || 
            empty($request->confirmpasswd) || $request->passwd !== $request->confirmpasswd))
            || (0 < $request->id && !empty($request->passwd) && !empty($request->confirmpasswd)
                && $request->passwd !== $request->confirmpasswd))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'tpl'       => 'edituser',
                'error'     => 'Passwords are not identiquals.',
                'datas'     => $datas,
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        if($nb)
        {
            $args = array($request->login);
            $query = '
    SELECT id
        FROM users
        WHERE (login=?';
                
            if(!empty($request->openid))
            {
                if(false === mb_strpos($request->openid, 'http://', 0, 'UTF-8'))
                        $request->openid = 'http://'.$request->openid;
                if('/' !== mb_substr($request->openid, -1, 1, 'UTF-8'))
                        $request->openid .= '/';
                $query .= ' OR openid=?)';
                array_push($args, $request->openid);
            }
            else $query .= ')';
            
            if(0 < $request->id)
            {
                $query .= ' AND id != '.$request->id;
            }
            
            $exists = $this->_db->getOneP($query, new DBRequest($args));
            unset($args);
            if($exists->count())
            {
                $request->setResponse(new Response(array(
                    'do'        => 'error',
                    'tpl'       => 'edituser',
                    'datas'     => $datas,
                    'error'     => 'Login or openid already used. Please choose another.',
                    'status'    => 409 // conflict
                )));
                return $this;
            }
            
            $request->rights = (int)$request->rights;
            
            if($request->rights > User::iGet()->getRights() || $request->rights > User::LEVEL_ADMIN)
            {
                $request->setResponse(new Response(array(
                    'do'        => 'error',
                    'tpl'       => 'edituser',
                    'datas'     => $datas,
                    'error'     => 'You can\'t create user with rights higher than yours.',
                    'status'    => Exception::E_OWR_BAD_REQUEST
                )));
                return $this;
            }
        }
        else
        { // first registered user = admin
            $request->rights = User::LEVEL_ADMIN;
        }
        
        $request->timezone = User::iGet()->getTimeZones($request->timezone);
        $request->ulang = User::iGet()->getLang($request->ulang);

        if(!empty($request->passwd) && !empty($request->confirmpasswd))
        {
            $args = array(
                'login'     => $request->login, 
                'passwd'    => md5($request->login.$request->passwd), 
                'rights'    => $request->rights, 
                'lang'      => $request->ulang, 
                'email'     => $request->email, 
                'timezone'  => $request->timezone, 
                'id'        => $request->id,
                'openid'    => $request->openid
            );
        }
        else
        {
            $args = array(
                'login'     => $request->login, 
                'rights'    => $request->rights, 
                'lang'      => $request->ulang, 
                'email'     => $request->email, 
                'timezone'  => $request->timezone, 
                'id'        => $request->id,
                'openid'    => $request->openid
            );
        }

        unset($request->passwd, $request->confirmpasswd); // remove from memory !

        if($request->id)
        {
            $user = $this->_dao->get($request->id);
            if(!$user)
            {
                $request->setResponse(new Response(array(
                    'do'        => 'error',
                    'error'     => 'Invalid id',
                    'status'    => Exception::E_OWR_BAD_REQUEST
                )));
                return $this;
            }
            $request->new = false;
        }
        else
        {
            $user = $this->_dao;
            $request->new = true;
        }

        $user->populate($args);
        unset($args);

        $this->_db->beginTransaction();
        $request->id = $user->save($request->new);

        if(!$nb || (!$request->new && (int)$request->id === (int)User::iGet()->getUid()))
        {
            if(!User::iGet()->auth($user->login, $user->passwd))
            { // ???
                unset($user);
                $request->setResponse(new Response(array(
                    'do'        => 'error',
                    'error'     => 'Internal error',
                    'status'    => Exception::E_OWR_DIE
                )));
                return $this;
            }
        }
        $this->_db->commit();

        unset($user);

        $request->setResponse(new Response(array(
            'do'        => 'redirect',
            'status'    => $request->new ? 201 : 200, // 201 on creation
            'datas'     => array('id' => $request->id)
        )));

        return $this;
    }

    /**
     * Deletes a user
     *
     * @access public
     * @param mixed $request the Request instance
     * @return $this
     */
    public function delete(Request $request)
    {
        if(!$request)
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Missing id',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        $type = DAO::getType($request->id);
        if('users' !== $type)
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Invalid id',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }


        $this->_db->beginTransaction();
        try
        {
            DAO::getCachedDAO('objects')->delete($request->id);
        }
        catch(Exception $e)
        {
            $this->_db->rollback();
            throw new Exception($e->getContent(), $e->getCode());
        }
        $this->_db->commit();


        if($request->id === User::iGet()->getUid())
        {
            $request->setResponse(new Response(array(
                'do'        => 'redirect',
                'location'  => 'logout'
            )));
        }
        else
        {
            $request->setResponse(new Response);
        }

        return $this;
    }

    /**
     * Gets datas to render a user
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $request the Request instance
     * @param array $args additional arguments, optionnal
     * @param string $order the order clause
     * @param string $groupby the groupby clause
     * @param string $limit the limit clause
     * @return $this
     */
    public function view(Request $request, array $args = array(), $order = '', $groupby = '', $limit = '')
    {
        $args['FETCH_TYPE'] = 'assoc';

        if(!empty($request->ids))
        {
            $datas = array();
            $dao = $this->_dao;

            foreach($request->ids as $id)
            {
                $args['id'] = $id;
                $data = $dao->get($args, 'id,login,rights,lang,email,openid,timezone', $order, $groupby, $limit);
                if(!$data)
                {
                    $request->setResponse(new Response(array(
                        'do'        => 'error',
                        'error'     => 'Invalid id',
                        'status'    => Exception::E_OWR_BAD_REQUEST
                    )));
                    return $this;
                }
                $datas[] = $data;
            }
        }
        elseif(!empty($request->id))
        {
            $args['id'] = $request->id;
            $datas = $this->_dao->get($args, 'id,login,rights,lang,email,openid,timezone', $order, $groupby, $limit);
            if(!$datas)
            {
                $request->setResponse(new Response(array(
                    'do'        => 'error',
                    'error'     => 'Invalid id',
                    'status'    => Exception::E_OWR_BAD_REQUEST
                )));
                return $this;
            }
        }
        else
        {
            $datas = $this->_dao->get($args, 'id,login,rights,lang,email,openid,timezone', $order, $groupby, $limit);
            if(!$datas)
            {
                $request->setResponse(new Response);
                return $this;
            }
        }

        $request->setResponse(new Response(array(
            'datas'        => $datas
        )));
        return $this;
    }

    /**
     * Deletes everything related to a user
     *
     * @access public
     * @param mixed $request the Request instance
     * @return $this
     */
    public function deleteRelated(Request $request)
    {
        $this->_db->beginTransaction();
        try
        {
            DAO::getCachedDAO('streams_groups')->delete();
            DAO::getCachedDAO('news_relations')->delete();
            DAO::getCachedDAO('streams_relations')->delete();
            DAO::getCachedDAO('streams_relations_name')->delete();
        }
        catch(Exception $e)
        {
            $this->_db->rollback();
            throw new Exception($e->getContent(), $e->getCode());
        }
        $this->_db->commit();

        $request->setResponse(new Response(array(
            'status'    => 204 // OK, no content to return
        )));

        return $this;
    }

    /**
     * Changes the user interface language
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param string $newLang the new lang
     * @param mixed $uid the id of the user
     */
    public function changeLang(Request $request)
    {
        if(empty($request->newlang))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Empty lang',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        $newLang = (string) User::iGet()->setLang($request->newlang);

        $dao = $this->_dao->get(User::iGet()->getUid(), 'id,lang');
        if(!$dao)
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Invalid user id',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
        }

        $dao->lang = $newLang;
        $dao->save();

        $request->setResponse(new Response);

        return $this;
    }
}