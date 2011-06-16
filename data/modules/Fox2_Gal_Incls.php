<?php

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

class QF_Gal_incls
{
    var $th_w = 64;
    var $th_h = 64;
    var $prv_w = 256;
    var $prv_h = 256;
    var $per_page = 15;
    var $do_fullsize_js = false;
    var $do_cooliris_js = true;

    function QF_Gal_incls()
    {
        global $QF;
        $QF->Run_Module('Gallery');

        list($this->th_w, $this->th_h) = explode('|', $QF->Config->Get('thb_size', 'files_cfg', '96|96'));
        list($this->prv_w, $this->prv_h) = explode('|', $QF->Config->Get('prv_size', 'files_cfg', '256|256'));
        $this->do_fullsize_js = $QF->Config->Get('jsshow_fullsize', 'gallery', false);
        $this->do_cooliris_js = $QF->Config->Get('jsshow_cooliris', 'gallery', false);
    }

    function DPage_RSS()
    {        global $QF, $FOX;
        // this is a complex page - lets try to make it ^)

        $QF->Run_Module('UList');
        $QF->Run_Module('Parser');
        $QF->Parser->Init_Std_Tags();

        $QF->LNG->Load_Language('gallery');
        $QF->Session->Open_Session(false, true);

        $mode = $QF->GPC->Get_String('act', QF_GPC_GET, QF_STR_WORD);
        $id = $QF->GPC->Get_String('id', QF_GPC_GET, QF_STR_WORD);

        $feed_params = Array(
            'lastBuildDate' => date('r', $QF->Timer->time),
            'copyright' => ($site_name = $QF->Config->Get('site_name')) ? $site_name : 'QuickFox 2',
            'ttl' => 5,
            'generator' => 'QuickFox 2 gallery MRSS',

            );
        $feed_items  = Array();
        if ($mode == 'album' && $info = $QF->Gallery->Get_Album_Info($id))
        {
            $feed_params['title'] = $info['caption'];
            $feed_params['link'] = $FOX->Gen_URL('FoxGal_album', $id, true, true);

            $items = $info['items'];
            foreach ($info['itm_l'] as $id => $lv)
                if (!$QF->User->CheckAccess($lv))
                    unset($items[$id]);
            $items = array_values($items);
        }
        else if ($mode = 'user' && $uinfo = $QF->UList->Get_UserInfo($id))
        {
            $feed_params['title'] = sprintf(Lang('FOX_GALLERY_PAGE_USER'), $uinfo['nick']);
            $feed_params['link'] = $FOX->Gen_URL('FoxGal_user', $id, true, true);
            $items = $QF->Gallery->Get_Items(QF_GALLERY_SEARCH_USERID, $id, $QF->User->acc_level);
        }
        else
            return false;

        if (count($items))
        {
            $QF->Gallery->Load_ItemInfos($items, true);
            $datas = Array();
            foreach ($items as $id)
                $datas[] = $QF->Gallery->Get_Item_Info($id);

            list($uids, $tids) = qf_2darray_cols($datas, Array('author_id', 'pt_root'));
            // $pt_stats = $QF->PTree->Get_Stats($tids);
            $QF->UList->Query_IDs($uids);

            foreach ($datas as $id => $item)
            {
                if (($finfo = $QF->Files->Get_FileInfo($item['file_id'])) && $finfo['aspect_ratio'] != 0 && $QF->Files->Get_ImageDims($finfo['id'], $wh, QF_FILES_IDIMS_IMAGE))
                {
                    $item['width_height'] = $wh[0].' x '.$wh[1].' px';
                    $item['filename'] = $finfo['filename'];
                    $item['pics_name'] = $finfo['pics_name'];
                    $item['filesize'] = $QF->LNG->Size_Format($finfo['file_size']);
                }
                else
                    continue;

                if ($uinfo = $QF->UList->Get_UserInfo($item['author_id']))
                    $item['author'] = $uinfo['nick'];

                $item['time'] = $QF->LNG->Time_Format($item['time']);
                $item['scaption'] = $QF->USTR->Str_SmartTrim($item['caption'], 32);
                /* if ($item['pt_root'] && isset($pt_stats[$item['pt_root']]) && $pt_stats[$item['pt_root']]['posts'])
                    $item['comments'] = $pt_stats[$item['pt_root']]['posts']; */
                $feed_items[$id] = $item;
            }

        }

        $feed = Array(
            '<?xml version="1.0" encoding="'.QF_INTERNAL_ENCODING.'" standalone="yes"?>',
            '<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/" xmlns:atom="http://www.w3.org/2005/Atom">',
            '<channel>',
            '<atom:icon>'.qf_smart_htmlschars(qf_full_url('static/images/qf-cooliris.png')).'</atom:icon>'
            );
        foreach ($feed_params as $id => $val)
            $feed[] = '<'.$id.'>'.qf_smart_htmlschars($val).'</'.$id.'>';
        if (count($feed_items))
            foreach ($feed_items as $item)
            {                $feed[] = '<item>';
                $feed[] = '<title>'.qf_smart_htmlschars($item['caption']).'</title>';
                $feed[] = '<pubDate>'.date('r', $item['time']).'</pubDate>';
                $feed[] = '<guid isPermaLink="false">'.$item['id'].'</guid>';
                $feed[] = '<link>'.qf_smart_htmlschars($FOX->Gen_URL('FoxGal_item', $item['id'], true, true)).'</link>';
                //$descr = $QF->Parser->Parse($item['description'], QF_BBPARSE_ALL);
                //$feed[] = '<description><![CDATA[ '.$descr.' ]]></description>';
                //$feed[] = '<media:description type="html"><![CDATA[ '.$descr.' ]]></media:description>';
                $feed[] = '<media:description>'.qf_smart_htmlschars($item['width_height'].' / '.$item['filesize'].' / '.$item['author'].' @ '.$item['time']).'</media:description>';
                $feed[] = '<media:thumbnail url="'.qf_smart_htmlschars($FOX->Gen_URL('fox2_file_preview_sid', Array($item['file_id'], $item['pics_name']), true, true)).'" />';
                $feed[] = '<media:content url="'.qf_smart_htmlschars($FOX->Gen_URL('fox2_file_download_sid', Array($item['file_id'], $item['filename']), true, true)).'" />';
                $feed[] = '</item>';
            }
        $feed[] = '</channel>';
        $feed[] = '</rss>';
        $feed = implode("\n", $feed);

        $QF->HTTP->do_HTML = false;
        $QF->HTTP->Clear();
        $QF->HTTP->Write($feed);
        $QF->HTTP->Send_Buffer('', 'text/xml', 600);
        return true;
    }

    function Page_Gallery(&$p_title, &$p_subtitle, &$d_result, &$d_status)
    {
        global $QF, $FOX;
        // this is a complex page - lets try to make it ^)

        $QF->Run_Module('UList');
        $QF->Run_Module('Parser');
        $QF->Parser->Init_Std_Tags();

        $mode = $QF->GPC->Get_String('act', QF_GPC_GET, QF_STR_WORD);
        $item = $QF->GPC->Get_String('id', QF_GPC_GET, QF_STR_WORD);

        $QF->VIS->Load_Templates('gallery');

        if ($QF->User->UID)
            $FOX->Draw_Panel('my_gallery');

        $p_title = Lang('FOX_GALLERY_GALLERY');

        switch ($mode)
        {
            case 'items':
                return $this->_Page_Item($item, $p_title, $p_subtitle, $d_result, $d_status);
            case 'edit':
                return $this->_Page_Edit_Item($item, $p_title, $p_subtitle, $d_result, $d_status);
            case 'albums':
                return ($item)
                    ? $this->_Page_Album($item, $p_title, $p_subtitle, $d_result, $d_status)
                    : $this->_Page_Albums($p_title, $p_subtitle, $d_result);
            case 'palbums':
                return ($item)
                    ? $this->_Page_Album($item, $p_title, $p_subtitle, $d_result, $d_status)
                    : $this->_Page_Albums($p_title, $p_subtitle, $d_result, true);
            case 'new_item':
                return $this->_Page_New_Item($p_title, $p_subtitle, $d_result, $d_status);
            case 'new_album':
                return $this->_Page_New_Album($p_title, $p_subtitle, $d_result, $d_status);
            case 'users':
                return $this->_Page_UAlbum($item, $p_title, $p_subtitle, $d_result, $d_status);
        }

        // lets draw the root

        //$QF->VIS->Add_Data(0, 'HIDE_PANELS', '1');

        $page_node = $QF->VIS->Create_Node('FOX_GALLERY_PAGE' );
        $page_params = Array(
            );

        if ($al_ids = $QF->Gallery->Get_Albums(0, 0, $QF->User->acc_level)) // albums
        {            $page_params['ALBUMS_COUNT'] = count($al_ids);
            $al_ids = array_slice($al_ids, 0, 10);
            $draw_albums = array();

            $uids = $albums = $items = Array();
            $QF->Gallery->Load_AlbumInfos($al_ids);
            foreach($al_ids as $id)
            {
                $album = $QF->Gallery->Get_Album_Info($id);

                $count = count($album['items']);
                $nitems = Array();
                for ($i = 0; $i < $count && count($nitems) < 3; $i++)
                    if ($QF->User->CheckAccess($album['itm_l'][$i]))
                    {
                        $nitems[] = $album['items'][$i];
                        $items[]  = $album['items'][$i];
                    }

                $album['lastthree'] = $nitems;
                if ($album['owner_id'])
                    $uids[] = $album['owner_id'];
                else
                    unset($album['owner_id']);
                $albums[$id] = $album;
            }
            $QF->Gallery->Load_ItemInfos($items, true);
            $QF->UList->Query_IDs($uids);

            while ($album = array_shift($albums))
            {
                $itms = 0;
                $album['T_WIDTH']  = $tw = (int) max($this->th_w*1.3, 170);
                $album['T_HEIGHT'] = $th = (int) $this->th_h*1.5;
                foreach ($album['lastthree'] as $item_id)
                {
                    $item = $QF->Gallery->Get_Item_Info($item_id);
                    if (($finfo = $QF->Files->Get_FileInfo($item['file_id'])) && $finfo['aspect_ratio'] != 0 && $QF->Files->Get_ImageDims($finfo['id'], $wh, QF_FILES_IDIMS_THUMB))
                    {
                        $w = $wh[0]; $h = $wh[1];

                        $t = ($th - $h)/2;
                        $l = ($tw - $w)/2;
                        if ($itms == 1) {$t*= 1.5; $l*=0.5;}
                        elseif ($itms == 2) {$t*= 0.5; $l*=1.5;}

                        $album['TOP_LEFT'.$itms] = 'top: '.round($t).'px; left: '.round($l).'px;';

                        $album['WIDTH_HEIGHT'.$itms] = 'width: '.round($w).'px; height: '.round($h).'px;';
                        $album['FILE_ID'.$itms] = $item['file_id'];
                        $album['FILENAME'.$itms] = $finfo['filename'];
                        $album['PICS_NAME'.$itms] = $finfo['pics_name'];
                        $album['ITMCAPT'.$itms] = $item['caption'];
                        $itms++;
                    }
                }

                if ($uinfo = $QF->UList->Get_UserInfo($album['owner_id']))
                    $album['owner'] = $uinfo['nick'];

                $album['SCAPTION'] = $QF->USTR->Str_SmartTrim($album['caption'], 32);
                if ($album['COUNT'] = count($album['items']))
                {
                    $album['LTIME'] = $QF->LNG->Time_Format($album['lasttime']);
                }

                array_push($draw_albums, $album);
            }

            $QF->VIS->Add_Node_Array('FOX_GALLERY_ALBUMS_ALBUM', 'ALBUMS', $page_node, $draw_albums);
        }

        if ($items = $QF->Gallery->Get_Items(QF_GALLERY_SEARCH_LAST20, 0, $QF->User->acc_level))
        {
            $FOX->Link_JScript('swfobject');
            $FOX->Link_JScript('imageshow');
            $QF->Gallery->Load_ItemInfos($items, true);
            $draw_items = $datas = Array();
            foreach ($items as $id)
                if ($item = $QF->Gallery->Get_Item_Info($id))
                    $datas[] = $item;

            list($uids, $tids) = qf_2darray_cols($datas, Array('author_id', 'pt_root'));
            $pt_stats = $QF->PTree->Get_Stats($tids);
            $QF->UList->Query_IDs($uids);

            foreach ($datas as $item)
            {
                if (($finfo = $QF->Files->Get_FileInfo($item['file_id'])) && $finfo['aspect_ratio'] != 0 && $QF->Files->Get_ImageDims($finfo['id'], $wh, QF_FILES_IDIMS_THUMB))
                {
                    $item['WIDTH_HEIGHT'] = 'width: '.$wh[0].'px; height: '.$wh[1].'px;';
                    $item['FILENAME'] = $finfo['filename'];
                    $item['PICS_NAME'] = $finfo['pics_name'];
                    $QF->Files->Get_ImageDims($finfo['id'], $whf, $this->do_fullsize_js ? QF_FILES_IDIMS_IMAGE : QF_FILES_IDIMS_PREVIEW);
                    $item['JSPIC_WIDTH'] = $whf[0];
                    $item['JSPIC_HEIGHT'] = $whf[1];
                }
                if ($uinfo = $QF->UList->Get_UserInfo($item['author_id']))
                    $item['author'] = $uinfo['nick'];

                $item['time'] = $QF->LNG->Time_Format($item['time']);
                $item['SCAPTION'] = $QF->USTR->Str_SmartTrim($item['caption'], 32);
                $item['T_HEIGHT'] = $this->th_h + 20;
                if ($item['pt_root'] && isset($pt_stats[$item['pt_root']]) && $pt_stats[$item['pt_root']]['posts'])
                {
                    $item['comments'] = $pt_stats[$item['pt_root']]['posts'];
                    $item['lastcommenttime'] = $pt_stats[$item['pt_root']]['l_time'];
                }
                $item['JS_FULLSIZE'] = $this->do_fullsize_js ? 1 : null;

                $draw_items[] = $item;
            }

            $QF->VIS->Add_Node_Array('FOX_GALLERY_ALBUM_ITEM', 'ITEMS', $page_node, $draw_items);
            $QF->VIS->Add_Node_Array('FOX_GALLERY_ALBUM_ITEM_JS', 'ITEMS_JSLOAD', $page_node, $draw_items);
        }

        $QF->VIS->Add_Data_Array($page_node, $page_params);

        return $page_node;
    }

    function Script_Gallery()
    {
        global $QF, $FOX;

        $QF->LNG->Load_Language('gallery');

        $mode = $QF->GPC->Get_String('act', QF_GPC_POST, QF_STR_WORD);

        if (!$QF->User->UID)
            return Array(Lang('ERR_NOACCESS'), $FOX->Gen_URL('FoxGal_root'), true);

        switch ($mode)
        {
            case 'new_item':
                return $this->_Script_New_Item();
            case 'edit_item':
                return $this->_Script_Edit_Item();
            case 'new_album':
                return $this->_Script_New_Album();
        }
    }

    function _Page_Item($item, &$p_title, &$p_subtitle, &$d_result, &$d_status)
    {
        global $QF, $FOX;

        $page_node = false;

        if (($info = $QF->Gallery->Get_Item_Info($item)) && ($finfo = $QF->Files->Get_FileInfo($info['file_id'])))
        {
            if (!$QF->User->CheckAccess($info['r_level']))
            {                $d_result = Array(Lang('ERR_GALLERY_ITEM_NO_ACCESS'), $FOX->Gen_URL('FoxGal_albums'), true);
                $d_status = 404;
                return null;
            }

            $p_subtitle = $info['caption'];
            $page_node = $QF->VIS->Create_Node('FOX_GALLERY_PAGE_ITEM' );

            $page_params = Array(
                'ID'       => $info['id'],
                'CAPTION'  => $info['caption'],
                'DESCR'    => $QF->Parser->Parse($info['description'], QF_BBPARSE_ALL),
                'SIZE'     => $QF->LNG->Size_Format($finfo['file_size']),
                'TYPE'     => $finfo['mime'],
                'TIME'     => $QF->LNG->Time_Format($info['time']),
                'LEVEL'    => $info['r_level'],
                'DLOADS'   => $finfo['dloads'],
                'FID'      => $finfo['id'],
                'FILENAME' => $finfo['filename'],
                'PICS_NAME' => $finfo['pics_name'],
                'AUTHOR'   => $info['author'],
                'AUTHOR_ID' => $info['author_id'],
                'CAN_MODIFY' => ($QF->User->CheckAccess($info['r_level'], 0, 0, $info['author_id']) >= 3) ? '1' : null,
                );

            if ($uinfo = $QF->UList->Get_UserInfo($info['author_id']))
            {
                $QF->VIS->Add_Node('USER_INFO_MIN_DIV', 'AUTHOR_INFO', $page_node, $uinfo + Array('HIDE_ACCESS' => 1));
                $page_params['AUTHOR'] = $uinfo['nick'];
            }
            elseif ($info['author'])
                $page_params['AUTHOR_INFO'] = $info['author'];
            else
                $page_params['AUTHOR_INFO'] = Lang('NO_DATA');

            $prv_params = Array(
                'FID'      => $finfo['id'],
                'CAPTION'  => $info['caption'],
                'FILENAME' => $finfo['filename'],
                'PICS_NAME' => $finfo['pics_name'],
                );

            if ($finfo['aspect_ratio'] != 0 && $QF->Files->Get_ImageDims($finfo['id'], $wh, QF_FILES_IDIMS_PREVIEW))
            {
                $prv_params['WIDTH_HEIGHT'] = 'width: '.$wh[0].'px; height: '.$wh[1].'px;';
                $QF->Files->Get_ImageDims($finfo['id'], $wh, QF_FILES_IDIMS_IMAGE);
                $page_params['IMAGE_W'] = $wh[0];
                $page_params['IMAGE_H'] = $wh[1];
            }

            $albums = Array();
            if (count($info['albums']))
                foreach ($info['albums'] as $album)
                    if ($QF->User->CheckAccess($album['r_level']))
                      $albums[] = $album;

            if (count($albums))
                $QF->VIS->Add_Node_Array('FOX_GALLERY_ITEM_ALBUM_LINK', 'ALBUMS', $page_node, $albums);

            $QF->VIS->Add_Node('FOX_GALLERY_PREV_BLOCK_IMG', 'PREVIEW_BLOCK', $page_node, $prv_params);
            if ($info['pt_root'])
                $QF->VIS->Append_Node($QF->PTree->Render_Tree($info['pt_root']), 'COMMENTS_PTREE', $page_node);

            $QF->VIS->Add_Data_Array($page_node, $page_params);
        }
        else
        {
            $d_result = Array(Lang('ERR_GALLERY_ITEM_NOT_FOUND'), $FOX->Gen_URL('FoxGal_albums'), true);
            $d_status = 404;
        }

        return $page_node;
    }

    function _Page_Albums(&$p_title, &$p_subtitle, &$d_result, $personal = false)
    {
        global $QF, $FOX;

        $page_node = false;

        if ($al_ids = $QF->Gallery->Get_Albums(($personal) ? QF_GALLERY_SEARCH_PERSONAL : QF_GALLERY_SEARCH_PUBLIC, 0, $QF->User->acc_level)) // public albums
        {
            //$QF->VIS->Add_Data(0, 'HIDE_PANELS', '1');

            $p_subtitle = ($personal) ? Lang('FOX_GALLERY_PALBUMS') : Lang('FOX_GALLERY_PUBLIC_ALBUMS');
            $page_node = $QF->VIS->Create_Node('FOX_GALLERY_PAGE_ALBUMS' );
            $page_params = Array(
                'PERSONAL' => ($personal) ? '1' : null,
                );

            $pages = (int) ceil(count($al_ids)/$this->per_page);
            $page = abs($QF->GPC->Get_Num('page', QF_GPC_GET));
            if ($page < 1)
                $page = 1;
            elseif ($page > $pages)
                $page = $pages;

            if ($pages > 1)
            {
                $draw_pages = $FOX->Gen_Pages($pages, $page, Array('PERSONAL' => ($personal) ? '1' : null));

                $QF->VIS->Add_Node_Array('FOX_GALLERY_ALBUM_PG_BTN', 'PAGE_BTNS', $page_node, $draw_pages);
                $page_params['CUR_PAGE'] = $page;

                $start = $this->per_page*($page - 1);
                $al_ids = array_slice($al_ids, $start, $this->per_page);
            }

            if (!$personal && ($pal_ids = $QF->Gallery->Get_Albums(QF_GALLERY_SEARCH_PERSONAL, 0, $QF->User->acc_level)))
            {
                $page_params['PALBUMS_COUNT'] = count($pal_ids);
                shuffle($pal_ids);
                $pal_ids = array_slice($pal_ids, 0, 3);
                while ($id = array_shift($pal_ids))
                    array_push($al_ids, $id);
            }

            if (count($al_ids))
            {
                $draw_albums = $draw_palbums = array();

                $uids = $albums = $items = Array();
                $QF->Gallery->Load_AlbumInfos($al_ids);
                foreach($al_ids as $id)
                {                    $album = $QF->Gallery->Get_Album_Info($id);

                    $count = count($album['items']);
                    $nitems = Array();
                    for ($i = 0; $i < $count && count($nitems) < 3; $i++)
                        if ($QF->User->CheckAccess($album['itm_l'][$i]))
                        {
                            $nitems[] = $album['items'][$i];
                            $items[]  = $album['items'][$i];
                        }

                    $album['lastthree'] = $nitems;
                    if ($album['owner_id'])
                        $uids[] = $album['owner_id'];
                    else
                        unset($album['owner_id']);
                    $albums[$id] = $album;
                }
                $QF->Gallery->Load_ItemInfos($items, true);
                $QF->UList->Query_IDs($uids);

                while ($album = array_shift($albums))
                {
                    $itms = 0;
                    $album['T_WIDTH']  = $tw = (int) max($this->th_w*1.3, 170);
                    $album['T_HEIGHT'] = $th = (int) $this->th_h*1.5;
                    foreach ($album['lastthree'] as $item_id)
                    {
                        $item = $QF->Gallery->Get_Item_Info($item_id);
                        if (($finfo = $QF->Files->Get_FileInfo($item['file_id'])) && $finfo['aspect_ratio'] != 0 && $QF->Files->Get_ImageDims($finfo['id'], $wh, QF_FILES_IDIMS_THUMB))
                        {
                            $w = $wh[0]; $h = $wh[1];

                            $t = ($th - $h)/2;
                            $l = ($tw - $w)/2;
                            if ($itms == 1) {$t*= 1.5; $l*=0.5;}
                            elseif ($itms == 2) {$t*= 0.5; $l*=1.5;}

                            $album['TOP_LEFT'.$itms] = 'top: '.round($t).'px; left: '.round($l).'px;';

                            $album['WIDTH_HEIGHT'.$itms] = 'width: '.round($w).'px; height: '.round($h).'px;';
                            $album['FILE_ID'.$itms] = $item['file_id'];
                            $album['FILENAME'.$itms] = $finfo['filename'];
                            $album['PICS_NAME'.$itms] = $finfo['pics_name'];
                            $album['ITMCAPT'.$itms] = $item['caption'];
                            $itms++;
                        }
                    }

                    if ($uinfo = $QF->UList->Get_UserInfo($album['owner_id']))
                        $album['owner'] = $uinfo['nick'];

                    $album['SCAPTION'] = $QF->USTR->Str_SmartTrim($album['caption'], 32);
                    if ($album['COUNT'] = count($album['items']))
                    {
                        $album['LTIME'] = $QF->LNG->Time_Format($album['lasttime']);
                    }

                    if ($album['owner_id'] && !$personal)
                        array_push($draw_palbums, $album);
                    else
                        array_push($draw_albums, $album);
                }

                $QF->VIS->Add_Node_Array('FOX_GALLERY_ALBUMS_ALBUM', 'ALBUMS', $page_node, $draw_albums);
                if (count($draw_palbums))
                    $QF->VIS->Add_Node_Array('FOX_GALLERY_ALBUMS_ALBUM', 'PALBUMS', $page_node, $draw_palbums);
            }

            $QF->VIS->Add_Data_Array($page_node, $page_params);

            return $page_node;
        }
        else  // TODO: redirecting
            $QF->HTTP->Redirect($FOX->Gen_URL('FoxGal_root'));
    }

    function _Page_Album($album, &$p_title, &$p_subtitle, &$d_result, &$d_status)
    {
        global $QF, $FOX;

        $page_node = false;

        if ($info = $QF->Gallery->Get_Album_Info($album))
        {
            $FOX->Link_JScript('swfobject');
            $FOX->Link_JScript('imageshow');
            $p_subtitle = $info['caption'];
            $page_node = $QF->VIS->Create_Node('FOX_GALLERY_PAGE_ALBUM' );

            $rss_url = $FOX->Gen_URL('FoxGal_rss_album', $album, false, true);
            $QF->VIS->Add_Data(0, 'META', '<link rel="alternate" href="'.qf_full_url($rss_url, true).'" type="application/rss+xml" title="'.qf_smart_htmlschars($info['caption']).'" />');
            $rss_url = $QF->Session->AddSID($rss_url);

            $page_params = Array(
                'CAPTION'  => $info['caption'],
                'LEVEL'    => $info['r_level'],
                'MRSS_URL' => ($this->do_cooliris_js) ? $rss_url : null,
                );

            $items = $info['items'];
            foreach ($info['itm_l'] as $id => $lv)
                if (!$QF->User->CheckAccess($lv))
                    unset($items[$id]);
            $items = array_values($items);

            //if (count($items) > 3)
                //$QF->VIS->Add_Data(0, 'HIDE_PANELS', '1');

            $pages = (int) ceil(count($items)/$this->per_page);
            $page = abs($QF->GPC->Get_Num('page', QF_GPC_GET));
            if ($page < 1)
                $page = 1;
            elseif ($page > $pages)
                $page = $pages;

            if ($pages > 1)
            {
                $draw_pages = $FOX->Gen_Pages($pages, $page, Array('AID' => $info['id'], 'ATID' => $info['t_id']));

                $QF->VIS->Add_Node_Array('FOX_GALLERY_ALBUM_PG_BTN', 'PAGE_BTNS', $page_node, $draw_pages);
                $page_params['CUR_PAGE'] = $page;

                $start = $this->per_page*($page - 1);
                $items = array_slice($items, $start, $this->per_page);
            }

            if (count($items))
            {
                $QF->Gallery->Load_ItemInfos($items, true);
                $datas = Array();
                foreach ($items as $id)
                    $datas[] = $QF->Gallery->Get_Item_Info($id);

                list($uids, $tids) = qf_2darray_cols($datas, Array('author_id', 'pt_root'));
                $pt_stats = $QF->PTree->Get_Stats($tids);
                $QF->UList->Query_IDs($uids);
                foreach ($datas as $id => $item)
                {
                    if (($finfo = $QF->Files->Get_FileInfo($item['file_id'])) && $finfo['aspect_ratio'] != 0 && $QF->Files->Get_ImageDims($finfo['id'], $wh, QF_FILES_IDIMS_THUMB))
                    {
                        $item['WIDTH_HEIGHT'] = 'width: '.$wh[0].'px; height: '.$wh[1].'px;';
                        $item['FILENAME'] = $finfo['filename'];
                        $item['PICS_NAME'] = $finfo['pics_name'];
                        $QF->Files->Get_ImageDims($finfo['id'], $whf, $this->do_fullsize_js ? QF_FILES_IDIMS_IMAGE : QF_FILES_IDIMS_PREVIEW);
                        $item['JSPIC_WIDTH'] = $whf[0];
                        $item['JSPIC_HEIGHT'] = $whf[1];
                    }
                    if ($uinfo = $QF->UList->Get_UserInfo($item['author_id']))
                        $item['author'] = $uinfo['nick'];

                    $item['time'] = $QF->LNG->Time_Format($item['time']);
                    $item['SCAPTION'] = $QF->USTR->Str_SmartTrim($item['caption'], 32);
                    $item['T_HEIGHT'] = $this->th_h + 20;
                    $item['JS_FULLSIZE'] = $this->do_fullsize_js ? 1 : null;
                    if ($item['pt_root'] && isset($pt_stats[$item['pt_root']]) && $pt_stats[$item['pt_root']]['posts'])
                    {
                        $item['comments'] = $pt_stats[$item['pt_root']]['posts'];
                        $item['lastcommenttime'] = $pt_stats[$item['pt_root']]['l_time'];
                    }

                    if ($this->do_cooliris_js > 1)
                        $item['mrss_url'] = $rss_url;
                    $items[$id] = $item;
                }

                $QF->VIS->Add_Node_Array('FOX_GALLERY_ALBUM_ITEM', 'ITEMS', $page_node, $items);
                $QF->VIS->Add_Node_Array('FOX_GALLERY_ALBUM_ITEM_JS', 'ITEMS_JSLOAD', $page_node, $items);
            }

            $QF->VIS->Add_Data_Array($page_node, $page_params);
        }
        else
        {
            $d_result = Array(Lang('ERR_GALLERY_ALBUM_NOT_FOUND'), $FOX->Gen_URL('FoxGal_albums'), true);
            $d_status = 404;
        }

        return $page_node;
    }

    function _Page_UAlbum($uid, &$p_title, &$p_subtitle, &$d_result, &$d_status)
    {
        global $QF, $FOX;

        $page_node = false;

        if ($uinfo = $QF->UList->Get_UserInfo($uid))
        {
            $FOX->Link_JScript('swfobject');
            $FOX->Link_JScript('imageshow');
            //$QF->VIS->Add_Data(0, 'HIDE_PANELS', '1');

            $page_node = $QF->VIS->Create_Node('FOX_GALLERY_PAGE_UALBUM' );

            $p_subtitle = sprintf(Lang('FOX_GALLERY_PAGE_USER'), $uinfo['nick']);

            $rss_url = $FOX->Gen_URL('FoxGal_rss_user', $uid, false, true);
            $QF->VIS->Add_Data(0, 'META', '<link rel="alternate" href="'.qf_full_url($rss_url, true).'" type="application/rss+xml" title="'.qf_smart_htmlschars($info['caption']).'" />');
            $rss_url = $QF->Session->AddSID($rss_url);

            $items = $QF->Gallery->Get_Items(QF_GALLERY_SEARCH_USERID, $uid, $QF->User->acc_level);
            $albums = $QF->Gallery->Get_Albums(QF_GALLERY_SEARCH_USERID, $uid, $QF->User->acc_level);

            $page_params = Array(
                'UID'     => $uinfo['uid'],
                'UAVATAR' => $uinfo['avatar'],
                'UAVATAR_WH' => $uinfo['avatar_wh'],
                'UITEMS'  => count($items),
                'UALBUMS' => count($albums),
                'UNICK'   => $uinfo['nick'],
                'MRSS_URL' => ($this->do_cooliris_js) ? $rss_url : null,
                );


            $pages = (int) ceil(count($items)/$this->per_page);
            $page = abs($QF->GPC->Get_Num('page', QF_GPC_GET));
            if ($page < 1)
                $page = 1;
            elseif ($page > $pages)
                $page = $pages;

            if ($pages > 1)
            {
                $draw_pages = $FOX->Gen_Pages($pages, $page, Array('UALBUM' => $uid));

                $QF->VIS->Add_Node_Array('FOX_GALLERY_ALBUM_PG_BTN', 'PAGE_BTNS', $page_node, $draw_pages);
                $page_params['CUR_PAGE'] = $page;

                $start = $this->per_page*($page - 1);
                $items = array_slice($items, $start, $this->per_page);
            }

            if (count($items))
            {
                $QF->Gallery->Load_ItemInfos($items, true);
                foreach ($items as $id)
                {
                    $item = $QF->Gallery->Get_Item_Info($id);
                    if ($QF->User->CheckAccess($item['r_level']))
                      $datas[] = $item;
                }

                $tids = qf_2darray_cols($datas, 'pt_root');
                $pt_stats = $QF->PTree->Get_Stats($tids);
                foreach ($datas as $item)
                {
                    $id = $item['id'];
                    if (($finfo = $QF->Files->Get_FileInfo($item['file_id'])) && $finfo['aspect_ratio'] != 0 && $QF->Files->Get_ImageDims($finfo['id'], $wh, QF_FILES_IDIMS_THUMB))
                    {
                        $item['WIDTH_HEIGHT'] = 'width: '.$wh[0].'px; height: '.$wh[1].'px;';
                        $item['FILENAME'] = $finfo['filename'];
                        $item['PICS_NAME'] = $finfo['pics_name'];
                        $QF->Files->Get_ImageDims($finfo['id'], $whf, $this->do_fullsize_js ? QF_FILES_IDIMS_IMAGE : QF_FILES_IDIMS_PREVIEW);
                        $item['JSPIC_WIDTH'] = $whf[0];
                        $item['JSPIC_HEIGHT'] = $whf[1];
                    }
                    unset($item['author_id']);
                    $item['time'] = $QF->LNG->Time_Format($item['time']);
                    $item['SCAPTION'] = $QF->USTR->Str_SmartTrim($item['caption'], 32);
                    $item['T_HEIGHT'] = $this->th_h + 20;
                    if ($item['pt_root'] && isset($pt_stats[$item['pt_root']]) && $pt_stats[$item['pt_root']]['posts'])
                    {
                        $item['comments'] = $pt_stats[$item['pt_root']]['posts'];
                        $item['lastcommenttime'] = $pt_stats[$item['pt_root']]['l_time'];
                    }
                    $item['JS_FULLSIZE'] = $this->do_fullsize_js ? 1 : null;
                    if ($this->do_cooliris_js > 1)
                        $item['mrss_url'] = $rss_url;
                    $items[$id] = $item;
                }

                $QF->VIS->Add_Node_Array('FOX_GALLERY_ALBUM_ITEM', 'ITEMS', $page_node, $items);
                $QF->VIS->Add_Node_Array('FOX_GALLERY_ALBUM_ITEM_JS', 'ITEMS_JSLOAD', $page_node, $items);
            }

            if (count($albums))
            {                $QF->Gallery->Load_AlbumInfos($albums);
                foreach ($albums as $id)
                {
                    $album = $QF->Gallery->Get_Album_Info($id);
                    unset($album['owner_id']);
                    $album['SCAPTION'] = $QF->USTR->Str_SmartTrim($album['caption'], 32);

                    $albums[$id] = $album;
                }

                $QF->VIS->Add_Node_Array('FOX_GALLERY_UALBUM_ALBUM', 'ALBUMS', $page_node, $albums);

            }

            $QF->VIS->Add_Data_Array($page_node, $page_params);
        }
        else
        {
            $d_result = Array(Lang('ERR_GALLERY_ALBUM_NOT_FOUND'), $FOX->Gen_URL('FoxGal_albums'), true);
            $d_status = 404;
        }

        return $page_node;
    }

    function _Page_New_Item(&$p_title, &$p_subtitle, &$d_result, &$d_status)
    {
        global $QF, $FOX;

        $QF->Run_Module('File_incls');

        $p_subtitle = Lang('FOX_GALLERY_NEW_ITEM');
        $page_node = $QF->VIS->Create_Node('FOX_GALLERY_PAGE_NEWITEM' );
        $page_params = Array(
            );

        if ($QF->Config->Get('allow_levels', 'gallery', false))
            $page_params['MAX_LVL'] = $QF->User->acc_level;


        $temps = $QF->File_incls->Node_MyTemps('file', 'image/', true);
        $QF->VIS->Append_Node($temps, 'MYTEMPS', $page_node);

        $albums = $malbums = Array();

        $galbums = $QF->Gallery->Get_Albums(0, 0, $QF->User->acc_level);

        if (count($galbums))
        {
            $QF->Gallery->Load_AlbumInfos($galbums);
            foreach ($galbums as $id)
            {
                $album = $QF->Gallery->Get_Album_Info($id);
                if ($QF->User->CheckAccess($album['r_level'], $album['w_level'], 0, $album['owner_id']) >= 2)
                {
                  if ($album['owner_id'] == $QF->User->UID)
                      $malbums[] = $album;
                  elseif ($album['owner_id'] == 0)
                      $albums[] = $album;
                }
            }
        }

        $QF->VIS->Add_Data_Array($page_node, $page_params);

        if (count($albums))
            $QF->VIS->Add_Node_Array('FOX_GALLERY_NEWITEM_ALBUM', 'PUBLIC_ALBUMS', $page_node, $albums);
        if (count($malbums))
            $QF->VIS->Add_Node_Array('FOX_GALLERY_NEWITEM_ALBUM', 'MY_ALBUMS', $page_node, $malbums);

        return $page_node;
    }

    function _Page_Edit_Item($item, &$p_title, &$p_subtitle, &$d_result, &$d_status)
    {
        global $QF, $FOX;

        $page_node = false;

        if (($info = $QF->Gallery->Get_Item_Info($item)) && ($finfo = $QF->Files->Get_FileInfo($info['file_id'])))
        {
            if ($QF->User->CheckAccess($info['r_level'], 0, 0, $info['author_id']) < 3)
            {
                $d_result = Array(sprintf(Lang('ERR_GALLERY_ITEM_NOTOWNER'), $info['caption']), $FOX->Gen_URL('FoxGal_item', $item), true);
                $d_status = 403;
                return false;
            }


            $p_subtitle = sprintf(Lang('FOX_GALLERY_EDIT_ITEM_WCAPT'), $info['caption']);
            $page_node = $QF->VIS->Create_Node('FOX_GALLERY_PAGE_EDITITEM' );

            $page_params = Array(
                'ID'       => $info['id'],
                'CAPTION'  => $info['caption'],
                'DESCR'    => $info['description'],
                'SIZE'     => $QF->LNG->Size_Format($finfo['file_size']),
                'TYPE'     => $finfo['mime'],
                'TIME'     => $QF->LNG->Time_Format($info['time']),
                'LEVEL'    => $info['r_level'],
                'DLOADS'   => $finfo['dloads'],
                'FID'      => $finfo['id'],
                'FILENAME' => $finfo['filename'],
                'PICS_NAME' => $finfo['pics_name'],
                'AUTHOR'   => $info['author'],
                'AUTHOR_ID' => $info['author_id'],
                'R_LEVEL'  => $info['r_level'],
                );
            if ($QF->Config->Get('allow_levels', 'gallery', false) || $QF->User->adm_level)
                $page_params['MAX_LVL'] = ($QF->User->UID && $QF->User->UID == $info['author_id']) ? $QF->User->acc_level : $QF->User->mod_level;

            if ($uinfo = $QF->UList->Get_UserInfo($info['author_id']))
            {
                $QF->VIS->Add_Node('USER_INFO_MIN_DIV', 'AUTHOR_INFO', $page_node, $uinfo + Array('HIDE_ACCESS' => 1));
                $page_params['AUTHOR'] = $uinfo['nick'];
            }
            elseif ($info['author'])
                $page_params['AUTHOR_INFO'] = $info['author'];
            else
                $page_params['AUTHOR_INFO'] = Lang('NO_DATA');

            if ($finfo['aspect_ratio'] != 0 && $QF->Files->Get_ImageDims($finfo['id'], $wh, QF_FILES_IDIMS_THUMB))
                $page_params['WIDTH_HEIGHT'] = 'width: '.$wh[0].'px; height: '.$wh[1].'px;';

            $albums = Array();
            if (count($info['albums']))
                foreach ($info['albums'] as $album)
                    if ($QF->User->CheckAccess($album['r_level']))
                      $albums[] = $album;

            $QF->VIS->Add_Data_Array($page_node, $page_params);

            $albums = $ualbums = $malbums = Array();

            $galbums = $QF->Gallery->Get_Albums(0, 0, $QF->User->acc_level);

            $inalbums = (count($info['albums'])) ? array_keys($info['albums']) : array();
            if (count($galbums))
            {
                $QF->Gallery->Load_AlbumInfos($galbums);
                foreach ($galbums as $id)
                {
                    $album = $QF->Gallery->Get_Album_Info($id);
                    if ($QF->User->CheckAccess($album['r_level'], $album['w_level'], 0, $album['owner_id']) >= 2)
                    {
                        if (in_array($album['id'], $inalbums))
                            $album['checked'] = '1';
                        if ($album['owner_id'] == $QF->User->UID)
                            $malbums[] = $album;
                        elseif ($album['owner_id'] == $info['author_id'])
                            $ualbums[] = $album;
                        elseif ($album['owner_id'] == 0)
                            $albums[] = $album;
                    }
                }
            }

            if (count($albums))
                $QF->VIS->Add_Node_Array('FOX_GALLERY_NEWITEM_ALBUM', 'PUBLIC_ALBUMS', $page_node, $albums);
            if (count($malbums))
                $QF->VIS->Add_Node_Array('FOX_GALLERY_NEWITEM_ALBUM', 'MY_ALBUMS', $page_node, $malbums);
            if (count($ualbums))
                $QF->VIS->Add_Node_Array('FOX_GALLERY_NEWITEM_ALBUM', 'US_ALBUMS', $page_node, $ualbums);
        }
        else
        {
            $d_result = Array(Lang('ERR_GALLERY_ITEM_NOT_FOUND'), $FOX->Gen_URL('FoxGal_albums'), true);
            $d_status = 404;
        }

        return $page_node;
    }

    function _Page_New_Album(&$p_title, &$p_subtitle, &$d_result, &$d_status)
    {
        global $QF, $FOX;

        $p_subtitle = Lang('FOX_GALLERY_NEW_ALBUM');
        $page_node = $QF->VIS->Create_Node('FOX_GALLERY_PAGE_NEWALBUM' );
        if ($QF->User->mod_level)
            $QF->VIS->Add_Data($page_node, 'CANPUBLIC', '1');

        return $page_node;
    }

    function _Script_New_Item()
    {
        global $QF, $FOX;

        $new_name = $QF->GPC->Get_String('caption', QF_GPC_POST, QF_STR_LINE);
        $new_desc = $QF->GPC->Get_String('description', QF_GPC_POST);
        $file_id  = $QF->GPC->Get_String('file', QF_GPC_POST, QF_STR_WORD);
        $new_level = $QF->GPC->Get_Num('r_level', QF_GPC_POST);
        $albums   = $QF->GPC->Get_Raw('albums', QF_GPC_POST);

        if ($finfo = $QF->Files->Get_FileInfo($file_id)) {} // do nothing - we've got the file
        elseif ($file_id = $QF->Files->Create_From_Upload())
            $finfo = $QF->Files->Get_FileInfo($file_id);

        if (!$finfo)
            return Array(Lang('ERR_GALLERY_NO_FILE'), $FOX->Gen_URL('FoxGal_new_item'), true);

        if (!$finfo['has_pics'])
            return Array(Lang('ERR_GALLERY_NO_IMAGE'), $FOX->Gen_URL('FoxGal_new_item'), true);

        if ($finfo['author_id'] != $QF->User->UID)
            return Array(sprintf(Lang('ERR_FILES_NOTOWNER'), $finfo['caption']), $FOX->Gen_URL('FoxGal_new_item'), true);

        if ($QF->USTR->Str_Len($new_name) < 3)
            return Array(Lang('ERR_GALLERY_SHORT_CAPT'), $FOX->Gen_URL('FoxGal_new_item'), true);

        $QF->Run_Module('Parser');
        $QF->Parser->Init_Std_Tags();
        $new_desc = $QF->Parser->Parse($new_desc, QF_BBPARSE_CHECK);

        $item_id = $QF->Gallery->Create_Item($new_name, $file_id, Array('description' => $new_desc, 'r_level' => min($QF->User->acc_level, max($new_level, 0))));
        if (!$item_id)
            return Array(Lang('ERR_GALLERY_NEW_DUPLICATES'), $FOX->Gen_URL('FoxGal_new_item'), true);

        $galbums = $QF->Gallery->Get_Albums(0, 0, $QF->User->acc_level);

        if (count($galbums) && is_array($albums) && count($albums))
        {
            $QF->Gallery->Load_AlbumInfos($galbums);
            foreach ($galbums as $id)
            {
                $album = $QF->Gallery->Get_Album_Info($id);
                if (in_array($album['id'], $albums)
                 && $QF->User->CheckAccess($album['r_level'], $album['w_level'], 0, $album['owner_id']) >= 2
                  && ($album['owner_id'] == $QF->User->UID || $album['owner_id'] == 0))
                   $QF->Gallery->Put_To_Album($item_id, $album['id']);
            }
        }

        return Array(Lang('RES_GALLERY_ITEM_CREATED'), $FOX->Gen_URL('FoxGal_item', $item_id));
    }

    function _Script_Edit_Item()
    {
        global $QF, $FOX;

        $item_id  = $QF->GPC->Get_String('item_id', QF_GPC_POST, QF_STR_WORD);
        $new_name = $QF->GPC->Get_String('caption', QF_GPC_POST, QF_STR_LINE);
        $new_desc = $QF->GPC->Get_String('description', QF_GPC_POST);
        $new_level = $QF->GPC->Get_Num('r_level', QF_GPC_POST);
        $albums   = $QF->GPC->Get_Raw('albums', QF_GPC_POST);
        $do_del   = $QF->GPC->Get_Bin('do_delete', QF_GPC_POST);

        if (!($info = $QF->Gallery->Get_Item_Info($item_id)))
            return Array(Lang('ERR_GALLERY_ITEM_NOT_FOUND'), $FOX->Gen_URL('FoxGal_albums'), true);

        if ($QF->User->CheckAccess($info['r_level'], 0, 0, $info['author_id']) < 3)
            return Array(sprintf(Lang('ERR_GALLERY_ITEM_NOTOWNER'), $info['caption']), $FOX->Gen_URL('FoxGal_item', $item_id), true);

        if ($do_del)
        {            if ($QF->Gallery->Drop_Item($item_id, $info['author_id'] != $QF->User->UID))
                return Array(Lang('RES_GALLERY_ITEM_DELETED'), $FOX->Gen_URL('FoxGal_albums'));
        }

        if ($QF->USTR->Str_Len($new_name) < 3)
            return Array(Lang('ERR_GALLERY_SHORT_CAPT'), $FOX->Gen_URL('FoxGal_edit_item', $item_id), true);

        $QF->Run_Module('Parser');
        $QF->Parser->Init_Std_Tags();
        $new_desc = $QF->Parser->Parse($new_desc, QF_BBPARSE_CHECK);

        $max_level = ($QF->User->UID && $QF->User->UID == $info['author_id']) ? $QF->User->acc_level : $QF->User->mod_level;
        if (!$QF->Gallery->Modif_Item($item_id, $new_name, Array('description' => $new_desc, 'r_level' => min($max_level, max($new_level, 0)))))
            return Array(Lang('ERR_GALLERY_CANTMODIF'), $FOX->Gen_URL('FoxGal_edit_item', $item_id), true);

        $galbums = $QF->Gallery->Get_Albums(0, 0, $QF->User->acc_level);
        $inalbums = (count($info['albums'])) ? array_keys($info['albums']) : array();

        if (!is_array($albums))
            $albums = Array();
        if (count($galbums) && (count($albums) || count($inalbums)))
        {
            $QF->Gallery->Load_AlbumInfos($galbums);
            foreach ($galbums as $id)
            {
                $album = $QF->Gallery->Get_Album_Info($id);
                if ($QF->User->CheckAccess($album['r_level'], $album['w_level'], 0, $album['owner_id']) >= 2
                  && ($album['owner_id'] == $QF->User->UID || $album['owner_id'] == $info['author_id'] || $album['owner_id'] == 0))
                  {
                      if (in_array($album['id'], $albums) && !in_array($album['id'], $inalbums))
                          $QF->Gallery->Put_To_Album($item_id, $album['id']);
                      elseif (!in_array($album['id'], $albums) && in_array($album['id'], $inalbums))
                          $QF->Gallery->Drop_From_Album($item_id, $album['id']);
                  }
            }
        }

        return Array(Lang('RES_GALLERY_ITEM_EDITED'), $FOX->Gen_URL('FoxGal_item', $item_id));
    }

    function _Script_New_Album()
    {
        global $QF, $FOX;

        $new_name = $QF->GPC->Get_String('caption', QF_GPC_POST, QF_STR_LINE);
        $new_type = $QF->GPC->Get_Bin('is_public', QF_GPC_POST);

        if ($QF->USTR->Str_Len($new_name) < 3)
            return Array(Lang('ERR_GALLERY_AL_SHORT_CAPT'), $FOX->Gen_URL('FoxGal_new_album'), true);

        if ($new_type && $QF->User->mod_level)
            $t_owner = 0;
        else
            $t_owner = $QF->User->UID;

        $t_id = preg_replace('#\W#', '', strtr($QF->LNG->Translit(($t_owner) ? $new_name.'_'.$QF->User->uname : $new_name), ' ', '_'));


        if ($id = $QF->Gallery->Create_Album($new_name, $t_id, $t_owner))
        {
            $data = $QF->Gallery->Get_Album_Info($id);
            return Array(Lang('RES_GALLERY_AL_CREATED'), $FOX->Gen_URL('FoxGal_album', Array($id, $data['t_id'])));
        }

        return Array(Lang('ERR_GALLERY_AL_DUPLICATES'), $FOX->Gen_URL('FoxGal_new_album'), true);
    }

    function Panel_My_Gallery ($pan_node = false)
    {
        global $QF;

        if (!$pan_node)
            $pan_node = $QF->VIS->Create_Node('PANEL_BODY', false, 'gallery_panel');

        $QF->VIS->Add_Data_Array($pan_node, Array(
            'title' => Lang('FOX_GALLERY_PANEL_CAPT'),
            ) );

        $cont = $QF->VIS->Add_Node('FOX_GALLERY_PANEL', 'contents', $pan_node, Array('UID' => $QF->User->UID));

        return $pan_node;
    }
}

?>
