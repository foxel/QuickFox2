<?php

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if (defined('QF_MUUSER_LOADED'))
    die('Scripting error');

define('QF_MUUSER_LOADED', true);

class Fox2_MultiUser
{
    function Fox2_MultiUser()
    {
    }

    function GetRelations($touid, $uid=0)
    {        global $QF;        if ($uid == 0)
            $uid = $QF->User->UID;
    }
}

?>
