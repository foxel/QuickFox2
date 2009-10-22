<?php

// -------------------------------------------------------------------------- \\
// QuickFox kernel 2 configs interface class                                  \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if ( defined('QF_KERNEL_CONFIG_LOADED') )
        die('Scripting error');

define('QF_KERNEL_CONFIG_LOADED', True);
define('QF_KERNEL_CONFIG_CACHENAME', 'GLOBAL_CONFIG');
define('QF_KERNEL_CONFIG_FILENAME', QF_DATA_ROOT.'cfg/configs.qfc');
define('QF_KERNEL_CONFIG_DEFGROUP', 'COMMON');
define('QF_KERNEL_CONFIG_DEFSCHEME', 'root');


class QF_Configs
{
    var $values = Array();        //Config vals for groups of configs
    var $val_upd = Array();       //Updates list for saving
    var $grp_upd = Array();       //Updates list for saving
    var $listeners = Array();     //Value update listeners
    var $need_save = false;
    var $loaded = false;
    var $cur_scheme = QF_KERNEL_CONFIG_DEFSCHEME;

    function QF_Configs()
    {
        $this->values = Array(QF_KERNEL_CONFIG_DEFSCHEME => Array());
    }

    function Load($set_empty = false)
    {
        global $QF;

        if ($this->loaded)
            return true;

        if ($set_empty)
        {
            $this->values = Array(QF_KERNEL_CONFIG_DEFSCHEME => Array());
            $this->loaded = true;
            return true;
        }

        if ($chdata = $QF->Cache->Get(QF_KERNEL_CONFIG_CACHENAME))
        {
            $this->values = $chdata;
            $QF->Timer->Time_Log('Configs loaded (from global cache)');
        }
        elseif ($QF->DBase->Check() && is_array($sqldata = $QF->DBase->Do_Select_All('config')) )
        {
            foreach ( $sqldata as $setting )
                if ( !empty($setting['name']) )
                {
                    if (empty($setting['scheme']))
                        $setting['scheme'] = QF_KERNEL_CONFIG_DEFSCHEME;
                    if (empty($setting['parent']))
                        $setting['parent'] = QF_KERNEL_CONFIG_DEFGROUP;
                    if (!$setting['scalar'])
                        $setting['value'] = unserialize($setting['value']);

                    $this->values[strtolower($setting['scheme'])][strtolower($setting['parent'])][strtolower($setting['name'])] = $setting['value'];
                }

            $QF->Cache->Set(QF_KERNEL_CONFIG_CACHENAME, $this->values);
            $QF->Timer->Time_Log('Configs loaded (from SQL database)');
            if (file_exists(QF_KERNEL_CONFIG_FILENAME))
                unlink(QF_KERNEL_CONFIG_FILENAME);
        }
        elseif (file_exists(QF_KERNEL_CONFIG_FILENAME) && ($fdata = unserialize(qf_file_get_contents(QF_KERNEL_CONFIG_FILENAME))))
        {
            $this->values = $fdata;
            $QF->Cache->Set(QF_KERNEL_CONFIG_CACHENAME, $this->values);
            $QF->Timer->Time_Log('Configs loaded (from cfg file)');
        }

        $this->loaded = true;
    }

    function Select_Scheme($scheme)
    {
        if (!$this->loaded)
            $this->Load();

        $this->cur_scheme = ($scheme) ? strtolower($scheme) : QF_KERNEL_CONFIG_DEFSCHEME;

        return true;
    }

    function List_Schemes()
    {        if (!$this->loaded)
            $this->Load();

        return array_keys($this->values);
    }

    function Get($name, $group = false, $if_notset = null, $from_scheme = false)
    {
        if (!$this->loaded)
            $this->Load();

        if (!$group)
            $group = QF_KERNEL_CONFIG_DEFGROUP;

        $scheme = ($from_scheme !== false)
             ? (($from_scheme) ? strtolower($from_scheme) : QF_KERNEL_CONFIG_DEFSCHEME)
             : $this->cur_scheme;
        $group = strtolower($group);
        $name  = strtolower($name);

        if (!isset($this->values[$scheme]) || !isset($this->values[$scheme][$group]) || !isset($this->values[$scheme][$group][$name]))
            $scheme = QF_KERNEL_CONFIG_DEFSCHEME;

        if (isset($this->values[$scheme]) && isset($this->values[$scheme][$group])
            && is_array($this->values[$scheme][$group]) && isset($this->values[$scheme][$group][$name]))
            return $this->values[$scheme][$group][$name];

        return $if_notset;
    }

    function Get_Full($name, $group = false)
    {
        if (!$this->loaded)
            $this->Load();

        if (!$group)
            $group = QF_KERNEL_CONFIG_DEFGROUP;

        $group = strtolower($group);
        $name  = strtolower($name);

        $result = Array();
        foreach ($this->values as $scheme => $sdata)
            if (isset($sdata[$group]) && is_array($sdata[$group]) && isset($sdata[$group][$name]))
                $result[$scheme] = $sdata[$group][$name];

        return $result;
    }

    function Set($name, $value, $group = false, $global = false, $to_scheme = false)
    {
        global $QF;

        if (!$this->loaded)
            $this->Load();

        if (!$group)
            $group = QF_KERNEL_CONFIG_DEFGROUP;

        $scheme = ($to_scheme !== false)
             ? (($to_scheme) ? strtolower($to_scheme) : QF_KERNEL_CONFIG_DEFSCHEME)
             : (($global) ? QF_KERNEL_CONFIG_DEFSCHEME : $this->cur_scheme);
        $group = strtolower($group);
        $name  = strtolower($name);

        $this->values[$scheme][$group][$name] = $value;

        if ($global)
        {
            if ($to_scheme === false)
                foreach($this->values as $scname => $scont)
                    if ($scname != $scheme)
                    {
                        $this->values[$scname][$group][$name] = null;
                        $this->val_upd[$scname][$group][$name] = true;
                    }

            $this->val_upd[$scheme][$group][$name] = true;

            if (is_array($this->listeners[$group]) && isset($this->listeners[$group][$name]))
                foreach ($this->listeners[$group][$name] as $func_link)
                    call_user_func_array($func_link, Array($value, $name, $group, $scheme));

            $this->need_save = true;
        }
    }

    function Add_Listener($name, $group, $func_link)
    {
        if (!$group)
            $group = QF_KERNEL_CONFIG_DEFGROUP;

        $group = strtolower($group);
        $name  = strtolower($name);

        if (is_callable($func_link))
        {
            $this->listeners[$group][$name][] = $func_link;
            return true;
        }
        else
            return false;
    }

    function _Close()
    {
        global $QF;

        if (!$this->loaded)
            return true;

        if (!$this->need_save)
            return true;

        $QF->Cache->Set(QF_KERNEL_CONFIG_CACHENAME, $this->values);

        if ($QF->DBase->Check())
        {
            foreach ($this->val_upd as $scname => $sccont)
              if (is_array($sccont))
                foreach ($sccont as $grname => $grcont)
                  if (is_array($grcont))
                    foreach ($grcont as $valname => $doupd)
                        if ($doupd)
                        {
                            $value = $this->values[$scname][$grname][$valname];
                            if (is_null($value))
                                $QF->DBase->Do_Delete('config', Array( 'scheme' => $scname, 'parent' => $grname, 'name' => $valname));
                            else
                            {
                                if (!is_scalar($value))
                                {
                                    $value = serialize($value);
                                    $scalar = false;
                                }
                                else
                                    $scalar = true;

                                $QF->DBase->Do_Insert('config', Array( 'scheme' => $scname, 'parent' => $grname, 'name' => $valname, 'value' => $value, 'scalar' => $scalar), true);
                            }
                        }
        }
        else
            qf_file_put_contents(QF_KERNEL_CONFIG_FILENAME, serialize($this->values));
    }
}

?>
