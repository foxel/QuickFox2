<?php

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if ( defined('FOXADD_ANTIIE_LOADED') )
        die('Scripting error');

define('FOXADD_ANTIIE_LOADED', True);

class Fox2_AntiIE
{
    function PostPage()
    {
        global $QF, $FOX;

        if ($QF->Session->clicks > 3)
            return;

        $QF->VIS->Load_Templates('antiie');
        $QF->VIS->Add_Node('FOXADD_ANTIIE_BLOCK', 'PANELS', 0, Array('NO_WARNING' => ($QF->Session->clicks > 1) ? 1 : null));
    }
}

?>