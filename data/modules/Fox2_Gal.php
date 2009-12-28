<?php

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

define('QF_GALLERY_FOLDER_ID', 'sys_gallery');

define('QF_GALLERY_CACHE_PREFIX', 'FOX_GALLERY.');

define('QF_GALLERY_SEARCH_ALL', 0);
define('QF_GALLERY_SEARCH_USERID', 1);
define('QF_GALLERY_SEARCH_PUBLIC', 2);
define('QF_GALLERY_SEARCH_PERSONAL', 3);
define('QF_GALLERY_SEARCH_LAST10', 8);


class QF_Gallery
{    var $items  = Array();
    var $albums = Array();
    var $u_itms = Array();
    var $al_tid = Array();
    var $my_folder = false;

    function QF_Gallery()
    {        global $QF;
        $QF->Run_Module('Files');
        $QF->Run_Module('PTree');

        if ($finfo = $QF->Files->Get_FolderInfo(QF_GALLERY_FOLDER_ID))
            $this->my_folder = $finfo['id'];        else
            $this->my_folder = $QF->Files->Create_Folder(QF_GALLERY_FOLDER_ID, 0, QF_GALLERY_FOLDER_ID, false, true);

    }

    function Drop_From_Album($item_id, $album_id)
    {
        global $QF;

        $album = $this->Get_Album_Info($album_id);
        $item  = $this->Get_Item_Info($item_id);

        if (!$album || !$item)
            return false;

        if (!in_array($item_id, $album['items']))
            return true;

        if (!$QF->DBase->Do_Delete('gal_al_itms', Array('album_id' => $album['id'], 'item_id' => $item['id'])))
            return false;

        if ($last_itms = $QF->DBase->Do_Select_All('gal_al_itms', 'item_id', Array('album_id' => $album['id']), ' ORDER BY put_at DESC LIMIT 0, 3'))
            $last_itms = array_reverse($last_itms);
        else
            $last_itms = Array();

        $last_itms = implode('|', $last_itms);

        $QF->DBase->Do_Update('gal_albums', Array('lastthree' => $last_itms), Array('id' => $album['id']));
        $QF->Cache->Drop(QF_GALLERY_CACHE_PREFIX);
        return true;
    }

    function Put_To_Album($item_id, $album_id)
    {        global $QF;

        $album = $this->Get_Album_Info($album_id);
        $item  = $this->Get_Item_Info($item_id);

        if (!$album || !$item)
            return false;

        if (in_array($item_id, $album['items']))
            return true;

        $data = Array (
            'item_id' => $item_id,
            'album_id' => $album['id'],
            'put_by' => $QF->User->UID,
            'put_at' => $QF->Timer->time,
            );

        if ($last_itms = $QF->DBase->Do_Select_All('gal_al_itms', 'item_id', Array('album_id' => $album['id']), ' ORDER BY put_at DESC LIMIT 0, 2'))
        {
            $last_itms = array_reverse($last_itms);
            $last_itms[] = $item_id;
        }
        else
            $last_itms = Array($item_id);
        $last_itms = implode('|', $last_itms);

        if ($QF->DBase->Do_Insert('gal_al_itms', $data))
        {            $QF->DBase->Do_Update('gal_albums', Array('lasttime' => $QF->Timer->time, 'lastthree' => $last_itms), Array('id' => $album['id']));
            $QF->Cache->Drop(QF_GALLERY_CACHE_PREFIX);
            return true;
        }
    }

    function Drop_Item($item_id, $drop_file = false)
    {        global $QF;

        $item  = $this->Get_Item_Info($item_id);

        if (!$item)
            return false;

        if ($QF->DBase->Do_Delete('gal_items', Array('id' => $item['id'])))
        {            // dropping album links
            if ($QF->DBase->Do_Delete('gal_al_itms', Array('item_id' => $item['id'])))
            {                $albums = Array_keys($item['albums']);
                foreach ($albums as $alid)
                {
                    if ($last_itms = $QF->DBase->Do_Select_All('gal_al_itms', 'item_id', Array('album_id' => $alid), ' ORDER BY put_at DESC LIMIT 0, 3'))
                        $last_itms = array_reverse($last_itms);
                    else
                        $last_itms = Array();

                    $last_itms = implode('|', $last_itms);

                    $QF->DBase->Do_Update('gal_albums', Array('lastthree' => $last_itms), Array('id' => $alid));
                }

            }

            // processing linked file
            if ($drop_file)
                $QF->Files->Drop_File($item['file_id']);
            elseif ($finfo = $QF->Files->Get_FileInfo($item['file_id']))
            {
                $upd = Array(
                    'is_temp' => 1,
                    'time'    => $QF->Timer->time,
                    );
                $QF->Files->Modif_File($item['file_id'], $upd);
            }

            unset($this->u_itms[$data['author_id']]);
            $QF->Cache->Drop(QF_GALLERY_CACHE_PREFIX);

            return true;
        }

        return false;
    }

    function Modif_Item($item_id, $caption, $params = false)
    {
        global $QF;

        $item  = $this->Get_Item_Info($item_id);

        if (!$item)
            return false;

        $data = Array(
            'author' => $item['author'], 'author_id' => $item['author_id'],
            'time' => $item['time'], 'r_level' => $item['r_level'], 'description' => $item['description'],
            );

        if (is_array($params))
          foreach ($params as $par=>$val)
            if (isset($data[$par]))
            {
                settype($val, gettype($data[$par]));
                $data[$par] = $val;
            }
        $data['r_level'] = min($data['r_level'], QF_FOX2_MAXULEVEL);
        $data += Array(
            'caption' => $caption,
            );

        if ($QF->DBase->Do_Update('gal_items', $data, Array('id' => $item['id'])) !== false)
        {
            if ($finfo = $QF->Files->Get_FileInfo($item['file_id']))
            {                $QF->Files->Move_File($file_id, $this->my_folder);
                $filename = $QF->LNG->Translit($caption.' by '.$data['author']);
                if ($ext = pathinfo($finfo['filename'], PATHINFO_EXTENSION))
                    $filename.= '.'.$ext;

                $upd = Array(
                    'caption'  => $caption,
                    'filename' => $filename,
                    'r_level'  => $data['r_level'],
                    );
                $QF->Files->Modif_File($item['file_id'], $upd);
            }
            if (!$item['pt_root'] && ($tid = $QF->PTree->Create_Tree('FOX2_GAL', Array($item['id']), Array('r_level' => $data['r_level'], 'w_level' => max($data['r_level'], 1)))))
                $QF->DBase->Do_Update('gal_items', Array('pt_root' => $tid), Array('id' => $item['id']));

            unset($this->u_itms[$data['author_id']]);
            $QF->Cache->Drop(QF_GALLERY_CACHE_PREFIX);

            return true;
        }

        return false;
    }

    function Create_Item($caption, $file_id, $params = false)
    {        global $QF;

        if (($finfo = $QF->Files->Get_FileInfo($file_id)) && $finfo['has_pics'])
        {            $new_id = qf_short_hash($caption.' - '.$QF->User->uname);
            $data = Array(
                'author' => $QF->User->uname, 'author_id' => $QF->User->UID,
                'time' => $QF->Timer->time, 'r_level' => 0, 'description' => '',
                );

            if (is_array($params))
              foreach ($params as $par=>$val)
                if (isset($data[$par]) && !empty($val))
                {
                    settype($val, gettype($data[$par]));
                    $data[$par] = $val;
                }
            $data['r_level'] = min($data['r_level'], QF_FOX2_MAXULEVEL);
            $data += Array(
                'id' => $new_id, 'caption' => $caption, 'file_id' => $file_id,
                );

            if ($QF->DBase->Do_Select('gal_items', '*', Array('id' => $new_id, 'file_id' => $file_id), '', QF_SQL_WHERE_OR ))
                return false; // duplicate enrties
            if ($QF->DBase->Do_Insert('gal_items', $data))
            {                $QF->Files->Move_File($file_id, $this->my_folder);
                $filename = $QF->LNG->Translit($caption.' by '.$data['author']);
                if ($ext = pathinfo($finfo['filename'], PATHINFO_EXTENSION))
                    $filename.= '.'.$ext;

                $upd = Array(
                    'caption'  => $caption,
                    'filename' => $filename,
                    'r_level'  => $data['r_level'],
                    );
                $QF->Files->Modif_File($file_id, $upd);
                if ($tid = $QF->PTree->Create_Tree('FOX2_GAL', Array($new_id), Array('r_level' => $data['r_level'], 'w_level' => max($data['r_level'], 1), 'author_id' => $data['author_id'], 'author' => $data['author'])))
                    $QF->DBase->Do_Update('gal_items', Array('pt_root' => $tid), Array('id' => $new_id));

                unset($this->u_itms[$data['author_id']]);
                $QF->Cache->Drop(QF_GALLERY_CACHE_PREFIX);

                return $new_id;
            }
        }

        return false;
    }

    function Create_Album($caption, $t_id = '', $owner = 0, $access = false)
    {        global $QF;

        if (!$caption)
            return false;

        if (!preg_match('#^\w+$#D', $t_id))
            $t_id = preg_replace('#\W#', '', strtr($QF->LNG->Translit($caption), ' ', '_'));

        $t_id = strtolower($t_id);

        if ($this->Get_Album_Info($t_id))
            return false;

        $data = Array(
            't_id'     => $t_id,
            'caption'  => $caption,
            'owner_id' => abs($owner),
            'lasttime' => $QF->Timer->time,
            );

        if (!$owner && is_array($access))
        {            if (isset($access['r_level']))
                $data['w_level'] = $data['r_level'] = min(abs($access['r_level']), QF_FOX2_MAXULEVEL);
            if (isset($access['w_level']))
                $data['w_level'] = min(abs($access['w_level']), $data['r_level'], QF_FOX2_MAXULEVEL);
        }

        if ($id = $QF->DBase->Do_Insert('gal_albums', $data))
        {
            $QF->Cache->Drop(QF_GALLERY_CACHE_PREFIX);
            return $id;
        }

        return false;
    }

    function Get_Items($mode = QF_GALLERY_SEARCH_ALL, $param = false, $level = false)
    {        global $QF;

        $quer = Array();
        $flags = 0;
        $other = Array('order' => Array('time' => 'DESC'));

        if ($mode & QF_GALLERY_SEARCH_LAST10)
        {
            $other['limit'] = Array(0, 10);
            $mode-= QF_GALLERY_SEARCH_LAST10;
        }
        switch ($mode)
        {
            case QF_GALLERY_SEARCH_USERID:
                $quer  = Array('author_id' => $param);
                break;
        }
        if (is_int($level))
        {
            $flags |= QF_SQL_USEFUNCS;
            $quer['r_level'] = ($level >= 0) ? '<= '.$level : '>= '.abs($level);
        }

        if ($datas = $QF->DBase->Do_Select_All('gal_items', 'id', $quer, $other, $flags))
            return $datas;

        return null;
    }

    function Get_Albums($mode = QF_GALLERY_SEARCH_ALL, $param = false, $level = false)
    {        global $QF;

        $quer = Array();
        $other = Array('order' => Array('lasttime' => 'DESC'));

        if ($mode & QF_GALLERY_SEARCH_LAST10)
        {
            $other['limit'] = Array(0, 10);
            $mode-= QF_GALLERY_SEARCH_LAST10;
        }
        switch ($mode)
        {
            case QF_GALLERY_SEARCH_USERID:
                $quer = Array('owner_id' => $param);
                break;
            case QF_GALLERY_SEARCH_PUBLIC:
                $quer = Array('owner_id' => 0);
                break;
            case QF_GALLERY_SEARCH_PERSONAL:
                $flags |= QF_SQL_USEFUNCS;
                $quer = Array('owner_id' => '!= 0');
                break;
        }
        if (is_int($level))
        {
            $flags |= QF_SQL_USEFUNCS;
            $quer['r_level'] = ($level >= 0) ? '<= '.$level : '>= '.abs($level);
        }

        if ($datas = $QF->DBase->Do_Select_All('gal_albums', 'id', $quer, $other, $flags))
            return $datas;

        return null;
    }

    function Get_Album_Info($album_id)
    {
        global $QF;

        if (is_numeric($album_id))
            $datas =&$this->albums;
        elseif (preg_match('#^\w+$#D', $album_id))
        {
            $album_id = strtolower($album_id);
            $datas =&$this->al_tid;
        }
        else
            return false;


        if (isset($datas[$album_id]))
            return $datas[$album_id];

        if ($this->Load_AlbumInfos($album_id))
            return $datas[$album_id];

        return false;
    }

    function Get_Item_Info($item_id)
    {        global $QF;

        if (!preg_match('#^[0-9A-z]{8}$#D', $item_id)) // incorrect ID
            return false;

        $item_id = strtolower($item_id);

        if (isset($this->items[$item_id]))
            return $this->items[$item_id];

        if ($this->Load_ItemInfos($item_id))
            return $this->items[$item_id];

        return false;
    }

    function Load_AlbumInfos($ids)
    {
        global $QF;

        if (!$ids)
            return false;

        if (!is_array($ids))
            $ids = explode('|', $ids);

        $ids = array_unique($ids);
        sort($ids);

        $cachename = (count($ids) > 2)
            ? QF_GALLERY_CACHE_PREFIX.'AL_Qs.'.md5(implode('|', $ids))
            : false;

        $alb_q = Array('gal_albums'  => Array('fields' => '*', 'where' => Array('id' => $ids, 't_id' => $ids)),
                       'gal_al_itms' => Array('fields' => false, 'join' => Array('album_id' => 'id'), 'order' => Array('put_at' => 'desc')),
                       'gal_items'   => Array('fields' => Array('id' => 'items', 'r_level' => 'itm_l'), 'join' => Array('id' => 'item_id'), 'join_to' => 1, 'order' => Array('time' => 'desc')));

        if ($cachename && (list($albums, $al_tid) = $QF->Cache->Get($cachename)) && is_array($albums) && is_array($al_tid))
        {
            $this->albums += $albums;
            if (count($al_tid))
                foreach($al_tid as $t_id => $id)
                    $this->al_tid[$t_id] =& $this->albums[$id];
            return true;
        }
        elseif ($datas = $QF->DBase->Do_Multitable_Select($alb_q, '', QF_SQL_LEFTJOIN | QF_SQL_SELECTALL | QF_SQL_WHERE_OR))//$QF->DBase->Do_Select_All('gal_albums', '*', Array('id' => $ids, 't_id' => $ids), '', QF_SQL_WHERE_OR))
        {
            $ids = Array();
            $al_tid = $albums = Array();

            foreach ($datas as $data)
            {
                $id = $data['id'];
                if (!isset($albums[$id]))
                {
                    $ids[] = $id;
                    $t_id = strtolower($data['t_id']);
                    if ($data['items'])
                    {
                        $data['itm_l'] = Array($data['items'] => (int) $data['itm_l']);
                    }
                    else
                        $data['items'] = $data['itm_l'] = Array();

                    $data['lastthree'] = (preg_match('#^[0-9A-z]{8}(\|[0-9A-z]{8})*$#', $data['lastthree'])) ? explode('|', $data['lastthree']) : array();
                    $albums[$id] = $data;
                    if ($t_id)
                        $al_tid[$t_id] = $id;
                }
                else
                    $albums[$id]['itm_l'][$data['items']] = (int) $data['itm_l'];
            }

            foreach ($albums as $id => $data)
            {
                $albums[$id]['items'] = array_keys($data['itm_l']);
                $albums[$id]['itm_l'] = array_values($data['itm_l']);
            }

            $this->albums += $albums;
            if (count($al_tid))
                foreach($al_tid as $t_id => $id)
                    $this->al_tid[$t_id] =& $this->albums[$id];

            if ($cachename)
                $QF->Cache->Set($cachename, Array($albums, $al_tid));
            return true;
        }
        else
            return false;
    }

    function Load_ItemInfos($ids, $with_fileinfos = false)
    {
        global $QF;

        if (!$ids)
            return false;

        if (!is_array($ids))
            $ids = explode('|', $ids);

        $ids = array_unique($ids);
        sort($ids);

        if (count($ids) > 2)
            $cachename = QF_GALLERY_CACHE_PREFIX.'ITM_Qs.'.md5(implode('|', $ids));

        if ($cachename && ($items = $QF->Cache->Get($cachename)) && is_array($items))
        {            $this->items += $items;
            $fids = qf_2darray_cols($items, 'file_id');
            if ($with_fileinfos)
                $QF->Files->Load_FileInfos($fids);

            return true;
        }
        elseif ($datas = $QF->DBase->Do_Select_All('gal_items', '*', Array('id' => $ids)))
        {
            $ids = Array();
            $fids = Array();
            $items = Array();

            foreach ($datas as $data)
            {
                if (!$data['pt_root'] && ($tid = $QF->PTree->Create_Tree('FOX2_GAL', Array($data['id']), Array('r_level' => $data['r_level'], 'w_level' => max($data['r_level'], 1), 'author_id' => $data['author_id'], 'author' => $data['author']))))
                {
                    $QF->DBase->Do_Update('gal_items', Array('pt_root' => $tid), Array('id' => $data['id']));
                    $data['pt_root'] = $tid;
                }
                $ids[] = $id = $data['id'];
                $fids[] = $data['file_id'];
                $data['albums'] = Array();
                $items[$id] = $data;
            }

            if ($with_fileinfos)
                $QF->Files->Load_FileInfos($fids);

            $mt_q = Array('gal_al_itms' => Array('fields' => 'item_id', 'where' => Array('item_id' => $ids)),
                          'gal_albums'  => Array('fields' => Array('id', 't_id', 'caption', 'r_level'), 'join' => Array('id' => 'album_id')));
            if ($datas = $QF->DBase->Do_Multitable_Select($mt_q, '', QF_SQL_LEFTJOIN | QF_SQL_SELECTALL))
            {                foreach ($datas as $data)
                {
                    $id = $data['item_id'];
                    unset($data['item_id']);
                    $items[$id]['albums'][$data['id']] = $data;
                }
            }

            $this->items += $items;

            if ($cachename)
                $QF->Cache->Set($cachename, $items);
            return true;
        }
        else
            return false;
    }

}

?>