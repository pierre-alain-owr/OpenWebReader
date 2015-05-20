<?php
/**
 * Model for 'streams_groups' object
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
namespace OWR\Model\Streams;
use OWR\Model,
    OWR\Request,
    OWR\Exception,
    OWR\DAO,
    OWR\Model\Response,
    OWR\Config,
    OWR\Plugins;
/**
 * This class is used to add/edit/delete groups
 * @package OWR
 * @subpackage Model
 * @uses OWR\Model extends the base class
 * @uses OWR\Request the request
 * @uses OWR\Exception the exception handler
 * @uses OWR\DAO the DAO
 * @uses OWR\Plugins Plugins manager
 * @subpackage Model
 */
class Groups extends Model
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
        Plugins::pretrigger($request);
        if(empty($request->name))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Missing name',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));

            return $this;
        }

        if(empty($request->id))
        {
            $group = $this->_dao->get(array('name' => $request->name), 'id');
            if(empty($group))
            {
                $request->new = true;
                $group = DAO::getDAO('streams_groups');
            }
        }
        else
        {
            $group = $this->_dao->get($request->id, 'id'); // check
            if(empty($group))
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
        Plugins::trigger($request);
        $request->setResponse(new Response(array(
            'status'    => $request->new ? 201 : 200,
            'datas'     => array('id' => $request->id)
        )));
        Plugins::posttrigger($request);
        return $this;
    }

    /**
     * Deletes a group and all contained streams
     *
     * @access public
     * @param int $id the id of the group to delete
     * @return $this
     */
    public function delete(Request $request)
    {
        Plugins::pretrigger($request);
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
        if('streams_groups' !== $type)
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
            if($streamids = DAO::getCachedDAO('streams_relations')->get(array('gid' => $request->id), 'rssid'))
            {
                $r = clone($request);
                if(is_array($streamids))
                {
                    $model = parent::getCachedModel('streams');
                    foreach($streamids as $rss)
                    {
                        $r->id = $rss->rssid;
                        $model->delete($r);
                        $response = $r->getResponse();
                        if('error' === $response->getNext())
                            Logs::iGet()->log($response->getError(), $response->getStatus());
                    }
                }
                else
                {
                    $r->id = $streamids->rssid;
                    parent::getCachedModel('streams')->delete($r);
                    $response = $r->getResponse();
                    if('error' === $response->getNext())
                        Logs::iGet()->log($response->getError(), $response->getStatus());
                    DAO::getCachedDAO('news_relations')->delete(array('rssid' => $streamids->rssid));
                }
            }
            unset($r);
            DAO::getCachedDAO('objects')->delete($request->id);
        }
        catch(Exception $e)
        {
            $this->_db->rollback();
            throw new Exception($e->getContent(), $e->getCode());
        }

        $this->_db->commit();
        Plugins::trigger($request);
        $request->setResponse(new Response);
        Plugins::posttrigger($request);
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
        Plugins::pretrigger($request);
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

        $datas = $this->_dao->get($args, 'id,name', $order, $groupby, $limit);
        if(empty($datas))
        {
            $request->setResponse(new Response(array(
                'status'    => 204
            )));
            return $this;
        }

        $this->_setUserTimestamp($datas);
        Plugins::trigger($request);
        $request->setResponse(new Response(array(
            'datas'        => $datas,
            'multiple'     => !isset($datas['id'])
        )));
        Plugins::posttrigger($request);
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
        Plugins::pretrigger($request);
        if(!empty($request->gid))
        {
            $group = $this->_dao->get($request->gid, 'id,name');
            if(!empty($group))
            {
                $request->gname = $group->name;
                $request->setResponse(new Response);
                return $this;
            }
        }

        $group = $this->_dao->get(array('name' => 'Root'), 'id');
        if(empty($group))
        {
            $group = DAO::getDAO('streams_groups');
            $group->name = 'Root';
            $group->save();
        }

        $request->gid = (int) $group->id;
        $request->gname = $group->name;

        unset($group);
        Plugins::trigger($request);
        $request->setResponse(new Response);
        Plugins::posttrigger($request);
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
        Plugins::pretrigger($request);
        if(empty($request->name))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Missing name',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        $group = $this->_dao->get(array('id'=>$request->id), 'id, uid');
        if(empty($group))
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
        Plugins::trigger($request);
        $request->setResponse(new Response);
        Plugins::posttrigger($request);
        return $this;
    }
}
