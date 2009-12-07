<?php

// -------------------------------------------------------------------------- \\
// Post tree system manager - provides interfaces for comments and forums     \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

class Fox2_PTree_Incls
{
    function Fox2_PTree_Incls()
    {        global $QF, $FOX;
        $QF->Run_Module('PTree');
        $QF->LNG->Load_Language('posttree');
    }

    function AJX_getpost(&$AJAX_STATUS)
    {        global $QF, $FOX;
        $pid = $QF->GPC->Get_Num('post_id', QF_GPC_POST);
        $pdata = $QF->PTree->Get_Post($pid);
        return $pdata;
    }

    function Script_PTManage()
    {        global $QF, $FOX;
        $mode = $QF->GPC->Get_String('action', QF_GPC_POST, QF_STR_WORD);
        $my_href = $QF->GPC->Get_String('return', QF_GPC_POST, QF_STR_LINE);
        $tid = $QF->GPC->Get_Num('tree_id', QF_GPC_POST);
        $pid = $QF->GPC->Get_Num('post_id', QF_GPC_POST);
        $do_del = $QF->GPC->Get_Bin('do_delete', QF_GPC_POST);
        $p_text = $QF->GPC->Get_String('post_text', QF_GPC_POST);

        $tdata = $QF->PTree->Get_Tree($tid);
        $pdata = $QF->PTree->Get_Post($pid);
        if (!$tdata)
            return Array(Lang('ERR_PTREE_PTMANAGE_NOTFOUND'), $my_href, true);
        if ($pid && (!$pdata || !isset($tdata['ptree'][$pid]) || $tdata['ptree'][$pid]['deleted']))
            return Array(Lang('ERR_PTREE_PTMANAGE_NOTFOUND'), $my_href, true);

        $t_acc = $QF->User->CheckAccess($tdata['r_level'], $tdata['w_level'], 0, $tdata['author_id']);
        $p_acc = ($pdata) ? $QF->User->CheckAccess($tdata['r_level'], $tdata['w_level'], 0, $pdata['author_id']) : 0;

        if (!$my_href || !qf_str_is_url($my_href))
            $my_href = QF_INDEX;

        switch($mode)
        {
            case 'delpost':
                break;
            case 'editpost':
                $my_href.= '#post'.$pid;
                if ($p_acc < 3)
                    return Array(Lang('RES_PTREE_PTMANAGE_LOWLEVEL'), $my_href, true);
                if ($QF->PTree->Modify_Post($pid, $p_text, Array('deleted' => $do_del ? 1 : 0)))
                    return Array(Lang('RES_PTREE_PTMANAGE_MODDED'), $my_href);
                break;
            default:
                if ($t_acc < 2)
                    return Array(Lang('RES_PTREE_PTMANAGE_LOWLEVEL'), $my_href.'#post'.$pid, true);
                $npid = $QF->PTree->Add_Post($tid, $p_text, $pid);
                $my_href.= '#post'.$npid;
                return Array(Lang('RES_PTREE_PTMANAGE_ADDED'), $my_href);
        }

        return Array(Lang('ERR_PTREE_PTMANAGE_ERROR'), $my_href, true);
    }

    function Page_PTManage(&$p_title, &$p_subtitle, &$d_result)
    {        global $QF, $FOX;

        $QF->Run_Module('UList');

        $mode = $QF->GPC->Get_String('action', QF_GPC_GET, QF_STR_WORD);
        $tid = $QF->GPC->Get_Num('id', QF_GPC_GET);
        $pid = $QF->GPC->Get_Num('pid', QF_GPC_GET);

        $my_href = $QF->GPC->Get_String('return', QF_GPC_GET, QF_STR_LINE);
        $my_href = qf_url_str_unpack($my_href);
        if (!$my_href || !qf_str_is_url($my_href))
            $my_href = QF_INDEX;

        $my_href_enc = qf_url_str_pack($my_href);


        $tdata = $pdata = null;
        // let's load the data
        switch($mode)
        {            case 'delpost':
            case 'editpost':
                $pdata = $QF->PTree->Get_Post($tid);
                if (!$pdata)
                {                    $d_result = Array(Lang('ERR_PTREE_PTMANAGE_NOTFOUND'), $my_href, true);
                    return false;
                }
                $pid = $tid;
                $tid = $pdata['root_id'];
                $tdata = $QF->PTree->Get_Tree($tid);
                break;
            default:
                $mode = 'answer';
                $tdata = $QF->PTree->Get_Tree($tid);
                if (!$tdata)
                {
                    $d_result = Array(Lang('ERR_PTREE_PTMANAGE_NOTFOUND'), $my_href, true);
                    return false;
                }
                if ($pid && isset($tdata['ptree'][$pid]) && !$tdata['ptree'][$pid]['deleted'])
                    $pdata = $QF->PTree->Get_Post($pid);
                if (!$pdata)
                    $pid = 0;
        }
        $t_acc = $QF->User->CheckAccess($tdata['r_level'], $tdata['w_level'], 0, $tdata['author_id']);
        $p_acc = ($pdata) ? $QF->User->CheckAccess($tdata['r_level'], $tdata['w_level'], 0, $pdata['author_id']) : 0;


        $page_node = $QF->VIS->Create_Node('FOX_POSTTREE_MANAGE');
        $post_node = $QF->VIS->Add_Node('FOX_POSTTREE_POST', 'POST_FORM', $page_node);

        $page_info = Array(
            'BACKHREF' => $my_href,
            'BACKHREF_ENC' => $my_href_enc,
            'ACTION' => $mode,
            'POST_ID' => $pid ? $pid : null,
            'TREE_ID' => $tid ? $tid : null,
            );
        $post_info = Array();

        if ($mode == 'delpost')
        {


        }
        elseif ($mode == 'editpost')
        {            $p_title = Lang('FOX2_PTREE_PAGEEDIT');
            if ($p_acc < 3)
            {
                $d_result = Array(Lang('RES_PTREE_PTMANAGE_LOWLEVEL'), $my_href, true);
                return false;
            }
            $post_info = $pdata + Array(
                'PTEXT' => $pdata['p_text'],
                'OTEXT' => $pdata['o_text'],
                'DO_EDIT' => '1',
                'CAN_AMD' => ($t_acc > 2) ? 1 : null,
                );
            if ($uinfo = $QF->UList->Get_UserInfo($pdata['author_id']))
            {
                $QF->VIS->Add_Node('USER_INFO_MIN_DIV', 'AUTHOR_INFO', $post_node, $uinfo /*+ Array('HIDE_ACCESS' => 1)*/);
                $post_info['author'] = $uinfo['nick'];
            }
            elseif ($pdata['author'])
                $post_info['author'] = $pdata['author'];
            else
                $post_info['author'] = Lang('NO_DATA');

        }
        else // answer
        {            $p_title = Lang('FOX2_PTREE_PAGENEW');
            if ($t_acc < 2)
            {
                $d_result = Array(Lang('RES_PTREE_PTMANAGE_LOWLEVEL'), $my_href, true);
                return false;
            }
            if ($pdata)
            {                $opost_node = $QF->VIS->Add_Node('FOX_POSTTREE_POST', 'POSTS', $page_node);
                $opost_info = $pdata + Array('PTEXT' => $pdata['p_text'], 'SHOW_ONLY' => '1');
                if ($uinfo = $QF->UList->Get_UserInfo($pdata['author_id']))
                {
                    $QF->VIS->Add_Node('USER_INFO_MIN_DIV', 'AUTHOR_INFO', $opost_node, $uinfo /*+ Array('HIDE_ACCESS' => 1)*/);
                    $opost_info['author'] = $uinfo['nick'];
                }
                elseif (!$pdata['author'])
                    $opost_info['author'] = Lang('NO_DATA');
                $QF->VIS->Add_Data_Array($opost_node, $opost_info + $page_info);
            }

            $post_info = Array(
                'TIME'    => $QF->Timer->time,
                'DO_NEW' => '1',
                );
            if ($QF->User->UID)
            {                $uinfo = $QF->UList->Get_UserInfo($QF->User->UID);
                $QF->VIS->Add_Node('USER_INFO_MIN_DIV', 'AUTHOR_INFO', $post_node, $uinfo /*+ Array('HIDE_ACCESS' => 1)*/);
                $post_info['AUTHOR'] = $uinfo['nick'];
            }
            else
                $post_info['AUTHOR'] = Lang('US_GUEST');

            $post_info['AUTHOR'] = $uinfo['nick'];
        }

        $QF->VIS->Load_Templates('posttree');

        $QF->VIS->Add_Data_Array($post_node, $post_info + $page_info);
        $QF->VIS->Add_Data_Array($page_node, $page_info);
        return $page_node;
    }
}

?>