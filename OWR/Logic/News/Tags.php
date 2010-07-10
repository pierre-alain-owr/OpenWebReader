<?php
/**
 * Logic for 'news_tags' object
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
namespace OWR\Logic\News;
use OWR\Logic,
    OWR\Request,
    OWR\Exception,
    OWR\DAO,
    OWR\Logic\Response,
    OWR\Config;
/**
 * This class is used to add/edit/delete tags
 * @package OWR
 * @subpackage Logic
 * @uses OWR\Logic extends the base class
 * @uses OWR\Request the request
 * @uses OWR\Exception the exception handler
 * @uses OWR\DAO the DAO
 * @subpackage Logic
 */
class Tags extends Logic
{
    /**
     * Adds/Edits a tag
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

        if(empty($request->id))
        {
            $tag = $this->_dao->get(array('name' => $request->name), 'id');
            if(empty($tag))
            {
                $request->new = true;
                $tag = DAO::getDAO('news_tags');
            }
        }
        else
        {
            $tag = $this->_dao->get($request->id, 'id'); // check
            if(empty($tag))
            {
                $request->setResponse(new Response(array(
                    'do'        => 'error',
                    'error'     => 'Invalid id',
                    'status'    => Exception::E_OWR_BAD_REQUEST
                )));
                return $this;
            }
        }

        $tag->name = $request->name;
        $request->id = $tag->save();
        unset($tag);

        $request->setResponse(new Response(array(
            'status'    => $request->new ? 201 : 200,
            'datas'     => array('id' => $request->id)
        )));

        return $this;
    }

    /**
     * Deletes a tag and all contained news
     *
     * @access public
     * @param int $id the id of the group to delete
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
        if('news_tags' !== $type)
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
     * @param string $tagby the groupby clause
     * @param string $limit the limit clause
     * @return $this
     */
    public function view(Request $request, array $args = array(), $order = '', $tagby = '', $limit = '')
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

        $datas = $this->_dao->get($args, 'id,name', $order, $tagby, $limit);
        if(empty($datas))
        {
            $request->setResponse(new Response(array(
                'status'    => 204
            )));
            return $this;
        }

        $request->setResponse(new Response(array(
            'datas'        => $datas,
            'multiple'     => !isset($datas['id'])
        )));
        return $this;
    }

    /**
     * Renames a tag
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param mixed $request the Request instance
     */
    public function rename(Request $request)
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

        $tag = $this->_dao->get(array('id'=>$request->id), 'id, uid');
        if(empty($tag))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Invalid id',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        $tag->name = $request->name;
        $tag->save();

        $request->setResponse(new Response);

        return $this;
    }

    /**
     * Edits tags and news relations
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param mixed $request the Request instance
     */
    public function editRelations(Request $request)
    {
        if(empty($request->ids))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Missing ids of news',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        $daoRelations = DAO::getCachedDAO('news_relations_tags');
        $tags = array_filter(array_map('trim', explode(',', $request->name)));

        // reset all tags for the id(s)
        if(is_array($request->ids))
        {
            foreach($request->ids as $id)
            {
                $daoRelations->delete(array('newsid' => $id));
            }
        }
        else
        {
            $daoRelations->delete(array('newsid' => $request->ids));
        }

        if(empty($tags))
        { // ok we are done, no tags to add
            $request->setResponse(new Response);

            return $this;
        }

        $ids = array();
        $dao = DAO::getCachedDAO('news_tags');

        foreach($tags as $tag)
        {
            $exists = $this->_dao->get(array('name'=>$tag), 'id');
            if(!$exists)
            {
                $dao->name = $tag;
                $daoRelations->tid = $dao->save();
                $ids[] = $daoRelations->tid;
                $dao->id = null;
            }
            else $daoRelations->tid = $exists->id;

            if(is_array($request->ids))
            {
                foreach($request->ids as $id)
                {
                    $daoRelations->newsid = (int) $id;
                    $daoRelations->save();
                }
            }
            else
            {
                $daoRelations->newsid = (int) $request->ids;
                $daoRelations->save();
            }
        }

        $request->setResponse(new Response(empty($ids) ? array() : array(
            'datas'     => array('ids' => $ids),
            'status'    => 201,
            'tpl'       => 'menu_tags_contents'
        )));

        return $this;
    }
}