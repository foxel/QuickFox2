<?php

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

class Fox2_adm
{
    function _Start()
    {
        global $QF;
        $QF->LNG->Load_Language('admin');
    }

    function Page_AdmPanel(&$p_title, &$p_subtitle, &$d_result)
    {
        global $QF, $FOX;

        $QF->VIS->Load_Templates('admin');

        if (!$QF->User->UID || !$QF->User->adm_level)
        {
            $d_result = Array(Lang('ADMPANEL_ERR_NOADMIN'), QF_INDEX, true);
            //header ($QF->HTTP->SERVER["SERVER_PROTOCOL"].' 403 Forbidden');
        }
        elseif (!$QF->User->S_Get('adm_logged'))
        {
            $p_title = $QF->LNG->Lang('PAGE_ADMPANEL_CAPT');
            return $QF->VIS->Create_Node('ADM_PAN_LOGIN', Array('URI' => $QF->HTTP->Request) );
        }
        else
        {
            $adm_pans = Array(            // TODO: - needs to be stored in datasets
                'configs' => 'L_ADMTAB_CONFIGURER',
                'menued'  => 'L_ADMTAB_MENUEDITOR',
                'mdomain' => 'L_ADMTAB_MULTIDOMAIN',
                'cms'     => 'L_ADMTAB_CMSCONTROL',
                'users'   => 'L_ADMTAB_USERS',
                'packs'   => 'L_ADMTAB_PACKAGES',
                'backup'  => 'L_ADMTAB_BACKUP',
                );

            if (!$QF->Config->Get('tgl_multidomain', 'fox2'))
                unset($adm_pans['mdomain']);

            $adm_pans = $QF->LNG->LangParse($adm_pans);

            $c_panel = $QF->GPC->Get_String('admp', QF_GPC_GET, QF_STR_WORD);
            if (!$c_panel)
                $c_panel = $QF->Session->Get('last_admp');
            if (!$c_panel || !isset($adm_pans[$c_panel]))
                $c_panel = 'configs';

            $QF->Session->Set('last_admp', $c_panel);

            $p_title = $QF->LNG->Lang('PAGE_ADMPANEL_CAPT');
            $ADM_FRAME = $QF->VIS->Create_Node('ADM_PAGE_MAIN', false, 'ADM_FRAME');
            foreach ($adm_pans as $pid => $pname)
            {
                $tab = $QF->VIS->Add_Node('FOX_WINDOW_TAB', 'TABS', $ADM_FRAME, Array('href' => $FOX->Gen_URL('fox2_adm_panel_admp', Array($pid), true), 'caption' => $pname) );
                if ($c_panel == $pid)
                {
                    $QF->VIS->Add_Data($tab, 'SELECTED', 1);
                    $QF->VIS->Add_Data($ADM_FRAME, 'PANEL_SUBTITLE', $pname);
                    $p_subtitle = $pname;
                }
            }

            // we'll load and run selected adm page
            // it must return node ID and use $p_subtitle to set panel subtitle
            if (($data = $QF->DSets->Get_DSet_Value('fox_admpanels', $c_panel)) && isset($data['module'], $data['method']))
            {
                $QF->Run_Module($data['module']);
                $admp_subtitle = '';
                $admd_result = false; // not implemented
                $pg_node = qf_func_call_arr(Array(&$QF->$data['module'], $data['method']), Array(&$admp_subtitle, &$admd_result));
                if ($pg_node)
                    $QF->VIS->Append_Node($pg_node, 'ADM_FRAME', $ADM_FRAME);
                if ($admp_subtitle)
                    $QF->VIS->Add_Data($ADM_FRAME, 'PFRAME_SUBTITLE', $admp_subtitle);
            }


            return $ADM_FRAME;
        }
    }

    function AdmP_Backup(&$p_subtitle)
    {
        global $QF, $FOX;

        $node = $QF->VIS->Create_Node('ADM_FRAME_BACKUP');

        return $node;
    }

    function Script_Backup()
    {
        global $QF, $FOX;

        if ($acc_err = $this->_Scr_Check_Access())
            return $acc_err;

        $QF->Run_Module('EFS');
        $QF->Run_Module('DBDump');
        $QF->Run_Module('TARFS');
        $QF->Run_Module('Files');
        $QF->Run_Module('CMS');

        $dbkey = $QF->DBase->tbl_prefix;
        $time = date('Y-m-d', $QF->Timer->time);
        $dbdumpfile = 'QF2-DB-Dump-'.$time.'.sql';
        $backupfile = 'QF2-BackUp-'.$time.'.tar';

        $FilesToTAR = Array();

        $SQLoptions = Array(
            'all_tables' => false,
            'repldbkey'  => false,
            );

        if ($old_back = $QF->Config->Get('last_backup_id', 'qf2_temps')) //obtaining previous backup file ID
        {
            $QF->Files->Drop_File($old_back);
            $QF->Config->Set('last_backup_id', null, 'qf2_temps', true); //lets clear this data from DB
        }

        // removing time limits
        set_time_limit(3600);

        // making the list
        $FilesToTAR[] = 'data/cfg/database.qfc'; // database access configuration file

        // + stored uploads
        if ($QF->Files->Load_FileInfos('!ALL')) // Wasting lots of time and resouces but we'll get good archive
        {
            $IDs = $QF->Files->Get_LoadedFIDs();
            foreach($IDs as $fid)
                if ($fid != $old_back && ($info = $QF->Files->Get_FileInfo($fid)))
                {
                    $FilesToTAR[] = str_replace(QF_DATA_ROOT, 'data/', $info['file_link']);
                }
        }

        // + CMS pages
        if ($CMSList = $QF->CMS->Get_List())
        {
            foreach($CMSList as $pg)
                $FilesToTAR[] = str_replace(QF_DATA_ROOT, 'data/', $pg['file_link']);
        }

        // DB backup file
        $dbdumpfile = $QF->DBDump->Dump_Tables($dbdumpfile, true, $SQLoptions);
        chmod($dbdumpfile, 0600); //we'll protect this file from downloading

        array_unshift($FilesToTAR, $dbdumpfile); // database dump file will be first

        // Calculating MD5 hashes
        $files_MD5 = Array();
        foreach($FilesToTAR as $fname)
            $files_MD5[] = qf_md5_file($fname).' '.$fname;

        $files_MD5 = implode("\n", $files_MD5);

        // opening the main TAR stream
        $tarstream = $QF->TARFS->OpenTAR($backupfile, true);

        $QF->TARFS->PackData($tarstream, $files_MD5, 'Dump.md5', '600');

        $QF->TARFS->MakeDir($tarstream, 'data', '700');
        $QF->TARFS->MakeDir($tarstream, 'data/certifs', '700');
        $QF->TARFS->MakeDir($tarstream, 'data/cfg', '700');
        $QF->TARFS->MakeDir($tarstream, 'data/langs', '700');
        $QF->TARFS->MakeDir($tarstream, 'data/styles', '700');
        $QF->TARFS->MakeDir($tarstream, 'data/includes', '700');
        $QF->TARFS->MakeDir($tarstream, 'data/includes/pages', '700');
        $QF->TARFS->MakeDir($tarstream, 'data/includes/scripts', '700');
        $QF->TARFS->MakeDir($tarstream, 'data/jscripts', '700');
        $QF->TARFS->MakeDir($tarstream, 'data/modules', '700');
        $QF->TARFS->MakeDir($tarstream, 'data/cms_pgs', '700');
        $QF->TARFS->MakeDir($tarstream, 'data/uploads', '700');
        $QF->TARFS->MakeDir($tarstream, 'data/thb_n_prvw', '700');
        $QF->TARFS->MakeDir($tarstream, 'static', '755');
        $QF->TARFS->MakeDir($tarstream, 'static/images', '755');
        $QF->TARFS->MakeDir($tarstream, 'static/images/styles', '755');

        foreach($FilesToTAR as $fname)
            $QF->TARFS->PackFile($tarstream, $fname);

        $QF->TARFS->CloseTAR($tarstream);

        $FileParams = Array(
                'author' => $QF->User->uname, 'author_id' => $QF->User->UID, 'r_level' => QF_FOX2_MAXULEVEL,
                'filename' => $backupfile, 'caption' => 'QuickFox2 BackUp ('.$time.')',
                );
        $dump_id = $QF->Files->Create_File($backupfile, $FileParams, true);

        if ($dump_id)
        {
            unlink($dbdumpfile);
            $QF->Files->Move_File($dump_id, 0);
            $QF->Config->Set('last_backup_id', $dump_id, 'qf2_temps', true); //lets store the new dump file ID
            return Array($QF->LNG->Lang('RES_ADMPANEL_BACKUP_DONE'), $FOX->Gen_URL('fox2_file_fileinfo', $dump_id));
        }

        return Array($QF->LNG->Lang('ERR_ADMPANEL_BACKUP_FAIL'), $FOX->Gen_URL('fox2_adm_panel_admp', 'backup'), true);
    }

    function AdmP_MultiDomain(&$p_subtitle)
    {
        global $QF, $FOX;

        $cur_domain = strtolower($QF->HTTP->SrvName);

        $schemes = $QF->Config->List_Schemes();
        $packs   = $QF->DSets->Get_Packages();
        $e_packs = $QF->Config->Get('use_packs', 'qf2_sys');
        if (!is_array($e_packs))
            $e_packs = Array();

        $p_packs = $QF->DSets->Get_DSet('fox_pages', true);
        $p_packs = array_unique(array_values($p_packs[1]));

        $d_schemas = $QF->Config->Get('domain_schemas', 'fox2');
        $p_domains = $QF->Config->Get('package_domains', 'fox2');

        if (!is_array($d_schemas))
            $d_schemas = Array($cur_domain => '');
        if (!is_array($p_domains))
            $p_domains = Array();

        $domains = array_unique(array_merge(array_keys($d_schemas), array_values($p_domains)));

        if (!in_array($cur_domain, $domains))
            array_unshift($domains, $cur_domain);


        $cfg_node = $QF->VIS->Create_Node('ADM_FRAME_MDOMAIN', Array(
            ));

        $scm_vars = Array();
        foreach ($schemes as $scm)
            $scm_vars[$scm] = array('val' => $scm, 'capt' => $scm);
        $scm_vars[QF_KERNEL_CONFIG_DEFSCHEME] = array('val' => '', 'capt' => $QF->LNG->Lang('ADMPANEL_CFGS_NOSCHEME'));

        $domain_vars = Array('' => Array('val' => '', 'capt' => $QF->LNG->Lang('ADMPANEL_MDOMAIN_NODOMAIN')));
        foreach ($domains as $domain)
        {
            $nnode = $QF->VIS->Add_Node('ADM_FRAME_MDOMAIN_DITEM', 'DOMAINS_DATA', $cfg_node, Array('domain' => $domain));
            $scm_vars0 = $scm_vars;
            if (isset($d_schemas[$domain]) && isset($scm_vars0[$d_schemas[$domain]]))
                $scm_vars0[$d_schemas[$domain]]['sel'] = true;
            $QF->VIS->Add_Node_Array('MISC_SELECT_OPTION', 'SCHEME_VARS', $nnode, $scm_vars0);

            $domain_vars[$domain] = array('val' => $domain, 'capt' => 'http://'.$domain);
        }

        foreach ($packs as $id => $data)
        {
            if (!in_array($id, $p_packs) || (!in_array($id, $e_packs) && (!isset($data['Type']) || $data['Type'] != 'sys')))
                continue;

            $nnode = $QF->VIS->Add_Node('ADM_FRAME_MDOMAIN_PITEM', 'PACKAGES_DATA', $cfg_node, Array('PACKAGE' => $id));
            $domain_vars0 = $domain_vars;
            if (isset($p_domains[$id]) && isset($domain_vars0[$p_domains[$id]]))
                $domain_vars0[$p_domains[$id]]['sel'] = true;
            $QF->VIS->Add_Node_Array('MISC_SELECT_OPTION', 'DOMAIN_VARS', $nnode, $domain_vars0);
        }


        return $cfg_node;
    }

    function Script_MultiDomain()
    {
        global $QF, $FOX;

        $cur_domain = strtolower($QF->HTTP->SrvName);

        $schemes = $QF->Config->List_Schemes();
        $packs   = $QF->DSets->Get_Packages();
        $e_packs = $QF->Config->Get('use_packs', 'qf2_sys');
        if (!is_array($e_packs))
            $e_packs = Array();
        $do_action = $QF->GPC->Get_String('action', QF_GPC_POST, QF_STR_WORD);

        if ($acc_err = $this->_Scr_Check_Access())
        {
            return $acc_err;
        }
        elseif ($do_action == 'add_scheme')
        {
            $new_scheme = $QF->GPC->Get_String('scheme', QF_GPC_POST, QF_STR_WORD);
            if ($new_scheme && $new_scheme != QF_KERNEL_CONFIG_DEFSCHEME)
            {
                $QF->Config->Set('site_name', $QF->Config->Get('site_name'), false, true, $new_scheme);
                return Array($QF->LNG->Lang('RES_ADMPANEL_MDOMAIN_SCREATED'), $FOX->Gen_URL('fox2_adm_panel_admp', 'mdomain'));
            }
        }
        elseif ($do_action == 'add_domain')
        {
            $new_domain = $QF->GPC->Get_String('domain', QF_GPC_POST, QF_STR_LINE);
            $d_schemas = $QF->Config->Get('domain_schemas', 'fox2');
            if (!is_array($d_schemas))
                $d_schemas = Array($cur_domain => '');

            if ($new_domain && preg_match('#[0-9A-z_\-\.]+\.[A-z]{2,4}#', $new_domain))
            {
                $d_schemas[$new_domain] = '';
                $QF->Config->Set('domain_schemas', $d_schemas, 'fox2', true);
                return Array($QF->LNG->Lang('RES_ADMPANEL_MDOMAIN_DCREATED'), $FOX->Gen_URL('fox2_adm_panel_admp', 'mdomain'));
            }
        }
        elseif ($do_action == 'd_schemes')
        {
            $d_schemas = $QF->Config->Get('domain_schemas', 'fox2');
            if (!is_array($d_schemas))
                $d_schemas = Array($cur_domain => '');

            $n_schemas = $QF->GPC->Get_Raw('d_schemes', QF_GPC_POST);
            $del_domains = $QF->GPC->Get_Raw('del_domain', QF_GPC_POST);
            if (!is_array($del_domains))
                $del_domains = Array();
            $do_delete = $QF->GPC->Get_Bin('do_delete', QF_GPC_POST);

            if (is_array($n_schemas))
            {
                $domains = array_keys($d_schemas);
                foreach ($domains as $domain)
                {
                    if (isset($n_schemas[$domain]))
                    {
                        $n_scheme = $n_schemas[$domain];
                        if (!in_array($n_scheme, $schemes))
                            $n_scheme = '';
                        $d_schemas[$domain] = $n_scheme;
                        if (in_array($domain, $del_domains) && $do_delete)
                            unset($d_schemas[$domain]);
                    }
                }

                $QF->Config->Set('domain_schemas', $d_schemas, 'fox2', true);
                return Array($QF->LNG->Lang('RES_ADMPANEL_MDOMAIN_DSAPPLIED'), $FOX->Gen_URL('fox2_adm_panel_admp', 'mdomain'));
            }
        }
        elseif ($do_action == 'p_domains')
        {
            $d_schemas = $QF->Config->Get('domain_schemas', 'fox2');
            if (!is_array($d_schemas))
                $d_schemas = Array($cur_domain => '');

            $domains = array_keys($d_schemas);

            $n_domains = $QF->GPC->Get_Raw('p_domains', QF_GPC_POST);
            $p_domains = Array();
            if (is_array($n_domains))
            {
                foreach ($packs as $id => $data)
                {
                    if (!in_array($id, $e_packs) && (!isset($data['Type']) || $data['Type'] != 'sys'))
                        continue;

                    if (isset($n_domains[$id]))
                    {
                        $n_domain = $n_domains[$id];
                        if (in_array($n_domain, $domains))
                            $p_domains[$id] = $n_domain;
                    }
                }

                $QF->Config->Set('package_domains', $p_domains, 'fox2', true);
                $QF->Cache->Clear();
                return Array($QF->LNG->Lang('RES_ADMPANEL_MDOMAIN_PDAPPLIED'), $FOX->Gen_URL('fox2_adm_panel_admp', 'mdomain'));
            }
        }

        return Array($QF->LNG->Lang('ERR_ADMPANEL_MDOMAIN_WRONGDATA'), $FOX->Gen_URL('fox2_adm_panel_admp', 'mdomain'), true);
    }

    function AdmP_Packages(&$p_subtitle)
    {
        global $QF, $FOX;

        $enabled = $QF->Config->Get('use_packs', 'qf2_sys');
        $packs = $QF->DSets->Get_Packages();

        $cfg_node = $QF->VIS->Create_Node('ADM_FRAME_PACKAGES');

        $packs_data = Array();
        if (!is_array($enabled))
            $enabled = Array();
        foreach ($packs as $id => $data)
        {
            $packs_data[] = Array(
                'id'        => $id,
                'capt'      => (isset($data['Name'])) ? $data['Name'] : $id,
                'developer' => (isset($data['Developer'])) ? $data['Developer'] : 'n/a',
                'enabled'   => (in_array($id, $enabled)) ? '1' : null,
                'is_sys'    => (isset($data['Type']) && $data['Type'] == 'sys') ? '1' : null,
                );
        }

        if (count($packs_data))
            $QF->VIS->Add_Node_Array('ADM_FRAME_PACKS_ITEM', 'PACKS_DATA', $cfg_node, $packs_data);
        return $cfg_node;
    }

    function Script_Packages()
    {
        global $QF, $FOX;

        //$enabled = $QF->Config->Get('use_packs', 'qf2_sys');
        $packs   = $QF->DSets->Get_Packages();

        if ($acc_err = $this->_Scr_Check_Access())
        {
            return $acc_err;
        }
        else
        {
            $enabled = $QF->GPC->Get_Raw('enable', QF_GPC_POST);
            if (is_array($enabled))
            {
                $data = Array();
                foreach (array_keys($packs) as $id)
                    if (in_array($id, $enabled))
                        $data[] = $id;
                $QF->Config->Set('use_packs', $data, 'qf2_sys', true);
            }
            else
                $QF->Config->Set('use_packs', null, 'qf2_sys', true);

            $QF->Cache->Clear();
            $QF->DSets->ReInit(); //needed to normally reinit URLs list
            return Array($QF->LNG->Lang('RES_ADMPANEL_PACKS_UPDATED'), $FOX->Gen_URL('fox2_adm_panel_admp', 'packs'));
        }
    }

    function AdmP_Users(&$p_subtitle)
    {
        global $QF, $FOX;

        $QF->Run_Module('UList');

        $list = $QF->UList->Get_List();

        $list = array_values($list);
        $cfg_node = $QF->VIS->Create_Node('ADM_FRAME_USERS_LIST', Array('MAX_ADM' => $QF->User->adm_level - 1));

        $QF->UList->Query_IDs($list);
        $uss_data = Array();
        foreach ($list as $uid)
        {
            $udata = $QF->UList->Get_UserInfo($uid);

            if ($udata['a_lvl'] < $QF->User->adm_level)
                $udata['can_edit'] = '1';
            $uss_data[] = $udata;
        }

        $QF->VIS->Add_Node_Array('ADM_FRAME_USERS_ITEM', 'ITEMS_DATA', $cfg_node, $uss_data);
        return $cfg_node;
    }

    function Script_Users()
    {
        global $QF, $FOX;

        $QF->Run_Module('UList');
        $action = $QF->GPC->Get_String('mode', QF_GPC_POST, QF_STR_WORD);

        if ($acc_err = $this->_Scr_Check_Access())
        {
            return $acc_err;
        }
        elseif ($action == 'multi_edit')
        {
            $uids = $QF->GPC->Get_Raw('sel_user', QF_GPC_POST);
            $QF->UList->Query_IDs($uids);
            $edit_users = Array();
            foreach ($uids as $uid)
            {
                $udata = $QF->UList->Get_UserInfo($uid);

                if ($udata['a_lvl'] < $QF->User->adm_level)
                    $edit_users[] = $uid;
            }

            if (!count($edit_users))
                return Array($QF->LNG->Lang('ERR_ADMPANEL_USERS_NONE_SEL'), $FOX->Gen_URL('fox2_adm_panel_admp', 'users'), true);

            $adm_level = $QF->GPC->Get_Num('adm_level', QF_GPC_POST);
            if ($adm_level == -1)
                $adm_level = false;
            $mod_level = $QF->GPC->Get_Num('mod_level', QF_GPC_POST);
            if ($mod_level == -1)
                $mod_level = false;
            $acc_level = $QF->GPC->Get_Num('acc_level', QF_GPC_POST);
            if ($acc_level == -1)
                $acc_level = false;

            if ($QF->UList->Set_Levels($edit_users, $acc_level, $mod_level, $adm_level))
            {
                $result = sprintf($QF->LNG->Lang('RES_ADMPANEL_USERS_ACC_UPDATED'), count($edit_users));
                return Array($result, $FOX->Gen_URL('fox2_adm_panel_admp', 'users'));
            }
            else
                return Array($QF->LNG->Lang('ERR_ADMPANEL_USERS_ACC_UPD'), $FOX->Gen_URL('fox2_adm_panel_admp', 'users'), true);
        }
        elseif ($action == 'force_create')
        {

            $nuser = substr($QF->GPC->Get_String('user_login', QF_GPC_POST, QF_STR_LINE), 0, 16);
            $npasssrc1 = $QF->GPC->Get_String('user_pass', QF_GPC_POST, QF_STR_LINE);

            if (!$QF->UList->Create_User($nuser, $npasssrc1))
            {
                $err = $QF->UList->Get_Error();
                switch ($err)
                {
                    case QF_ERRCODE_USERLIB_BAD_LOGIN:
                        return Array($QF->LNG->lang('ERR_ADMPANEL_USERS_LOGIN_BAD'), $FOX->Gen_URL('fox2_adm_panel_admp', 'users'), true);
                    case QF_ERRCODE_USERLIB_BAD_NPASS:
                        return Array($QF->LNG->lang('ERR_ADMPANEL_USERS_PASS_BAD'), $FOX->Gen_URL('fox2_adm_panel_admp', 'users'), true);
                    case QF_ERRCODE_USERLIB_DUP_UNAME:
                        return Array($QF->LNG->lang('ERR_ADMPANEL_USERS_NICK_USED'), $FOX->Gen_URL('fox2_adm_panel_admp', 'users'), true);
                    case QF_ERRCODE_USERLIB_DUP_LOGIN:
                        return Array($QF->LNG->lang('ERR_ADMPANEL_USERS_LOGIN_USED'), $FOX->Gen_URL('fox2_adm_panel_admp', 'users'), true);
                    default:
                        return Array($QF->LNG->lang('ERR_ADMPANEL_USERS_NOTCREATED'), $FOX->Gen_URL('fox2_adm_panel_admp', 'users'), true);
                }
            }

            $result = sprintf($QF->LNG->Lang('RES_ADMPANEL_USERS_CREATED'), $nuser);
            return Array($result, $FOX->Gen_URL('fox2_adm_panel_admp', 'users'));
        }
        else
            return Array($QF->LNG->lang('ADMPANEL_QUERY_ERROR'), $FOX->Gen_URL('fox2_adm_panel_admp', 'users'), true);
    }

    function DPage_GetFile()
    {
        global $QF, $FOX;

        // first we need to load session data to determine if user is allowed to download the file
        $QF->Session->Open_Session();

        if ($acc_err = $this->_Scr_Check_Access())
        {
            return $acc_err;
        }
        else
        {
            $fname = urldecode($QF->GPC->Get_String('file', QF_GPC_GET, QF_STR_LINE));
            if (file_exists($fname))
            {
                $fname = realpath($fname);
                $myroot = realpath($QF->HTTP->SERVER['DOCUMENT_ROOT']);
                if (strpos($fname, $myroot)===0)
                {
                    $fext = pathinfo($fname, PATHINFO_EXTENSION);
                    $ftype = qf_file_mime($fname, $fext);
                    $QF->HTTP->Send_File($fname, $fname, $ftype['mime']);
                }
                else
                    trigger_error('FOX_ADM: admin UID'.$QF->User->UID.' tried to get file "'.$fname.'" from the system', E_USER_ERROR);
            }
        }

        $FOX->Set_Result(Lang('NO_DATA').$fname, QF_INDEX, true);
    }

    function AdmP_Config(&$p_subtitle)
    {
        global $QF, $FOX;

        $QF->Run_Module('ConfSets');

        $schemes  = $QF->Config->List_Schemes();
        if (!($cur_scheme = $QF->GPC->Get_String('cfgscheme', QF_GPC_GET, QF_STR_WORD)))
            $cur_scheme = QF_KERNEL_CONFIG_DEFSCHEME;
        if (!in_array($cur_scheme, $schemes))
            $cur_scheme = $schemes[0];

        $cfg_sets = $QF->ConfSets->Get_List($cur_scheme != QF_KERNEL_CONFIG_DEFSCHEME);
        if (!($cur_cfgset = $QF->GPC->Get_String('cfgset', QF_GPC_GET, QF_STR_WORD)))
            $cur_cfgset = 'common';
        if (!in_array($cur_cfgset, $cfg_sets))
            $cur_cfgset = $cfg_sets[0];



        $cfg_node = $QF->VIS->Create_Node('ADM_FRAME_CONFIGS', Array(
            'CUR_CFGSET' => $cur_cfgset,
            'CUR_SCHEME' => ($cur_scheme != QF_KERNEL_CONFIG_DEFSCHEME) ? $cur_scheme : null,
            ));

        $p_subtitle = $cur_cfgset;

        if (count($schemes) > 1)
            foreach ($schemes as $s_id)
            {
                $vars = Array('val' => $s_id, 'capt' => ($s_id == QF_KERNEL_CONFIG_DEFSCHEME)
                        ? $QF->LNG->Lang('ADMPANEL_CFGS_NOSCHEME')
                        : $s_id);
                if ($s_id == $cur_scheme)
                    $vars['sel'] = 1;
                $QF->VIS->Add_Node('MISC_SELECT_OPTION', 'SCHEMES_LIST', $cfg_node, $vars);
            }

        foreach ($cfg_sets as $set_id)
        {
            $vars = Array('val' => $set_id, 'capt' => $set_id);
            if ($set_id == $cur_cfgset)
                $vars['sel'] = 1;
            $QF->VIS->Add_Node('MISC_SELECT_OPTION', 'CFGSETS_LIST', $cfg_node, $vars);
        }

        $cfgs = $QF->ConfSets->Get_ConfSet($cur_cfgset);
        foreach ($cfgs as $vars)
        {
            if ($cur_scheme != QF_KERNEL_CONFIG_DEFSCHEME && !$vars['schemable'])
                continue;

            $type = $vars['type'];
            $c_vals = $QF->Config->Get_Full($vars['cfg_name'], $vars['cfg_parent']);
            $vars['cur_val'] = (isset($c_vals[$cur_scheme])) ? $c_vals[$cur_scheme] : null;

            if ($type == 'str')
            {
                if (isset($vars['subtype']))
                {
                    $subtype = $vars['subtype'];
                    if ($subtype == 'e-mail')
                    {
                        if (!qf_str_is_email($vars['cur_val']))
                            $vars['cur_val'] = '';
                    }
                    elseif ($subtype == 'word')
                    {
                        if (!preg_match('#\w+#', $vars['cur_val']))
                            $vars['cur_val'] = '';
                    }
                    elseif ($subtype == 'preg')
                    {
                        if (!@preg_match($vars['mask'], $vars['cur_val']))
                            $vars['cur_val'] = '';
                    }
                }
                $QF->VIS->Add_Node('ADM_FRAME_CONFIGS_CFG_STRING', 'CONFIGS_LIST', $cfg_node, $vars);
            }
            elseif ($type == 'text')
            {
                $QF->VIS->Add_Node('ADM_FRAME_CONFIGS_CFG_TEXT', 'CONFIGS_LIST', $cfg_node, $vars);
            }
            elseif ($type == 'int')
            {
                if (!is_null($vars['cur_val']))
                    $vars['cur_val'] = (int) $vars['cur_val'];
                if ($vars['max'] && (($vars['max'] - $vars['min']) < 30))
                    $QF->VIS->Add_Node('ADM_FRAME_CONFIGS_CFG_INTSEL', 'CONFIGS_LIST', $cfg_node, $vars);
                else
                    $QF->VIS->Add_Node('ADM_FRAME_CONFIGS_CFG_INT', 'CONFIGS_LIST', $cfg_node, $vars);
            }
            elseif ($type == 'bool')
            {
                if (!is_null($vars['cur_val']))
                    $vars['cur_val'] = ($vars['cur_val']) ? 'true' : 'false';
                $QF->VIS->Add_Node('ADM_FRAME_CONFIGS_CFG_BOOL', 'CONFIGS_LIST', $cfg_node, $vars);
            }
            elseif ($type == 'select')
            {
                $nnode = $QF->VIS->Add_Node('ADM_FRAME_CONFIGS_CFG_SELECT', 'CONFIGS_LIST', $cfg_node, $vars);
                $options = array();
                foreach ($vars['variants'] as $op_val => $op_name)
                    $options[] = Array('VAL' => $op_val, 'CAPT' => $op_name, 'SELECTED' => (!is_null($vars['cur_val']) && $op_val == $vars['cur_val']) ? true : null);
                $QF->VIS->Add_Node_Array('ADM_FRAME_CONFIGS_CFG_SELOPT', 'OPTIONS', $nnode, $options);
            }
        }

        return $cfg_node;
    }

    function Script_Config()
    {
        global $QF, $FOX;
        $QF->Run_Module('ConfSets');
        $cur_cfgset = $QF->GPC->Get_String('cfgset', QF_GPC_POST, QF_STR_WORD);
        if (!($cur_scheme = $QF->GPC->Get_String('cfgscheme', QF_GPC_POST, QF_STR_WORD)))
            $cur_scheme = QF_KERNEL_CONFIG_DEFSCHEME;
        $schemes  = $QF->Config->List_Schemes();

        if ($acc_err = $this->_Scr_Check_Access())
        {
            return $acc_err;
        }
        elseif (($cfgs = $QF->ConfSets->Get_ConfSet($cur_cfgset)) && (!$cur_scheme || in_array($cur_scheme, $schemes)))
        {
            $sets_data = $QF->GPC->Get_Raw('confs', QF_GPC_POST);
            $QF->Run_Module('Parser');

            $dropped = Array();
            $drop_ch = false;
            $drop_cfgs = Array();
            foreach ($cfgs as $vars)
            {
                if ($cur_scheme != QF_KERNEL_CONFIG_DEFSCHEME && !$vars['schemable'])
                    continue;

                $type = $vars['type'];
                $varname = $vars['cfg_name'];
                if (!isset($sets_data[$varname]))
                    continue;

                $c_vals = $QF->Config->Get_Full($vars['cfg_name'], $vars['cfg_parent']);
                $vars['cur_val'] = (isset($c_vals[$cur_scheme])) ? $c_vals[$cur_scheme] : null;

                $cval = (string) $sets_data[$varname];
                if ($vars['drops_ch'] && !$drop_ch) // checking for cache drop needed
                {
                    $oval = (string) $vars['cur_val'];
                    if ($oval !== $cval)
                        $drop_ch = true;
                }

                if ($vars['drops_cfs'])
                {
                    $oval = (string) $vars['cur_val'];
                    if ($oval !== $cval)
                        $drop_cfgs = array_merge($drop_cfgs, $vars['drops_cfs']);
                }


                if ($cval === '')
                {
                    $QF->Config->Set($varname, null, $vars['cfg_parent'], true, ($vars['schemable']) ? $cur_scheme : false);
                    continue;
                }

                if ($type == 'str' || $type == 'text')
                {
                    if ($c_recoded = $QF->Session->Get('recode_out'))
                        if ($val = $QF->USTR->Str_Convert($cval, QF_INTERNAL_ENCODING, $c_recoded))
                            $cval = $val;
                    $subtype = (isset($vars['subtype'])) ? $vars['subtype'] : '';

                    if ($type == 'str')
                    {
                        $cval = preg_replace('#[\r\n]#', '', $cval);
                        if ($subtype == 'e-mail')
                        {
                            if (!qf_str_is_email($cval))
                                $cval = null;
                        }
                        elseif ($subtype == 'word')
                        {
                            if (!preg_match('#\w+#', $cval))
                                $cval = null;
                        }
                        elseif ($subtype == 'preg')
                        {
                            if (!@preg_match($vars['mask'], $cval))
                                $cval = null;
                        }
                    }

                    if ($subtype == 'html')
                    {
                        $cval = $QF->Parser->XML_Check($cval, true);
                    }

                    if ($subtype == 'path')
                    {
                        if (!file_exists($cval))
                            $cval = null;
                    }

                    $mylen = $QF->USTR->Str_Len($cval);
                    if ($vars['max'] && ($mylen > $vars['max']))
                        $cval = $QF->USTR->Str_Substr($cval, 0, $vars['max']);
                    elseif ($vars['min'] && ($mylen < $vars['min']))
                        $cval = null;
                }
                elseif ($type == 'int')
                {
                    $cval = (int) $cval;
                    if ($vars['max'] && ($cval > $vars['max']))
                        $cval = $vars['max'];
                    elseif ($vars['min'] && ($cval < $vars['min']))
                        $cval = null;
                }
                elseif ($type == 'bool')
                {
                    $cval = ($cval)
                        ? 1
                        : 0;
                }
                elseif ($type == 'select')
                {
                    $variants = array_keys($vars['variants']);
                    if (!in_array($cval, $variants))
                        $cval = null;
                }

                if (is_null($cval))
                    $dropped[] = '['.$vars['cfg_parent'].' => '.$varname.']';
                else
                    $QF->Config->Set($varname, $cval, $vars['cfg_parent'], true, ($vars['schemable']) ? $cur_scheme : false);
            }

            foreach ($drop_cfgs as $drop_cf)
            {
                list($dr_val, $dr_par) = explode('/', $drop_cf.'/');
                $QF->Config->Set($dr_val, null, $dr_par, true, false);
            }

            $result = sprintf($QF->LNG->Lang('ADMPANEL_CFGSET_UPDATED'), $cur_cfgset);
            if ($dropped)
                $result.= ' '.sprintf($QF->LNG->Lang('ADMPANEL_CFGSET_UPD_MISSED'), implode('; ', $dropped));
            if ($drop_ch)
                $QF->Cache->Clear();

            return Array($result, ($cur_scheme != QF_KERNEL_CONFIG_DEFSCHEME)
                 ? $FOX->Gen_URL('fox2_adm_configs_cfgscheme', Array($cur_scheme, $cur_cfgset))
                 : $FOX->Gen_URL('fox2_adm_configs_cfgset', Array($cur_cfgset)));
        }
        else
            return Array($QF->LNG->lang('ADMPANEL_QUERY_ERROR'), $FOX->Gen_URL('fox2_adm_configs_admp'), true);
    }

    function AdmP_MenuEd(&$p_subtitle)
    {
        global $QF, $FOX;

        $schemes  = $QF->Config->List_Schemes();
        if (!($cur_scheme = $QF->GPC->Get_String('cfgscheme', QF_GPC_GET, QF_STR_WORD)))
            $cur_scheme = QF_KERNEL_CONFIG_DEFSCHEME;
        if (!in_array($cur_scheme, $schemes))
            $cur_scheme = $schemes[0];

        $cfg_node = $QF->VIS->Create_Node('ADM_FRAME_MENUED', Array(
            'CUR_SCHEME' => ($cur_scheme != QF_KERNEL_CONFIG_DEFSCHEME) ? $cur_scheme : null,
            ));

        if (count($schemes) > 1)
            foreach ($schemes as $s_id)
            {
                $vars = Array('val' => $s_id, 'capt' => ($s_id == QF_KERNEL_CONFIG_DEFSCHEME)
                        ? $QF->LNG->Lang('ADMPANEL_CFGS_NOSCHEME')
                        : $s_id);
                if ($s_id == $cur_scheme)
                    $vars['sel'] = 1;
                $QF->VIS->Add_Node('MISC_SELECT_OPTION', 'SCHEMES_LIST', $cfg_node, $vars);
            }

        $c_vals = $QF->Config->Get_Full('menu_buttons', 'visual');
        $items = (isset($c_vals[$cur_scheme])) ? $c_vals[$cur_scheme] : null;

        if (!is_array($items))
            $items = Array();
        $ord = 1;
        foreach ($items as $itm)
        {
            $itm['order'] = $ord++;
            $QF->VIS->Add_Node('ADM_FRAME_MENUED_ITEM', 'ITEMS_LIST', $cfg_node, qf_value_htmlschars($itm));
        }
        for ($i=0; $i<5; $i++)
            $QF->VIS->Add_Node('ADM_FRAME_MENUED_ITEM', 'ITEMS_LIST', $cfg_node, Array('order' => $ord++));

        return $cfg_node;
    }

    function Script_MenuEd()
    {
        global $QF, $FOX;
        $menu_data = $QF->GPC->Get_Raw('menuitms', QF_GPC_POST);
        if (!($cur_scheme = $QF->GPC->Get_String('cfgscheme', QF_GPC_POST, QF_STR_WORD)))
            $cur_scheme = QF_KERNEL_CONFIG_DEFSCHEME;

        if ($acc_err = $this->_Scr_Check_Access())
        {
            return $acc_err;
        }
        elseif (is_array($menu_data))
        {
            $menu_data = qf_2darray_sort($menu_data, 'order', false, SORT_NUMERIC);
            $dropped = Array();
            $menu_cfg = Array();
            foreach ($menu_data as $itm)
            {
                if (!isset($itm['caption'], $itm['url']))
                    continue;

                $capt = $itm['caption'];
                $mylen = $QF->USTR->Str_Len($capt);
                if ($mylen > 20)
                    $cval = $QF->USTR->Str_Substr($cval, 0, 20);
                elseif ($mylen < 3)
                    continue;

                $url = $itm['url'];
                if (!qf_str_is_url($url))
                {
                    $url = '#';
                    $dropped[] = $capt;
                }

                $is_sub = isset($itm['is_sub']) ? (($itm['is_sub']) ? 1 : 0) : 0;

                $menu_cfg[] = Array(
                    'caption' => $capt,
                    'url'     => $url,
                    'is_sub'  => $is_sub,
                    );
            }
            if (count($menu_cfg))
                $menu_cfg[0]['is_sub'] = 0;
            else
                $menu_cfg = null;

            $QF->Config->Set('menu_buttons', $menu_cfg, 'visual', true, $cur_scheme);

            $result = $QF->LNG->Lang('ADMPANEL_MENUED_UPDATED');
            if ($dropped)
                $result.= sprintf($QF->LNG->Lang('ADMPANEL_MENUED_URL_DROPPED'), implode('; ', $dropped));

            return Array($result, ($cur_scheme != QF_KERNEL_CONFIG_DEFSCHEME)
                 ? $FOX->Gen_URL('fox2_adm_menued_cfgscheme', Array($cur_scheme))
                 : $FOX->Gen_URL('fox2_adm_panel_admp', Array('menued')));
        }

        return Array($QF->LNG->lang('ADMPANEL_QUERY_ERROR'), $FOX->Gen_URL('fox2_adm_panel_admp', Array('menued')), true);
    }


    function Script_Login()
    {
        global $QF, $FOX;

        $login = $QF->GPC->Get_String('login', QF_GPC_POST, QF_STR_LINE);
        $pass  = $QF->GPC->Get_String('pass', QF_GPC_POST, QF_STR_LINE);
        $redir_uri = $QF->GPC->Get_String('pan_uri', QF_GPC_POST, QF_STR_LINE);

        if (!$QF->User->UID || !$QF->User->adm_level)
            return Array(Lang('ADMPANEL_ERR_NOADMIN'), QF_INDEX, true);

        if ($QF->User->CheckAuth($pass, $login))
        {
            $QF->User->S_Set('adm_logged', true);
            return Array(Lang('ADMPANEL_RES_LOGIN_LOGGED'), (qf_str_is_url($redir_uri)) ? qf_full_url($redir_uri) : $FOX->Gen_URL('fox2_adm_panel'));
        }

        return Array(Lang('ERR_LOGIN_ERRDATA'), $FOX->Gen_URL('fox2_adm_panel'), true);
    }

    function _Scr_Check_Access()
    {
        global $QF;

        if (!$QF->User->UID || !$QF->User->adm_level)
        {
            return Array(Lang('ADMPANEL_ERR_NOADMIN'), QF_INDEX, true);
        }
        elseif (!$QF->User->S_Get('adm_logged'))
        {
            return Array(Lang('ADMPANEL_LOGIN_REQUEST'), QF_INDEX, true);
        }
        else
            return false;
    }
}

?>
