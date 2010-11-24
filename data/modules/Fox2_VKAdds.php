<?php

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if ( defined('FOX_VKADDS_LOADED') )
        die('Scripting error');

define('FOX_VKADDS_LOADED', True);

class Fox2_VKAdds
{
    function PostPage($pg_id)
    {
        global $QF, $FOX;

        // linking API
        $QF->VIS->Add_Data(0, 'JS_BLOCKS', '<script type="text/javascript" src="http://vkontakte.ru/js/api/openapi.js" ></script>');

        // Group
        $G_ID = (int) $QF->Config->Get('vk_group_id', 'vkadds');
        if ($G_ID)
        {
            $QF->VIS->Add_Node('MISC_DIV', 'PANELS', 0, Array('contents' => '<hr /><div id="qf_vk_group"></div>', 'style' => 'width: 200px; margin: 10px auto;'));
            $QF->VIS->Add_Data(0, 'BOTT_JS', 'try {VK.Widgets.Group("qf_vk_group", {mode: 1, width: 200, height: 150}, '.$G_ID.');} catch(err) {};');
        }

        /* if (in_array($pg_id, Array('pages', 'cms', 'gallery', 'blogs')))
        {            $QF->VIS->Add_Data(0, 'JS_BLOCKS', '<script type="text/javascript" src="http://vkontakte.ru/js/api/share.js" ></script>');
            $QF->VIS->Add_Node('MISC_DIV', 'PAGE_CONT', 0, Array('id' => 'vk_share_btn'));
            $QF->VIS->Add_Data(0, 'BOTT_JS', 'try {qf_getbyid("vk_share_btn").innerHTML = VK.Share.button(false,{type: "round", text: "Сохранить"});} catch(err) {};');
        } */
    }
}

?>