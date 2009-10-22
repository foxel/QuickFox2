<?php

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

class Fox2_CMS_Adm
{
    function _Start()
    {        global $QF;
        $QF->Run_Module('CMS');
        $QF->LNG->Load_Language('admin');
        $QF->LNG->Load_Language('cms_spec');
    }

    function AdmP_CMS(&$p_subtitle)
    {        global $QF, $FOX;

        $f_types = Array(
            'bbc'  => Lang('CMS_INFO_TYPE_BBC'),
            'text' => Lang('CMS_INFO_TYPE_TEXT'),
            'html' => Lang('CMS_INFO_TYPE_HTML'),
            );

        $QF->VIS->Load_Templates('cms_spec');
        $QF->Run_Module('UList');

        if (($pg = $QF->GPC->Get_String('info', QF_GPC_GET, QF_STR_WORD)) && $QF->CMS->Load_Page($pg))
        {
            $data = $QF->CMS->Get_Info();
            $cms_page = $QF->VIS->Create_Node('CMS_ADM_INFO_FRAME');
            $p_subtitle = sprintf(Lang('CMS_INFO_FRAME_CAPT'), $data['caption']);
            $PageInfo = Array(
                    'ID' => $data['id'],
                    'CAPTION' => $data['caption'],
                    'IS_SECT' => ($data['is_sect']) ? 1 : null,
                    'FILENAME' => $data['file_name'],
                    'URL_FILENAME' => urlencode($data['file_name']),
                    'MODDATE' => $QF->LNG->Time_Format($data['mod_date']),
                    'LEVEL' => $data['r_level'],
                    'VIEWS' => $data['views'],
                    'TYPE'  => (isset($f_types[$data['file_type']])) ? $f_types[$data['file_type']] : $f_types['html'],
                    'V_BYREF' => $data['v_by_refer'],
                    'LASTVIEW' => (!is_null($data['last_view'])) ? $QF->LNG->Time_Format($data['last_view']) : Lang('NO_DATA'),
                );

            if ($uinfo = $QF->UList->Get_UserInfo($data['author_id']))
                $QF->VIS->Add_Node('USER_INFO_MIN', 'AUTHOR', $cms_page, $uinfo);
            else
                $PageInfo['AUTHOR'] = Lang('NO_DATA');

            $QF->VIS->Add_Data_Array($cms_page, $PageInfo);

            if (($links = $data['links']) && is_array($links))
            {
                $elinks = Array();
                foreach ($links as $link => $capt)
                    $elinks[] = Array('id' => $link, 'caption' => $capt);

                $QF->VIS->Add_Node_Array('CMS_ILINK', 'LINKS', $cms_page, $elinks);
            }

            if ($data['is_sect'])
            {                $elinks = array_values($QF->CMS->Get_List($data['id']));
                if (count($elinks))
                    $QF->VIS->Add_Node_Array('CMS_ILINK', 'SUBS', $cms_page, $elinks);
            }

            if ($data['parent'] && $pinfo = $QF->CMS->Get_List_Item($data['parent']))
                $QF->VIS->Add_Node('CMS_ILINK', 'PARENT', $cms_page, $pinfo);

            return $cms_page;
        }
        elseif (($pg = $QF->GPC->Get_String('edit', QF_GPC_GET, QF_STR_WORD)) && $QF->CMS->Load_Page($pg))
        {
            $data = $QF->CMS->Get_Info();
            $cms_page = $QF->VIS->Create_Node('CMS_ADM_EDIT_FRAME');
            $p_subtitle = sprintf(Lang('CMS_EDIT_FRAME_CAPT'), $data['caption']);
            $PageInfo = Array(
                    'ID' => $data['id'],
                    'CAPTION' => $data['caption'],
                    'IS_SECT' => ($data['is_sect']) ? 1 : null,
                    'FILENAME' => $data['file_name'],
                    'URL_FILENAME' => urlencode($data['file_name']),
                    'LEVEL' => $data['r_level'],
                    'NOLEVEL' => ($data['id'] == 'index') ? '1' : null,
                );

            if ($data['id'] != 'index' && ($pgs = $QF->CMS->Get_Tree()))
            {                $par_variants = Array('' => Array('VAL' => '', 'CAPT' => '---'));
                $skipping = false;
                foreach ($pgs as $pg)
                {
                    if ($skipping >= $pg['t_level'])
                        $skipping = false;

                    if ($pg['id'] == $data['id'])
                        $skipping = $pg['t_level'];

                    if ($skipping === false && $pg['is_section'])
                        $par_variants[$pg['id']] = Array('VAL' => $pg['id'], 'CAPT' => str_repeat('+ ', $pg['t_level']).$pg['caption']);
                }
                unset($pgs);

                $par_variants[$data['parent']]['SEL'] = '1';
                if (count($par_variants) > 1)
                    $QF->VIS->Add_Node_Array('MISC_SELECT_OPTION', 'PARENT_VARS', $cms_page, $par_variants);
            }

            $type_variants = Array(
                0      => Array('VAL' => '', 'CAPT' => Lang('CMS_INFO_TYPE_HTML')),
                'bbc'  => Array('VAL' => 'bbc', 'CAPT' => Lang('CMS_INFO_TYPE_BBC')),
                'text' => Array('VAL' => 'text', 'CAPT' => Lang('CMS_INFO_TYPE_TEXT')),
                );

            if (($f_type = $data['file_type']) && isset($type_variants[$f_type]))
                $type_variants[$f_type]['SEL'] = '1';

            $QF->VIS->Add_Node_Array('MISC_SELECT_OPTION', 'TYPE_VARS', $cms_page, $type_variants);

            $QF->VIS->Add_Data_Array($cms_page, $PageInfo);

            if (($links = $data['links']) && is_array($links))
            {
                $elinks = Array();
                foreach ($links as $link => $capt)
                    $elinks[] = Array('id' => $link, 'caption' => $capt);
                $QF->VIS->Add_Node_Array('CMS_ELINK', 'ELINKS', $cms_page, $elinks);
            }

            if (($recodes = Lang('__CODEPAGES')))
            {
                $recodes = explode(' ', $recodes);
                $cps = Array();
                foreach ($recodes as $cp)
                    $cps[] = Array('val' => $cp, 'capt' => $cp);
                $QF->VIS->Add_Node_Array('MISC_SELECT_OPTION', 'RECODE_VARS', $cms_page, $cps);
            }

            return $cms_page;
        }
        elseif (($pg = $QF->GPC->Get_String('sort', QF_GPC_GET, QF_STR_WORD)) && $QF->CMS->Load_Page($pg))
        {
            $data = $QF->CMS->Get_Info();
            $cms_page = $QF->VIS->Create_Node('CMS_ADM_SORT_FRAME');
            $PageInfo = Array(
                    'ID' => $data['id'],
                    'CAPTION' => $data['caption'],
                    'LEVEL' => $data['r_level'],
                );

            $p_subtitle = sprintf(Lang('CMS_SORT_FRAME_CAPT'), $data['caption']);
            if (!$data['is_sect'])
                $QF->HTTP->Redirect($FOX->Gen_URL('fox2_adm_panel_admp', Array('cms')));

            $elinks = array_values($QF->CMS->Get_List($data['id']));
            if (!count($elinks))
                $QF->HTTP->Redirect($FOX->Gen_URL('fox2_adm_panel_admp', Array('cms')));

            $sortdata = Array();
            $i = 0;
            foreach ($elinks as $pg)
                $sortdata[] = $pg + Array('order_id' => ($i+= 10));

            $QF->VIS->Add_Node_Array('CMS_ADM_SORT_ITEM', 'ROWS_DATA', $cms_page, $sortdata);

            $QF->VIS->Add_Data_Array($cms_page, $PageInfo);

            return $cms_page;
        }
        else
        {
            $lst = $QF->CMS->Get_Tree() + $QF->CMS->Get_List();
            $lst = Array_values($lst);
            $lst_page = $QF->VIS->Create_Node('CMS_ADM_LIST_FRAME');

            $per_page = 15; // items per page
            $pages = (int) ceil(count($lst)/$per_page);
            $page = abs($QF->GPC->Get_Num('page', QF_GPC_GET));
            if ($page < 1)
                $page = 1;
            elseif ($page > $pages)
                $page = $pages;

            if ($pages > 1)
            {
                $draw_pages = $FOX->Gen_Pages($pages, $page);

                $QF->VIS->Add_Node_Array('CMS_ADM_LIST_PBTN', 'PAGE_BTNS', $lst_page, $draw_pages);
                $QF->VIS->Add_Data($lst_page, 'CUR_PAGE', $page);

                $start = $per_page*($page - 1);
                $lst = array_slice($lst, $start, $per_page);

                $plst = Array();
                $tree = $QF->CMS->Get_Tree();
                $id = $lst[0]['parent'];
                while ($id)
                {                    array_unshift($plst, $tree[$id]);
                    $id = $plst[0]['parent'];
                }

                if (count($plst))
                    $QF->VIS->Add_Node_Array('CMS_ADM_LIST_PROW', 'ROWS_DATA', $lst_page, $plst);
            }

            $QF->VIS->Add_Node_Array('CMS_ADM_LIST_ROW', 'ROWS_DATA', $lst_page, $lst);

            return $lst_page;
        }

    }

    function Page_PageEdit(&$p_title, &$p_subtitle, &$d_result)
    {        global $QF, $FOX;

        if (!$QF->User->UID || !$QF->User->adm_level)
        {
            $d_result = array(Lang('ADMPANEL_ERR_NOADMIN'), QF_INDEX, true);
            //header ($QF->HTTP->SERVER["SERVER_PROTOCOL"].' 403 Forbidden');
        }
        elseif (!$QF->User->S_Get('adm_logged'))
        {
            $QF->VIS->Load_Templates('admin');
            $p_title = $QF->LNG->Lang('PAGE_ADMPANEL_CAPT');
            return $QF->VIS->Create_Node('ADM_PAN_LOGIN', Array('URI' => $QF->HTTP->Request) );
        }
        elseif (($pg = $QF->GPC->Get_String('page', QF_GPC_GET, QF_STR_WORD)) && $QF->CMS->Load_Page($pg, true))
        {
            $QF->VIS->Load_Templates('cms_spec');
            $data = $QF->CMS->Get_Data();
            $p_title = $QF->LNG->Lang('CMS_PAGEEDITOR_CAPT');
            $p_subtitle = $data['caption'];
            $cms_pageeditor = $QF->VIS->Create_Node('CMS_PAGEEDITOR', Array(
                'ID' => $data['id'],
                'PAGE_CAPTION' => $data['caption'],
                'PG_CONTENTS' => htmlspecialchars($data['text']),
                'RETURN_TO' => $QF->GPC->Get_String('return_to', QF_GPC_GET, QF_STR_WORD),
                ) );

            $type_variants = Array(
                0      => Array('VAL' => '', 'CAPT' => Lang('CMS_INFO_TYPE_HTML')),
                'bbc'  => Array('VAL' => 'bbc', 'CAPT' => Lang('CMS_INFO_TYPE_BBC')),
                'text' => Array('VAL' => 'text', 'CAPT' => Lang('CMS_INFO_TYPE_TEXT')),
                );

            if (($f_type = $data['file_type']) && isset($type_variants[$f_type]))
                $type_variants[$f_type]['SEL'] = '1';

            $QF->VIS->Add_Node_Array('MISC_SELECT_OPTION', 'TYPE_VARS', $cms_pageeditor, $type_variants);

            return $cms_pageeditor;
        }
        else
        {
            $d_result = Array(Lang('ERR_CMS_PAGE_LOAD'), ($pg) ? QF_INDEX : false, true);
            return false;
        }
    }

    function Script_CMSEdit()
    {
        global $QF, $FOX;

        $pg_id = $QF->GPC->Get_String('cms_id', QF_GPC_POST, QF_STR_WORD);

        $QF->Run_Module('Fox2_adm');

        if ($acc_err = $QF->Fox2_adm->_Scr_Check_Access())
        {
            return $acc_err;
        }
        elseif ($QF->GPC->Get_Bin('force_create', QF_GPC_POST))
        {
            if (!$QF->CMS->Get_List_Item($pg_id))
            {                $filename = QF_CMS_PGS_DIR.$pg_id.'.htf';
                qf_file_put_contents($filename, '<h2>'.$pg_id.'</h2> Empty CMS page.');
                $ins_data = Array(
                    'id'        => $pg_id,
                    'caption'   => $pg_id,
                    'file_id'   => $pg_id,
                    'author_id' => $QF->User->UID,
                    'mod_date'  => $QF->Timer->time,
                    );

                $QF->Cache->Drop(QF_CMS_TREE_CACHENAME);

                if ($QF->DBase->Do_Insert('cms_pgs', $ins_data))
                    return Array($QF->LNG->lang('CMS_EDIT_CREATED'), $FOX->Gen_URL('fox2_cms_edit_page', Array($pg_id)));
                else
                    return Array($QF->LNG->lang('ERR_CMS_CREATE_ERROR'), $FOX->Gen_URL('fox2_adm_panel_admp', Array('cms')), true);
            }
            else
                return Array($QF->LNG->lang('ERR_CMS_CREATE_DUPL'), $FOX->Gen_URL('fox2_adm_panel_admp', Array('cms')), true);

        }
        elseif ($QF->GPC->Get_Bin('force_delete', QF_GPC_POST))
        {
            $got_pgs = $QF->GPC->Get_Raw('del_cms', QF_GPC_POST);
            if (is_array($got_pgs) && count($got_pgs))
            {
                $to_del = $QF->DBase->Do_Select_All('cms_pgs', Array('id', 'file_id'), Array('id' => $got_pgs));
                $dels = Array();
                foreach ($to_del as $data)
                {                    if ($data['id'] == QF_CMS_INDEXPAGE) // we can't drop index
                        continue;

                    $filename = QF_CMS_PGS_DIR.$data['file_id'].'.htf';
                    if (unlink($filename))
                    {
                        $dels[] = $data['id'];
                        $cachename = QF_CMS_CACHE_PREFIX.$pg_id;
                        $QF->Cache->Drop($cachename);
                        $QF->Cache->Drop(QF_CMS_TREE_CACHENAME);
                    }

                }

                if ($QF->DBase->Do_Delete('cms_pgs', Array('id' => $dels)))
                {
                    $QF->DBase->Do_Delete('cms_stats', Array('id' => $dels));
                    $QF->DBase->Do_Update('cms_pgs', Array('parent' => ''), Array('parent' => $dels));
                    return Array($QF->LNG->lang('CMS_EDIT_DELETED'), $FOX->Gen_URL('fox2_adm_panel_admp', Array('cms')));
                }
                else
                    return Array($QF->LNG->lang('ERR_CMS_DELETE_ERROR'), $FOX->Gen_URL('fox2_adm_panel_admp', Array('cms')), true);
            }
            else
                return Array($QF->LNG->lang('ERR_CMS_DELETE_NONE'), $FOX->Gen_URL('fox2_adm_panel_admp', Array('cms')), true);
        }
        elseif ($QF->GPC->Get_Bin('force_sort', QF_GPC_POST))
        {
            $got_pgs = $QF->GPC->Get_Raw('cms_order', QF_GPC_POST);
            $to_sort = $QF->DBase->Do_Select_All('cms_pgs', 'id', Array('id' => array_keys($got_pgs), 'parent' => $pg_id));

            if (is_array($got_pgs) && count($got_pgs) && count($to_sort))
            {
                asort($got_pgs);

                $order = 1;
                foreach ($got_pgs as $id => $val)
                    if (in_array($id, $to_sort))
                        $QF->DBase->Do_Update('cms_pgs', Array('order_id' => $order++), Array('id' => $id));

                $QF->Cache->Drop(QF_CMS_TREE_CACHENAME);
                return Array($QF->LNG->lang('CMS_EDIT_SORTED'), $FOX->Gen_URL('fox2_cms_info_page', Array($pg_id)));
            }
            else
                return Array($QF->LNG->lang('ERR_CMS_SORT_NONE'), $FOX->Gen_URL('fox2_adm_panel_admp', Array('cms')), true);
        }
        elseif ($pg_id && $QF->CMS->Load_Page($pg_id))
        {
            $odata = $QF->CMS->Get_Info();

            $new_type = $QF->GPC->Get_String('cms_type', QF_GPC_POST, QF_STR_WORD);
            switch ($new_type)
            {
                case 'bbc':
                case 'text':
                case '':
                    $new_type = $new_type;
                    break;
                default:
                    $new_type = $odata['file_type'];
            }

            if ($QF->GPC->Get_Bin('force_editpage', QF_GPC_POST)) // editing contents
            {
                $ret_to_page = ($QF->GPC->Get_String('return_to', QF_GPC_POST, QF_STR_WORD) == 'page');

                $pedit_url = ($ret_to_page) ? 'fox2_cms_pedit_page' : 'fox2_cms_pedit_page_ret';
                $pview_url = ($ret_to_page) ? 'fox2_cms_page' : 'fox2_cms_info_page';

                if ($filedata = $QF->GPC->Get_String('contents', QF_GPC_POST)) // let's get contents
                {
                    $QF->Run_Module('Parser');
                    $QF->Parser->Init_Std_Tags();
                    switch ($new_type)
                    {
                        case 'bbc':
                            $filedata = $QF->Parser->Parse($filedata, QF_BBPARSE_CHECK);
                            break;
                        case 'text':
                            break;
                        case '':
                        case 'html':
                            $filedata = $QF->Parser->XML_Check($filedata, true);
                            if (preg_match('#<body>(.*)</body>#s', $filedata, $subdata))
                                $filedata = $subdata[1];
                            break;
                    }

                    $filename = $odata['file_name'];

                    if (qf_file_put_contents($filename, $filedata))
                    {
                        $upd_data = Array(
                            'file_type' => $new_type,
                            'mod_date'  => $QF->Timer->time,
                            );

                        if ($QF->DBase->Do_Update('cms_pgs', $upd_data, Array('id' => $pg_id)))
                        {
                            $cachename = QF_CMS_CACHE_PREFIX.$pg_id;
                            $QF->Cache->Drop($cachename);

                            return Array($QF->LNG->lang('CMS_EDIT_PG_EDITED'), $FOX->Gen_URL($pview_url, Array($pg_id)));
                        }
                        else
                            return Array($QF->LNG->lang('ERR_CMS_EDIT_ERROR'), $FOX->Gen_URL($pedit_url, Array($pg_id)), true);
                    }
                    else
                        return Array($QF->LNG->lang('ERR_CMS_EDIT_ERROR'), $FOX->Gen_URL($pedit_url, Array($pg_id)), true);
                }
                else
                    return Array($QF->LNG->lang('ERR_CMS_EDIT_NOCONT'), $FOX->Gen_URL($pedit_url, Array($pg_id)), true);

            }
            elseif ($QF->GPC->Get_Bin('force_upload', QF_GPC_POST)) // uploading a new file
            {                if (!($file = $QF->GPC->Get_File('cms_file')))
                    return Array($QF->LNG->lang('ERR_CMS_UPLOAD_NOFILE'), $FOX->Gen_URL('fox2_cms_edit_page', Array($pg_id)), true);
                elseif (!$file['error'] && ($filedata = qf_file_get_contents($file['tmp_name']))) // it's OK now
                {
                    if ($recode_from = $QF->GPC->Get_String('cms_file_recode', QF_GPC_POST, QF_STR_WORD))
                        $filedata = $QF->USTR->Str_Convert($filedata, QF_INTERNAL_ENCODING, $recode_from);

                    $QF->Run_Module('Parser');
                    switch ($new_type)
                    {
                        case 'bbc':
                            $filedata = $QF->Parser->Parse($filedata, QF_BBPARSE_CHECK);
                            break;
                        case 'text':
                            break;
                        case '':
                        case 'html':
                            $filedata = $QF->Parser->XML_Check($filedata, true);
                            if (preg_match('#<body>(.*)</body>#s', $filedata, $subdata))
                                $filedata = $subdata[1];
                            break;
                    }

                    $filename = $odata['file_name'];

                    if (qf_file_put_contents($filename, $filedata))
                    {                        $upd_data = Array(
                            'file_type' => $new_type,
                            'mod_date'  => $QF->Timer->time,
                            );

                        if ($QF->DBase->Do_Update('cms_pgs', $upd_data, Array('id' => $pg_id)))
                        {
                            $cachename = QF_CMS_CACHE_PREFIX.$pg_id;
                            $QF->Cache->Drop($cachename);

                            return Array($QF->LNG->lang('CMS_EDIT_UPLOADED'), $FOX->Gen_URL('fox2_cms_info_page', Array($pg_id)));
                        }
                        else
                            return Array($QF->LNG->lang('ERR_CMS_EDIT_ERROR'), $FOX->Gen_URL('fox2_cms_edit_page', Array($pg_id)), true);
                    }
                    else
                        return Array($QF->LNG->lang('ERR_CMS_UPLOAD_ERROR'), $FOX->Gen_URL('fox2_cms_edit_page', Array($pg_id)), true);
                }
                else
                    return Array($QF->LNG->lang('ERR_CMS_UPLOAD_ERROR'), $FOX->Gen_URL('fox2_cms_edit_page', Array($pg_id)), true);

            }
            else // reconfigs only
            {                $new_capt = $QF->GPC->Get_String('cms_capt', QF_GPC_POST, QF_STR_LINE);
                $mylen = $QF->USTR->Str_Len($new_capt);
                if ($mylen > 255)
                    $new_capt = $QF->USTR->Str_Substr($new_capt, 0, 255);
                elseif ($mylen < 3)
                    $new_capt = $odata['caption'];

                $par_variants = Array('');
                $new_parent = $QF->GPC->Get_String('cms_parent', QF_GPC_POST, QF_STR_WORD);
                if ($data['id'] != 'index' && ($pgs = $QF->CMS->Get_Tree()))
                {
                    $skipping = false;
                    foreach ($pgs as $pg)
                    {
                        if ($skipping >= $pg['t_level'])
                            $skipping = false;

                        if ($pg['id'] == $data['id'])
                            $skipping = $pg['t_level'];

                        if ($skipping === false && $pg['is_section'])
                            $par_variants[] = $pg['id'];
                    }
                    unset($pgs);
                }
                if (!in_array($new_parent, $par_variants))
                    $new_parent = $odata['parent'];

                $new_issect = $QF->GPC->Get_Bin('cms_issect', QF_GPC_POST);

                $new_level = ($data['id'] != 'index') ? $QF->GPC->Get_Num('cms_acc_level', QF_GPC_POST) : 0;
                if ($new_level < 0 || $new_level > QF_FOX2_MAXULEVEL)
                    $new_level = $odata['r_level'];

                $got_links = $QF->GPC->Get_Raw('cms_links', QF_GPC_POST);
                if (is_array($got_links))
                    $got_links = array_merge(array_values($got_links), explode(' ', $QF->GPC->Get_String('cms_add_links', QF_GPC_POST, QF_STR_LINE)));
                else
                    $got_links = explode(' ', $QF->GPC->Get_String('cms_add_links', QF_GPC_POST, QF_STR_LINE));
                $got_links = array_unique($got_links);
                $new_links = Array();
                $pg_list = $QF->CMS->Get_List();
                foreach($pg_list as $lst_itm)
                    if (in_array($lst_itm['id'], $got_links) && ($lst_itm['id'] != $pg_id))
                        $new_links[] = $lst_itm['id'];
                $new_links = implode('|', $new_links);

                $upd_data = Array(
                    'caption'    => $new_capt,
                    'is_section' => ($new_issect) ? 1 : 0,
                    'parent'     => $new_parent,
                    'file_type'  => $new_type,
                    'links_to'   => $new_links,
                    'r_level'    => $new_level,
                    'mod_date'   => $QF->Timer->time,
                    );

                if ($QF->DBase->Do_Update('cms_pgs', $upd_data, Array('id' => $pg_id)))
                {                    if ($QF->GPC->Get_Bin('cms_reset', QF_GPC_POST))
                        $QF->DBase->Do_Delete('csm_stats', Array('id' => $pg_id));

                    $cachename = QF_CMS_CACHE_PREFIX.$pg_id;
                    $QF->Cache->Drop($cachename);
                    $QF->Cache->Drop(QF_CMS_TREE_CACHENAME);

                    return Array($QF->LNG->lang('CMS_EDIT_EDITED'), $FOX->Gen_URL('fox2_cms_info_page', Array($pg_id)));
                }
                else
                    return Array($QF->LNG->lang('ERR_CMS_EDIT_ERROR'), $FOX->Gen_URL('fox2_cms_edit_page', Array($pg_id)), true);
            }
        }
        else
            return Array($QF->LNG->lang('ERR_CMS_PAGE_LOAD'), $FOX->Gen_URL('fox2_adm_panel_admp', Array('cms')), true);
    }
}
?>
