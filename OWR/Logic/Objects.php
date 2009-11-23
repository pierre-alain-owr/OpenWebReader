<?php
/**
 * Logic for 'objects' object
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
    OWR\DAO as DAO;
/**
 * This class is used to add/delete objects
 * @package OWR
 * @subpackage Logic
 * @uses OWR\Logic extends the base class
 * @uses OWR\Request the request
 * @uses OWR\Exception the exception handler
 * @uses OWR\DAO the DAO
 * @subpackage Logic
 */
class Objects extends Logic
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
        // we don't send 201 status because it is an internal call
        // if we add news we don't want to send created status
        // because it is NOT a user action
        $request->setResponse(new Response);
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
        if(!$request->id)
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

        $request->setResponse(new Response);
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
        $args['FETCH_TYPE'] = 'assoc';

        if(!empty($request->ids))
        {
            $datas = array();

            foreach($request->ids as $id)
            {
                $args['id'] = $id;
                $data = $this->_dao->get($args, '*', $order, $groupby, $limit);
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
            $datas = $this->_dao->get($args, '*', $order, $groupby, $limit);
            if(!$datas)
            {
                $request->setResponse(new Response(array(
                    'do'        => 'error',
                    'error'     => 'Invalid id',
                    'status'    => Exception::E_OWR_BAD_REQUEST
                )));
                return $this;
            }

            parent::getCachedLogic($datas['type'])->view($request, $args, $order, $groupby, $limit);
            return $this;
        }
        else
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Missing id',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        $request->setResponse(new Response(array(
            'datas'        => $datas
        )));
        return $this;
    }
}