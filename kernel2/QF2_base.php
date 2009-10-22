<?php

// -------------------------------------------------------------------------- \\
// This file contents some basic classes for QuickFox kernel2                 \\
// Contents:                                                                  \\
//     QF_UString                                                             \\
//     QF_Timer                                                               \\
//     QF_GPC_Request                                                         \\
//     QF_HTTP                                                                \\
//     QF_Events                                                              \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');


if ( defined('QF_KERNEL_BASE_LOADED') )
        die('Scripting error');

define('QF_KERNEL_BASE_LOADED', True);

// internal encoding (usually UTF-8)
define('QF_INTERNAL_ENCODING', 'utf-8');
// default Cookie Prefix
define('QF_DEF_COOKIE_PREFIX', 'QF2');
// behaviour control constants
define('QF_USTR_LTT_CACHEPREFIX', 'USTR.LTT.');
define('QF_USTR_CHR_CACHEPREFIX', 'USTR.CHR.');
define('QF_USTR_USEMB', extension_loaded('mbstring'));
define('QF_GPC_STRIP', (bool) get_magic_quotes_gpc());

// defining some usefull constants
// HTTP send content types
define('QF_HTTP_FILE_ATTACHMENT', 1);
define('QF_HTTP_FILE_RFC1522'  ,  8);
define('QF_HTTP_FILE_TRICKY'   , 16);

// GPC resource types
define('QF_GPC_ALL', 0);
define('QF_GPC_GET', 1);
define('QF_GPC_POST', 2);
define('QF_GPC_COOKIE', 3);

// GPC String parse subtypes
define('QF_STR_HEX',  1);
define('QF_STR_HTML', 2);
define('QF_STR_WORD', 3);
define('QF_STR_LINE', 4);

// GPC files err-codes
define('QF_UPLOAD_OK', 0); // OK status
define('QF_UPLOAD_ERR_INI_SIZE', 1); // this four statuses are equal to PHP ones
define('QF_UPLOAD_ERR_FORM_SIZE', 2);
define('QF_UPLOAD_ERR_PARTIAL', 3);
define('QF_UPLOAD_ERR_NO_FILE', 4);
define('QF_UPLOAD_ERR_SERVER', 0x10); // this means that was a error on server we'll give PHP 15 status message variants for future
define('QF_UPLOAD_MOVED', 0x20); // this means that file already moved


// String parsers for multiple 8-bits ASCII-safe encodings
class QF_UString
{
    var $ltts = Array(); // alphabetic chars data array
    var $chrs = Array(); // Charconv tables

    function QF_UString()
    {
        setlocale(LC_ALL, 'EN');
        if (QF_USTR_USEMB)
        {
            mb_substitute_character(63);
            mb_language('uni');
            mb_internal_encoding(QF_INTERNAL_ENCODING);
        }
        if (function_exists('iconv_set_encoding'))
            iconv_set_encoding('internal_encoding', QF_INTERNAL_ENCODING);
    }

    function Str_ToUpper($string, $encoding = QF_INTERNAL_ENCODING)
    {
        if (function_exists('mb_strtoupper') && $out = mb_strtoupper($string, $encoding))
            return $out;

        $table = $this->_Get_LetterTable($encoding);
        $lowers = array_keys($table);
        $uppers = array_values($table);
        return str_replace($lowers, $uppers, $string);

    }

    function Str_ToLower($string, $encoding = QF_INTERNAL_ENCODING)
    {
        if (function_exists('mb_strtolower') && $out = mb_strtolower($string, $encoding))
            return $out;

        $table = $this->_Get_LetterTable($encoding);
        $lowers = array_keys($table);
        $uppers = array_values($table);
        return str_replace($uppers, $lowers, $string);

    }

    function Str_Len($string, $encoding = QF_INTERNAL_ENCODING)
    {
        if (QF_USTR_USEMB && $out = mb_strlen($string, $encoding))
            return $out;
        elseif (function_exists('iconv_strlen') && $out = iconv_strlen($string, $encoding))
            return $out;


        $encoding = strtolower($encoding);
        if ($encoding == 'utf-8')
        {
            $string = preg_replace('#[\x80-\xBF]+#', '', $string);
            return strlen($string);
        }
        else
            return strlen($string);
    }

    function Str_Convert($string, $to_enc = QF_INTERNAL_ENCODING, $from_enc = QF_INTERNAL_ENCODING )
    {
        $from_enc = strtolower($from_enc);
        $to_enc   = strtolower($to_enc);
        if (!$to_enc || !$from_enc)
            return false;
        if ($to_enc == $from_enc)
            return $string;

        if (QF_USTR_USEMB && $out = mb_convert_encoding($string, $to_enc, $from_enc))
            return $out;
        //elseif (extension_loaded('iconv') && $out = iconv($from_enc, $to_enc.'//IGNORE//TRANSLIT', $string))
        //    return $out;

        if ($from_enc == 'utf-8')
            return $this->_Sub_FromUtf($string, $to_enc);
        elseif ($to_enc == 'utf-8')
            return $this->_Sub_ToUtf($string, $from_enc);
        else
        {
            if (!($table1 = $this->_Get_CharTable($from_enc)) || !($table2 = $this->_Get_CharTable($to_enc)))
                return false;

            $table = Array();
            foreach($table1 as $ut=>$cp)
                $table[ord($cp)] = $table2[$ut];

            $unk = (isset($table2[0x3F])) // Try set unknown to '?'
                 ? $table2[0x3F]
                 : '';
            unset($table1, $table2);

            $out = '';
            $in_len = strlen($string);
            for ($i=0; $i<$in_len; $i++)
            {
                $ch = ord($string[$i]);

                $out.= (isset($table[$ch]))
                     ? $table[$ch]
                     : $out.= '?';
            }
            return $out;
        }
    }

    function Str_Mime($string, $recode_to = '', $Quoted_Printable = false)
    {
        if (!$recode_to)
            $recode_to = QF_INTERNAL_ENCODING;
        //if (QF_USTR_USEMB && $out = mb_encode_mimeheader($string, $recode_to, 'B'))
        //    return $out;

        if ($recode_to && $recoded = $this->Str_Convert($string, $recode_to))
            $string = $recoded;
        else
            $recode_to = QF_INTERNAL_ENCODING;

        if ($Quoted_Printable)
            $out = '=?'.$recode_to.'?Q?'.strtr(rawurlencode($string), '%', '=').'?=';
        else
            $out = '=?'.$recode_to.'?B?'.base64_encode($string).'?=';

        return $out;
    }

    function Str_Substr($string, $start, $length = false, $encoding = QF_INTERNAL_ENCODING)
    {
        if ($length === false)
            $length = strlen($string);

        if (QF_USTR_USEMB && $out = mb_substr($string, $start, $length, $encoding))
            return $out;
        elseif (function_exists('iconv_substr') && $out = iconv_substr($string, $start, $length, $encoding))
            return $out;

        $encoding = strtolower($encoding);
        if ($encoding != 'utf-8')
            return substr($string, $start, $length);

        if ($letters = $this->_UtfExplode($string))
        {
            $str_len = count($letters);
            if ($str_len <= $start)
                return false;
            $letters = array_slice($letters, $start, $length);
            $out = implode('', $letters);
            return $out;
        }
        return '';
    }

    function Str_SmartTrim($string, $length = 15, $encoding = QF_INTERNAL_ENCODING)
    {
        $len = $this->Str_Len($string, $encoding);

        if ($len > $length) {
            $string = $this->Str_Substr($string, 0, $length);
            $pos = strrpos($string, ' ');
            if ($pos > 0)
                $string = substr($string, 0, $pos);
            return $string.'…';
        }
        else
            return $string;
    }

    // private functions
    function _Sub_FromUtf($string, $to_enc)
    {
        if ($to_enc == 'utf-8')
            return $string;

        if (!($table = $this->_Get_CharTable($to_enc)))
            return false;

        $unk = (isset($table[0x3F])) // Try set unknown to '?'
             ? $table[0x3F]
             : '';
        if ($letters = $this->_UtfExplode($string))
        {
            $out = '';
            reset($letters);
            while (list($i, $lett) = each($letters))
            {
                $uni = ord($lett[0]);

                if ($uni < 0x80)
                    $uni = $uni;
                elseif (($uni >> 5) == 0x06)
                    $uni = (($uni & 0x1F) <<  6) | (ord($lett[1]) & 0x3F);
                elseif (($uni >> 4) == 0x0E)
                    $uni = (($uni & 0x0F) << 12) | ((ord($lett[1]) & 0x3F) <<  6) | (ord($lett[2]) & 0x3F);
                elseif (($uni >> 3) == 0x1E)
                    $uni = (($uni & 0x07) << 18) | ((ord($lett[1]) & 0x3F) << 12) | ((ord($lett[2]) & 0x3F) << 6) | (ord($lett[3]) & 0x3F);
                else
                {
                    $out.= $unk;
                    continue;
                }

                $out.= (isset($table[$uni]))
                     ? $table[$uni]
                     : $unk;
            }
        }
        return $out;
    }

    function _Sub_ToUtf($string, $from_enc)
    {
        if ($from_enc == 'utf-8')
            return $string;

        if (!($table0 = $this->_Get_CharTable($from_enc)))
            return false;

        $table = Array();
        foreach ($table0 as $ut=>$cp)
            $table[ord($cp)] = $ut;
        unset($table0);

        $out = '';
        $in_len = strlen($string);
        for ($i=0; $i<$in_len; $i++)
        {
            $ch = ord($string[$i]);

            if (isset($table[$ch]))
            {
                $uni = $table[$ch];
                if ($uni < 0x80)
                    $out.= chr($uni);
                elseif ($UtfCharInDec < 0x800)
                    $out.= chr(($uni >>  6) + 0xC0).chr(($uni & 0x3F) + 0x80);
                elseif ($UtfCharInDec < 0x10000)
                    $out.= chr(($uni >> 12) + 0xE0).chr((($uni >>  6) & 0x3F) + 0x80).chr(($uni & 0x3F) + 0x80);
                elseif ($UtfCharInDec < 0x200000)
                    $out.= chr(($uni >> 18) + 0xF0).chr((($uni >> 12) & 0x3F) + 0x80).chr((($uni >> 6)) & 0x3F + 0x80). chr(($uni & 0x3F) + 0x80);
                else
                    $out.= '?';
            }
            else
                $out.= '?';
        }
        return $out;
    }

    function _Get_LetterTable($encoding = QF_INTERNAL_ENCODING)
    {
        global $QF;

        $encoding = strtolower($encoding);
        $is_utf = ($encoding == 'utf-8');

        $cachename = QF_USTR_LTT_CACHEPREFIX.$encoding;
        if (isset($this->ltts[$encoding]))
        {
            return $this->ltts[$encoding];
        }
        elseif ($data = $QF->Cache->Get($cachename))
        {
            return ($this->ltts[$encoding] = $data);
        }
        elseif ($data = qf_file_get_contents(QF_KERNEL_DIR.'/chars/'.$encoding.'.ltt')) // we'll try to load chars data
        {
            $table = Array();
            preg_match_all('#0x([0-9a-fA-F]{1,6})\[0x([0-9a-fA-F]{1,6})\]#', $data, $matches, PREG_SET_ORDER);
            foreach ($matches as $part)
            {
                if ($is_utf)
                    $table[$this->_HexToUtf($part[1])] = $this->_HexToUtf($part[2]);
                else
                    $table[$this->_HexToChr($part[1])] = $this->_HexToChr($part[2]);
            }

            $QF->Cache->Set($cachename, $table);
            return ($this->ltts[$encoding] = $table);
        }
        else
        {
            trigger_error('QF_UString: There is no letter table for '.$encoding, E_USER_NOTICE);
            return Array();
        }
    }

    // loads unicode to charset table
    function _Get_CharTable($encoding = QF_INTERNAL_ENCODING)
    {
        global $QF;

        $encoding = strtolower($encoding);
        if ($encoding == 'utf-8')
            return false;

        $cachename = QF_USTR_CHR_CACHEPREFIX.$encoding;

        if (isset($this->chrs[$encoding]))
        {
            return $this->chrs[$encoding];
        }
        elseif ($data = $QF->Cache->Get($cachename))
        {
            return ($this->chrs[$encoding] = $data);
        }
        elseif ($data = qf_file_get_contents(QF_KERNEL_DIR.'/chars/'.$encoding.'.chr')) // we'll try to load chars data
        {
            $table = Array();
            preg_match_all('#0x([0-9a-fA-F]{1,6})\[0x([0-9a-fA-F]{1,6})\]#', $data, $matches, PREG_SET_ORDER);
            foreach ($matches as $part)
            {
                $table[hexdec($part[1])] = $this->_HexToChr($part[2]);
            }

            $QF->Cache->Set($cachename, $table);
            return ($this->chrs[$encoding] = $table);
        }
        else
        {
            trigger_error('QF_UString: Can\'t load chartable for '.$encoding, E_USER_WARNING);
            return false;
        }
    }

    function _HexToChr ($Hex)
    {
        $dec = (hexdec($Hex) & 255);
        return chr($dec);
    }

    function _HexToUtf ($UtfCharInHex)
    {
        $OutputChar = '';
        $UtfCharInDec = hexdec($UtfCharInHex);

        if ($UtfCharInDec < 0x80)
            $OutputChar.= chr($UtfCharInDec);
        elseif ($UtfCharInDec < 0x800)
            $OutputChar.= chr(($UtfCharInDec >>  6) + 0xC0).chr(($UtfCharInDec & 0x3F) + 0x80);
        elseif ($UtfCharInDec < 0x10000)
            $OutputChar.= chr(($UtfCharInDec >> 12) + 0xE0).chr((($UtfCharInDec >>  6) & 0x3F) + 0x80).chr(($UtfCharInDec & 0x3F) + 0x80);
        elseif ($UtfCharInDec < 0x200000)
            $OutputChar.= chr(($UtfCharInDec >> 18) + 0xF0).chr((($UtfCharInDec >> 12) & 0x3F) + 0x80).chr((($UtfCharInDec >> 6)) & 0x3F + 0x80). chr(($UtfCharInDec & 0x3F) + 0x80);
        else
            return false;

        return $OutputChar;
    }

    function _UtfExplode($string)
    {
        $letters = Array();
        if (preg_match_all('#[\x00-\x7F]|[\xC0-\xDF][\x80-\xBF]|[\xE0-\xEF][\x80-\xBF]{2}|[\xF0-\xF7][\x80-\xBF]{3}#', $string, $letters))
            $letters = $letters[0];
        else
            $letters = Array();
        return $letters;
    }
}

// extended time counting/logging class
class QF_Timer
{
    var $time;
    var $start_time;
    var $point_time;
    var $events = Array();

    function QF_Timer()
    {
        $this->time = time();
        $this->start_time = $this->Get_MTime();
        $this->point_time = $this->start_time;
    }

    function Get_MTime()
    {
        $mtime = explode(' ',microtime());
        $mtime = $mtime[1] + $mtime[0];
        return $mtime;
    }

    function Time_Point()
    {
        $time = $this->Get_MTime();
        $diff = $time - $this->point_time;
        $this->point_time = $time;
        return $diff;
    }

    function Time_Point_Spent()
    {
        return ($this->Get_MTime() - $this->point_time);
    }

    function Time_Spent()
    {
        return ($this->Get_MTime() - $this->start_time);
    }

    function Time_Log($event = '')
    {
        $this->events[] = Array(
            'time' => $this->Time_Spent(),
            'name' => $event );
    }

}

// request data input class
class QF_GPC_Request
{
    var $raw = array();
    var $str_recode_func = null;
    var $UPLOADS = null;
    var $GET, $POST, $COOKIE, $REQUEST, $FILES;
    var $CPrefix = QF_DEF_COOKIE_PREFIX;

    function QF_GPC_Request()
    {
        // here was if (PHP_VERSION<'4.1.0') block
        $this->GET =& $_GET;
        $this->POST =& $_POST;
        $this->COOKIE =& $_COOKIE;
        $this->REQUEST =& $_REQUEST;
        $this->FILES =& $_FILES;
        $this->CPrefix = QF_DEF_COOKIE_PREFIX;

        // Clear all the registered globals if they not cleared on kernel load
        if (ini_get('register_globals') && !defined('QF_GLOBALS_CLEARED'))
        {
            $drop_globs = $this->REQUEST + $this->FILES;
            foreach ($drop_globs as $rvar => $rval)
               if ($GLOBALS[$rvar] === $rval)
                   unset ($GLOBALS[$rvar]);
            define('QF_GLOBALS_CLEARED', true);
        }
    }

    function _Start()
    {
        global $QF;
        $this->CPrefix = $QF->Config->Get('cookie_prefix', 'common', QF_DEF_COOKIE_PREFIX);
        $QF->Config->Add_Listener('cookie_prefix', 'common', Array(&$this, 'Rename_Cookies'));
    }

    // special function for chenging prefix without dropping down the session
    function Rename_Cookies($new_prefix)
    {
        global $QF;
        if (!$new_prefix)
            $new_prefix = QF_DEF_COOKIE_PREFIX;

        $new_cookies = Array();
        $o_prefix = $this->CPrefix.'_';
        foreach ($this->COOKIE as $val => $var)
        {
            if (strpos($val, $o_prefix) === 0)
            {
                $QF->HTTP->Set_Cookie($val, false, false, false, false, true);
                $val = $new_prefix.'_'.substr($val, strlen($o_prefix));
                $QF->HTTP->Set_Cookie($val, $var, false, false, false, true);
            }
            $new_cookies[$val] = $var;
        }
        $this->CPrefix = $new_prefix;
        $this->COOKIE = $new_cookies;
    }

    // useful for special inpur parsings
    function Set_Raws($datas, $set = QF_GPC_GET)
    {
        if (!is_array($datas))
            return false;

        $raw = &$this->raw;
        foreach ($datas as $key => $data)
            $raw[$set][$key] = $data;

        return true;
    }

    function Get_Raw($var_name, $from = QF_GPC_GET )
    {

        $raw = &$this->raw;

        if (!isset($raw[$from][$var_name]))
        {
            $svar_name = $var_name;
            switch ($from) {
                case QF_GPC_GET:
                    $source =& $this->GET;
                    break;
                case QF_GPC_POST:
                    $source =& $this->POST;
                    break;
                case QF_GPC_COOKIE:
                    $svar_name = $this->CPrefix.'_'.$var_name;
                    $source =& $this->COOKIE;
                    break;
                default:
                    $source =& $this->REQUEST;
            }

            if (isset($source[$svar_name]))
                $val = $source[$svar_name];
            else
                $val = null;

            if (QF_GPC_STRIP)
                $val = qf_value_unslash($val);

            $raw[$from][$var_name] = $val;
        }
        else
            $val = $raw[$from][$var_name];

        return $val;
    }

    function Get_Bin($var_name, $from = QF_GPC_GET)
    {
        $val = $this->Get_Raw($var_name, $from);
        if ($val === null)
            return null;
        return ($val) ? true : false;
    }

    function Get_Num($var_name, $from = QF_GPC_GET, $get_float = false )
    {
        $val = $this->Get_Raw($var_name, $from);
        if ($val === null)
            return null;
        return ($get_float) ? floatval($val) : intval($val);
    }

    function Get_String($var_name, $from = QF_GPC_GET, $subtype = false )
    {
        $val = $this->Get_Raw($var_name, $from);
        if ($val === null)
            return null;
        $val = trim(strval($val));

        if (is_callable($this->str_recode_func))
            $val = call_user_func($this->str_recode_func, $val);

        switch ($subtype)
        {
            case QF_STR_HEX:
                $val = strtolower(preg_replace('#[^0-9a-fA-F]#', '', $val));
                break;

            case QF_STR_HTML:
                $val = htmlspecialchars($val); //, ENT_NOQUOTES
                break;

            case QF_STR_WORD:
                $val = preg_replace('#[^0-9a-zA-Z_\-]#', '', $val);
                break;

            case QF_STR_LINE:
                $val = preg_replace('#[\r\n]#', '', $val);
                break;
        }

        return $val;
    }

    function Get_File($var_name)
    {
        if (is_null($this->UPLOADS))
            $this->_Recheck_Files();

        if (isset($this->UPLOADS[$var_name]))
            return $this->UPLOADS[$var_name];
        else
            return null;
    }

    function Move_File($var_name, $to_file, $force_replace = false)
    {
        if (is_null($this->UPLOADS))
            $this->_Recheck_Files();

        if (!isset($this->UPLOADS[$var_name]))
            return false;

        $file =&$this->UPLOADS[$var_name];
        if (isset($file['is_group']))
            return false;
        elseif ($file['error'])
            return false;

        $old_file = $file['tmp_name'];
        if (file_exists($old_file) && is_uploaded_file($old_file))
        {
            if (!file_exists($to_file) || $force_replace)
            {
                if (move_uploaded_file($old_file, $to_file))
                {
                    $file['error'] = QF_UPLOAD_MOVED;
                    return true;
                }
                else
                    return false;
            }
            else
                return false;
        }
    }

    // inner funtions
    function _Recheck_Files()
    {
        static $empty_file = Array('name' => '', 'type' => '', 'tmp_name' => '', 'error' => 0, 'size' => 0);

        $fgroups = Array();
        $this->UPLOADS = $this->FILES;
        // reparsing arrays
        do
        {
            $need_loop = false;
            $files = Array();
            foreach($this->UPLOADS as $varname=>$fileinfo)
            {
                $tmp_file = $fileinfo['tmp_name'];
                if (is_array($tmp_file))
                {
                    $fgroup = Array('is_group' => true);
                    $need_loop = true;
                    foreach($tmp_file as $id=>$data)
                    {
                        $sub_var = $varname.'['.$id.']';
                        $fgroup[] = $sub_var;
                        $sub_info = Array();
                        foreach ($fileinfo as $var=>$val)
                            $sub_info[$var] = $val[$id];
                        $files[$sub_var] = $sub_info;
                    }
                    $fgroups[$varname] = $fgroup;
                }
                else
                    $files[$varname] = $fileinfo;
            }
            $this->UPLOADS = $files;
        } while ($need_loop);

        // checking files
        foreach($this->UPLOADS as $varname=>$upload)
        {
            $upload = $this->UPLOADS[$varname] + $empty_file;
            if (QF_GPC_STRIP)
                $upload = qf_value_unslash($upload);

            $tmp_file = $upload['tmp_name'];
            if ($upload['name'])
            {
                if (is_callable($this->str_recode_func))
                    $upload['name'] = call_user_func($this->str_recode_func, $upload['name']);

                $upload['name'] = qf_basename($upload['name']);
            }

            if (!$upload['name']) //there is no uploaded file
            {
                $upload = null;
            }
            elseif ($upload['error'])
            {
                trigger_error('GPC: error uploading file to server: filename="'.$upload['name'].'"; tmp="'.$tmp_file.'"; size='.$upload['size'].'; srv_err='.$upload['error'], E_USER_WARNING);
            }
            elseif (!file_exists($tmp_file) || !is_uploaded_file($tmp_file))
            {
                trigger_error('GPC: uploaded file not found: filename="'.$upload['name'].'"; tmp="'.$tmp_file.'"; size='.$upload['size'], E_USER_WARNING);
                $upload['error'] = QF_UPLOAD_ERR_SERVER;
            }
            elseif (($fsize = filesize($tmp_file)) != $upload['size'])
            {
                trigger_error('GPC: uploaded file is not totally uploaded: filename="'.$upload['name'].'"; tmp="'.$tmp_file.'"; size='.$upload['size'].'; realsize='.$fsize, E_USER_WARNING);
                $upload['error'] = QF_UPLOAD_ERR_PARTIAL;
            }
            $this->UPLOADS[$varname] = $upload;
        }


        $this->UPLOADS+= $fgroups;
    }

}

// HTTP interface
// Outputs data to user
class QF_HTTP
{
    var $IP      = '';
    var $IP_int  = 0;
    var $RootUrl = '';
    var $RootDir = '';
    var $RootFul = '';
    var $SrvName = '';
    var $Request = '';
    var $PtInfo  = '';
    var $Referer = '';
    var $ExtRef  = false;
    var $UAgent  = '';
    var $Cl_ID   = '';
    var $SERVER  = Array();
    var $ENV     = Array();
    var $Secure  = false;

    var $CDomain = false;
    var $CPrefix = QF_DEF_COOKIE_PREFIX;

    var $buffer  = '';

    var $do_HTML = true;
    var $do_GZIP = true;

    function QF_HTTP()
    {
        //here was if (PHP_VERSION < '4.1.0') block
        $this->SERVER =& $_SERVER;
        $this->ENV =& $_ENV;

        // Clear all the registered globals if they not cleared on kernel load
        if (ini_get('register_globals') && !defined('QF_GLOBALS_CLEARED'))
        {
            $glob_keys = array_keys($this->SERVER + $this->ENV);
            foreach ($glob_keys as $rvar)
               unset ($GLOBALS[$rvar]);
        }

        $this->IP      = $this->SERVER['REMOTE_ADDR'];
        $this->IP_int  = IP_to_int($this->IP);
        $this->UAgent  = trim($this->SERVER['HTTP_USER_AGENT']);
        $this->Cl_ID   = md5($this->IP_int.'|'.$this->UAgent); // Client ID usefull for client machine identity

        $this->SrvName = preg_replace('#^\/*|\/*$#', '\1', trim($this->SERVER['SERVER_NAME']));
        $this->RootUrl = 'http://'.$this->SrvName.'/';
        $this->Request = preg_replace('#\/|\\\+#', '/', trim($this->SERVER['REQUEST_URI']));
        $this->Request = preg_replace('#[\'\"]|^/+#s', '', $this->Request);

        $this->RootDir = dirname($this->SERVER['PHP_SELF']);
        $this->RootDir = preg_replace('#\/|\\\+#', '/', $this->RootDir);

        if ( isset($this->SERVER['PATH_INFO']) && $this->SERVER['PATH_INFO'] )
            $this->PtInfo = preg_replace('#\/|\\\+#', '/', $this->SERVER['PATH_INFO']);
        elseif ( ($this->SERVER['PHP_SELF'] != QF_INDEX) && (strpos($this->SERVER['PHP_SELF'], QF_INDEX) === 0) )
            $this->PtInfo = preg_replace('#\/|\\\+#', '/', substr($this->SERVER['PHP_SELF'], strlen(QF_INDEX)));

        $this->PtInfo  = preg_replace('#^\/*|\/*$#', '', $this->PtInfo);

        if ( $this->RootDir = preg_replace('#^\/*|\/*$#', '', $this->RootDir) )
        {
            $this->Request = preg_replace('#^\/*'.$this->RootDir.'\/+#', '', $this->Request);
            $this->RootUrl.= $this->RootDir.'/';
        }
        if ($this->Request)
            $this->Request = substr($this->Request, 0, 255);

        $this->RootFul = preg_replace(Array('#\/|\\\+#', '#\/*$#'), Array('/', ''), $this->SERVER['DOCUMENT_ROOT']).$this->RootDir;

        if (isset($this->SERVER['HTTP_REFERER']) && ($this->Referer = trim($this->SERVER['HTTP_REFERER'])))
        {
            if (strpos($this->Referer, $this->RootUrl) === 0)
            {
                $this->Referer = substr($this->Referer, strlen($this->RootUrl));
                if (!$this->Referer || $this->Request == $this->Referer)
                    $this->Referer = QF_INDEX;
            }
            else
                $this->ExtRef = true;
        }

        if (isset($this->SERVER['HTTPS']) && ($this->SERVER['HTTPS'] == 'on'))
            $this->Secure = true;
    }

    function _Start()
    {
        Global $QF;

        if (headers_sent($file, $line))
            trigger_error('QuickFox HTTP initialization error (Headers already sent)', E_USER_ERROR);
        if (ob_get_contents() !== false)
            trigger_error('QuickFox HTTP initialization error (Output buffering is started elsewhere)', E_USER_ERROR);

        header('X-Powered-By: QuickFox kernel 2 (PHP/'.PHP_VERSION.')');
        ob_start(array( &$this, 'Out_Filter'));
        set_error_handler(array( &$this, 'Error_Handler'));
        ini_set ('default_charset', '');

        $this->CPrefix = $QF->Config->Get('cookie_prefix', 'common', QF_DEF_COOKIE_PREFIX);
        $QF->Config->Add_Listener('cookie_prefix', 'common', Array(&$this, '_New_CPrefix'));
    }

    function _New_CPrefix($new_prefix)
    {
        if (!$new_prefix)
            $new_prefix = QF_DEF_COOKIE_PREFIX;

        $this->CPrefix = $new_prefix;
    }

    function Write($text, $no_nl = false)
    {
        if (is_scalar($text))
            $this->buffer.= (string) $text;

        if (!$no_nl)
            $this->buffer.= "\n";
    }

    function From_OB($append = false)
    {
        if ($append)
            $this->buffer.= ob_get_contents();
        else
            $this->buffer = ob_get_contents();
    }

    function Get_OB()
    {
        return ob_get_contents();
    }

    function Clear()
    {
        $this->buffer = '';
    }

    function Send_File($file, $filename = '', $filemime = '', $filemtime = false, $flags = 0)
    {
        global $QF;

        if (headers_sent())
        {
            trigger_error('QuickFox HTTP error', E_USER_ERROR);
            return false;
        }

        if (file_exists($file))
        {
            Ignore_User_Abort(false);
            if (!$filename)
                $filename = $file;

            if (!$filemime)
                $filemime = 'application/octet-stream';

            $filename = qf_basename($filename);
            $disposition = ($flags & QF_HTTP_FILE_ATTACHMENT)
                ? 'attachment'
                : 'inline';

            $FileSize = filesize($file);
            $FileTime = (is_int($filemtime))
                ? $filemtime
                : gmdate('D, d M Y H:i:s ', filemtime($file)).'GMT';

            if (isset($this->SERVER['HTTP_RANGE']) && preg_match('#bytes\=(\d+)\-(\d*?)#i', $this->SERVER['HTTP_RANGE'], $ranges))
            {
                $NeedRange = true;
                $SeekFile  = intval($ranges[1]);
            }
            else
            {
                $NeedRange = false;
                $SeekFile  = 0;
            }

            if ($stream = fopen($file, 'rb'))
            {
                qf_ob_free();

                $filename = preg_replace('#[\x00-\x1F]+#', '', $filename);

                if (preg_match('#[^\x20-\x7F]#', $filename))
                {
                    // according to RFC 2183 all headers must contain only of ASCII chars
                    // according to RFC 1522 there is a way to represent non-ASCII chars
                    //  in MIME encoded strings like =?utf-8?B?0KTQsNC50LsuanBn?=
                    //  but actually only Gecko-based browsers accepted that type of message...
                    //  so in this part non-ASCII chars will be transliterated according to
                    //  selected language and all unknown chars will be replaced with '_'
                    //  if you want to send non-ASCII filename to FireFox you'll need to
                    //  set 'QF_HTTP_FILE_RFC1522' flag
                    // Or you may use tricky_mode to force sending 8-bit UTF-8 filenames
                    //  via breaking some standarts. Opera will get it but IE not
                    //  so don't use it if you don't really need to
                    if ($flags & QF_HTTP_FILE_RFC1522)
                    {
                        $filename = $QF->USTR->Str_Mime($filename);
                    }
                    elseif ($flags & QF_HTTP_FILE_TRICKY)
                    {
                        if (preg_match('#^text/#i', $filemime))
                            $disposition = 'attachment';
                        $filemime.= '; charset="'.QF_INTERNAL_ENCODING.'"';
                    }
                    else
                        $filename = $QF->LNG->Translit($filename);
                }

                header('Last-Modified: '.$FileTime);
                header('Expires: '.date('r', $QF->Timer->time + 3600*24), true);
                header('Content-Transfer-Encoding: binary');
                header('Content-Disposition: '.$disposition.'; filename="'.$filename.'"');
                header('Content-Type: '.$filemime);
                header('Content-Length: '.($FileSize - $SeekFile));
                header('Accept-Ranges: bytes');
                header('X-QF-GenTime: '.$QF->Timer->Time_Spent());

                if ($NeedRange)
                {
                    header($this->SERVER['SERVER_PROTOCOL'] . ' 206 Partial Content');
                    header('Content-Range: bytes '.$SeekFile.'-'.($FileSize-1).'/'.$FileSize);
                }

                fseek($stream, $SeekFile);
                fpassthru($stream);
                fclose($stream);

                exit();
            }
        }
    }

    function Send_Buffer($recode_to = '', $c_type = '', $force_cache = 0)
    {
        Global $QF;

        if (headers_sent())
        {
            trigger_error('QuickFox HTTP error', E_USER_ERROR);
            return false;
        }

        qf_ob_free();

        if ($this->do_HTML)
        {
            $QF->Events->Call_Event_Ref('HTTP_HTML_parse', $this->buffer );

            $statstring = sprintf(Lang('FOOT_STATS_PAGETIME'), $QF->Timer->Time_Spent()).' ';
            if ($QF->DBase->num_queries)
                $statstring.= sprintf(Lang('FOOT_STATS_SQLSTAT'), $QF->DBase->num_queries, $QF->DBase->queries_time).' ';
            if (isset($QF->Services))
                if (get_class($QF->Services) == 'QF_Services')
                    if ($QF->Services->time_spent)
                        $statstring.= sprintf(Lang('FOOT_STATS_SRVSTAT'), $QF->Services->time_spent).' ';

            $this->buffer = str_replace('<!--Page-Stats-->', $statstring, $this->buffer);
            $c_type = (preg_match('#[\w\-]+/[\w\-]+#', $c_type)) ? $c_type : 'text/html';
        }
        else
            $c_type = (preg_match('#[\w\-]+/[\w\-]+#', $c_type)) ? $c_type : 'text/plain';

        if ($encoding = $recode_to)
        {
            if ($buffer = $QF->USTR->Str_Convert($this->buffer, $encoding))
                $this->buffer = $buffer;
            else
                $encoding = QF_INTERNAL_ENCODING;

            header('Content-Type: '.$c_type.'; charset='.$encoding);
            $meta_conttype = '<meta http-equiv="Content-Type" content="'.$c_type.'; charset='.$encoding.'" />';
        }
        else
        {
            header('Content-Type: '.$c_type.'; charset='.QF_INTERNAL_ENCODING);
            $meta_conttype = '<meta http-equiv="Content-Type" content="'.$c_type.'; charset='.QF_INTERNAL_ENCODING.'" />';
        }

        if ($this->do_HTML)
            $this->buffer = str_replace('<!--Meta-Content-Type-->', $meta_conttype, $this->buffer);

        if ($this->do_GZIP)
        {
            if ($this->_Try_GZIP_Encode($this->buffer))
                header('Content-Encoding: gzip');
        }


        if ($force_cache > 0)
            header('Expires: '.date('r', $QF->Timer->time + $force_cache), true);
        else
            header('Cache-Control: no-cache');

        header('Content-Length: '.strlen($this->buffer));
        header('X-QF-Page-GenTime: '.$QF->Timer->Time_Spent());
        print $this->buffer;
        exit();
    }

    function Send_Binary($data = '', $c_type = '', $force_cache = 0)
    {
        Global $QF;

        if (headers_sent())
        {
            trigger_error('QuickFox HTTP error', E_USER_ERROR);
            return false;
        }

        qf_ob_free();

        $c_type = (preg_match('#[\w\-]+/[\w\-]+#', $c_type)) ? $c_type : 'application/octet-stream';

        header('Content-Type: '.$c_type);

        if (false && $this->do_GZIP)
        {
            if ($this->_Try_GZIP_Encode($this->buffer))
                header('Content-Encoding: gzip');
        }


        if ($force_cache > 0)
            header('Expires: '.date('r', $QF->Timer->time + $force_cache), true);
        else
            header('Cache-Control: no-cache');

        $data = ($data) ? $data : $this->buffer;

        header('Content-Length: '.strlen($data));
        header('X-QF-Page-GenTime: '.$QF->Timer->Time_Spent());
        print $data;
        exit();
    }

    // sets cookies domain (checks if current client request is sent on that domain or it's sub)
    function Set_Cookie_Domain($domain)
    {
        if (!preg_match('#[\w\.]+\w\.\w{2,4}#', $domain))
            return false;
        $my_domain = '.'.ltrim(strtolower($this->SrvName), '.');
        $domain    = '.'.ltrim(strtolower($domain), '.');
        $len = strlen($domain);
        if (substr($my_domain, -$len) == $domain)
        {
            $this->CDomain = $domain;
            return true;
        }
        return false;
    }

    // Sets cookie with root dir parameter (needed on sites with many copies of QF)
    function Set_Cookie($name, $value = false, $expire = false, $root = false, $no_domain = false, $no_prefix = false)
    {
        if (!$root)
            $root = ($this->RootDir) ? '/'.$this->RootDir.'/' : '/';
        if (!$no_prefix)
            $name = $this->CPrefix.'_'.$name;
        return ($no_domain)
            ? setcookie($name, $value, $expire, $root)
            : setcookie($name, $value, $expire, $root, $this->CDomain);
    }

    // Redirecting function
    function Redirect($url)
    {
        Global $QF;

        if (headers_sent())
        {
            trigger_error('QuickFox HTTP error', E_USER_ERROR);
            return false;
        }

        if (strstr(urldecode($url), "\n") || strstr(urldecode($url), "\r"))
            trigger_error('Tried to redirect to potentially insecure url.', E_USER_WARNING);

        qf_ob_free();

        $url = qf_full_url($url);
        $QF->Events->Call_Event_Ref('HTTP_URL_Parse', $url );
        $hurl = strtr($url, Array('&' => '&amp;'));

        // Redirect via an HTML form for PITA webservers
        if (@preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE')))
        {
            header('Refresh: 0; URL='.$url);
            echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"><meta http-equiv="refresh" content="0; url='.$hurl.'"><title>Redirect</title></head><body><div align="center">If your browser does not support meta redirection please click <a href="'.$hurl.'">HERE</a> to be redirected</div></body></html>';
            exit();
        }

        // Behave as per HTTP/1.1 spec for others
        header('Location: '.$url);
        exit();
    }

    function Set_Status($stat_code)
    {
        static $codes = Array(
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            503 => 'Service Unavailable',
            );

        if (isset($codes[$stat_code]))
            header (implode(' ', Array($this->SERVER["SERVER_PROTOCOL"], $stat_code, $codes[$stat_code])));
    }

    // returns client signature based on browser, ip and proxy
    function Get_Client_Signature($level = 0)
    {        static $sign_parts = Array('HTTP_USER_AGENT', 'HTTP_ACCEPT', 'HTTP_ACCEPT_CHARSET'); //, 'HTTP_ACCEPT_ENCODING'
        static $psign_parts = Array('HTTP_VIA', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP');

        $sign = Array();
        foreach ($sign_parts as $key)
            if (isset($this->SERVER[$key]))
                $sign[] = $this->SERVER[$key];

        if ($level > 0)
        {
            $ip = explode('.', $this->IP);
            $sign[] = $ip[0];
            $sign[] = $ip[1];
            if ($level > 1)
            {
                $sign[] = $ip[2];
                foreach ($psign_parts as $key)
                    if (isset($this->SERVER[$key]))
                        $sign[] = $this->SERVER[$key];
            }
            if ($level > 2)
                $sign[] = $ip[3];
        }

        $sign = implode('|', $sign);
        $sign = md5($sign);
        return $sign;
    }

    // private functions
    function _Try_GZIP_Encode(&$text, $level = 9)
    {
        if (!extension_loaded('zlib')) // commented as QF requires PHP 4.0.6 where gzcompress exists {|| !function_exists('gzcompress'))}
            return false;

        $compress = false;
        if ( strstr($this->SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') )
            $compress = true;

        $level = abs(intval($level)) % 10;

        if ($compress)
        {
            $gzip_size = strlen($text);
            $gzip_crc = crc32($text);


            $text = gzcompress($text, $level);
            $text = substr($text, 0, strlen($text) - 4);

            $out = "\x1f\x8b\x08\x00\x00\x00\x00\x00";
            $out.=  $text;
            $out.=  pack('V', $gzip_crc);
            $out.=  pack('V', $gzip_size);

            $text = $out;
        }
        // $text = gzencode($text, $level); // strange but does not work on PHP 4.0.6

        return $compress;
    }

    // filters off OB output
    function Out_Filter( $text )
    {
        global $debug;

        //return $text;
        if (!$this->buffer || $debug)
            return false;
        else
            return '';
    }


    function _Close()
    {
        restore_error_handler();

        if ( headers_sent($file, $line) )
            if ($file)
            {
                // Critical error - some script module violated QF HTTP otput rules
                if ($file != __FILE__)
                    trigger_error('Script module "'.$file.'" violated QF HTTP otput rules at line '.$line, E_USER_ERROR);

            }
    }

    function Error_Handler($errno, $errstr, $errfile = 'n/a', $errline = 'n/a')
    {
        global $QF;
        global $debug;
        static $logfile;
        static $ERR_TYPES = Array(
            E_ERROR        => 'PHP ERROR',
            E_WARNING      => 'PHP WARNING',
            E_NOTICE       => 'PHP NOTICE',
            E_USER_ERROR   => 'QF ERROR',
            E_USER_WARNING => 'QF WARNING',
            E_USER_NOTICE  => 'QF NOTICE',
            E_STRICT       => 'PHP5 STRICT',
            );

        $errfile = str_replace('\\', '/', $errfile);
        if (strpos($errfile, $this->RootFul) === 0)
            $errfile = str_replace($this->RootFul, '', $errfile);

        if (!$logfile) {
            $logfile = fopen('qf2_err.log', 'ab');
        }

        if ( $errno & ~(E_NOTICE | E_USER_NOTICE | E_STRICT) )
        {
            if ($logfile)
                fwrite($logfile, date('[d M Y H:i]').' '.$ERR_TYPES[$errno].': '.$errstr.'. File: '.$errfile.'. Line: '.$errline.".\r\n");
        }

        if ( $errno & ~(E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING | E_STRICT) )
        {
            qf_ob_free();
            $QF->Cache->Clear();
            header('Content-Type: text/html; charset='.QF_INTERNAL_ENCODING);
            if (function_exists('apache_child_terminate'))
                apache_child_terminate();

            if ($debug)
                die ($ERR_TYPES[$errno].': '.$errstr.'. <hr>File: '.$errfile.'. Line: '.$errline.'<br />');
            else
                die ('<html><head><title>'.Lang('ERR_CRIT_PAGE', true).'</title></head><body><h1>'.Lang('ERR_CRIT_PAGE', true).'</h1>'.Lang('ERR_CRIT_MESS', true).'</body></html>');
        }
        elseIf ($debug) {
            //if ( $errno & ~(E_NOTICE | E_USER_NOTICE | E_STRICT) )
                print $ERR_TYPES[$errno].': '.$errstr.'. <hr>File: '.$errfile.'. Line: '.$errline.'<br />';
        }

    }


}

// Events controller
class QF_Events
{
    var $events = Array();

    function QF_Events()
    {

    }

    function Set_On_Event($ev_name, $func_link)
    {
        $ev_name = strtolower($ev_name);

        if (is_callable($func_link))
        {
            $this->events[$ev_name][] = $func_link;
            return true;
        }
        else
            return false;
    }

    // first three arguments may be parsed by link
    function Call_Event($ev_name)
    {
        $ev_name = strtolower($ev_name);

        if (!isset($this->events[$ev_name]))
            return false;
        else
        {
            if (func_num_args() > 1)
                $args = array_slice(func_get_args(), 1);
            else
                $args = Array();

            $ev_arr =& $this->events[$ev_name];

            foreach ($ev_arr as $ev_link)
                call_user_func_array($ev_link, $args);

            return true;
        }
    }

    // variant with arrayed args (references inside array may be used)
    function Call_Event_Arr($ev_name, $args = Array())
    {
        $ev_name = strtolower($ev_name);

        if (!isset($this->events[$ev_name]))
            return false;
        else
        {
            if (!is_array($args))
                $args = Array();

            $ev_arr =& $this->events[$ev_name];

            foreach ($ev_arr as $ev_link)
                call_user_func_array($ev_link, $args);

            return true;
        }
    }

    // special variant to parse first param by reference, all other params are passed by value
    function Call_Event_Ref($ev_name, &$var)
    {
        $ev_name = strtolower($ev_name);

        if (!isset($this->events[$ev_name]))
            return false;
        else
        {
            $args = Array(&$var);
            if (($nargs = func_num_args()) > 2)
                for ($i = 2; $i<$nargs; $i++)
                    $args[] = func_get_arg($i);

            $ev_arr =& $this->events[$ev_name];

            foreach ($ev_arr as $ev_link)
                call_user_func_array($ev_link, $args);

            return true;
        }
    }
}
?>
