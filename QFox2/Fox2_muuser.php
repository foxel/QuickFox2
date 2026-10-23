<?php

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if (defined('QF_MUUSER_LOADED'))
    die('Scripting error');

define('QF_MUUSER_LOADED', true);

#[AllowDynamicProperties]
class Fox2_MultiUser
{
    function __construct()
    {

    }

    function GetRelations($touid, $uid=0)
    {
        global $QF;
        if ($uid == 0)
            $uid = $QF->User->UID;
    }
}

?>
