<?php

// -------------------------------------------------------------------------- \\
// Post tree system manager - provides interfaces for comments and forums     \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if (defined('QF_POSTTREE_LOADED'))
    die('Scripting error');

define('QF_POSTTREE_LOADED', true);


define('QF_POSTTREE_CACHE_PREFIX', 'POSTTREE.');
define('QF_POSTTREE_CACHE_STATS', QF_POSTTREE_CACHE_PREFIX.'TSTATS');

class Fox2_PostTree
{
    var $ptrees = Array();
    var $stats = Array();

    function Fox2_PostTree()
    {
    }

    function Render_Tree($tid)
    {        global $QF, $FOX;

        if (!is_numeric($tid))
            return false;

        $tid = (int) $tid;
        if (!isset($this->ptrees[$tid]) && !$this->_Load_Tree($tid))
            return false;

        $data = $this->ptrees[$tid];
        $t_acc = $QF->User->CheckAccess($data['r_level'], $data['w_level'], 0, $data['author_id']);

        $QF->Run_Module('VIS');
        $QF->Run_Module('UList');

        $my_href_enc = qf_url_str_pack($QF->HTTP->Request);
        $QF->VIS->Load_Templates('posttree');
        $FOX->Link_JScript('posttree');

        $root_node = $QF->VIS->Create_Node('FOX_POSTTREE_OUTER');
        $root_params = Array(
            'MYHREF' => $QF->HTTP->Request,
            'MYHREF_ENC' => $my_href_enc,
            'TREE_ID' => $tid,
            'CAN_ADM' => ($t_acc > 2) ? 1 : null,
            'SHOW_ONLY' => ($t_acc < 2) ? 1 : null,
            );
        if ($QF->User->UID)
        {
            $uinfo = $QF->UList->Get_UserInfo($QF->User->UID);
            $QF->VIS->Add_Node('USER_INFO_MIN_DIV', 'USER_INFO', $root_node, $uinfo /*+ Array('HIDE_ACCESS' => 1)*/);
            $root_params['USER_NAME'] = $uinfo['nick'];
        }
        else
            $root_params['USER_NAME'] = Lang('US_GUEST');

        $par_nodes = Array(1 => $root_node);
        $pids = qf_2darray_cols($data['ptree'], 'post_id');
        $p_datas = $this->_Load_Posts($pids);
        list($uids, $chuids) = qf_2darray_cols($p_datas, Array('author_id', 'ch_user_id'));
        $QF->UList->Query_IDs($uids + $chuids);

        $pids = Array();
        foreach($data['ptree'] as $pdata)
        {            if ($pdata['deleted'])
            {                $cur_node = $QF->VIS->Add_Node('FOX_POSTTREE_DELPOST', 'SUB_POSTS', $par_nodes[$pdata['t_level']], $pdata);
                $par_nodes[$pdata['t_level']+1] = $cur_node;
                continue;
            }

            $cur_node = $QF->VIS->Add_Node('FOX_POSTTREE_POST', 'SUB_POSTS', $par_nodes[$pdata['t_level']]);
            $par_nodes[$pdata['t_level']+1] = $cur_node;

            if (!isset($p_datas[$pdata['post_id']]))
                continue;
            $pdata = $p_datas[$pdata['post_id']] + $pdata;
            $p_acc = $QF->User->CheckAccess($data['r_level'], $data['w_level'], 0, $pdata['author_id']);
            $node_params = $pdata + Array(
                'PTEXT' => $pdata['p_text'],
                'CAN_ADM' => ($p_acc > 2) ? 1 : null,
                'SHOW_ONLY' => ($p_acc < 2) ? 1 : null,
                );
            if ($uinfo = $QF->UList->Get_UserInfo($pdata['author_id']))
            {
                $QF->VIS->Add_Node('USER_INFO_MIN_DIV', 'AUTHOR_INFO', $cur_node, $uinfo + Array('HIDE_ACCESS' => 1));
                $node_params['author'] = $uinfo['nick'];
            }
            elseif (!$pdata['author'])
                $node_params['AUTHOR_INFO'] = Lang('NO_DATA');
            if ($pdata['ch_user_id'] && ($uinfo = $QF->UList->Get_UserInfo($pdata['ch_user_id'])))
                $node_params['ch_user'] = $uinfo['nick'];
            else
                $node_params['ch_user_id'] = null;

            $QF->VIS->Add_Data_Array($cur_node, $node_params + $root_params);
            $pids[] = $pdata['post_id'];
        }
        $root_params['JS_POSTSARR'] = implode(', ', $pids);
        $QF->VIS->Add_Data_Array($root_node, $root_params);
        return $root_node;
    }

    function Create_Tree($class, $data, $params = null)
    {
        global $QF, $FOX;

        $class = strtolower($class);
        $new_data = Array(
            'class' => $class, 'a_key' => '', 'b_key1' => '', 'b_key2' => '', 'b_key3' => '',
            'author' => $QF->User->uname, 'author_id' => $QF->User->UID, 'caption' => '',
            'time' => $QF->Timer->time, 'r_level' => 0, 'w_level' => 1, 'locked' => false, 'marked' => false,
            );

        qf_array_modify($new_data, $params);

        $new_data['data'] = serialize($data);
        $new_data['hash'] = md5($new_data['data']);

        $new_data['r_level'] = min($new_data['r_level'], QF_FOX2_MAXULEVEL);
        $new_data['w_level'] = min($new_data['w_level'], QF_FOX2_MAXULEVEL);
        $new_data['w_level'] = max($new_data['w_level'], $new_data['r_level']);

        if ($tid = $QF->DBase->Do_Select('pt_roots', 'root_id', Array('hash' => $new_data['hash'])))
        {
        }
        elseif ($tid = $QF->DBase->Do_Insert('pt_roots', $new_data))
        {            $QF->Cache->Drop(QF_POSTTREE_CACHE_STATS);
            return $tid;
        }
        return false;
    }

    function Modify_Post($pid, $text, $params = null)
    {        global $QF, $FOX;

        $posts = $this->_Load_Posts($pid);
        if (!isset($posts[$pid]))
            return false;
        $pinfo = $posts[$pid];

        $tid = $pinfo['root_id'];
        if (!($tinfo = $this->Get_Tree($tid)))
            return false;

        $data = Array(
            'author' => $pinfo['author'], 'author_id' => $pinfo['author_id'], 'time' => $pinfo['time'],
            'locked' => $pinfo['locked'], 'marked' => $pinfo['marked'], 'deleted' => $pinfo['deleted'],
            'ch_user' => $QF->User->uname, 'ch_user_id' => $QF->User->UID, 'ch_time' => $QF->Timer->time,
            );

        qf_array_modify($data, $params);

        $data['ch_user_ip'] = $QF->HTTP->IP_int;

        $QF->Run_Module('Parser');
        $QF->Parser->Init_Std_Tags();
        $text = $QF->Parser->Parse($text, QF_BBPARSE_CHECK);
        $hash = md5($text);

        $t_data = Array(
            'o_text' => $text,
            'p_text' => $QF->Parser->Parse($text, QF_BBPARSE_PREP),
            'preparsed' => 1,
            'hash' => $hash,
            );

        // TODO: checking for hash

        $cachename = QF_POSTTREE_CACHE_PREFIX.$tid;
        if ($QF->DBase->Do_Update('pt_posts', $data, Array('post_id' => $pid)) !== false)
        {
            $t_data['post_id'] = $pid;
            $QF->DBase->Do_Update('pt_ptext', $t_data, Array('post_id' => $pid));
            $QF->Cache->Drop($cachename);
            $QF->Cache->Drop(QF_POSTTREE_CACHE_STATS);
            unset($this->ptrees[$tid]);
            return true;
        }
        return false;
    }

    function Add_Post($tid, $text, $parent = 0, $params = null)
    {
        global $QF, $FOX;

        if (!($tinfo = $this->Get_Tree($tid)))
            return false;

        if (!isset($tinfo['ptree'][$parent]))
            $parent = 0;

        $data = Array(
            'author' => $QF->User->uname, 'author_id' => $QF->User->UID, 'time' => $QF->Timer->time,
            'locked' => false, 'marked' => false,
            );

        qf_array_modify($data, $params);

        $data['root_id'] = $tid;
        $data['parent'] = $parent;
        $data['author_ip'] = $QF->HTTP->IP_int;

        $QF->Run_Module('Parser');
        $QF->Parser->Init_Std_Tags();
        $text = $QF->Parser->Parse($text, QF_BBPARSE_CHECK);
        $hash = md5($text);

        $t_data = Array(
            'o_text' => $text,
            'p_text' => $QF->Parser->Parse($text, QF_BBPARSE_PREP),
            'preparsed' => 1,
            'hash' => $hash,
            );

        // TODO: checking for hash

        $cachename = QF_POSTTREE_CACHE_PREFIX.$tid;
        if ($pid = $QF->DBase->Do_Insert('pt_posts', $data))
        {
            $t_data['post_id'] = $pid;
            $QF->DBase->Do_Insert('pt_ptext', $t_data);
            $t_data = Array(
                'posts' => $tinfo['posts']+1,
                'l_author' => $data['author'],
                'l_author_id' => $data['author_id'],
                'l_time' => $data['time'],
                'l_post_id' => $pid,
                );
            $QF->DBase->Do_Update('pt_roots', $t_data, Array('root_id' => $tid));
            $QF->Cache->Drop($cachename);
            $QF->Cache->Drop(QF_POSTTREE_CACHE_STATS);
            unset($this->ptrees[$tid]);
            return $pid;
        }
        return false;
    }

    // TODO: gets tree by any of 4 keys
    function Get_ByKey($tid)
    {
        global $QF, $FOX;
        if (!is_numeric($tid))
            return false;

        $tid = (int) $tid;
        if (isset($this->ptrees[$tid]) || $this->_Load_Tree($tid))
            return $this->ptrees[$tid];

        return null;
    }

    function Get_Tree($tid)
    {        global $QF, $FOX;
        if (!is_numeric($tid))
            return false;

        $tid = (int) $tid;
        if (isset($this->ptrees[$tid]) || $this->_Load_Tree($tid))
            return $this->ptrees[$tid];

        return null;
    }

    function Get_Stats($tids)
    {
        global $QF, $FOX;
        if (!count($this->stats) && !$this->_Load_Stats())
            return null;

        if (is_numeric($tids))
            return (isset($this->stats[$tids])) ? $this->stats[$tids] : null;
        elseif (is_array($tids))
        {            $out = Array();
            foreach ($tids as $tid)
                $out[$tid] = (isset($this->stats[$tid])) ? $this->stats[$tid] : null;
            return $out;
        }
        else
            return $this->stats;
    }

    function Get_Post($pid)
    {
        global $QF, $FOX;
        if (!is_numeric($pid))
            return false;

        $pid = (int) $pid;
        $data = $this->_Load_Posts($pid);
        if (count($data))
            return $data[$pid];
        else
            return null;
    }

    function _Load_Posts($pids)
    {        global $QF, $FOX;
        if (!is_array($pids))
            $pids = explode('|', $pids);

        $query = Array('pt_posts'  => Array('fields' => '*', 'where' => Array('post_id' => $pids)),
                       'pt_ptext'  => Array('fields' => '*', 'join' => Array('post_id' => 'post_id')),
                       );

        if ($datas = $QF->DBase->Do_Multitable_Select($query, '', QF_SQL_SELECTALL))
        {            $QF->Run_Module('Parser');
            $QF->Parser->Init_Std_Tags();
            $got_data = Array();
            foreach ($datas as $data)
            {                if (!$data['preparsed'])
                {
                    $data['p_text'] = $QF->Parser->Parse($data['o_text'], QF_BBPARSE_PREP);
                    $data['preparsed'] = 1;
                    $QF->DBase->Do_Update('pt_ptext', Array('p_text' => $data['p_text'], 'preparsed' => $data['preparsed']), Array('post_id' => $data['post_id']));
                }

                $data['p_text'] = $QF->Parser->Parse($data['p_text'], QF_BBPARSE_POSTPREP);
                $got_data[$data['post_id']] = $data;
            }
            return $got_data;
        }
        return Array();
    }

    function _Load_Tree($tid)
    {        global $QF, $FOX;        if (!is_numeric($tid))
            return false;

        $tid = (int) $tid;        $cachename = QF_POSTTREE_CACHE_PREFIX.$tid;

        if ($tdata = $QF->Cache->Get($cachename))
        {            $this->ptrees[$tid] = $tdata;
            return true;
        }
        elseif ($tinfo = $QF->DBase->Do_Select('pt_roots', '*', Array('root_id' => $tid)))
        {            $pinfos = $QF->DBase->Do_Select_All('pt_posts', Array('post_id', 'parent', 'time', 'deleted'), Array('root_id' => $tid), 'ORDER BY `time` ASC' );
            $ptree = qf_2darray_tree($pinfos, 'post_id', 'parent', 0);
            $i = 1;
            foreach (array_keys($ptree) as $id)
                $ptree[$id]['order_id'] = $i++;

            $tinfo['ptree'] = $ptree;
            if ($tinfo['posts'] != count($ptree)) // we'll need to repair this tree stats
            {                $pid = max(array_keys($ptree));
                $pdata = $QF->DBase->Do_Select('pt_posts', '*', Array('post_id' => $pid));
                $t_data = Array(
                    'posts' => count($ptree),
                    'l_author' => $pdata['author'],
                    'l_author_id' => $pdata['author_id'],
                    'l_time' => $pdata['time'],
                    'l_post_id' => $pid,
                    );
                $QF->DBase->Do_Update('pt_roots', $t_data, Array('root_id' => $tid));
            }

            $this->ptrees[$tid] = $tinfo;
            $QF->Cache->Set($cachename, $tinfo);
            return true;
        }

        return false;
    }

    function _Load_Stats()
    {        global $QF, $FOX;

        $cachename = QF_POSTTREE_CACHE_STATS;
        if ($data = $QF->Cache->Get($cachename))
        {
            $this->stats = $data;
            return true;
        }
        elseif ($tinfos = $QF->DBase->Do_Select_All('pt_roots', Array('root_id', 'class', 'posts', 'l_post_id', 'l_time', 'l_author_id')))
        {            $stats = Array();
            list($tposts, $l_posts, $l_times) = qf_2darray_cols($tinfos, Array('posts', 'l_post_id', 'l_time'));
            $stats[0] = Array(
                'posts' => array_sum($tposts),
                'l_post_id' => max($tposts),
                'l_time' => max($l_times),
                );
            foreach ($tinfos as $tinfo)
                $stats[$tinfo['root_id']] = $tinfo;

            $QF->Cache->Set($cachename, $stats);
            $this->stats = $stats;
            return true;
        }

        return false;
    }

    function _Parse_RootParams($params, $new_params)
    {        static $my_params = Array('class', 'a_key', 'b_key1', 'b_key2', 'b_key3', 'caption', 'data');
        if (!is_array($params))
            return Array();

        $out_params = Array();
        foreach($my_params as $par_name)
        {            if (isset($new_params[$par_name]))
                $out_params[$par_name] = $new_params[$par_name];
        }

        if (isset($out_params['data']))
            $out_params['hash'] = md5($out_params['data']);


        $r_level = (isset($new_params['r_level'])) ? $new_params['r_level'] : $params['r_level'];
        $w_level = (isset($new_params['w_level'])) ? $new_params['w_level'] : $params['w_level'];
        $r_level = 0;
    }
}

?>