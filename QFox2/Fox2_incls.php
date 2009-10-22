<?php

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

define('QF_FOX2_HTC_CACHEPREFIX', 'HTC.');

class Fox2_incls
{
    var $per_page = 15;

    function DPage_ASImg()
    {
        global $QF, $FOX;

        $QF->Run_Module('Imager');
        $QF->Session->Open_Session();

        if (QF_IMAGER_USE_GD && (imagetypes() & IMG_PNG))
        {
            $code = $FOX->Gen_ASCode();
            $root_img = imagecreatefrompng(QUICKFOX_DIR.'as_data.dat');

            $count = strlen($code);
            $width = $count*20 + 5;

            $dest_img = imagecreatetruecolor($width, 40);
            imagefilledrectangle($dest_img, 0, 0, $width-1, 39, imagecolorallocate($dest_img, rand(200, 255), rand(200, 255), rand(200, 255)));

            if (function_exists('imageantialias'))
                imageantialias($dest_img, true);

            for ($i = 0; $i < $count; $i++)
                imageline($dest_img, rand(-10, 0), rand(-30, 70), $width + rand(0, 10), rand(-30, 70), imagecolorallocate($dest_img, rand(100, 200), rand(100, 200), rand(100, 200)));

            for ($i = 0; $i < $count; $i++)
            {
                $id = (int) hexdec($code{$i}) + rand(0,3)*16;

                $sx = ($id%8) * 24;
                $sy = floor($id/8) * 32;

                $x = $i*20;
                $w = rand(20, 24);
                $h = rand(22, 32);
                $x+= rand(0, 25-$w);
                $y = rand(0, 40-$h);
                imagecopyresampled($dest_img, $root_img, $x, $y, $sx, $sy, $w, $h, 24, 32);
            }
            imagerectangle($dest_img, 0, 0, $width-1, 39, 0x6b7d87);

            imagetruecolortopalette($dest_img, false, 128);
            ob_start();
            imagepng($dest_img);
            $img = ob_get_contents();
            ob_end_clean();


            $QF->HTTP->Send_Binary($img, 'image/png', 10);
        }
    }

    function DPage_CSS()
    {
        global $QF, $FOX;
        $QF->Run_Module('VIS');
        // here we need to configure style
        $style = $QF->GPC->Get_String('style', QF_GPC_GET, QF_STR_WORD);
        $variant = $QF->GPC->Get_String('variant', QF_GPC_GET, QF_STR_WORD);
        $QF->VIS->Configure(Array(
            'style' => $style,
            'CSS' => $variant,
            ), true);

        $QF->HTTP->do_HTML = false;
        $QF->HTTP->Clear();
        $QF->HTTP->Write($QF->VIS->Make_CSS());
        $QF->HTTP->Send_Buffer($QF->Session->Get('recode_out'), 'text/css', 3600);
    }

    function DPage_HTC()
    {
        global $QF, $FOX;
        // here we need to load EJS data
        $name = $QF->GPC->Get_String('name', QF_GPC_GET, QF_STR_WORD);
        $filename = QF_DATA_ROOT.'htc/'.$name.'.htc';
        $cachename = QF_FOX2_HTC_CACHEPREFIX.$name;

        $QF->HTTP->do_HTML = false;
        $QF->HTTP->Clear();
        if ($data = $QF->Cache->Get($cachename))
            $QF->HTTP->Write($data);
        elseif (file_exists($filename) && ($data = qf_file_get_contents($filename)))
        {            $QF->Run_Module('VIS');
            $repls = Array(
                '{IMGS}' => qf_full_url(QF_IMAGES_DIR),
                '{STATICS}' => qf_full_url(QF_STATICS_DIR),
                );

            $data = strtr($data, $repls);
            $FOX->On_EJS_Prep($data);
            $QF->Cache->Set($cachename, $data);
            $QF->HTTP->Write($data);
        }
        else
            $QF->HTTP->Set_Status(404);

        $QF->HTTP->Send_Buffer(false, 'text/x-component', 3600*72);
    }

    function DPage_JS()
    {
        global $QF, $FOX;
        $QF->Run_Module('VIS');
        // here we need to load EJS data
        $name = $QF->GPC->Get_String('name', QF_GPC_GET, QF_STR_WORD);
        $QF->VIS->Load_EJS($name);

        $QF->HTTP->do_HTML = false;
        $QF->HTTP->Clear();
        $QF->HTTP->Write($QF->VIS->Make_JS());
        $QF->HTTP->Send_Buffer($QF->Session->Get('recode_out'), 'text/javascript', 3600);
    }

    function Page_ShowResult()
    {
        global $QF, $FOX;

        $res_id = $QF->GPC->Get_String('res_id', QF_GPC_GET, QF_STR_HEX);

        if ($curres = $QF->DBase->Do_Select('results', '*', Array( 'res_id' => $res_id) ))
        {
            if (($curres['u_sid'] && $curres['u_sid'] != $QF->Session->SID) || ($curres['u_id'] && $curres['u_id'] != $QF->User->UID))
                $QF->HTTP->Redirect(QF_INDEX);
            else
            {
                $descr_errs = ($curres['tr_errs']) ? qf_array_parse(explode('|', $curres['tr_errs']), 'hexdec') : false;
                $FOX->Draw_Result($curres['text'], $curres['redir_to'], $curres['is_err'], $descr_errs);
            }
        }
    }

    function Page_Login(&$p_title)
    {
        global $QF;

        $p_title = $QF->LNG->Lang('PAGE_LOGIN_CAPT');
        $node = $QF->VIS->Create_Node('PAGE_LOGIN');
        if ($QF->User->UID)
            $QF->VIS->Add_Data($node, 'CURUSER', $QF->User->uname);
        elseif ($QF->DSets->Package_Used('qf2_multiuser'))
            $QF->VIS->Add_Data($node, 'SHOW_REG', 1);

        if ($QF->Session->Get_Status(QF_SESSION_USEURL) || ($QF->Config->Get('max_autologins', 'users', 1) < 1))
            $QF->VIS->Add_Data($node, 'DISABLE_AL', 1);

        return $node;
    }

    function Pan_Login($pan_node = false)
    {
        global $QF;
        $VIS = $QF->VIS;

        if (!$pan_node)
            $pan_node = $QF->VIS->Create_Node('PANEL_BODY', false, 'login_panel');

        $QF->VIS->Add_Data_Array($pan_node, Array(
            'title' => Lang('PAN_LOGIN_CAPT'),
            ) );

        if (!$QF->User->UID)
        {
            $node = $QF->VIS->Add_Node('PAN_LOGIN_LOG', 'contents', $pan_node, Array('URL' => $QF->HTTP->Request) );
            if ($QF->DSets->Package_Used('qf2_multiuser'))
                $bline = $QF->VIS->Add_Node('PAN_LOGIN_LOG_BOTTLINE', 'bottline', $pan_node);

            if ($QF->Session->Get_Status(QF_SESSION_USEURL) || ($QF->Config->Get('max_autologins', 'users', 1) < 1))
                $QF->VIS->Add_Data($node, 'DISABLE_AL', 1);
            if ($QF->Session->clicks > 3)
                $QF->VIS->Add_Data($pan_node, 'HIDDEN', 1);
        }
        else
        {
            $cont = $QF->VIS->Add_Node('PAN_LOGIN_GREET', 'contents', $pan_node, Array('user' => $QF->User->uname) );
            $bline = $QF->VIS->Add_Node('PAN_LOGIN_BOTTLINE', 'bottline', $pan_node);
            if ($QF->User->adm_level)
                $QF->VIS->Add_Data($bline, 'is_adm', true);
        }

        return $pan_node;
    }

    function Page_UserInfo(&$p_title, &$p_subtitle, &$d_result)
    {        global $QF, $FOX;
        $QF->Run_Module('UList');

        $uid = $QF->GPC->Get_Num('uid', QF_GPC_GET);

        $info_frame = null;
        $p_title = $QF->LNG->Lang('UINFO_PAGE');

        if ($uid)
        {            $uinfo = $QF->UList->Get_UserInfo($uid, false);
            if (!$uinfo)
            {
                $d_result = Array(Lang('ERR_UINFO_NOUSER'), $FOX->Gen_URL('fox2_userinfo_list'), true);
                return false;
            };

            $info_frame = $QF->VIS->Create_Node('USER_PAGE_INFO', false, 'USERINFO_PAGE');

            foreach ($uinfo['stats'] as $a_key => $a_val)
            {
                $a_key = 'stat_'.$a_key;
                if (!isset($uinfo[$a_key]))
                    $uinfo[$a_key] = $a_val;
            }
            unset($uinfo['stats']);

            $uinfo['regtime'] = $QF->LNG->Time_Format($uinfo['regtime']);
            $uinfo['stat_lastseen'] = $QF->LNG->Time_Format($uinfo['stat_lastseen']);
            $uinfo['stat_last_ip'] = IP_from_int($uinfo['stat_last_ip']);

            $p_subtitle = $uinfo['nick'];

            if ($QF->User->adm_level)
                $uinfo['show_adm'] = '1';

            $QF->VIS->Add_Data_Array($info_frame, $uinfo);
        }
        else
        {            $ulist = $QF->UList->Get_List();

            $info_frame = $QF->VIS->Create_Node('USERS_PAGE_LIST');

            $pages = (int) ceil(count($ulist)/$this->per_page);
            $page = abs($QF->GPC->Get_Num('page', QF_GPC_GET));
            if ($page < 1)
                $page = 1;
            elseif ($page > $pages)
                $page = $pages;

            if ($pages > 1)
            {
                $draw_pages = $FOX->Gen_Pages($pages, $page);

                $QF->VIS->Add_Node_Array('USERS_LIST_PAGE_BTN', 'PAGE_BTNS', $info_frame, $draw_pages);
                $page_params['CUR_PAGE'] = $page;

                $start = $this->per_page*($page - 1);
                $ulist = array_slice($ulist, $start, $this->per_page);
            }

            $QF->UList->Query_IDs($ulist);
            $us_items = Array();
            foreach ($ulist as $uid)
                $us_items[] = $QF->UList->Get_UserInfo($uid, false);

            $QF->VIS->Add_Node_Array('USERS_LIST_ITEM', 'USER_ITEMS', $info_frame, $us_items);
        }

        return $info_frame;
    }

    function Page_UserCabinet(&$p_title, &$p_subtitle, &$d_result)
    {        global $QF, $FOX;
        $QF->Run_Module('UList');

        $QF->VIS->Load_Templates('usercab');

        if (!$QF->User->UID)
        {
            $d_result = Array(Lang('ERR_USERCAB_NOUSER'), QF_INDEX, true);
            return null;
            //header ($QF->HTTP->SERVER["SERVER_PROTOCOL"].' 403 Forbidden');
        }

        $ucab_pans = Array(            // TODO: - needs to be stored in datasets
            'info' => 'L_USERCAB_VIEWINFO',
            'sys' => 'L_USERCAB_SYSPARAMS',
            'set_info' => 'L_USERCAB_INFOPARAMS',
            );

        $ucab_pans = $QF->LNG->LangParse($ucab_pans);

        $c_panel = $QF->GPC->Get_String('panel', QF_GPC_GET, QF_STR_WORD);
        if (!$c_panel)
            $c_panel = 'info';

        $p_title = $QF->LNG->Lang('PAGE_USERCAB_CAPT');
        $ucab_frame = $QF->VIS->Create_Node('USERCAB_PAGE_MAIN', false, 'ADM_FRAME');
        foreach ($ucab_pans as $pid => $pname)
        {
            $tab = $QF->VIS->Add_Node('FOX_WINDOW_TAB', 'TABS', $ucab_frame, Array('href' => $FOX->Gen_URL('fox2_usercab_panel', Array($pid), true), 'caption' => $pname) );
            if ($c_panel == $pid)
            {
                $QF->VIS->Add_Data($tab, 'SELECTED', 1);
                $QF->VIS->Add_Data($ucab_frame, 'PANEL_SUBTITLE', $pname);
                $p_subtitle = $pname;
            }
        }

        // we'll load and run selected adm page
        // it must return node ID and use $p_subtitle to set panel subtitle
        if (($data = $QF->DSets->Get_DSet_Value('fox_ucabpanels', $c_panel)) && isset($data['module'], $data['method']))
        {
            $QF->Run_Module($data['module']);
            $ucabp_subtitle = '';
            $ucabd_result = false; // not implemented
            $pg_node = qf_func_call_arr(Array(&$QF->$data['module'], $data['method']), Array(&$ucabp_subtitle, &$ucabd_result));
            if ($pg_node)
                $QF->VIS->Append_Node($pg_node, 'UCAB_FRAME', $ucab_frame);
            if ($p_subtitle)
                $QF->VIS->Add_Data($ucab_frame, 'PFRAME_SUBTITLE', $ucabp_subtitle);
        }

        return $ucab_frame;
    }

    function UserCab_Info(&$p_subtitle)
    {
        global $QF, $FOX;

        $node = $QF->VIS->Create_Node('UCAB_FRAME_INFO');
        $uinfo = $QF->UList->Get_UserInfo($QF->User->UID, true);

        foreach ($uinfo['auth_info'] as $a_key => $a_val)
        {            $a_key = 'auth_'.$a_key;
            if (!isset($uinfo[$a_key]))
                $uinfo[$a_key] = $a_val;
        }
        foreach ($uinfo['stats'] as $a_key => $a_val)
        {
            $a_key = 'stat_'.$a_key;
            if (!isset($uinfo[$a_key]))
                $uinfo[$a_key] = $a_val;
        }
        unset($uinfo['auth_info'], $uinfo['stats']);

        $uinfo['regtime'] = $QF->LNG->Time_Format($uinfo['regtime']);
        $uinfo['auth_lastauth'] = $QF->LNG->Time_Format($uinfo['auth_lastauth']);
        $uinfo['stat_last_ip'] = IP_from_int($uinfo['stat_last_ip']);

        $QF->VIS->Add_Data_Array($node, $uinfo);

        return $node;
    }

    function UserCab_Sys(&$p_subtitle)
    {
        global $QF, $FOX;

        $node  = $QF->VIS->Create_Node('UCAB_FRAME_SYS');
        $uinfo = $QF->UList->Get_UserInfo($QF->User->UID, true);

        $ndata = Array(
            'AUTH_LOGIN' => $uinfo['auth_info']['login'],
            );
        $QF->VIS->Add_Data_Array($node, $ndata);

        return $node;
    }

    function UserCab_SetInfo(&$p_subtitle)
    {
        global $QF, $FOX;

        $node  = $QF->VIS->Create_Node('UCAB_FRAME_SETINFO');
        $uinfo = $QF->UList->Get_UserInfo($QF->User->UID, true);

        $ndata = Array(
            'nick'   => $uinfo['nick'],
            'avatar' => $uinfo['avatar'],
            'avatar_wh' => $uinfo['avatar_wh'],
            );

        $QF->VIS->Add_Data_Array($node, $ndata);

        return $node;
    }

    function Script_MySys()
    {        global $QF, $FOX;

        $QF->Run_Module('UList');
        $QF->LNG->Load_Language('usercab');

        $mode = $QF->GPC->Get_String('action', QF_GPC_POST, QF_STR_WORD);
        $pass = $QF->GPC->Get_String('pass', QF_GPC_POST, QF_STR_LINE);

        if (!$QF->User->CheckAuth($pass))
            return Array($QF->LNG->lang('ERR_USERCAB_AUTH_WRONG'), $FOX->Gen_URL('fox2_user_cabinet'), true);

        if ($mode == 'auth')
        {            $nlogin = substr($QF->GPC->Get_String('new_login', QF_GPC_POST, QF_STR_LINE), 0, 16);
            $npasssrc1 = $QF->GPC->Get_String('new_pass', QF_GPC_POST, QF_STR_LINE);
            $npasssrc2 = $QF->GPC->Get_String('new_pass_dup', QF_GPC_POST, QF_STR_LINE);

            if ($npasssrc1 != $npasssrc2)
                return Array($QF->LNG->lang('ERR_USERCAB_AUTH_PASSES_DIFF'), $FOX->Gen_URL('fox2_usercab_panel', 'sys'), true);

            $params = Array();
            if ($npasssrc1)
                $params['pass'] = $npasssrc1;
            if ($nlogin)
                $params['login'] = $nlogin;

            if ($QF->UList->Set_Auth($QF->User->UID, $params))
                return Array($QF->LNG->lang('RES_USERCAB_AUTH_MODIFIED'), $FOX->Gen_URL('fox2_user_cabinet'));

            return Array($QF->LNG->lang('ERR_USERCAB_NOTMODIFIED'), $FOX->Gen_URL('fox2_usercab_panel', 'sys'), true);
        }
        elseif ($mode == 'email')
        {            $nemail = substr($QF->GPC->Get_String('new_email', QF_GPC_POST, QF_STR_LINE), 0, 32);

            if (!qf_str_is_email($nemail))
                return Array($QF->LNG->lang('ERR_USERCAB_WRONG_EMAIL'), $FOX->Gen_URL('fox2_usercab_panel', 'sys'), true);

            if ($QF->UList->Set_Auth($QF->User->UID, Array('email' => $nemail)))
                return Array($QF->LNG->lang('RES_USERCAB_SYSEMAIL_MODIFIED'), $FOX->Gen_URL('fox2_user_cabinet'));

            return Array($QF->LNG->lang('ERR_USERCAB_NOTMODIFIED'), $FOX->Gen_URL('fox2_usercab_panel', 'sys'), true);
        }

        return Array($QF->LNG->lang('USERCAB_QUERY_ERROR'), $FOX->Gen_URL('fox2_user_cabinet'), true);
    }

    function Script_MyInfo()
    {
        global $QF, $FOX;
        $av_w = 72; $av_h = 96;

        $QF->Run_Module('UList');
        $QF->LNG->Load_Language('usercab');

        $mode = $QF->GPC->Get_String('action', QF_GPC_POST, QF_STR_WORD);

        if (!$QF->User->UID)
            return Array($QF->LNG->lang('ERR_USERCAB_AUTH_WRONG'), $FOX->Gen_URL('fox2_user_cabinet'), true);

        $uinfo = $QF->UList->Get_UserInfo($QF->User->UID, true);

        if ($mode == 'setnick')
        {
            $nnick = $QF->USTR->Str_Substr($QF->GPC->Get_String('new_nick', QF_GPC_POST, QF_STR_LINE), 0, 16);

            if (!$nnick)
                return Array($QF->LNG->lang('ERR_USERCAB_INFO_WRONG_UNAME'), $FOX->Gen_URL('fox2_usercab_panel', 'set_info'), true);

            if ($QF->UList->Set_BaseInfo($QF->User->UID, Array('nick' => $nnick)))
                return Array($QF->LNG->lang('RES_USERCAB_UNAME_MODIFIED'), $FOX->Gen_URL('fox2_usercab_panel', 'set_info'));

            return Array($QF->LNG->lang('ERR_USERCAB_NOTMODIFIED'), $FOX->Gen_URL('fox2_usercab_panel', 'set_info'), true);
        }
        elseif ($mode == 'avatar')
        {
            $file = $QF->GPC->Get_File('new_avatar');
            if (!$file)
                return Array($QF->LNG->lang('ERR_USERCAB_AVATAR_NOFILE'), $FOX->Gen_URL('fox2_usercab_panel', 'set_info'), true);
            if ($file['error'])
                return Array($QF->LNG->lang('ERR_USERCAB_AVATAR_FILEERR').$file['error'], $FOX->Gen_URL('fox2_usercab_panel', 'set_info'), true);

            $real_file = $file['tmp_name'];

            if (filesize($real_file) > 102400)
                return Array($QF->LNG->lang('ERR_USERCAB_AVATAR_FILEBIG'), $FOX->Gen_URL('fox2_usercab_panel', 'set_info'), true);

            $QF->Run_Module('Imager');
            $iinfo = $QF->Imager->Check_IType($real_file);
            if (!$iinfo)
                return Array($QF->LNG->lang('ERR_USERCAB_AVATAR_NOTIMAGE'), $FOX->Gen_URL('fox2_usercab_panel', 'set_info'), true);

            $new_av = 'US'.$QF->User->UID.'-'.$QF->Timer->time;
            $new_av_file = QF_USERLIB_AVATARS_BASEPATH.$new_av;
            $out_type = $iinfo[2];
            $conf = Array(
                'width' => $av_w,
                'height' => $av_h,
                'rsize_mode' => QF_IMAGER_RSIZE_FIT,
            );

            if ($iinfo[0] <= $av_w && $iinfo[1] <= $av_h)
            {
                $new_av.= '.'.$iinfo['def_ext'];
                $new_av_file = QF_USERLIB_AVATARS_BASEPATH.$new_av;
                rename($real_file, $new_av_file);
            }
            elseif ($QF->Imager->Parse_Image($real_file, $new_av_file, $out_type, QF_IMAGER_DO_RESIZE, $conf))
            {                $o_av_file = $new_av_file;
                $iinfo = $QF->Imager->Check_IType($o_av_file);
                $new_av.= '.'.$iinfo['def_ext'];
                $new_av_file = QF_USERLIB_AVATARS_BASEPATH.$new_av;
                rename($o_av_file, $new_av_file);
            }
            else
                return Array($QF->LNG->lang('ERR_USERCAB_AVATAR_ERRPARSING'), $FOX->Gen_URL('fox2_usercab_panel', 'set_info'), true);

            if ($QF->UList->Set_BaseInfo($QF->User->UID, Array('avatar' => $new_av)))
            {
                if ($uinfo['avatar'] && is_file($uinfo['avatar']))
                    unlink($uinfo['avatar']);
                return Array($QF->LNG->lang('RES_USERCAB_AVATAR_MODIFIED'), $FOX->Gen_URL('fox2_usercab_panel', 'set_info'));
            }
            else
                unlink($new_av_file);

            return Array($QF->LNG->lang('ERR_USERCAB_NOTMODIFIED'), $FOX->Gen_URL('fox2_usercab_panel', 'set_info'), true);
        }
        elseif ($mode == 'delavatar')
        {
            if ($QF->UList->Set_BaseInfo($QF->User->UID, Array('avatar' => '')))
            {
                if ($uinfo['avatar'] && is_file($uinfo['avatar']))
                    unlink($uinfo['avatar']);
                return Array($QF->LNG->lang('RES_USERCAB_AVATAR_DELETED'), $FOX->Gen_URL('fox2_usercab_panel', 'set_info'));
            }

            return Array($QF->LNG->lang('ERR_USERCAB_NOTMODIFIED'), $FOX->Gen_URL('fox2_usercab_panel', 'set_info'), true);
        }

        return Array($QF->LNG->lang('USERCAB_QUERY_ERROR'), $FOX->Gen_URL('fox2_user_cabinet'), true);
    }
}

?>
