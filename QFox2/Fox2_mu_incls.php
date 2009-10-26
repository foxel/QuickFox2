<?php

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');


class Fox2_mu_incls
{
    function Fox2_mu_incls()
    {        global $QF;
        $QF->Run_Module('MultiUser');
    }

    function _Start()
    {
        global $QF;
        $QF->LNG->Load_Language('multiuser');
    }

    function Page_Register(&$p_title)
    {
        global $QF;

        $QF->VIS->Load_Templates('multiuser');

        $p_title = $QF->LNG->Lang('PAGE_REGISTER_CAPT');
        $node = $QF->VIS->Create_Node('PAGE_REGISTER');
        if ($QF->User->UID)
            $QF->VIS->Add_Data($node, 'CURUSER', $QF->User->uname);
        if ($QF->Session->Get_Status(QF_SESSION_USEURL) || ($QF->Config->Get('max_autologins', 'users', 1) < 1))
            $QF->VIS->Add_Data($node, 'DISABLE_AL', 1);

        return $node;

    }

    function Script_Register()
    {
        global $QF, $FOX;

        $QF->Run_Module('UList');

        $nlogin = substr($QF->GPC->Get_String('new_login', QF_GPC_POST, QF_STR_LINE), 0, 16);
        $nuname = $QF->USTR->Str_Substr($QF->GPC->Get_String('new_name', QF_GPC_POST, QF_STR_LINE), 0, 16);
        $npasssrc1 = $QF->GPC->Get_String('new_pass', QF_GPC_POST, QF_STR_LINE);
        $npasssrc2 = $QF->GPC->Get_String('new_pass_dup', QF_GPC_POST, QF_STR_LINE);
        $nemail = substr($QF->GPC->Get_String('new_email', QF_GPC_POST, QF_STR_LINE), 0, 32);

        $ascode = $QF->GPC->Get_String('asp_code', QF_GPC_POST, QF_STR_WORD);
        if (!$FOX->Check_ASCode($ascode))
            return Array($QF->LNG->lang('ERR_REGISTER_WRONG_ASCODE'), $FOX->Gen_URL('fox2_register'), true);
        elseif ($npasssrc1 != $npasssrc2)
            return Array($QF->LNG->lang('ERR_REGISTER_PASSES_DIFF'), $FOX->Gen_URL('fox2_register'), true);
        elseif (!qf_str_is_email($nemail))
            return Array($QF->LNG->lang('ERR_REGISTER_WRONG_EMAIL'), $FOX->Gen_URL('fox2_register'), true);
        elseif (!$QF->UList->Create_User($nlogin, $npasssrc1, $nuname, $nemail))
        {
            $err = $QF->UList->Get_Error();
            switch ($err)
            {
                case QF_ERRCODE_USERLIB_BAD_LOGIN:
                    return Array($QF->LNG->lang('ERR_REGISTER_BAD_LOGIN'), $FOX->Gen_URL('fox2_register'), true);
                case QF_ERRCODE_USERLIB_BAD_NPASS:
                    return Array($QF->LNG->lang('ERR_REGISTER_BAD_PASS'), $FOX->Gen_URL('fox2_register'), true);
                case QF_ERRCODE_USERLIB_DUP_UNAME:
                    return Array($QF->LNG->lang('ERR_REGISTER_USED_NICK'), $FOX->Gen_URL('fox2_register'), true);
                case QF_ERRCODE_USERLIB_DUP_LOGIN:
                    return Array($QF->LNG->lang('ERR_REGISTER_USED_LOGIN'), $FOX->Gen_URL('fox2_register'), true);
                default:
                    return Array($QF->LNG->lang('ERR_REGISTER_NOT_CREATED'), $FOX->Gen_URL('fox2_register'), true);
            }
        }

        $result = sprintf($QF->LNG->Lang('RES_REGISTER_OK'), $nuname, $nlogin);
        return Array($result, $FOX->Gen_URL('fox2_login'));

    }
}

?>