<?php
/**
 * Model for 'users' object
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
 * @subpackage Model
 */
namespace OWR\Model;
use OWR\Model,
    OWR\Request,
    OWR\Exception,
    OWR\DAO,
    OWR\User,
    OWR\DB\Request as DBRequest,
    OWR\Object;
/**
 * This class is used to add/edit/delete users and his related tables ()
 * @package OWR
 * @uses OWR\Model extends the base class
 * @uses OWR\Request the request
 * @uses OWR\Exception the exception handler
 * @uses OWR\DAO the DAO
 * @uses OWR\User the user
 * @uses OWR\DB\Request a request sent to DB
 * @uses OWR\Object transforms an object to an array
 * @subpackage Model
 */
class Users extends Model
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
                'tpl'       => 'user',
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
                'tpl'       => 'user',
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
                'tpl'       => 'user',
                'error'     => 'Login too long, please limit it to 55 chars.',
                'datas'     => $datas,
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        if((empty($request->id) && (empty($request->passwd) ||
            empty($request->confirmpasswd) || $request->passwd !== $request->confirmpasswd))
            || (0 < $request->id && !empty($request->passwd) && !empty($request->confirmpasswd)
                && $request->passwd !== $request->confirmpasswd))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'tpl'       => 'user',
                'error'     => 'Passwords are not identiquals.',
                'datas'     => $datas,
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        if(!empty($nb))
        {
            $args = array($request->login);
            $query = '
    SELECT id
        FROM users
        WHERE (login=?)';

            if(!empty($request->id))
            {
                $query .= ' AND id != '. (int) $request->id;
            }

            $exists = $this->_db->getOneP($query, new DBRequest($args));
            unset($args);
            if($exists->count())
            {
                $request->setResponse(new Response(array(
                    'do'        => 'error',
                    'tpl'       => 'user',
                    'datas'     => $datas,
                    'error'     => 'Login already used. Please choose another.',
                    'status'    => 409 // conflict
                )));
                return $this;
            }

            $request->rights = (int) $request->rights;

            if($request->rights > User::iGet()->getRights() || $request->rights > User::LEVEL_ADMIN)
            {
                $request->setResponse(new Response(array(
                    'do'        => 'error',
                    'tpl'       => 'user',
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

        // TODO : check this dynamicly
        $cfg = Object::toArray($request->config);
        $cfg['nbnews'] = (int) (isset($cfg['nbnews']) && $cfg['nbnews'] > 0 && $cfg['nbnews'] <= 50 && !($cfg['nbnews']%10) ? $cfg['nbnews'] : 10);
        $cfg['blockimg'] = (bool) (isset($cfg['blockimg']) ? $cfg['blockimg'] : false);
        $cfg['abstract'] = (bool) (isset($cfg['abstract']) ? $cfg['abstract'] : false);

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
                'config'    => $cfg
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
                'config'    => $cfg
            );
        }

        unset($request->passwd, $request->confirmpasswd, $cfg); // remove from memory !

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
        try
        {
            $request->id = $user->save($request->new);

            if(empty($nb) || (!$request->new && (int)$request->id === (int)User::iGet()->getUid()))
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
        }
        catch(Exception $e)
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'tpl'       => 'user',
                'datas'     => $datas,
                'error'     => $e->getContent(),
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
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
        if(empty($request->id))
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
            $args['id'] = $request->ids;
            $limit = count($request->ids);
        }
        elseif(!empty($request->id))
        {
            $args['id'] = $request->id;
            $limit = 1;
        }

        $datas = $this->_dao->get($args, 'id,login,rights,lang,email,timezone,config', $order, $groupby, $limit);
        if(empty($datas))
        {
            $request->setResponse(new Response(array(
                'status'    => 204
            )));
            return $this;
        }

        $multiple = !isset($datas['id']);

        if($multiple)
        {
            foreach($datas as $row)
            {
                $row['config'] = @unserialize($row['config']);
            }
        }
        else $datas['config'] = @unserialize($datas['config']);

        $request->setResponse(new Response(array(
            'datas'        => $datas,
            'multiple'     => $multiple
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
     * @param mixed $request the Request instance
     * @return $this
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

        $user = $this->_dao->get(User::iGet()->getUid(), 'id,lang');
        if(empty($user))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Invalid user id',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
        }

        $user->lang = $newLang;
        $user->save();

        $request->setResponse(new Response);

        return $this;
    }

    /**
     * Return some few statistics about current user
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param mixed $request the Request instance
     * @return $this
     */
    public function stat(Request $request)
    {
        $datas = array();
        $datas['nbCategories'] = DAO::getCachedDAO('streams_groups')->count()->nb;
        $datas['nbStreams'] = DAO::getCachedDAO('streams_relations')->count()->nb;
        $datas['nbDeadStreams'] = $datas['nbStreams'] - DAO::getCachedDAO('streams_relations')->count(array('streams.status' => 0))->nb;
        $datas['nbNews'] = DAO::getCachedDAO('news_relations')->count()->nb;
        $datas['nbUnreads'] = DAO::getCachedDAO('news_relations')->count(array('status' => 1))->nb;
        $datas['nbTags'] = DAO::getCachedDAO('news_tags')->count()->nb;

        if(User::iGet()->isAdmin())
        {
            $datas['nbUsers'] = $this->_dao->count()->nb;
            $datas['nbTotalStreams'] = DAO::getCachedDAO('streams')->count()->nb;
            $datas['nbTotalNews'] = DAO::getCachedDAO('news')->count()->nb;
            $datas['nbTotalDeadStreams'] = $datas['nbTotalStreams'] - DAO::getCachedDAO('streams')->count(array('status' => 0))->nb;
        }

        $request->setResponse(new Response(array(
            'datas'     => $datas,
            'tpl'       => 'stats'
        )));

        return $this;
    }
}
