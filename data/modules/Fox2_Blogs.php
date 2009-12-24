<?php

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if ( defined('FOX_BLOGS_LOADED') )
        die('Scripting error');

define('FOX_BLOGS_LOADED', True);

define('FOX_BLOGS_CACHE_PREFIX', 'FOX_BLOGS.');

class QF_Blogs
{
    function QF_Blogs()
    {

        $QF->Run_Module('PTree');
        $QF->Run_Module('UList');
        $QF->Run_Module('Parser');
        $QF->Parser->Init_Std_Tags();
    }


    function Page_Blogs(&$p_title, &$p_subtitle, &$d_result, &$d_status)
    {

        $QF->VIS->Load_Templates('blogs');
        $mode = $QF->GPC->Get_String('mode', QF_GPC_GET, QF_STR_WORD);
        $id = $QF->GPC->Get_String('id', QF_GPC_GET, QF_STR_WORD);

        $p_title = $QF->LNG->Lang('FOX_BLOGS_CAPTION');

        if ($QF->User->UID)
            $FOX->Draw_Panel('my_blog');

        if (!$mode || $mode == 'index')
            return $this->_Page_Index($p_subtitle, $d_result, $d_status);

        switch ($mode)
        {
                return $this->_Page_Entry($id, $p_subtitle, $d_result, $d_status);
            case 'user':
                return $this->_Page_UserBlog($id, $p_subtitle, $d_result, $d_status);
            case 'edit':
                return $this->_Page_Entry_Edit($id, $p_subtitle, $d_result, $d_status);
            case 'new':
                return $this->_Page_Entry_New($p_subtitle, $d_result, $d_status);
        }

        $QF->HTTP->Redirect($FOX->Gen_URL('FoxBlogs_root'));
    }

    function Script_Blogs()
    {
        global $QF, $FOX;

        $QF->LNG->Load_Language('blogs');

        $mode = $QF->GPC->Get_String('mode', QF_GPC_POST, QF_STR_WORD);
        $id = $QF->GPC->Get_String('id', QF_GPC_POST, QF_STR_WORD);

        if (!$QF->User->UID)
            return Array(Lang('ERR_NOACCESS'), $FOX->Gen_URL('FoxBlogs_root'), true);

        switch ($mode)
        {
            case 'edit':
                return $this->_Script_Entry_Edit($id);
            case 'new':
                return $this->_Script_Entry_New();
        }

        $QF->HTTP->Redirect($FOX->Gen_URL('FoxBlogs_root'));
    }

    function _Page_Index(&$p_subtitle, &$d_result, &$d_status)
    {

        $p_subtitle = $QF->LNG->Lang('FOX_BLOGS_CAPT_RECENT');

        $page_node = $QF->VIS->Create_Node('FOX_BLOGS_INDEXPAGE' );
        $entries = $this->Load_Index();
        $ids = array_keys($entries);

        list($uids, $tids) = qf_2darray_cols($entries, Array('author_id', 'pt_root'));
        $pt_stats = $QF->PTree->Get_Stats($tids);
        $QF->UList->Query_IDs($uids);

        $texts = $this->Load_Texts($ids);

        foreach ($entries as $entry)
        {
            $id = $entry['id'];
            if (isset($pt_stats[$entry['pt_root']]) && $pt_stats[$entry['pt_root']]['posts'])
                $entry['COMMENTS'] = $pt_stats[$entry['pt_root']]['posts'];
            $itm_node = $QF->VIS->Add_Node('FOX_BLOGS_ENTRY', 'ENTRIES', $page_node, $entry);
            if ($uinfo = $QF->UList->Get_UserInfo($entry['author_id']))
                $QF->VIS->Add_Node('USER_INFO_MIN_DIV', 'AUTHOR_INFO', $itm_node, $uinfo + Array('HIDE_ACCESS' => 1));
            if (isset($texts[$id]))
                $QF->VIS->Add_Data($itm_node, 'text', $texts[$id]['p_text']);
        }
        return $page_node;
    }

    function _Page_UserBlog($user_id, &$p_subtitle, &$d_result, &$d_status)
    {
        global $QF;

        $uinfo = $QF->UList->Get_UserInfo($user_id);

        if (!$uinfo)
            $QF->HTTP->Redirect($FOX->Gen_URL('FoxBlogs_root')); // TODO: Error message

        $p_subtitle = sprintf($QF->LNG->Lang('FOX_BLOGS_CAPT_USERBLOG'), $uinfo['nick']);

        $page_node = $QF->VIS->Create_Node('FOX_BLOGS_INDEXPAGE' );
        $entries = $this->Load_Index(1, $user_id);
        $ids = array_keys($entries);

        list($uids, $tids) = qf_2darray_cols($entries, Array('author_id', 'pt_root'));
        $pt_stats = $QF->PTree->Get_Stats($tids);

        $texts = $this->Load_Texts($ids);

        foreach ($entries as $entry)
        {
            $id = $entry['id'];
            if (isset($pt_stats[$entry['pt_root']]) && $pt_stats[$entry['pt_root']]['posts'])
                $entry['COMMENTS'] = $pt_stats[$entry['pt_root']]['posts'];
            $itm_node = $QF->VIS->Add_Node('FOX_BLOGS_ENTRY', 'ENTRIES', $page_node, $entry);

            $QF->VIS->Add_Node('USER_INFO_MIN_DIV', 'AUTHOR_INFO', $itm_node, $uinfo + Array('HIDE_ACCESS' => 1));

            if (isset($texts[$id]))
                $QF->VIS->Add_Data($itm_node, 'text', $texts[$id]['p_text']);
        }
        return $page_node;
    }

    function _Page_Entry($id, &$p_subtitle, &$d_result, &$d_status)
    {
        global $QF;

        $entry = $this->Load_Entry($id);

        if (!$entry)
            $QF->HTTP->Redirect($FOX->Gen_URL('FoxBlogs_root')); // TODO: Error message

        $p_subtitle = $entry['caption'];
        $texts = $this->Load_Texts($id);
        $pt_stats = $QF->PTree->Get_Stats($entry['pt_root']);

        $page_node = $QF->VIS->Create_Node('FOX_BLOGS_ENTRYPAGE' );

        $id = $entry['id'];
        $entry['CAN_MODIFY'] = ($QF->User->CheckAccess($entry['r_level'], 0, 0, $entry['author_id']) >= 3) ? '1' : null;

        $QF->VIS->Add_Data_Array($page_node, $entry + Array('COMMENTS' => $pt_stats['posts']));
        if ($uinfo = $QF->UList->Get_UserInfo($entry['author_id']))
            $QF->VIS->Add_Node('USER_INFO_MIN_DIV', 'AUTHOR_INFO', $page_node, $uinfo + Array('HIDE_ACCESS' => 1));
        if (isset($texts[$id]))
            $QF->VIS->Add_Data($page_node, 'text', $texts[$id]['p_text']);
        if ($entry['pt_root'])
            $QF->VIS->Append_Node($QF->PTree->Render_Tree($entry['pt_root']), 'COMMENTS_PTREE', $page_node);

        return $page_node;
    }

    function _Page_Entry_Edit($id, &$p_subtitle, &$d_result, &$d_status)
    {
        global $QF;

        $entry = $this->Load_Entry($id);

        if (!$entry)
            $QF->HTTP->Redirect($FOX->Gen_URL('FoxBlogs_root')); // TODO: Error message

        $p_subtitle = sprintf(Lang('FOX_BLOGS_CAPT_EDIT'), $entry['caption']);
        $texts = $this->Load_Texts($id);
        $pt_stats = $QF->PTree->Get_Stats($entry['pt_root']);

        $page_node = $QF->VIS->Create_Node('FOX_BLOGS_ENTRYFORM' );

        $id = $entry['id'];

        if ($QF->User->CheckAccess($entry['r_level'], 0, 0, $entry['author_id']) < 3)
            $QF->HTTP->Redirect($FOX->Gen_URL('FoxBlogs_root')); // TODO: Error message

        $QF->VIS->Add_Data_Array($page_node, $entry + Array('COMMENTS' => $pt_stats['posts'], 'DO_EDIT' => '1'));
        if ($uinfo = $QF->UList->Get_UserInfo($entry['author_id']))
            $QF->VIS->Add_Node('USER_INFO_MIN', 'AUTHOR_INFO', $page_node, $uinfo + Array('HIDE_ACCESS' => 1));
        if (isset($texts[$id]))
            $QF->VIS->Add_Data($page_node, 'otext', $texts[$id]['o_text']);

        return $page_node;
    }

    function _Page_Entry_New(&$p_subtitle, &$d_result, &$d_status)
    {
        global $QF;

        if (!$QF->User->UID)
            $QF->HTTP->Redirect($FOX->Gen_URL('FoxBlogs_root')); // TODO: Error message

        $p_subtitle = $QF->LNG->Lang('FOX_BLOGS_CAPT_NEW');
        $pt_stats = $QF->PTree->Get_Stats($entry['pt_root']);

        $page_node = $QF->VIS->Create_Node('FOX_BLOGS_ENTRYFORM' );

        $id = $entry['id'];

        $entry = Array(
            'time' => $QF->Timer->time,
            );

        $QF->VIS->Add_Data_Array($page_node, $entry);
        if ($uinfo = $QF->UList->Get_UserInfo($QF->User->UID))
            $QF->VIS->Add_Node('USER_INFO_MIN', 'AUTHOR_INFO', $page_node, $uinfo + Array('HIDE_ACCESS' => 1));

        return $page_node;
    }

    function _Script_Entry_New()
    {

        $new_capt = $QF->GPC->Get_String('entry_capt', QF_GPC_POST, QF_STR_LINE);
        $new_text = $QF->GPC->Get_String('entry_text', QF_GPC_POST);

        if ($QF->USTR->Str_Len($new_capt) < 3)
            return Array(Lang('ERR_BLOGS_SHORT_CAPT'), $FOX->Gen_URL('FoxBlogs_newentry'), true);

        if ($QF->USTR->Str_Len($new_text) < 20)
            return Array(Lang('ERR_BLOGS_SHORT_TEXT'), $FOX->Gen_URL('FoxBlogs_newentry'), true);

        $id = qf_short_hash($new_capt.' - '.$QF->User->uname.'|'.$QF->Timer->time);;

        $new_text = $QF->Parser->Parse($new_text, QF_BBPARSE_CHECK);
        $new_ptext = $QF->Parser->Parse($new_text, QF_BBPARSE_PREP);
        $data_ins = Array(
            'id' => $id,
            'author' => $QF->User->uname,
            'author_id' => $QF->User->UID,
            'caption' => $new_capt,
            'time' => $QF->Timer->time,
            'r_level' => 0,
            );
        $text_ins = Array(
            'id' => $id,
            'o_text' => $new_text,
            'p_text' => $new_ptext,
            'preparsed' => 1,
            'hash' => md5($new_text),
            );

        if ($QF->DBase->Do_Insert('blog_entries', $data_ins)
            && $QF->DBase->Do_Insert('blog_texts', $text_ins))
        {
                $QF->DBase->Do_Update('blog_entries', Array('pt_root' => $tid), Array('id' => $id));
            $QF->Cache->Drop(FOX_BLOGS_CACHE_PREFIX);
            return Array(Lang('RES_BLOGS_ADDED'), $FOX->Gen_URL('FoxBlogs_entry', $id));
        }

        return Array(Lang('ERR_BLOGS_ERROR'), $FOX->Gen_URL('FoxBlogs_newentry'), true);
    }

    function _Script_Entry_Edit($id)
    {
        global $QF, $FOX;

        $entry = $this->Load_Entry($id);

        if (!$entry)
            $QF->HTTP->Redirect($FOX->Gen_URL('FoxBlogs_root')); // TODO: Error message

        $id = $entry['id'];

        $new_capt = $QF->GPC->Get_String('entry_capt', QF_GPC_POST, QF_STR_LINE);
        $new_text = $QF->GPC->Get_String('entry_text', QF_GPC_POST);

        if ($QF->USTR->Str_Len($new_capt) < 3)
            return Array(Lang('ERR_BLOGS_SHORT_CAPT'), $FOX->Gen_URL('FoxBlogs_editentry', $id), true);

        if ($QF->USTR->Str_Len($new_text) < 20)
            return Array(Lang('ERR_BLOGS_SHORT_TEXT'), $FOX->Gen_URL('FoxBlogs_editentry', $id), true);

        if ($QF->User->CheckAccess($entry['r_level'], 0, 0, $entry['author_id']) < 3)
            return Array(Lang('ERR_BLOGS_NOT_OWNER'), $FOX->Gen_URL('FoxBlogs_entry', $id), true);

        $new_text = $QF->Parser->Parse($new_text, QF_BBPARSE_CHECK);
        $new_ptext = $QF->Parser->Parse($new_text, QF_BBPARSE_PREP);
        $text_upd = Array(
            'o_text' => $new_text,
            'p_text' => $new_ptext,
            'preparsed' => 1,
            'hash' => md5($new_text),
            );

        if ($QF->DBase->Do_Update('blog_entries', Array('caption' => $new_capt), Array('id' => $id)) !== false
            && $QF->DBase->Do_Update('blog_texts', $text_upd, Array('id' => $id)) !== false)
        {
            $QF->Cache->Drop(FOX_BLOGS_CACHE_PREFIX);
            return Array(Lang('RES_BLOGS_EDITED'), $FOX->Gen_URL('FoxBlogs_entry', $id));
        }

        return Array(Lang('ERR_BLOGS_ERROR'), $FOX->Gen_URL('FoxBlogs_entry', $id), true);
    }

    function Panel_My_Blog ($pan_node = false)
    {
        global $QF;

        if (!$pan_node)
            $pan_node = $QF->VIS->Create_Node('PANEL_BODY', false, 'blog_panel');

        $QF->VIS->Add_Data_Array($pan_node, Array(
            'title' => Lang('FOX_BLOGS_PANEL_CAPT'),
            ) );

        $cont = $QF->VIS->Add_Node('FOX_BLOGS_PANEL', 'contents', $pan_node, Array('UID' => $QF->User->UID));

        return $pan_node;
    }

    function Load_Entry($id)
    {
        global $QF;

        return $QF->DBase->Do_Select('blog_entries', '*', Array('id' => $id));
    }

    function Load_Index($filter = 0, $filter_data = null)
    {

        $where = Array();
        $other = Array('order' => Array('time' => 'DESC'));

        if (!is_array($filter_data))
            $filter_data = Array($filter_data);
        switch ($filter)
        {
                $where['author_id'] = $filter_data;
                $cachename = FOX_BLOGS_CACHE_PREFIX.'us-'.md5(implode('|', $filter_data));
                break;

            default:
                $cachename = FOX_BLOGS_CACHE_PREFIX.'index';
        }

        if ($data = $QF->Cache->Get($cachename))
        {
        }
        else if ($data = $QF->DBase->Do_Select_All('blog_entries', '*', $where, $other))
        {
            $QF->Cache->Set($cachename, $data);
            return $data;
        }

        return Array();
    }

    function Load_Texts($ids)
    {

        if (!$ids)
            return false;

        if (!is_array($ids))
            $ids = explode('|', $ids);

        $ids = array_unique($ids);
        sort($ids);

        if (count($ids) > 2)
            $cachename = FOX_BLOGS_CACHE_PREFIX.'Text_Qs.'.md5(implode('|', $ids));

        $got_data = Array();
        if ($cachename && ($datas = $QF->Cache->Get($cachename)))
        {
        }
        else if ($datas = $QF->DBase->Do_Select_All('blog_texts', '*', Array('id' => $ids)))
        {
            {
                if (!$data['preparsed'])
                {
                    $data['p_text'] = $QF->Parser->Parse($data['o_text'], QF_BBPARSE_PREP);
                    $data['preparsed'] = 1;
                    $QF->DBase->Do_Update('blog_texts', Array('p_text' => $data['p_text'], 'preparsed' => $data['preparsed']), Array('id' => $data['id']));
                }

                $got_data[$data['id']] = $data;
            }
            $QF->Cache->Set($cachename, $got_data);
        }

        foreach ($datas as $id => $data)
        {
            $got_data[$id]['p_text'] = $QF->Parser->Parse($data['p_text'], QF_BBPARSE_POSTPREP);
        }
        return $got_data;
    }
}

?>