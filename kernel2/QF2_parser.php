<?php

// -------------------------------------------------------------------------- \\
// QuickFox kernel 2 parser classes                                           \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if ( defined('QF_KERNEL_PARSER_LOADED') )
        die('Scripting error');

define('QF_KERNEL_PARSER_LOADED', True);

// defining parse mode constants
define('QF_BBPARSE_CHECK', 0);     // only checks and reconstructs bb-code structure
define('QF_BBPARSE_ALL',  1);       // parses all tags
define('QF_BBPARSE_PREP', 2);      // parses only static tags (preaparation to cache)
define('QF_BBPARSE_POSTPREP', 3);  // parses only tags (postparsing of cached)

// defining tag mode constants
define('QF_BBTAG_NOSUB',  1);      // inside that tag bbtags can not be opened
define('QF_BBTAG_SUBDUP', 2);      // can have itself as a subtag (e.g. quote inside quote)
define('QF_BBTAG_BLLEV',  4);      // blocklevel tags - can't be inside of non-blocklevel
define('QF_BBTAG_USEBRK', 8);      // this tag uses bekers within it's contents
define('QF_BBTAG_FHTML', 16);      // formatted html string as a replace (not a html tag name)
define('QF_BBTAG_NOCH',  32);      // tag data in not cachable (must have function to parse)

// defining XML tag mode constants
define('QF_XMLTAG_ACLOSE',  1);    // self closing tag (e.g. <br />
define('QF_XMLTAG_XHEADER', 2);    // self closing tag (e.g. <br />

class QF_BB_Parser
{
    var $mode = 0;
    var $tags = Array();
    var $pregs = Array();
    var $noparse_tag = 'no_bb';    // contetns of this tag will not be parsed (lower case)
    var $tagbreaker   = '*';
    var $tag_stack = Array();

    var $last_time = 0;
    var $cur_mode = 0;

    function QF_BB_Parser()
    {

    }

    function Init_Std_Tags()
    {
        global $QF;

        $this->Add_Tag('b', 'b');
        $this->Add_Tag('i', 'i');
        $this->Add_Tag('u', 'u');
        $this->Add_Tag('s', 'strike');
        $this->Add_Tag('sub', 'sub');
        $this->Add_Tag('sup', 'sup');

        $this->Add_Tag('h0', 'h1', QF_BBTAG_BLLEV);
        $this->Add_Tag('h1', 'h2', QF_BBTAG_BLLEV);
        $this->Add_Tag('h2', 'h3', QF_BBTAG_BLLEV);

        $this->Add_Tag('align', '<div style="text-align: {param};">{data}</div>', QF_BBTAG_FHTML | QF_BBTAG_BLLEV, Array('param_mask' => 'left|right|center|justify') );
        $this->Add_Tag('center', '<div style="text-align: center;">{data}</div>', QF_BBTAG_FHTML | QF_BBTAG_BLLEV);
        $this->Add_Tag('float', '<div style="float: {param};">{data}</div>', QF_BBTAG_FHTML | QF_BBTAG_BLLEV, Array('param_mask' => 'left|right') );

        $this->Add_Tag('color', '<span style="color: {param};">{data}</span>', QF_BBTAG_FHTML, Array('param_mask' => '\#[0-9A-Fa-f]{6}|[A-z\-]+') );
        $this->Add_Tag('background', '<span style="background-color: {param};">{data}</span>', QF_BBTAG_FHTML, Array('param_mask' => '\#[0-9a-f]{6}|[a-z\-]+') );
        $this->Add_Tag('font', '<span style="font-family: {param};">{data}</span>', QF_BBTAG_FHTML, Array('param_mask' => '[0-9A-z\x20]+') );
        $this->Add_Tag('size', '<span style="font-size: {param}px;">{data}</span>', QF_BBTAG_FHTML, Array('param_mask' => '[1-2]?[0-9]') );
        $this->Add_Tag('email', '<a href="mailto:{data}">{data}</a>', QF_BBTAG_FHTML, Array('data_mask' => QF_EMAIL_MASK));
        $this->Add_Tag('img', '', QF_BBTAG_NOSUB, Array('func' => Array( &$this, '_BBCode_Std_UrlImg') ) );
        $this->Add_Tag('url', '', false, Array('func' => Array( &$this, '_BBCode_Std_UrlImg') ) );
        $this->Add_Tag('table', '', QF_BBTAG_BLLEV | QF_BBTAG_USEBRK, Array('func' => Array( &$this, '_BBCode_Std_Table') ) );

        $this->Add_Preg(QF_FURL_MASK, '[url]{data}[/url]');
        //$this->Add_Preg(QF_EMAIL_MASK, '[email]{data}[/email]');

        $this->Add_Tag('quote', '<div class="qf_quote_outer"><div class="qf_quote_capt">'.$QF->LNG->Lang('QUOTE_CAPT').': <b>{param}</b></div><div class="qf_quote_text">{data}</div></div>', QF_BBTAG_BLLEV | QF_BBTAG_FHTML | QF_BBTAG_SUBDUP );
    }

    function Add_Tag($bbtag, $html, $tag_mode=0, $extra = null)
    {
        static $extras = Array( 'param', 'param_mask', 'func', 'data_mask' );

        $bbtag = strtolower($bbtag);
        if (!$bbtag)
            return false;

        $newtag = Array(
            'html'       => $html,
            'mode'       => (int) $tag_mode,
            );

        if (is_array($extra))
        {
            foreach ($extras as $exname)
                if (isset($extra[$exname]))
                    $newtag[$exname] = $extra[$exname];
                else
                    $newtag[$exname] = '';
        }
        else
            foreach ($extras as $exname)
                $newtag[$exname] = '';

        $this->tags[$bbtag] = $newtag;

        return true;
    }

    function Add_Preg($mask, $data, $func = null)
    {        static $preg_lk = null;
        if (!$preg_lk)
            $preg_lk = (PHP_VERSION >= '4.3.0') ? '${0}' : '$0$99'; // ${n} was added only in PHP 4.3.0 so this is a trick

        $id = count($this->pregs);
        $mask = '#(?<=\s|^)'.$mask.'(?=\s|$)#';
        $data = strtr($data, Array('\\' => '\\\\', '$' => '\\$'));
        $data = strtr($data, Array('{data}' => $preg_lk));
        $new_preg = Array(
            'mask' => $mask,
            'data' => $data,
            );
        if ($func && is_callable($func)) // some trick with functioned replaces
        {
            $gen_tag = 'preg_trigger_'.$id;
            $this->Add_Tag($gen_tag, '', QF_BBTAG_NOSUB, Array('func' => $func ) );
            $new_preg['data'] = '['.$gen_tag.']$0[/'.$gen_tag.']';
        }
        $this->pregs[$id] = $new_preg;

        return true;
    }

    function Parse($input, $mode = QF_BBPARSE_CHECK, $style = 0)
    {
        if ($mode == QF_BBPARSE_ALL || $mode == QF_BBPARSE_PREP) // doing replaces and html strips
        {
            $input = htmlspecialchars($input, ENT_NOQUOTES);
            $input = $this->Pregs_Parse($input, $style);
            $input = nl2br($input);
        }
        elseif ($mode == QF_BBPARSE_POSTPREP) // in postprep mode tagparcer works with all the tags
            $mode = QF_BBPARSE_ALL;

        $input = $this->BB_Parse($input, $mode, $style);

        return $input;
    }

    function Pregs_Parse($input, $style = 0)
    {        if (!is_array($this->pregs))
            return $input;
        foreach ($this->pregs as $preg)
        {            $input = preg_replace($preg['mask'], $preg['data'], $input);
        }

        return $input;
    }

    function BB_Parse($input, $mode = QF_BBPARSE_CHECK, $style = 0)
    {
        $stime = explode(' ',microtime());
        $start_time=$stime[1]+$stime[0];

        if (!count($this->tags))
            $this->Init_Std_Tags();
        //    return $input;       // there is no loaded tags data

        $this->cur_mode = (int) $mode;

        $state_nobb  = false;
        $state_strip = false;
        $state_breakers = 0;
        $used_tags   = Array();
        $cur_tag     = null;
        $buffer      = '';
        $struct      = Array();

        preg_match_all('#\[((?>[\w]+)|'.preg_quote($this->tagbreaker).')(?:\s*=\s*(\"([^\"\[\]]*)\"|[^\s<>\[\]]+))?\s*\]|\[\/((?>\w+))\]|[^\[]+|\[#', $input, $struct, PREG_SET_ORDER);

        $this->_TStack_Clear();

        foreach ($struct as $part)
        {

            if ($tagname = strtolower($part[1]))      // open tag
            {
                if ($tagname == $this->noparse_tag)
                {
                    if ($this->cur_mode == QF_BBPARSE_CHECK || $this->cur_mode == QF_BBPARSE_PREP || $state_nobb)
                    {
                        $tdata = '['.$this->noparse_tag.']';
                        if (!$this->_TStack_Write($tdata))
                            $buffer.= $tdata;
                    }
                    $state_nobb = true;
                }
                elseif ($tagname == $this->tagbreaker && !$state_nobb)
                {
                    if ($state_breakers)
                    {
                        while ($subtname = $this->_TStack_Last())
                        {
                            $subtmode = $this->tags[$subtname]['mode'];
                            if ($subtmode & QF_BBTAG_USEBRK)
                                break;
                            else
                            {
                                $tdata = $this->_TStack_Get();
                                if (isset($used_tags[$subtname]))
                                    $used_tags[$subtname]--;

                                $tdata = $this->Parse_Tag($tdata['name'], $tdata['param'], $tdata['buffer']);
                                if (!$this->_TStack_Write($tdata))
                                    $buffer.= $tdata;

                            }
                        }
                    }

                    $tdata = '['.$this->tagbreaker.']';
                    if (!$this->_TStack_Write($tdata))
                        $buffer.= $tdata;
                }
                elseif (isset($this->tags[$tagname]) && !$state_nobb)
                {
                    $tag = $this->tags[$tagname];
                    $tmode = $tag['mode'];

                    if ($state_strip)
                    {
                        // do nothing - strippeng tags
                    }
                    else
                    {
                        if ($tmode & QF_BBTAG_BLLEV)
                            while ($subtname = $this->_TStack_Last())
                            {
                                $subtmode = $this->tags[$subtname]['mode'];
                                if ($subtmode & QF_BBTAG_BLLEV)
                                    break;
                                $tdata = $this->_TStack_Get();
                                $subtname = $tdata['name'];
                                if (isset($used_tags[$subtname]))
                                    $used_tags[$subtname]--;

                                if ($subtmode & QF_BBTAG_USEBRK && $state_breakers)
                                    $state_breakers--;

                                $tdata = $this->Parse_Tag($tdata['name'], $tdata['param'], $tdata['buffer']);
                                if (!$this->_TStack_Write($tdata))
                                    $buffer.= $tdata;
                            }

                        if ($tmode & QF_BBTAG_USEBRK)
                            $state_breakers++;

                        $tused = (isset($used_tags[$tagname])) ? $used_tags[$tagname] : 0;

                        if (!$tused || ($tmode & QF_BBTAG_SUBDUP))
                        {
                            $tparam = ($part[2]) ? (($part[3]) ? $part[3] : $part[2]) : '';
                            $this->_TStack_Add($tagname, $tparam);

                            if ($tmode & QF_BBTAG_NOSUB)
                                $state_strip = true;

                            $tused++;

                            $used_tags[$tagname] = $tused;
                        }
                    }

                }
                else
                {
                    if (!$this->_TStack_Write($part[0]))
                        $buffer.= $part[0];
                }
            }
            elseif ($tagname = strtolower($part[4]))  // close tag
            {
                if ($tagname == $this->noparse_tag)
                {
                    if ($state_nobb && ($this->cur_mode == QF_BBPARSE_CHECK || $this->cur_mode == QF_BBPARSE_PREP))
                    {
                        $tdata = '[/'.$this->noparse_tag.']';
                        if (!$this->_TStack_Write($tdata))
                            $buffer.= $tdata;
                    }
                    $state_nobb = false;
                }

                elseif (isset($this->tags[$tagname]) && !$state_nobb)
                {
                    $tag = $this->tags[$tagname];
                    $tmode = $tag['mode'];

                    if ($state_strip)
                    {
                        if ($tagname == $this->_TStack_Last())
                            $state_strip = false;
                    }

                    if (!$state_strip)
                    {
                        $tused = (isset($used_tags[$tagname])) ? $used_tags[$tagname] : 0;

                        if ($tused)
                            while ($tdata = $this->_TStack_Get())
                            {
                                $subtname = $tdata['name'];
                                $subtmode = $this->tags[$subtname]['mode'];
                                if (isset($used_tags[$subtname]))
                                    $used_tags[$subtname]--;

                                if ($subtmode & QF_BBTAG_USEBRK && $state_breakers)
                                    $state_breakers--;

                                $tdata = $this->Parse_Tag($tdata['name'], $tdata['param'], $tdata['buffer']);
                                if (!$this->_TStack_Write($tdata))
                                    $buffer.= $tdata;

                                if ($subtname == $tagname)
                                    break;
                            }
                    }

                }
                else
                {
                    if (!$this->_TStack_Write($part[0]))
                        $buffer.= $part[0];
                }

            }
            else              // string data
            {
                if (!$this->_TStack_Write($part[0]))
                    $buffer.= $part[0];
            }

        }

        if ($state_nobb && ($this->cur_mode == QF_BBPARSE_CHECK || $this->cur_mode == QF_BBPARSE_PREP))
        {
            $tdata = '[/'.$this->noparse_tag.']';
            if (!$this->_TStack_Write($tdata))
                $buffer.= $tdata;
            $state_nobb = false;
        }

        while ($tdata = $this->_TStack_Get())
        {
            $subtname = $tdata['name'];
            if (isset($used_tags[$subtname]))
                $used_tags[$subtname]--;

            $tdata = $this->Parse_Tag($tdata['name'], $tdata['param'], $tdata['buffer']);
            if (!$this->_TStack_Write($tdata))
                $buffer.= $tdata;
        }

        $stime = explode(' ',microtime());
        $stop_time = $stime[1]+$stime[0];
        $this->last_time = $stop_time - $start_time;

        return $buffer;
    }

    function Parse_Tag($name, $param, $buffer='')
    {
        if (!$buffer)
            return '';

        $param = preg_replace('#\[(\/?\w+)#', '[ $1', $param);
        if ($this->cur_mode == QF_BBPARSE_CHECK)
            return ('['.$name.($param ? '="'.$param.'"' : '').']'.$buffer.'[/'.$name.']');

        elseif ($tag = $this->tags[$name])
        {
            $tmode = $tag['mode'];

            if ($tag['func'])
            {
                if (($tmode & QF_BBTAG_NOCH) && $this->cur_mode == QF_BBPARSE_PREP)
                    return ('['.$name.($param ? '="'.$param.'"' : '').']'.$buffer.'[/'.$name.']');
                else
                    return qf_func_call($tag['func'], $name, $buffer, $param);
            }

            if ($p_mask = $tag['param_mask'])
            {
                if (preg_match('#('.$p_mask.')#', $param, $parr))
                    $param = $parr[0];
                else
                    return $buffer;
            }
            if ($d_mask = $tag['data_mask'])
            {
                if (preg_match('#('.$d_mask.')#', $buffer, $darr))
                    $buffer = $darr[0];
                else
                    return $buffer;
            }

            if (($tmode & QF_BBTAG_NOCH) && $this->cur_mode == QF_BBPARSE_PREP)
                return ('['.$name.($param ? '="'.$param.'"' : '').']'.$buffer.'[/'.$name.']');
            elseif ($tmode & QF_BBTAG_FHTML)
            {
                $out = strtr($tag['html'], Array('{param}' => $param, '{data}' => $buffer));
                return $out;
            }
            else
            {
                $out = '<'.$tag['html'].(($param && $tag['param']) ? ' '.$tag['param'].'="'.$param.'"' : '').'>'.$buffer.'</'.$tag['html'].'>';
                return $out;
            }
        }
    }

    Function XML_Check($input, $use_html_specs = false)
    {        $stime = explode(' ',microtime());
        $start_time=$stime[1]+$stime[0];

        $state_strip = false;
        $used_tags   = Array();
        $struct      = Array();
        $t_flags     = Array('?xml' => QF_XMLTAG_XHEADER); // flags to control behaviour
        $t_pars      = Array(); // some tags may be only clids of some parents
        $t_clilds    = Array(); // some tags may include only some clilds (not implemented yet)

        if ($use_html_specs)
        {
            $t_pars = Array(
                'tr' => 'table|tbody',
                'td' => 'tr',
                'th' => 'tr',
                );

            $t_flags = Array(
                '?xml'  => QF_XMLTAG_XHEADER,
                '!DOCTYPE' => QF_XMLTAG_XHEADER,
                'hr'    => QF_XMLTAG_ACLOSE,
                'br'    => QF_XMLTAG_ACLOSE,
                'img'   => QF_XMLTAG_ACLOSE,
                'input' => QF_XMLTAG_ACLOSE,
                );
        }

        preg_match_all('#\<\!\[(CDATA)\[.*?\]\]\>|\<(\!\-\-).*?\-\-\>|\<((?:\!|\?)?[\w\-\:_]+)((?:\s+|\"[^\"]*\"|\'[^\']*\'|[^\s\<\>]+)*)\>|\<\/\s*([\w\-\:_]+)\s*\>|[^\<]+|\<#s', $input, $struct, PREG_SET_ORDER);

        $this->_TStack_Clear();

        $output = '';

        foreach ($struct as $part)
        {            if ($part[1] == 'CDATA' || $part[2] == '!--') // CDATA & comments
                $output.= $part[0];
            elseif ($tag = strtolower($part[3])) // open tag or full tag
            {
                $pstr = $part[4];
                $flags = (isset($t_flags[$tag])) ? $t_flags[$tag] : 0;
                $is_full = (bool) ($flags & QF_XMLTAG_ACLOSE);

                if ($flags & QF_XMLTAG_XHEADER)
                {
                    $output.= '<'.$tag.$pstr.'>';
                    continue;
                }

                if (isset($t_pars[$tag]) && ($ptags = explode('|', $t_pars[$tag])))
                {                    $ptused = false;
                    foreach ($ptags as $ptag)
                        if (isset($used_tags[$ptag]) && $used_tags[$ptag])
                        {                            $ptused = true;
                            break;
                        }

                    if ($ptused)
                    {
                        while ($suptag = $this->_TStack_Last())
                        {
                            if (in_array($suptag, $ptags))
                                break;
                            else
                            {                                $this->_TStack_Get();
                                if (isset($used_tags[$suptag]))
                                    $used_tags[$suptag]--;
                                $output.= '</'.$suptag.'>';
                            }
                        }
                    }
                    else
                        continue;
                }

                if ($pstr = trim($pstr))
                {                    if (substr($pstr, -1) == '/')
                        $is_full = true;
                    if ($pstr = $this->_XML_Check_Params($tag, $pstr))
                    	$pstr = ' '.$pstr;
                }

                if (!$is_full)
                {                    $this->_TStack_Add($tag);
                    if (isset($used_tags[$tag]))
                        $used_tags[$tag]++;
                    else
                        $used_tags[$tag] = 1;
                }

                $output.= '<'.$tag.$pstr.(($is_full) ? ' /' : '').'>';
            }
            elseif ($tag = strtolower($part[5]))
            {                $tused = isset($used_tags[$tag]) ? $used_tags[$tag] : 0;
                if ($tused)
                    while ($tdata = $this->_TStack_Get())
                    {
                        $subtag = $tdata['name'];
                        if (isset($used_tags[$subtag]))
                            $used_tags[$subtag]--;

                        $output.= '</'.$subtag.'>';

                        if ($subtag == $tag)
                            break;
                    }
            }
            else
            {                $output.= qf_smart_htmlschars($part[0]);
            }
        }

        while ($tdata = $this->_TStack_Get())
            $output.= '</'.$tdata['name'].'>';

        $stime = explode(' ',microtime());
        $stop_time = $stime[1]+$stime[0];
        $this->last_time = $stop_time - $start_time;

        return $output;
    }

    function _XML_Check_Params($tag, $param_str)
    {        preg_match_all('#([\w\-]+)(\s*=\s*(?:\"([^\"]*)\"|\'([^\']*)\'|([^\s]+)))?#', $param_str, $struct, PREG_SET_ORDER);
        $params = Array();
        foreach ($struct as $part)
        {            $val = $par = strtolower($part[1]);
            if (isset($part[2]))
            {                $val = (isset($part[5]))
                    ? $part[5]
                    : ((isset($part[4]))
                        ? $part[4]
                        : $part[3]);
                $val = qf_smart_htmlschars($val);
            }
            $params[] = $par.'="'.$val.'"';
        }
        $param_str = implode(' ', $params);
        return $param_str;
    }

    function _TStack_Clear()
    {
        $this->tag_stack = Array();
    }

    function _TStack_Add($name, $param='')
    {
        $pos = count($this->tag_stack);
        $new = Array('name' => $name, 'param' => $param, 'buffer' => '');
        $this->tag_stack[$pos] =& $new;
    }

    function _TStack_Write($text)
    {
        $pos = count($this->tag_stack)-1;
        if ($pos>=0)
        {
            $this->tag_stack[$pos]['buffer'].= $text;
            return true;
        }
        else
            return false;
    }

    function _TStack_Get()
    {
        if ($out = array_pop($this->tag_stack))
            return $out;
        else
            return false;
    }

    function _TStack_Last()
    {
        $pos=count($this->tag_stack)-1;
        if ($pos>=0) {
            $out = $this->tag_stack[$pos]['name'];
            return $out;
        }
        else
            return false;
    }

    function _BBCode_Std_UrlImg($name, $buffer, $param = false)
    {
        if ($name == 'url')
            $html = '<a href="{url}" title="{url}" >{capt}</a>';
        elseif ($name == 'img')
            $html = '<img src="{url}" alt="{capt}" />';
        else
            return $buffer;

        if ($param)
        {
            $url = $param;
            $capt = $buffer;
        }
        else
        {
            $url = $capt = $buffer;
        }

        if (preg_match('#^'.QF_FURL_MASK.'$#D', $url, $uarr))
            $url = $uarr[0];
        else
            return $buffer;

        if ($name == 'img')
            if (!preg_match('#\.(jpg|jpeg|png|gif|swf|bmp|tif|tiff)$#i', $url))
                $html = '<a href="{url}" title="{url}" >{capt} [Image blocked]</a>';

        return strtr($html, Array('{url}' => $url, '{capt}' => $capt));
    }

    function _BBCode_Std_Table($name, $buffer, $param = false)
    {
        $useborder = false;
        $parr = explode('|', $param);
        if (count($parr)>1)
        {
            $param = $parr[0];
            $useborder = (bool) $parr[1];
        }
        $param = (int) $param;
        if ($param <= 0)
            $param = 1;

        $table = explode('['.$this->tagbreaker.']', $buffer);
        $buffer = ($useborder)
            ? '<table style="border: solid 1px;"><tr>'
            : '<table><tr>';
        $i = 0;
        foreach ($table as $part)
        {
            if ($i>0 && ($i%$param == 0))
                $buffer.= '</tr><tr>';

            if ($part==='')
                $part = '&nbsp;';

            $buffer.= '<td>'.$part.'</td>';
            $i++;
        }
        while ($i%$param != 0)
        {
            $buffer.= '<td>&nbsp;</td>';
            $i++;
        }
        $buffer.= '</table>';

        return $buffer;
    }
}

?>
