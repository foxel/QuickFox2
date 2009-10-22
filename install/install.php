<?php

// Simple InUSE Check
if ( !defined('QF_STARTED') || !defined('QF_SETUP_STARTED'))
        die('Hacking attempt');

require 'kernel2/QF_kernel2.php';

define('QF_FOX2_LOGIN_MASK', '^[0-9\w_\+\-=\(\)\[\] ]{3,16}$');

$QF->Cache->Clear();
$QF->Run_Module('VIS');
$QF->VIS->Configure(Array('style' => 'qf_def', 'root_node' => 'SETUP_HTMLPAGE'));
$QF->VIS->Load_Templates('qf_setup');

$QF_Pagedata = Array();

$SET_error = '';

if ($SET_step=='finish_install')
{
    $goto_url = 'index.php?drop_cache=1';

    if ($hta_confs = qf_file_get_contents('install/ht_confs.cfg'))
    {
        $repls = Array('{ROOT_DIR}' => ($QF->HTTP->RootDir) ? '/'.$QF->HTTP->RootDir.'/' : '/',
                       '{QF_INDEX}' => 'index.php');

        $ht_repls = Array( );

        $hta_confs = '#<!QuickFox>#'."\n".strtr($hta_confs, $repls)."\n".'#</QuickFox>#';
        $hta_mask  = '#\#<!QuickFox>\#.*\#</QuickFox>\##is';

        if ($old_conf = qf_file_get_contents('.htaccess'))
        {
            if ($ht_repls)
                $old_conf = preg_replace(array_keys($ht_repls), array_values($ht_repls), $old_conf);

            if (preg_match($hta_mask, $old_conf))
                $cur_conf = preg_replace($hta_mask, addcslashes($hta_confs, '\\$'), $old_conf);
            else
                $cur_conf = $hta_confs."\n\n".$old_conf;
        }
        else
            $cur_conf = $hta_confs;

        if ($old_conf != $cur_conf && !qf_file_put_contents('.htaccess', $cur_conf))
        {
            qf_file_put_contents('!put_to_htaccess', $cur_conf);

            $res_id = qf_short_uid('res');
            $ins_data = Array(
                'res_id'   => $res_id,
                'text'     => (string) sprintf($QF->LNG->Lang('SETUP_ERR_FILEMODIFY'), '.htaccess', '!put_to_htaccess', $goto_url),
                'is_err'   => 1,
                'time'     => $QF->Timer->time,
                'got_at'   => $QF->HTTP->Request,
                );

            if ($QF->DBase->Do_Insert('results', $ins_data))
                $goto_url.= '&st=showresult&res_id='.$res_id;
        }
    }

    if (!file_exists('setup.lock'))
    {
	    unlink('setup.php');
	    if (file_exists('init_err.log'))
	        unlink('init_err.log');
	    if (file_exists('qf2_err.log'))
	        unlink('qf2_err.log');
	}
    $QF->HTTP->Redirect($goto_url);
}

if ($SET_step == 'admin_setup')
{
    $QF_Pagedata['step_info'] = $QF->LNG->Lang('SETUP_STEP_ADMIN_SET');
    if ($SET_act == 'GO')
    {

        $nuser = substr($QF->GPC->Get_String('admin_nick', QF_GPC_POST, QF_STR_LINE), 0, 16);
        $npasssrc1 = $QF->GPC->Get_String('admin_pass', QF_GPC_POST, QF_STR_LINE);
        $npass1 = md5($npasssrc1);
        $npasssrc2 = $QF->GPC->Get_String('admin_passd', QF_GPC_POST, QF_STR_LINE);
        $npass2 = md5($npasssrc2);
        $nemail = $QF->GPC->Get_String('admin_email', QF_GPC_POST, QF_STR_LINE);

        if (!preg_match('#'.QF_FOX2_LOGIN_MASK.'#i', $nuser))
            $SET_error = $QF->LNG->Lang('SETUP_STEP_ADMIN_NAME_ERR');
        elseif (!qf_str_is_email($nemail))
            $SET_error = $QF->LNG->Lang('SETUP_STEP_ADMIN_EMAIL_ERR');
        elseif (strlen($npasssrc1) < 5)
            $SET_error = $QF->LNG->Lang('SETUP_STEP_ADMIN_PASS_ERR');
        elseif ($npass1 != $npass2)
            $SET_error = $QF->LNG->Lang('SETUP_STEP_ADMIN_PASSD_ERR');

        unset($npasssrc1, $npasssrc2);

        if (!$SET_error)
        {
            $ins_data = Array(
                'nick'     => $nuser,
                'regtime'  => $QF->Timer->time,
                'lastseen' => $QF->Timer->time,
                'level'    => 7,
                'mod_lvl'  => 7,
                'adm_lvl'  => 3,
                'av_sig'   => 'Root Admin',
                );
            if ($id = $QF->DBase->Do_Insert('users', $ins_data))
            {
                $ins_data = Array(
                    'uid'       => $id,
                    'login'     => $nuser,
                    'pass_hash' => $npass1,
                    'sys_email' => $nemail,
                    );
                if (!$QF->DBase->Do_Insert('users_auth', $ins_data))
                    $SET_error = $QF->LNG->Lang('SETUP_STEP_ADMIN_ERR');
            }
            else
                $SET_error = $QF->LNG->Lang('SETUP_STEP_ADMIN_ERR');
        }

        if (!$SET_error)
        {
            $QF_Pagedata['step'] = 'finish_install';
            $QF_Pagedata['form_cont'] = $QF->LNG->Lang('SETUP_STEP_ADMIN_OK');
        }
        else
        {
            $QF_Pagedata['step'] = $SET_step;
            $QF_Pagedata['form_cont'] = $SET_error;
        }
    }
    else
    {
        $QF_Pagedata['step'] = $SET_step;
        $QF_Pagedata['action'] = 'GO';
        $QF->VIS->Add_Node('SETUP_STEP_ADMIN_SET', 'FORM_CONT', 0, $tmpl);
    }
}

if ($SET_step=='dbase_import')
{
    $QF_Pagedata['step_info'] = $QF->LNG->Lang('SETUP_STEP_DATA_IMP');
    $upd_possible = false;

    $imp_mode = $QF->GPC->Get_String('imp_mode', QF_GPC_POST, QF_STR_WORD);

    $dbkey = $QF->DBase->tbl_prefix;

    $query = 'SHOW TABLES';
    if ($result = $QF->DBase->SQL_Query($query))
        while($tbl = $QF->DBase->SQL_FetchRow($result, false))
        {
            list($tblname) = $tbl;
            if ($tblname == $dbkey.'users_auth')
            {
                $upd_possible = true;
                break;
            }
        }

    if (!$upd_possible)
    {
        $SET_act = 'GO';
        $imp_mode = 'new';
    }

    if ($SET_act == 'GO')
    {
        $QF->Run_Module('DBImport');

        if ($imp_mode=='upd') {
            $impfile = fopen('install/qf_dbase.str', 'rb');
            $data = fread($impfile, filesize('install/qf_dbase.str'));
            eval('$bases = '.$data.';');
            foreach ($bases as $name=>$struct)
                $QF->DBImport->Apply_Table_Struct($struct, true);
            $QF->DBImport->Parse_SQL_File('install/content.sql', false);

            $QF_Pagedata['step'] = 'finish_install';
            $QF_Pagedata['form_cont'] = $QF->LNG->Lang('SETUP_STEP_DATA_UPD_OK');
        }
        else
        {
            $QF->DBImport->Parse_SQL_File('install/structure.sql', false);
            $QF->DBImport->Parse_SQL_File('install/content.sql', false);
            if (file_exists('install/empty.sql'))
                $QF->DBImport->Parse_SQL_File('install/empty.sql', false);

            $QF_Pagedata['step'] = 'admin_setup';
            $QF_Pagedata['form_cont'] = $QF->LNG->Lang('SETUP_STEP_DATA_NEW_OK');
        }

        if (is_array($QF->DBImport->errlog) && count($QF->DBImport->errlog))
            qf_file_put_contents('DBImp_err.log', qf_array_definition($QF->DBImport->errlog));
    }
    else
    {
        $QF_Pagedata['step'] = $SET_step;
        $QF_Pagedata['action'] = 'GO';
        $QF->VIS->Add_Node('SETUP_STEP_DATA_IMP', 'FORM_CONT', 0, $tmpl);
    }
}
elseif ($SET_step=='data_acc')
{
    $QF_Pagedata['step_info'] = $QF->LNG->Lang('SETUP_STEP_DATA_ACC');
    $QF_Dbase_Config = qf_file_get_carray('data/cfg/database.qfc');

    if ($SET_act == 'GO') {

        $QF_New_Dbase_Config = Array(
            'location' => $QF->GPC->Get_String('dblocation', QF_GPC_POST, QF_STR_LINE),
            'database' => $QF->GPC->Get_String('dbname', QF_GPC_POST, QF_STR_LINE),
            'username' => $QF->GPC->Get_String('dbuser', QF_GPC_POST, QF_STR_LINE),
            'password' => $QF->GPC->Get_String('dbpasswd', QF_GPC_POST, QF_STR_LINE),
            'prefix'   => $QF->GPC->Get_String('dbkey', QF_GPC_POST, QF_STR_LINE),
            );

        if ($QF_New_Dbase_Config['password'] == '*old password*')
            $QF_New_Dbase_Config['password'] = ($QF_Dbase_Config) ? $QF_Dbase_Config['password'] : '';


        if (!$QF->DBase->Connect($QF_New_Dbase_Config))
        {
            $SET_error = $QF->LNG->Lang('SETUP_STEP_DATA_ERR');
        }
        else
        {
            if ( empty($QF_New_Dbase_Config['prefix']) )
                $QF_New_Dbase_Config['prefix'] = 'qf2_';

            qf_mkdir_recursive('data/cfg/', 0700);
            $conffile = fopen('data/cfg/database.qfc', 'w');
            if (!$conffile)
                  $SET_error = $QF->LNG->Lang('SETUP_STEP_DATA_ERR_FILE');
            else
            {
                foreach ($QF_New_Dbase_Config as $cfg => $val)
                    fwrite($conffile, $cfg.' => '.$val."\n");
                fclose($conffile);
                chmod('data/cfg/database.qfc', 0600);
            }


        }

        unset($QF_Dbase_Config);

        if (!$SET_error)
        {
            $QF_Pagedata['step'] = 'dbase_import';
            $QF_Pagedata['form_cont'] = $QF->LNG->Lang('SETUP_STEP_DATA_OK');
        }
        else
        {
            $QF_Pagedata['step'] = $SET_step;
            $QF_Pagedata['form_cont'] = $SET_error;
        }
    }
    else
    {
        $tmpl = Array(
            'db_loc'  => 'localhost',
            'db_key'  => 'qf2_' );

        if ($QF_Dbase_Config)
        {
            $tmpl=Array(
                'db_loc'  => $QF_Dbase_Config['location'],
                'db_name' => $QF_Dbase_Config['database'],
                'db_user' => $QF_Dbase_Config['username'],
                'db_pass' => '*old password*',
                'db_key'  => $QF_Dbase_Config['prefix'],
                'db_loaded' => 1 );
        }

        $QF_Pagedata['step'] = $SET_step;
        $QF_Pagedata['action'] = 'GO';
        $QF->VIS->Add_Node('SETUP_STEP_DATA_ACC', 'FORM_CONT', 0, $tmpl);
    }
}

$QF->VIS->Add_Data_Array(0, $QF_Pagedata);
$QF->HTTP->Clear();
$QF->HTTP->Write($QF->VIS->Make_HTML());
$QF->HTTP->Send_Buffer($QF->Session->Get('recode_out'));


?>
