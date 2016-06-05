var OWR = {};
OWR = function(lang, ttl, token, isSearch) {
    this.init(lang, ttl, token, isSearch);
};
OWR.prototype = {
    currentId: 0,
    pageOffset: 0,
    menuTogglerStatus: 0,
    isLoading: 0,
    adding: '',
    period: 0,
    TS: [],
    nbLogsLine: 0,
    sortables:false,
    aAcc:{},
    lang:'',
    languages:['fr_FR','en_US'],
    messages:[],
    lastUpd: 0,
    ttl: 0,
    keywords: '',
    sort: '',
    dir: '',
    token: '',
    boardTogglerStatus: 1,
    live:1,
    ssrequest:null,
    init: function(lang, ttl, isSearch)
    {
        this.loading(true);
        if(!this.languages.contains(lang)) { lang='en_US'; }
        this.lang=lang;
        this.ttl = ttl / 1000;
        this.lastUpd = this.getTS();
        this.period = this.getLastNews.periodical(ttl, this);
        var args = window.location.search.replace(/^\?/, '').split('&');
        var split, i;
        for(i = 0; i < args.length; i++) {
        	split = args[i].split('=');
        	if('token' === split[0]) {
        		this.token = split[1];
        		break;
        	}
		}
		$$('a[href^=' + window.location.origin + '],link[rel=search]').each(function(item) {
            var href = item.get('href');
            if(-1 !== href.indexOf('logout')) return;
            item.set('href', href + (-1 === href.indexOf('?') ? '?token=' : '&token=') + this.token);
        }.bind(this));
        switch(this.lang) {
            case 'fr_FR':
                this.messages['Loading interface'] = "Chargement de l'interface";
                this.messages['Adding a category'] = "Ajout d'une catégorie";
                this.messages['Adding a stream'] = "Ajout d'un flux";
                this.messages['Searching for keywords'] = "Recherche des mots-clés";
                this.messages['Renaming the element'] = "Renomage de l'élément";
                this.messages['Moving the element'] = "Déplacement de l'élement";
                this.messages['Asking for the RSS gateway token'] = "Demande du lien pour la passerelle RSS";
                this.messages['Asking for the OPML gateway token'] = "Demande du lien pour la passerelle OPML";
                this.messages['Asking for the REST auth token'] = "Demande des identifiants de connexion REST";
                this.messages['Refreshing the interface'] = "Mise à jour de l'interface";
                this.messages['Marking news as read'] = "Marquage des nouvelles comme lues";
                this.messages['Refreshing the menu'] = "Mise à jour du menu";
                this.messages['Getting the news'] = "Récupération des nouvelles";
                this.messages['Asking for refreshing of streams'] = "Demande de mise à jour des flux";
                this.messages['Deleting'] = "Suppression";
                this.messages['Moving to page '] = "Déplacement vers la page ";
                this.messages['Deleting news'] = "Suppression des nouvelles";
                this.messages['Making the maintenance.. please wait, it may take a while'] = "Maintenance en cours, celà peut prendre un certain temps";
                this.messages['Setting the new interface language'] = "Mise à jour de la langue de l'interface";
                this.messages['Getting the list of the users'] = "Récupération des données utilisateurs";
                this.messages['Wait wait ! A request is still running ! Please confirm you are leaving'] = "Attendez ! Attendez ! Une requête est toujours en cours ! Veuillez confirmer que vous partez";
                this.messages['Abort current request to the server ?'] = "Abandonner la requête en cours vers le serveur ?";
                this.messages['Delete ?'] = "Supprimer ?";
                this.messages['Getting contents of the category'] = "Récupération du contenu de la catégorie";
                this.messages['Getting details of the stream'] = "Récupération des détails du flux";
                this.messages['Getting contents of the new'] = "Récupération du contenu de la nouvelle";
                this.messages['Clearing myself, bye !'] = "Nettoyage en cours, à bientôt !";
                this.messages['Getting details of the new'] = "Récupération des informations de la nouvelle";
                this.messages['Marking news as unread'] = "Marquage des nouvelles comme non-lues";
                this.messages['Editing the url of the stream'] = "Édition de l'url du flux";
                this.messages['Editing tags'] = "Édition des tags";
                this.messages['Getting tags'] = "Récupération des tags";
                this.messages['Generating some statistics'] = "Génération des statistiques";
                this.messages['Asking logs'] = "Affichage des logs CLI";
                this.messages['Getting category'] = "Récupération de la catégorie";
            break;
            case 'en_US': /* don't need here, messages are by default in english */
            break;
            default:
            break;
        }
        var n = this.setLog('Loading interface');
        this.setTS();
        this.initMenu(true);
        this.initContents();
        if(isSearch !== "0") {
            this.currentId = 'search';
            this.initCurrent();
            $('menu_title_streams').getNext().toggle();
            $('keywords').set('value', isSearch);
            $('menu_tools').getNext().toggle();
            $('do_search').status = 1;
            $('contents_do_search').slide('in');
        }
        this.loading(false, n);
    },
    getMessage: function(msg) {
        return ($defined(this.messages[msg]) ? this.messages[msg] : msg);
    },
    initMenu: function(act){
        this.loading(true);
        if(act) {
            $$('div.menu_actions_toggler').removeEvents('click').each(function(item) {
                item.addEvent('click', function(e, el){
                    e.stop();
                    var id = el.get('id');
                    if(!el.status) {
                        el.status = 1;
                        $('contents_'+id).setStyles({'display':'block','visibility':'visible'}).slide('in');
                    } else {
                        el.status = !el.status;
                        $('contents_'+id).slide();
                    }
                }.bindWithEvent(this, item));
            }, this);
            $$('div.menu_actions_contents').each(function(item) {
                item.slide('out');
            });
            $$('li.menu_part_title').each(function(item, k) {
                var n = item.getNext();
                if(k > 0) {
                    n.toggle();
                }
                item.removeEvents('click').addEvent('click', function() {
                    this.toggle();
                }.bindWithEvent(n));
            });
        } else {
            this.initSortables();
        }

        this.loading(false);
    },
    gstreamsToggle: function(id, el) {
        var element = $('groupContainer_'+id);
        if(!$defined(el.status)) {
            this.getMenuPartGroup(id);
        } else {
            el.setStyle('background-position', (el.status ? '-303px 0px' : '-319px 0px'));
            element.getParent().setStyle('height', 'auto');
            element.toggle();
            el.status = !el.status;
        }
    },
    toggleBoard: function()
    {
        var board = $('board');
        if(this.boardTogglerStatus === 0) {
            $('main').setStyle('margin-top', '65px');
            if($('news_ordering')) {
                $('news_ordering').setStyle('top', '65px');
            }
            board.setStyle('display', 'block');
            $('board_toggler').setStyle('background-position', '-692px 0px').setStyle('top', '65px');
            $('menu_toggler').setStyle('top', '65px');
            this.boardTogglerStatus = 1;
        } else {
            if($('news_ordering')) {
                $('news_ordering').setStyle('top', '10px');
            }
            $('main').setStyle('margin-top', '10px');
            board.setStyle('display', 'none');
            $('board_toggler').setStyle('background-position', '-675px 0px').setStyle('top', '10px');
            $('menu_toggler').setStyle('top', '10px');
            this.boardTogglerStatus = 0;
        }
    },
    manageToggler: function(force)
    {
        if(force || this.menuTogglerStatus === 0) {
            $('menu').setStyle('display', 'none');
            $('contents').getParent().setStyle('margin-left', '0').removeClass('span8').addClass('span12');
            $('menu_toggler').setStyle('background-position', '-18px 0px');
            this.menuTogglerStatus = 1;
        } else {
            $('contents').getParent().setStyle('margin-left', '').removeClass('span12').addClass('span8');
            $('menu').setStyle('display', 'block');
            $('menu_toggler').setStyle('background-position', '0px 0px');
            this.menuTogglerStatus = 0;
        }
    },
    loadImage: function(obj, url)
    {
        this.loading(true);
        var imgObj = obj.getFirst();
        var p = obj.getParent('div.article_contents');
        var sizeParent = p.getParent().getSize();
        var size = p.getSize();
        /* need to resize dynamicly the div*/
        imgObj.addEvent('load', function() {
            obj.set('title', url);
            obj.set('target', '_blank'); /* burk */
            obj.onclick = function() { this.set('href', url); };
            p.getParent().setStyle('height', sizeParent.y + (p.getSize().y - size.y));
            this.loading(false);
        }.bindWithEvent(this));
        imgObj.set('src', url);
        obj.set('class', '');
    },
    initContents: function()
    {
        this.loading(true);
        $$('div[class^=article_title]').removeEvents('click').each(function(item) {
            var timer;
            item.addEvent('click', function(e, el) {
                $clear(timer);
                if(e.target.hasClass('link_go')) {
                    if(el.hasClass('new_container_nread')) {
                        this.updateNews(el.get('id').split('_')[1]);
                    }
                    return false;
                }
                if(e.target.hasClass('new_status') || e.target.hasClass('new_tag') || e.target.hasClass('new_tags') || e.target.hasClass('delete')) {return false;}
                e.stop();
                timer = (function(){
                    var id = el.get('id').split('_');
                    var element = $('new__'+id[1]+'_'+id[2]+'_'+id[3]);
                    if(!element) {return false;} /* hu ? */
                    if(!element.get('html').trim()) {
                        if($('new_abstract_'+id[1])) { $('new_abstract_'+id[1]).addClass('hidden'); }
                        this.getNew(el.get('id'));
                    } else {
                        var cur = el.getStyle('color');
                        var scroll = false;
                        if(el.hasClass('opened')) {
                            if($('new_abstract_'+id[1])) { $('new_abstract_'+id[1]).removeClass('hidden'); }
                            el.removeClass('opened');
                        } else {
                            if($('new_abstract_'+id[1])) { $('new_abstract_'+id[1]).addClass('hidden'); }
                            el.addClass('opened');
                            scroll = true;
                        }
                        element.toggle();
                        if(scroll) {
                            var s = new Fx.Scroll(document.body, {'wheelStops':true, 'offset':{x:0,y:el.getPosition().y - 105}});
                            s.toTop();
                        }
                    }
                }).delay(200, this);
            }.bindWithEvent(this, item));
            item.addEvent('dblclick', function(e, el) {
                if(e.target.hasClass('new_status') || e.target.hasClass('new_tag') || e.target.hasClass('new_tags')) {return false;}
                $clear(timer);
                window.open(el.getElements('.link_go').get('href'));
                if(el.hasClass('new_container_nread')) {
                    this.updateNews(el.get('id').split('_')[1]);
                }
            }.bindWithEvent(this, item));
        }, this);
        this.loading(false);
    },
    setLogs: function(msgs, error)
    {
        if(!msgs) {return;}
        $each(msgs, function(item) {
            if('object' === typeof item) {
                this.setLogs(item, error);
            } else {
                this.setLog(item, error);
            }
        }, this);
    },
    parseResponse: function(response, responseText, tpl)
    {
        if(response) {
            if(response.location) {
                window.location.href = response.location;
            }
            if(response.logs) {
                this.setLogs(response.logs);
            }
            if(response.errors) {
                this.setLogs(response.errors, true);
            }
            if($defined(response.contents)) {
                if(tpl) {
                    var tpl = $(tpl);
                    if(tpl) {
                        tpl.empty();
                        tpl.set('html', response.contents);
                        /* evaluating js from response, with pseudo security to exec only owr js */
                        tpl.getElements('script[data-owr=plugins]').each(function(e) {
                           $exec(e.text);
                        });
                    }
                    if(response.unreads) {
                        this.getUnread(response.unreads);
                    }
                    return '';
                }
                if(response.unreads) {
                    this.getUnread(response.unreads);
                }
                return response.contents;
            } else {
                if(tpl) {
                    var tpl = $(tpl);
                    if(tpl) {
                        tpl.empty();
                        tpl.set('html', response);
                        /* evaluating js from response, with pseudo security to exec only owr js */
                        tpl.getElements('script[data-owr=plugins]').each(function(e) {
                           $exec(e.text);
                        });
                    }
                }
                if(response.unreads) {
                    this.getUnread(response.unreads);

                }
            }
        } else {
            if(responseText) {
                this.setLog(responseText, true);
            }
            if(tpl) {
                var tpl = $(tpl);
                if(tpl) {
                    tpl.empty();
                }
            }
        }
        return '';
    },
    editStreamGroupsFormAction: function()
    {
        var f = $('editstreamgroup');
        var v = f.getChildren().getChildren('input[name=name]')[0].get('value');
        if('' === v) { return false; }
        this.loading(true);
        var n = this.setLog('Adding a category');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            var contents = this.parseResponse(json);
            contents.id = contents.id.toInt();
            $$('select[id^=move_]').each(function(item){
                var ok = false;
                item.getChildren('option').each(function(item){
                    if(item.get('value').toInt() === contents.id) {
                        ok = true;
                    }
                });
                if(!ok) {
                    var element = new Element('option', {'value':contents.id});
                    element.appendText(v); /* DO NOT USE element.set('html', v) FOR SECURITY REASON */
                    element.inject(item);
                }
            });
            if(!$('stream_'+contents.id) && contents.menu) {
                var div = new Element('div');
                div.set('html', contents.menu);
                $('menu_streams').grab(div.getFirst());
                if(!$$('a.anchor')) { this.initMenu(); }
            }
        }.bindWithEvent(this, n));
        r.post(f);
        return false;
    },
    editStreamFormAction: function()
    {
        this.loading(true);
        var n = this.setLog('Adding a stream');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            var stream = this.parseResponse(json);
            if(stream) {
                var div = new Element('div', {'html':stream});
                var li = div.getFirst();
                var gid = $('move_category').get('value');
                if(gid.toInt()) {
                    var exists = $('stream_' + gid);
                    var opened = $('groupContainer_' + gid);
                    if(exists) {
                        if(opened) {
                            li.inject(opened);
                        } else {
                            $('gstream_toggler_' + gid).click();
                        }
                    } else {
                        this.getStreamGroup(gid);        
                    }
                } else {
                    this.getStreamGroup();
                }
                this.initMenu();
            }
        }.bindWithEvent(this, n));
        r.post($('editstream'));
        return false;
    },
    getStreamGroup: function(id, open) {
        id = typeof id !== 'undefined' ? id : 0;
        open = typeof open !== 'undefined' ? open : true;
        this.loading(true);
        var n = this.setLog('Getting category');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            var category = this.parseResponse(json);
            if(category) {
                var div = new Element('div', {'html':category});
                var li = div.getFirst();
                var id = li.get('id');
                var exists = $(id);
                if(exists) {
                    li.replaces(exists);
                } else {
                    li.inject('menu_streams');
                }
                this.initMenu();
                if(open) {
                    $('gstream_toggler_' + id.split('_')[1]).click();
                }
            }
        }.bindWithEvent(this, n));
        r.get({'do': 'getstreamgroup', 'id': id});
        return false;
    },
    searchFormAction: function(id)
    {
        var val = '';
        if(id) {
            val = $('search_'+id).get('value');
        } else {
            val = $('keywords').get('value');
            id = 0;
        }
        if(val === '') { return false; }
        this.loading(true);
        var n = this.setLog('Searching for keywords');
        this.setLog('"' + val + '"');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n, val, id){
            this.loading(false, n);
            this.parseResponse(json, null, 'body_container');
            this.initContents();
            this.pageOffset = 0;
            this.keywords = val;
            this.currentId = 'search_'+id;
            this.initCurrent();
            var s = new Fx.Scroll(document.body, {'wheelStops':true, 'offset':{x:0,y:$('contents').getPosition().y - 105}});
            s.toTop();
        }.bindWithEvent(this, [n, val, id]));
        if(!id) {
            r.post($('search_0'));
        } else {
            r.post({'do': 'search', 'id':id, 'keywords':val});
        }
        return false;
    },
    searchStream: function(id)
    {
        if(!id) { return; }
        this.loading(true);
        var search = $('search_'+id);
        var el = $('stream_toggler_'+id);
        if(!el) {
            el = $('gstream_toggler_'+id);
        }
        if(search.getStyle('display') == 'block') {
            if(this.sortables) { el.status ? this.sortables.addItems(el.getParents()[1]) : this.sortables.removeItems(el.getParents()[1]); }
            search.setStyle('display', 'none');
        } else {
            if(this.sortables) { this.sortables.removeItems(el.getParents()[1]); }
            search.setStyles({'display': 'block', visibility:'visible'});
        }
        this.loading(false);
    },
    raiseXHRError: function(response, n)
    {
        this.loading(false, n, true);
        response = JSON.decode(response, true);
        if(response.errors) {
            this.setLogs(response.errors, true);
        }
        if(response.unreads) {
            this.getUnread(response.unreads);
        }
    },
    inputRename: function(obj)
    {
        this.loading(true);
        var val = obj.get('value');
        if(val === '') {
            obj.setStyle('display', 'none');
            this.loading(false);
            return;
        }
        var id = obj.get('id').split('_')[1];
        var contents = $('showStream_'+id).get('html');
        myRegexp = new RegExp('(<span class="title">)(.*?)(</span>)', 'gi');
        var newContents = contents.replace(myRegexp, "$1" + val + "$3");
        if(contents == newContents) {
            obj.setStyle('display', 'none');
            this.loading(false);
            return;
        }
        var n = this.setLog('Renaming the element');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n, newContents){
            this.loading(false, n);
            this.parseResponse(json);
            $('showStream_'+id).set('html', newContents);
            if($('stream_'+id).hasClass('groups')) {
                newContents = newContents.replace(myRegexp, "$1");
                $$('select[id^=move_]').each(function(item) {
                    var ok = false;
                    item.getChildren('option').each(function(item) {
                        if(item.get('value').toInt() == id) {
                            item.set('html', newContents);
                            ok = true;
                        }
                    });
                    if(!ok) {
                        var element = new Element('option', {'value':id});
                        element.inject(item);
                        element.appendText(newContents);
                    }
                });
            }
            if(this.sortables && !$('stream_toggler_'+id).status) { this.sortables.addItems($('stream_'+id)); }
        }.bindWithEvent(this,[n, newContents]));
        r.post({'do':'rename', 'name':obj.get('value'), 'id':id});
        obj.setStyle('display', 'none');
    },
    inputEditStreamUrl: function(obj)
    {
        this.loading(true);
        var val = obj.get('value');
        if(val === '') {
            obj.setStyle('display', 'none');
            this.loading(false);
            return;
        }
        var id = obj.get('id').split('_')[1];
        var gid = obj.getParents('.menu_groups')[0].get('id').split('_')[1];
        var n = this.setLog('Editing the url of the stream');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n, gid){
            this.loading(false, n);
            this.parseResponse(json);
            this.getMenuPartGroup(gid);
            this.showStream(0);
        }.bindWithEvent(this,[n, gid]));
        r.post({'do':'editstream', 'url':obj.get('value'), 'id':id});
        obj.setStyle('display', 'none');
    },
    selectMove: function(obj)
    {
        this.loading(true);
        var n = this.setLog('Moving the element');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n, obj){
            this.loading(false, n);
            this.parseResponse(json);
            var s = $('stream_'+obj.get('id').split('_')[1]);
            var o = $('gstream_toggler_'+obj.get('value'));
            if(s && o.status) {
                s.inject($('groupContainer_'+obj.get('value')));
            } else {
                s.destroy();
            }
            this.initMenu();
            this.initCurrent();
        }.bindWithEvent(this,[n, obj]));
        r.post({'do':'move','id':obj.get('id').split('_')[1], 'gid':obj.get('value')});
    },
    getRestAuthToken: function()
    {
        this.loading(true);
        var n = this.setLog('Asking for the REST auth token');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            var token = this.parseResponse(json);
            prompt('', token);
        }.bindWithEvent(this, n));
        r.get({'do':'regeneraterestauthtoken'});
    },
    getRssToken: function(id)
    {
        this.loading(true);
        var n = this.setLog('Asking for the RSS gateway token');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            var token = this.parseResponse(json);
            prompt('', token);
        }.bindWithEvent(this, n));
        r.get({'do':'regeneratersstoken','id':id});
    },
    editOPML: function()
    {
        if($('opml').get('value').length) {
            this.loading(true);
            var n = this.setLog('Adding a stream');
            var iframe = new Element('iframe');
            iframe.set({'id':'ieditopml', 'name':'ieditopml'});
            iframe.setStyles({'width':0, 'height':0, 'border':'none'});
            var f = $('editopml');
            f.set('action', './?do=editopml&token='+this.token);
            f.set('target', 'ieditopml');
            f.getParent().adopt(iframe);
            iframe.addEvent('load', function(e,n) {
                this.loading(false, n);
                $('ieditopml').destroy();
                window.location = './?token='+this.token;
            }.bindWithEvent(this, n));
            f.submit();
        } else {
            var v = $('url').get('value');
            if(!v) { return false; }
            this.loading(true);
            var n = this.setLog('Adding a stream');
            var r = new Request.JSON({'url':'./?do=editopml&token='+this.token});
            r.addEvent('failure', function(xhr, n) {
                this.raiseXHRError(xhr.responseText, n);
            }.bindWithEvent(this, n));
            r.addEvent('success', function(json, n){
                this.loading(false, n);
                this.parseResponse(json);
            }.bindWithEvent(this, n));
            r.post($('editopml'));
        }
        return false;
    },
    getOpmlToken: function(id)
    {
        this.loading(true);
        var n = this.setLog('Asking for the OPML gateway token');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            var token = this.parseResponse(json);
            prompt('', token);
        }.bindWithEvent(this, n));
        r.get({'do':'regenerateopmltoken','id':id});
    },
    initCurrent: function()
    {
        this.loading(true);
        if(typeof(this.currentId) === 'string' && -1 !== this.currentId.indexOf("search_")) {
            var id = this.currentId.split('_')[1].toInt();
            $$('span[id^=current_]').setStyle('display', 'none');
            if(!id) {
                $('current_search').setStyle('display', 'inline-block').removeClass('hidden');
            } else {
                $('current_'+id+'_all').setStyle('display', 'inline-block').removeClass('hidden');
            }
        } else {
            $$('span[id^=current_]').setStyle('display', 'none');
            $('current_'+this.currentId+(this.live ? '':'_all')).setStyle('display', 'inline-block').removeClass('hidden');
        }
        this.loading(false);
    },
    initSortables: function()
    {
        if(this.sortables) {
            $$('ul.menu_groups').each(function(e) {
                if(!this.sortables.lists.contains(e))
                    this.sortables.addLists(e);
            }, this);
            return;
        }
        this.sortables = new Sortables($$('ul.menu_groups'), {
            clone: true,
            revert:true,
            opacity:0.5,
            onStart: function(e) {
                this.currentgid = e.getParent().get('id').split('_')[1].toInt();
            }
        });
        this.sortables.addEvent('complete', function(e, el) {
                if(!el.currentgid) {return;}
                var gid = e.getParent().get('id').split('_')[1].toInt();
                if(gid === el.currentgid) {el.currentgid=0;return;}
                var id = e.get('id').split('_')[1];
                el.currentgid=0;
                this.moveSortables(id, gid);
        }.bindWithEvent(this, this.sortables));
    },
    streamsToggle: function(id, el)
    {
        var element = $('streamContainer_'+id);
        if(!$defined(el.status)) {
            if(!element) {
                var e = $('stream_'+id);
                if(!e) { return; } /* hu ? */
                e.adopt(new Element('ul', {'id': 'streamContainer_'+id, 'class': 'stream_more'}));
            }
            this.getMenuPartStream(id);
            if(this.sortables) { this.sortables.removeItems(el.getParents()[1]); }
        } else {
            el.setStyle('background-position', (el.status ? '-303px 0px' : '-319px 0px'));
            el.getParents()[2].setStyle('height', 'auto');
            element.toggle();
            if(this.sortables) { el.status ? this.sortables.addItems(el.getParents()[1]) : this.sortables.removeItems(el.getParents()[1]); }
            el.status = !el.status;
        }
    },
    moveSortables: function(id, gid)
    {
        if(!id || !gid) {return;}
        this.loading(true);
        var n = this.setLog('Moving the element');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            this.parseResponse(json);
        }.bindWithEvent(this, n));
        r.post({'do':'move','id':id, 'gid':gid});
    },
    loading: function(isLoading, ind, err)
    {
        if(isLoading) {
            if(!this.isLoading) {
            	$('logo').addClass('hide');
                $('logo-loading').removeClass('hide');
            }
            ++this.isLoading;
        } else {
            if(this.isLoading > 0) {
                --this.isLoading;
            }
            if(!this.isLoading) {
                $('logo-loading').addClass('hide');
                $('logo').removeClass('hide');
            }
            if($chk(ind)) {
                ind = ind.toInt();
                var el = $('logs').getChildren()[ind];
                if(el) {
                    el.set('html', '<span style="color:' + (err ? 'red' : 'green') + '">' + el.get('html') + '</span>');
                }
            }
        }
    },
    editStreamUrl: function(id)
    {
        if(!id) { return; }
        this.loading(true);
        var rename = $('editurl_'+id);
        if(rename.getStyle('display') == 'inline') {
            rename.setStyle('display', 'none');
            if(this.sortables && !$('stream_toggler_'+id).status) { this.sortables.addItems(rename.getParents('li[id^=stream_]')[0]); }
        } else {
            if(this.sortables) { this.sortables.removeItems(rename.getParents('li[id^=stream_]')[0]); }
            rename.setStyles({'display': 'inline', 'visibility':'visible'});
        }
        this.loading(false);
    },
    renameStream: function(id)
    {
        if(!id) { return; }
        this.loading(true);
        var rename = $('rename_'+id);
        if(rename.getStyle('display') == 'block') {
            rename.setStyle('display', 'none');
            if(this.sortables && !$('stream_toggler_'+id).status) { this.sortables.addItems(rename.getParents('li[id^=stream_]')[0]); }
        } else {
            if(this.sortables) { this.sortables.removeItems(rename.getParents('li[id^=stream_]')[0]); }
            rename.setStyles({'display': 'block','visibility':'visible'});
        }
        this.loading(false);
    },
    moveStream: function(id)
    {
        if(!id) { return; }
        this.loading(true);
        var move = $('move_'+id);
        move.setStyles(move.getStyle('display') == 'inline' ? {'display': 'none'} : {display:'inline','visibility':'visible'});
        if(move.getStyle('display') == 'inline') {
            if(this.sortables && !$('stream_toggler_'+id).status) { this.sortables.removeItems(move.getParents('li[id^=stream_]')[0]); }
        } else {
            if(this.sortables) { this.sortables.addItems(move.getParents('li[id^=stream_]')[0]); }
        }
        this.loading(false);
    },
    getUnread: function(arr)
    {
        this.loading(true);
        if(!arr) {
            var n = this.setLog('Refreshing the interface');
            var r = new Request.JSON({
                url: './?token='+this.token,
                onSuccess: function(json, text) {
                    if(!json) {
                        this.parseResponse(null, text);
                    }
                }.bindWithEvent(this)
            });
            r.addEvent('failure', function(xhr, n) {
                this.raiseXHRError(xhr.responseText, n);
            }.bindWithEvent(this, n));
            r.addEvent('success', function(json, n){
                this.loading(false, n);
                $$('span[id^=unread_]').each(function(item){
                    var id = item.get('id').split('_');
                    if(json.contents[id[1]]) {
                        item.set('html', json.contents[id[1]]);
                        item.getParent().setStyle('font-weight', 'bold');
                    } else {
                        item.set('html', '0');
                        item.getParent().setStyle('font-weight', '');
                    }
                });
                this.lastUpd = this.getTS();
            }.bindWithEvent(this, n));
            r.get({'do': 'getunread'});
        } else {
            $$('span[id^=unread_]').each(function(item){
                var id = item.get('id').split('_');
                if(arr[id[1]]) {
                    item.set('html', arr[id[1]]);
                    item.getParent().setStyle('font-weight', 'bold');
                } else {
                    item.set('html', '0');
                    item.getParent().setStyle('font-weight', 'normal');
                }
            });
            this.lastUpd = this.getTS();
            this.loading(false);
        }
    },
    updateNews: function(id, toggle)
    {
        var status = 0;
        if('page' == id) {
            id = [];
            $$('div.new_container_nread').each(function(item) {
                this.push(item.get('id').split('_')[1]);
            }, id);
            if(!id.length) { return; }
		} else if ('read' == id) {
			var currentPos = $(document).getScroll().y + $('news_ordering').getPosition().y;
			id = [];
			$$('div.article_title.new_container_nread').each(function(item) {
				if(item.getPosition().y < currentPos) this.push(item.get('id').split('_')[1]);
			}, id);
			if(!id.length) return;
        } else {
            if(toggle) {
                status = $$('div[id^=new_'+id+']')[0].hasClass('new_container_read') ? 1 : 0;
            }
        }
        this.loading(true);
        var n = this.setLog(status ? 'Marking news as unread' : 'Marking news as read');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n, status){
            this.loading(false, n);
            this.parseResponse(json);
            if(this.currentId === 0 && id === 0) { /* unread news page, asked for mark all news as (un)read */
                $('body_container').empty();
                this.pageOffset = 0;
            } else {
                if('object' === typeof id) {
                	id.each(function(item) {
						$$('div.article_title[id*="_' + item + '_"],div.article_title[id$="_' + item + '"]').each(function(it) {
							it.removeClass('new_container_nread').addClass('new_container_read');
							$('imgnew_' + it.get('id').split('_')[1]).addClass('hidden');
						});
                	});
                	if(0 === this.currentId && !$$('div.new_container_nread').length)
            			--this.pageOffset;
            	} else if(id === this.currentId) {
                    $$('div.new_container_nread]').removeClass('new_container_nread').addClass('new_container_read');
                    $$('span[id^=imgnew]').setStyle('display', 'none');
                    if('object' === typeof id && (0 === this.currentId || this.sort)) {
                        --this.pageOffset;
                    }
                } else {
                    var cleared = 0;
                    if(0 === id.toInt()) {
                        $$('div.new_container_nread').each(function(item) {
                            item.removeClass('new_container_nread').addClass('new_container_read');
                            ++cleared;
                        });
                    } else {
                        if(status) {
                            $$('div.new_container_read').each(function(item) {
                                var ids = item.get('id').split('_');
                                if(ids[1] == id || ids[2] == id || ids[3] == id) {
                                    item.removeClass('new_container_read').addClass('new_container_nread');
                                    $('imgnew_'+ids[1]).setStyle('display', 'inline-block').removeClass('hidden');
                                }
                            });
                        } else {
                            $$('div.new_container_nread').each(function(item) {
                                var ids = item.get('id').split('_');
                                if(ids[1] == id || ids[2] == id || ids[3] == id) {
                                    item.removeClass('new_container_nread').addClass('new_container_read');
                                    $('imgnew_'+ids[1]).setStyle('display', 'none');
                                    ++cleared;
                                }
                            });
                        }
                    }
                    if(cleared > 0 && (0 === this.currentId || this.sort)) {
                        --this.pageOffset;
                    }
                }
            }
        }.bindWithEvent(this, [n, status]));
        if('object' === typeof id) {
            r.get({'ids': id, 'do': 'upnew', 'currentid': this.currentId, 'status': status});
        } else {
            r.get({'id': id, 'do': 'upnew', 'currentid': this.currentId, 'status': status});
        }
    },
    clearLogs: function() { $('logs').empty();this.nbLogsLine=0; },
    showStream: function(id, live,  sort, dir){
    	if(this.ssrequest) {
    		this.ssrequest.cancel();
    	}
        this.loading(true);
        if(!id) { id=0; }
        var n = this.setLog('Getting the news');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n, sort, dir, id, live){
            this.loading(false, n);
            this.live = live;
            this.currentId = id;
            this.initCurrent();
            this.parseResponse(json, null, 'body_container');
            this.initContents();
            if(!this.boardTogglerStatus && $('news_ordering')) {
                $('news_ordering').setStyle('top', '10px');
            }
            var s = new Fx.Scroll(document.body, {'wheelStops':true, 'offset':{x:0,y:$('contents').getPosition().y - 116}});
            s.toTop();
            this.setTS();
            this.pageOffset = 0;
            this.sort = sort;
            this.dir = dir;
            this.ssrequest = null;
        }.bindWithEvent(this, [n, sort, dir, id, live]));
        r.addEvent('cancel', function(){
        	this.loading(false, this.ssrequest.n);
        	this.ssrequest = null;
        }.bindWithEvent(this));
        if(typeof id === 'string' && -1 !== id.indexOf('search_')) {
            id = id.split('_')[1];
            r.get({'do':'search', 'keywords':this.keywords, 'offset': this.pageOffset, 'sort':sort, 'dir':dir, 'id':id});
        } else {
            r.get({'id': id, 'live':live, 'do': 'getstream', 'sort': sort, 'dir': dir});
        }
        this.ssrequest = r;
        this.ssrequest.n = n;
    },
    refreshStream: function(id, refresh) {
        this.loading(true);
        if(!id) { id = 0; }
        if(!refresh) { refresh = 0; }
        else {
            if(refresh !== 0 && refresh !== 1) {
                if(refresh === "true") { refresh = 1; }
                else { refresh = 0; }
            }
        }
        var n = this.setLog('Asking for refreshing of streams');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            this.parseResponse(json);
        }.bindWithEvent(this, n));
        r.get({'id': id, 'do': 'refreshstream', 'currentid': this.currentId, 'force': refresh});
    },
    deleteStream: function(id) {
        if(!confirm(this.getMessage('Delete ?'))) { return; }
        if(!id) { id=0; }
        this.loading(true);
        var n = this.setLog('Deleting');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            if(0 !== id && (id == this.currentId || 0 === this.currentId)) {
                this.parseResponse(json, null, 'body_container');
                this.initContents();
            } else {
                if(0 === id) {
                    $('body_container').empty();
                    $('menu_streams').getChildren().each(function(item) {
                        if('stream_0' != item.get('id')) {
                            item.destroy();
                        }
                    });
                    $$('select[id^=move_]').each(function(item){
                        item.getChildren('option').each(function(item){
                            item.destroy();
                        });
                    });
                }
                this.parseResponse(json);
            }
            if(0 !== id) {
                if($('stream_'+id)) {
                    $('stream_'+id).destroy();
                    $$('select[id^=move_]').each(function(item){
                        item.getChildren('option').each(function(item){
                            if(item.get('value').toInt() === id) {
                                item.destroy();
                            }
                        });
                    });
                }
                var news = $$('div[id^=new_\d+]');
                var ids = [];
                news.each(function(item){
                    ids = item.get('id').split('_');
                    if(ids[1] == id || ids[2] == id || ids[3] == id) { item.destroy();$('new__'+id[1]+'_'+id[2]+'_'+id[3]).destroy(); }
                });
            }
            if(id == this.currentId) { this.currentId = 0; }
        }.bindWithEvent(this, n));
        r.get({'do': 'delete', 'id': id, 'currentid': this.currentId});
    },
    deleteUser: function(id) {
        if(!id) { return; }
        if(!confirm(this.getMessage('Delete ?'))) {
            return;
        }
        this.loading(true);
        var n = this.setLog('Deleting');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n, id){
            this.loading(false, n);
            $('user_'+id).destroy();
        }.bindWithEvent(this, [n, id]));
        r.get({'do': 'delete', 'id': id});
    },
    deleteNew: function(id) {
        if(!id) { return; }
        if(!confirm(this.getMessage('Delete ?'))) {
            return;
        }
        this.loading(true);
        var n = this.setLog('Deleting');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n, id){
            this.loading(false, n);
            $$('id^=new__?'+id).destroy();
        }.bindWithEvent(this, [n, id]));
        r.get({'do': 'delete', 'id': id});
    },
    moveToPage: function(offset, status) {
        if(!offset) { offset = 0; }
        if(this.pageOffset == offset) { return; }
        if(status) {
            ids = [];
            $$('div.new_container_nread').each(function(item) {
                this.push(item.get('id').split('_')[1]);
            }, ids);
        }
        this.loading(true);
        if('next' === offset) {
            offset = this.pageOffset >= 0 ? this.pageOffset + 1 : 0;
            if(status && (this.live || (this.sort == "status" && ids.length > 0)) && offset > 0) {
                --offset;
            }
        } else {
            if('prev' === offset) {
                offset = this.pageOffset > 0 ? this.pageOffset - 1 : 0;
                if(status && (!this.live || (this.sort == "status" && ids.length > 0))) {
                    ++offset;
                }
            }
        }
        var n = this.setLog(['Moving to page ',offset+1]);
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            $$('div.links[id^=moveToPage_]').destroy();
            this.parseResponse(json, null, 'body_container');
            this.initContents();
            if(!this.boardTogglerStatus && $('news_ordering')) {
                $('news_ordering').setStyle('top', '10px');
            }
            var s = new Fx.Scroll(document.body, {'wheelStops':true, 'offset':{x:0,y:$('contents').getPosition().y - 105}});
            s.toTop();
            this.pageOffset = offset;
        }.bindWithEvent(this, n));
        if(typeof this.currentId === 'string' && -1 !== this.currentId.indexOf('search_')) {
            r.get({'do':'search', 'keywords':this.keywords, 'offset': offset, 'sort':this.sort, 'dir':this.dir, 'id':this.currentId.split('_')[1]});
        } else {
            if(status && ids.length) {
                r.get({'do': 'getstream', 'id': this.currentId, 'offset': offset, 'sort':this.sort, 'dir':this.dir, 'status':0, 'ids':ids, 'live': this.live});
            } else {
                r.get({'do': 'getstream', 'id': this.currentId, 'offset': offset, 'sort':this.sort, 'dir':this.dir, 'live':this.live});
            }
        }
    },
    clearStream: function(id) {
        if(!confirm(this.getMessage('Delete ?'))) {return;}
        this.loading(true);
        var n = this.setLog('Deleting news');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            this.parseResponse(json, null, 'body_container');
            this.initContents();
        }.bindWithEvent(this, n));
        r.get({'do': 'clearstream', 'id': id, 'currentid': this.currentId});
    },
    askMaintenance: function() {
        this.loading(true);
        var n = this.setLog('Making the maintenance.. please wait, it may take a while');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            this.parseResponse(json);
        }.bindWithEvent(this, n));
        r.get({'do': 'maintenance'});
    },
    setLog: function(msg, error) {
        if(!msg) {return;}
        var li = new Element('li');
        var spanC = new Element('span');
        if(error) {
            spanC.set('class', 'error');
        }
        var date = new Date();
        var h = date.getHours();
        var m = date.getMinutes();
        var s = date.getSeconds();
        spanC.appendText((h < 10 ? '0'+h.toString() : h)  + ':' + (m < 10 ? '0'+m.toString() : m) + ':' + (s < 10 ? '0'+s.toString() : s) + ' ');
        if("string" !== (typeof msg)) {
            msg.each(function(item){
                if($defined(this.messages[item])) { item = this.messages[item]; }
                spanC.appendText(item);
            }, this);
        } else {
            if($defined(this.messages[msg])) { msg = this.messages[msg]; }
            spanC.appendText(msg);
        }
        li.adopt(spanC);
        ++this.nbLogsLine;
        li.set('id', 'logging_line_' + this.nbLogsLine);
        if(!$('logs')) {
            $('logs_container').adopt(new Element('ul', {'id':'logs'}));
        }
        $('logs').adopt(li);
        if(this.nbLogsLine>3) {
            /* TODO : without Fx.Scroll (useless here, often launched) */
            var s = new Fx.Scroll($('logs_container'), {
                duration: 1
                });
            s.toElement(li);
        }
        return (this.nbLogsLine - 1);
    },
    setLang: function(lang) {
        this.loading(true);
        var n = this.setLog('Setting the new interface language');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            if(!json.errors) {
                window.location.href = './?token='+this.token;
                return;
            }
            this.parseResponse(json);
        }.bindWithEvent(this, n));
        r.post({'do': 'changelang', 'newlang':lang});
    },
    getUsersList: function() {
        this.loading(true);
        var n = this.setLog('Getting the list of the users');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            this.parseResponse(json, null, 'body_container');
            this.currentId = 0;
            var s = new Fx.Scroll(document.body, {'wheelStops':true, 'offset':{x:0,y:$('contents').getPosition().y - 105}});
            s.toTop();
        }.bindWithEvent(this, n));
        r.get({'do': 'getusers'});
    },
    getStats: function() {
        this.loading(true);
        var n = this.setLog('Generating some statistics');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            this.parseResponse(json, null, 'body_container');
            this.currentId = 0;
            var s = new Fx.Scroll(document.body, {'wheelStops':true, 'offset':{x:0,y:$('contents').getPosition().y - 105}});
            s.toTop();
        }.bindWithEvent(this, n));
        r.get({'do': 'stats'});
    },
    getLastNews: function() {
        if((this.lastUpd + this.ttl) > this.getTS()) { return; }
        this.loading(true);
        var n = this.setLog('Refreshing the interface');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            if(0 === this.currentId) {
                this.parseResponse(json);
            } else {
                this.parseResponse(json);
            }
        }.bindWithEvent(this, n));
        r.get({'do': 'getlastnews', 'currentid': this.currentId});
    },
    confirmExit: function() {
        if(this.isLoading) {
            this.setLog('Wait wait ! A request is still running ! Please confirm you are leaving', true);
            if(!confirm('Abort current request to the server ?')) {
                return false;
            }
        }
        return true;
    },
    getMenuPartGroup: function(id) {
        if(!id) {return;}
        this.loading(true);
        var n = this.setLog('Getting contents of the category');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            if(!$('groupContainer_' + id)) {
                $('stream_' + id).adopt(new Element('ul', {'id': 'groupContainer_'+id, 'class': 'menu_groups'}));
            }
            if(this.sortables)
                this.sortables.removeLists($('groupContainer_'+id));
            this.parseResponse(json, null, 'groupContainer_'+id);
            this.initSortables();
            $('gstream_toggler_'+id).setStyle('background-position', '-319px 0px').status = 1;
            $('groupContainer_'+id).setStyle('display', 'block').getParent().setStyle('height', 'auto');
        }.bindWithEvent(this, n));
        r.get({'do': 'getmenupartgroup', 'id': id});
    },
    getMenuPartStream: function(id) {
        if(!id) {return;}
        this.loading(true);
        var n = this.setLog('Getting details of the stream');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            this.parseResponse(json, null, 'streamContainer_'+id);
            $('stream_toggler_'+id).setStyle('background-position', '-319px 0px').status = 1;
            $('streamContainer_'+id).setStyle('display', 'block').getParents()[2].setStyle('height', 'auto');
        }.bindWithEvent(this, n));
        r.get({'do': 'getmenupartstream', 'id': id});
    },
    getNewDetails: function(id, obj) {
        if(!id) { return false; }
        if(!obj.status) {
            this.loading(true);
            var n = this.setLog('Getting details of the new');
            var r = new Request.JSON({
                url: './?token='+this.token,
                onSuccess: function(json, text) {
                    if(!json) {
                        this.parseResponse(null, text);
                    }
                }.bindWithEvent(this)
            });
            r.addEvent('failure', function(xhr, n) {
                this.raiseXHRError(xhr.responseText, n);
            }.bindWithEvent(this, n));
            r.addEvent('success', function(json, n, el, id){
                this.loading(false, n);
                this.parseResponse(json, null, 'new_details_'+id);
                $('new_details_'+id).toggle();
                el.status = 1;
            }.bindWithEvent(this, [n, obj, id]));
            r.get({'do': 'getnewdetails', 'id': id});
        } else {
            $('new_details_'+id).toggle();
        }
    },
    getTS: function() {
        return Math.floor((new Date()).getTime() / 1000);
    },
    setTS: function(id) {
        if(id) {
            this.TS[id] = this.getTS();
        } else {
            this.TS[this.currentId] = this.getTS();
        }
    },
    getCurrentTS: function(id) {
        if(id) {
            if($defined(this.TS[id])) {
                return this.TS[id];
            }
        } else {
            if($defined(this.TS[this.currentId])) {
                return this.TS[this.currentId];
            }
        }
        if(this.lastUpd) return this.lastUpd;
        if($defined(this.TS[0])) return this.TS[0];
        return 0;
    },
    getNew: function(id) {
        if(!id) {return;}
        this.loading(true);
        var n = this.setLog('Getting contents of the new');
        var el = $(id);
        var live = el.hasClass('new_container_nread') ? 1 : 0;
        var ids = id.split('_');
        idc = el.getNext();
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n, ids, idc, live, el){
            this.loading(false, n);
            var contents = this.parseResponse(json);
            if(contents) {
                idc.set('html', contents).setStyle('visibility', 'visible').toggle();
                el.removeClass('new_container_nread').addClass('new_container_read').addClass('opened');
            }
            var img = $('imgnew_'+ids[1]);
            if(img) {img.setStyle('display', 'none');}
            if(0 === this.currentId) {
                --this.pageOffset;
            }
            var s = new Fx.Scroll(document.body, {'wheelStops':true, 'offset':{x:0,y:el.getPosition().y - 105}});
            s.toTop();
        }.bindWithEvent(this, [n, ids, idc, live, el]));
        r.get({'do': 'getnewcontents', 'id': ids[1], 'live': live, 'currentid': this.currentId, 'offset':this.pageOffset});
    },
    editTags: function(id) {
        var tags = $('edit_tags_'+id);
        if(!tags) {
            return;
        }
        if(tags.hasClass('hidden')) {
            if(!tags.status) {
                var n = this.setLog('Getting tags');
                var r = new Request.JSON({
                    url: './?token='+this.token,
                    onSuccess: function(json, text) {
                        if(!json) {
                            this.parseResponse(null, text);
                        }
                    }.bindWithEvent(this)
                });
                r.addEvent('failure', function(xhr, n) {
                    this.raiseXHRError(xhr.responseText, n);
                }.bindWithEvent(this, n));
                r.addEvent('success', function(json, n, tags){
                    this.loading(false, n);
                    var contents = this.parseResponse(json);
                    if(contents) {
                        tags.set('value', contents);
                    }
                    tags.removeClass('hidden').setStyle('display', 'block');
                    tags.focus();
                    tags.status = 1;
                }.bindWithEvent(this, [n,tags]));
                r.get({'do': 'gettags', 'id': id});
            } else {
                tags.setStyle('display', 'block');
            }
        } else {
            if('none' == tags.getStyle('display')) {
                tags.setStyle('display', 'block');
            } else {
                tags.setStyle('display', 'none');
            }
        }
    },
    inputEditTags: function(obj, id) {
        var n = this.setLog('Editing tags');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n, id){
            this.loading(false, n);
            var contents = this.parseResponse(json);
            if(contents) {
                var div = new Element('div',{'html':contents});
                $('menu_tags').adopt(div.getChildren());
            }
            this.editTags(id);
        }.bindWithEvent(this, [n,id]));
        r.post({'do': 'edittagsrelations', 'ids': id, 'name': obj.get('value')});
    },
    clear: function() {
        this.setLog('Clearing myself, bye !');
        if($chk(this.period)) { $clear(this.period); }
        delete this.period;
        delete this.currentId;
        delete this.pageOffset;
        delete this.menuTogglerStatus;
        delete this.isLoading;
        if($chk(this.adding)) { delete this.adding; }
        delete this.aAcc;
        delete this.TS;
        delete this.nbLogsLine;
        delete this.sortables;
        delete this.lang;
        delete this.adding;
        delete this.languages;
        delete this.messages;
        delete this.lastUpd;
        delete this.ttl;
        delete this.keywords;
        delete this.sort;
        delete this.dir;
        delete this.token;
        delete this.boardTogglerStatus;
        return true;
    },
    getCLILogs: function() {
    	this.loading(true);
    	var n = this.setLog('Asking logs');
        var r = new Request.JSON({
            url: './?token='+this.token,
            onSuccess: function(json, text) {
                if(!json) {
                    this.parseResponse(null, text);
                }
            }.bindWithEvent(this)
        });
        r.addEvent('failure', function(xhr, n) {
            this.raiseXHRError(xhr.responseText, n);
        }.bindWithEvent(this, n));
        r.addEvent('success', function(json, n){
            this.loading(false, n);
            this.parseResponse(json, null, 'body_container');
            this.currentId = 0;
            var s = new Fx.Scroll(document.body, {'wheelStops':true, 'offset':{x:0,y:$('contents').getPosition().y - 105}});
            s.toTop();
        }.bindWithEvent(this, n));
        r.get({'do': 'getclilogs'});
    }
};
