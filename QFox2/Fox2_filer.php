<?php

// -------------------------------------------------------------------------- \\
// Uploaded files manager - provides some uploads control structures          \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if (defined('QF_FILER_LOADED'))
    die('Scripting error');

define('QF_FILER_LOADED', true);

define('QF_FILES_UPLOADS_DIR' ,    QF_DATA_ROOT.'uploads/');
define('QF_FILES_THUMBS_DIR'  ,    QF_DATA_ROOT.'thb_n_prvw/');
define('QF_FILES_PREVIEWS_DIR',    QF_DATA_ROOT.'thb_n_prvw/');

define('QF_FOLDER_TREE_CACHENAME', 'FOLDERS_TREE');
define('QF_FILES_CACHE_PREFIX', 'FOX_FILES.');
define('QF_TEMPFILE_LIFETIME', 24*3600);

define('QF_FILES_IDIMS_IMAGE', 0);
define('QF_FILES_IDIMS_THUMB', 1);
define('QF_FILES_IDIMS_PREVIEW', 2);

define('FOX_ERR_FILES_DUPLICATE', 0x20);

class Fox2_Files
{

    var $infos = Array();
    var $f_tree = Array();
    var $f_tids = Array();
    var $fconts = Array();

    function Fox2_Files()
    {

    }

    function Get_FolderInfo($fold_id)
    {
        global $QF;

        if (!is_numeric($fold_id))
        {
            $fold_id = strtolower($fold_id);
            if (!isset($this->f_tids[$fold_id]))
                return false;

            $fold_id = $this->f_tids[$fold_id];
        }

        $fold_id = (int) $fold_id;

        if (!isset($this->f_tree[$fold_id]))
            return false;

        $data =&$this->f_tree[$fold_id];

        //if (!isset($data['contents']));

        return $data;
    }

    function Get_FolderConts($fold_id, $acc_lvl = false)
    {
        global $QF;
        static $sorters = Array(
            0 => Array('time', 1),
            1 => Array('time', 0),
            2 => Array('caption', 0),
            );

        $fold_id = (int) $fold_id;

        if (!isset($this->f_tree[$fold_id]))
            return false;
        $finfo = $this->f_tree[$fold_id];

        if (!isset($this->fconts[$fold_id]));
        {
            $where = Array('folder' => $fold_id, 'is_temp' => 0);
            if (is_int($acc_lvl))
                $where['r_level'] = '<= '.$acc_lvl;

            if (isset($sorters[$finfo['sort_type']]))
                $order_by = 'ORDER BY '.$sorters[$finfo['sort_type']][0].' '.(($sorters[$finfo['sort_type']][1]) ? 'DESC' : 'ASC');
            else
                $order_by = 'ORDER BY time DESC';
            $conts = $QF->DBase->Do_Select_All('files', 'id', $where, $order_by, QF_SQL_USEFUNCS);
            $this->fconts[$fold_id] = (is_array($conts)) ? $conts : Array();
        }

        return $this->fconts[$fold_id];
    }

    function Get_FoldersTree()
    {
        return $this->f_tree;
    }

    function Get_TempFiles($uid = false, $mime_cls = false)
    {
        global $QF;

        $flags = false;
        $cond = Array('is_temp' => 1);

        if ($del_files = $QF->DBase->Do_Select_All('files', 'id', Array('is_temp' => 1, 'time' => '< '.($QF->Timer->time - QF_TEMPFILE_LIFETIME)), '', QF_SQL_USEFUNCS ))
        {
            $this->Load_FileInfos($del_files);
            foreach($del_files as $dfid)
                $this->Drop_File($dfid);
        }

        if (is_int($uid))
            $cond['author_id'] = $uid;
        if (is_string($mime_cls) && $mime_cls)
        {
            $cond['mime'] = 'LIKE '.preg_replace('#\W#', '', $mime_cls).'/%';
            $flags |= QF_SQL_USEFUNCS;
        }
        elseif (is_array($mime_cls))
            $cond['mime'] = $mime_cls;

        $conts = $QF->DBase->Do_Select_All('files', 'id', $cond, 'ORDER BY time', $flags);


        return $conts;
    }

    function Get_FileInfo($fid)
    {
        global $QF;

        if (!preg_match('#^[0-9A-z]{8}$#D', $fid)) // incorrect file ID
            return false;

        $fid = strtolower($fid);

        if (isset($this->infos[$fid]))
            return $this->infos[$fid];

        if ($this->Load_FileInfos($fid))
            return $this->infos[$fid];

        return false;
    }

    function Get_ImageDims($fid, &$out_dims, $dims_type = QF_FILES_IDIMS_IMAGE)
    {        $info = $this->Get_FileInfo($fid);
        if (!$info || !$info['has_pics'])
            return false;

        if (!$info['image_dims'] && $this->Regen_Pics($fid))
            $info = $this->Get_FileInfo($fid);

        $wh = explode('|', $info['image_dims']);
        if (count($wh) >= 2)
        {            $w = (int) $wh[0]; $h = (int) $wh[1];
        }
        else
            $w = $h = 200;

        switch ($dims_type)
        {            case QF_FILES_IDIMS_THUMB: $w = min($w, $this->thb_w); $h = min($h, $this->thb_h); break;
            case QF_FILES_IDIMS_PREVIEW: $w = min($w, $this->prv_w); $h = min($h, $this->prv_h); break;
        }

        if (($w/$h) < $info['aspect_ratio'])
            $h = round($w/$info['aspect_ratio']);
        else
            $w = round($h*$info['aspect_ratio']);

        $out_dims = Array($w, $h);
        return true;
    }

    function Get_LoadedFIDs()
    {
        return array_keys($this->infos);
    }

    function Create_Folder($name, $parent = 0, $t_id = '', $params = false, $is_sys = false)
    {
        global $QF;

        if (!is_numeric($parent))
        {
            $parent = strtolower($parent);
            if (!isset($this->f_tids[$parent]))
                return false;

            $parent = $this->f_tids[$parent];
        }

        $fold_id = (int) $fold_id;

        if (!isset($this->f_tree[$parent]))
            return false;

        $data = Array(
            'parent' => $parent,
            'name' => $name,
            'r_level' => 0, 'w_level' => 1,
            );

        qf_array_modify($data, $params);

        if (preg_match('#\w+#', $t_id) && !isset($this->f_tids[$t_id]))
            $data['t_id'] = $t_id;

        if ($is_sys)
            $data['is_sys'] = 1;

        $data['r_level'] = min($data['r_level'], QF_FOX2_MAXULEVEL);
        $data['w_level'] = min($data['w_level'], QF_FOX2_MAXULEVEL);
        $data['w_level'] = max($data['w_level'], $data['r_level']);

        if ($id = $QF->DBase->Do_Insert('file_folders', $data))
        {
            unset($this->fconts[$parent]);
            $QF->Cache->Drop(QF_FOLDER_TREE_CACHENAME);
            $this->_Analyze_Folders();
            return ($data['t_id']) ? $data['t_id'] : $id;
        }
        else
        {
            trigger_error('FOX_FILES: Can\'t insert new folder info into DB', E_USER_WARNING);
            return false;
        }

        return false;

    }

    function Load_FileInfos($fids)
    {
        global $QF;

        if (!$fids)
            return false;

        $where = Array();

        if ($fids != '!ALL')
        {
            if (!is_array($fids))
                $fids = explode('|', $fids);

            $fids = array_unique($fids);
            sort($fids);
            $where = Array('id' => $fids);
        }

        $cachename = (is_array($fids) && count($fids) > 2)
            ? QF_FILES_CACHE_PREFIX.'q-'.md5(implode('|', $fids))
            : false;

        if ($cachename && (list($new_infos, $ch_time) = $QF->Cache->Get($cachename)) && is_array($new_infos))
        {            // need to load statistics
            $where['last_dload'] = '>= '.$ch_time;
            if ($datas = $QF->DBase->Do_Select_All('files', Array('id', 'dloads', 'last_dload'), $where, '', QF_SQL_USEFUNCS))
                foreach ($datas as $data)
                if (isset($new_infos[$data['id']]))
                    {
                        $new_infos[$data['id']]['dloads'] = $data['dloads'];
                        $new_infos[$data['id']]['last_dload'] = $data['last_dload'];
                    }
            $this->infos += $new_infos;
            return true;
        }
        elseif ($datas = $QF->DBase->Do_Select_All('files', '*', $where))
        {
            $needs_regen = Array();
            $new_infos = Array();
            foreach ($datas as $data)
            {
                $fid = $data['id'];
                $file_link = QF_FILES_UPLOADS_DIR.$data['file_id'].'.qff';
                if (file_exists($file_link))
                {
                    $data['file_link'] = $file_link;
                    $fsize = filesize($file_link);
                    if ($fsize != $data['file_size'])
                    {
                        $QF->DBase->Do_Update('files', Array('file_size' => $fsize), Array('id' => $fid));
                        $data['file_size'] = $fsize;
                    }
                }
                else
                {
                    $QF->DBase->Do_Delete('files', Array('id' => $fid));
                    $data['file_link'] = false;
                    continue;
                }

                if ($data['has_pics'])
                {
                    $prv_link = QF_FILES_PREVIEWS_DIR.$data['file_id'].'.qfp';
                    $thb_link = QF_FILES_THUMBS_DIR.$data['file_id'].'.qft';
                    $data['prv_link'] =& $prv_link;
                    $data['thb_link'] =& $thb_link;


                    if (!file_exists($thb_link) || !file_exists($prv_link))
                    {
                        $thb_link = false;
                        $prv_link = false;
                        $needs_regen[] = $fid;
                    }
                }

                if (!$data['mime'])
                {
                    $fext = pathinfo($data['filename'], PATHINFO_EXTENSION);
                    if ($ftype = qf_file_mime($data['file_link'], $fext))
                        $data['mime'] = $ftype;
                    else
                        $data['mime'] = 'application/octet-stream';
                }

                $new_infos[$fid] = $data;
            }

            if ($cachename && !count($needs_regen))
                $QF->Cache->Set($cachename, Array($new_infos, $QF->Timer->time));

            $this->infos += $new_infos;
            foreach ($needs_regen as $fid)
                $this->Regen_Pics($fid);
            return true;
        }
        else
            return false;
    }

    function Modif_File($fid, $params = Array(), $new_file = false)
    {
        global $QF, $FOX;

        if (!preg_match('#^[0-9A-z]{8}$#D', $fid)) // incorrect file ID
            return false;

        $fid = strtolower($fid);

        if (!isset($this->infos[$fid]))
            if (!$this->Load_FileInfos($fid))
                return false;

        if (!is_array($params))
            $params = Array();

        $finfo =& $this->infos[$fid];

        $can_modif = Array(
            'is_temp', 'author', 'author_id', 'author_ip',
            'time', 'r_level', 'caption', 'filename',
            );

        $upd_info = Array();
        foreach ($can_modif as $par)
        {
            if (isset($params[$par]) && !(empty($params[$par]) && !is_int($params[$par])))
            {
                settype($params[$par], gettype($finfo[$par]));
                $upd_info[$par] = $params[$par];
            }
        }
        if ($upd_info['filename'])
        {
            $upd_info['filename'] = preg_replace('#[\*\?\/]#', '_', $upd_info['filename']);
            if ($finfo['pics_name'])
                $upd_info['pics_name'] = $QF->USTR->Str_Substr(preg_replace('#\.\w{1,5}$#', '.'.qf_basename_ext($finfo['pics_name']), $upd_info['filename']), -128);
        }

        if ($upd_info['r_level'])
            $upd_info['r_level'] = min($upd_info['r_level'], QF_FOX2_MAXULEVEL);

        $file_link = false;
        if ($new_file && file_exists($new_file))
        {
            $mime = qf_file_mime($new_file, pathinfo($upd_info['filename'], PATHINFO_EXTENSION));
            $md5  = (string) qf_md5_file($new_file);

            if (($old_id = $QF->DBase->Do_Select('files', 'id', Array('file_md5' => $md5))) && $old_id != $fid)
            {
                $FOX->Trace_Error(FOX_ERR_FILES_DUPLICATE);
                return false;
            }

            $new_fid = md5(uniqid($new_file));
            $file_link = QF_FILES_UPLOADS_DIR.$new_fid.'.qff';
            if (copy($new_file, $file_link))
            {
                chmod($file_link, 0600);

                $upd_info += Array(
                    'file_id' => $new_fid, 'file_md5' => $md5, 'file_gzip' => 0,
                    'file_size' => filesize($new_file), 'mime' => $mime, 'is_arch' => ($mime == 'application/x-tar') ? 1 : 0,
                    );
            }

        }

        if ($QF->DBase->Do_Update('files', $upd_info, Array('id' => $fid)) !== false)
        {
            $o_folder = $finfo['folder'];
            $finfo = $upd_info + $finfo;
            $fold_id = $finfo['folder'];
            unset($this->fconts[$fold_id], $this->fconts[$o_folder]);
            if ($file_link)
            {
                if (($o_link = $this->infos[$fid]['file_link']) && file_exists($o_link))
                    unlink ($o_link);
                if (($o_link = $this->infos[$fid]['prv_link']) && file_exists($o_link))
                    unlink ($o_link);
                if (($o_link = $this->infos[$fid]['thb_link']) && file_exists($o_link))
                    unlink ($o_link);

                $finfo['file_link'] = $file_link;
                $this->Regen_Pics($fid);
            }
            $QF->Cache->Drop(QF_FOLDER_TREE_CACHENAME);
            $QF->Cache->Drop(QF_FILES_CACHE_PREFIX);
            return true;
        }

        return false;
    }

    function Create_From_Upload($var_name = 'upl_file', $params = false)
    {
        global $QF;

        if (!is_array($params))
            $params = Array();

        if ($file = $QF->GPC->Get_File($var_name))
        {
            $real_file = $file['tmp_name'];
            $filename = $file['name'];
            $capt = trim(preg_replace('#(?<=\S)\.\w+$#', '', strtr($filename, '_', ' ')));

            $params+= Array(
                'author' => $QF->User->uname, 'author_id' => $QF->User->UID,
                'filename' => $filename, 'caption' => $capt,
                );

            if (!$file['error'] && ($fid = $this->Create_File($real_file, $params)))
                return $fid;
        }

        return false;
    }

    function Create_File($filename, $params = false, $do_move = false)
    {
        global $QF, $FOX;

        if (!file_exists($filename))
            return false;

        $fid = md5(uniqid($filename));
        $data = Array(
            'folder' => 0, 'is_temp' => 1,
            'author' => '', 'author_id' => 0, 'author_ip' => $QF->HTTP->IP_int,
            'time' => $QF->Timer->time, 'r_level' => 0, 'caption' => $filename, 'filename' => $filename,
            );

        if (is_array($params))
          foreach ($params as $par=>$val)
            if (isset($data[$par]) && !empty($val))
            {
                settype($val, gettype($data[$par]));
                $data[$par] = $val;
            }

        $data['r_level'] = min($data['r_level'], QF_FOX2_MAXULEVEL);

        $mime = qf_file_mime($filename, pathinfo($data['filename'], PATHINFO_EXTENSION));
        $md5  = (string) qf_md5_file($filename);

        if ($QF->DBase->Do_Select('files', 'id', Array('file_md5' => $md5)))
        {
            $FOX->Trace_Error(FOX_ERR_FILES_DUPLICATE);
            return false;
        }

        $data += Array(
            'id' => qf_short_uid($filename),
            'file_id' => $fid, 'file_md5' => $md5, 'file_gzip' => 0,
            'file_size' => filesize($filename), 'mime' => $mime, 'is_arch' => ($mime == 'application/x-tar') ? 1 : 0,
            );

        $file_link = QF_FILES_UPLOADS_DIR.$data['file_id'].'.qff';
        if (($do_move) ? rename($filename, $file_link) : copy($filename, $file_link))
        {
            chmod($file_link, 0600);

            $i = 10;
            $done = false;
            while (!$done && $i>0)
            {
                $data['id'] = qf_short_uid($filename);
                $done = $QF->DBase->Do_Insert('files', $data);
                $i--;
            }

            if (!$done)
            {
                unlink($file_link);
                trigger_error('FOX_FILES: Can\'t insert new file info into DB', E_USER_WARNING);
                return false;
            }

            $this->Regen_Pics($data['id']);
            $QF->Cache->Drop(QF_FILES_CACHE_PREFIX);
            return $data['id'];
        }
        else
            trigger_error('FOX_FILES: Can\'t copy file into a storage folder', E_USER_WARNING);

        return false;
    }

    function Regen_Pics($fid)
    {
        global $QF;

        if (!preg_match('#^[0-9A-z]{8}$#D', $fid)) // incorrect file ID
            return false;

        $fid = strtolower($fid);

        if (!isset($this->infos[$fid]))
            if (!$this->Load_FileInfos($fid))
                return false;


        $finfo =& $this->infos[$fid];

        if (strstr($finfo['mime'], 'image/') && $QF->Run_Module('Imager') && ($iinfo = $QF->Imager->Check_IType($finfo['file_link'])))
        {
            //$QF->Imager->Configure(false, true);

            $conf = Array(
                'width' => $this->prv_w,
                'height' => $this->prv_h,
                'rsize_mode' => QF_IMAGER_RSIZE_FIT,
            );

            $conf_th = Array(
                'width' => $this->thb_w,
                'height' => $this->thb_h,
                'rsize_mode' => QF_IMAGER_RSIZE_FIT,
            );

            $out_type = $iinfo[2];

            $QF->Cache->Drop(QF_FILES_CACHE_PREFIX);

            $prv_file = QF_FILES_PREVIEWS_DIR.$finfo['file_id'].'.qfp';
            $thb_file = QF_FILES_THUMBS_DIR.$finfo['file_id'].'.qft';
            $finfo['prv_link'] =& $prv_file;
            $finfo['thb_link'] =& $thb_file;
            if ($QF->Imager->Parse_Image($finfo['file_link'], $prv_file, $out_type, QF_IMAGER_DO_RESIZE, $conf)
                && $QF->Imager->Parse_Image($finfo['file_link'], $thb_file, $out_type, QF_IMAGER_DO_RESIZE | QF_IMAGER_DO_DROPANIM, $conf_th))
            {
                $pinfo = $QF->Imager->Check_IType($prv_file);

                $upd_data = Array(
                    'has_pics' => 1,
                    'pics_mime' => $pinfo['mime'],
                    'pics_name' => $QF->USTR->Str_Substr(preg_replace('#\.\w{1,5}$#', '.'.$pinfo['def_ext'], $finfo['filename']), -128),
                    'aspect_ratio' => $iinfo[0]/$iinfo[1],
                    'image_dims' => $iinfo[0].'|'.$iinfo[1],
                    );

                if ($QF->DBase->Do_Update('files', $upd_data, Array('id' => $fid)) !== false)
                {
                    foreach ($upd_data as $key=>$val)
                        $finfo[$key] = $val;

                    return true;
                }
            }

        }

        $upd_data = Array('has_pics' => 0, 'pics_mime' => '', 'pics_name' => '', 'aspect_ratio' => 0, 'image_dims' => '');
        $QF->DBase->Do_Update('files', $upd_data, Array('id' => $fid));
        foreach ($upd_data as $key=>$val)
            $finfo[$key] = $val;
        $prv_file = $thb_file = false;

        if (file_exists($prv_file))
            unlink($prv_file);
        if (file_exists($thb_file))
            unlink($thb_file);

        return false;
    }

    function Drop_File($fid)
    {
        global $QF;

        if (!preg_match('#^[0-9A-z]{8}$#D', $fid)) // incorrect file ID
            return false;

        $fid = strtolower($fid);

        if (!isset($this->infos[$fid]))
            if (!$this->Load_FileInfos($fid))
                return false;

        if ($QF->DBase->Do_Delete('files', Array('id' => $fid)))
        {
            $o_folder = $this->infos[$fid]['folder'];
            unset($this->fconts[$o_folder]);
            if (($o_link = $this->infos[$fid]['file_link']) && file_exists($o_link))
                unlink ($o_link);
            if (($o_link = $this->infos[$fid]['prv_link']) && file_exists($o_link))
                unlink ($o_link);
            if (($o_link = $this->infos[$fid]['thb_link']) && file_exists($o_link))
                unlink ($o_link);

            unset($this->infos[$fid]);
            $QF->Cache->Drop(QF_FOLDER_TREE_CACHENAME);
            $QF->Cache->Drop(QF_FILES_CACHE_PREFIX);
            return true;
        }

        return false;
    }

    function Move_File($fid, $fold_id)
    {
        global $QF;

        if (!preg_match('#^[0-9A-z]{8}$#D', $fid)) // incorrect file ID
            return false;

        $fold_id = (int) $fold_id;

        if (!isset($this->f_tree[$fold_id]))
            return false;

        $fid = strtolower($fid);

        if (!isset($this->infos[$fid]))
            if (!$this->Load_FileInfos($fid))
                return false;

        if ($QF->DBase->Do_Update('files', Array('is_temp' => 0, 'folder' => $fold_id), Array('id' => $fid)))
        {
            $this->infos[$fid]['is_temp'] = 0;
            $o_folder = $this->infos[$fid]['folder'];
            $this->infos[$fid]['folder'] = $fold_id;
            unset($this->fconts[$fold_id], $this->fconts[$o_folder]);
            $QF->Cache->Drop(QF_FOLDER_TREE_CACHENAME);
            return true;
        }

        return false;
    }

    function Incrase_Stats($fid)
    {
        global $QF, $FOX;

        if (!preg_match('#^[0-9A-z]{8}$#D', $fid)) // incorrect file ID
            return false;

        if ($QF->DBase->DO_Update('files', Array('dloads' => '++ 1', 'last_dload' => $QF->Timer->time), Array('id' => $fid), QF_SQL_USEFUNCS))
            return true;
        else
            return false;
    }

    function Check_DCode($fid, $dcode)
    {
        global $QF;

        if (!preg_match('#^[0-9A-z]{8}$#D', $fid)) // incorrect file ID
            return false;

        $selector = Array(
            'dl_id'     => $dcode,
            'file_id'   => $fid,
            'client_ip' => $QF->HTTP->IP_int,
            );

        if (($act_till = $QF->DBase->Do_Select('file_dloads', 'active_till', $selector)) && ($act_till >= $QF->Timer->time))
            return true;
        else
            return false;
    }

    function Gen_DCode($fid)
    {
        global $QF;

        if (!preg_match('#^[0-9A-z]{8}$#D', $fid)) // incorrect file ID
            return false;

        $fid = strtolower($fid);

        if (!isset($this->infos[$fid]) && !$this->Get_FileInfo($fid))
            return false;

        $info = $this->infos[$fid];

        $QF->Session->Open_Session(false, true);

        if ($QF->User->is_spider || !$QF->User->CheckAccess($info['r_level']))
            return false;

        // we'll try to find needed dload by session
        $selector = Array(
            'sid'       => $QF->Session->SID,
            'file_id'   => $info['id'],
            'client_ip' => $QF->HTTP->IP_int,
            );
        if ($QF->Session->Get_Status(QF_SESSION_LOADED) && ($ddata = $QF->DBase->Do_Select('file_dloads', Array('dl_id', 'active_till'), $selector)) && ($ddata['active_till'] >= $QF->Timer->time))
        {
            $dcode = $ddata['dl_id'];
            return $dcode;
        }
        else
        {
            $dcode = md5(uniqid($QF->HTTP->Cl_ID));
            $selector['active_till'] = $QF->Timer->time + 3*QF_KERNEL_SESSION_LIFETIME;
            $selector['dl_id'] = $dcode;
            $selector['sid']   = $QF->Session->SID;
            if ($QF->DBase->Do_Insert('file_dloads', $selector, true))
            {
                $QF->DBase->Do_Delete('file_dloads', Array('active_till' => '< '.$QF->Timer->time), QF_SQL_USEFUNCS);
                $this->Incrase_Stats($info['id']); // update dloads counter
                return $dcode;
            }
            else
                trigger_error('FOX_FILES: Can\'t insert new dload into DB', E_USER_ERROR);
        }

        return false;

    }

    function _Start()
    {
        global $QF;

        list($this->thb_w, $this->thb_h) = explode('|', $QF->Config->Get('thb_size', 'files_cfg', '96|96'));
        list($this->prv_w, $this->prv_h) = explode('|', $QF->Config->Get('prv_size', 'files_cfg', '256|256'));

        if (list($f_tree, $f_conts, $f_tids) = $QF->Cache->Get(QF_FOLDER_TREE_CACHENAME))
        {
            $this->f_tree = $f_tree;
            $this->f_tids = $f_tids;
        }
        else
            $this->_Analyze_Folders();
    }

    function _Analyze_Folders()
    {
        global $QF;

        $f_tree = Array(0 => Array('id' => 0, 't_id' => 'root', 'parent' => null, 'name' => '[root folder]', 'type' => 0,
                           'r_level' => 0, 'w_level' => 7, 'acc_gr' => 0, 'mtime' => 0, 'files' => 0,
                           'size' => 0, 'is_sys' => 0));
        $f_tids = Array('root' => 0);
        $f_conts = Array();

        if ($root_name = $QF->Config->Get('root_name', 'files_cfg'))
            $f_tree[0]['name'] = $root_name;

        if ($folders = $QF->DBase->Do_Select_All('file_folders', '*'))
        {
            $fls_pars = $fls_tmps = Array();

            foreach ($folders as $folder) // temporary data dividing
            {
                $fls_pars[$folder['id']] = $folder['parent'];
                $fls_tmps[$folder['id']] = $folder;
                if ($folder['t_id'])
                    $f_tids[$folder['t_id']] = $folder['id'];
            }
            unset ($folders);

            $cur_fl = 0;
            $cstack = Array();
            while (count($fls_pars)) // folder tree resorting
            {
                if ($childs = array_keys($fls_pars, $cur_fl))
                {
                    array_push($cstack, $cur_fl);
                    $cur_fl = $childs[0];
                    $child = $fls_tmps[$cur_fl];
                    $child['t_level'] = count($cstack); // level
                    $f_tree[$cur_fl] = $child;
                    unset($fls_pars[$cur_fl]);
                }
                elseif (count ($cstack) && ($st_top = array_pop($cstack)) !== null)
                {
                    // getting off the branch
                    $cur_fl = $st_top;
                }
                else // this will open looped parentship
                {
                    reset($fls_pars);
                    $key = key($fls_pars);
                    $fls_tmps[$key]['parent'] = 0; // we'll link one folder to root
                    $fls_pars[$key] = 0;
                    $QF->DBase->Do_Update('file_folders', Array('parent' => 0), Array('id' => $key) );
                }
            }

            unset ($fls_pars, $fls_tmps);


        }

        $QF->Cache->Set(QF_FOLDER_TREE_CACHENAME, Array($f_tree, $f_conts, $f_tids));
        $this->f_tree = $f_tree;
        $this->f_tids = $f_tids;
    }

}

?>
