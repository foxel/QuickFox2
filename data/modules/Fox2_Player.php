<?php

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

class Fox2_MPlayer
{
    var $fold_name = 'music';

    function Fox2_MPlayer()
    {        global $QF, $FOX;

        $this->fold_name = $QF->Config->Get('play_folder', 'fox_player', 'music');
    }

    function PrePage($pg)
    {
        global $QF, $FOX;

        if ($pg == 'openfolder')
            $QF->Events->Set_On_Event('fox_folderpage_draw',  Array(&$this, '_OnFolderPageDraw') );
    }

    function PostPage($pg, $pg_node)
    {        global $QF, $FOX;

        if (!$QF->Config->Get('show_in_panels', 'fox_player', 1))
            return;

        $QF->VIS->Load_Templates('music_player');
        $QF->VIS->Add_Node('FOX_PLAYER_SIDEINCL', 'PANELS', 0, Array('FOLDER_ID' => $this->fold_name));
    }

    function _OnFolderPageDraw($finfo_page, $fold_id, $conts)
    {        global $QF, $FOX;

        $got_mus = $got_mp3 = false;
        foreach ($conts as $cont_id)
        {
            if (is_array($cont_id))
                continue;

            $cont_item = $QF->Files->Get_FileInfo($cont_id);
            if (!$QF->User->CheckAccess($cont_item['r_level']))
                continue;

            if ($cont_item['mime'] == 'audio/mpeg')
            {
                $got_mus = $got_mp3 = true;
                break;
            }
            if (strpos($cont_item['mime'], 'audio/') === 0)
                $got_mus = true;
        }

        if ($got_mus)
        {
            $QF->VIS->Load_Templates('music_player');
            $QF->VIS->Add_Node('FOX_PLAYER_FOLDERPAGE_PLAY', 'ADD_BUTTS', $finfo_page, Array('FOLDER_ID' => $fold_id, 'CAN_PLAY' => $got_mp3 ? 1 : null));
        }
    }

    function DPage()
    {        global $QF, $FOX;
        $p_type = $QF->GPC->Get_String('p_type', QF_GPC_GET, QF_STR_WORD);
        switch($p_type)
        {            case 'playlist':
                return $this->DPage_PL();
            case 'm3u':
                return $this->DPage_PL(true);
            default:
                return $this->DPage_Player();
        }

        return false;
    }

    function DPage_PL($is_m3u = false)
    {        global $QF, $FOX;
        $QF->Run_Module('Files');


        if (($my_fold = $QF->GPC->Get_String('folder', QF_GPC_GET, QF_STR_WORD)) === null)
            $my_fold = $this->fold_name;
        else
            $QF->Session->Open_Session();

        if ($fold = $QF->Files->Get_FolderInfo($my_fold))
            $conts = $QF->Files->Get_FolderConts($fold['id']);
        else
        {
            $conts = Array();
            if ($my_fold == $this->fold_name)
                $QF->Config->Set('show_in_panels', false, 'fox_player', true);
        }
        $QF->Files->Load_FileInfos($conts);

        $mime = 'text/xml';
        $filename = null;
        $output = Array();
        if ($is_m3u)
        {            $mime = 'audio/x-mpegurl';
            $filename = ($fold['t_id'] ? $fold['t_id'] : $fold['id']).'.m3u8';
            $output[] = '#EXTM3U';
            foreach ($conts as $cont_id)
            {
                $cont_item = $QF->Files->Get_FileInfo($cont_id);
                if (strpos($cont_item['mime'], 'audio/') !== 0 || !$QF->User->CheckAccess($cont_item['r_level']))
                    continue;

                $output[] = '#EXTINF:-1,'.$cont_item['caption'];
                $output[] = $FOX->Gen_URL('fox2_file_download_bysess', Array($cont_item['id'], $cont_item['filename']), true, true, true);
            }
        }
        else
        {
            $output = Array('<?xml version="1.0" encoding="'.QF_INTERNAL_ENCODING.'"?>', '<list folder="'.$my_fold.'" >');
            foreach ($conts as $cont_id)
            {
                $cont_item = $QF->Files->Get_FileInfo($cont_id);
                if ($cont_item['mime'] != 'audio/mpeg' || !$QF->User->CheckAccess($cont_item['r_level']))
                    continue;

                $output[] = ' <item href="'.$FOX->Gen_URL('fox2_file_download_bysess', Array($cont_item['id'], $cont_item['filename']), true, true, true).'" capt="'.$cont_item['caption'].'" />';
            }
            $output[] = '</list>';
        }
        $output = implode("\n", $output);

        $QF->HTTP->do_HTML = false;
        $QF->HTTP->Clear();
        $QF->HTTP->Write($output);
        $QF->HTTP->Send_Buffer('', $mime, 60, $filename);
    }

    function DPage_Player()
    {        global $QF, $FOX;
        $PLUrl = (($my_fold = $QF->GPC->Get_String('folder', QF_GPC_GET, QF_STR_WORD)) !== null)
                    ? $FOX->Gen_URL('fox2_player_plist_folder', $my_fold, false, true)
                    : $FOX->Gen_URL('fox2_player_playlist', null, false, true);

        $data = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml">
        <head>
          <!--Meta-Content-Type-->
          <meta http-equiv="Content-Style-Type" content="text/css" />
          <title>Music Player</title>
        </head>
        <body>
        <div style="padding: 0; margin: 5px auto; width: 180px; height: 40px;">
        <object type="application/x-shockwave-flash" data="'.qf_full_url($FOX->Gen_URL('fox2_player_swf')).'" style="width: 180px; height: 40px;">
         <param name="bgcolor" value="#ffffff" />
         <param name="allowScriptAccess" value="always" />
         <param name="allowFullScreen" value="true" />
         <param name="wmode" value="transparent" />
         <param name="movie" value="'.qf_full_url($FOX->Gen_URL('fox2_player_swf')).'" />
         <param name="flashvars" value="&amp;PLFile='.qf_url_encode_part($PLUrl).'" />
        </object>
        </div>
        </body>
        </html>';
        $data = preg_replace('#(\n\s*)+#', "\n", $data);
        $QF->HTTP->Clear();
        $QF->HTTP->Write($data);
        $QF->HTTP->Send_Buffer('', '', 3600);
    }
}

?>