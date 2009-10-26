<?php

// -------------------------------------------------------------------------- \\
// Contents Managing System module provides basic CMS functions               \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if (defined('QF_CMS_LOADED'))
    die('Scripting error');

define('QF_CMS_LOADED', true);

// cache prefix
define('QF_CMS_CACHE_PREFIX', 'CMS_PG.');
define('QF_CMS_TREE_CACHENAME', 'CMS_TREE');

// data directories constants
define('QF_CMS_PGS_DIR', QF_DATA_ROOT.'cms_pgs/');

// some consts
define('QF_CMS_INDEXPAGE', 'index');

class Fox2_CMS
{
    var $cur_page = null;
    var $pgs_list = Array();
    var $pgs_tree = Array();
    var $pgs_pars = Array();

    function Fox2_CMS()
    {
        $this->cur_page = null;
    }

    function _Start()
    {
        global $QF;

        if (list($pgs_tree, $pgs_list, $pgs_pars) = $QF->Cache->Get(QF_CMS_TREE_CACHENAME))
        {
            $this->pgs_tree = $pgs_tree;
            $this->pgs_list = $pgs_list;
            $this->pgs_pars = $pgs_pars;
        }
        else
            $this->_Build_Tree();
    }

    function _Build_Tree()
    {
        global $QF;

        $pgs_tree = $pgs_list = $pgs_pars = Array();

        if ($pgs_list0 = $QF->DBase->Do_Select_All('cms_pgs', Array('id', 'parent', 'caption', 'is_section', 'file_id', 'r_level'), false, Array('order' => Array('order_id', 'id'))))
        {
            $tmp_pars = $pgs_tmps = $to_correct = Array();

            foreach ($pgs_list0 as $pg) // we need an indexed array. And we'll generate file links
            {
                $file_id = preg_replace('#[^A-Za-z0-9_\-]#', '', $pg['file_id']);
                $pg['file_link'] = QF_CMS_PGS_DIR.$file_id.'.htf';
                $pgs_list[$pg['id']] = $pg;
            }
            unset ($pgs_list0);

            foreach ($pgs_list as $id => $pg) // temporary data dividing
            {
                $in_tree = false;
                if ($pg['is_section'])
                    $in_tree = true;
                if ($pg['parent'])
                {
                    if (isset($pgs_list[$pg['parent']]) && $pgs_list[$pg['parent']]['is_section'])
                        $in_tree = true;
                    else
                    {
                        $to_correct[] = $pg['id'];
                        $pg['parent'] = '';
                        $pgs_list[$id] = $pg;
                    }
                }
                if ($in_tree)
                {
                    $tmp_pars[$pg['id']] = $pg['parent'];
                    $pgs_tmps[$pg['id']] = $pg;
                }
            }

            $cur_pg = '';
            $cstack = Array();
            while (count($tmp_pars)) // folder tree resorting
            {
                if ($childs = array_keys($tmp_pars, $cur_pg))
                {
                    array_push($cstack, $cur_pg);
                    $cur_pg = $childs[0];
                    $child = $pgs_tmps[$cur_pg];
                    $child['t_level'] = count($cstack) - 1; // level
                    if ($child['is_section'])
                        $child['scount'] = 0;

                    $pgs_tree[$cur_pg] = $child;
                    $pgs_pars[$cur_pg] = $child['parent'];

                    if ($child['parent'])
                        $pgs_tree[$child['parent']]['scount']++;
                    unset($tmp_pars[$cur_pg]);
                }
                elseif (count($cstack) && ($st_top = array_pop($cstack)) !== null)
                {
                    // getting off the branch
                    $cur_pg = $st_top;
                }
                else // this will open looped parentship
                {
                    reset($tmp_pars);
                    $key = key($tmp_pars);
                    $pgs_tmps[$key]['parent'] = ''; // we'll link one folder to root
                    $pgs_list[$key]['parent'] = '';
                    $tmp_pars[$key] = '';
                    $to_correct[] = $key;
                }
            }

            if (count($to_correct)) // we'll correct missparented pages
                $QF->DBase->Do_Update('cms_pgs', Array('parent' => ''), Array('id' => $to_correct));

            unset ($tmp_pars, $pgs_tmps);


        }

        $QF->Cache->Set(QF_CMS_TREE_CACHENAME, Array($pgs_tree, $pgs_list, $pgs_pars));
        $this->pgs_tree = $pgs_tree;
        $this->pgs_list = $pgs_list;
        $this->pgs_pars = $pgs_pars;
    }

    function Get_List($parent = false)
    {
        global $QF;

        if (!is_string($parent))
            return $this->pgs_list;
        elseif ($childs = array_keys($this->pgs_pars, $parent))
        {
            $returns = Array();
            while ($id = array_shift($childs))
                $returns[$id] = $this->pgs_list[$id];
            return $returns;
        }
        else
            return array();
    }

    function Get_List_Item($id)
    {
        global $QF;

        if (isset($this->pgs_list[$id]))
            return $this->pgs_list[$id];

        return null;
    }

    function Get_Tree()
    {
        global $QF;

        return $this->pgs_tree;
    }

    function Load_Page($pg, $raw_data = false)
    {
        global $QF;
        $pg = preg_replace('#[^A-Za-z0-9_\-]#', '', $pg);
        if (!$pg)
            return false;

        $cachename = QF_CMS_CACHE_PREFIX.$pg;

        if (!$raw_data && $PData = $QF->Cache->Get($cachename))
        {
            $this->cur_page =& $PData;
            $QF->Timer->Time_Log($pg.' CMS data loaded (from global cache)');
            return true;
        }
        elseif ($PData = $QF->DBase->Do_Select('cms_pgs', '*', Array('id' => $pg) ))
        {
            $file_id = preg_replace('#[^A-Za-z0-9_\-]#', '', $PData['file_id']);
            $filename = QF_CMS_PGS_DIR.$file_id.'.htf';
            if ($file_data = qf_file_get_contents($filename))
            {
                $links = Array();

                if ($PData['links_to'])
                    $links = explode('|', $PData['links_to']);

                if (count($links))
                {
                    $links = array_unique($links);
                    foreach ($links as $num => $name)
                        $links[$num] = '"'.preg_replace('#[^A-Za-z0-9_\-]#', '', $name).'"';

                    $links = implode(', ', $links);
                    $Ndata = $QF->DBase->Do_Select_All('cms_pgs', Array('id', 'caption'), 'WHERE id IN ('.$links.')');
                    $links = Array();
                    foreach ($Ndata as $rec)
                        $links[$rec['id']] = $rec['caption'];
                }


                $PData = Array(
                    'id'        => $PData['id'],
                    'parent'    => $PData['parent'],
                    'is_sect'   => $PData['is_section'],
                    'caption'   => $PData['caption'],
                    'author_id' => $PData['author_id'],
                    'file_name' => $filename,
                    'file_type' => strtolower($PData['file_type']),
                    'mod_date'  => $PData['mod_date'],
                    'r_level'   => $PData['r_level'],
                    'text'      => $file_data,
                    'links'     => $links,
                    'parsed'    => false,
                    );

                $this->cur_page =&$PData;
                if (!$raw_data)
                    if ($this->Parse_Page())
                        $QF->Cache->Set($cachename, $this->cur_page);

                $QF->Timer->Time_Log($pg.' CMS data loaded (from file & DB)');
                return true;

            }
            else
            {
                trigger_error('CMS: error loading file for "'.$pg.'" page', E_USER_WARNING);
                return false;
            }
        }
        else
            trigger_error('CMS: "'.$pg.'" page data not found', E_USER_WARNING);

        return false;
    }

    function Parse_Page()
    {
        global $QF, $FOX;
        if (!$this->cur_page)
            if (!$this->Load_Page('index'))
                return false;

        if ($this->cur_page['parsed'])
            return true;

        $file_type = strtolower($this->cur_page['file_type']);
        $data =&$this->cur_page['text'];
        if ($file_type == 'bbc') // bbcoded structure
        {
            $QF->Run_Module('Parser');
            $QF->Parser->Init_Std_Tags();
            $QF->Parser->Add_Tag('cmspage', '<a href="'.$FOX->Gen_URL('fox2_cms_page', Array('{param}'), true, true).'" >{data}</a>', QF_BBTAG_FHTML, Array('param_mask' => '[0-9A-z_\-]+') );
            $data = $QF->Parser->Parse($data, QF_BBPARSE_ALL);
        }
        elseif ($file_type == 'text') // plain text
        {
            $data = '<h2>'.$this->cur_page['caption'].'</h2>'.nl2br(preg_replace('#^([^\n\S]+)#me', 'str_repeat("&nbsp; ", strlen("\\1"));', htmlspecialchars($data)));
            $data = preg_replace('#\{CMSL_([0-9A-z_\-]+)\}#i', $FOX->Gen_URL('fox2_cms_page', Array('$1'), true, true), $data);
        }
        else // raw html
        {
            // XML check is commented 'cause it's supposed we did it while uploading or creating file
            // $QF->Run_Module('Parser');
            // $data = $QF->Parser->XML_Check($data, true);
            $data = preg_replace('#\{CMSL_([0-9A-z_\-]+)\}#i', $FOX->Gen_URL('fox2_cms_page', Array('$1'), true ,true), $data);
        }

        $this->cur_page['parsed'] = true;
        return true;
    }

    function Get_Data()
    {
        global $QF, $FOX;
        if (!$this->cur_page)
            if (!$this->Load_Page('index'))
                return false;

        return $this->cur_page;
    }

    function Get_Info()
    {
        global $QF, $FOX;
        if (!$this->cur_page)
            if (!$this->Load_Page('index', true))
                return false;

        $PData = $this->cur_page;
        unset($PData['text'], $PData['parsed']);

        if ($SData = $QF->DBase->Do_Select('cms_stats', '*', Array('id' => $this->cur_page['id'])))
            $PData += $SData;
        else
            $PData += Array('views' => 0, 'v_by_refer' => 0, 'last_view' => null);

        return $PData;
    }

    function Incrase_Stats()
    {
        global $QF, $FOX;
        if (!$this->cur_page)
            if (!$this->Load_Page('index'))
                return false;

        $upd_stat = Array(
            'views'     => '++ 1',
            'last_view' => $QF->Timer->time,
            );
        if ($QF->HTTP->ExtRef)
            $upd_stat['v_by_refer'] = '++ 1';

        if (!$QF->DBase->DO_Update('cms_stats', $upd_stat, Array('id' => $this->cur_page['id']), QF_SQL_USEFUNCS))
        {
            $upd_stat = Array(
                'id'         => $this->cur_page['id'],
                'views'      => 1,
                'v_by_refer' => $QF->HTTP->ExtRef ? 1 : 0,
                'last_view'  => $QF->Timer->time,
                );
            return $QF->DBase->DO_Insert('cms_stats', $upd_stat, true);
        }

        return true;
    }

    function Page_CMS (&$p_title, &$p_subtitle, &$d_result)
    {
        global $QF, $FOX;
        $pg = $QF->GPC->Get_String('page', QF_GPC_GET, QF_STR_WORD);

        if (!$this->Load_Page(($pg) ? $pg : QF_CMS_INDEXPAGE))
        {
            if (!$pg || $pg == QF_CMS_INDEXPAGE)
            {
                $QF->VIS->Load_Templates('cms_spec');
                $p_title = $QF->LNG->Lang('CMS_NOINDEX_CAPT');
                return $QF->VIS->Create_Node('CMS_NOINDEXPAGE', Array('IS_ADM' => ($QF->User->adm_level) ? true : null));
            }
            else
            {
                $d_result = Array(Lang('ERR_CMS_PAGE_LOAD'), ($pg) ? QF_INDEX : false, true);
                return false;
            }
        }
        elseif (!($my_acc = $QF->User->CheckAccess($this->cur_page['r_level'], 0, 0, $this->cur_page['author_id'])) && $pg != QF_CMS_INDEXPAGE)
        {
            $d_result = Array(Lang('ERR_CMS_PAGE_NOACC'), QF_INDEX, true);
            return false;
        }

        if (!$this->cur_page['parsed'])
            if (!$this->Parse_Page())
            {
                $d_result = Array(Lang('ERR_CMS_PAGE_DRAW'), QF_INDEX, true);
                return false;
            }


        $cms_page = $QF->VIS->Create_Node('CMS_PAGE_MAIN', Array('CONTENT' => $this->cur_page['text'],
                                                  'CAPTION' => $this->cur_page['caption'] ));
        $p_title = $this->cur_page['caption'];

        if ($links = $this->cur_page['links'])
        {
            $cms_page_links = $QF->VIS->Add_Node('CMS_PAGE_LINKS', 'PAGE_LINKS', $cms_page);
            foreach ($links as $link => $capt)
            {
                $QF->VIS->Add_Node('CMS_PLINK', 'LINKS', $cms_page_links, Array('id' => $link, 'caption' => $capt, 'scaption' => $QF->USTR->Str_SmartTrim($capt, 25)) );
            }
        }
        if ($QF->User->UID && ($QF->User->adm_level))
            $cms_page_modblock = $QF->VIS->Add_Node('CMS_PAGE_MODBLOCK', 'PAGE_MODBLOCK', $cms_page, Array('page_id' => $this->cur_page['id']));

        $this->Incrase_Stats();
        if ($this->cur_page['parent'] || $this->cur_page['is_sect'] || ($QF->Config->Get('show_root_sects', 'qf2_cms') && count ($this->pgs_tree)))
            $FOX->Draw_Panel('cms_navi');
        return $cms_page;
    }

    Function Panel_CMS_Navi ($pan_node = false)
    {
        global $QF;

        if (!$this->cur_page)
            return false;

        if (!$pan_node)
            $pan_node = $QF->VIS->Create_Node('PANEL_BODY', false, 'cms_navi_panel');

        $QF->VIS->Add_Data_Array($pan_node, Array(
            'title' => Lang('CMS_PAN_NAVI'),
            'empty' => '1',
            ) );

        $cont = $QF->VIS->Add_Node('CMS_PANEL_NAVI', 'contents', $pan_node);
        $parents[0] = $cont;
        $par = $this->cur_page['id'];
        $show_links = Array();
        if ($QF->Config->Get('show_root_sects', 'qf2_cms'))
            $show_links[] = '';
        while ($par)
        {
            $show_links[] = $par;
            $par = $this->pgs_tree[$par]['parent'];
        }

        foreach ($this->pgs_tree as $pg)
        {
            if (!in_array($pg['id'], $show_links) && !in_array($pg['parent'], $show_links))
                continue;
            if (!$QF->User->CheckAccess($pg['r_level']) )
                continue;
            $pg['scaption'] = $QF->USTR->Str_SmartTrim($pg['caption'], 23 - $pg['t_level']);
            $node = $QF->VIS->Add_Node('CMS_PANEL_NAVI_LINK', 'subs', $parents[$pg['t_level']], $pg);
            if ($pg['id'] == $this->cur_page['id'])
                $QF->VIS->Add_Data($node, 'HLIGHT', 1);
            $parents[$pg['t_level']+1] = $node;
        }

        return $pan_node;
    }

}
?>
