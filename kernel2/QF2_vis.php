<?php

// -------------------------------------------------------------------------- \\
// VIS Class that provides template working and HTML visualization            \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');


if ( defined('QF_KERNEL_VIS_LOADED') )
        die('Scripting error');

define('QF_KERNEL_VIS_LOADED', True);

// Cache prefixes for module data
define('QF_KERNEL_VIS_VPREFIX', 'VIS.');
define('QF_KERNEL_VIS_CPREFIX', 'VIS_CSS.');
define('QF_KERNEL_VIS_JPREFIX', 'VIS_JS.');
define('QF_KERNEL_VIS_COMMON', 'common');
define('QF_KERNEL_VIS_DEFSTYLE', 'qf_def');

// data directories
define('QF_STYLES_DIR', QF_DATA_ROOT.'styles/');
//define('QF_STCNTS_DIR', QF_DATA_ROOT.'vis_consts/'); // not used for now - not needed
define('QF_JSCRIPTS_DIR', QF_DATA_ROOT.'jscripts/');
define('QF_STATICS_DIR', 'static');
define('QF_IMAGES_DIR', QF_STATICS_DIR.'/images');
define('QF_ST_IMGS_DIR', QF_IMAGES_DIR.'/styles/');

// defining some usefull constants
// VIS resource types
define('QF_VIS_NORMAL',  0);
define('QF_VIS_STATIC',  1);
define('QF_VIS_DINAMIC', 2);
// Breakline
define('VIS_BR', "\r\n");

// node flags
define('QF_VISNODE_ARRAY', 1); // node is an array of sametype nodes


class QF_Visual
{
    var $templates  = Array();

    var $VCSS_data  = ''; // CSS loaded from visuals
    var $VJS_data   = ''; // JS loaded from visuals
    var $CSS_data   = '';
    var $JS_data    = '';
    var $Consts     = Array();

    var $node_type  = Array();
    var $node_vars  = Array();
    var $node_subs  = Array();
    var $node_flags = Array();
    var $named_node = Array();
    var $parsed     = Array();

    var $style_name = QF_KERNEL_VIS_DEFSTYLE;
    var $style_vari = QF_KERNEL_VIS_COMMON;

    var $VIS_loaded = Array();
    var $CSS_loaded = false;
    var $JS_loaded  = Array();

    var $lang_name  = null;
    var $lang       = null;     // linking lang vars
    var $LNG_loaded = null;     // linking lang vars

    var $force_append  = true;  // forces to append CSS
    var $force_compact = true;  // forces to compact CSS/JS data
    var $vis_consts = Array();
    var $func_parsers = Array();

    function QF_Visual()
    {
        global $QF;

        if (defined('QF_VASUAL_CREATED'))
            trigger_error('Duplicate visual manager creation!', E_USER_ERROR);

        define('QF_VASUAL_CREATED', true);

        $this->node_type  = Array(0 => 'MAIN_PAGE');
        $this->style_name = QF_KERNEL_VIS_DEFSTYLE;
        $this->node_vars  = Array(0 => Array());
        $this->node_subs  = Array(0 => Array());
        $this->node_flags = Array(0 => 0);
        $this->named_node = Array('PAGE' => 0, 'MAIN' => 0);
        $this->func_parsers = Array(
            'FULLURL' => 'qf_full_url',
            'HTMLQUOTE' => 'htmlspecialchars',
            'URLEN' => 'qf_url_encode_part',
            'JS_DEF' => 'qf_value_JS_definition',
            'FTIME' => Array(&$QF->LNG, 'Time_Format'),
            'FBYTES' => Array(&$QF->LNG, 'Size_Format'),
            );
        $this->vis_consts = Array(
            'TIME' => $QF->Timer->time,
            );
    }

    function _Start()
    {
        global $QF;

        $data = $QF->LNG->Get_Data_Links();
        $this->lang       =& $data['lang'];
        $this->lang_name  =& $data['lang_name'];
        $this->LNG_loaded =& $data['LNG_loaded'];
        $this->Configure(Array(), true);
    }

    // configuring and loading functions
    function Configure($params, $force_clear = false)
    {
        global $QF;

        if (is_array($params))
        {
            extract($params);

            if ($force_clear) // clear all settings
            {
                $this->node_type  = Array(0 => 'MAIN_PAGE');
                $this->style_name = QF_KERNEL_VIS_DEFSTYLE;
                $this->style_vari = QF_KERNEL_VIS_COMMON;
                $this->node_vars  = Array(0 => Array());
                $this->node_subs  = Array(0 => Array());
                $this->named_node = Array('PAGE' => 0);
                $this->named_node = Array('MAIN' => 0);
                $this->templates  = Array();
                $this->VCSS_data  = '';
                $this->VJS_data   = '';
                $this->VIS_loaded = Array();
                $this->CSS_data   = '';
                $this->JS_data    = '';
                $this->CSS_loaded = false;
                $this->JS_loaded  = Array();
                $this->force_append  = true;
                $this->force_compact = true;
            }

            if (isset($lang))
                $QF->LNG->Select($lang);

            if (isset($style))
            {
                $this->style_name = preg_replace('#\W#', '_', $style);
                if (count($this->VIS_loaded))
                {
                    $parts = $this->VIS_loaded;
                    $this->templates = Array();
                    $this->VCSS_data = '';
                    $this->VJS_data  = '';
                    $this->VIS_loaded = Array();
                    if (!$force_clear)
                        foreach ($parts as $part)
                            $this->Load_Templates($part);
                }

                if (isset($CSS))
                    $this->style_vari = $CSS;
                elseif($CSS = $this->CSS_loaded)
                    if (!$force_clear)
                        $this->Load_ECSS($CSS);

            }
            elseif (isset($CSS))
                $this->style_vari = $CSS;

            if (isset($root_node))
                $this->node_type[0] = $root_node;

            if (isset($force_append))
                $this->force_append = (bool) $force_append;
            if (isset($force_compact))
                $this->force_compact = (bool) $force_compact;
        }
    }

    function Set_VConsts($consts, $no_replace = false)
    {        if (!is_array($consts))
            return false;

        if ($no_replace)
            $this->vis_consts = $this->vis_consts + $consts;
        else
            $this->vis_consts = $consts + $this->vis_consts;

        return true;
    }

    function Load_ECSS($variant = '')
    {
        Global $QF;

        if (!$variant)
            $variant = $this->style_vari;
        else
            $variant = strtolower(preg_replace('#\W#', '_', $variant));

        $cachename = QF_KERNEL_VIS_CPREFIX.$this->style_name.'.'.$this->lang_name.'.'.$variant;

        if ($Cdata = $QF->Cache->Get($cachename))
        {
            $this->CSS_data = $Cdata;
            $QF->Timer->Time_Log($this->style_name.'.'.$variant.' CSS file loaded (from global cache)');
        }
        else
        {
            $file = $variant.'.ecss';
            $style = $this->style_name;
            $cfile = QF_STYLES_DIR.$style.'/'.$file;
            $commons = QF_STYLES_DIR.$style.'/'.QF_KERNEL_VIS_COMMON.'.ecss';

            if (!file_exists($cfile))
            {
                trigger_error('VIS: there is no '.$this->style_name.'.'.$variant.' ECSS file', E_USER_WARNING );
                $cfile = $commons;
            }

            if ($indata = qf_file_get_contents($cfile))
            {
                $consts = Array(
                    'IMGS' => qf_full_url(QF_IMAGES_DIR),
                    'ST_IMGS' => qf_full_url(QF_ST_IMGS_DIR.$style),
                    'STATICS' => qf_full_url(QF_STATICS_DIR),
                    );

                if (($cfile != $commons) && strstr($indata, '{COMMON_ECSS}') && ($commons = qf_file_get_contents($commons)))
                    $indata = str_replace('{COMMON_ECSS}', $commons, $indata);

                $Cdata = $this->Prepare_ECSS($indata, $consts);

                $QF->Cache->Set($cachename, $Cdata);
                $this->CSS_data = $Cdata;
                $QF->Timer->Time_Log($this->style_name.'.'.$variant.' CSS file loaded (from ECSS file)');

            }
            else
                trigger_error('VIS: error loading '.$this->style_name.'.'.$variant.' ECSS file', E_USER_WARNING );
        }

        $this->style_vari = $this->CSS_loaded = $variant;
        return true;

    }

    function Load_EJS($name = '')
    {        Global $QF;

        if (!$name)
            $name = QF_KERNEL_VIS_COMMON;
        else
            $name = strtolower(preg_replace('#\W#', '_', $name));

        if (!in_array($name, $this->JS_loaded))
        {
            $cachename = QF_KERNEL_VIS_JPREFIX.$this->lang_name.'.'.$name;

            if ($JSData = $QF->Cache->Get($cachename))
            {
                $this->JS_data.= VIS_BR.$JSData;

                $QF->Timer->Time_Log('"'.$name.'" JScript loaded (from global cache)');
            }
            else
            {
                if (!in_array($name, $this->LNG_loaded))
                    $QF->LNG->Load_Language($name);

                $file = $name.'.ejs';
                $cfile = QF_JSCRIPTS_DIR.$file;

                if (!file_exists($cfile))
                {
                    trigger_error('VIS: there is no '.$name.' EJS file', E_USER_WARNING );
                }
                elseif ($indata = qf_file_get_contents($cfile))
                {
                    $style = $this->style_name;
                    $indata = str_replace('{ST_IMGS}', qf_full_url(QF_ST_IMGS_DIR.$style), $indata);
                    $indata = str_replace('{IMGS}', qf_full_url(QF_IMAGES_DIR), $indata);
                    $indata = str_replace('{STATICS}', qf_full_url(QF_STATICS_DIR), $indata);

                    $indata = preg_replace_callback('#\{(?>L_((?:\w+|\"[^\"]+\"|\|)+))\}#',Array(&$this, '_Templ_Lang_CB'),$indata);
                    $indata = preg_replace('#\{(?>CONST:([\w\|]+))\}#e','(isset(\$this->Templ_Consts("$1"))) ? \$this->Templ_Consts("$1") : ""',$indata);

                    $QF->Events->Call_Event_Ref('EJS_PreParse', $indata);

                    $JSData = $this->Prepare_EJS($indata);
                    $this->JS_data.= VIS_BR.$JSData;

                    $QF->Cache->Set($cachename, $JSData );
                    $QF->Timer->Time_Log('"'.$name.'" JScript loaded (from EJS file)');
                }
                else
                    trigger_error('VIS: error loading "'.$name.'" EJS file', E_USER_WARNING );
            }

            $this->JS_loaded[] = $name;
        }
        return true;
    }

    function Load_Templates($part = '', $force_style = false)
    {
        Global $QF;

        if (!$part)
            $part = QF_KERNEL_VIS_COMMON;
        else
            $part = strtolower(preg_replace('#\W#', '_', $part));

        if (!in_array($part, $this->VIS_loaded))
        {
            if (!in_array($part, $this->LNG_loaded))
                $QF->LNG->Load_Language($part);

            $cachename = QF_KERNEL_VIS_VPREFIX.$this->style_name.'.'.$this->lang_name.'.'.$part;

            if (list($Tdata, $VCSS, $VJS) = $QF->Cache->Get($cachename))
            {
                $this->templates += $Tdata;
                $this->VCSS_data .= VIS_BR.$VCSS;
                $this->VJS_data  .= VIS_BR.$VJS;

                // not used for now - not needed
                //if ($part == QF_KERNEL_VIS_COMMON && $add_Consts = qf_file_load_datafile(QF_STCNTS_DIR.QF_KERNEL_VIS_COMMON.'.cnt', true))
                //    $this->Consts += $add_Consts;

                $QF->Timer->Time_Log('"'.$part.'" visuals loaded (from global cache)');
            }
            else
            {
                $file = $part.'.vis';
                $style = ($force_style)
                    ? $force_style
                    : $this->style_name;
                $cfile = QF_STYLES_DIR.$style.'/'.$file;
                if (!file_exists($cfile))
                {
                    $style = QF_KERNEL_VIS_DEFSTYLE;
                    $cfile = QF_STYLES_DIR.$style.'/'.$file;
                }

                if (!file_exists($cfile))
                {
                    trigger_error('VIS: there is no '.$this->style_name.'.'.$part.' VIS file', E_USER_WARNING );
                }
                elseif ($indata = qf_file_get_contents($cfile))
                {
                    // not used for now - not needed
                    //if ($add_Consts = qf_file_load_datafile(QF_STCNTS_DIR.$part.'.cnt', true))
                    //    $this->Consts += $add_Consts;
                    $QF->Events->Call_Event_Ref('VIS_RawParse', $indata, $style, $part);

                    $indata = str_replace('{ST_IMGS}', QF_ST_IMGS_DIR.$style, $indata);
                    $indata = str_replace('{IMGS}', QF_IMAGES_DIR, $indata);
                    $indata = str_replace('{STATICS}', qf_full_url(QF_STATICS_DIR), $indata);

                    $indata = preg_replace_callback('#\{(?>L_((?:\w+|\"[^\"]+\"|\|)+))\}#',Array(&$this, '_Templ_Lang_CB'),$indata);
                    $indata = preg_replace('#\{(?>CONST:([\w\|]+))\}#e','(isset(\$this->Templ_Consts("$1"))) ? \$this->Templ_Consts("$1") : ""',$indata);

                    $QF->Events->Call_Event_Ref('VIS_PreParse', $indata, $style, $part);

                    preg_match_all("#<<\+ '(?>(\w+))'>>(.*?)<<- '\\1'>>#s", $indata, $blocks);

                    if (is_array($blocks[1]))
                    {
                        $Tdata  = Array();
                        $VCSS   = '';
                        $VJS    = '';
                        foreach ($blocks[1] as $num => $name)
                        {
                            $templ = $blocks[2][$num];

                            if ($name == 'CSS')
                                $VCSS.= $this->Prepare_ECSS($templ);
                            elseif ($name == 'JS')
                                $VJS.= $templ; // EJS can contain {V_ links
                                               // so we need to store it first and parse after VIS loading
                            else // normal VIS
                                $Tdata[$name] = $this->Prepare_VIS($templ);
                        }

                        $this->templates += $Tdata;
                        $this->VCSS_data .= VIS_BR.$VCSS;
                        $VJS = $this->Prepare_EJS($VJS);
                        $this->VJS_data  .= VIS_BR.$VJS;

                        $QF->Cache->Set($cachename, Array($Tdata, $VCSS, $VJS) );
                        $QF->Timer->Time_Log('"'.$part.'" visuals loaded (from VIS file)');
                    }
                    else
                        trigger_error('VIS: error parsing "'.$part.'" VIS file for style "'.$this->style_name.'"', E_USER_WARNING );

                }
                else
                    trigger_error('VIS: error loading "'.$part.'" VIS file for style "'.$this->style_name.'"', E_USER_WARNING );
            }

            $this->VIS_loaded[] = $part;

        }
        return true;
    }

    // parsing functions
    function Parse($node = 0)
    {
        if (!is_int($node))
            $node = $this->Find_Node($node);

        if (is_null($node))
            return false;

        if (isset($this->parsed[$node]))
            return $this->parsed[$node];

        if (!isset($this->node_type[$node]))
        {
            trigger_error('VIS: trying to parse a fake node', E_USER_WARNING);
            return false;
        }

        $type =& $this->node_type[$node];
        $vars =& $this->node_vars[$node];
        $subs =& $this->node_subs[$node];
        $flags =& $this->node_flags[$node];
        $data = Array();
        $text = '';

        if ($flags && QF_VISNODE_ARRAY)
        {
            $parts = Array();
            $delimiter = (isset($vars['_DELIM'])) ? $vars['_DELIM'] : '';
            foreach ($vars as $data)
                if (is_array($data))
                    $parts[] = $this->Parse_VIS($type, $data);
            $text = implode($delimiter, $parts);
        }
        else
        {
            foreach ($subs as $var => $subnodes)
                foreach ($subnodes as $subnode)
                    $vars[$var][] = $this->Parse($subnode);

            foreach ($vars as $var => $vals)
                $data[$var] = implode(VIS_BR, $vals);

            $text = $this->Parse_VIS($type, $data);
        }

        if ($node>0)
        {
            unset($this->node_type[$node],
                $this->node_vars[$node],
                $this->node_subs[$node],
                $this->node_flags[$node]);

            $this->parsed[$node] =& $text;
        }

        return $text;
    }

    function Make_HTML()
    {
        if ($this->force_append)
        {
            if (!$this->CSS_loaded)
                $this->load_ECSS();
            if (!in_array(QF_KERNEL_VIS_COMMON, $this->JS_loaded))
                $this->load_EJS();
        }

        if (!count($this->VIS_loaded))
            $this->Load_Templates();

        $type =& $this->node_type[0];
        $vars =& $this->node_vars[0];
        $subs =& $this->node_subs[0];
        $data = Array();
        $text = '';
        $vars['CSS'][] = trim($this->CSS_data.VIS_BR.$this->VCSS_data);
        $vars['JS'][]  = trim($this->JS_data.VIS_BR.$this->VJS_data);

        foreach ($subs as $var => $subnodes)
            foreach ($subnodes as $subnode)
                $vars[$var][] = $this->Parse($subnode);

        foreach ($vars as $var => $vals)
            $data[$var] = implode(VIS_BR, $vals);


        $text = $this->Parse_VIS($type, $data);

        return $text;
    }

    function Make_CSS()
    {
        if (!$this->CSS_loaded)
            $this->load_ECSS();
        return trim($this->CSS_data);
    }

    function Make_JS()
    {
        if (!$this->JS_loaded)
            $this->load_EJS();
        return trim($this->JS_data);
    }

    // tree construction functions
    function Create_Node($template, $data_arr = false, $globname = null)
    {
        $template = (string) $template;
        if (!$template)
            return false;

        end($this->node_type);
        $id = key($this->node_type) + 1;
        $this->node_type[$id] = $template;
        $this->node_vars[$id] = Array();
        $this->node_subs[$id] = Array();
        $this->node_flags[$id] = 0;

        if (is_array($data_arr))
            foreach ($data_arr as $key => $var)
            {
                $key = strtoupper($key);
                if (is_array($var))
                    $var = implode(' ', $var);
                $this->node_vars[$id][$key][] = $var;
            }

        if ($globname && !is_numeric($globname))
        {
            $globname = strtoupper($globname);
            $this->named_node[$globname] = $id;
        }

        return $id;
    }

    function Append_Node($node_id, $varname, $parent = 0)
    {
        if (!is_int($parent))
            $parent = $this->Find_Node($parent);
        if (!is_int($node_id))
            $node_id = $this->Find_Node($node_id);

        $varname  = strtoupper($varname);

        if (!$varname)
            return false;

        if (!isset($this->node_type[$parent]))
        {
            trigger_error('VIS: trying to append data to fake node', E_USER_WARNING);
            return false;
        }
        if (!isset($this->node_type[$node_id]))
        {
            trigger_error('VIS: trying to append a fake node', E_USER_WARNING);
            return false;
        }

        $this->node_subs[$parent][$varname][] = $node_id;
        return true;
    }

    function Add_Node($template, $varname, $parent = 0, $data_arr = false, $globname = null)
    {
        if (!is_int($parent))
            $parent = $this->Find_Node($parent);

        $varname  = strtoupper($varname);

        if (!$varname)
            return false;

        if (!isset($this->node_type[$parent]))
        {
            trigger_error('VIS: trying to append data to fake node', E_USER_WARNING);
            return false;
        }

        if ($id = $this->Create_Node($template, $data_arr, $globname))
        {
            $this->node_subs[$parent][$varname][] = $id;
            return $id;
        }

        return false;
    }

    // Adds arrayed node
    function Add_Node_Array($template, $varname, $parent = 0, $data_arr = false, $delimiter = false)
    {
        if (!is_int($parent))
            $parent = $this->Find_Node($parent);

        $varname  = strtoupper($varname);

        if (!$varname)
            return false;

        if (!isset($this->node_type[$parent]))
        {
            trigger_error('VIS: trying to append data to fake node', E_USER_WARNING);
            return false;
        }

        if ($id = $this->Create_Node($template))
        {
            $this->node_subs[$parent][$varname][] = $id;
            $this->node_flags[$id] = QF_VISNODE_ARRAY;

            if (is_array($data_arr) && count($data_arr))
            {
              $in = 0;
              foreach ($data_arr as $arr)
                if (is_array($arr))
                {
                  foreach ($arr as $key => $var)
                  {
                      $key = strtoupper($key);
                      if (is_array($var))
                          $var = implode(' ', $var);
                      $this->node_vars[$id][$in][$key] = $var;
                  }

                  $this->node_vars[$id][$in]['_POS'] = $in;

                  $in++;
                }

                $this->node_vars[$id][0]['_IS_FIRST'] = '1';
                $this->node_vars[$id][$in-1]['_IS_LAST'] = '1';
            }

            if (strlen($delimiter))
                $this->node_vars[$id]['_DELIM'] = (string) $delimiter;
            return $id;
        }

        return false;
    }

    function Add_Data($node, $varname, $data)
    {
        if (is_null($node))
            return false;

        $varname = strtoupper($varname);

        if (!$varname)
            return false;

        if (!isset($this->node_type[$node]) || ($this->node_flags[$node] && QF_VISNODE_ARRAY))
        {
            trigger_error('VIS: trying to append data to fake node', E_USER_WARNING);
            return false;
        }

        if (is_array($data))
            $data = implode(' ', $data);
        $this->node_vars[$node][$varname][] = $data;

        return true;
    }

    function Add_Data_Array($node, $arr)
    {
        if (is_null($node))
            return false;

        if (!isset($this->node_type[$node]) || ($this->node_flags[$node] && QF_VISNODE_ARRAY))
        {
            trigger_error('VIS: trying to append data to fake node', E_USER_WARNING);
            return false;
        }

        if (!is_array($arr))
            return false;

        foreach ($arr as $key => $var)
        {
            $key = strtoupper($key);
            if (is_array($var))
                $var = implode(' ', $var);
            $this->node_vars[$node][$key][] = $var;
        }

        return true;
    }

    function Find_Node($to_find)
    {
        if (!$to_find)
            return 0;

        if (!is_numeric($to_find))
        {
            $to_find = strtoupper($to_find);
            if (isset($this->named_node[$to_find]))
                $to_find = $this->named_node[$to_find];
            else
                return null;
        }

        $to_find = (int) $to_find;
        return $to_find;
    }


    // Inner parsing functions

    // reparses {L_...} blocks in raw templates
    function _Templ_Lang_CB($matches)
    {
        $code = $matches[1];
        $code = explode('|', $code);

        if (!($lng = strtoupper($code[0])))
            return '';
        if (!isset($this->lang[$lng]))
            return '';

        $data = $this->lang[$lng];
        if (count($code)>1)
        {
            $params = array_slice($code, 1);
            foreach ($params as $id => $val)
                $params[$id] = ($val{0} == '"') ? substr($val, 1, -1) : '{'.$val.'}';

            $data = qf_sprintf_arr($data, $params);
        }

        return $data;
    }

    function Templ_Consts($code)
    {
        $code = explode('|', $code);

        if (!($id = strtoupper($code[0])))
            return '#';
        if (!isset($this->Consts[$id]))
            return '#';

        $data = $this->Consts[$id];
        if (count($code)>1)
        {
            $params = array_slice($code, 1);
            foreach ($params as $id => $val)
                $params[$id] = (is_numeric($val{0})) ? (int) $val : '{'.$val.'}';
            $data = qf_sprintf_arr($data, $params);
        }

        return $data;
    }

    function Prepare_VIS($text)
    {
        global $QF;

        static $consts = Array(
            'QF_MARK'  => 'Powered by<br />QuickFox 2<br />&copy; Foxel aka LION<br /> 2006 - 2009',
            'QF_INDEX' => QF_INDEX,
            );

        $consts['QF_ROOT'] = $QF->HTTP->RootUrl;
        $text = trim($text);

        if ($this->force_compact)
            $text = $this->VIS_compact($text);

        $text = preg_replace('#(?<=\})\n\s*?(?=\{\w)#', '', $text);
        preg_match_all('#\{(\!?)((?>\w+))(?:\:((?:(?>\w+)(?:[\!=\>\<]{1,2}(?:\w+|\"[^\"]*\"))?|\||)*))?\}|[^\{]+|\{#', $text, $struct, PREG_SET_ORDER);

        $writes_to = '$OUT';
        $text = $writes_to.' = <<<QFT'.VIS_BR;
        $vars = Array();

        $iflevel = 0;
        $outiflevel = 0;
        $in_for = false;

        $keys = array_keys($struct);

        foreach ($keys as $key)
        {
            $part =& $struct[$key];

            if (isset($part[2]) && ($tag = strtoupper($part[2])))
            {
                $got_a = ($part[1]) ? true : false;
                $params = Array();
                if (isset($part[3]) && ($got = preg_match_all('#((?>\w+))(?:([\!=\>\<]{1,2})(\w+|\"[^\"]*\"))?#', $part[3], $params, PREG_PATTERN_ORDER)))
                    for ($i = 0; $i < $got; $i++)
                        $params[1][$i] = strtoupper($params[1][$i]);

                if ($tag == 'WRITE')
                {                    if (isset($params[1]) && count($params[1]) && ($var = $params[1][0]) && !is_numeric($var{0}))
                        $var = '$'.$var;
                    else
                        $var = '$OUT';
                    if ($var != $writes_to)
                    {                        $writes_to = $var;
                        $text.= VIS_BR.'QFT;'.VIS_BR.$writes_to.(($got_a) ? '' : '.').'= <<<QFT'.VIS_BR;
                    }
                }
                elseif (isset($this->func_parsers[$tag])) //parsing the variable with func
                {
                    $func_parser = $this->func_parsers[$tag];

                    if (!isset($params[1]) || !count($params[1]))
                        continue;
                    $pars = count($params[1]);
                    $needFunc = false;
                    $realPars = Array();
                    $varPars = Array();
                    for ($i = 0; $i < $pars; $i++)
                    {
                        $val = $params[1][$i];

                        if (is_numeric($val{0}))
                            $text.= qf_heredoc_addslashes(qf_func_call($func_parser, intval($val)), 'QFT');
                        elseif ($val{0} == '"')
                            $text.= qf_heredoc_addslashes(qf_func_call($func_parser, substr($val, 1, -1)), 'QFT');
                        else
                        {
                            $val = strtoupper($val);
                            if (substr($val, 0, 2) == 'L_')
                                $text.= qf_heredoc_addslashes(qf_func_call($func_parser, $this->_Templ_Lang_CB(Array(1 => substr($val, 2)))), 'QFT');
                            elseif (isset($consts[$val]))
                                $text.= qf_heredoc_addslashes(qf_func_call($func_parser, $consts[$val]), 'QFT');
                            else
                            {
                                $vars[$val] = '';
                                $text.= VIS_BR.'QFT'.VIS_BR.'.$this->Do_Func(\''.$tag.'\', $'.$val.').<<<QFT'.VIS_BR;
                            }
                        }
                    }
                }
                elseif ($tag == 'SET')
                {                    if ($pars = count($params[1]))
                    {
                        $sets = '';
                        for($i = 0; $i < $pars; $i++)
                        {                            $var = $params[1][$i];
                            if (is_numeric($var{0}) || !isset($params[3][$i]) && !strlen($params[3][$i]))
                                continue;
                            $val = $params[3][$i];
                            $sets.= '$'.$var.' = ';

                            if (is_numeric($val{0}))
                                $sets.= intval($val).';';
                            elseif ($val{0} == '"')
                                $sets.= '<<<QFT'.VIS_BR.qf_heredoc_addslashes(substr($val, 1, -1), 'QFT').VIS_BR.'QFT'.VIS_BR.';';
                            else
                            {
                                $val = strtoupper($val);
                                if (substr($val, 0, 2) == 'L_')
                                    $sets.= '<<<QFT'.VIS_BR.qf_heredoc_addslashes($this->_Templ_Lang_CB(Array(1 => substr($val, 2))), 'QFT').VIS_BR.'QFT'.VIS_BR.';';
                                elseif (isset($consts[$val]))
                                    $sets.= '<<<QFT'.VIS_BR.qf_heredoc_addslashes($consts[$val], 'QFT').VIS_BR.'QFT'.VIS_BR.';';
                                else
                                {
                                    $vars[$val] = '';
                                    $sets.= '$'.$val.';';
                                }
                            }
                        }

                        if ($sets)
                            $text.= VIS_BR.'QFT;'.VIS_BR.$sets.VIS_BR.$writes_to.'.= <<<QFT'.VIS_BR;
                    }
                }
                elseif ($tag == 'FOR')
                {                    if (!isset($params[1]) || !count($params[1]))
                        continue;
                    $params = $params[1];
                    $p1 = array_shift($params);
                    $p2 = array_shift($params);
                    $p3 = array_shift($params);

                    $p1 = (is_numeric($p1{0})) ? intval($p1) : '(int) $'.$p1.($vars[$p1] = '');
                    if ($p2)
                        $p2 = (is_numeric($p2{0})) ? intval($p2) : '(int) $'.$p2.($vars[$p2] = '');
                    else
                    {
                        $p2 = $p1;
                        $p1 = '0';
                    }
                    if ($p3)
                        $p3 = (is_numeric($p3{0})) ? intval($p3) : '(int) $'.$p3.($vars[$p3] = '');
                    else
                        $p3 = '1';

                    $in_for = true;
                    $outiflevel = $iflevel;
                    $iflevel = 0;
                    $text.= VIS_BR.'QFT;'.VIS_BR.'for ($I = '.$p1.'; $I <= '.$p2.'; $I+= '.$p3.') {'.VIS_BR.$writes_to.'.= <<<QFT'.VIS_BR;
                }
                elseif ($tag == 'ENDFOR')
                {
                    if ($in_for)
                    {
                        $text.= VIS_BR.'QFT;'.VIS_BR;
                        $in_for = false;
                        $text.= str_repeat('} ', $iflevel);
                        $text.= VIS_BR.'}'.VIS_BR.$writes_to.'.= <<<QFT'.VIS_BR;
                        $iflevel = $outiflevel;
                    }
                }
                elseif ($tag == 'VIS')
                {
                    if (!isset($params[1]) || !count($params[1]))
                        continue;
                    $visname = $params[1][0];
                    $text.= VIS_BR.'QFT;'.VIS_BR.$writes_to.(($got_a) ? '' : '.').'= $this->Parse_VIS(\''.$visname.'\'';
                    if (count($params[1]) > 1)
                    {
                        $text.= ', Array(';
                        $pars = count($params[1]);
                        for($i = 1; $i < $pars; $i++)
                        {
                            $var = $params[1][$i];
                            $val = (isset($params[3][$i]) && strlen($params[3][$i])) ? $params[3][$i] : '1';
                            $text.= '\''.$var.'\' => ';

                            if (is_numeric($val{0}))
                                $text.= intval($val).',';
                            elseif ($val{0} == '"')
                                $text.= '<<<QFT'.VIS_BR.qf_heredoc_addslashes(substr($val, 1, -1), 'QFT').VIS_BR.'QFT'.VIS_BR.',';
                            else
                            {                                $val = strtoupper($val);
                                if (substr($val, 0, 2) == 'L_')
                                    $text.= '<<<QFT'.VIS_BR.qf_heredoc_addslashes($this->_Templ_Lang_CB(Array(1 => substr($val, 2))), 'QFT').VIS_BR.'QFT'.VIS_BR.',';
                                elseif (isset($consts[$val]))
                                    $text.= '<<<QFT'.VIS_BR.qf_heredoc_addslashes($consts[$val], 'QFT').VIS_BR.'QFT'.VIS_BR.',';
                                else
                                {
                                    $vars[$val] = '';
                                    $text.= '$'.$val.',';
                                }
                            }
                        }
                        $text.= ') ';
                    }
                    $text.= ');'.VIS_BR.$writes_to.'.= <<<QFT'.VIS_BR;
                }
                elseif ($tag == 'IF' || $tag == 'ELSEIF')
                {
                    if (!isset($params[1]) || !count($params[1]))
                        continue;
                    $varname = $params[1][0];
                    $vars[$varname] = '';
                    if (isset($params[3][0]) && strlen($params[3][0]))
                    {
                        $condvar = $params[3][0];
                        $condition = $params[2][0];
                        switch ($condition)
                        {
                            case '>':
                            case '<':
                            case '>=':
                            case '<=':
                            case '!=':
                                $condition = ' '.$condition.' ';
                                break;
                            default:
                                $condition = ' == ';

                        }

                        if (is_numeric($condvar{0}))
                            $condition = '((int) $'.$varname.$condition.intval($condvar).')';
                        elseif ($condvar{0} == '"')
                        {
                            $condvar = substr($condvar, 1, -1);
                            $condition = '($'.$varname.$condition.'\''.strtr($condvar, Array('\'' => '\\\'', '\\' => '\\\\')).'\')';
                        }
                        else
                            $condition = '($'.$varname.$condition.'(string) $'.$condvar.')';
                    }
                    else
                        $condition = 'strlen($'.$varname.')';

                    if ($got_a)
                        $condition = '!'.$condition;

                    if ($tag == 'IF')
                    {
                        $text.= VIS_BR.'QFT;'.VIS_BR.'if ('.$condition.') {'.VIS_BR.$writes_to.'.= <<<QFT'.VIS_BR;
                        $iflevel++;
                    }
                    elseif ($iflevel)
                        $text.= VIS_BR.'QFT;'.VIS_BR.'} elseif('.$condition.') {'.VIS_BR.$writes_to.'.= <<<QFT'.VIS_BR;
                }
                elseif ($tag == 'ELSE')
                {
                    if ($iflevel)
                        $text.= VIS_BR.'QFT;'.VIS_BR.'} elseif(true) {'.VIS_BR.$writes_to.'.= <<<QFT'.VIS_BR;
                }
                elseif ($tag == 'ENDIF')
                {
                    if ($iflevel)
                    {
                        $text.= VIS_BR.'QFT;'.VIS_BR.'}'.VIS_BR.$writes_to.'.= <<<QFT'.VIS_BR;
                        $iflevel--;
                    }
                }
                else
                {
                    $varname = $tag;
                    if (isset($consts[$varname]))
                        $text.= qf_heredoc_addslashes($consts[$varname], 'QFT');
                    elseif (!is_numeric($varname{0}))
                    {
                        $vars[$varname] = '';
                        if ($got_a)
                            $text.= VIS_BR.'QFT'.VIS_BR.'.qf_smart_htmlschars($'.$varname.').<<<QFT'.VIS_BR;
                        else
                            $text.= '{$'.$varname.'}';
                    }
                }
            }
            else
            {
                $text.= qf_heredoc_addslashes($part[0], 'QFT');
            }

            unset($struct[$key]);
        }

        $text.= VIS_BR.'QFT;'.VIS_BR;

        $text.= str_repeat('} ', $iflevel);
        if ($in_for)
            $text.= str_repeat('} ', $outiflevel+1);

        $text = preg_replace('#\$\w+\.\=\s*\<\<\<QFT\s+QFT;#', '', $text);
        return Array('T' => $text, 'V' => $vars);
    }

    function ____PreReplace_VISes($VISes) //RAW VISes including... TODO
    {        foreach ($VISes as $id => $text)
        {            preg_match_all('#\{\!?VIS(?:\:((?:(?>\w+)(?:[\!=\>\<]{1,2}(?:\w+|\"[^\"]*\"))?|\||)*))?\}|[^\{]+|\{#', $text, $struct, PREG_SET_ORDER);

            $text = '';
            $keys = array_keys($struct);

            foreach ($keys as $key)
            {
                $part =& $struct[$key];

                if (isset($part[2]) && ($tag = strtoupper($part[2])))
                {
                    $got_a = ($part[1]) ? true : false;
                    $params = Array();
                    if (isset($part[3]) && ($got = preg_match_all('#((?>\w+))(?:([\!=\>\<]{1,2})(\w+|\"[^\"]*\"))?#', $part[3], $params, PREG_PATTERN_ORDER)))
                        for ($i = 0; $i < $got; $i++)
                            $params[1][$i] = strtoupper($params[1][$i]);
                }
            }
        }
    }

    function Do_Func($func_name, $data) //parsing data with funcParser
    {
        if (!isset($this->func_parsers[$func_name]))
            return $data;

        $func_parser = $this->func_parsers[$func_name];
        return qf_func_call($func_parser, $data);
    }

    function Parse_VIS($vis, $data = Array())
    {
        Static $__COUNTER=1;
        Static $QF_SID;
        if (!$QF_SID)
        {
            global $QF;
            $QF_SID = $QF->Session->SID;
            unset($QF);
        }

        $COUNTER = $__COUNTER++;
        $RANDOM = dechex(rand(0x1FFF, getrandmax()));

        if (!isset($this->templates[$vis]))
            return implode(VIS_BR, $data);

        if (extract($this->templates[$vis]['V'], EXTR_SKIP))
        {
            extract($data, EXTR_OVERWRITE | EXTR_PREFIX_ALL, 'IN');
            extract($this->vis_consts, EXTR_OVERWRITE | EXTR_PREFIX_ALL, 'C');
        }

        $OUT = '';

        if (eval($this->templates[$vis]['T']) === false)
            trigger_error('VIS: error running "'.$vis.'" template. CODE ['.$this->templates[$vis]['T'].']', E_USER_ERROR);

        return $OUT;
    }

    function Prepare_ECSS($indata, $constants = null)
    {
        if (is_array($constants))
            foreach ($constants as $name=>$val)
                $indata = str_replace('{'.$name.'}', $val, $indata);

        $vars_mask='#\{((?>[\w\-]+))\}\s*=(.*)#';
        $vars_block='#\{VARS\}(.*?)\{/VARS\}#si';

        preg_match_all($vars_block, $indata, $blocks);
        $blocks = implode(' ', $blocks[0]);

        preg_match_all($vars_mask, $blocks, $sets);
        if (is_array($sets[1]))
            foreach ($sets[1] as $num => $name)
                $CSSVars[strtoupper($name)] = trim($sets[2][$num]);

        $Cdata = preg_replace($vars_block, '', $indata);

        if ($this->force_compact)
            $Cdata = $this->CSS_compact($Cdata);

        $Cdata = preg_replace('#\{(?>(\w+))\}#e', '(isset(\$CSSVars[strtoupper("\1")])) ? \$CSSVars[strtoupper("\1")] : ""', $Cdata);

        return $Cdata;
    }

    function Prepare_EJS($Jdata, $constants = null)
    {
        if (is_array($constants))
            foreach ($constants as $name=>$val)
                $Jdata = str_replace('{'.$name.'}', $val, $Jdata);
        if ($this->force_compact)
            $Jdata = $this->EJS_compact($Jdata);
        return $Jdata;
    }

    function EJS_compact($indata)
    {
        $indata = str_replace("\r", '', $indata);
        $indata = preg_replace('#^//.*?$#m', '', $indata);
        $indata = preg_replace('#(?<=\s)//.*?$#m', '', $indata);
        $indata = preg_replace('#(\n\s*)+#', "\n", $indata);
        $indata = preg_replace('#\n(.{1,5})$#m', ' \\1', $indata);
        $indata = str_replace("\n", VIS_BR, $indata);
        $indata = trim($indata);
        return $indata;
    }

    function CSS_compact($indata)
    {
        $indata = str_replace("\r", '', $indata);
        $indata = preg_replace('#/\*.+\*/#sU', '', $indata);
        $indata = preg_replace('#(\n\s*)+#', "\n", $indata);
        $indata = preg_replace('#\n(.{1,5})$#m', ' \\1', $indata);
        $indata = preg_replace('#\s*(,|:|;|\{)\s+#', '\\1 ', $indata);
        $indata = preg_replace('#\s+\}\s+#', " }\n", $indata);
        $indata = str_replace("\n", VIS_BR, $indata);
        $indata = trim($indata);
        return $indata;
    }

    function VIS_compact($indata)
    {
        $indata = str_replace("\r", '', $indata);
        $indata = preg_replace('#(\n\s*)+#', "\n", $indata);
        $indata = preg_replace('#\n(.{1,5})$#m', ' \\1', $indata);
        $indata = preg_replace('#\x20+#', ' ', $indata);
        $indata = str_replace("\n", VIS_BR, $indata);
        $indata = trim($indata);
        return $indata;
    }
}


?>
