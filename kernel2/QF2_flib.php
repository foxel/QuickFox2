<?php

// ---------------------------------------------------------------------- \\
// This file contents some basic functions for QuickFox kernel2           \\
// ---------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');


if ( defined('QF_KERNEL_FLIB_LOADED') )
        die('Scripting error');

define('QF_KERNEL_FLIB_LOADED', True);

// some global consts
define('QF_RURL_MASK', '[\w\#$%&~/\.\-;:=,?@+\(\)\[\]\|]+');
define('QF_FURL_MASK', '(?>[0-9A-z]+://[0-9A-z_\-\.]+\.[A-z]{2,4})(?:\/'.QF_RURL_MASK.')?');
define('QF_EMAIL_MASK', '[0-9A-z_\-\.]+@[0-9A-z_\-\.]+\.[A-z]{2,4}');

// function defining constants with checkup
// used for behaviour constants that might be pregefined
function qf_define($name, $val, $case_is = false)
{
    if (!defined($name))
        return define($name, $val, $case_is);
    return false;
}

function qf_is_a($obj, $class_name)
{
    if (function_exists('is_a'))
        return is_a($obj, $class_name);

    if (!is_object($obj))
        return false;

    $class = get_class($obj);
    while ($class)
    {
        if ($class == $class_name)
            return true;
        $class = get_parent_class($class);
    }

    return false;
}


// slashes
function qf_heredoc_addslashes($text, $heder_border = false)
{
    $text = str_replace(Array('\\', '$'), Array('\\\\', '\\$'), $text);
    if ($heder_border)
        $text = str_replace("\n".$heder_border, "\n ".$heder_border, $text);
    return $text;
}

// unslashes variable with non scalar types parsing
function qf_value_unslash($data)
{
    if (is_scalar($data))
    {
        if (!is_numeric($data) && !is_null($data))
            $data = stripslashes($data);
    }
    elseif (is_array($data))
    {
        foreach ($data AS $key=>$val)
            $data[$key] = qf_value_unslash($val);
    }
    elseif (is_object($data))
    {
        foreach ($data AS $key=>$val)
            $data->$key = qf_value_unslash($val);
    }

    return $data;
}

// applies htmlspecialchars to variable with non scalar types parsing
function qf_value_htmlschars($data, $q_mode = ENT_COMPAT)
{
    if (is_scalar($data))
    {
        if (!is_numeric($data) && !is_null($data))
            $data = htmlspecialchars($data, $q_mode);
    }
    elseif (is_array($data))
    {
        foreach ($data AS $key=>$val)
            $data[$key] = qf_value_htmlschars($val, $q_mode);
    }
    elseif (is_object($data))
    {
        foreach ($data AS $key=>$val)
            $data->$key = qf_value_htmlschars($val, $q_mode);
    }

    return $data;
}

// converts variable to JS format
function qf_value_JS_definition($data)
{
    static $_JS_REPLACE = array(
       '\\' => '\\\\', '/'  => '\\/', "\r" => '\\r', "\n" => '\\n',
       "\t" => '\\t',  "\b" => '\\b', "\f" => '\\f', '"'  => '\\"',
       );

    $odata = 'null';

    if (is_bool($data))
        $odata = $data ? 'true' : 'false';
    elseif (is_scalar($data))
    {
        $odata = '"'.strtr($data, $_JS_REPLACE).'"';
    }
    elseif (is_array($data) || is_object($data))
    {
        $odata = Array();
        foreach ($data AS $key=>$val)
            $odata[] = $key.': '.qf_value_JS_definition($val);
        $odata = '{ '.implode(', ', $odata).' }';
    }

    return $odata;
}

// replaces & with &amp; but does not touch &foo;
function qf_smart_ampersands($string)
{
    return preg_replace('#\&(?!([A-z]+|\#\d{1,5}|\#x[0-9a-fA-F]{2,4});)#', '&amp;', $string);
}

// replaces HTML spec chars with &foo; but does not touch existing ones
function qf_smart_htmlschars($string)
{
    static $trans_table = null;
    if (is_null($trans_table))
    {
        $trans_table = get_html_translation_table(HTML_SPECIALCHARS);
        unset($trans_table['&']);
    }

    return strtr(qf_smart_ampersands($string), $trans_table);
}

function qf_2darray_sort(&$array, $field, $rsort = false, $sort_flags = SORT_REGULAR)
{
    if (!is_array($array))
        return $array;
    $resorter = Array();
    foreach ($array as $key=>$val)
    {
        if (!is_array($val) || !isset($val[$field]))
            $skey = 0;
        else
            $skey = $val[$field];

        if (!isset($resorter[$skey]))
            $resorter[$skey] = Array();
        $resorter[$skey][$key] = $val;
    }
    if ($rsort)
        krsort($resorter, $sort_flags);
    else
        ksort($resorter, $sort_flags);
    $array = Array();
    foreach ($resorter as $valblock)
        $array+= $valblock;

    return $array;
}

function qf_2darray_keycol(&$array, $field)
{
    if (!is_array($array))
        return $array;
    $narray = Array();
    foreach ($array as $val)
    {
        if (!is_array($val) || !isset($val[$field]))
            $skey = 0;
        else
            $skey = $val[$field];

        if (!isset($narray[$skey]))
            $narray[$skey] = $val;
    }
    $array = $narray;

    return $array;
}

function qf_2darray_cols($array, $fields)
{
    if (!is_array($array))
        return $array;

    $get_one = false;
    if (!is_array($fields))
    {
        $get_one = true;
        $fields = Array(0 => $fields);
    }

    $result = Array();

    foreach ($array as $key => $row)
    {
        foreach($fields as $fkey => $field)
            if (isset($row[$field]))
                $result[$fkey][$key] = $row[$field];
    }

    if ($get_one)
        $result = $result[0];

    return $result;
}

function qf_2darray_tree($array, $by_id = 'id', $by_par = 'parent', $root_id = 0, $by_lvl = 't_level')
{    $itm_pars = $itm_tmps = Array();

    foreach ($array as $item) // temporary data dividing
    {
        $itm_pars[$item[$by_id]] = $item[$by_par];
        $itm_tmps[$item[$by_id]] = $item;
    }
    unset ($array);

    $out_tree = Array();
    $cur_itm = $root_id;
    $cstack = Array();
    while (count($itm_pars)) // tree resorting
    {
        if ($childs = array_keys($itm_pars, $cur_itm))
        {
            array_push($cstack, $cur_itm);
            $cur_itm = $childs[0];
            $child = $itm_tmps[$cur_itm];
            $child[$by_lvl] = count($cstack); // level
            $out_tree[$cur_itm] = $child;
            unset($itm_pars[$cur_itm]);
        }
        elseif (count ($cstack) && ($st_top = array_pop($cstack)) !== null)
        {
            // getting off the branch
            $cur_itm = $st_top;
        }
        else // this will open looped parentship
        {
            reset($itm_pars);
            $key = key($itm_pars);
            $itm_tmps[$key][$by_par] = $root_id; // we'll link one item to root
            $itm_pars[$key] = $root_id;
        }
    }

    unset ($itm_pars, $itm_tmps);
    return $out_tree;
}


// sorter for usort
function qf_sorter_by_length($a, $b)
{
    $a = strlen($a);
    $b = strlen($b);
    if ($a == $b)
        return 0;
    return ($a > $b) ? -1 : 1;
}

function qf_str_is_email($string)
{
    static $EMAIL_MASK;
    $EMAIL_MASK = '#^'.QF_EMAIL_MASK.'$#D';
    if (preg_match($EMAIL_MASK, $string))
        return true;
    else
        return false;
}

function qf_str_is_url($string)
{
    static $MASK1, $MASK2;
    $MASK1 = '#^'.QF_FURL_MASK.'$#D';
    $MASK2 = '#^'.QF_RURL_MASK.'$#D';
    if (preg_match($MASK1, $string))
        return 1;
    elseif (preg_match($MASK2, $string))
        return 2;
    else
        return 0;
}

// creates a constant lenght string
// use STR_PAD_RIGHT, STR_PAD_LEFT, STR_PAD_BOTH as mode
function qf_fix_string($string, $length, $pad_with = ' ', $mode = null)
{
    if (!is_scalar($string))
        return $string;
    if ($length<=0)
        return $string;

    $len = strlen($string);
    if ($len > $length)
    {
        switch ($mode)
        {
            case STR_PAD_LEFT:
                return substr($string, -$length);
                break;
            case STR_PAD_BOTH;
                return substr($string, ($len - $length)/2, $length);
                break;
            default:
                return substr($string, 0, $length);
        }
    }
    elseif ($len < $length)
    {
        if (!strlen($pad_with))
            $pad_with = ' ';

        switch ($mode)
        {
            case STR_PAD_LEFT;
            case STR_PAD_RIGHT:
            case STR_PAD_BOTH;
                return str_pad($string, $length, $pad_with, $mode);
                break;
            default:
                return str_pad($string, $length, $pad_with);
        }
    }

    return $string;
}

// sprintf with array input
// vsprintf function in PHP 4.1.0
function qf_sprintf_arr($format, $args)
{
    if (is_scalar($args))
        $args = Array($args);

    if (function_exists('vsprintf'))
        return vsprintf($format, $args);
    else
    {
        foreach ($args as $id => $val)
        {
            if (is_string($val))
                $args[$id] = '\''.$val.'\'';
            elseif (!is_numeric($val))
                $args[$id] = (int) $val;
        }
        $args = implode(', ', $args);
        $code = '$out = sprintf($format, '.$args.');';
        eval($code);
        return $out;
    }
}

// corrects a string for filepath
function qf_str_path($path)
{
    if (!is_string($path))
        return $path;

    $path = preg_replace('#(\\\|/)+#', '/', $path);
    $path = preg_replace('#[\x00-\x1F\*\?\|]|/$|^\.$|(?<!^[A-z])\:#', '', $path);
    $path = trim($path);
    return $path;
}

// fixing strange behaviour of built in 'basename'
function qf_basename($name)
{
    return (preg_match('#[\x80-\xFF]#', $name)) ? preg_replace('#^.*[\\\/]#', '', $name) : basename($name);
}

function qf_basename_ext($name)
{
    return (preg_match('#[\x80-\xFF]#', $name)) ? preg_replace('#^.*\.#', '', $name) : pathinfo($name, PATHINFO_EXTENSION);
}

// encodes a parameter for URL
function qf_url_encode_part($string, $spec_rw = false)
{
    $string = rawurlencode($string);
    if ($spec_rw)
        $string = str_replace('%2F', '/', $string); // strange but needed for mod_rw
    return $string;
}

// packs string data to be parsed via url :)
function qf_url_str_pack($data)
{
    $data = (string) $data;
    $hash = qf_short_hash($data);
    $data = rawurlencode(base64_encode($hash.'|'.$data));
    return $data;
}

// unpacks string parsed via url :)
function qf_url_str_unpack($data)
{
    $data = (string) $data;
    $data = base64_decode(rawurldecode($data));
    list($hash, $data) = explode('|', $data, 2);
    $rhash = qf_short_hash($data);
    if ($hash == $rhash)
        return $data;
    else
        return false;
}

// generates full url
function qf_full_url($url, $with_amps = false, $force_host = '')
{
    global $QF;

    if (strcasecmp(get_class($QF), 'QuickFox_kernel2') != 0)
        return $url;

    if ($url{0} == '#')
        return $url;

    $url_p = parse_url($url);

    if (preg_match('#mailto#i', $url_p['scheme']))
        return $url;


    $url = '';
    if (isset($url_p['scheme']))
        $url.= $url_p['scheme'].'://';
    else
        $url.= ($QF->HTTP->Secure) ? 'https://' : 'http://';

    if (isset($url_p['host']))
    {
        if (isset($url_p['username']))
        {
            $url.= $url_p['username'];
            if (isset($url_p['password']))
                $url.= $url_p['password'];
            $url.= '@';
        }
        $url.= $url_p['host'];
        if (isset($url_p['port']))
            $url.= ':'.$url_p['port'];

        if (isset($url_p['path']))
            $url.= preg_replace('#(\/|\\\)+#', '/', $url_p['path']);
    }
    else
    {
        $url.= ($force_host) ? $force_host : $QF->HTTP->SrvName;
        if (isset($url_p['path']))
        {
            if ($url_p['path']{0} != '/')
                $url_p['path'] = '/'.$QF->HTTP->RootDir.'/'.$url_p['path'];
        }
        else
            $url_p['path'] = '/'.$QF->HTTP->RootDir.'/'.QF_INDEX;

        $url_p['path'] = preg_replace('#(\/|\\\)+#', '/', $url_p['path']);
        $url.= $url_p['path'];
    }

    if (isset($url_p['query']))
        $url.= '?'.$url_p['query'];

    if (isset($url_p['fragment']))
        $url.= '#'.$url_p['fragment'];

    $url = ($with_amps) ? preg_replace('#\&(?![A-z]+;)#', '&amp;', $url) : str_replace('&amp;', '&', $url);

    return $url;
}

// checks if given timestamp is DST
function qf_time_DST($time, $tz = 0, $style = '')
{
    static $styles = Array(
        'eur' => Array('+m' => 3, '+d' => 25, '+wd' => 0, '+h' => 2, '-m' => 10, '-d' => 25, '-wd' => 0, '-h' => 2),
        'usa' => Array('+m' => 3, '+d' =>  8, '+wd' => 0, '+h' => 2, '-m' => 11, '-d' =>  1, '-wd' => 0, '-h' => 2),
        );
    static $defstyle;
    $defstyle = $styles['eur'];

    $style = strtolower($style);

    if (isset($styles[$style]))
        $DST = $styles[$style];
    else
        $DST = $defstyle;

    if (!isset($DST['gmt']))
        $time += (int) $tz*3600;

    if ($data = gmdate('n|j|w|G', $time))
    {
        $data = explode('|', $data);
        $cm = $data[0];
        if ($cm < $DST['+m'] || $cm > $DST['-m'])
            return false;
        elseif ($cm > $DST['+m'] && $cm < $DST['-m'])
            return true;
        else
        {
            if ($cm == $DST['+m'])
            {
                $dd = $DST['+d'];
                if (isset($DST['+wd']))
                    $dwd = $DST['+wd'];
                $dh = $DST['+h'];
                $bres = false;
            }
            else
            {
                $dd = $DST['-d'];
                if (isset($DST['-wd']))
                    $dwd = $DST['-wd'];
                $dh = $DST['-h'];
                $bres = true;
            }
            $cd = $data[1];


            if ($cd < $dd)
                return $bres;
            elseif (!isset($dwd))
            {
                if ($cd > $dd)
                    return !$bres;
                else
                    return ($data[3] >= $dh) ? !$bres : $bres;
            }
            else
            {
                $cvwd = $cd - $dd;
                if ($cvwd >= 7)
                    return !$bres;

                $cwd = $data[2];
                $dvwd = ($dwd - $cwd + $cvwd) % 7;
                if ($dvwd < 0)
                    $dvwd += 7;

                if ($cvwd < $dvwd)
                    return $bres;
                elseif ($cvwd > $dvwd)
                    return !$bres;
                else
                    return ($data[3] >= $dh) ? !$bres : $bres;
            }
        }
    }
    else
        return false;
}

// provides md5_file function on PHP 4
function qf_md5_file($filename, $raw_output = false)
{
    if (!file_exists($filename))
        return false;

    if (PHP_VERSION < '4.2.0')
    {
        $data = qf_file_get_contents($filename);
        $data = md5($data);

    }
    else
        $data = md5_file($filename);

    if ($raw_output)
        $data = pack('H*', $data);

    return $data;
}

// provides file_get_contents function on PHP 4
function qf_file_get_contents($filename)
{
    if (!file_exists($filename))
        return null;

    if (function_exists('file_get_contents'))
        return file_get_contents($filename);

    elseif ($stream = fopen($filename, 'rb'))
    {
        $data = fread($stream, filesize($filename));
        fclose($stream);
        return $data;
    }
    else
        return null;
}

// provides file_put_contents function on PHP 4
function qf_file_put_contents($filename, $data)
{
    if (is_object($data))
        return false;

    $pdir = dirname($filename);
    if (!is_dir($pdir))
        qf_mkdir_recursive($pdir);

    if (function_exists('file_put_contents'))
        return file_put_contents($filename, $data);
    elseif ($stream = fopen($filename, 'wb'))
    {
        if (is_array($data))
            $data = implode('', $data);
        $result = fwrite($stream, $data);
        fclose($stream);
        return $result;
    }
    else
        return false;
}

// loads simle array from file in format:
// {value_name} => {value_data_string}\n
function qf_file_get_carray($filename, $force_upcase = false)
{
    if ($data = qf_file_get_contents($filename))
    {
        $matches = Array();
        $arr = Array();
        preg_match_all('#^\s*([\w\-\/]+)\s*=>(.*)$#m', $data, $matches);
        if (is_array($matches[1]))
        {
            foreach ($matches[1] as $num => $name)
            {
                if ($force_upcase)
                    $name = strtoupper($name);
                $arr[$name] = trim($matches[2][$num]);
            }

            return $arr;
        }
        else
            return Array();
    }
    else
        return Array();
}

// this function loads data array from special quickfox datafiles
// each data chunk is a block like "ID:data\n---"
function qf_file_load_datafile($filename, $force_upcase = false, $force_explode = '')
{
    if ($indata = qf_file_get_contents($filename))
    {
        $matches = Array();
        $arr = Array();
        preg_match_all('#^((?>\w+)):(.*?)\n---#sm', $indata, $matches);
        if (is_array($matches[1]))
        {
            $names =& $matches[1];
            $vars  =& $matches[2];

            foreach ($names as $num => $name)
            {
                if ($force_upcase)
                    $name = strtoupper($name);
                $var = trim($vars[$num]);
                if ($force_explode)
                    $var = explode($force_explode, $var);
                $arr[$name] = $var;
            }
        }

        return $arr;
    }
    else
        return false;
}

// Closes all the OB buffers
function qf_ob_free()
{
    if (function_exists('ob_get_level')) // PHP 4.2.0
        while (ob_get_level())
            ob_end_clean();
    else
        while (ob_get_contents()!==false)
            ob_end_clean();
}

// makes dir
function qf_mkdir_recursive($path, $chmod = null)
{
    if (is_dir($path))
        return true;
    elseif (is_file($path))
        return false;

    if (!is_int($chmod))
        $chmod = 0755;

    $pdir = dirname($path);

    if (!is_dir($pdir))
        qf_mkdir_recursive($pdir, $chmod);

    return mkdir($path, $chmod);
}

// returns true if given function link leads to existing func
// provides is_callable function on old PHP (older then 4.0.6)
// actually Unused
function qf_func_exists($func_link)
{
    if (function_exists('is_callable'))
        return is_callable($func_link, true);

    if (is_string($func_link))
    {
        return function_exists($func_link);
    }
    elseif (is_array($func_link))
    {
        if (count($func_link)!=2)
            return false;

        $obj =& $func_link[0];
        $met =& $func_link[1];
        if (is_object($obj))
        {
            if (is_string($met))
            {
                return method_exists($obj, $met);
            }
            else
                return false;
        }
        else
            return false;
    }
    else
        return false;
}

// calls user function with checkup
function qf_func_call($func_link)
{
    if (is_callable($func_link))
    {
        if (func_num_args() > 1)
            $args = array_slice(func_get_args(), 1);
        else
            $args = Array();

        return call_user_func_array($func_link, $args);
    }
    else
        return false;
}

// calls user function with checkup and first argument passed by reference
function qf_func_call_ref($func_link, &$val)
{
    if (is_callable($func_link))
    {
        $args = Array(&$val);
        if (($nargs = func_num_args()) > 2)
            for ($i = 2; $i<$nargs; $i++)
                $args[] = func_get_arg($i);

        return call_user_func_array($func_link, $args);
    }
    else
        return false;
}

// calls user function with checkup with arrayed arguments list
// all arguments may be parsed by reference
function qf_func_call_arr($func_link, $args = Array())
{
    if (is_callable($func_link))
    {
        if (!is_array($args))
            $args = Array();

        return call_user_func_array($func_link, $args);
    }
    else
        return false;
}
// returns PHP array definition
function qf_array_definition($data, $tabs=0)
{
    $tab  = '    ';
    $tabs = intval($tabs);
    $pref = str_repeat($tab, $tabs);

    if (!is_array($data))
    {
        if (is_numeric($data))
            $def = $data;
        elseif (is_bool($data))
            $def = (($data) ? 'true' : 'false');
        elseif (is_null($data))
            $def = 'null';
        else
            $def = "'".addslashes($data)."'";
    }
    else
    {
        $def = "Array (\n";
        $fields = Array();
        $maxlen = 0;
        foreach( $data as $key => $val )
        {
            if (is_numeric($key))
                $field = $pref.$tab.$key." => ";
            else
                $field = $pref.$tab."'".addslashes($key)."' => ";

            if (is_numeric($val))
                $field.= $val;
            elseif (is_bool($val))
                $field.= (($val) ? 'true' : 'false');
            elseif (is_array($val))
                $field.= qf_array_definition($val, $tabs+1);
            elseif (is_null($val))
                $field.= 'null';
            else
                $field.= "'".addslashes($val)."'";

            $fields[]=$field;
        }
        $def.=implode(" ,\n", $fields)."\n$pref) ";
    }
    return $def;
}

function qf_array_parse($data, $call_back)
{
    if (!is_array($data))
        return false;
    if (!is_callable($call_back))
        return $data;

    $keys = array_keys($data);
    $args = array_slice(func_get_args(), 2);

    foreach ($keys as $key)
    {
        $args[0] = $data[$key];
        $data[$key] = call_user_func_array($call_back, $args);
    }
    return $data;
}

function qf_array_modify(&$old_one, $new_one)
{
    if (!is_array($old_one) || !is_array($new_one))
        return false;
    foreach ($new_one as $par=>$val)
        if (isset($old_one[$par]) && !is_null($val))
        {
            settype($val, gettype($old_one[$par]));
            $old_one[$par] = $val;
        }

    return $old_one;
}

function qf_file_mime($filename, $try_ext='')
{
    global $QF;

    if (strcasecmp(get_class($QF), 'QuickFox_kernel2') != 0)
        return $url;

    static $mode = false;
    static $ext_mime = Array();
    static $finfo_res = null;

    if (!$mode)
    {
        if (function_exists('finfo_file') && ($finfo_res = finfo_open(FILEINFO_MIME, QF_KERNEL_DIR.'magic.mime')))
            $mode = 3;
        elseif (function_exists('mime_content_type') && ($mfile = ini_get('mime_magic.magicfile')) && file_exists($mfile))
            $mode = 2;
        else
            $mode = 1;
    }
    $mode = 1;
    $ctype = false;
    if ($mode == 3 && ($f_mime = finfo_file($finfo_res, $filename)))
        $ctype = $f_mime;
    elseif ($mode == 2 && file_exists($filename))
        $ctype = mime_content_type($filename);
    elseif (file_exists($filename) && (strcasecmp(get_class($QF), 'QuickFox_kernel2') == 0) && !$QF->Config->Get('no_exec_mime'))
    {
        list($status, $value) = qf_exec(Array('file', '-ibL', $filename));
        // TODO: add checking of 'file' is accessible (file returns status 1 for -v flag)
        if (!$status) // error running 'file'
            $QF->Config->Set('no_exec_mime', true, '', true);
        elseif (preg_match('#\w+\/[\w\-\_\.]+#', $value, $values))
            $ctype = $values[0];
    }

    if (!$ctype || $ctype == 'application/octet-stream' || $ctype == 'text/plain')
    {
        if (!$ext_mime)
            $ext_mime = qf_file_get_carray(QF_KERNEL_DIR.'by_ext.mime', true);

    	$ext = ($try_ext) ? $try_ext : pathinfo($filename, PATHINFO_EXTENSION);
    	$ext = strtoupper($ext);

    	$ctype = (isset($ext_mime[$ext])) ? $ext_mime[$ext] : ($ctype ? $ctype : 'application/octet-stream');
    }

    return $ctype;
}

function qf_exec($cmd_parts)
{
    static $is_win = null;
    if (is_null($is_win))
        $is_win = (strncasecmp(PHP_OS, 'win', 3) == 0);

    $cmd = '';
    foreach ($cmd_parts as $part)
        if ($part === '>')
            $cmd.= '>';
        elseif (strlen($part))
            $cmd.= '"'.$part.'" ';

    $err_file = qf_short_uid().'.err';

    if ($is_win)
    {
        $cmd = 'cmd /q /a /c " '.$cmd.' 2> "'.$err_file.'" "';
        $ok_status = 0;
    }
    else
    {
        $cmd = '('.$cmd.') 2>'.$err_file;
        $ok_status = 0;
    }
    exec($cmd, $output, $status);
    $output = trim(implode("\n", $output));
    if (file_exists($err_file))
    {
        $err = trim(qf_file_get_contents($err_file));
        unlink($err_file);
    }
    else
        $err = '';

    $status = ($status == $ok_status);

    if ($err && !$status)
        trigger_error('Platform: Error "'.$err.'" while executing "'.$cmd.'"', E_USER_WARNING);

    return array($status, $output, $err);
}

// generates short unique ID (4 bytes, 8 chars HEX string)
function qf_short_uid($add_entr = '')
{
    static $etropy = '';
    $out = str_pad(dechex(crc32(uniqid($add_entr.$etropy))), 8, '0', STR_PAD_LEFT);
    $etropy = $out;
    return $out;
}

function qf_short_hash($data)
{
    static $etropy = '';
    $out = str_pad(dechex(crc32($data)), 8, '0', STR_PAD_LEFT);
    $etropy = $out;
    return $out;
}

//
// IP codec functiond
//
function IP_to_int($dotquad_ip)
{
    $ip_sep = explode('.', $dotquad_ip);
    return hexdec(sprintf('%02x%02x%02x%02x', $ip_sep[0], $ip_sep[1], $ip_sep[2], $ip_sep[3]));
}

function IP_from_int($int_ip)
{
    $int_ip = dechex($int_ip);
    $hexipbang = explode('.', chunk_split($int_ip, 2, '.'));
    return hexdec($hexipbang[0]). '.' . hexdec($hexipbang[1]) . '.' . hexdec($hexipbang[2]) . '.' . hexdec($hexipbang[3]);
}
?>
