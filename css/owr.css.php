<?php
header('Content-type: text/css; charset=utf-8');
header('Cache-Control: Public, must-revalidate');
header("Expires: ".@gmdate("D, d M Y H:i:s", time() + 60*60*24*365)." GMT");
$lastMtime = filemtime(__FILE__);
header("Last-Modified: ".@gmdate("D, d M Y H:i:s", $lastMtime)." GMT"); 
$etag = '"css-'.md5_file(__FILE__).'"';
header('Etag: '.$etag);
if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag)
{
    header("HTTP/1.1 304 Not Modified");
    exit;
}
unset($etag,$lastMtime);
// $contents = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', file_get_contents('owr_nominify.css'));
// $contents = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $contents);
// echo $contents;
//readfile('owr_nominify.css');
//exit;
// try to gzip the page
$encoding = false;
if(extension_loaded('zlib') && !ini_get('zlib.output_compression'))
{
    if(function_exists('ob_gzhandler') && @ob_start('ob_gzhandler'))
        $encoding = 'gzhandler';
    elseif(!headers_sent() && isset($_SERVER['HTTP_ACCEPT_ENCODING']))
    {
        if(strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false) 
        {
            $encoding = 'x-gzip';
        } 
        elseif(strpos($_SERVER['HTTP_ACCEPT_ENCODING'],'gzip') !== false) 
        {
            $encoding = 'gzip';
        }
    }
}
switch($encoding)
{
    case 'gzhandler':
        @ob_implicit_flush(0);
        break;
    case 'gzip':
    case 'x-gzip':
        header('Content-Encoding: ' . $encoding);
        ob_start();
    default:
        break;
}
?>
body{background-color:#EBEBEB;padding:0;margin:0;font-family:sans-serif;font-size:90%;}img{border:0;}a{color:black;text-decoration:none;}select{font-size:smaller;margin:0 0 5px 0;padding:0;}input{font-size:smaller;margin:0 0 5px 0;}table, fieldset, legend{border:none;}legend {font-weight:bold;}table caption{font-weight:bold;text-decoration:underline;}table tr, table td, table th{border:1px solid #F5F5F5;}.error_warning{font-size:60%;color:black;font-weight:bold;}.error{color:red;}.hidden{display:none;}.small{font-size:small;}.verysmall{font-size:smaller;}.bigger{font-size:larger;}.left{float:left;}.right{float:right;}.bold{font-weight:bold;}.opac{opacity:0.4;-ms-filter:"progid:DXImageTransform.Microsoft.Alpha(Opacity=40)";filter:alpha(opacity=40);-moz-opacity:0.4;-khtml-opacity:0.4;-webkit-opacity:0.4;}.opac:hover{opacity:1;-ms-filter:"progid:DXImageTransform.Microsoft.Alpha(Opacity=100)";filter:alpha(opacity=100);-moz-opacity:1;-khtml-opacity:1;-webkit-opacity:1;}.full_rounded{border-radius:10px 10px 10px 10px; -moz-border-radius:10px 10px 10px 10px;-webkit-border-radius:10px 10px 10px 10px;-khtml-border-radius:10px 10px 10px 10px;-o-border-radius:10px 10px 10px 10px; }.bottom_rounded{border-radius:0 0 10px 10px; -moz-border-radius:0 0 10px 10px;-webkit-border-radius:0 0 10px 10px;-khtml-border-radius:0 0 10px 10px;-o-border-radius:0 0 10px 10px; }.shadow{box-shadow:3px 5px 5px gray;-moz-box-shadow:3px 5px 5px gray;-webkit-box-shadow:3px 5px 5px gray;-khtml-box-shadow:3px 5px 5px gray;-o-box-shadow:3px 5px 5px gray; }.backgrounded{background-repeat:no-repeat;background-image:url(../images/images.php?f=icons.png);width:16px;height:16px;display:block;}.noborder{border:none;}.underlined{text-decoration:underline;}div#menu{width:25%;overflow:auto;position:fixed;padding:0;margin:0;background-color:#EBEBEB;top:75px;bottom:0;}ul#logs{list-style-type:none;padding:0;margin:0;font-size:80%;}div#logs_container{height:100%;overflow:auto;padding:0;margin:0 86px 0 0;padding:0;color:white;}ul#logs li{padding:0 8px 0 8px;}div#contents{width:75%;margin:80px 0 0 0;padding:0;height:100%;background-color:#EBEBEB;}a#menu_toggler{background-position:0px 0px;background-color:#EBEBEB;width:16px;height:16px;cursor:pointer;border:1px solid #BBBBBB;margin:0 0 0 15px;padding:0;position:fixed;z-index:1;}a#board_toggler{background-position:-692px 0px;background-color:#EBEBEB;width:16px;height:16px;cursor:pointer;border:1px solid #BBBBBB;margin:0 0 0 40px;padding:0;position:fixed;z-index:1;}div#body_container{margin:30px 0 0 0;padding:0;}div#board{width:100%;display:block;position:fixed;background-color:black;height:70px;max-height:70px;padding:0;border:0;z-index:1;}ul.menu_container{list-style-type:none;margin:0;padding:0;}ul.menu_container li{border:1px solid #BBBBBB;border-radius:10px 10px 10px 10px; -moz-border-radius:10px 10px 10px 10px;-webkit-border-radius:10px 10px 10px 10px;-khtml-border-radius:10px 10px 10px 10px;-o-border-radius:10px 10px 10px 10px; margin:3px;}ul.menu_container li.noborder{border:none;}ul.menu_groups li:hover{background-color:#DDDDDD;}ul.menu_actions, ul.menu_streams{list-style-type:none;padding:5px;}ul.menu_actions li{padding:0 0 0 5px;font-size:80%;line-height:25px;}ul.menu_streams li{font-size:80%;margin-bottom:0;}ul.menu_actions li:hover{background-color:#DDDDDD;}img.favicon_stream, a.favicon_stream{margin:0 3px 0 0;}div.links, img.links{display:inline;}div.links, div.menu_actions_toggler, img.links{cursor:pointer;}ul.stream_more{display:none;list-style-type:none;padding-left:0;}ul.menu_groups{display:none;list-style-type:none;padding-left:0;padding-bottom:16px;}ul.menu_groups li {font-size:100%;padding:5px;cursor:move;}ul.menu_streams li.groups{padding:5px;margin:3px;}div.article{margin-top:5px;margin-bottom:3px;padding:2px;border:1px solid #999;}div.article_title{padding:5px;margin:10px 16px 0 16px;cursor:pointer;border:1px solid #666666;background-color:#BBBBBB;opacity:0.7;-ms-filter:"progid:DXImageTransform.Microsoft.Alpha(Opacity=70)";filter:alpha(opacity=70);-moz-opacity:0.7;-khtml-opacity:0.7;-webkit-opacity:0.7;}div.article_title:hover{opacity:1;-ms-filter:"progid:DXImageTransform.Microsoft.Alpha(Opacity=100)";filter:alpha(opacity=100);-moz-opacity:1;-khtml-opacity:1;-webkit-opacity:1;}div.article_contents{border:1px solid #BBBBBB;padding:5px;margin:0 16px 0 16px;font-size:90%;background-color:#DDDDDD;}div.news_spacer{margin:16px;}div.new_container_nread{font-weight:bold;}div.new_container_read{font-weight:normal;}h5.article_link{padding:3px;margin:0;}img.article_image{margin:5px 13px 3px 3px;}div#sprites_0{margin:0;}div.spacer{clear:both;}.new{background-position:-285px 0px;margin:0 10px 0 0;}.link_go{background-position:-232px 0px;margin:2px 5px 0 5px;}.current, .current_stream{background-position:-20px 0px;}.clear{margin-top:2px;background-position:-120px 0px;}div#board a.clear{margin:16px 8px 16px 8px;background-position:-120px 0px;}.gstream_toggler, .stream_toggler{margin:2px 2px 0 1px;background-position:-302px 0px;}.delete{margin-top:2px;background-position:-140px 0px;}.logout{background-position:-251px 0px;margin:16px 8px 16px 8px;}.lang_fr{background-position:-196px 0px;margin:16px 8px 16px 8px;}.lang_us{background-position:-214px 0px;margin:16px 8px 16px 8px;}.rename{margin-top:2px;background-position:-100px 0px;}.editurl{background-position:-480px 0px;margin-top:2px;}.read{margin-top:2px;background-position:-40px 0px;}.read_page{margin:0 30px 0 30px;background-position:-40px 0px;}.refresh{margin-top:2px;background-position:-60px 0px;}.refresh_category{margin-top:2px;padding:0;background-position:-268px 0px;}.move{margin-top:2px;background-position:-80px 0px;}.img_blocked{background-position:-176px 0px;display:inline;}div.article_description{padding:0 8px 0 8px;font-size:60%;font-weight:normal;margin:0 0 0 18px;}div.article_content{padding:16px 16px 32px 16px;}div.article_content a{text-decoration:underline;}div.article_content a.img_blocked{text-decoration:none;}p.stream_description{font-size:90%;padding-bottom:16px;padding-top:0;padding-right:3px;line-height:15px;}div#login{font-size:80%;width:280px;margin:10% 0% 0 40%;padding:5px;background-color:#BBBBBB;border:none;}div#login fieldset, div#loginOpenID fieldset{border:none;}div#login legend, div#loginOpenID legend{font-size:120%;text-shadow:1px 1px 0 gray;border-bottom:1px solid gray;}table.users{width:85%;text-align:right;font-size:90%;}table.users thead{height:30px;}table.users th{text-align:center;background-color:#BBBBBB;border:1px solid #BBBBBB;}table.users th.empty{background-color:#EBEBEB;border:none;}table.users td{height:35px;padding:5px;border:1px solid #BBBBBB;}table.users td.actions:hover{background-color:#BBBBBB;}table.users caption{margin:0 0 25px 0;font-size:medium;}div.users{margin:16px;}a.current.hidden, a.current_stream.hidden{display:none;}input.openid{background-image:url(../images/images.php?f=icons.png);background-position:-710px 0px;background-repeat:no-repeat;width:180px;padding-left:20px;}.icon_unavailable_feed{background-position:-425px 0px;width:18px;}.icon_opml{background-position:-371px 0px;margin:4px 10px 0 0;}.icon_category{background-position:-408px 0px;margin:0 5px 0 0;}.icon_add_category{background-position:-462px 0px;margin:4px 10px 0 0;}.search{background-position:-516px 0px;margin:2px 0 0 0;}.icon_search{background-position:-516px 0px;margin:4px 10px 0 0;}.icon_edit_user{background-position:-570px 0px;margin:4px 10px 0 0;}.icon_maintenance{background-position:-498px 0px;margin:4px 10px 0 0;}.icon_add_user{background-position:-533px 0px;margin:4px 10px 0 0;}.icon_list_user{background-position:-624px 0px;margin:4px 10px 0 0;}.icon_cache_delete{background-position:-444px 0px;margin:4px 10px 0 0;}.icon_link_rss{background-position:-480px 0px;margin:4px 10px 0 0;}.icon_rss{background-position:-390px 0px;margin:4px 10px 0 0;}.new_status{background-position:-659px 0px;margin:2px 5px 0 0;}li.menu_part_title{padding:5px;font-weight:bold;text-shadow:1px 1px 0 gray;font-size:130%;cursor:pointer;}img.favicon, a.favicon{margin:-12px 8px 0 -20px;}a.no_favicon{background-position:-642px 0px;}div.pager{margin:0 16px 5px 0;}.pager_right{background-position:-341px 0px;}.pager_left{background-position:-355px 0px;}img.logo{margin:-60px 17px 0 0;}div#login img.logo{margin:0;}.icon_user_edit{background-position:-570px 0px;}.icon_user_delete{background-position:-551px 0px;}.icon_rights_user{background-position:-606px 0px;}.icon_rights_administrator{background-position:-589px 0px;}div.article_full_details{margin:10px 0 0 0;display:none;background-color:#EBEBEB;border:1px solid #BBBBBB;}ul.stream_details{padding:0;margin:0;}ul.stream_details li{list-style-type:none;}a.stream_details{text-decoration:none;}ul.new_details{margin:0;padding:10px;list-style-type:none;}div.apis{padding:10px;}div.news_ordering{margin:25px 0 20px 0;font-size:small;text-align:center;}div.new_title{margin:0 6px 0 30px;}
<?php 
switch($encoding)
{
    case 'gzhandler':
        @ob_end_flush();
        break;
    case 'gzip':
    case 'x-gzip':
        $contents = ob_get_clean();
        $size = strlen($contents);
        $contents = gzcompress($contents, 6);
        $contents = substr($contents, 0, $size);
        echo "\x1f\x8b\x08\x00\x00\x00\x00\x00";
        echo $contents;
        flush();
    default:
        flush();
        break;
}
exit;
?>