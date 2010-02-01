<?php

// -------------------------------------------------------------------------- \\
// QuickFox kernel 2 session management class                                 \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if ( defined('QF_KERNEL_SESSION_LOADED') )
        die('Scripting error');

define('QF_KERNEL_SESSION_LOADED', True);
define('QF_KERNEL_SESSION_CACHEPREFIX', 'KRNL_SESSIONS.');
define('QF_KERNEL_SESSION_LIFETIME', 3600);         // 1 hour  session lifetime

// status consts
define('QF_SESSION_OK'    , 1);
define('QF_SESSION_LOADED', 2);
define('QF_SESSION_USEURL', 4);
define('QF_SESSION_FIX'   , 8);

// quickfox sid cookie name
define('QF_SESSION_SID_NAME', 'SID');

class QF_Session
{
    var $SID        = '';           // Session ID
    var $sess_data  = Array();      // session variables

    var $sess_cache = Array();      // session cache data
    var $got_cache  = Array();      // session cache 'loaded' flags
    var $upd_cache  = Array();      // session cache 'need upd' flags
    var $drop_cache = Array();      // session cache 'need drop' flags

    var $clicks     = 1;            // session clicks stats

    var $started    = false;        // if the session is started
    var $loaded     = false;        // if the session is loaded

    var $status     = 0;            // status

    var $error      = false;        // lang code of error string to show session errors to user

    // constructor
    function QF_Session()
    {
        if (defined('QF_SESSION_CREATED'))
            trigger_error('Duplicate session manager creation!', 256);

        define('QF_SESSION_CREATED', true);
    }

    function Open_Session($fix = false, $no_url_mods = false)
    {
        global $QF;

        if ($this->started)
            return true;

        if ($fix)
            $this->status |= QF_SESSION_FIX;

        $this->SID = $QF->GPC->Get_String(QF_SESSION_SID_NAME, QF_GPC_COOKIE, QF_STR_HEX);
        if ($ForceSID = $QF->GPC->Get_String('ForceQFSID', QF_GPC_POST, QF_STR_HEX))
            $this->SID = $ForceSID;

        if (!$this->SID)
        {
            $this->status |= QF_SESSION_USEURL;
            $this->SID = $QF->GPC->Get_String(QF_SESSION_SID_NAME, QF_GPC_ALL, QF_STR_HEX);
        }

        if ($this->SID)
            $this->Load_Session();
        else
            $this->Create_Session();


        if ($this->status & QF_SESSION_OK)
        {            $this->started = true;

            // allows status changes and any special reactions
            $QF->Events->Call_Event_Ref('session_preopen', $this->status, $this->SID);

            if (!($this->status & QF_SESSION_FIX))
                $QF->HTTP->Set_Cookie(QF_SESSION_SID_NAME, $this->SID);

            // allows status changes and any special reactions
            $QF->Events->Call_Event_Ref('session_opened', $this->status, $this->SID);

            if (!$no_url_mods && ($this->status & QF_SESSION_USEURL) && $QF->Config->Get('sid_urls', 'session', true))
            {
                $QF->Events->Set_On_Event('HTTP_HTML_parse', Array(&$this, 'HTML_URLs_AddSID') );
                $QF->Events->Set_On_Event('HTTP_URL_Parse', Array(&$this, 'AddSID') );
            }

        }

        return true;
    }

    function Load_Session()
    {
        global $QF;

        if ($this->started)
            return true;

        $sess = ($QF->DBase->Check())
                ? $QF->DBase->Do_Select('sessions', '*', Array('sid' => $this->SID) )
                : $QF->Cache->Get(QF_KERNEL_SESSION_CACHEPREFIX.$this->SID);

        if ($sess)
        {
            if ($sess['ip'] != $QF->HTTP->IP_int)
                $sess = null;
            if ($sess['lastused'] < ($QF->Timer->time - QF_KERNEL_SESSION_LIFETIME))
                $sess = null;
        }
        else
            $sess = null;


        if (is_array($sess))
        {
            $this->sess_data = unserialize($sess['vars']);

            $this->clicks   = $sess['clicks'] + 1;

            $QF->Timer->Time_Log('Session data loaded');

            $this->status |= (QF_SESSION_OK | QF_SESSION_LOADED);
            $QF->Events->Call_Event_Ref('session_loaded', $this->sess_data, $this->status);

            return true;
        }
        else
            return $this->Create_Session();

    }

    function Create_Session()
    {
        global $QF;

        if ($this->started)
            return true;

        $this->SID       = md5(uniqid('SESS', true));
        $this->clicks    = 1;
        $this->sess_data = Array();


        $QF->Timer->Time_Log('Session data created');

        $this->Cache_Clear();

        $this->status |= (QF_SESSION_OK | QF_SESSION_USEURL);
        $QF->Events->Call_Event_Ref('session_created', $this->sess_data, $this->status );

        return true;
    }

    function Save_Session()
    {
        Global $QF;

        if (!$this->started)
            return false;

        if ($this->status & QF_SESSION_FIX)
            return true;

        $QF->Events->Call_Event_Ref('session_save', $this->sess_data);

        $q_arr = Array(
            'ip'       => $QF->HTTP->IP_int,
            'vars'     => serialize($this->sess_data),
            'lastused' => $QF->Timer->time,
            'clicks'   => $this->clicks,
            );

        if (!$QF->DBase->Check())
        {            $q_arr['sid'] = $this->SID;
            return $QF->Cache->Set(QF_KERNEL_SESSION_CACHEPREFIX.$this->SID, $q_arr);
        }
        elseif ($this->status & QF_SESSION_LOADED)
            $QF->DBase->Do_Update('sessions', $q_arr, Array('sid' => $this->SID) );
        else
        {
            $q_arr['sid'] = $this->SID;
            $q_arr['starttime'] = $QF->Timer->time;
            $QF->DBase->Do_Insert('sessions', $q_arr, true);
        }

        // delete old session data
        if ( $QF->DBase->Do_Delete('sessions', Array('lastused' => '< '.($QF->Timer->time - QF_KERNEL_SESSION_LIFETIME)), QF_SQL_USEFUNCS ) )
        {
            //let's clear old session cache data
            $QF->DBase->Do_Delete('sess_cache', Array('ch_stored' => '< '.($QF->Timer->time - QF_KERNEL_SESSION_LIFETIME)), QF_SQL_USEFUNCS );
        }

        return true;
    }

    function Get_Status($check = 255)
    {        return ($this->status & $check);
    }

    // session variables control
    function Get($query)
    {
        if (!$this->started)
            return false;

        $names = explode(' ', $query);
        if (count($names)>1)
        {
            $out = Array();
            foreach ($names as $name)
                $out[$name] = (isset($this->sess_data[$name])) ? $this->sess_data[$name] : null;

            return $out;
        }
        else
            return (isset($this->sess_data[$query])) ? $this->sess_data[$query] : null;
    }

    function Set($name, $val)
    {
        if (!$this->started)
            return false;

        return ($this->sess_data[$name] = $val);
    }

    function Drop($query)
    {
        if (!$this->started)
            return false;

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
    function Clear()
    {        if (!$this->started)
            return false;

        $this->sess_data = Array();

        $this->Cache_Clear();
        return true;
    }

    // session cache control
    function Cache_Get($name)
    {
        global $QF;

        if (!$this->started)
            return false;

        if (!in_array($name, $this->got_cache) && ($this->status & QF_SESSION_LOADED) && $QF->DBase->Check())
        {
            if ( list($tmp) = $QF->DBase->Do_Select('sess_cache', 'ch_data', Array('sid' => $this->SID, 'ch_name' => $name) ) )
            {
                $this->sess_cache[$name] = unserialize($tmp);
            }
            $this->got_cache[] = $name;
        }

        return ($this->sess_cache[$name]) ? $this->sess_cache[$name] : null;
    }

    function Cache_Add($name, $value)
    {
        if (!$this->started)
            return false;

        $this->sess_cache[$name] = $value;

        $this->got_cache[] = $name;
        $this->upd_cache[] = $name;
    }

    function Cache_Drop($name, $global=false)
    {
        if (!$this->started)
            return false;

        $this->sess_cache[$name] = null;

        if ($global)
            $this->drop_cache[] = $name;
        else
            $this->upd_cache[] = $name;
    }

    function Cache_Drop_List($list, $global=false)
    {
        if (!$this->started)
            return false;

        $names = explode(' ', $list);
        if (count($names)) {
            $out = Array();
            foreach ($names as $name)
                $this->Cache_Drop($name, $global);

            return true;
        }
        else
            return false;

    }

    function Cache_Clear()
    {
        global $QF;

        if (!$this->started || ($this->status & QF_SESSION_FIX))
            return false;

        $this->sess_cache = Array();
        $this->drop_cache = Array();
        $this->upd_cache = Array();

        if ($QF->DBase->Check())
            $QF->DBase->Do_Delete('sess_cache', Array('sid' => $this->SID) );

        return true;
    }


    function Cache_Do()
    {
        global $QF;

        if (!$this->started || ($this->status & QF_SESSION_FIX) || !$QF->DBase->Check())
            return false;

        $this->drop_cache = array_unique($this->drop_cache);
        $this->upd_cache = array_unique($this->upd_cache);

        foreach ($this->drop_cache as $name)
            $QF->DBase->Do_Delete('sess_cache', Array('ch_name' => $name) );

        foreach ($this->upd_cache as $name) {
            $query = false;
            if (!$this->sess_cache[$name]) {
                if (!in_array($name, $this->drop_cache))
                    $QF->DBase->Do_Delete('sess_cache', Array('ch_name' => $name, 'sid' => $this->SID) );
            }
            else
                $QF->DBase->Do_Insert('sess_cache', Array('sid' => $this->SID, 'ch_name' => $name, 'ch_data' => serialize($this->sess_cache[$name]), 'ch_stored' => $timer->time), true );
        }

        $this->drop_cache = Array();
        $this->upd_cache = Array();
        return true;
    }

    function AddSID($url, $ampersand=false)
    {
        $url=trim($url);

        if (!$this->started)
            return $url;

        $url = qf_url_add_param($url, QF_SESSION_SID_NAME, $this->SID, $ampersand);

        return $url;
    }

    function HTML_URLs_AddSID(&$buffer)
    {
        if (!$this->started || !($this->status & QF_SESSION_USEURL))
            return false;

        $buffer = preg_replace_callback('#(<(a|form)\s+[^>]*)(href|action)\s*=\s*(\"([^\"<>\(\)]*)\"|\'([^\'<>\(\)]*)\'|[^\s<>\(\)]+)#i', Array(&$this, 'SID_Parse_Callback'), $buffer);
        //$buffer = preg_replace('#(<form [^>]*>)#i', "\\1\n".'<input type="hidden" name="'.QF_SESSION_SID_NAME.'" value="'.$this->SID.'" />', $buffer);
    }

    function SID_Parse_Callback($vars)
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

        if ( preg_match('#^\w+:#', $url) )
             if ( strpos($url, $QF->HTTP->RootUrl)!==0 )
                return $vars[1].$vars[3].' = '.$bounds.$url.$bounds;

        if ( !strstr($url, QF_SESSION_SID_NAME.'=') && !strstr($url, 'javascript') )
        {
            $insert = ( !strstr($url, '?') ) ? '?' : '&amp;';
            $insert.= QF_SESSION_SID_NAME.'='.$this->SID;

            $url= preg_replace('#(\#|$)#', $insert.'\\1', $url, 1);
        }

        return $vars[1].$vars[3].' = '.$bounds.$url.$bounds;

    }

    function _Close()
    {
        if (!$this->started)
            return false;

        $this->Save_session();
    }
}


?>
