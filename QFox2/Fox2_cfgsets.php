<?php

// -------------------------------------------------------------------------- \\
// Configurer addidtional module. Provides some interfaces                    \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_FOX_STARTED') )
        die('Hacking attempt');

if (defined('QF_CONFIGURER_LOADED'))
    die('Scripting error');

define('QF_CONFIGURER_LOADED', true);

// cache prefix
define('QF_CFGSETS_CACHE_PREFIX', 'CFGSETS.');

class Fox2_cfgsets
{
    var $filter   = Array();

    function Fox2_cfgsets()
    {

    }

    function _Start()
    {
        global $QF;

        $QF->LNG->Load_Language('qf2_cfgs');
        $filter = $QF->Config->Get('use_packs', 'qf2_sys');
        if (!$filter || !is_array($filter))
            $filter = Array('qf2', 'qf2_base', 'qf2_adm');
        else
        {
            $filter = qf_array_parse($filter, 'strtolower');
            $filter[] = 'qf2';
            $filter[] = 'qf2_base';
            $filter[] = 'qf2_adm';
            $filter = array_unique($filter);
        }

        $this->filter = $filter;
    }

    function Get_List($schemable = false)
    {
        global $QF;

        // loads config sets list
        $where = Array('package' => $this->filter);
        if ($schemable)
            $where['schemable'] = 1;
        $cfg_sets = array_unique($QF->DBase->Do_Select_All('config_sets', Array('set_id'), $where));
        sort($cfg_sets);
        return $cfg_sets;
    }

    function Get_ConfSet($cfgset = 'common')
    {
        global $QF;

        $cachename = QF_CFGSETS_CACHE_PREFIX.$QF->LNG->Ask().'.'.$cfgset;
        if ($conf_set = $QF->Cache->Get($cachename))
            return $conf_set;
        elseif ($configs = $QF->DBase->Do_Select_All('config_sets', '*', Array('set_id' => $cfgset, 'sec_set_id' => $cfgset), 'ORDER BY order_id DESC', QF_SQL_WHERE_OR))
        {
            $out_confs = Array();
            $used_modules = Array();
            foreach($configs as $conf)
            {
                if ($module = $conf['package'])
                {
                    if (!in_array($module, $used_modules))
                    {
                        $QF->LNG->Load_Language($module.'_cfgs');
                        $used_modules[] = $module;
                    }

                    if (!in_array($module, $this->filter)) // filtering by package
                        continue;
                }

                $cfgname = $conf['capt'];
                if (substr($cfgname, 0, 2) == 'L_')
                    $cfgname = $QF->LNG->Lang(substr($cfgname, 2));

                $o_conf = Array(
                    'cfg_parent' => $conf['cfg_parent'],
                    'cfg_name'   => $conf['cfg_name'],
                    'module'     => $module,
                    'capt'       => $cfgname,
                    'drops_ch'   => ($conf['drops_cache']) ? true : false,
                    'drops_cfs'  => ($conf['drops_confs']) ? explode('|', $conf['drops_confs']) : Array(),
                    'schemable'  => $conf['schemable'],
                    );


                $type_sets = explode('|', $conf['cfg_type']);
                $type = strtolower(array_shift($type_sets));
                if ($type == 'str' || $type == 'int' || $type == 'text')
                {
                    $o_conf['type'] = $type;
                    $par1 = array_shift($type_sets);
                    if (!is_numeric($par1))
                    {
                        $o_conf['subtype'] = $par1;
                        if ($o_conf['subtype'] == 'preg')
                            $o_conf['mask'] = $conf['src_data'];
                        $len1 = (int) array_shift($type_sets);
                    }
                    else
                        $len1 = (int) $par1;
                    $len2 = (int) array_shift($type_sets);
                    if ($len2 > $len1)
                    {
                        $o_conf['min'] = $len1;
                        $o_conf['max'] = $len2;
                    }
                    elseif ($len1 > 0)
                    {
                        $o_conf['min'] = 0;
                        $o_conf['max'] = $len1;
                    }

                    $out_confs[] = $o_conf;
                }
                elseif($type == 'bool')
                {
                    $o_conf['type'] = $type;
                    $out_confs[] = $o_conf;
                }
                elseif($type == 'dset')
                {
                    $o_conf['type'] = 'select';
                    $par1 = array_shift($type_sets);
                    $vars = $QF->DSets->Get_DSet($par1);
                    $o_conf['variants'] = $vars;
                    $out_confs[] = $o_conf;
                }
                elseif($type == 'select')
                {
                    $o_conf['type'] = $type;
                    $vars = unserialize($conf['src_data']);
                    foreach ($vars as $v_val => $v_name)
                        if (substr($v_name, 0, 2) == 'L_')
                            $vars[$v_val] = $QF->LNG->Lang(substr($v_name, 2));
                    $o_conf['variants'] = $vars;
                    $out_confs[] = $o_conf;
                }
            }

            $QF->Cache->Set($cachename, $out_confs);

            return $out_confs;
        }
        else
            trigger_error('ConfSets: configs set "'.$cfgset.'" is empty', E_USER_WARNING );
        return false;
    }
}
?>
