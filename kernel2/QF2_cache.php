<?php

// ---------------------------------------------------------------------- \\
// Caching system for QuickFox kernel 2                                   \\
// ---------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');


if ( defined('QF_KERNEL_CACHER_LOADED') )
        die('Scripting error');

define('QF_KERNEL_CACHER_LOADED', True);

define('QF_KERNEL_CACHE_LIFETIME', 86400); // 1 day cache lifetime
//define('QF_KERNEL_CACHE_LIFETIME', 300); // 5 mins cache lifetime - debus needs
define('QF_KERNEL_CACHE_TEMPPREF', 'TEMP.');


// caching class indeed
class QF_Cacher
{
    var $chdata = Array();
    var $got_cache = Array();
    var $upd_cache = Array();
    var $cache_folder = '';

    function QF_Cacher()
    {
        if (defined('QF_CACHER_CREATED'))
            trigger_error('Duplicate cache manager creation!', E_USER_ERROR);

        define('QF_CACHER_CREATED', true);

        $this->cache_folder = QF_DATA_ROOT.'cache';
        if (!is_dir($this->cache_folder))
            qf_mkdir_recursive($this->cache_folder);
    }

    // cache control functiond
    function Get($name)
    {
        $name = strtolower($name);

        if (!in_array($name, $this->got_cache)) {

            $this->chdata[$name] = $this->CFS_Load($name);
            $this->got_cache[] = $name;
        }

        return $this->chdata[$name];
    }

    function Set($name, $value)
    {
        $name = strtolower($name);

        $this->chdata[$name] = $value;

        $this->got_cache[] = $name;
        $this->upd_cache[] = $name;
    }

    function Drop($name)
    {
        $name = strtolower($name);

        $this->chdata[$name] = null;
        if (substr($name, -1) == '.')
        {            $keys = array_keys($this->chdata);
            foreach ($keys as $key)
                if (strpos($key, $name) === 0)
                    $this->chdata[$key] = null;
        }

        $this->upd_cache[] = $name;
    }

    function Drop_List($list)
    {
        $names = explode(' ', $list);
        if (count($names)) {
            $out = Array();
            foreach ($names as $name)
                $this->Drop($name);
            return true;
        }
        else
            return false;
    }

    function _Close()
    {
        $this->upd_cache = array_unique($this->upd_cache);

        foreach ($this->upd_cache as $name) {
            $query = false;
            if (is_null($this->chdata[$name]))
                $this->CFS_Drop($name);
            else
                $this->CFS_Save($name, $this->chdata[$name]);
        }

        $this->upd_cache = Array();
        return true;
    }

    function Clear()
    {
        $this->chdata = Array();
        $this->upd_cache = Array();

        $this->CFS_Clear();

        return true;
    }


    // Temp files managing funtion
    function Create_TempFile($name)
    {
        if (!$name)
            return false;

        $name = strtolower(QF_KERNEL_CACHE_TEMPPREF.$name);
        $name = preg_replace('#[^0-9a-zA-Z_\-\.]#', '_', $name);
        $name = str_replace('.', '/', $name).'.chd';

        $filename = $this->cache_folder.'/'.$name;

        if (qf_file_put_contents($filename, $name.' tempfile'))
            return $filename;
        else
            return null;
    }

    function Store_TempFile($name, $data)
    {
        if (!$name || !$data)
            return false;

        $name = strtolower(QF_KERNEL_CACHE_TEMPPREF.$name);
        $name = preg_replace('#[^0-9a-zA-Z_\-\.]#', '_', $name);
        $name = str_replace('.', '/', $name).'.chd';

        $filename = $this->cache_folder.'/'.$name;

        return qf_file_put_contents($filename, $data);
    }

    function Get_TempFile($name)
    {        Global $QF;

        if (!$name)
            return false;

        $name = strtolower(QF_KERNEL_CACHE_TEMPPREF.$name);
        $name = preg_replace('#[^0-9a-zA-Z_\-\.]#', '_', $name);
        $name = str_replace('.', '/', $name).'.chd';

        $filename = $this->cache_folder.'/'.$name;

        if (!file_exists($filename))
            return null;

        if (filemtime($filename) < ($QF->Timer->time - QF_KERNEL_CACHE_LIFETIME))
            return null;

        return $filename;
    }

    //Cacher filesystem functions
    function CFS_Clear($folder = false)
    {
        $folder = rtrim($folder, '/');

        $folder = (strpos($folder, $this->cache_folder.'/') === 0) ? $folder : $this->cache_folder;
        $stack = Array();
        if (is_dir($folder) && $dir = opendir($folder))
        {
            do {
                while ($entry = readdir($dir))
                    if ($entry!='.' && $entry!='..') {
                        $entry = $folder.'/'.$entry;
                        if (is_file($entry))
                        {
                            $einfo = pathinfo($entry);
                            if (strtolower($einfo['extension'])=='chd')
                                unlink($entry);
                        }
                        elseif (is_dir($entry))
                        {
                            if ($ndir = opendir($entry))
                            {
                                array_push($stack, Array($dir, $folder));
                                $dir = $ndir;
                                $folder = $entry;
                            }
                        }
                    }
                closedir($dir);
                rmdir($folder);
            } while (list($dir, $folder) = array_pop($stack));
        }
    }

    function CFS_Load($name)
    {
        Global $QF;

        if (!$name)
            return false;

        $name = preg_replace('#[^0-9a-zA-Z_\-\.]#', '_', $name);
        $name = str_replace('.', '/', $name).'.chd';

        $filename = $this->cache_folder.'/'.$name;

        if (!file_exists($filename))
            return null;

        if (filemtime($filename) < ($QF->Timer->time - QF_KERNEL_CACHE_LIFETIME))
            return null;

        if ($data = qf_file_get_contents($filename)) {
            $data = unserialize($data);
            return $data;
        }
        else
            return null;
    }

    function CFS_Save($name, $data)
    {
        if (!$name)
            return false;

        $name = preg_replace('#[^0-9a-zA-Z_\-\.]#', '_', $name);
        $name = str_replace('.', '/', $name).'.chd';

        $data = serialize($data);

        return qf_file_put_contents($this->cache_folder.'/'.$name, $data);
    }

    function CFS_Drop($name)
    {
        if (!$name)
            return false;

        $name = preg_replace('#[^0-9a-zA-Z_\-\.]#', '_', $name);
        $name = str_replace('.', '/', $name);
        if (substr($name, -1) != '/')
            $name.= '.chd';

        $file = $this->cache_folder.'/'.$name;
        if (is_file($file))
            return unlink($file);
        elseif (is_dir($file))
            return $this->CFS_Clear($file);
        else
            return true;
    }
}

?>
