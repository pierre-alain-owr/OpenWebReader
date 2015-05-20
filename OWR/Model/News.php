<?php
/**
 * Model for 'news' object
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
    OWR\Config,
    OWR\Logs,
    OWR\Plugins;
/**
 * This class is used to add/edit/delete news
 * @uses OWR\Model extends the base class
 * @uses OWR\Request the request
 * @uses OWR\Exception the exception handler
 * @uses OWR\DAO the DAO
 * @uses OWR\Logs the log object
 * @uses OWR\Plugins Plugins manager
 * @package OWR
 * @subpackage Model
 */
class News extends Model
{
    /**
     * Adds/Edits a stream
     *
     * @access public
     * @param OWR\Request $request the Request instance
     * @return $this
     */
    public function edit(Request $request)
    {
        Plugins::pretrigger($request);

        $link = $request->item->get('link');
        $hash = md5($link.$request->streamid);
        $pubDate = $request->item->get('pubDate');

        $new = $this->_dao->get(array('hash'=>$hash), 'id,pubDate');
        if(!empty($new))
        {
            $r = new Request(array('id'=>$new->id, 'streamid'=>$request->streamid));
            $this->insertNewsRelations($r);
            $response = $r->getResponse();
            if('error' === $response->getNext())
                Logs::iGet()->log($response->getError(), $response->getStatus());
            $request->setResponse(new Response);
            return $this;
        }
        else $new = DAO::getDAO('news');

        $new->rssid = $request->streamid;
        $new->link = $link;
        $new->hash = $hash;
        unset($link, $hash);
        $new->title = $request->item->get('title');
        $new->contents = serialize($request->item->get());
        $new->pubDate = $pubDate;
        $new->author = $request->item->get('author');
        if(is_array($new->author)) $new->author = join(', ', $new->author);

        unset($request->item, $pubDate); // free memory

        $this->_db->beginTransaction();
        try
        {
            $request->id = $new->save();
        }
        catch(Exception $e)
        {
            $this->_db->rollback();
            throw new Exception($e->getContent(), $e->getCode());
        }

        $contents = DAO::getDAO('news_contents');
        $contents->id = $request->id;
        $contents->contents = $new->contents;
        unset($new);

        try
        {
            $contents->save();
        }
        catch(Exception $e)
        {
            $this->_db->rollback();
            throw new Exception($e->getContent(), $e->getCode());
        }
        $this->_db->commit();
        unset($contents);

        $this->insertNewsRelations($request);
        $response = $request->getResponse();
        if('error' === $response->getNext())
            Logs::iGet()->log($response->getError(), $response->getStatus());

        Plugins::trigger($request);

        $request->setResponse(new Response);

        Plugins::posttrigger($request);

        return $this;
    }

    /**
     * Deletes a new
     *
     * @access public
     * @param OWR\Request $request the Request instance
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
        if('news' !== $type)
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
            DAO::getCachedDAO('news_relations')->delete(array('newsid' => $request->id));
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
     * Gets datas to render a new
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param OWR\Request $request the Request instance
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
        $multiple = false;

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

	$datas = $this->_dao->get($args, 'id,rssid AS streamid,news.lastupd,pubDate,author,title,link,gid,status,streams_relations_name.name,streams_groups.name AS gname,favicon', $order, $groupby, $limit);
        if(empty($datas))
        {
            $request->setResponse(new Response(array(
                'status'    => 204
            )));
            return $this;
        }

        if(!isset($datas['id']))
        {
            $multiple = true;
            $ids = array();
            foreach($datas as $k => $data)
            {
                $ids[] = $data['id'];
            }
            $datas['ids'] = $ids;

            if(!isset($request->getContents) || $request->getContents)
            {    
                $contents = DAO::getCachedDAO('news_contents')->get(array('id' => $ids), '*', 'FIELD(id,'. join(',', $ids) .')');
                foreach($contents as $k => $content)
                {
                    $datas[$k]['contents'] = unserialize($content->contents);
                }
                unset($contents, $content);
            }
        }
        else
        {
            if(!isset($request->getContents) || $request->getContents)
                $datas['contents'] = unserialize(DAO::getCachedDAO('news_contents')->get(array('id' => $datas['id']), 'contents')->contents);
        }
        Plugins::trigger($request);
        $request->setResponse(new Response(array(
            'datas'        => $datas,
            'multiple'     => $multiple
        )));
        Plugins::posttrigger($request);
        return $this;
    }

    /**
     * Insert the relations between user(s) and a new
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param OWR\Request $request the Request instance
     * @return $this
     * @access public
     */
    public function insertNewsRelations(Request $request)
    {
        Plugins::pretrigger($request);
        $id = (int) $request->id;
        $streamid = (int) $request->streamid;

        if(empty($request->current))
        { // add a relation for all users
            $query = '
    SELECT uid
        FROM streams_relations
        WHERE uid NOT IN (
            SELECT DISTINCT(uid)
                FROM news_relations
                WHERE rssid='.$streamid.' AND newsid='.$id.'
        ) AND rssid='.$streamid;
            $users = $this->_db->execute($query);

            if($users->count())
            {
                $relations = DAO::getDAO('news_relations');
                $relations->newsid = $id;
                $relations->rssid = $streamid;
                while($users->next())
                {
                    $relations->uid = $users->uid;
                    try
                    {
                        $relations->save(true);
                    }
                    catch(Exception $e)
                    {
                        Logs::iGet()->log($e->getContent(), $e->getCode());
                    }
                }
            }
        }
        else
        {
            $relations = DAO::getDAO('news_relations');
            $relations->newsid = $id;
            $relations->rssid = $streamid;
            try
            {
                $relations->save(true);
            }
            catch(Exception $e)
            {
                Logs::iGet()->log($e->getContent(), $e->getCode());
            }
        }

        Plugins::trigger($request);

        $request->setResponse(new Response);
        Plugins::posttrigger($request);
        return $this;
    }

    /**
     * Update new(s) status (read/unread)
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param OWR\Request $request the Request instance
     * @access public
     * @return $this
     */
    public function update(Request $request)
    {
        Plugins::pretrigger($request);
        $status = (int) $request->status;

        if(!empty($request->ids) && is_array($request->ids))
        {
            $query = '
    UPDATE news_relations
        SET status='.$status.'
        WHERE uid='.User::iGet()->getUid().' AND status='.(int) !$status.'
        AND newsid IN ('.join(',', $request->ids).')';
        }
        elseif(0 < $request->id)
        {
            $table = DAO::getType($request->id);

            if('streams' === $table)
            {
//                if($request->timestamp > 0)
  //              {
                    $query = '
    UPDATE news_relations nr
        JOIN news n ON (nr.newsid=n.id)
        SET status='.$status.'
        WHERE uid='.User::iGet()->getUid().' AND status='.(int) !$status.'
        AND nr.rssid='.$request->id.' AND lastupd < FROM_UNIXTIME('.User::iGet()->getTimestamp($request->id).')';
    //            }
     //           else
      //         {
        //            $query = '
//    UPDATE news_relations
  //      SET status='.$status.'
    //    WHERE uid='.User::iGet()->getUid().' AND status='.(int) !$status.' AND rssid='.$request->id;
      //          }
            }
            elseif('streams_groups' === $table)
            {
//                if($request->timestamp > 0)
 //               {
                    $query = '
    UPDATE news_relations nr
        JOIN streams_relations rr ON (nr.rssid=rr.rssid)
        JOIN news n ON (nr.newsid=n.id)
        SET status='.$status.'
        WHERE nr.uid='.User::iGet()->getUid().' AND status='.(int) !$status.'
        AND rr.gid='.$request->id.' AND lastupd < FROM_UNIXTIME('.User::iGet()->getTimestamp($request->id).')';
/*                }
                else
                {
                    $query = '
    UPDATE news_relations nr
        JOIN streams_relations rr ON (nr.rssid=rr.rssid)
        SET status='.$status.'
        WHERE nr.uid='.User::iGet()->getUid().' AND status='.(int) !$status.' AND rr.gid='.$request->id;
                }*/
            }
            elseif('news_tags' === $table)
            {
  //              if($request->timestamp > 0)
//                {
                    $query = '
    UPDATE news_relations nr
        JOIN news_relations_tags nrt ON (nrt.newsid=nr.newsid)
        JOIN news n ON (nr.newsid=n.id)
        SET status='.$status.'
        WHERE nr.uid='.User::iGet()->getUid().' AND status='.(int) !$status.'
        AND nrt.tid='.$request->id.' AND lastupd < FROM_UNIXTIME('.User::iGet()->getTimestamp($request->id).')';
/*                }
                else
                {
                    $query = '
    UPDATE news_relations nr
        JOIN news_relations_tags nrt ON (nrt.newsid=nr.newsid)
        SET status='.$status.'
        WHERE nr.uid='.User::iGet()->getUid().' AND status='.(int) !$status.' AND nrt.tid='.$request->id;
                }*/
            }
            elseif('news' === $table)
            {
                $query = '
    UPDATE news_relations
        SET status='.$status.'
        WHERE uid='.User::iGet()->getUid().' AND status='.(int) !$status.' AND newsid='.$request->id;
            }
            else
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
            $query = '
    UPDATE news_relations nr
        JOIN news n ON (nr.newsid=n.id)
        SET status='.$status.'
        WHERE uid='.User::iGet()->getUid().' AND status='.(int) !$status;
//            if($request->timestamp > 0)
  //          {
                $query .= ' AND lastupd < FROM_UNIXTIME('.User::iGet()->getTimestamp().')';

//            }
        }
        $this->_db->set($query);
        Plugins::trigger($request);
        $request->setResponse(new Response);
        Plugins::posttrigger($request);
        return $this;
    }
}
