<?php

// -------------------------------------------------------------------------- \\
// QuickFox kernel 2 services control module                                  \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if ( defined('QF_KERNEL_SERVICES_LOADED') )
        die('Scripting error');

define('QF_KERNEL_SERVICES_LOADED', True);

class QF_Services
{    var $time_spent = 0;

    function QF_Services()
    {
    }

    function _Start()
    {        global $QF;

        if ($service = $QF->DBase->Do_Select('services', '*', 'active = 1 AND next_run <= '.$QF->Timer->time, 'ORDER BY next_run'))
        {            $file = $service['run_php'];
            if (file_exists($file))
            {                $srv_start_time = $QF->Timer->Get_MTime();
                include($file);
                $this->time_spent = $QF->Timer->Get_MTime() - $srv_start_time;
                $QF->DBase->Do_Update('services', Array('next_run' => $QF->Timer->time + $service['run_period']), Array('name' => $service['name']));
            }
            else
            {
                $QF->DBase->Do_Update('services', Array('active' => 0), Array('name' => $service['name']));
                trigger_error('SERVICE: Service "'.$service['name'].'" can\'t be executed because file "'.$file.'" couldn\'t be found. Service deactivated', E_USER_WARNING);
            }
        }
    }

}

?>