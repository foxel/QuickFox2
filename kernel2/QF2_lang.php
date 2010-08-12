<?php

// -------------------------------------------------------------------------- \\
// VIS Class that provides template working and HTML visualization            \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');


if ( defined('QF_KERNEL_LANG_LOADED') )
        die('Scripting error');

define('QF_KERNEL_LANG_LOADED', True);
define('QF_KERNEL_LANG_CACHEPREFIX', 'LANG.');
define('QF_KERNEL_LANG_DEFLANG', 'RU');
define('QF_KERNEL_LANG_DEFLLOCALE', 'ru_RU.CP1251');
define('QF_KERNEL_LANG_COMMON', 'kernel');
define('QF_LANGS_DIR', QF_DATA_ROOT.'langs/');

class QF_Lang
{
    var $lang       = Array();
    var $lang_name  = 'RU';
    var $LNG_loaded = Array();
    var $time_tr    = null;
    var $bsize_tr   = null;


    function QF_Lang()
    {
        if (defined('QF_LANG_CREATED'))
            trigger_error('Duplicate language manager creation!', E_USER_ERROR);

        define('QF_LANG_CREATED', true);

        if (!function_exists('Lang'))
        {
            function Lang($key, $dontload = false)
            {
                global $QF;
                return $QF->LNG->Lang($key, $dontload);
            }
        }

    }

    function _Start()
    {        $this->Load_Kernel_Defs();
    }

    function Select($lang)
    {
        $n_lang = preg_replace('#\W#', '_', $lang);
        if ($this->lang_name == $n_lang)
            return true;

        $this->lang_name = $n_lang;
        $this->time_tr = null;
        $this->lang = Array();
        $this->Load_Kernel_Defs();
        if ($parts = $this->LNG_loaded)
        {
            $this->LNG_loaded = Array();
            foreach ($parts as $part)
                $this->Load_Language($part);
        }
        return true;
    }

    function Ask()
    {        return $this->lang_name;
    }

    function Load_Language($part = '')
    {
        Global $QF;

        if (!$part)
            $part = QF_KERNEL_LANG_COMMON;
        else
        {
            $part = preg_replace('#\W#', '_', $part);
            if ($part != QF_KERNEL_LANG_COMMON && !in_array(QF_KERNEL_LANG_COMMON, $this->LNG_loaded))
                $this->Load_Language();
        }

        if (!in_array($part, $this->LNG_loaded))
        {
            $cachename = QF_KERNEL_LANG_CACHEPREFIX.$this->lang_name.'.'.$part;

            if ($Ldata = $QF->Cache->Get($cachename))
            {
                $this->lang = $Ldata + $this->lang;
                $QF->Timer->Time_Log($this->lang_name.'.'.$part.' language loaded (from global cache)');
            }
            else
            {
                $file = $part.'.lng';
                $vdir = QF_LANGS_DIR.$this->lang_name;
                $ddir = QF_LANGS_DIR.QF_KERNEL_LANG_DEFLANG;
                $odir = (file_exists($vdir.'/'.$file)) ? $vdir : $ddir;
                $file = $odir.'/'.$file;

                if (!file_exists($file))
                {
                    trigger_error('LANG: '.$this->lang_name.'.'.$part.' lang file does not exist', E_USER_NOTICE );
                }
                elseif (($Ldata = qf_file_load_datafile($file, true)) !== false)
                {

                    $QF->Cache->Set($cachename, $Ldata);
                    $this->lang = $Ldata + $this->lang;
                    $QF->Timer->Time_Log($this->lang_name.'.'.$part.' language file loaded (from lang file)');
                    //trigger_error('LANG: error parsing '.$this->lang_name.'.'.$part.' lang file', E_USER_WARNING );
                }
                else
                    trigger_error('LANG: error loading '.$this->lang_name.'.'.$part.' lang file', E_USER_WARNING );

            }

            $this->LNG_loaded[] = $part;
        }
        return true;

    }

    function Lang($key, $dontload = false)
    {
        $key = strtoupper($key);
        if (!$key)
            return '';
        if (!count($this->LNG_loaded))
            if (!$dontload)
                $this->Load_Language();

        if (isset($this->lang[$key]))
            return $this->lang[$key];
        else
            return '['.$key.']';
    }

    function LangParse($data, $prefix = 'L_')
    {        if (is_scalar($data))
        {
            if (preg_match('#^'.preg_quote($prefix, '#').'\w+$#D', $data))
                $data = $this->Lang(substr($data, strlen($prefix)));
        }
        elseif (is_array($data))
        {
            foreach ($data AS $key=>$val)
                $data[$key] = $this->LangParse($val, $prefix);
        }
        elseif (is_object($data))
        {
            foreach ($data AS $key=>$val)
                $data->$key = $this->LangParse($val, $prefix);
        }

        return $data;
    }

    function Time_Format($timestamp, $format = '', $tz = '', $force_no_rels = false)
    {        global $QF;

        static $now, $correct, $today, $yesterday, $time_f, $last_tz = null, $no_rels;

        if (!$now)
        {
            $now = $QF->Timer->time;
            $correct = (int) $QF->Config->Get('time_correction', 'common', 0);
            $time_f = $QF->Config->Get('def_time_format', 'visual', 'H:i');
            $no_rels = (bool) $QF->Config->Get('force_no_rel_time', 'common', false);
        }

        if (!count($this->LNG_loaded))
            $this->Load_Language();

        if (!is_array($this->time_tr))
        {            $keys = Array(
                1 => Array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                2 => Array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'),
                3 => Array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'),
                4 => Array('Jan', 'Feb', 'Mar', 'Apr', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'),
                );
            $lnames = Array(
                1 => 'DATETIME_TR_DAYS',
                2 => 'DATETIME_TR_DAYS_SHORT',
                3 => 'DATETIME_TR_MONTHS',
                4 => 'DATETIME_TR_MONTHS_SHORT',
                );

            $translate = Array();
            for ($i = 1; $i<=4; $i++)
            {
                $lname = $lnames[$i];
                if (isset($this->lang[$lname]))
                {                    $part = explode('|', $this->lang[$lname]);
                    $pkeys = $keys[$i];
                    if (count($part) == count($pkeys))
                        foreach ($pkeys as $id => $key)
                            $translate[$key] = $part[$id];
                }
            }
            $this->time_tr = $translate;
        }
        else
            $translate = $this->time_tr;

        if (!strlen($tz))
            $tz = (int) $QF->Config->Get('time_zone', 'common', 0);
        else
            $tz = intval($tz);

        $tzc = (3600 * $tz + 60 * $correct); // correction of GMT

        if ($last_tz !== $tz) {
            $today = $now + $tzc;
            if (qf_time_DST($now, $tz))
                $today+= 3600;
            $today = floor($today/86400)*86400;
            $yesterday = $today - 86400;
            $last_tz = $tz;
        }

        if (!$format)
            $format = $QF->Config->Get('def_date_format', 'visual', 'd M Y H:i');

        $timetodraw = $timestamp + $tzc;
        if (qf_time_DST($timestamp, $tz))
            $timetodraw+= 3600;

        if ($no_rels || $force_no_rels || $timestamp == $now)
            $out = gmdate($format, $timetodraw);
        elseif ($timestamp > $now) {
            if ($timestamp < $now + 60)
                $out = sprintf($this->lang['DATETIME_FUTURE_SECS'], ($timestamp - $now));
            elseif ($timestamp < $now + 3600)
                $out = sprintf($this->lang['DATETIME_FUTURE_MINS'], round(($timestamp - $now)/60));
            else
                $out = gmdate($format, $timetodraw);
        }
        elseif ($timestamp > ($now - 60))
            $out = sprintf($this->lang['DATETIME_PAST_SECS'], ($now - $timestamp));
        elseif ($timestamp > ($now - 3600))
            $out = sprintf($this->lang['DATETIME_PAST_MINS'], round(($now - $timestamp)/60));
        elseif ($timetodraw > $today)
            $out = sprintf($this->lang['DATETIME_TODAY'], gmdate($time_f, $timetodraw));
        elseif ($timetodraw > $yesterday)
            $out = sprintf($this->lang['DATETIME_YESTERDAY'], gmdate($time_f, $timetodraw));
        else
            $out = gmdate($format, $timetodraw);

        if (count($translate))
            $out = strtr($out, $translate);

        return $out;
    }

    function Size_Format($size, $bits = false)
    {        if (!count($this->LNG_loaded))
            $this->Load_Language();

        $size = (int) $size;

        if (!is_array($this->bsize_tr))
        {
            $bnames = Array(0 => 'BSIZE_FORM_BYTES', 1 => 'BSIZE_FORM_BITS');

            $this->bsize_tr = Array(0 => Array(1 => 'B'), 1 => Array(1 => 'b'));
            foreach ($bnames as $class => $cl_lang)
                if (isset($this->lang[$cl_lang]) && $this->lang[$cl_lang])
                {                    $parts = explode('|', $this->lang[$cl_lang]);
                    $i = 1;
                    $this->bsize_tr[$class] = Array();
                    foreach ($parts as $part)
                    {                        if (!$i)
                            break;
                        $this->bsize_tr[$class][$i] = $part;
                        $i *= 1024;
                    }
                    krsort($this->bsize_tr[$class]);
                }
        }

        $bnames = $this->bsize_tr[(int) $bits];

        $out = $size;
        foreach ($bnames as $bsize => $name)
        {
            if ($bsize == 1)
            {
                $out = sprintf('%d %s', $size, $name);
                break;
            }
            elseif ($size >= $bsize)
            {                $size = $size/$bsize;
                $out = sprintf('%01.2f %s', $size, $name);
                break;
            }
        }

        return $out;
    }

    function Translit($inp)
    {        static $trans_arr = null;
        if (is_null($trans_arr))
        {            if (!isset($this->lang['__TRANSLIT_FROM']) || !isset($this->lang['__TRANSLIT_TO']))
            {                $trans_arr = false;
                return preg_replace('#[\x80-\xFF]+#', '_', $inp);
            }

            $from = explode('|', $this->lang['__TRANSLIT_FROM']);
            $to = explode('|', $this->lang['__TRANSLIT_TO']);
            foreach ($from as $id => $ent)
                if (isset($to[$id]))
                    $trans_arr[$ent] = $to[$id];
        }

        if ($trans_arr)
            $inp =  strtr($inp, $trans_arr);

        return preg_replace('#[\x80-\xFF]+#', '_', $inp);
    }

    function Get_Data_Links()
    {
        return Array(
            'lang'       => &$this->lang,
            'lang_name'  => &$this->lang_name,
            'LNG_loaded' => &$this->LNG_loaded,
            );
    }

    function Load_Kernel_Defs()
    {
        Global $QF;
        static $K_lang = null;

        if (!is_null($K_lang))
        {            $this->lang = $K_lang + $this->lang;
            return true;
        }

        $file = QF_KERNEL_DIR.'krnl_def.lng';

        $cachename = QF_KERNEL_LANG_CACHEPREFIX.'krnl_defs';

        if ($Ldata = $QF->Cache->Get($cachename))
        {
            $this->lang = $Ldata + $this->lang;
            $K_lang = $Ldata;
        }
        elseif ($Ldata = qf_file_load_datafile($file, true))
        {
            $QF->Cache->Set($cachename, $Ldata);
            $this->lang = $Ldata + $this->lang;
            $K_lang = $Ldata;

            //trigger_error('LANG: error parsing kernel lang file: '.$file, E_USER_ERROR);
        }
        else
            trigger_error('LANG: error loading kernel lang file: '.$file, E_USER_ERROR);

        return true;
    }
}


?>
