<?php
/**
 * Logic for 'streams_groups' object
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
namespace OWR\Logic\Streams;
use OWR\Logic as Logic,
    OWR\Request as Request,
    OWR\Exception as Exception,
    OWR\DAO as DAO,
    OWR\Logic\Response as Response,
    OWR\Config as Config;
/**
 * This class is used to add/edit/delete groups
 * @package OWR
 * @subpackage Logic
 * @uses OWR\Logic extends the base class
 * @uses OWR\Request the request
 * @uses OWR\Exception the exception handler
 * @uses OWR\DAO the DAO
 * @subpackage Logic
 */
class Groups extends Logic
{
    /**
     * Adds/Edits a stream
     *
     * @access public
     * @param mixed $request the Request instance
     * @return $this
     */
    public function edit(Request $request)
    {
        if(empty($request->name))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Missing name',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));

            return $this;
        }

        if(!$request->id)
        {
            $group = $this->_dao->get(array('name' => $request->name), 'id'); 
            if(!$group)
            {
                $request->new = true;
                $group = DAO::getDAO('streams_groups');
            }
        }
        else
        {
            $group = $this->_dao->get($request->id, 'id'); // check
            if(!$group)
            {
                $request->setResponse(new Response(array(
                    'do'        => 'error',
                    'error'     => 'Invalid id',
                    'status'    => Exception::E_OWR_BAD_REQUEST
                )));
                return $this;
            }
        }
        
        $group->name = $request->name;
        $request->id = $group->save();
        unset($group);

        $request->setResponse(new Response(array(
            'status'    => $request->new ? 201 : 200,
            'datas'     => array('id' => $request->id)
        )));

        return $this;
    }

    /**
     * Deletes a groups and all contained streams
     *
     * @access public
     * @param int $id the id of the group to delete
     * @return $this
     */
    public function delete(Request $request)
    {
        if(empty($request->id))
        {
            $request->setResponse(array(
                'do'        => 'error',
                'error'     => 'Missing id',
                'status'    => Exception::E_OWR_BAD_REQUEST
            ));
            return $this;
        }

        $type = DAO::getType($request->id);
        if('streams_groups' !== $type)
        {
            $request->setResponse(array(
                'do'        => 'error',
                'error'     => 'Invalid id',
                'status'    => Exception::E_OWR_BAD_REQUEST
            ));
            return $this;
        }

        $this->_db->beginTransaction();
        try
        {
            if($streamids = DAO::getCachedDAO('streams_relations')->get(array('gid' => $request->id), 'rssid'))
            {
                $r = clone($request);
                if(is_array($streamids))
                {
                    $logic = parent::getCachedLogic('streams');
                    foreach($streamids as $rss)
                    {
                        $r->id = $rss->rssid;
                        $logic->delete($r);
                        $response = $r->getResponse();
                        if('error' === $response->getNext())
                            Logs::iGet()->log($response->getError(), $response->getStatus());
                    }
                }
                else
                {
                    $r->id = $streamids->rssid;
                    parent::getCachedLogic('streams')->delete($r);
                    $response = $r->getResponse();
                    if('error' === $response->getNext())
                        Logs::iGet()->log($response->getError(), $response->getStatus());
                    DAO::getCachedDAO('news_relations')->delete(array('rssid' => $streamids->rssid));
                }
            }
            unset($r);
            DAO::getCachedDAO('objects')->delete($request);
        }
        catch(Exception $e)
        {
            $this->_db->rollback(); 
            throw new Exception($e->getContent(), $e->getCode());
        }

        $this->_db->commit();

        $request->setResponse(new Response);

        return $this;
    }

    /**
     * Gets datas to render a group
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

            foreach($request->ids as $id)
            {
                $args['id'] = $id;
                $data = $this->_dao->get($args, 'id,name', $order, $groupby, $limit);
                if(!$data)
                {
                    $request->setResponse(array(
                        'do'        => 'error',
                        'error'     => 'Invalid id',
                        'status'    => Exception::E_OWR_BAD_REQUEST
                    ));
                    return $this;
                }

                $datas[] = $data;
            }
        }
        elseif(!empty($request->id))
        {
            $args['id'] = $request->id;
            $datas = $this->_dao->get($args, 'id,name', $order, $groupby, $limit);
            if(!$datas)
            {
                $request->setResponse(array(
                    'do'        => 'error',
                    'error'     => 'Invalid id',
                    'status'    => Exception::E_OWR_BAD_REQUEST
                ));
                return $this;
            }
        }
        else
        {
            $datas = $this->_dao->get($args, 'id,name', $order, $groupby, $limit);
            if(!$datas)
            {
                $request->setResponse(new Response);
                return $this;
            }
        }

        $request->setResponse(array(
            'datas'        => $datas
        ));
        return $this;
    }

    /**
     * Checks if a group exists relative to the id
     * If no id is passed it will try to get the root group, and create it if it does not exist
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param int &$gid the id to look for, optionnal
     * @access public
     * @return mixed the DAO object for table streams_groups for specified id
     */
    public function checkGroupById(Request $request)
    {
        if($request->gid > 0)
        {
            $group = $this->_dao->get($request->gid, 'id,name');
            if($group)
            {
                $request->gname = $group->name;
                $request->setResponse(new Response);
                return $this;
            }
        }

        $group = $this->_dao->get(array('name' => 'Root'), 'id');
        if(!$group)
        {
            $group = DAO::getDAO('streams_groups');
            $group->name = 'Root';
            $group->save();
        }

        $request->gid = (int)$group->id;
        $request->gname = $group->name;

        unset($group);

        $request->setResponse(new Response);
        
        return $this;
    }

    /**
     * Renames a group
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param mixed $request the Request instance
     */
    public function rename(Request $request)
    {
        $group = $this->_dao->get(array('id'=>$request->id), 'id, uid');
        if(!$group)
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Invalid id',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        $group->name = $request->name;
        $group->save();

        $request->setResponse(new Response);

        return $this;
    }
}