<?php
/**
 * Model for 'streams' object
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
    OWR\Stream\Reader as StreamReader,
    OWR\Stream\Parser as StreamParser,
    OWR\Logs,
    OWR\cURLWrapper,
    OWR\Cron,
    OWR\User,
    OWR\OPML\Parser as OPMLParser,
    OWR\Upload,
    OWR\Config,
    OWR\Threads,
    OWR\Plugins;
/**
 * This class is used to add/edit/delete stream and his related tables
 * @package OWR
 * @subpackage Model
 * @uses OWR\Model extends the base class
 * @uses OWR\Request the request
 * @uses OWR\Exception the exception handler
 * @uses OWR\DAO the DAO
 * @uses OWR\Streams\Reader the stream reader
 * @uses OWR\Stream\Parser the stream parser
 * @uses OWR\Logs the logs object
 * @uses OWR\Cron add/modify crontab
 * @uses OWR\Plugins Plugins manager
 * @subpackage Model
 */
class Streams extends Model
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
        if(empty($request->url))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Missing url',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        parent::getCachedModel('streams_groups')->checkGroupById($request); // fill gid and gname

        $request->url = html_entity_decode($request->url, ENT_COMPAT, 'UTF-8');
        $hash = md5($request->url);

        $ids = array();
        $id = (int) $request->id;
        if(!empty($id))
        {
            if('streams' !== DAO::getType($id))
            {
                $id = 0;
            }
        }

        $cron = Cron::iGet();
        $streams = $this->_dao->get(array('hash' => $hash));
        if(!empty($streams))
        { // stream exists
            unset($hash);
            $request->id = (int) $streams->id;
            $request->ttl = $streams->ttl;

            $streams_relation = DAO::getCachedDAO('streams_relations')->get(array('rssid' => $streams->id));
            if($streams_relation)
            { // user already have this stream !
                $request->setResponse(new Response);
                return $this;
            }

            $streams_relation = DAO::getDAO('streams_relations');
            $streams_relation->rssid = $streams->id;
            $streams_relation->gid = $request->gid;

            $this->_db->beginTransaction();
            try
            {
                $streams_relation->save(); // save
            }
            catch(Exception $e)
            {
                $this->_db->rollback();
                throw new Exception($e->getContent(), $e->getCode());
            }
            unset($streams_relation);

            if(!empty($id) && $id !== $streams->id)
            { // user changed the url of one of his own stream
                $r = clone($request);
                $r->id = $id;
                $this->delete($r);
                $response = $r->getResponse();
                if('error' === $response->getNext())
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                unset($r);
            }

            $news = DAO::getCachedDAO('news')->get(array('rssid' => $streams->id), 'id', 'pubDate DESC, lastupd DESC');
            if(!empty($news))
            {
                $r = clone($request);
                $r->current = true; // we add a news_relations only for the current user
                $r->streamid = $streams->id;
                if(is_array($news))
                {
                    $model = parent::getCachedModel('news');

                    foreach($news as $new)
                    {
                        $r->id = $new->id;
                        $model->insertNewsRelations($r);
                        $response = $r->getResponse();
                        if('error' === $response->getNext())
                            Logs::iGet()->log($response->getError(), $response->getStatus());
                        else $ids[] = $r->id;
                    }
                }
                else
                {
                    $r->id = $news->id;
                    parent::getCachedModel('news')->insertNewsRelations($r);
                    $response = $r->getResponse();
                    if('error' === $response->getNext())
                        Logs::iGet()->log($response->getError(), $response->getStatus());
                    else $ids[] = $r->id;
                }
            }
            unset($news, $r);

            if(empty($request->name))
            {
                $reader = new StreamReader(array(
                    'channel' => unserialize(DAO::getCachedDAO('streams_contents')->get(array('rssid'=>$streams->id), 'contents')->contents)
                ));
                $request->name = $reader->get('title');
                unset($reader);
            }

            $streams_name = DAO::getDAO('streams_relations_name');
            $streams_name->name = $request->name;
            $streams_name->rssid = $streams->id;
            unset($streams);

            try
            {
                $streams_name->save(); // save title of the stream for the user
            }
            catch(Exception $e)
            {
                $this->_db->rollback();
                throw new Exception($e->getContent(), $e->getCode());
            }
            $this->_db->commit();

            unset($streams_name);

            if(empty($request->escape) && empty($request->escapeNews))
            {
                $request->setResponse(new Response(array(
                    'do'        => 'ok',
                    'tpl'       => 'stream',
                    'datas'     => array('id'=>$request->id, 'gid'=>$request->gid, 'name'=>$request->gname, 'ids' => $ids),
                    'status'    => 201
                )));
            }
            else
            {
                $request->setResponse(new Response(array(
                    'datas' => array('id' => $request->id, 'ids' => $ids)
                )));
            }

            $request->new = true;

            return $this;
        }

        if('' === ($stream = $this->_parse($request->url)))
        {
            // should NOT arrive
            // if we come here, that means that cURLWrapper got a 304 http response
            // but the stream does not exists in DB ? surely a bug or DB not up to date
            // TODO : fix it by forcing cURLWrapper to fetch datas
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Can\'t parse the stream'
            )));
            return $this;
        }
        elseif(false === $stream)
        {
            // no stream(s) detected
            // try auto-discovery
            $index = cURLWrapper::get($request->url, array(), false, true);
            $nb = $nbErr = 0;
            if(!empty($index))
            {
                if($hrefs = $this->_extractHREF(array(
                        'rel'=>array('subscriptions', 'alternate', 'related'),
                        'type'=>array(
                            'application/rss+xml',
                            'application/atom+xml',
                            'application/rdf+xml'
                        )
                    ), $index))
                { // streams
                    $nb += count($hrefs);
                    $r = clone($request);

                    foreach($hrefs as $href)
                    {
                        if('/' === mb_substr($href, 0, 1, 'UTF-8'))
                            $href = $request->url . $href;

                        try
                        {
                            $r->url = $href;
                            $this->edit($r);
                            $ids[] = $r->id;
                        }
                        catch(Exception $e)
                        {
                            ++$nbErr;
                            Logs::iGet()->log($e->getContent(), $e->getCode());
                        }
                    }
                }
                unset($r, $hrefs, $href);

                if($hrefs = $this->_extractHREF(array(
                        'rel'=>array('subscriptions', 'alternate', 'related'),
                        'type'=>array('text/x-opml')), $index))
                { // opml
                    $nb += count($hrefs);
                    $r = clone($request);

                    foreach($hrefs as $href)
                    {
                        if('/' === mb_substr($href, 0, 1, 'UTF-8'))
                            $href = $request->url . $href;

                        try
                        {
                            $r->url = $href;
                            $this->editOPML($r);
                            $ids[] = $r->ids;
                            $ids[] = $r->id;
                        }
                        catch(Exception $e)
                        {
                            ++$nbErr;
                            Logs::iGet()->log($e->getContent(), $e->getCode());
                        }
                    }
                }
            }
            unset($index, $hrefs, $href, $r);
            if($nb === $nbErr)
            {
                $request->setResponse(new Response(array(
                    'do'        => 'error',
                    'error'     => 'Can\'t parse the stream'
                )));
                return $this;
            }
            else
            {
                $request->new = true;
                if(empty($request->escape) && empty($request->escapeNews))
                {
                    $request->setResponse(new Response(array(
                        'do'        => 'redirect',
                        'status'    => 201,
                        'datas'     => array('ids' => $ids)
                    )));
                }
                return $this;
            }
        }

        $ttl = $stream->get('ttl');
        $streams = DAO::getDAO('streams');
        $streams->url = $request->url;
        $streams->hash = $hash;
        $streams->lastupd = (int) $request->begintime;
        $streams->ttl = $ttl;
        $streams->status = 0;
        $streams->id = $id ?: null;
        unset($hash);

        $this->_db->beginTransaction();
        try
        {
            $request->id = $streams->save(); // save stream
        }
        catch(Exception $e)
        {
            $this->_db->rollback();
            throw new Exception($e->getContent(), $e->getCode());
        }

        unset($streams);

        $streams_contents = DAO::getDAO('streams_contents');
        $streams_contents->src = $stream->get('src');
        $streams_contents->rssid = $request->id;
        $streams_contents->contents = serialize($stream->get('channel'));

        try
        {
            $streams_contents->save(); // save stream parsed contents and src
        }
        catch(Exception $e)
        {
            $this->_db->rollback();
            throw new Exception($e->getContent(), $e->getCode());
        }

        $this->_db->commit();
        unset($streams_contents);
        // ok stream is fully saved, we try to find the favicon, in background
        Threads::iGet()->add(array('do' => 'managefavicons', 'id' => $request->id));

        // save user relations
        $streams_relation = DAO::getDAO('streams_relations');
        $streams_relation->rssid = $request->id;
        $streams_relation->gid = $request->gid;

        $this->_db->beginTransaction();
        try
        {
            $streams_relation->save(); // save
        }
        catch(Exception $e)
        {
            $this->_db->rollback();
            throw new Exception($e->getContent(), $e->getCode());
        }

        unset($streams_relation);

        if(empty($request->name))
        { // user does not fill the title field, we try to get it from stream
            $request->name = $stream->get('title');
        }
        $streams_name = DAO::getDAO('streams_relations_name');
        $streams_name->name = $request->name;
        $streams_name->rssid = $request->id;

        try
        {
            $streams_name->save(); // save title of the stream for the user
        }
        catch(Exception $e)
        {
            $this->_db->rollback();
            throw new Exception($e->getContent(), $e->getCode());
        }
        $this->_db->commit();
        unset($streams_name);

        if(empty($request->escapeNews))
        { // save the news
            $model = parent::getCachedModel('news');
            $r = clone($request);
            $r->streamid = $request->id;
            $r->item = array();
            while($r->item = $stream->get('item'))
            {
                try
                {
                    $model->edit($r);
                    $response = $r->getResponse();
                    if('error' === $response->getNext())
                        Logs::iGet()->log($response->getError(), $response->getStatus());
                    else $ids[] = $r->id;
                }
                catch(Exception $e)
                {
                    switch($e->getCode())
                    {
                        case Exception::E_OWR_NOTICE:
                        case Exception::E_OWR_WARNING:
                            Logs::iGet()->log($e->getContent(), $e->getCode());
                            break;
                        default: throw new Exception($e->getContent(), $e->getCode());
                            break;
                    }
                }
            }
        }

        unset($stream);

        try
        {
            if(!$request->escape)
            {
                $cron->manage(array('type'=>'managefavicons'));
                $cron->manage(array('type'=>'checkstreamsavailability'));
            }
            $cron->manage(array('type'=>'refreshstream', 'ttl'=>$ttl));
        }
        catch(Exception $e)
        {
            switch($e->getCode())
            {
                case Exception::E_OWR_NOTICE:
                case Exception::E_OWR_WARNING:
                    Logs::iGet()->log($e->getContent(), $e->getCode());
                    break;
                default: throw new Exception($e->getContent(), $e->getCode());
                    break;
            }
        }

        Plugins::trigger($request);

        if(empty($request->escape) && empty($request->escapeNews))
        {
            $request->setResponse(new Response(array(
                'do'        => 'ok',
                'tpl'       => 'stream',
                'datas'     => array('id'=>$request->id, 'gid'=>$request->gid, 'name'=>$request->gname, 'ids' => $ids),
                'status'    => 201
            )));
        }
        else
        {
            $request->setResponse(new Response(array(
                'datas' => array('id' => $request->id, 'ids' => $ids)
            )));
        }

        $request->new = true;
        Plugins::posttrigger($request);

        return $this;
    }

    /**
     * Gets datas to render a stream
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

        $datas = $this->_dao->get($args, 'id,streams_relations_name.name,url,ttl,lastupd,favicon,status,gid,streams_groups.name AS gname'.(!isset($request->getContents) || $request->getContents ? ',contents' : ''), $order, $groupby, $limit);
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
            if(!isset($request->getContents) || $request->getContents)
            {
                foreach($datas as $k => $data)
                    $datas[$k]['contents'] = unserialize($data['contents']);
            }
        }
        elseif(!isset($request->getContents) || $request->getContents)
        {
            $datas['contents'] = unserialize($datas['contents']);
        }

        $this->_setUserTimestamp($datas);

        Plugins::trigger($request);

        $request->setResponse(new Response(array(
            'datas'        => $datas,
            'multiple'     => $multiple
        )));
        Plugins::posttrigger($request);
        return $this;
    }

    /**
     * Deletes a stream
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

        $type = DAO::getType($request->id);
        if('streams' !== $type)
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
            DAO::getCachedDAO('streams_relations')->delete(array('rssid' => $request->id));
            DAO::getCachedDAO('news_relations')->delete(array('rssid' => $request->id));
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
     * Moves a stream into another category
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @param mixed $request the Request instance
     */
    public function move(Request $request)
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

        $stream = DAO::getCachedDAO('streams_relations')->get(array('rssid' => $request->id), 'rssid');
        if(empty($stream))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Invalid id',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        parent::getCachedModel('streams_groups')->checkGroupById($request);

        $stream->gid = $request->gid;
        $stream->save();

        unset($stream);
        Plugins::trigger($request);
        $request->setResponse(new Response);
        Plugins::posttrigger($request);
        return $this;
    }

    /**
     * Updates a stream
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $request the Request instance
     * @return boolean true on success
     * @access public
     */
    public function update(Request $request)
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

        $streams = $this->_dao->get($request->id, 'id,url,hash');
        if(empty($streams))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Invalid id',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        $streams_contents = DAO::getCachedDAO('streams_contents')->get(array('rssid'=>$request->id));
        $streams->lastupd = (int)$request->begintime;

        $cron = Cron::iGet();
        if('' === ($stream = $this->_parse($streams->url, $streams_contents->src)))
        { // 304 not changed
            $reader = new StreamReader(array('channel' => unserialize($streams_contents->contents)));
            $streams->status = 0;
            $streams->ttl = $reader->get('ttl');
            unset($reader);
            $streams->save();
            try
            {
                $cron->manage(array('type'=>'refreshstream','ttl'=>$streams->ttl));
            }
            catch(Exception $e)
            {
                switch($e->getCode())
                {
                    case Exception::E_OWR_NOTICE:
                    case Exception::E_OWR_WARNING:
                        Logs::iGet()->log($e->getContent(), $e->getCode());
                        break;
                    default: throw new Exception($e->getContent(), $e->getCode());
                        break;
                }
            }
            $request->setResponse(new Response);
            return $this;
        }
        elseif(false === $stream)
        {
            $request->setResponse(new Response(array(
                'do'    => 'error',
                'error' => 'Can\'t parse the stream'
            )));
            return $this;
        }

        $streams->status = 0;
        $streams->ttl = $stream->get('ttl');

        $this->_db->beginTransaction();
        try
        {
            $streams->save();
        }
        catch(Exception $e)
        {
            $this->_db->rollback();
            throw new Exception($e->getContent(), $e->getCode());
        }

        $ttl = $streams->ttl;
        unset($streams);
        $streams_contents->src = $stream->get('src');
        $streams_contents->contents = serialize($stream->get('channel'));
        try
        {
            $streams_contents->save();
        }
        catch(Exception $e)
        {
            $this->_db->rollback();
            throw new Exception($e->getContent(), $e->getCode());
        }
        $this->_db->commit();

        unset($streams_contents);

        $r = clone($request);
        $model = parent::getCachedModel('news');
        $ids = array();
        while($r->item = $stream->get('item'))
        {
            try
            {
                $r->streamid = $request->id;
                $model->edit($r);
                $ids[] = $r->id;
                $response = $r->getResponse();
                if('error' === $response->getNext())
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                else $ids[] = $r->id;
            }
            catch(Exception $e)
            {
                switch($e->getCode())
                {
                    case Exception::E_OWR_NOTICE:
                    case Exception::E_OWR_WARNING:
                        Logs::iGet()->log($e->getContent(), $e->getCode());
                        break;
                    default: throw new Exception($e->getContent(), $e->getCode());
                        break;
                }
            }
        }
        unset($stream, $r);

        try
        {
            $cron->manage(array('type'=>'refreshstream','ttl'=>$ttl));
        }
        catch(Exception $e)
        {
            switch($e->getCode())
            {
                case Exception::E_OWR_NOTICE:
                case Exception::E_OWR_WARNING:
                    Logs::iGet()->log($e->getContent(), $e->getCode());
                    break;
                default: throw new Exception($e->getContent(), $e->getCode());
                    break;
            }
        }
        Plugins::trigger($request);
        $request->setResponse(new Response(array(
            'datas' => array('ids' => $ids)
        )));
        Plugins::posttrigger($request);
        return $this;
    }

    /**
     * Clear a stream of all the news
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $request the Request instance
     * @return boolean true on success
     * @access public
     */
    public function clear(Request $request)
    {
        Plugins::pretrigger($request);
        if(empty($request->id))
        {
            DAO::getCachedDAO('news_relations')->delete();
        }
        else
        {
            $table = DAO::getType($request->id);

            switch($table)
            {
                case 'streams':
                    DAO::getCachedDAO('news_relations')->delete(array('rssid' => $request->id));
                    break;

                case 'streams_groups':
                    $query = '
    DELETE nr FROM news_relations nr
        JOIN streams_relations sr ON (nr.rssid=sr.rssid)
        WHERE gid='.$request->id.' AND nr.uid='.User::iGet()->getUid().' AND sr.uid='.User::iGet()->getUid();

                    $this->_db->set($query);
                    break;

                case 'news_tags':
                    DAO::getCachedDAO('news_relations_tags')->delete(array('tid' => $request->id));
                    break;

                default:
                    $request->setResponse(new Response(array(
                        'do'        => 'error',
                        'error'     => 'Invalid id',
                        'status'    => Exception::E_OWR_BAD_REQUEST
                    )));
                    return $this;
                    break;
            }
        }

        Plugins::trigger($request);

        if($request->currentid === $request->id || 0 === $request->currentid)
        {
            $request->setResponse(new Response(array(
                'tpl'   => 'posts',
                'datas' => array(
                    'id' => $request->currentid,
                    'offset' => $request->offset,
                    'sort' => $request->sort,
                    'dir' => $request->dir
            ))));
        }
        else $request->setResponse(new Response);
        Plugins::posttrigger($request);
        return $this;
    }

    /**
     * Renames a stream
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param mixed $request the Request instance
     * @return $this
     */
    public function rename(Request $request)
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

        if(empty($request->name))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Missing name',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        $stream = DAO::getCachedDAO('streams_relations_name')->get(array('rssid' => $request->id), 'rssid, uid');
        if(empty($stream))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Invalid id',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        $stream->name = $request->name;
        $stream->save();
        Plugins::trigger($request);
        $request->setResponse(new Response);
        Plugins::posttrigger($request);
        return $this;
    }

    /**
     * Checks for dead streams
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param mixed $request the Request instance
     * @return $this
     */
    public function checkAvailability(Request $request)
    {
        Plugins::pretrigger($request);
        if(empty($request->id))
        {
            $streams = $this->_db->execute('
    SELECT id,url
        FROM streams
        WHERE status > 0');

            if($streams->count())
            {
                $threads = Threads::iGet();
                while($streams->next())
                {
                    $threads->add(array('do'=>'checkstreamsavailability', 'id'=>$streams->id));
                }

                $request->setResponse(new Response(array(
                    'status'    => 202
                )));
            }

            $request->setResponse(new Response);
            return $this;
        }

        $streams = $this->_db->execute('
    SELECT id,url
        FROM streams
        WHERE id='.$request->id.' AND status > 0');

        if($streams->count())
        {
            $dao = DAO::getDAO('streams');
            while($streams->next())
            {
                try
                {
                    if(false !== cURLWrapper::get($streams->url))
                    {
                        $dao->id = $streams->id;
                        $dao->status = 0; // available, else it's a timestamp of downtime
                        $dao->ttl = 0; // to be refreshed on next cron processing
                        $dao->save();
                    }
                }
                catch(Exception $e)
                {
                    switch($e->getCode())
                    {
                        case Exception::E_OWR_NOTICE:
                        case Exception::E_OWR_WARNING:
                            Logs::iGet()->log($e->getContent(), $e->getCode());
                            break;
                        default:
                            throw new Exception($e->getContent(), $e->getCode());
                            break;
                    }
                }
            }
        }
        Plugins::trigger($request);
        $request->setResponse(new Response);
        Plugins::posttrigger($request);
        return $this;
    }

    /**
     * Tries to get streams favicons
     * If you have Imagick extension installed, it will also try to validate the icon
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $request the Request instance
     * @access protected
     * @return $this
     */
    public function manageFavicons(Request $request)
    {
        Plugins::pretrigger($request);
        if(empty($request->id))
        {
            $streams = $this->_dao->get(array(), 'id, url');
            if(empty($streams))
            {
                $request->setResponse(new Response);
                return $this;
            }

            $threads = Threads::iGet();

            foreach($streams as $stream)
            {
                $threads->add(array('do'=>'managefavicons', 'id'=>$stream->id));
            }

            $request->setResponse(new Response(array(
                'status'    => 202
            )));
            return $this;
        }

        $stream = $this->_dao->get(array('id'=>$request->id), 'id, url, favicon');
        $currentFavicon = $stream->favicon;
        $streamContents = DAO::getCachedDAO('streams_contents')->get(array('rssid'=>$request->id), 'contents');
        if(empty($stream))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Invalid id',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        $streamContents = DAO::getCachedDAO('streams_contents')->get(array('rssid'=>$stream->id));
        $reader = new StreamReader(array('channel'=>unserialize($streamContents->contents)));
        unset($streamContents);
        $favicons = $indexes = array();

        if(!empty($stream->favicon))
            $favicons[] = $stream->favicon;

        $url = $reader->get('realurl');
        unset($reader);
        if(!empty($url))
        {
            $values = @parse_url($url);
            if(false !== $values && isset($values['scheme']) && isset($values['host']) && 'file' !== $values['scheme'])
            {
                $favicons[] = $values['scheme'].'://'.$values['host'].'/favicon.ico';
                $indexes[] = $values['scheme'].'://'.$values['host'];
                $indexes[] = $url;
            }
        }

        $favicon = '';
        $values = @parse_url($stream->url);
        if(false === $values || !isset($values['scheme']) || 'file' === $values['scheme'])
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Invalid url',
                'status'    => Exception::E_OWR_UNAVAILABLE
            )));
            return $this;
        }
        else
        {
            $base = $values['scheme'].'://'.$values['host'];
            // we check the base of the domain first
            // some blogs are responding at url like http://blog.com/feeds/(favicon.ico|something)
            // with stream contents burk, we would /require/ imagick to check..
            $favicons[] = $base.'/favicon.ico';
            $indexes[] = $base;
            if(isset($values['path']) && '/' !== ($path = dirname($values['path'])))
            {
                $favicons[] = $base.$path.'/favicon.ico';
                $indexes[] = $base.$path;
            }
        }

        $favicons = array_unique($favicons);
        
        foreach($favicons as $fav)
        {
            try
            {
                $headers = array();
                $icon = cURLWrapper::get($fav, array(), false, true, $headers);
            }
            catch(Exception $e)
            {
                // is it really usefull to log here, surely not, only for debug
                if(DEBUG) Logs::iGet()->log($e->getContent(), $e->getCode());
            }

            if(empty($icon) || false == strpos($headers['Content-Type'], 'image')) continue;
            
            if(class_exists('Imagick', false))
            {
                try
                {
                    $image = new \Imagick();
                    $image->setFormat('ico');
                    if(@$image->readImageBlob($icon))
                    {
                        $image->destroy();
                        unset($image);
                        $favicon = $fav;
                        break;
                    }
                }
                catch(Exception $e)
                { // is it really usefull to log here, surely not
                    if(DEBUG) Logs::iGet()->log($e->getContent(), $e->getCode());
                }

                unset($image);
            }
            else
            {
                if(@imagecreatefromstring($icon))
                {
                    $favicon = $fav;
                    break;
                }
                elseif('ico' === pathinfo($fav, PATHINFO_EXTENSION))
                {
                    $favicon = $fav;
                }
            }
        }
        
        unset($favicons, $icon);

        if(empty($favicon))
        {
            $indexes = array_unique($indexes);
            foreach($indexes as $index)
            {
                try
                {
                    $page = cURLWrapper::get($index, array(), false, true, $headers);
                }
                catch(Exception $e)
                {
                    unset($page);
                    // is it really usefull to log here, surely not, only for debug
                    if(DEBUG) Logs::iGet()->log($e->getContent(), $e->getCode());
                    continue;
                }

                if(empty($page) || !($hrefs = $this->_extractHREF(array('rel' => array('icon', 'shortcut icon')), $page)))
                {
                    unset($page);
                    continue;
                }

                unset($page);

                $icon = array();
                foreach($hrefs as $href)
                {
                    $url = @parse_url($href);
                    if(!$url) continue;

                    if((!isset($url['scheme']) || 'file' === $url['scheme']))
                    {
                        if(!isset($url['path'])) continue;

                        if('/' !== mb_substr($index, -1, 1, 'UTF-8'))
                            $index .= '/';

                        $url = @parse_url($index.$url['path']);
                        if(!$url || !isset($url['path']) || !isset($url['scheme']) || !isset($url['host'])) continue;

                        // try to resolve relative paths
                        // can't use realpath() because it only resolves local path
                        $realpath = array();
                        $path = explode('/', preg_replace(array('/\/+/', '/\/\.\//'), '/', $url['path']));
                        foreach($path as $part)
                        {
                            if('..' === $part)
                            {
                                array_pop($realpath);
                            }
                            elseif('' !== $part)
                            {
                                $realpath[] = $part;
                            }
                        }

                        if(empty($realpath)) continue;
                        $href = $url['scheme'].'://'.$url['host'].'/'.join('/', $realpath);
                    }

                    try
                    {
                        $headers = array();
                        $icon = cURLWrapper::get($href, array(), false, true, $headers);
                    }
                    catch(Exception $e)
                    {
                        unset($icon);
                        // is it really usefull to log here, surely not, only for debug
                        if(DEBUG) Logs::iGet()->log($e->getContent(), $e->getCode());
                        continue;
                    }

                    if(empty($icon) /*|| false === strpos($headers['Content-Type'], 'image')*/) continue;

                    if(class_exists('Imagick', false))
                    {
                        try
                        {
                            $image = new \Imagick();
                            $image->setFormat('ico');
                            if(@$image->readImageBlob($icon))
                            {
                                $image->destroy();
                                unset($image);
                                $favicon = $href;
                                break 2;
                            }
                        }
                        catch(Exception $e)
                        { // is it really usefull to log here, surely not
                            if(DEBUG) Logs::iGet()->log($e->getContent(), $e->getCode());
                        }

                        unset($image);
                    }
                    else
                    {
                        if(@imagecreatefromstring($icon))
                        {
                            $favicon = $href;
                            break 2;
                        }
                        elseif('ico' === pathinfo($href, PATHINFO_EXTENSION))
                        { // TODO : to be enhanced
                            $favicon = $href;
                            break 2;
                        }
                    }
                }
            }
            unset($indexes, $index, $page);
        }
        
        if((string) $currentFavicon !== (string) $favicon)
        {
            $stream->favicon = (string) $favicon;
            $stream->url = null;
            $stream->save();
        }
        Plugins::trigger($request);
        $request->setResponse(new Response);
        Plugins::posttrigger($request);
        return $this;
    }

    /**
     * Tries to refresh stream(s)
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @param mixed $request the Request instance
     * @return $this
     */
    public function refresh(Request $request)
    {
        Plugins::pretrigger($request);
        if(empty($request->id))
        {
            $query = "
    SELECT r.id
        FROM streams_relations rel
        JOIN streams r ON (rel.rssid=r.id)
        WHERE rel.uid=".User::iGet()->getUid().' AND (r.lastupd + (r.ttl * 60)) <= UNIX_TIMESTAMP()';

            $rss = $this->_db->getAll($query);
            if($rss->count())
            {
                $threads = Threads::iGet();
                while($rss->next())
                {
                    $threads->add(array('do'=>'refreshstream', 'id'=>$rss->id));
                }
            }

            unset($rss);
            Plugins::trigger($request);
            $request->setResponse(new Response(array(
                'status'    => 202
            )));
            return $this;
        }
        else
        {
            $table = DAO::getType($request->id);

            if('streams' === $table)
            {
                $this->update($request);
            }
            elseif('streams_groups' === $table)
            {
                $query = '
    SELECT r.id
        FROM streams r
        JOIN streams_relations rel ON (r.id=rel.rssid)
        WHERE rel.gid='.$request->id.' AND rel.uid='.User::iGet()->getUid().'
        AND (lastupd + (ttl * 60)) <= UNIX_TIMESTAMP()
        GROUP BY r.id';

                $rss = $this->_db->getAll($query);
                if($rss->count())
                {
                    $threads = Threads::iGet();
                    while($rss->next())
                    {
                        $threads->add(array('do'=>'refreshstream', 'id'=>$rss->id));
                    }
                }

                unset($rss);
                Plugins::trigger($request);
                $request->setResponse(new Response(array(
                    'status'    => 202
                )));
                Plugins::posttrigger($request);
            }
            else
            {
                Plugins::trigger($request);
                $request->setResponse(new Response(array(
                    'do'        => 'error',
                    'error'     => 'Invalid id',
                    'status'    => Exception::E_OWR_BAD_REQUEST
                )));
                Plugins::posttrigger($request);
            }
        }

        return $this;
    }

    /**
     * Tries to refresh stream(s)
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @param mixed $request the Request instance
     * @return $this
     */
    public function refreshAll(Request $request)
    { // in cli, we refresh for all users
        Plugins::pretrigger($request);
        if(empty($request->id))
        {
            // status = 0 means stream is alive
            // seems obvious but in the other case it will be a timestamp of down time
            $query = '
    SELECT r.id
        FROM streams r
        WHERE (lastupd + (ttl * 60)) <= UNIX_TIMESTAMP() AND status=0';

            $streams = $this->_db->getAll($query);
            if($streams->count())
            {
                $threads = Threads::iGet();
                while($streams->next())
                {
                    $threads->add(array('do'=>'refreshstream', 'id'=>$streams->id));
                }
                Plugins::trigger($request);
                $request->setResponse(new Response(array(
                    'status'    => 202
                )));
                Plugins::posttrigger($request);
                return $this;
            }
        }
        else
        {
            $table = DAO::getType($request->id);

            if('streams' === $table)
            {
                $streams = $this->_db->getOne('
    SELECT r.id, uid
        FROM streams r
        JOIN streams_relations rel ON (r.id=rel.rssid)
        WHERE r.id='.$request->id.' AND (lastupd + (ttl * 60)) <= UNIX_TIMESTAMP()
        GROUP BY r.id');
                if($streams->next())
                {
                    User::iGet()->setUid($streams->uid);
                    try
                    {
                        $this->update($request);
                    }
                    catch(Exception $e)
                    {
                        switch($e->getCode())
                        {
                            case Exception::E_OWR_NOTICE:
                            case Exception::E_OWR_WARNING:
                                Logs::iGet()->log($e->getContent(), $e->getCode());
                                break;
                            default: throw new Exception($e->getContent(), $e->getCode());
                                break;
                        }
                    }
                }
                unset($streams);
            }
            elseif('streams_groups' === $table)
            {
                $query = '
    SELECT r.id
        FROM streams r
        JOIN streams_relations rel ON (r.id=rel.rssid)
        WHERE rel.gid='.$request->id.' AND (lastupd + (ttl * 60)) <= UNIX_TIMESTAMP()
        GROUP BY r.id';

                $streams = $this->_db->getAll($query);
                if($streams->count())
                {
                    $threads = Threads::iGet();
                    while($streams->next())
                    {
                        $threads->add(array('do'=>'refreshstream', 'id'=>$streams->id));
                    }
                    Plugins::trigger($request);
                    $request->setResponse(new Response(array(
                        'status'    => 202
                    )));
                    Plugins::posttrigger($request);
                    return $this;
                }

                unset($streams);
            }
        }
        Plugins::trigger($request);
        $request->setResponse(new Response);
        Plugins::posttrigger($request);
        return $this;
    }

    /**
     * Adds/Edits a stream
     *
     * @access public
     * @param mixed $request the Request instance
     * @return $this
     */
    public function editOPML(Request $request)
    {
        Plugins::pretrigger($request);
        if(empty($request->escape) && empty($_POST) && empty($_FILES['opml']['tmp_name']))
        {
            $request->setResponse(new Response(array(
                'tpl'        => 'upload'
            )));

            return $this;
        }

        User::iGet()->checkToken();

        parent::getCachedModel('streams_groups')->checkGroupById($request);
        $erase = false;

        if(empty($request->escape) && !empty($_FILES['opml']['tmp_name']))
        {
            $upload = new Upload('opml', array(
                'isArray'       => false,
                'mime'          => array('text/x-opml+xml', 'text/xml'),
                'finfo_mime'    => 'application/xml',
                'maxFileSize'   => Config::iGet()->get('maxUploadFileSize'),
                'ext'           => array('opml', 'xml')
            ));

            try
            {
                $request->url = $upload->get();
            }
            catch(Exception $e)
            {
                $request->setResponse(new Response(array(
                    'do'        => 'error',
                    'error'     => $e->getContent(),
                    'status'    => $e->getCode()
                )));

                return $this;
            }
        }

        if(empty($request->url))
        {
            $request->setResponse(new Response(array(
                'do'        => 'error',
                'error'     => 'Missing url',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));

            return $this;
        }

        $reader = new OPMLParser();

        $reader->parse($request->url, isset($upload));

        if(isset($upload))
        {
            unlink($request->url);
            unset($upload);
        }

        $streams = $reader->export($request->url);

        $r = clone($request);
        $r->gid = 0;
        parent::getCachedModel('streams_groups')->checkGroupById($r);
        $gidRoot = $r->gid;
        unset($r);

        $currentGroup = array();

        $ids = array();

        $streamsGroupsModel = parent::getCachedModel('streams_groups');
        $r = clone($request);
        $sr = clone($request);
        $sr->delay = true;

        $gid = (0 !== $request->gid && ($gidRoot !== $request->gid)) ? $request->gid : 0;

        foreach($streams['item'] as $stream)
        {
            $url = isset($stream['xmlUrl']) ? $stream['xmlUrl'] :
                (isset($stream['htmlUrl']) ? $stream['htmlUrl'] : null);

            if(empty($url))
            {
                Logs::iGet()->log('Passing stream, missing url', Exception::E_OWR_WARNING);
                continue;
            }

            $folderId = null;
            $sr->gid = $sr->id = 0;

            if($gid)
                $sr->gid = $gid;
            elseif(isset($stream['folder']))
            {
                if(!isset($currentGroup[$stream['folder']]))
                {
                    $folderId = DAO::getCachedDAO('streams_groups')->get(array('name' => $stream['folder']), 'id');
                    if(!$folderId)
                    {
                        try
                        {
                            $r->id = 0;
                            $r->name = $stream['folder'];
                            $streamsGroupsModel->edit($r);
                            $response = $r->getResponse();
                            if('error' === $response->getNext())
                            {
                                Logs::iGet()->log($response->getError(), $response->getStatus());
                                $folderId = $gidRoot;
                            }
                            else
                            {
                                $folderId = $r->id;
                                $ids[] = $r->id;
                            }
                        }
                        catch(Exception $e)
                        {
                            switch($e->getCode())
                            {
                                case Exception::E_OWR_NOTICE:
                                case Exception::E_OWR_WARNING:
                                    Logs::iGet()->log($e->getContent(), $e->getCode());
                                    break;
                                default:
                                    throw new Exception($e->getContent(), $e->getCode());
                                    break;
                            }
                            continue;
                        }
                    }
                    else $folderId = $folderId->id;

                    $currentGroup[$stream['folder']] = $folderId;
                    unset($folderId);
                }

                $sr->gid = $currentGroup[$stream['folder']];
            }
            else $sr->gid = $gidRoot;

            $sr->name = !empty($stream['title']) ? $stream['title'] : (
                        !empty($stream['text']) ? $stream['text'] : 'No title');
            try
            {
                $sr->url = $url;
                $this->edit($sr);
                $response = $sr->getResponse();
                if('error' === $response->getNext())
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                else
                {
                    if($sr->new) $request->new = true;
                    $ids[] = $sr->id;
                }
            }
            catch(Exception $e)
            {
                switch($e->getCode())
                {
                    case Exception::E_OWR_NOTICE:
                    case Exception::E_OWR_WARNING:
                        Logs::iGet()->log($e->getContent(), $e->getCode());
                        break;
                    default:
                        throw new Exception($e->getContent(), $e->getCode());
                        break;
                }
            }
        }
        unset($gidRoot, $r, $sr);
        Plugins::trigger($request);
        $request->setResponse(new Response(array(
            'status'    => 201,
            'datas'     => array('ids' => $ids)
        )));
        Plugins::posttrigger($request);
        return $this;
    }

    /**
     * Tries to parse a stream
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $url the url to parse
     * @param string $src the original source, optionnal
     * @return mixed StreamReader on success, '' if stream has not changed, false on error
     * @access protected
     */
    protected function _parse($url, $src='')
    {
        $url = (string) $url;
        isset($this->_streamParser) || $this->_streamParser = new StreamParser();

        try
        {
            if($src)
            {
                $csrc = (string)$this->_streamParser->getSrc($url);
                if('' === $csrc || trim($csrc) === trim($src)) return ''; // stream has not changed

                $stream =  (!$this->_streamParser->parse($url, $csrc) ? false : $this->_streamParser->export());
            }
            else $stream = (!$this->_streamParser->parse($url) ? false : $this->_streamParser->export());
        }
        catch(Exception $e)
        {
            switch($e->getCode())
            {
                case Exception::E_OWR_NOTICE:
                case Exception::E_OWR_WARNING:
                    Logs::iGet()->log($e->getContent(), $e->getCode());
                    break;
                default: throw new Exception($e->getContent(), $e->getCode());
                    break;
            }
            return false;
        }

        return $stream;
    }

    /**
     * Try to get href from a specific &lt;link&gt; tag
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @param array $requestedParams the requested parameters
     * @param string $src the source to search
     * @return array found href
     */
    protected function _extractHREF(array $requestedParams, $src)
    {
        $hrefs = array();

        if(!preg_match_all('/<link\b((\s+[a-z]+\s*=\s*(["\'])[^\\3]+?\\3)+)+\s*\/?>/is', $src, $tags))
            return $hrefs;

        foreach($tags[1] as $tag)
        {
            if(!preg_match_all('/([a-z]+)\s*=\s*(["\'])([^\\2]+?)\\2/i', $tag, $params))
                continue;

            $rel = $href = $type = null;

            foreach($params[1] as $k => $param)
            {
                $param = strtolower($param);
                if('rel' === $param)
                    $rel = strtolower($params[3][$k]);
                elseif('href' === $param)
                    $href = $params[3][$k];
                elseif(isset($requestedParams['type']) && 'type' === $param)
                    $type = strtolower($params[3][$k]);
            }

            unset($params);

            if((isset($requestedParams['type']) && !isset($type)) || !$rel || !$href)
                continue;

            foreach($requestedParams['rel'] as $k=>$r)
            {
                if($rel !== $r) continue;

                if(isset($requestedParams['type']))
                {
                    foreach($requestedParams['type'] as $t)
                    {
                        if($t === $type) $hrefs[] = $href;
                    }
                }
                else
                {
                    $hrefs[] = $href;
                }
            }
        }

        return $hrefs;
    }
}
