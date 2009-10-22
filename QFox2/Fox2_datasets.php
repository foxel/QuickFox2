<?php

// -------------------------------------------------------------------------- \\
// DataSets manager                                                           \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if (defined('QF_DATASETS_LOADED'))
    die('Scripting error');

define('QF_DATASETS_LOADED', true);

define('QF_DATASETS_CACHEPREFIX', 'DSET.');

class QF2_DSets
{
    var $datasets = Array();
    var $datapkgs = Array();
    var $injects  = Array();
    var $filter   = Array();
    var $packages = Array();


    function QF2_DSets()
    {

    }

    function _Start()
    {
        global $QF;

        $data = $QF->LNG->Get_Data_Links();
        $this->lang       =& $data['lang'];
        $this->lang_name  =& $data['lang_name'];
        $this->LNG_loaded =& $data['LNG_loaded'];

        $this->ReInit();
    }

    function ReInit()
    {
        global $QF;

        $this->datasets = Array();
        $this->datapkgs = Array();
        $this->injects  = Array();
        $this->filter   = Array();
        $this->packages = Array();

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

    function Package_Used($name)
    {
        $name = strtolower($name);
        if ($name && in_array($name, $this->filter))
            return true;

        return false;
    }

    function Get_Packages()
    {
        global $QF;

        if ($this->packages)
            return $this->packages;
        elseif ($data = $QF->DBase->Do_Select_All('datasets', '*', Array('set_id' => 'packages')))
        {
            $this->packages = Array();
            foreach($data as $rec)
            {
                $cont = $rec['scalar'] ? $rec['data'] : unserialize($rec['data']);
                if ($rec['data_id'] == $rec['package'])
                    $this->packages[$rec['data_id']] = $cont;
                else
                    trigger_error('DataSets: Suspicious package description detected: Package "'.$rec['package'].'" describes another package "'.$rec['data_id'].'". Data record ignored.', E_USER_WARNING);
            }
            return $this->packages;
        }
        else
            return Array();
    }

    function Get_DSet($set_name, $ret_pkgs = false )
    {
        if (!($set_name = preg_replace('#[^0-9A-z_\-\.]+#', '', $set_name)))
            return false;
        $set_name = strtolower($set_name);

        if (!isset($this->datasets[$set_name]))
            $this->Load_DSet($set_name);

        return ($ret_pkgs)
            ? Array($this->datasets[$set_name], $this->datapkgs[$set_name])
            : $this->datasets[$set_name];
    }

    function Get_DSet_Value($set_name, $val_name, $ret_pkgs = false)
    {
        if (!($set_name = preg_replace('#[^0-9A-z_\-\.]+#', '', $set_name)) || !strlen($val_name))
            return false;
        $set_name = strtolower($set_name);

        if (!isset($this->datasets[$set_name]))
            $this->Load_DSet($set_name);

        if (isset($this->datasets[$set_name][$val_name]))
            return ($ret_pkgs)
                ? Array($this->datasets[$set_name][$val_name], $this->datapkgs[$set_name][$val_name])
                : $this->datasets[$set_name][$val_name];
        else
            return null;
    }

    // this funtion is usefull if some module needs to modify dataset of other module to provide some advantages
    // injected dataset's content will be loaded over the original dataset
    function DSet_Inject($set_name, $inj_set_name)
    {
        if (!($set_name = preg_replace('#[^0-9A-z_\-\.]+#', '', $set_name)) ||
            !($inj_set_name = preg_replace('#[^0-9A-z_\-\.]+#', '', $inj_set_name)))
            return false;
        $set_name = strtolower($set_name);
        $inj_set_name = strtolower($inj_set_name);

        $this->injects[$set_name][] = $inj_set_name;

        return true;
    }

    // inner loaders
    function Load_DSet($set_name)
    {
        global $QF;

        if (!($set_name = preg_replace('#[^0-9A-z_\-\.]+#', '', $set_name)))
            return false;
        $set_name = strtolower($set_name);

        if (isset($this->datasets[$set_name]))
            return true;

        $cachename = QF_DATASETS_CACHEPREFIX.$this->lang_name.'.'.$set_name;

        if ($data = $QF->Cache->Get($cachename))
        {
            $this->datasets[$set_name] = $data['vals'];
            $this->datapkgs[$set_name] = $data['pkgs'];
        }
        elseif ($data = $QF->DBase->Do_Select_All('datasets', '*', Array('set_id' => $set_name, 'package' => $this->filter)))
        {
            $this->datasets[$set_name] = Array();
            foreach($data as $rec)
            {
                $cont = $rec['scalar'] ? $rec['data'] : unserialize($rec['data']);
                if (strlen($rec['lparse_sufx']))
                {                    $lname = $rec['package'];
                    $lname.= ($rec['lparse_sufx'] != '!') ? '_'.trim($rec['lparse_sufx']) : '';
                    if (!in_array($lname, $this->LNG_loaded))
                        $QF->LNG->Load_Language($name);
                    $cont = $QF->LNG->LangParse($cont);
                }
                $this->datasets[$set_name][$rec['data_id']] = $cont;
                $this->datapkgs[$set_name][$rec['data_id']] = $rec['package'];
            }

            if (isset($this->injects[$set_name]))
            {
                $injects = array_unique($this->injects[$set_name]);
                foreach ($injects as $inj_dset)
                    if ($data = $QF->DBase->Do_Select_All('datasets', '*', Array('set_id' => $inj_dset, 'package' => $this->filter)))
                    {
                        foreach($data as $rec)
                        {
                            $cont = $rec['scalar'] ? $rec['data'] : unserialize($rec['data']);
                            $this->datasets[$set_name][$rec['data_id']] = $cont;
                            $this->datapkgs[$set_name][$rec['data_id']] = $rec['package'];
                        }
                    }
            }

            $QF->Cache->Set($cachename, Array('vals' => $this->datasets[$set_name], 'pkgs' => $this->datapkgs[$set_name]) );
        }
        else
            $this->datasets[$set_name] = Array();

        return true;
    }
}

?>
