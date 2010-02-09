<?php

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

define('QF_VIEWARCH_CACHEPREFIX', 'TARS.');

class Fox2_file_incls
{
    var $th_w = 64;
    var $th_h = 64;
    var $prv_w = 256;
    var $prv_h = 256;
    var $per_page = 20;

    function _Start()
    {
        global $QF;
        $QF->Run_Module('Files');
        list($this->th_w,  $this->th_h)  = explode('|', $QF->Config->Get('thb_size', 'files_cfg', '96|96'));
        list($this->prv_w, $this->prv_h) = explode('|', $QF->Config->Get('prv_size', 'files_cfg', '256|256'));
    }

    function AJX_Deleter(&$AJAX_STATUS)
    {        global $QF, $FOX;

        if (!$QF->User->UID || ($QF->User->acc_level < $QF->Config->Get('upl_minlvl', 'files_cfg', 1)))
        {
            $AJAX_STATUS = 403;
            return Lang('FILES_UPL_DISALLOW');
        }

        $error = '';
        $selected = $QF->GPC->Get_Raw('sel_files', QF_GPC_POST);
        if ($sel_one || !is_array($selected))
            $selected = Array($fid);

        $conts = Array();
        $valname = $QF->GPC->Get_String('valname', QF_GPC_POST, QF_STR_LINE);
        $sel_one = $QF->GPC->Get_Num('sel_one', QF_GPC_POST);
        $mimes   = $QF->GPC->Get_String('mimes', QF_GPC_POST, QF_STR_LINE);
        $mime_cls = explode('|', $mimes);
        $mime_cls = (count($mime_cls) > 1) ? $mime_cls : $mimes;

        if (($conts = $QF->Files->Get_TempFiles($QF->User->UID, $mime_cls)) && ($dconts = array_intersect($conts, $selected)))
        {
            foreach($dconts as $file_id)
                $QF->Files->Drop_File($file_id);
        }
        else
            $error = sprintf(Lang('ERR_FILES_UPL_DELNONE'), '');


        if (!$error)
        {
            $QF->Run_Module('VIS');
            $QF->VIS->Load_Templates();

            $QF->Files->Load_FileInfos($conts);
            if (!$valname)
                $valname = 'files';
            $node  = $this->Node_MyTemps($valname, $mime_cls, $sel_one, null, true);

            $data = $QF->VIS->Parse($node);
            $FOX->HTML_FullURLs($data);
            //$QF->Events->Call_Event_Ref('HTML_block_parse', $data );
            return $data;
        }
        else
        {
            $AJAX_STATUS = 400;
            return $error;
        }
    }

    function AJX_Uploader(&$AJAX_STATUS)
    {
        global $QF, $FOX;

        if (!$QF->User->UID || ($QF->User->acc_level < $QF->Config->Get('upl_minlvl', 'files_cfg', 1)))
        {
            $AJAX_STATUS = 403;
            return Lang('FILES_UPL_DISALLOW');
        }

        $error = '';
        if (($file = $QF->GPC->Get_File('upl_file')) || ($file = $QF->GPC->Get_File('Filedata')))
        {
            $real_file = $file['tmp_name'];

            $filename = $file['name'];
            $capt = trim(preg_replace('#(?<=\S)\.\w+$#', '', strtr($filename, '_', ' ')));

            $data = Array(
                'author' => $QF->User->uname, 'author_id' => $QF->User->UID,
                'filename' => $filename, 'caption' => $capt,
                );

            if ($file['error'])
                $error = sprintf($QF->LNG->Lang('ERR_FILES_UPL_SERVER'), $file['name'], $QF->LNG->Lang('ERR_FILES_UPL_SERVER_'.$file['error']));
            elseif (!($fid = $QF->Files->Create_File($real_file, $data)))
                $error = sprintf($QF->LNG->Lang('ERR_FILES_UPL_ERROR'), $file['name'], implode(', ', $FOX->Describe_ErrCodes()));
        }
        else
            $error = sprintf(Lang('ERR_FILES_UPL_GOTNONE'), '');


        if (!$error)
        {
            $QF->Run_Module('VIS');
            $QF->VIS->Load_Templates();

            $valname = $QF->GPC->Get_String('valname', QF_GPC_POST, QF_STR_LINE);
            $sel_one = $QF->GPC->Get_Num('sel_one', QF_GPC_POST);
            $mimes   = $QF->GPC->Get_String('mimes', QF_GPC_POST, QF_STR_LINE);
            $mime_cls = explode('|', $mimes);
            $mime_cls = (count($mime_cls) > 1) ? $mime_cls : $mimes;
            $selected = $QF->GPC->Get_Raw('sel_files', QF_GPC_POST);
            if ($sel_one || !is_array($selected))
                $selected = Array($fid);

            $conts = $QF->Files->Get_TempFiles($QF->User->UID, $mime_cls);

            if (!in_array($fid, $conts))
            {
                $AJAX_STATUS = 400;
                return sprintf($QF->LNG->Lang('ERR_FILES_UPL_FILTERED'), $file['name']);
            }

            $QF->Files->Load_FileInfos($conts);
            if (!$valname)
                $valname = 'files';
            $node  = $this->Node_MyTemps($valname, $mime_cls, $sel_one, $selected, true);

            $data = $QF->VIS->Parse($node);
            $FOX->HTML_FullURLs($data);
            //$QF->Events->Call_Event_Ref('HTML_block_parse', $data );
            return $data;

        }
        else
        {
            $AJAX_STATUS = 400;
            return $error;
        }

    }

    function Node_MyTemps($valname = 'files', $mime_cls = false, $sel_one = false, $selected = null, $for_ajax = false)
    {
        global $QF, $FOX;

        if (!$for_ajax)
        {            $FOX->Link_JScript('ajax');
            $FOX->Link_JScript('swfobject');
            $FOX->Link_JScript('uploader');
        }

        $node  = $QF->VIS->Create_Node(($for_ajax) ? 'FILES_MYTEMPS_AJX' : 'FILES_MYTEMPS', Array(
            'RET_TO'  => base64_encode($QF->HTTP->Request),
            'VALNAME' => $valname,
            'SEL_ONE' => ($sel_one) ? 1 : null,
            'MIMES'   => (is_array($mime_cls)) ? implode('|', $mime_cls) : $mime_cls,
            'MY_SID'  => $QF->Session->SID,
            ) );
        $conts = $QF->Files->Get_TempFiles($QF->User->UID, $mime_cls);

        if (!is_array($selected))
            $selected = Array($selected);
        elseif ($sel_one && (count($selected) > 1))
            $selected = Array(current($selected));

        $QF->Files->Load_FileInfos($conts);
        if (!$valname)
            $valname = 'files';
        foreach ($conts as $cont_id)
        {
            $cont_item = $QF->Files->Get_FileInfo($cont_id);

            $file_info = Array(
                'FID'      => $cont_item['id'],
                'CAPTION'  => $QF->USTR->Str_SmartTrim($cont_item['caption'], 32),
                'FILENAME' => $cont_item['filename'],
                'SIZE'     => $QF->LNG->Size_Format($cont_item['file_size']),
                'TYPE'     => $cont_item['mime'],
                'TIME'     => $QF->LNG->Time_Format($cont_item['time']),
                'LEVEL'    => $cont_item['r_level'],
                'DLOADS'   => $cont_item['dloads'],
                'IS_ARCH'  => ($cont_item['is_arch']) ? 1 : null,
                'SHOW_THUMB' => ($cont_item['has_pics']) ? 1 : null,
                'PICS_NAME' => $cont_item['pics_name'],
                'VAL_NAME' => $valname,
                'SEL_ONE'  => ($sel_one) ? 1 : null,
                'CHECKED'  => (in_array($cont_item['id'], $selected)) ? 1 : null,
                );

            if ($cont_item['aspect_ratio'] != 0 && $QF->Files->Get_ImageDims($cont_id, $wh, QF_FILES_IDIMS_THUMB) )
                $file_info['WIDTH_HEIGHT'] = 'width: '.$wh[0].'px; height: '.$wh[1].'px;';

            $QF->VIS->Add_Node('FILES_MYTEMPS_ITEM', 'ITEMS', $node, $file_info);
        }

        return $node;
    }

    function Page_Uploader(&$p_title, &$p_subtitle, &$d_result)
    {
        global $QF, $FOX;

        $ret_to = $QF->GPC->Get_String('return', QF_GPC_GET);
        $back_url = base64_decode($ret_to);
        if (!qf_str_is_url($back_url))
            $ret_to = base64_encode($back_url = $FOX->Gen_URL('fox2_files_viewrootdir'));

        $p_title=$QF->LNG->Lang('FILES_UPLOADER');

        $upl_page = $QF->VIS->Create_Node('FILES_UPLOADER', Array(
            'FILDS_COUNT' => 3,
            'RET_URL' => $back_url,
            'RET_TO' => $ret_to ) );

        return $upl_page;
    }

    function Script_ModifFile()
    {
        global $QF, $FOX;

        $file_id = $QF->GPC->Get_String('fid', QF_GPC_POST, QF_STR_HEX);
        if (!$file_id)
        {
            $QF->HTTP->Redirect($FOX->Gen_URL('fox2_files_viewrootdir'));
        }
        elseif (!($info = $QF->Files->Get_FileInfo($file_id)))
        {
            return Array(Lang('ERR_FILE_NOT_FOUND'), $FOX->Gen_URL('fox2_files_viewrootdir'), true);
        }
        elseif ($QF->User->CheckAccess($info['r_level'], 0, 0, $info['author_id']) < 3)
        {
            return Array(sprintf(Lang('ERR_FILES_NOTOWNER'), $info['caption']), $FOX->Gen_URL('fox2_files_viewrootdir'), true);
        }
        else
        {            if ($QF->GPC->Get_Bin('do_delete', QF_GPC_POST))
            {
                if ($QF->Files->Drop_File($file_id))
                    return Array(Lang('RES_FILE_DELETE_OK'), $FOX->Gen_URL('fox2_files_openfolder', $info['folder']));
            }

            $upd = Array(
                'caption'  => $QF->GPC->Get_String('set_capt', QF_GPC_POST, QF_STR_LINE),
                'filename' => $QF->GPC->Get_String('set_fname', QF_GPC_POST, QF_STR_LINE),
                'r_level'  => $QF->GPC->Get_Num('set_acc', QF_GPC_POST),
                );
            $to_folder = $QF->GPC->Get_Num('set_folder', QF_GPC_POST);
            if (!$info['is_temp'] && ($finfo = $QF->Files->Get_FolderInfo($info['folder'])) && $finfo['is_sys'])
                $to_folder = -1;

            $max_lvl = ($QF->User->UID && $QF->User->UID == $info['author_id']) ? max($QF->User->acc_level, $QF->User->mod_level) : $QF->User->mod_level;
            if ($upd['r_level'] < 0 || $upd['r_level'] > $max_lvl)
                unset($upd['r_level']);

            $new_file = false;
            if ($file = $QF->GPC->Get_File('new_file'))
            {
                $new_file = $file['tmp_name'];
                $upd['filename'] = $file['name'];

                if ($file['error'])
                    return Array(sprintf($QF->LNG->Lang('ERR_FILES_UPL_SERVER'), $file['name'], $file['error']), $FOX->Gen_URL('fox2_file_fileinfo', $file_id), true);
            }

            if ($QF->Files->Modif_File($file_id, $upd, $new_file))
            {
                if ($to_folder >= 0
                    && ($fold_info = $QF->Files->Get_FolderInfo($to_folder))
                    && !$fold_info['is_sys']
                    && ($QF->User->CheckAccess($fold_info['r_level'], $fold_info['w_level']) >= 2))
                    $QF->Files->Move_File($file_id, $to_folder);

                return Array(Lang('RES_FILE_MODIF_OK'), $FOX->Gen_URL('fox2_file_fileinfo', $file_id));
            }
        }

        return Array(Lang('ERR_FILE_MODIF_ERR'), $FOX->Gen_URL('fox2_file_fileinfo', $file_id), true);
    }

    function Script_FolderCreate()
    {
        global $QF, $FOX;
        $to_folder = $QF->GPC->Get_Num('to_folder', QF_GPC_POST);
        if (!($fold_info = $QF->Files->Get_FolderInfo($to_folder)))
            $QF->HTTP->Redirect($FOX->Gen_URL('fox2_files_viewrootdir'));

        if ($fold_info['is_sys'] && ($QF->User->CheckAccess($fold_info['r_level'], $fold_info['w_level'], $fold_info['acc_gr']) < 2))
            return Array(Lang('ERR_FOLDER_NOACCESS'), $FOX->Gen_URL('fox2_files_openfolder', ($fold_info['t_id']) ? $fold_info['t_id'] : $fold_info['id']), true);

        $caption = $QF->GPC->Get_String('caption', QF_GPC_POST, QF_STR_LINE);
        if (!$caption)
            return Array(Lang('ERR_FILES_FCREATE_NONAME'), $FOX->Gen_URL('fox2_files_openfolder', ($fold_info['t_id']) ? $fold_info['t_id'] : $fold_info['id']), true);


        $t_id = preg_replace('#\W+#', '', $QF->GPC->Get_String('t_id', QF_GPC_POST, QF_STR_LINE));
        if (!$t_id)
            $t_id = preg_replace('#\W#', '', strtr($QF->LNG->Translit($caption), ' ', '_'));
        $t_id = preg_replace('#^sys_#', 'f_', strtolower($t_id));


        $params = Array(
            'r_level'  => max(min($QF->GPC->Get_Num('r_level', QF_GPC_POST), $QF->User->mod_level), 0),
            'w_level'  => max(min($QF->GPC->Get_Num('w_level', QF_GPC_POST), $QF->User->mod_level), 1),
            );

        if ($nid = $QF->Files->Create_Folder($caption, $to_folder, $t_id, $params))
            return Array(sprintf(Lang('RES_FILES_FCREATED_OK'), $caption), $FOX->Gen_URL('fox2_files_openfolder', ($t_id) ? $t_id : $nid));

        return Array($QF->LNG->Lang('ERR_FILES_FCREATE_ERROR'), $FOX->Gen_URL('fox2_files_openfolder', ($fold_info['t_id']) ? $fold_info['t_id'] : $fold_info['id']), true);
    }

    function Script_FolderStore()
    {
        global $QF, $FOX;
        $to_folder = $QF->GPC->Get_Num('to_folder', QF_GPC_POST);
        if (!($fold_info = $QF->Files->Get_FolderInfo($to_folder)))
            $QF->HTTP->Redirect($FOX->Gen_URL('fox2_files_viewrootdir'));

        $move_files = $QF->GPC->Get_Raw('files', QF_GPC_POST);

        if ($fold_info['is_sys'] && ($QF->User->CheckAccess($fold_info['r_level'], $fold_info['w_level'], $fold_info['acc_gr']) < 2))
            return Array(Lang('ERR_FOLDER_NOACCESS'), $FOX->Gen_URL('fox2_files_openfolder', $to_folder), true);


        $QF->Files->Load_FileInfos($move_files);
        if (is_array($move_files))
        foreach ($move_files as $fid)
        {
            $finfo = $QF->Files->Get_FileInfo($fid);
            if ($QF->User->CheckAccess($finfo['r_level'], 0, 0, $finfo['author_id']) < 3)
                return Array(sprintf(Lang('ERR_FILES_NOTOWNER'), $finfo['caption']), $FOX->Gen_URL('fox2_files_openfolder', ($fold_info['t_id']) ? $fold_info['t_id'] : $fold_info['id']), true);

            $QF->Files->Move_File($fid, $to_folder);
        }
        if ($file = $QF->GPC->Get_File('upl_file'))
        {
            $real_file = $file['tmp_name'];
            $filename = $file['name'];
            $capt = trim(preg_replace('#(?<=\S)\.\w+$#', '', strtr($filename, '_', ' ')));

            $data = Array(
                'author' => $QF->User->uname, 'author_id' => $QF->User->UID,
                'filename' => $filename, 'caption' => $capt,
                );

            if ($file['error'])
                return Array(sprintf($QF->LNG->Lang('ERR_FILES_UPL_SERVER'), $file['name'], $QF->LNG->Lang('ERR_FILES_UPL_SERVER_'.$file['error'])), $FOX->Gen_URL('fox2_files_openfolder', ($fold_info['t_id']) ? $fold_info['t_id'] : $fold_info['id']), true);
            elseif (!($fid = $QF->Files->Create_File($real_file, $data)))
                return Array(sprintf($QF->LNG->Lang('ERR_FILES_UPL_ERROR'), $file['name'], ''), $FOX->Gen_URL('fox2_files_openfolder', ($fold_info['t_id']) ? $fold_info['t_id'] : $fold_info['id']), true);

            $QF->Files->Move_File($fid, $to_folder);
        }

        return Array(sprintf(Lang('RES_FILES_STORED_OK'), $fold_info['name']), $FOX->Gen_URL('fox2_files_openfolder', ($fold_info['t_id']) ? $fold_info['t_id'] : $fold_info['id']));
    }

    function Script_Uploader()
    {
        global $QF, $FOX;

        $errors = Array();
        $got_files = 0;

        $ret_to = $QF->GPC->Get_String('return', QF_GPC_POST);
        $back_url = base64_decode($ret_to);
        if (!qf_str_is_url($back_url))
            $ret_to = base64_encode($back_url = QF_INDEX);

        if (!$QF->User->UID || ($QF->User->acc_level < $QF->Config->Get('upl_minlvl', 'files_cfg', 1)))
            return Array(Lang('FILES_UPL_DISALLOW'), $back_url, true);

        if ($files = $QF->GPC->Get_File('upl_file'))
        {
            $capts = $QF->GPC->Get_Raw('upl_descr', QF_GPC_POST);
            foreach($files as $file_id)
                if ($file = $QF->GPC->Get_File($file_id))
                {
                    $real_file = $file['tmp_name'];
                    $filename = preg_replace('#[^\w\.\-]#', '_', $file['name']);
                    $capt = trim(preg_replace('#(?<=\S)\.\w+$#', '', strtr($filename, '_', ' ')));

                    if (preg_match('#upl_file\[(.+)\]#', $file_id, $mts) && isset($capts[$mts[1]]) && $capts[$mts[1]])
                        $capt = $capts[$mts[1]];

                    $data = Array(
                        'author' => $QF->User->uname, 'author_id' => $QF->User->UID,
                        'filename' => $filename, 'caption' => $capt,
                        );


                    if ($file['error'])
                        $errors[] = sprintf($QF->LNG->Lang('ERR_FILES_UPL_SERVER'), $file['name'], $QF->LNG->Lang('ERR_FILES_UPL_SERVER_'.$file['error']));
                    elseif (!$QF->Files->Create_File($real_file, $data))
                        $errors[] = sprintf($QF->LNG->Lang('ERR_FILES_UPL_ERROR'), $file['name'], '');
                    else
                        $got_files++;
                }
        }

        $errors = '<br />'.implode('<br />', $errors);
        if (!$got_files)
            return Array(sprintf(Lang('ERR_FILES_UPL_GOTNONE'), $errors), $FOX->Gen_URL('fox2_file_uploader', $ret_to), true);
        else
            return Array(sprintf(Lang('RES_FILES_UPL_GOTOK'), $errors), $back_url);
    }

    function Page_ViewFolder(&$p_title, &$p_subtitle, &$d_result)
    {
        global $QF, $FOX;

        $fold_id = $QF->GPC->Get_String('id', QF_GPC_GET, QF_GPC_WORD);
        if (!$fold_id)
            $fold_id = 0;
        if (($info = $QF->Files->Get_FolderInfo($fold_id)) && !$info['is_sys'] && ($my_acc = $QF->User->CheckAccess($info['r_level'], $info['w_level'], $info['acc_gr'])))
        {
            $fold_id = ($info['t_id']) ? $info['t_id'] : $info['id'];
            $conts = $QF->Files->Get_FolderConts($info['id'], $QF->User->acc_level);
            $folds = $QF->Files->Get_FoldersTree();
            while ($fold = array_pop($folds))
            {
                if (!is_null($fold['parent'])
                    && $fold['parent'] == $info['id'] && !$fold['is_sys']
                    && $QF->User->CheckAccess($fold['r_level'], $fold['w_level'], $fold['acc_gr']))
                    array_unshift($conts, $fold);
            }

            $finfo_page = $QF->VIS->Create_Node('FILES_FOLDER_PAGE');
            $PageInfo = Array(
                'FID'      => $info['id'],
                'CAPTION'  => $info['name'],
                'TIME'     => $QF->LNG->Time_Format($info['mtime']),
                'ACC_LVL'  => $info['r_level'],
                );
            if (!is_null($info['parent']) && $pinfo = $QF->Files->Get_FolderInfo($info['parent']))
            {                $PageInfo['PARENT_ID'] = ($pinfo['t_id']) ? $pinfo['t_id'] : $pinfo['id'];
                $PageInfo['PARENT_CAPT'] = $pinfo['name'];
            }

            if ($my_acc >= 2 && $QF->User->UID)
            {
                $PageInfo['PERM_STORE'] = 1;
                $temps = $this->Node_MyTemps('files');
                $QF->VIS->Append_Node($temps, 'MYTEMPS', $finfo_page);
            }

            if ($my_acc >= 3 && $QF->User->UID)
            {
                $PageInfo['PERM_FCREATE'] = 1;
                $PageInfo['MAX_LVL'] = $QF->User->mod_level;
            }

            $QF->VIS->Add_Data_Array($finfo_page, $PageInfo);
            $p_title = $info['name'];

            $pages = (int) ceil(count($conts)/$this->per_page);
            $page = abs($QF->GPC->Get_Num('page', QF_GPC_GET));
            if ($page < 1)
                $page = 1;
            elseif ($page > $pages)
                $page = $pages;

            if ($pages>1)
            {
                $draw_pages = $FOX->Gen_Pages($pages, $page, Array('FOLD_ID' => $fold_id));

                $QF->VIS->Add_Node_Array('FILES_FOLDER_PAGE_BTN', 'PAGE_BTNS', $finfo_page, $draw_pages);
                $QF->VIS->Add_Data($finfo_page, 'CUR_PAGE', $page);

                $start = $this->per_page*($page - 1);
                $conts = array_slice($conts, $start, $this->per_page);
            }

            $QF->Files->Load_FileInfos($conts);
            $i = 0;
            foreach ($conts as $cont_item)
            {

                if (!is_array($cont_item))
                {
                    $cont_item = $QF->Files->Get_FileInfo($cont_item);

                    $itm_info = Array(
                        'FID'      => $cont_item['id'],
                        'CAPTION'  => $cont_item['caption'],
                        'SCAPTION' => $QF->USTR->Str_SmartTrim($cont_item['caption'], 32),
                        'T_HEIGHT' => $this->th_h + 5,
                        'FILENAME' => $cont_item['filename'],
                        'SIZE'     => $QF->LNG->Size_Format($cont_item['file_size']),
                        'TYPE'     => $cont_item['mime'],
                        'TIME'     => $QF->LNG->Time_Format($cont_item['time']),
                        'LEVEL'    => $cont_item['r_level'],
                        'DLOADS'   => $cont_item['dloads'],
                        'IS_ARCH'  => ($cont_item['is_arch']) ? 1 : null,
                        'SHOW_THUMB' => ($cont_item['has_pics']) ? 1 : null,
                        'PICS_NAME' => $cont_item['pics_name'],
                        );

                    if ($cont_item['aspect_ratio'] != 0 && $QF->Files->Get_ImageDims($cont_item['id'], $wh, QF_FILES_IDIMS_THUMB) )
                        $itm_info['WIDTH_HEIGHT'] = 'width: '.$wh[0].'px; height: '.$wh[1].'px;';
                }
                else
                {                    $itm_info = Array(
                        'FOLDID'   => ($cont_item['t_id']) ? $cont_item['t_id'] : $cont_item['id'],
                        'CAPTION'  => $cont_item['name'],
                        'SCAPTION' => $QF->USTR->Str_SmartTrim($cont_item['name'], 32),
                        'T_HEIGHT' => $this->th_h + 5,
                        'SIZE'     => $QF->LNG->Size_Format($cont_item['size']),
                        'TIME'     => $QF->LNG->Time_Format($cont_item['mtime']),
                        'LEVEL'    => $cont_item['r_level'],
                        );
                }

                $QF->VIS->Add_Node('FILES_FOLDER_ITEM', 'ITEMS', $finfo_page, $itm_info);
                $i++;
            }

            $QF->Events->Call_Event('fox_folderpage_draw', $finfo_page, $fold_id, $conts);

            return $finfo_page;
        }
        else
            $QF->HTTP->Redirect($FOX->Gen_URL('fox2_files_viewrootdir'));

    }

    function DPage_GetFile()
    {
        global $QF, $FOX;

        $file_id = $QF->GPC->Get_String('fid', QF_GPC_GET, QF_STR_HEX);
        $dcode = $QF->GPC->Get_String('dcode', QF_GPC_GET, QF_STR_HEX);

        if (($info = $QF->Files->Get_FileInfo($file_id)) && $info['file_link'])
        {
            if ($dcode = 'useSID')
            {                $QF->Session->Open_Session(false, true);
                $sessName = 'dcode_'.$file_id;
                $dcode = $QF->Session->Get($sessName);
                if (is_null($dcode) || $QF->Files->Check_DCode($file_id, $dcode))
                {                    if ($dcode = $QF->Files->Gen_DCode($file_id))
                        $QF->Session->Set($sessName, $dcode);
                    else
                        $FOX->Set_Result(Lang('ERR_FILE_NOACCESS'), $FOX->Gen_URL('fox2_files_viewrootdir'), true);
                }
                $itsOK = true;
            }
            else
                $itsOK = $QF->Files->Check_DCode($file_id, $dcode);

            if ($itsOK)
            {
                $flags = 0;
                if ($QF->Config->Get('tricky_gecko', 'files_cfg') && preg_match('#\WGecko\/#i', $QF->HTTP->UAgent))
                    $flags |= QF_HTTP_FILE_RFC1522;
                elseif ($QF->Config->Get('tricky_opera', 'files_cfg') && preg_match('#^Opera\/|\WGecko\/|Download\x20Master#i', $QF->HTTP->UAgent))
                    $flags |= QF_HTTP_FILE_TRICKY;
                if (!$QF->Config->Get('send_inline', 'files_cfg') || $info['force_save'])
                    $flags |= QF_HTTP_FILE_ATTACHMENT;

                $QF->HTTP->Send_File($info['file_link'], $info['filename'], $info['mime'], $info['time'], $flags);
            }
            elseif ($dcode = $QF->Files->Gen_DCode($file_id)) // new DCode genereted
            {
                $QF->HTTP->Redirect($FOX->Gen_URL('fox2_file_download_dcode', Array($info['id'], $dcode, $info['filename'])));
            }
            else
                $FOX->Set_Result(Lang('ERR_FILE_NOACCESS'), $FOX->Gen_URL('fox2_files_viewrootdir'), true);

        }
        else
            $FOX->Set_Result(Lang('ERR_FILE_NOT_FOUND'), $FOX->Gen_URL('fox2_files_viewrootdir'), true);
    }

    function DPage_FilePreview()
    {
        global $QF, $FOX;

        $file_id = $QF->GPC->Get_String('fid', QF_GPC_GET, QF_STR_HEX);

        if (($info = $QF->Files->Get_FileInfo($file_id)) && $info['prv_link'])
        {
            $QF->Session->Open_Session(true);

            if (!$QF->User->is_spider && ($QF->User->acc_level >= $info['r_level']))
            {
                $flags = 0;
                if ($QF->Config->Get('tricky_gecko', 'files_cfg') && preg_match('#\WGecko\/#i', $QF->HTTP->UAgent))
                    $flags |= QF_HTTP_FILE_RFC1522;
                elseif ($QF->Config->Get('tricky_opera', 'files_cfg') && preg_match('#^Opera\/|\WGecko\/|Download\x20Master#i', $QF->HTTP->UAgent))
                    $flags |= QF_HTTP_FILE_TRICKY;

                $QF->HTTP->Send_File($info['prv_link'], 'prv_'.$info['pics_name'], $info['pics_mime'], $info['time'], $flags);
            }
            else
                $QF->HTTP->Redirect('static/images/att_low.gif');
        }
        else
            $QF->HTTP->Redirect('static/images/att_ferr.gif');

    }

    function DPage_FileThumb()
    {
        global $QF, $FOX;

        $file_id = $QF->GPC->Get_String('fid', QF_GPC_GET, QF_STR_HEX);

        if (($info = $QF->Files->Get_FileInfo($file_id)) && $info['thb_link'])
        {
            $QF->Session->Open_Session(true);

            if (!$QF->User->is_spider && ($QF->User->acc_level >= $info['r_level']))
            {
                $flags = 0;
                if ($QF->Config->Get('tricky_gecko', 'files_cfg') && preg_match('#\WGecko\/#i', $QF->HTTP->UAgent))
                    $flags |= QF_HTTP_FILE_RFC1522;
                elseif ($QF->Config->Get('tricky_opera', 'files_cfg') && preg_match('#^Opera\/|\WGecko\/|Download\x20Master#i', $QF->HTTP->UAgent))
                    $flags |= QF_HTTP_FILE_TRICKY;

                $QF->HTTP->Send_File($info['thb_link'], 'thb_'.$info['pics_name'], $info['pics_mime'], $info['time'], $flags);
            }
            else
                $QF->HTTP->Redirect('static/images/att_low.gif');
        }
        else
            $QF->HTTP->Redirect('static/images/att_ferr.gif');

    }

    function DPage_GetArchFile()
    {
        global $QF, $FOX;

        $file_id = $QF->GPC->Get_String('fid', QF_GPC_GET, QF_STR_HEX);
        $id = $QF->GPC->Get_Num('part', QF_GPC_GET);
        $dcode = $QF->GPC->Get_String('dcode', QF_GPC_GET, QF_STR_HEX);

        if (($info = $QF->Files->Get_FileInfo($file_id)) && ($info['file_link']))
        {
            $QF->Run_Module('TARFS');
            $cachename = QF_VIEWARCH_CACHEPREFIX.$file_id;
            $tarfile = null;
            if ($contents = $QF->Cache->Get($cachename))
            {
                // do nothing - contents is needed later
            }
            elseif (($tarfile = $QF->TARFS->OpenTAR($info['file_link'])) && ($contents = $QF->TARFS->ParseContent($tarfile)))
            {
                $QF->Cache->Set($cachename, $contents);
            }

            if ($contents)
            {
                if (!isset($contents[$id]) || $contents[$id]['type']!=0)
                    $QF->HTTP->Redirect($FOX->Gen_URL('fox2_file_viewarch', Array($file_id)));

                if ($QF->Files->Check_DCode($file_id, $dcode))
                {
                    $temp_id = $cachename.'.'.$id;
                    if (!($filename = $QF->Cache->Get_TempFile($temp_id)))
                    {
                        $filename = $QF->Cache->Create_TempFile($temp_id);
                        if (!$tarfile)
                            $tarfile = $QF->TARFS->OpenTAR($info['file_link']);
                        if ($QF->TARFS->UnpackFile($tarfile, $id, $filename, true, '600'))
                            touch($filename);
                        else
                        {
                            unlink($filename);
                            $filename = null;
                        }
                    }

                    if (!$filename)
                        trigger_error('FOX_GETARCH: Can\'t extract file '.$id.' from '.$file_id, E_USER_ERROR);

                    $subinfo = $contents[$id];
                    $ftype = pathinfo($subinfo['name']);
                    $ftype = $QF->DSets->Get_DSet_Value('mimetypes', strtolower($ftype['extension']));

                    $flags = 0;
                    if ($QF->Config->Get('tricky_gecko', 'files_cfg') && preg_match('#\WGecko\/#i', $QF->HTTP->UAgent))
                        $flags |= QF_HTTP_FILE_RFC1522;
                    elseif ($QF->Config->Get('tricky_opera', 'files_cfg') && preg_match('#^Opera\/|\WGecko\/|Download\x20Master#i', $QF->HTTP->UAgent))
                        $flags |= QF_HTTP_FILE_TRICKY;
                    if (!$QF->Config->Get('send_inline', 'files_cfg'))
                        $flags |= QF_HTTP_FILE_ATTACHMENT;

                    $QF->HTTP->Send_File($filename, $subinfo['name'], $ftype['mime'], $subinfo['time'], $flags);
                }
                elseif ($dcode = $QF->Files->Gen_DCode($file_id)) // new DCode genereted
                {
                    $QF->HTTP->Redirect($FOX->Gen_URL('fox2_viewarch_download_dcode', Array($info['id'], $id, $dcode, qf_basename($contents[$id]['name']))));
                }
                else
                    $FOX->Set_Result(Lang('ERR_FILE_NOACCESS'), $FOX->Gen_URL('fox2_files_viewrootdir'), true);
            }
            else
                $FOX->Set_Result(Lang('ERR_FILE_NOTARCH'), $FOX->Gen_URL('fox2_file_fileinfo', Array($file_id)), true);
        }
        else
            $FOX->Set_Result(Lang('ERR_FILE_NOT_FOUND'), $FOX->Gen_URL('fox2_files_viewrootdir'), true);
    }

    function Page_ViewArch(&$p_title, &$p_subtitle, &$d_result)
    {
        global $QF, $FOX;

        $file_id = $QF->GPC->Get_String('fid', QF_GPC_GET, QF_STR_HEX);
        if (!$file_id)
        {
            $QF->HTTP->Redirect($FOX->Gen_URL('fox2_files_viewrootdir'));
        }
        elseif (!($info = $QF->Files->Get_FileInfo($file_id)))
        {
            $d_result = Array(Lang('ERR_FILE_NOT_FOUND'), $FOX->Gen_URL('fox2_files_viewrootdir'), true);
        }
        elseif ($QF->User->is_spider || !($QF->User->acc_level >= $info['r_level']))
        {
            $d_result = Array(Lang('ERR_FILE_NOACCESS'), $FOX->Gen_URL('fox2_files_viewrootdir'), true);
        }
        elseif ($info['file_link'])
        {
            $QF->Run_Module('TARFS');
            $cachename = QF_VIEWARCH_CACHEPREFIX.$file_id;
            if ($contents = $QF->Cache->Get($cachename))
            {
                // do nothing - contents is ready
            }
            elseif (($tarfile = $QF->TARFS->OpenTAR($info['file_link'])) && ($contents = $QF->TARFS->ParseContent($tarfile)))
            {
                $QF->Cache->Set($cachename, $contents);
                $QF->TARFS->CloseTAR($tarfile);
                unset($tarfile);
            }


            if ($contents) // if we got contents
            {
                $file_id = $info['id'];
                $finfo_page = $QF->VIS->Create_Node('FILES_VIEWARCH');
                $PageInfo = Array(
                        'FID'      => $info['id'],
                        'CAPTION'  => $info['caption'],
                        'FILENAME' => $info['filename'],
                    );
                $QF->VIS->Add_Data_Array($finfo_page, $PageInfo);

                $pages = (int) ceil(count($contents)/$this->per_page);
                $page = $QF->GPC->Get_Num('page', QF_GPC_GET);
                if ($page < 1)
                    $page = 1;
                elseif ($page > $pages)
                    $page = $pages;

                if ($pages>1)
                {
                    $draw_pages = $FOX->Gen_Pages($pages, $page, Array('FILE_ID' => $file_id));

                    $QF->VIS->Add_Node_Array('FILES_VIEWARCH_PAGE_BTN', 'PAGE_BTNS', $finfo_page, $draw_pages);
                    $QF->VIS->Add_Data($finfo_page, 'CUR_PAGE', $page);
                }

                $cont_list = Array();
                $start = $this->per_page*($page - 1) + 1;
                $stop = min($start + $this->per_page, count($contents) + 1);
                for ($id = $start; $id < $stop; $id++)
                {
                    $subinfo = $contents[$id];
                    $cont_list[] = Array(
                        'ARCHID'   => $file_id,
                        'ID'       => $id,
                        'FILENAME' => $subinfo['name'],
                        'BASENAME' => qf_basename($subinfo['name']),
                        'FILESIZE' => $QF->LNG->Size_Format($subinfo['size']),
                        'FILETIME' => $QF->LNG->Time_Format($subinfo['time']),
                        'NOT_FILE' => ($subinfo['type'] != 0) ? true : null,
                        );
                }
                $QF->VIS->Add_Node_Array('FILES_VIEWARCH_CONT_ROW', 'CONT_LIST', $finfo_page, $cont_list);

                $p_title = sprintf($QF->LNG->lang('FILES_VIEWARCH_PAGE_CAPT'), $info['filename']);

                return $finfo_page;
            }
            else
                $d_result = Array(Lang('ERR_FILE_NOTARCH'), $FOX->Gen_URL('fox2_file_fileinfo', Array($file_id)), true);

        }
        else
            $d_result = Array(Lang('ERR_FILE_NOT_FOUND'), $FOX->Gen_URL('fox2_files_viewrootdir'), true);

        return false;
    }

    function Page_FileInfo(&$p_title, &$p_subtitle, &$d_result)
    {
        global $QF, $FOX;

        $file_id = $QF->GPC->Get_String('fid', QF_GPC_GET, QF_STR_HEX);
        if (!$file_id)
        {
            $QF->HTTP->Redirect($FOX->Gen_URL('fox2_files_viewrootdir'));
        }
        elseif (!($info = $QF->Files->Get_FileInfo($file_id)))
        {
            $d_result = Array(Lang('ERR_FILE_NOT_FOUND'), $FOX->Gen_URL('fox2_files_viewrootdir'), true);
        }
        elseif (!($my_acc = $QF->User->CheckAccess($info['r_level'], 0, 0, $info['author_id'])))
        {
            $d_result = Array(Lang('ERR_FILE_NOACCESS'), $FOX->Gen_URL('fox2_files_viewrootdir'), true);
        }
        elseif ($info['file_link'])
        {
            $QF->Run_Module('UList');
            $finfo_page = $QF->VIS->Create_Node('FILES_INFOPAGE');
            if ($QF->User->adm_level && $QF->GPC->Get_String('force_regen', QF_GPC_GET, QF_STR_HEX)
                && strstr($info['mime'], 'image/') && $QF->Files->Regen_Pics($file_id))
                $info = $QF->Files->Get_FileInfo($file_id);

            $PageInfo = Array(
                'FID'      => $info['id'],
                'CAPTION'  => $info['caption'],
                'FILENAME' => $info['filename'],
                'SIZE'     => $QF->LNG->Size_Format($info['file_size']),
                'TYPE'     => $info['mime'],
                'TIME'     => $QF->LNG->Time_Format($info['time']),
                'MD5SUM'   => $info['file_md5'],
                'LEVEL'    => $info['r_level'],
                'DLOADS'   => $info['dloads'],
                'IS_ARCH'  => ($info['is_arch']) ? 1 : null,
                'SHOW_PREVIEW' => ($info['has_pics'] && $info['prv_link']) ? 1 : null,
                'GOT_ACCESS'   => '1',
                'SHOW_EXTRAS'  => ($QF->User->UID) ? '1' : null,
                'PICS_NAME' => $info['pics_name'],
            );

            if ($info['aspect_ratio'] != 0 && $QF->Files->Get_ImageDims($file_id, $wh, QF_FILES_IDIMS_PREVIEW) )
            {
                $PageInfo['WIDTH_HEIGHT'] = 'width: '.$wh[0].'px; height: '.$wh[1].'px;';
                $QF->Files->Get_ImageDims($info['id'], $wh, QF_FILES_IDIMS_IMAGE);
                $PageInfo['IMAGE_W'] = $wh[0];
                $PageInfo['IMAGE_H'] = $wh[1];
            }

            if (!$info['is_temp'] && ($finfo = $QF->Files->Get_FolderInfo($info['folder'])) && $finfo['is_sys'] == 0)
            {                $PageInfo['PAR_FOLDER'] = ($finfo['t_id']) ? $finfo['t_id'] : $finfo['id'];
                $PageInfo['PAR_FOLDER_NAME'] = $finfo['name'];
            }

            if ($folders = $QF->Files->Get_FoldersTree())
            {
                $par_variants = Array();
                if ($info['is_temp'])
                    $par_variants[-1] = Array('VAL' => '-1', 'CAPT' => '---');

                foreach ($folders as $fl)
                    if (!$fl['is_sys'] && $QF->User->CheckAccess($fl['r_level'], $fl['w_level']) >= 2)
                        $par_variants[$fl['id']] = Array('VAL' => $fl['id'], 'CAPT' => str_repeat('+ ', $fl['t_level']).$fl['name']);
                unset($pgs);

                $my_par = ($info['is_temp']) ? -1 : $info['folder'];
                if (isset($par_variants[$my_par]) && count($par_variants) > 1)
                {
                    $par_variants[$my_par]['SEL'] = '1';
                    $QF->VIS->Add_Node_Array('MISC_SELECT_OPTION', 'FOLDERS_VARS', $finfo_page, $par_variants);
                }
            }


            if ($uinfo = $QF->UList->Get_UserInfo($info['author_id']))
                $QF->VIS->Add_Node('USER_INFO_MIN', 'AUTHOR', $finfo_page, $uinfo);
            elseif ($info['author'])
                $PageInfo['AUTHOR'] = $info['author'];
            else
                $PageInfo['AUTHOR'] = Lang('NO_DATA');

            if ($QF->User->UID && $QF->User->adm_level)
            {
                $PageInfo['SHOW_ADM'] = 'true';
                if ($info['author_ip'])
                    $PageInfo['AUTHOR_IP'] = IP_from_int($info['author_ip']);
            }

            if ($my_acc >= 3)
            {
                $PageInfo['CAN_MODIFY'] = 'true';
                $PageInfo['MAX_LVL'] = ($QF->User->UID && $QF->User->UID == $info['author_id']) ? max($QF->User->acc_level, $QF->User->mod_level) : $QF->User->mod_level;
            }

            if ($info['mime'] == 'audio/mpeg')
            {
                $QF->VIS->Add_Node('FILES_VIEWER_MP3', 'ALT_PRV', $finfo_page, Array(
                    'FILENAME' => urlencode($FOX->Gen_URL('fox2_file_download_bysess', $info['id'], false, true)),
                    'FILECAPT' => urlencode($info['caption']),
                    ));
            }
            elseif ($info['mime'] == 'video/x-flv')
            {
                $QF->VIS->Add_Node('FILES_VIEWER_FLV', 'ALT_PRV', $finfo_page, Array(
                    'FILENAME' => urlencode($FOX->Gen_URL('fox2_file_download_bysess', $info['id'], false, true)),
                    'FILECAPT' => urlencode($info['caption']),
                    ));
            }

            $QF->VIS->Add_Data_Array($finfo_page, $PageInfo);
            $p_title = $QF->LNG->lang('FILE_INFOPAGE_CAPT');
            $p_title = $info['caption'];
            return $finfo_page;
        }
        else
            $d_result = Array(Lang('ERR_FILE_NOT_FOUND'), $FOX->Gen_URL('fox2_files_viewrootdir'), true);

        return false;
    }
}

?>
