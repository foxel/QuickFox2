<?php

// -------------------------------------------------------------------------- \\
// This is the main QuickFox 2 file                                           \\
//                                                       (c) LION 2007 - 2008 \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if (defined('QF_FOX_STARTED'))
    die('Scripting error');

define('QF_FOX_STARTED', true);

define('QUICKFOX_DIR', dirname(__FILE__).'/');

// data directories constants
define('QF_INC_ROOT',    QF_DATA_ROOT.'includes/');
define('QF_MODULES_DIR', QF_DATA_ROOT.'modules/');
define('QF_PAGES_DIR',   QF_INC_ROOT.'pages/');
define('QF_SCRIPTS_DIR', QF_INC_ROOT.'scripts/');

// Defining some usefull constants
define('QF_FOX2_LOGIN_MASK', '^[0-9\w_\+\-=\(\)\[\] ]{3,16}$');
define('QF_FOX2_SERVICE_PERIOD', 60);  // 1 minute service check period
define('QF_FOX2_RESULT_LIFETIME', 3600);
define('QF_FOX2_MAXULEVEL', 7);

define('QF_FOX2_URLTEMPS_CACHENAME', 'FOX2.URLTEMPS');
define('QF_FOX2_URLTEMPS_RW_CACHENAME', 'FOX2.RW_URLTEMPS');

class Fox2
{
    var $URL_temps;
    var $URL_domain = null;
    var $VIS_redefs = Array();
    var $err_traced = Array();

    function Fox2()
    {
        global $QF;
        if ($CL_Config = qf_file_get_carray(QUICKFOX_DIR.'modules.qfc'))
        {
            foreach ($CL_Config as $mod => $cfg)
            {
                $cfg = explode('|', $cfg);
                if (isset($cfg[1]) && $cfg[1])
                    $QF->Register_Module($mod, QUICKFOX_DIR.trim($cfg[1]), trim($cfg[0]));
                else
                    $QF->Register_Module($mod, QUICKFOX_DIR.trim($cfg[0]));
            }
        }

    }

    function _Start()
    {
        global $QF;
        $GLOBALS['FOX'] =& $this;

        //qf_file_put_contents('acc'.$QF->Timer->time, qf_array_definition($_REQUEST));

        $QF->Timer->Time_Log('FOX2 module started');

        if ($QF->GPC->Get_Bin('drop_cache', QF_GPC_GET))
            $QF->Cache->Clear();

        $QF->LNG->Load_Language('common');
        //$QF->Config->Set('linked_domains', Array('gallery' => 'www.quickfox.dev'), 'fox2');
        //$QF->Config->Set('basic_domain', 'quickfox.dev', 'fox2');

        // starting neded modules
        $QF->Run_Module('DSets');
        $QF->Run_Module('User');

        $revision = $QF->DSets->Get_DSet_Value('dev_rev_info', 'qf2');
        header('X-Powered-By: QuickFox 2 ['.$revision.'] (PHP/'.PHP_VERSION.')');

        // url reparsing
        $rw_id = $QF->GPC->Get_String('rw_id', QF_GPC_GET, QF_STR_WORD);
        $rw_data = $QF->GPC->Get_String('rw_data', QF_GPC_GET, QF_STR_LINE);
        // working with multidomain
        if ($QF->Config->Get('tgl_multidomain', 'fox2'))
        {
            $d_schemas = $QF->Config->Get('domain_schemas', 'fox2');
            $domain = strtolower($QF->HTTP->SrvName);
            if (!is_array($d_schemas))
                $d_schemas = Array();

            if (!isset($d_schemas[$domain]))
            {
                $d_schemas[$domain] = '';
                $QF->Config->Set('domain_schemas', $d_schemas, 'fox2', true);
            }
            elseif ($d_schemas[$domain])
                $QF->Config->Select_Scheme($d_schemas[$domain]);

            if (($domains = $QF->Config->Get('linked_domains', 'fox2'))
                && ($basic_domain = $QF->Config->Get('basic_domain', 'fox2'))
                && is_array($domains) && count($domains))
            {                list($link) = array_keys($domains, $domain);
                if ($link)
                {                    $this->URL_domain = $basic_domain;
                    if ($rw_id && $rw_id != $link)
                        $rw_data = $rw_id.'/'.$rw_data;
                    $rw_id = $link;
                }
            }
        }
        // rewrited url reparsing
        if ($rw_id)
        {
            $this->_Parse_RW($rw_id, $rw_data);
            $QF->Events->Set_On_Event('HTTP_HTML_parse', Array(&$this, 'HTML_FullURLs') );
            $QF->Events->Set_On_Event('HTML_block_parse', Array(&$this, 'HTML_FullURLs') );
        }

        // running the services
        $next_start = $QF->Config->Get('service_nextstart', 'temp');
        if ($next_start < $QF->Timer->time)
        {
            $QF->Run_Module('Services');
            $QF->Config->Set('service_nextstart', ($QF->Timer->time + QF_FOX2_RESULT_LIFETIME), 'temp', true);
        }

        // registering extended modules
        if ($r_mods = $QF->DSets->Get_DSet('fox_modules'))
            foreach ($r_mods as $r_mod => $r_cfg)
            {
                $r_cfg = explode('|', $r_cfg);
                if (isset($r_cfg[1]) && $r_cfg[1])
                    $QF->Register_Module($r_mod, QF_MODULES_DIR.trim($r_cfg[1]), trim($r_cfg[0]));
                else
                    $QF->Register_Module($r_mod, QF_MODULES_DIR.trim($r_cfg[0]));
            }

        //$this->_Parse_PathInfo();

        if ($domain = $QF->Config->Get('cookie_domain'))
            $QF->HTTP->Set_Cookie_Domain($domain);

        $QF->Events->Set_On_Event('VIS_PreParse',  Array(&$this, 'On_VIS_Prep') );
        $QF->Events->Set_On_Event('EJS_PreParse',  Array(&$this, 'On_EJS_Prep') );
        if ($QF->Config->Get('vis_redefined', 'fox2'))
            $QF->Events->Set_On_Event('VIS_RawParse',  Array(&$this, '_VISUserMods_Add') );

        // running any autoruns from packages
        if ($ar_datas = $QF->DSets->Get_DSet('fox_autoruns'))
            foreach ($ar_datas as $arun)
                if (isset($arun['module'], $arun['method']))
                {
                    $QF->Run_Module($arun['module']);
                    qf_func_call(Array(&$QF->$arun['module'], $arun['method']));
                }


        // first try to determine if we are running a datapage
        if ($data_page = $QF->GPC->Get_String('sr', QF_GPC_GET, QF_STR_WORD))
            $this->Run_Data($data_page);

        // open session
        $QF->Session->Open_Session();
        if ($QF->Config->Get('sid_urls', 'session', true) && $QF->Config->Get('cookie_check', 'session') && $QF->Session->Get_Status(QF_SESSION_USEURL | QF_SESSION_LOADED) == QF_SESSION_USEURL)
            $QF->HTTP->Redirect($QF->HTTP->Request);

        // then try to determine if we are running an AJAX parser
        if ($aj_page = $QF->GPC->Get_String('aj', QF_GPC_GET, QF_STR_WORD))
            $QF->User->is_spider || $this->Run_AJAX($aj_page);

        // then try to run script if needed
        if ($script = $QF->GPC->Get_String('script', QF_GPC_POST, QF_STR_WORD))
            $QF->User->is_spider || $this->Run_Script($script);

        // if we don't need to run scripts or datapages we will draw a normal page
        $QF->Run_Module('VIS');
        $vis_style = $QF->Config->Get('vis_style', '', 'qf_def');
        $vis_style = explode('|', $vis_style);
        if (!isset($vis_style[1]))
            $vis_style[1] = QF_KERNEL_VIS_COMMON;
        $QF->VIS->Configure(Array(
            'root_node' => 'GLOBAL_HTMLPAGE',
            'style' => $vis_style[0],
            'CSS' => $vis_style[1],
            ), false);


        if ($QF->Config->Get('css_separate'))
        {
            $QF->VIS->Configure(Array('force_append' => false));
            $QF->VIS->Add_Data(0, 'META', '<link rel="stylesheet" type="text/css" href="'.$this->Gen_URL('fox2_css_data', $vis_style, true).'" />');
        }
        $this->Link_JScript('common');
        $this->Link_JScript('effects');

        $QF->VIS->Set_VConsts(Array('MAXULEVEL' => QF_FOX2_MAXULEVEL));
        $QF->VIS->Load_Templates();

        $page = $QF->GPC->Get_String('st', QF_GPC_GET, QF_STR_WORD);

        if (!$page)
            $page = $QF->Config->Get('index_dpage', 'fox2', 'pages');

        $this->Show_Page($page);
    }

    function Run_Data($name)
    {
        global $QF;

        if (connection_aborted())
            trigger_error('FOX: connection was aborted at client side', E_USER_WARNING);
        if (($data = $QF->DSets->Get_DSet_Value('fox_datapages', $name)) && isset($data['module'], $data['method']))
        {
            $QF->Run_Module($data['module']);
            qf_func_call(Array(&$QF->$data['module'], $data['method']));
            trigger_error('FOX: datapage "'.$name.'" did not end execution by it\'s own', E_USER_NOTICE);
        }
        else
            trigger_error('FOX: there is no "'.$name.'" datapage data', E_USER_WARNING);

        header ($QF->HTTP->SERVER["SERVER_PROTOCOL"].' 501 Not Implemented');
        $QF->HTTP->Redirect(($QF->HTTP->Referer && !$QF->HTTP->ExtRef) ? $QF->HTTP->Referer : QF_INDEX);
    }

    function Run_AJAX($name)
    {
        global $QF;

        $AJAX_DATA = null;
        $AJAX_STATUS = 200;

        if (connection_aborted())
            trigger_error('FOX: connection was aborted at client side', E_USER_WARNING);
        elseif ($name == 'PING') // session pinger works
        {

        }
        elseif (($data = $QF->DSets->Get_DSet_Value('fox_ajax_scripts', $name)) && isset($data['module'], $data['method']))
        {
            $QF->Run_Module($data['module']);
            $AJAX_DATA = qf_func_call_ref(Array(&$QF->$data['module'], $data['method']), $AJAX_STATUS);
        }
        else
        {
            trigger_error('FOX: there is no "'.$name.'" AJAX parser file', E_USER_WARNING);
            $AJAX_STATUS = 404;
            $AJAX_DATA = 'Parser Not Found';
        }

        $AJAX_DATA = '{ status: '.intval($AJAX_STATUS).', data: '.qf_value_JS_definition($AJAX_DATA).' }';
        if ($QF->GPC->Get_String('AJMethod', QF_GPC_GET, QF_STR_WORD) == 'form')
        {
            $AJAX_ID = $QF->GPC->Get_String('AJID', QF_GPC_GET, QF_STR_WORD);
            $AJAX_DATA = 'top && top.QF_AJAX && top.QF_AJAX.Form_Ready(\''.$AJAX_ID.'\', '.$AJAX_DATA.' );';
            $AJAX_DATA = '<?xml version="1.0"?>
                <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
                <html xmlns="http://www.w3.org/1999/xhtml">
                <head><!--Meta-Content-Type--></head><body><script type="text/javascript">
                // JavaScript Starts Here <![CDATA[
                '.$AJAX_DATA.'
                //]]> JavaScript ends here
                </script></body></html>';
        }
        else
            $QF->HTTP->do_HTML = false;

        $QF->HTTP->Clear();
        $QF->HTTP->Write($AJAX_DATA);
        $QF->HTTP->Send_Buffer($QF->Session->Get('recode_out'));
    }

    function Run_Script($name)
    {
        global $QF;

        if (connection_aborted())
            trigger_error('FOX: connection was aborted at client side', E_USER_WARNING);
        elseif ($data = $QF->DSets->Get_DSet_Value('fox_scripts', $name))
        {
            Ignore_User_Abort(True);

            $result = false;

            if (isset($data['module'], $data['method']))
            {
                $QF->Run_Module($data['module']);
                $result = qf_func_call(Array(&$QF->$data['module'], $data['method']));
                if (!$result)
                {
                    trigger_error('FOX: script "'.$file.'" did not set result by it\'s own', E_USER_NOTICE);
                    $result = Array(Lang('SCR_FINISHED_NORES'));
                }
                elseif (!is_array($result))
                    $result = Array($result);
            }
            elseif (isset($data['include']) && ($file = QF_SCRIPTS_DIR.$data['include'].'.php') && file_exists($file))
            {
                include ($file);
                trigger_error('FOX: script-include "'.$file.'" did not end execution by it\'s own', E_USER_NOTICE);
                $result = Array(Lang('SCR_FINISHED_NORES'));
            }
            else
            {
                $result = Array(Lang('ERR_SCR_CODE_NOTFOUND'), '', true);
                trigger_error('FOX: there is no "'.$name.'" script data', E_USER_WARNING);
            }

            call_user_func_array(Array(&$this, 'Set_Result'), $result);
        }
        else
            trigger_error('FOX: there is no "'.$name.'" script data', E_USER_WARNING);
    }

    function Show_Page($pg)
    {
        global $QF;

        // running prepage autoruns from packages
        if ($ar_datas = $QF->DSets->Get_DSet('fox_prepages'))
            foreach ($ar_datas as $arun)
                if (isset($arun['module'], $arun['method']))
                {
                    $QF->Run_Module($arun['module']);
                    qf_func_call(Array(&$QF->$arun['module'], $arun['method']), $pg);
                }

        $QF->VIS->Add_Data_Array(0, Array(
            'SITE_NAME' => ($site_name = $QF->Config->Get('site_name')) ? htmlspecialchars($site_name) : 'QuickFox 2',
            ) );
        if ($adv = $QF->Config->Get('adv_data', 'visual'))
            $QF->VIS->Add_Data(0, 'ADV', $adv);


        if ((list($data, $pkg) = $QF->DSets->Get_DSet_Value('fox_pages', $pg, true)) && isset($data['module'], $data['method']))
        {
            // redirecting on multidomain redirection enabled
            if ($QF->Config->Get('tgl_multidomain', 'fox2') && $QF->Config->Get('tgl_mdomain_redir', 'fox2') && ($domains = $QF->Config->Get('package_domains', 'fox2')) && is_array($domains) && count($domains))
            {
                if (isset($domains[$pkg]))
                {
                    $domain = $domains[$pkg];
                    $domain = preg_replace('#^http\:(?:/)*|/$#i', '', $domain);
                    if ($domain != $QF->HTTP->SrvName)
                    {
                        $url = qf_full_url($QF->HTTP->Request, false, $domain);
                        $QF->HTTP->Redirect($url);
                    }
                }
            }

            $QF->Run_Module($data['module']);
            $p_subtitle = $p_title = '';
            $d_result = false;
            $d_status = 200;
            $pg_node = qf_func_call_arr(Array(&$QF->$data['module'], $data['method']), Array(&$p_title, &$p_subtitle, &$d_result, &$d_status));
            if ($pg_node)
                $QF->VIS->Append_Node($pg_node, 'PAGE_CONT', 0);
            if ($p_title)
                $QF->VIS->Add_Data(0, 'PAGE_TITLE', $p_title);
            if ($p_subtitle)
                $QF->VIS->Add_Data(0, 'PAGE_SUBTITLE', $p_subtitle);
            if ($d_status != 200)
                $QF->HTTP->Set_Status($d_status);
            if ($d_result && is_array($d_result))
                call_user_method_array('Draw_Result', $this, $d_result);
        }
        elseif (($file = QF_PAGES_DIR.$pg.'_inc.php') && file_exists($file))
            include ($file);
        else
        {
            $this->Draw_Result(Lang('ERR_INCLUDE_LOAD'), ($QF->HTTP->Referer && !$QF->HTTP->ExtRef) ? $QF->HTTP->Referer : QF_INDEX, true);
            $QF->HTTP->Set_Status(404);
        }


        $this->Draw_menu();
        if ($QF->User->UID || ($QF->Config->Get('show_login_pan') && !$QF->User->is_spider))
            $this->Draw_Panel('login');


        if ($recode = $QF->GPC->Get_String('recode', QF_GPC_GET, QF_STR_WORD))
            $QF->Session->Set('recode_out', $recode);
        elseif ($recode !== null)
            $QF->Session->Drop('recode_out');

        // running postpage autoruns from packages
        if ($ar_datas = $QF->DSets->Get_DSet('fox_postpages'))
            foreach ($ar_datas as $arun)
                if (isset($arun['module'], $arun['method']))
                {
                    $QF->Run_Module($arun['module']);
                    qf_func_call(Array(&$QF->$arun['module'], $arun['method']), $pg, $pg_node);
                }

        $QF->HTTP->Clear();
        $QF->HTTP->Write($QF->VIS->Make_HTML());
        $QF->HTTP->Send_Buffer($QF->Session->Get('recode_out'));
    }

    function Draw_Menu()
    {
        global $QF;
        $menubts = $QF->Config->Get('menu_buttons', 'visual');
        $cur_butt = false;
        if (is_array($menubts))
            foreach ($menubts as $butt)
                if (is_array($butt) && qf_str_is_url($butt['url']))
                {
                    $butt['url'] = qf_full_url($butt['url'], true, $this->URL_domain);
                    if (isset($butt['is_sub']) && $butt['is_sub'] && $cur_butt)
                        $QF->VIS->Add_Node('MENU_SUBBUTTON', 'SUBS', $cur_butt, $butt);
                    else
                        $cur_butt = $QF->VIS->Add_Node('MENU_BUTTON', 'MENU_ITEMS', 0, $butt);
                }
    }

    function Draw_Panel($name)
    {
        global $QF;


        if (($data = $QF->DSets->Get_DSet_Value('fox_panels', $name)) && isset($data['module'], $data['method']))
        {
            $CUR_PANEL = $QF->VIS->Add_Node('PANEL_BODY', 'PANELS', 0, false, $name.'_panel');
            $QF->Run_Module($data['module']);
            qf_func_call(Array(&$QF->$data['module'], $data['method']), $CUR_PANEL);
            return true;
        }
        else
            return false;
    }


    function Gen_ASCode()
    {
        global $QF;

        $new_code = qf_short_uid();

        if ($QF->Session->Set('QFox2_ASCode', $new_code))
            return $new_code;

        return false;
    }

    function Check_ASCode($code)
    {
        global $QF;

        $real_code = $QF->Session->Get('QFox2_ASCode');

        return ($code == $real_code);
    }


    function Set_Result($text, $redir_to = '', $is_err = false, $code = '')
    {
        global $QF;

        $res_id = qf_short_uid('res');

        $QF->DBase->Do_Delete('results', 'WHERE time < '.($QF->Timer->time - QF_FOX2_RESULT_LIFETIME));
        if (!$QF->Session->SID)
            $QF->Session->Open_Session();
        $ins_data = Array(
            'res_id'   => $res_id,
            'code'     => $code,
            'text'     => (string) $text,
            'is_err'   => ($is_err) ? 1 : 0,
            'tr_errs'  => ($is_err) ? implode('|', qf_array_parse($this->err_traced, 'dechex')) : '',
            'time'     => $QF->Timer->time,
            'got_at'   => $QF->HTTP->Request,
            'redir_to' => $redir_to,
            'u_sid'    => $QF->Session->SID,
            'u_id'     => $QF->User->UID,
            );

        if ($QF->DBase->Do_Insert('results', $ins_data))
            $QF->HTTP->Redirect($this->Gen_URL('fox2_showresult_page', Array($res_id)));
        else
        {
            trigger_error('FOX: error setting result data', E_USER_WARNING);
            // if user must be regirected we'll try do this without setting result data
            if ($redir_to)
                $QF->HTTP->Redirect($redir_to);
        }

    }

    function Trace_Error($err_code)
    {
        global $QF;
        $this->err_traced[] = (int) $err_code;
        if ($QF->Config->Get('log_errors', 'loggers', 1))
            $this->Log_Event($err_code, 'err_log');
    }

    function Link_JScript($name)
    {
        global $QF;
        static $separate = null;
        static $linked = Array();

        if (!isset($QF->VIS))
            return false;

        if (is_null($separate))
            $separate = (bool) $QF->Config->Get('css_separate');

        if (in_array($name, $linked))
            return true;

        $linked[] = $name;

        if ($separate)
            return $QF->VIS->Add_Data(0, 'JS_BLOCKS', '<script type="text/javascript" src="'.$this->Gen_URL('fox2_js_data', $name, true).'" ></script>');
        else
            return $QF->VIS->Load_EJS($name);
    }

    function Describe_ErrCodes($descr_errs = false)
    {
        global $QF;
        if (!$descr_errs)
            $descr_errs = $this->err_traced;

        if (is_array($descr_errs) && count($descr_errs))
        {
            $output = Array();
            $packs = Array();
            foreach ($descr_errs as $err_code)
            {
                $err_code = '0x'.dechex($err_code);
                $info = $pkg = null;
                list ($info, $pkg) = $QF->DSets->Get_DSet_Value('err_messages', $err_code, true);

                if (!$info)
                    continue;
                if (isset($info['sys']) && $info['sys'])
                    continue;

                if (!in_array($pkg, $packs))
                {
                    array_push($packs, $pkg);
                    $QF->LNG->Load_Language($pkg.'_errs');
                }


                $message = $info['mess'];
                if (substr($message, 0, 2) == 'L_')
                    $message = $QF->LNG->Lang(substr($message, 2));

                $output[$err_code] = $message;
            }

            return $output;
        }
        return false;
    }

    function Draw_Result($text, $redir_to = '', $is_err = false, $descr_errs = false)
    {
        global $QF;

        $hurl = '';

        if ($redir_to)
        {
            $url = qf_full_url($redir_to, false, $this->URL_domain);
            $QF->Events->Call_Event_Ref('HTTP_URL_Parse', $url );
            $hurl = strtr($url, Array('&' => '&amp;'));

            header('Refresh: 7; URL="'.$url.'"');
        }
        $res_node = $QF->VIS->Add_Node('FOX_RESULT_WINDOW', 'PAGE_CONT', 0, Array(
            'text'    => $text,
            'is_err'  => ($is_err) ? 1 : null,
            'redir_url' => $hurl,
            ) );
        $QF->VIS->Add_Data(0, 'PAGE_TITLE', ($is_err) ? Lang('RES_CAPT_ERR') : Lang('RES_CAPT'));

        if ($descr_errs = $this->Describe_ErrCodes($descr_errs))
        {
            $err_nodes = Array();
            foreach($descr_errs as $code => $mess)
                $err_nodes[] = Array('ERRCODE' => $code, 'MESSAGE' => $mess);

            if (count($err_nodes))
                $QF->VIS->Add_Node_Array('FOX_RESULT_ERR_ITEM', 'ERRORS', $res_node, $err_nodes);
        }
    }

    function Gen_Pages($pages, $cur_page, $params = false)
    {
        if (!is_array($params))
            $params = Array();

        if ($pages < 2)
            return false;

        $cur_page = max(1, min($cur_page, $pages));

        $draw_pages = Array();
        $pp = False;
        for($stt = 1; $stt <= $pages; $stt++)
        {
            if ($stt <= 4 || $stt >= ($pages - 3) || Abs($stt - $cur_page) < 3) {
                $draw_pages[] = $params + Array('PAGE' => $stt, 'CUR' => ($stt == $cur_page) ? true : null);
                $pp = true;
            }
            elseif ($pp)
            {
                $draw_pages[] = Array('SEPAR' => true);
                $pp = false;
            }
        }

        return $draw_pages;
    }

    function Log_Event($event, $log_name = 'common')
    {
        global $QF;
        $log_name = substr(preg_replace('#\W#', '_', $log_name), 0, 32);
        $ins_data = Array(
            'log_id'    => $log_name,
            'time'      => $QF->Timer->time,
            'event'     => $event,
            'cl_ip'     => $QF->HTTP->IP_int,
            'cl_req'    => $QF->HTTP->Request,
            'cl_uagent' => $QF->HTTP->UAgent,
            );
        return $QF->DBase->Do_Insert('fox_logs', $ins_data);
    }

    function Gen_URL($url_id, $params = Array(), $with_amps = false, $full = false, $no_enc = false)
    {
        global $QF;
        static $vars;
        if (!$vars)
            $vars = Array(
                '{QF_SID}' => $QF->Session->SID,
                '{TIME}' => $QF->Timer->time,
                );

        if (!is_array($params))
            $params = Array($params);
        if (!$url_id)
            return QF_INDEX;

        $url_id = strtoupper($url_id);

        if (!is_array($this->URL_temps))
            $this->_Load_URLS();

        if (isset($this->URL_temps[$url_id]))
        {
            if (!$no_enc)
                $params = qf_array_parse($params, 'qf_url_encode_part');

            $string = strtr($this->URL_temps[$url_id], $vars);
            $masks = Array();
            $a_params = $r_params = Array();

            $count = preg_match_all('#\%(\d+)\$|\%\w#', $string, $masks, PREG_PATTERN_ORDER);
            if ($count > 0)
            {
                $masks = $masks[1];
                $count = max($count, max($masks));
                $r_params = explode('|', str_repeat('|', $count));
            }

            foreach($params as $key => $param)
            {
                if (is_int($key))
                    $r_params[$key] = $param;
                else
                    $a_params[] = qf_url_encode_part($key).'='.$param;
            }
            $string = qf_sprintf_arr($string, $r_params);
            if (count($a_params))
                $string.= ((strstr($string, '?')) ? '&' : '?').implode('&', $a_params);

            $string = ($full) ? qf_full_url($string, $with_amps, $this->URL_domain) : (($with_amps) ? preg_replace('#\&(?![A-z]+;)#', '&amp;', $string) : str_replace('&amp;', '&', $string));
            return $string;
        }
        else
            return '#';
    }

    function On_VIS_Prep(&$indata, $type = false)
    {
        if (!is_array($this->URL_temps))
            $this->_Load_URLS();

        $indata = preg_replace_callback('#\{(?>(F|R)?URL:((?:\w+|\"[^\"]+\"|\|)+))\}#',Array(&$this, '_VISParse_URL_CB'),$indata);
    }

    function On_EJS_Prep(&$indata, $type = false)
    {
        if (!is_array($this->URL_temps))
            $this->_Load_URLS();

        $indata = preg_replace_callback('#\{(?>(F|R)?URL:((?:\w+|\"[^\"]+\"|\|)+))\}#',Array(&$this, '_VISParse_URL_CB'),$indata);
    }

    function _VISUserMods_Add(&$indata, $style, $part)
    {
        global $QF;

        if (!isset($this->VIS_redefs[$style]) && !$this->_VISUserMods_Preload($style))
            return;

        if (isset($this->VIS_redefs[$style]) && isset($this->VIS_redefs[$style][$part]))
            $indata.= $this->VIS_redefs[$style][$part];
    }

    /*function _VISUserMods_Preload()
    {        global $QF;
        $QF->VIS->Load_Templates('user_redefined');
    } */

    function _VISUserMods_Preload($style)
    {        global $QF;
        if (!$QF->Check_Module('VIS'))
            return false;

        $style = strtolower($style);
        if (isset($this->VIS_redefs[$style]))
            return true;

        $cachename = QF_KERNEL_VIS_VPREFIX.$style.'_redefs';
        $cfile = QF_STYLES_DIR.$style.'/user_redefined.vis';
        if ($data = $QF->Cache->Get($cachename))
            $this->VIS_redefs[$style] = $data;
        elseif ($data = qf_file_get_contents($cfile))
        {            preg_match_all('#\<\<part \'(\w+)\'\>\>|[^\<]+|\<#', $data, $struct, PREG_SET_ORDER);
            $data = Array();
            $p_name = QF_KERNEL_VIS_COMMON;
            foreach ($struct as $part)
            {
                if ($part[1])
                    $p_name = strtolower($part[1]);
                elseif (isset($data[$p_name]))
                    $data[$p_name].= $part[0];
                else
                    $data[$p_name] = $part[0];
            }
            $QF->Cache->Set($cachename, $data);
            $this->VIS_redefs[$style] = $data;
            return true;
        }
        else
            return false;
    }

    function _VISParse_URL_CB($matches)
    {
        $code = $matches[2];
        $code = explode('|', $code);

        if (!($url_id = strtoupper($code[0])))
            return '#';
        if (!isset($this->URL_temps[$url_id]))
            return '#';

        if ($params = array_slice($code, 1))
        {
        	foreach ($params as $id => $val)
                $params[$id] = (is_numeric($val{0})) ? (int) $val : (($val{0} == '"') ? rawurlencode(substr($val, 1, -1)) : '{URLEN:'.$val.'}');
        }
        else
            $params = Array();

        $url = $this->Gen_URL($url_id, $params, true, false, true);
        if ($matches[1] == 'F')
            $url = qf_full_url($url, true, $this->URL_domain);
        elseif ($matches[1] == 'R') // && $url{0} != '/'
        {
            $comps = parse_url($url);
            if (!$comps['scheme'])
                $url = '{QF_ROOT}'.$url;
        }

        return $url;
    }

    function _Load_URLS()
    {
        global $QF;
        static $consts = Array(
            '{QF_INDEX}' => QF_INDEX,
            );


        if (count($this->URL_temps))
            return false;

        $cachename = ($QF->Config->Get('gen_rwurls'))
            ? QF_FOX2_URLTEMPS_RW_CACHENAME : QF_FOX2_URLTEMPS_CACHENAME;

        if ($data = $QF->Cache->Get($cachename))
            $this->URL_temps = $data;
        elseif (list($data, $pkgs) = $QF->DSets->Get_DSet('urltemplates', true))
        {
            if ($QF->Config->Get('gen_rwurls') && (list($rwdata, $rwpkgs) = $QF->DSets->Get_DSet('mod_rw_urlts', true)))
            {
                $data = $rwdata + $data;
                $pkgs = $rwpkgs + $pkgs;
            }

            $data = str_replace(array_keys($consts), array_values($consts), $data);


            if ($QF->Config->Get('tgl_multidomain', 'fox2'))
            {                $domains  = $QF->Config->Get('package_domains', 'fox2');
                $ldomains = $QF->Config->Get('linked_domains', 'fox2');
                $basedomain = $QF->Config->Get('basic_domain', 'fox2');
                if (!is_array($domains))
                    $domains  = Array();
                if (!is_array($ldomains) || !$basedomain)
                    $ldomains = Array();

                if (count($domains) || count($ldomains))
                {
                    $domains  = preg_replace('#^http\:(?:/)*|/$#i', '', $domains);
                    $ldomains = preg_replace('#^http\:(?:/)*|/$#i', '', $ldomains);
                    $basedomain = preg_replace('#^http\:(?:/)*|/$#i', '', $basedomain);

                    foreach ($data as $key => $val)
                    {
                        $cur_pkg = $pkgs[$key];
                        list($cur_link, $cur_url) = explode('/', $val, 2);
                        if ($cur_link && isset($ldomains[$cur_link]))
                        {
                            $domain = $ldomains[$cur_link];
                            $val = qf_full_url($cur_url, true, $domain);
                            $data[$key] = $val;
                            if (isset($domains[$cur_pkg]))
                                unset($domains[$cur_pkg]);
                        }
                    }
                    foreach ($data as $key => $val)
                    {
                        $cur_pkg = $pkgs[$key];
                        if (isset($domains[$cur_pkg]))
                        {
                            $domain = $domains[$cur_pkg];
                            $val = qf_full_url($val, true, $domain);
                            $data[$key] = $val;
                        }
                    }
                }
            }

            $QF->Cache->Set($cachename, $data);
            $this->URL_temps = $data;
        }
        else
            $this->URL_temps = Array();

        return true;
    }

    function _Parse_PathInfo()
    {
        global $QF;
        if (!($pt_info = $QF->HTTP->PtInfo))
            return false;

        $pt_info = explode('/', $pt_info);
        $pt_id   = $pt_info[0];
        $pt_parse = $QF->DSets->Get_DSet_Value('ptinfo_masks', $pt_id);
        if (is_array($pt_parse))
        {
            $pt_parse = array_values($pt_parse);
            $pt_data = Array();
            $imax = min(count($pt_parse), count($pt_info));
            for ($i=0; $i<$imax; $i++)
                $pt_data[$pt_parse[$i]] = $pt_info[$i];

            trigger_error(qf_array_definition($pt_data), E_USER_WARNING);

            $QF->GPC->Set_Raws($pt_data, QF_GPC_GET);
        }
    }


    function _Parse_RW($rw_id, $rw_data)
    {
        global $QF;

        if (!$rw_id)
            return false;

        $rw_mask = $QF->DSets->Get_DSet_Value('RW_masks', $rw_id);
        $rw_data = preg_replace('#\.\w*$#D', '', $rw_data);

        if (!$rw_mask)
        {
            $QF->GPC->Set_Raws(Array('st' => $rw_id, 'dpath' => $rw_data), QF_GPC_GET);
        }
        elseif (is_array($rw_mask))
        {
            $rw_data = explode('/', $rw_data);
            $rw_mask = preg_replace('#\$(\d+)#e', '(isset(\$rw_data[\1])) ? \$rw_data[\1] : ""', $rw_mask);

            $QF->GPC->Set_Raws($rw_mask, QF_GPC_GET);
        }
        else
            return false;

        return true;
    }

    function HTML_FullURLs(&$buffer)
    {
        $buffer = preg_replace_callback('#(<(a|form|img|link|script)\s+[^>]*?)(href|action|src)\s*=\s*(\"([^\"<>\(\)]*)\"|\'([^\'<>\(\)]*)\'|[^\s<>\(\)]+)#i', Array(&$this, '_FullURLs_Parse_Callback'), $buffer);
    }

    function _FullURLs_Parse_Callback($vars)
    {
        Global $QF;
        if (!is_array($vars))
            return false;

        if (isset($vars[6]))
        {
            $url = $vars[6];
            $bounds = '\'';
        }
        elseif (isset($vars[5]))
        {
            $url = $vars[5];
            $bounds = '"';
        }
        else
        {
            $url = $vars[4];
            $bounds = '';
        }

        if (qf_str_is_url($url) == 2)
            $url = qf_full_url($url, true, $this->URL_domain);

        return $vars[1].$vars[3].'='.$bounds.$url.$bounds;

    }

}

$QF->Register_Module('FOX', __FILE__, 'Fox2');
$QF->Run_Module('FOX');

?>
