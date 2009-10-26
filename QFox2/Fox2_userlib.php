<?php

// ---------------------------------------------------------------------- \\
// QuickFox 2 userlib module.                                             \\
// ---------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');


if ( defined('QF_USERLIB_LOADED') )
        die('Scripting error');

define('QF_USERLIB_LOADED', True);

define('QF_USERLIB_AL_LIFETIME', 2592000);   // 30 days autologin lifetime
define('QF_USERLIB_GID_ONCETIME', 3600);     // 1 hour guest once entered lifetime
define('QF_USERLIB_GID_LIFETIME', 2592000);  // 30 days guest lifetime

define('QF_USERLIB_SPYDATA_CACHENAME', 'SPYMAP');
define('QF_USERLIB_SPYDATA_DATASET', 'spiders');

define('QF_USERLIB_AVATARS_BASEPATH', 'static/images/avatars/');

// Kookie names
define('QF_USERLIB_COOKIE_ALOGIN', 'ALogin');
define('QF_USERLIB_COOKIE_GUEST',  'Guest');
define('QF_USERLIB_COOKIE_VISKEY', 'Visitor');

// Error codes
define('QF_ERRCODE_USERLIB_BAD_LOGIN', 1001); // bad username given
define('QF_ERRCODE_USERLIB_BAD_NPASS', 1002); // bad new password given (too short)
define('QF_ERRCODE_USERLIB_DUP_LOGIN', 1003); // duplicate login
define('QF_ERRCODE_USERLIB_DUP_UNAME', 1004); // duplicate username

class Fox2_curuser
{
    var $UID        = 0;            // Detected User ID
    var $GID        = '';           // Detected Guest ID
    var $is_spider  = false;        // if this user is a spider

    var $uname      = '';           // Username
    var $acc_group  = 0;            // User access group id
    var $acc_level  = 0;            // User access level
    var $mod_level  = 0;            // User moderation level
    var $adm_level  = 0;            // User administration level
    var $is_frosen  = true;         // User is frozen
    var $readonly   = true;         // User is readonly
    var $lastseen   = null;         // Lastseen timestamp
    var $flags      = null;         // flags_info

    var $sess_data  = Array();

    var $error      = false;        // lang code of error string to show session errors to user

    function Fox2_curuser()
    {

    }

    function _Start()
    {
        Global $QF;

        $this->UID = 0;
        $this->GID = '';
        $this->uname = '';
        $this->acc_group = 0;
        $this->acc_level = 0;
        $this->mod_level = 0;
        $this->adm_level = 0;
        $this->is_frozen = true;
        $this->readonly  = true;
        $this->user_data = null;
        $this->guest_data = null;
        $this->spider_data = null;
        $this->lastseen = $QF->Timer->time;
        $this->flags = null;
        $this->sess_data  = Array();

        $QF->Events->Set_On_Event('session_loaded',  Array(&$this, 'On_Session_Load') );
        $QF->Events->Set_On_Event('session_created', Array(&$this, 'On_Session_Create') );
        $QF->Events->Set_On_Event('session_preopen',  Array(&$this, 'On_Session_Open') );
        $QF->Events->Set_On_Event('session_opened',  Array(&$this, 'On_Session_Opened') );
        $QF->Events->Set_On_Event('session_save',    Array(&$this, 'On_Session_Save') );
    }

    // user session variables control
    function S_Get($query)
    {
        $names = explode(' ', $query);
        if (count($names)>1)
        {
            $out = Array();
            foreach ($names as $name)
                $out[$name] = (isset($this->sess_data[$name])) ? $this->sess_data[$name] : null;

            return $out;
        }
        else
            return (isset($this->sess_data[$query]))
                ? $this->sess_data[$query]
                : $this->sess_data[$query];
    }

    function S_Set($name, $val)
    {
        return ($this->sess_data[$name] = $val);
    }

    function S_Drop($query)
    {
        $names = explode(' ', $query);
        if (count($names)) {
            $out = Array();
            foreach ($names as $name)
                unset ($this->sess_data[$name]);
            return true;
        }
        else
            unset ($this->sess_data[$query]);
        return true;
    }

    // totally clears session data
    function S_Clear()
    {
        $this->sess_data = Array();

        return true;
    }

    function CheckAccess($r_lvl, $w_lvl = 0, $acc_grp = 0, $cont_owner = 0)
    {
        $my_alvl = ($this->adm_level) ? QF_FOX2_MAXULEVEL : $this->acc_level;
        $my_mlvl = ($this->adm_level) ? QF_FOX2_MAXULEVEL : $this->mod_level;

        $w_lvl = max($r_lvl, $w_lvl);
        if ($this->UID && (($my_mlvl >= $r_lvl && $my_mlvl) || $cont_owner == $this->UID))
            return 3;
        elseif (!$this->is_spider && $my_alvl >= $w_lvl)
            return 2;
        elseif ($my_alvl >= $r_lvl)
            return 1;
        else
            return 0;
    }

    function CheckAuth($pass, $login = false)
    {        global $QF;

        if ($this->UID && ($cuser = $QF->DBase->Do_Select('users_auth', '*', Array('uid' => $this->UID))))
        {
            $pass_hash = md5($pass);
            if ($pass_salt = $cuser['pass_salt'])
                $pass_hash = md5(md5($pass_salt).$pass_hash);

            $result = ($login !== false)
                ? strcasecmp($login, $cuser['login']) == 0
                : true;

            return ($result && ($cuser['pass_hash'] == $pass_hash));
        }

        return false;
    }

    // users/guests loaders
    function Load_User($uid, $by_sid = false, $with_al = false, $no_updates = false)
    {
        Global $QF;

        if ($this->UID)
            $this->sess_data = Array();

        $this->UID = 0;

        if ($cuser = $QF->DBase->Do_Select('users', '*', Array('uid' => $uid)))
        {
            $force_logout = false;
            if ( $by_sid )
                $force_logout = $QF->GPC->Get_Bin('do_logout', QF_GPC_GET);

            if ( $by_sid && $cuser['sess_id'] != $by_sid )
            {
                $this->error = 'ERR_USER_SID_WRONG';

                $upd_data = Array(
                    'sess_id'     => '',
                    );

                $this->sess_data = Array();
                $QF->DBase->Do_Update('users', $upd_data, Array('uid' => $cuser['uid']));
                if ($with_al)
                    $QF->DBase->Do_Delete('users_al', Array('al_id' => $with_al));
                $QF->HTTP->Set_Cookie(QF_USERLIB_COOKIE_ALOGIN);
            }
            elseif ($force_logout)
            {
                $this->sess_data = Array();
                $QF->DBase->Do_Update('users', Array('sess_id' => ''), Array('uid' => $cuser['uid']));
                if ($with_al)
                    $QF->DBase->Do_Delete('users_al', Array('al_id' => $with_al));
                $QF->HTTP->Set_Cookie(QF_USERLIB_COOKIE_ALOGIN);
            }
            else
            {
                if (!$by_sid)
                    $this->sess_data = Array();
                $this->UID = (int) $cuser['uid'];
                $this->GID = '';
                $this->is_spider = false;

                // loading user data
                $this->uname     = $cuser['nick'];
                $this->acc_group = $cuser['acc_group'];
                $this->acc_level = $cuser['level'];
                $this->mod_level = $cuser['mod_lvl'];
                $this->adm_level = $cuser['adm_lvl'];
                $this->is_frozen = ($cuser['frozen']) ? true : false;
                $this->readonly  = ($cuser['readonly']) ? true : false;
                $this->lastseen  = $cuser['lastseen'];
                $this->flags     = ($cuser['us_flags']) ? unserialize($cuser['us_flags']) : null;

                $upd_data = Array(
                    'sess_id'     => $QF->Session->SID,
                    'lastseen'    => $QF->Timer->time,
                    'last_url'    => $QF->HTTP->Request,
                    'last_uagent' => $QF->HTTP->UAgent,
                    'last_ip'     => $QF->HTTP->IP_int,
                    );

                if (!$no_updates)
                    $QF->DBase->Do_Update('users', $upd_data, Array('uid' => $cuser['uid']));

                $QF->Events->Call_Event('user_data_loaded', $cuser['uid']);

                return true;
            }
        }
        else
            trigger_error('USER: Error selecting user by given user id', E_USER_WARNING);

        return false;
    }

    // tries to load guest by cookie then by given gid and then checks spiders - if no spiders found we'll create a new guest
    function Load_Guest($try_gid = '', $by_sid = false)
    {
        Global $QF, $FOX;

        if ($this->UID)
            return false;

        $gid = $QF->GPC->Get_String(QF_USERLIB_COOKIE_GUEST, QF_GPC_COOKIE, QF_STR_HEX);

        if ($gid)
            $long_reg = 2;
        elseif ($gid = $try_gid)
            $long_reg = 1;
        else
            $long_reg = 0;



        $this->UID = 0;
        $this->GID = '';
        $this->is_spider = false;

        // setting user data
        $this->uname     = '';
        $this->acc_group = 0;  //TODO - guest acc group
        $this->acc_level = 0;
        $this->mod_level = 0;
        $this->adm_level = 0;
        $this->is_frozen = false;  //TODO - guest frozen
        $this->readonly  = false;  //TODO - guest readonly
        $this->flags     = null;

        $try_guests  = $QF->Config->Get('try_guests', 'session');
        $try_spiders = $QF->Config->Get('try_spiders', 'session', true);

        if ($try_guests && $gid)
        {
            if ($guest = $QF->DBase->Do_Select('guests', '*', Array('gid' => $gid) ) )
            {
                $guest['views']++;

                $this->GID       = $guest['gid'];
                $this->uname     = $guest['nick'];
                $this->lastseen  = $guest['lastseen'];

                switch ($long_reg)
                {
                    case 2:
                        $reg_till = $QF->Timer->time + QF_USERLIB_GID_LIFETIME;
                        break;
                    case 1:
                        $reg_till = $QF->Timer->time + QF_KERNEL_SESSION_LIFETIME;
                        break;
                    default:
                        $reg_till = $QF->Timer->time + QF_USERLIB_GID_ONCETIME;
                }

                $upd_data = Array(
                    'lastseen'    => $QF->Timer->time,
                    'last_url'    => $QF->HTTP->Request,
                    'last_uagent' => $QF->HTTP->UAgent,
                    'last_ip'     => $QF->HTTP->IP_int,
                    'long_reg'    => $long_reg,
                    'reg_till'    => $reg_till,
                    'views'       => $guest['views'],
                    );

                $QF->DBase->Do_Update('guests', $upd_data, Array('gid' => $guest['gid']));

                $QF->HTTP->Set_Cookie(QF_USERLIB_COOKIE_GUEST, $gid, $reg_till );

                $QF->Events->Call_Event('guest_data_loaded', $gid);

                return true;
            }
        }

        if ($try_spiders && !$by_sid)
        {
            $spy_preg = '';
            // first lets load all the data we need
            if (list($spy_list, $spy_map, $spy_preg) = $QF->Cache->Get(QF_USERLIB_SPYDATA_CACHENAME))
            {
                // do nothing. all data is ready to use
            }
            elseif($spy_list = $QF->DSets->Get_DSet(QF_USERLIB_SPYDATA_DATASET))
            {
                $spy_map = $spy_preg = Array();
                foreach($spy_list as $spy_name => $spy_data)
                    foreach($spy_data['masks'] as $spy_mask)
                        $spy_map[strtolower(trim($spy_mask))] = $spy_name;
                uksort($spy_map, 'qf_sorter_by_length');
                foreach ($spy_map as $spy_mask => $spy_name)
                    $spy_preg[] = preg_quote($spy_mask, '#');
                $spy_preg = '#'.implode('|', $spy_preg).'#i';
                $QF->Cache->Set(QF_USERLIB_SPYDATA_CACHENAME, Array($spy_list, $spy_map, $spy_preg));
            }

            // let's find out our spiders :)
            if ($spy_preg && preg_match($spy_preg, $QF->HTTP->UAgent, $match))
            {
                $this->is_spider = true;
                $spy_name = $spy_map[strtolower($match[0])];
                $stats_data = Array(
                    'visits' => '++ 1',
                    'last_time' => $QF->Timer->time,
                    'last_ip'   => $QF->HTTP->IP_int,
                    'last_uagent' => $QF->HTTP->UAgent,
                    );
                if (!$QF->DBase->Do_Update('spiders_stats', $stats_data, Array('sp_name' => $spy_name), QF_SQL_USEFUNCS))
                {
                    $stats_data['visits'] = 1;
                    $stats_data['sp_name'] = $spy_name;
                    $QF->DBase->Do_Insert('spiders_stats', $stats_data);
                }
                if ($QF->Config->Get('log_spiders', 'loggers'))
                    $FOX->Log_Event('"'.$spy_name.'" spider visited the site', 'spy_log');
            }
        }

        if ($try_guests && !$this->is_spider)
        {
            $new_gid = md5(uniqid($QF->Timer->time, true));

            $reg_till = $QF->Timer->time + QF_USERLIB_GID_ONCETIME;

            $new_guest = Array(
                'gid'         => $new_gid,
                'lastseen'    => $QF->Timer->time,
                'last_url'    => $QF->HTTP->Request,
                'last_uagent' => $QF->HTTP->UAgent,
                'last_ip'     => $QF->HTTP->IP_int,
                'long_reg'    => 0,
                'reg_till'    => $reg_till,
                'views'       => 1,
                );

            $QF->DBase->Do_Insert('guests', $new_guest);
            // deleting old users
            $QF->DBase->Do_Delete('guests', Array('reg_till' => '< '.$QF->Timer->time), QF_SQL_USEFUNCS);

            $this->sess_data = Array();
            $this->GID      = $new_gid;
            $this->lastseen = $new_guest['lastseen'];

            $QF->HTTP->Set_Cookie(QF_USERLIB_COOKIE_GUEST, $new_gid, $reg_till );

            $QF->Events->Call_Event('guest_data_loaded', $new_gid);

            return true;

        }
    }

    function Script_Login()
    {
        global $QF, $FOX;

        $login = $QF->GPC->Get_String('login', QF_GPC_POST, QF_STR_LINE);
        $pass  = $QF->GPC->Get_String('pass', QF_GPC_POST, QF_STR_LINE);
        $do_al = $QF->GPC->Get_Bin('set_auto', QF_GPC_POST);

        if ($cuid = $this->UID)
        {
            $QF->DBase->Do_Update('users', Array('sess_id' => ''), Array('uid' => $cuid));
            if ($u_al = $this->S_Get('U_AL'))
            {
                $QF->DBase->Do_Delete('users_al', Array('al_id' => $u_al));
            }
            $QF->Session->Drop('UID');
            $this->S_Clear();
            $QF->HTTP->Set_Cookie(QF_USERLIB_COOKIE_ALOGIN);
        }

        $QF->Session->Cache_Clear();
        if ($cuser = $QF->DBase->Do_Select('users_auth', '*', Array('login' => $login)))
        {
            $uid = $cuser['uid'];
            $pass_hash = md5($pass);
            if ($pass_salt = $cuser['pass_salt'])
                $pass_hash = md5(md5($pass_salt).$pass_hash);

            if ($cuser['pass_hash'] == $pass_hash)
            {
                if ($this->Load_User($uid))
                {
                    $new_salt = substr(md5(uniqid('QF_salt', true)), rand(0, 24), 7);
                    $new_hash = md5(md5($new_salt).md5($pass));

                    $autologin = ($do_al) ? md5(uniqid('QF_AL', true)) : '';

                    $upd_data = Array(
                        'lastauth'  => $QF->Timer->time,
                        'pass_hash' => $new_hash,
                        'pass_salt' => $new_salt,
                        );

                    if ($QF->DBase->Do_Update('users_auth', $upd_data, Array('uid' => $cuser['uid'])))
                    {
                        if ($do_al)
                        {
                            $al_sig = ($secure_level = $QF->Config->Get('autologin_secure', 'users', 1))
                                ? $QF->HTTP->Get_Client_Signature($secure_level - 1)
                                : '';

                            $ins_data = Array(
                                'al_id'  => $autologin,
                                'al_sig' => $al_sig,
                                'uid'    => $cuser['uid'],
                                'al_started' => $QF->Timer->time,
                                'al_lastuse' => $QF->Timer->time,
                                );

                            if (($max_al = $QF->Config->Get('max_autologins', 'users', 1)) > 1)
                            {
                                $max_al--;
                                $als = $QF->DBase->Do_Select('users_al', Array('al_id', 'uid'), Array('uid' => $cuser['uid']), 'ORDER BY al_lastuse DESC', QF_SQL_SELECTALL);
                                if (count($als) > $max_al)
                                {
                                    $als = array_slice($als, $max_al);
                                    $del_als = Array();
                                    foreach ($als as $al_d)
                                        $del_als[] = $al_d['al_id'];
                                    $QF->DBase->Do_Update('users_al', Array('uid' => 0), Array('uid' => $cuser['uid'], 'al_id' => $del_als));
                                }
                            }
                            else
                                $QF->DBase->Do_Update('users_al', Array('uid' => 0), Array('uid' => $cuser['uid']));

                            if (($max_al > 0) && $QF->DBase->Do_Insert('users_al', $ins_data))
                            {
                                $QF->HTTP->Set_Cookie(QF_USERLIB_COOKIE_ALOGIN, $autologin, $QF->Timer->time + QF_USERLIB_AL_LIFETIME );
                                $this->S_Set('U_AL', $autologin);
                            }
                            else
                                $QF->HTTP->Set_Cookie(QF_USERLIB_COOKIE_ALOGIN);
                        }
                        else
                            $QF->HTTP->Set_Cookie(QF_USERLIB_COOKIE_ALOGIN );
                    }

                    $QF->Events->Call_Event('user_authorized', $cuser['uid']);

                    $QF->Session->Set('UID', $uid);
                    $QF->Session->Drop('GID');
                    return Array(Lang('RES_LOGIN_LOGGED'), QF_INDEX); // it's OK
                }
            }
        }
        // if any error
        return Array(Lang('ERR_LOGIN_ERRDATA'), $FOX->Gen_URL('fox2_login'), true);
    }

    function On_Session_Load(&$sess_data, $sess_status)
    {
        Global $QF;

        $this->UID = 0;
        if (isset($sess_data['USESS_DATA']))
            $this->sess_data = $sess_data['USESS_DATA'];
        if (isset($sess_data['UID']))
            if ($uid = $sess_data['UID'])
            {
                $with_al = (isset($this->sess_data['U_AL']))
                    ? $this->sess_data['U_AL']
                    : false;

                if (!$this->Load_User($uid, $QF->Session->SID, $with_al, ($sess_status & QF_SESSION_FIX)))
                {
                    $this->sess_data = Array();
                    $QF->Session->Cache_Clear();
                }
            }

        if (!$this->UID)
        {
            $try_gid = (isset($sess_data['GID'])) ? $sess_data['GID'] : '';
            $this->Load_Guest($try_gid, true);
        }

        if (!$sess_data['UID'] = $this->UID)
            unset($this->sess_data['U_AL']);
        $sess_data['GID'] = $this->GID;
    }

    function On_Session_Create(&$sess_data)
    {
        Global $QF;

        $this->UID = 0;

        $autologin = $QF->GPC->Get_String(QF_USERLIB_COOKIE_ALOGIN, QF_GPC_COOKIE, QF_STR_HEX);

        //$client_vkey = $QF->GPC->Get_String(QF_USERLIB_COOKIE_VISKEY, QF_GPC_COOKIE, QF_STR_HEX);
        //$my_secret = $QF->Config->Get('qf_secret_string', 'users', null)
        //if (!$my_secret)
        //    $QF->Config->Set('qf_secret_string', $my_secret = qf_short_uid, 'users');
        //$needed_vkey = md5($QF->HTTP->Get_Client_Signature(0).$QF->HTTP->SrvName.$my_secret);


        if ($autologin && ($QF->Config->Get('max_autologins', 'users', 1) > 0))
        {
            $al_sig = ($secure_level = $QF->Config->Get('autologin_secure', 'users', 1))
                ? $QF->HTTP->Get_Client_Signature($secure_level - 1)
                : false;

            if ($cur_al = $QF->DBase->Do_Select('users_al', '*', Array('al_id' => $autologin)))
            {
                if ($cur_al['al_started'] > ($QF->Timer->time - QF_USERLIB_AL_LIFETIME))
                {
                    if ($cur_al['uid'] == 0)
                    {
                        $this->error = 'ERR_USER_AUTOLOGIN_CLOSED';
                        $QF->DBase->Do_Delete('users_al', Array('al_id' => $autologin));
                        $QF->HTTP->Set_Cookie(QF_USERLIB_COOKIE_ALOGIN);
                    }
                    elseif ($al_sig && $al_sig != $cur_al['al_sig'])
                    {
                        $this->error = 'ERR_USER_AUTOLOGIN_SECURE';
                        $QF->DBase->Do_Delete('users_al', Array('al_id' => $autologin));
                        $QF->HTTP->Set_Cookie(QF_USERLIB_COOKIE_ALOGIN);
                    }
                    elseif ($this->Load_User($cur_al['uid']))
                    {
                        $new_al = md5(uniqid('QF_AL', true));
                        $upd_data = Array(
                            'al_id' => $new_al,
                            'al_sig' => $al_sig,
                            'al_lastuse' => $QF->Timer->time,
                            );
                        if ($QF->DBase->Do_Update('users_al', $upd_data, Array('al_id' => $autologin)))
                        {
                            $QF->HTTP->Set_Cookie(QF_USERLIB_COOKIE_ALOGIN, $new_al, $QF->Timer->time + QF_USERLIB_AL_LIFETIME);
                            $this->sess_data['U_AL'] = $new_al;
                        }

                        $QF->Events->Call_Event('user_authorized', $cur_al['uid'], true);
                    }
                    else
                        trigger_error('USER: Error selecting user by autologin user id', E_USER_WARNING);
                }
                else
                {
                    $this->error = 'ERR_USER_AUTOLOGIN_OLD';
                    $QF->DBase->Do_Delete('users_al', Array('al_started' => '< '.($QF->Timer->time - QF_USERLIB_AL_LIFETIME)), QF_SQL_USEFUNCS);
                    $QF->HTTP->Set_Cookie(QF_USERLIB_COOKIE_ALOGIN);
                }
            }
            else
            {
                $this->error = 'ERR_USER_AUTOLOGIN_WRONG';
                $QF->HTTP->Set_Cookie(QF_USERLIB_COOKIE_ALOGIN);
            }
        }

        if (!$this->UID)
            $this->Load_Guest();

        $sess_data['UID'] = $this->UID;
        $sess_data['GID'] = $this->GID;
    }

    function On_Session_Open(&$sess_status)
    {
        Global $QF;

        if ($this->is_spider)
            $sess_status = ($sess_status & ~QF_SESSION_USEURL) | QF_SESSION_FIX;
    }

    function On_Session_Opened(&$sess_status)
    {
        Global $QF;

        if ($this->error)
            $QF->FOX->Set_Result(Lang($this->error), '', true);
    }

    function On_Session_Save(&$sess_data)
    {
        $sess_data['USESS_DATA'] = $this->sess_data;
    }

}

class Fox2_UserList
{
    var $list   = Array();
    var $users  = Array();
    var $uinfo  = Array();
    var $id_qrs = Array();
    var $last_res = 0;

    // adds an ID query
    // $ids_arr might be an array or a string with '|' separator
    function Query_IDs($ids_arr)
    {
        if (is_array($ids_arr))
            $ids_arr = array_values($ids_arr);
        else
            $ids_arr = explode('|', $ids_arr);

        $ids_arr = array_unique($ids_arr);

        for ($i = count($ids_arr) - 1; $i >= 0; $i--)
            $ids_arr[$i] = (int) $ids_arr[$i];
        $this->id_qrs[] = $ids_arr;

        return true;
    }

    function Get_User($id)
    {
        global $QF;

        $id = (int) $id;
        if ($id<=0)
            return null;

        if (isset($this->users[$id]))
            return $this->users[$id];
        else
        {
            // we'll need to parse queries
            $found = false;
            // looking for $id in ID queries
            foreach($this->id_qrs as $i => $data)
            {
                if (in_array($id, $data))
                {
                    $add_users = $QF->DBase->Do_Select_All('users', '*', Array('uid' => $data));
                    $found = true;
                    unset ($this->id_qrs[$i]);
                    break;
                }
            }

            if (!$found)
                $add_users[] = $QF->DBase->Do_Select('users', '*', Array('uid' => $id));


            // reparsing loaded users
            foreach($add_users as $us_data)
            {
                $cid = $us_data['uid'];
                if (isset($this->users[$cid]))
                    continue;

                $us_data['us_info'] = unserialize($us_data['us_info']);
                $us_data['us_sets'] = unserialize($us_data['us_sets']);
                if (!is_array($us_data['us_info']))
                    $us_data['us_info'] = Array();
                if (!is_array($us_data['us_sets']))
                    $us_data['us_sets'] = Array();

                $this->users[$cid] = $us_data;
            }
            unset($add_users);

            if (isset($this->users[$id]))
                return $this->users[$id];
        }

        return null;
    }

    function Get_User_Auth($id)
    {        global $QF;
        return $QF->DBase->Do_Select('users_auth', Array('uid', 'login', 'sys_email', 'lastauth'), Array('uid' => $id));
    }

    function Get_List()
    {
        global $QF;

        if (!$this->list)
            $this->list = $QF->DBase->Do_Select_All('users', 'uid');

        return $this->list;
    }

    // genereates array with info to show on common pages
    function Get_UserInfo($id, $get_auth_info = false)
    {
        $id = (int) $id;
        if ($id<=0)
            return null;

        $uinfo = null;
        if (isset($this->uinfo[$id]))
            $uinfo = $this->uinfo[$id];
        else
        {
            $udata = $this->Get_User($id);
            if (is_null($udata))
                return null;

            $uinfo = Array(
                'uid'    => $udata['uid'],
                'nick'   => $udata['nick'],
                'level'  => $udata['level'],
                'm_lvl'  => $udata['mod_lvl'],
                'a_lvl'  => $udata['adm_lvl'],
                'a_grp'  => $udata['acc_group'],
                'av_sig' => $udata['av_sig'],
                'avatar' => isset($udata['avatar']) ? QF_USERLIB_AVATARS_BASEPATH.$udata['avatar'] : null,
                'regtime' => $udata['regtime'],
                'avatar_wh' => null,
                );

            if (!is_file($uinfo['avatar']))
                $uinfo['avatar'] = null;
            if ($udata['av_dims'])
            {
                $wh = explode('|', $udata['av_dims'].'|');
                $uinfo['avatar_wh'] = 'width: '.$wh[0].'px; height: '.$wh[1].'px;';
            }

            $uinfo['stats'] = Array(
                'lastseen' => $udata['lastseen'],
                'last_url' => $udata['last_url'],
                'last_ip'  => $udata['last_ip'],
                'l_uagent' => $udata['last_uagent'],
                );

            $this->uinfo[$id] = $uinfo;

        }

        if ($get_auth_info)
            $uinfo['auth_info'] = $this->Get_User_Auth($id);

        return $uinfo;
    }

    function Set_Levels($uids, $access = false, $mod = false, $adm = false)
    {
        global $QF;

        if (!$uids)
            return ($this->last_res = false);

        $upd = $upd_mods = Array();
        if ($access !== false)
        {
            $upd = Array('level' => $access = min($access, QF_FOX2_MAXULEVEL));
            if ($mod !== false)
              $upd['mod_lvl'] = min($access, $mod);
            else
              $upd_mods[] = Array(Array('mod_lvl' => $access), Array('mod_lvl' => '> '.$access));
            if ($adm !== false)
              $upd['adm_lvl'] = min($access, $adm, 3);
            else
              $upd_mods[] = Array(Array('mod_lvl' => $access), Array('mod_lvl' => '> '.$access));
        }
        elseif ($mod !== false)
        {
            $upd = Array('mod_lvl' => $mod = min($mod, QF_FOX2_MAXULEVEL));
            $upd_mods[] = Array(Array('level' => $mod), Array('level' => '< '.$mod));
            if ($adm !== false)
              $upd['adm_lvl'] = min($mod, $adm, 3);
            else
              $upd_mods[] = Array(Array('mod_lvl' => $mod), Array('mod_lvl' => '> '.$mod));
        }
        elseif ($adm !== false)
        {
            $upd = Array('adm_lvl' => $adm = min($adm, 3));
            $upd_mods[] = Array(Array('level' => $mod), Array('level' => '< '.$mod));
            $upd_mods[] = Array(Array('mod_lvl' => $adm), Array('mod_lvl' => '< '.$adm));
        }
        else
            return ($this->last_res = true);

        if ($QF->DBase->Do_Update('users', $upd, Array('uid' => $uids)))
        {
            foreach ($upd_mods as $upd_mod)
                $QF->DBase->Do_Update('users', $upd_mod[0], Array('uid' => $uids) + $upd_mod[1], QF_SQL_USEFUNCS);
            return ($this->last_res = true);
        }

        return ($this->last_res = false);
    }

    function Set_Auth($uid, $params)
    {        global $QF, $FOX;
        if ($odata = $QF->DBase->Do_Select('users_auth', '*', Array('uid' => $uid)))
        {            $ndata = Array();
            if (isset($params['pass']) && $params['pass'])
            {                if (strlen($params['pass']) < 5)
                    return false;

                $ndata['pass_salt'] = $new_salt = substr(md5(uniqid('QF_salt', true)), rand(0, 24), 7);
                $ndata['pass_hash'] = $new_hash = md5(md5($new_salt).md5($params['pass']));
            }
            if (isset($params['login']) && $params['login'])
            {
                if (!preg_match('#'.QF_FOX2_LOGIN_MASK.'#i', $params['login']))
                    return false;
                elseif ($QF->DBase->Do_Select('users_auth', 'uid', Array('login' => $params['login'])))
                    return false;

                $ndata['login'] = $params['login'];
            }
            if (isset($params['email']) && qf_str_is_email($params['email']))
                $ndata['sys_email'] = $params['email'];

            if ($ndata && $QF->DBase->Do_Update('users_auth', $ndata, Array('uid' => $uid)))
                return true;
        }

        return false;
    }

    function Set_BaseInfo($uid, $params)
    {        global $QF, $FOX;

        if ($odata = $QF->DBase->Do_Select('users_auth', '*', Array('uid' => $uid)))
        {
            $ndata = Array();
            if (isset($params['nick']) && $params['nick'])
            {
                if (strlen($params['nick']) < 3)
                    return false;
                if ($QF->DBase->Do_Select('users', 'uid', Array('nick' => $params['nick'])))
                    return false;

                $ndata['nick'] = $params['nick'];
            }
            if (isset($params['avatar']))
            {
                $params['avatar'] = preg_replace('#^'.preg_quote(QF_USERLIB_AVATARS_BASEPATH, '#').'#i', '', $params['avatar']);
                $av_file = QF_USERLIB_AVATARS_BASEPATH.$params['avatar'];
                $ndata['av_dims'] = '';
                if ($params['avatar'])
                {                    if (!is_file($av_file))
                        return false;
                    $iinfo = getimagesize($av_file);
                    chmod($av_file, 0644);
                    $ndata['av_dims'] = $iinfo[0].'|'.$iinfo[1];
                }

                $ndata['avatar'] = $params['avatar'];
            }
            if (isset($params['av_sig']))
                $ndata['av_sig'] = $params['av_sig'];
            if (isset($params['signature']))
                $ndata['signature'] = $params['signature'];

            if ($ndata && $QF->DBase->Do_Update('users', $ndata, Array('uid' => $uid)))
                return true;
        }

        return false;
    }

    function Create_User($login, $pass, $uname = false, $nemail = false, $level = 1)
    {
        global $QF;

        $err = 0;

        if (strlen($uname) < 3)
            $uname = $login;

        $level = ($level >= 0 && $level <= QF_FOX2_MAXULEVEL) ? $level : 1;
        $nemail = (qf_str_is_email($nemail)) ? $nemail  : '';

        if (!preg_match('#'.QF_FOX2_LOGIN_MASK.'#i', $login))
            $err = QF_ERRCODE_USERLIB_BAD_LOGIN;
        elseif (strlen($pass) < 5)
            $err = QF_ERRCODE_USERLIB_BAD_NPASS;
        elseif ($QF->DBase->Do_Select('users_auth', 'uid', Array('login' => $login)))
            $err = QF_ERRCODE_USERLIB_DUP_LOGIN;
        elseif ($QF->DBase->Do_Select('users', 'uid', Array('nick' => $uname)))
            $err = QF_ERRCODE_USERLIB_DUP_UNAME;

        if ($err)
        {
            $this->last_res = $err;
            return false;
        }

        $new_salt = substr(md5(uniqid('QF_salt', true)), rand(0, 24), 7);
        $new_hash = md5(md5($new_salt).md5($pass));

        $ins_data = Array(
            'nick'     => $uname,
            'regtime'  => $QF->Timer->time,
            'level'    => $level,
            'mod_lvl'  => 0,
            'adm_lvl'  => 0,
            'av_sig'   => '',
            );
        if ($nid = $QF->DBase->Do_Insert('users', $ins_data))
        {
            $ins_data = Array(
                'uid'       => $nid,
                'login'     => $login,
                'pass_hash' => $new_hash,
                'pass_salt' => $new_salt,
                'sys_email' => $nemail,
                );
            if ($QF->DBase->Do_Insert('users_auth', $ins_data))
            {
                $this->last_res = true;
                return $nid;
            }
            else
                $QF->DBase->Do_Delete('users', Array('uid' => $nid));
        }

        return ($this->last_res = false);
    }

    function Get_Error()
    {
        if ($this->last_res === true)
            return 0;
        else
            return $this->last_res;
    }
}

?>
