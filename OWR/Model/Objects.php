<?php
/**
 * Model for 'objects' object
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
    OWR\Plugins;
/**
 * This class is used to add/delete objects
 * @package OWR
 * @subpackage Model
 * @uses OWR\Model extends the base class
 * @uses OWR\Request the request
 * @uses OWR\Exception the exception handler
 * @uses OWR\DAO the DAO
 * @uses OWR\Request a request sent to the model
 * @uses OWR\Plugins Plugins manager
 * @subpackage Model
 */
class Objects extends Model
{
    /**
     * Adds an object entry into the DB
     *
     * @access public
     * @param mixed $request the Request instance
     * @return $this
     */
    public function edit(Request $request)
    {
        Plugins::pretrigger($request);
        if(empty($request->type))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Missing type',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        $request->id = $this->_dao->getUniqueId($request->type);

         Plugins::trigger($request);

        // we don't send 201 status because it is an internal call
        // if we add news we don't want to send created status
        // because it is NOT a user action
        $request->setResponse(new Response);

        Plugins::posttrigger($request);

        return $this;
    }

    /**
     * Deletes an object
     *
     * @access public
     * @param mixed $request the Request instance
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

        DAO::getType($request->id); // check user has the rights to do that
        $this->_db->beginTransaction();
        try
        {
            $this->_dao->delete($request->id);
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
     * Gets datas to render an object
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

        $types = $this->_dao->get($args, '*', $order, $groupby, $limit);
        if(empty($types))
        {
            $request->setResponse(new Response(array(
                'status'    => 204
            )));
            return $this;
        }

        $r = new Request(array('id' => null));
        foreach($types as $type)
        {
            $r->id = $type['id'];
            parent::getCachedModel($type['type'])->view($r, $args, $order, $groupby, 1);
            $response = $r->getResponse();
            if('error' !== $response->getNext())
            {
                $data = $response->getDatas();
                empty($data) || $datas[] = $data;
            }
            else
            {
                $request->setResponse($response);
                return $this;
            }
        }

        Plugins::trigger($request);

        $request->setResponse(new Response(array(
            'datas'        => $datas,
            'multiple'     => !isset($types['id'])
        )));

        Plugins::posttrigger($request);

        return $this;
    }
}
