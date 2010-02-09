<?php

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if ( defined('FOX_GOOGLEADDS_LOADED') )
        die('Scripting error');

define('FOX_GOOGLEADDS_LOADED', True);

class Fox2_GoogleAdds
{
    function PostPage()
    {        global $QF, $FOX;

        // Google analytics script adding
        $GA_ID = $QF->Config->Get('ganalytics_id', 'googleadds');
        if (preg_match('#^UA-\d+-\d+$#iD', $GA_ID))
        {            $GA_ID = strtoupper($GA_ID);            $QF->VIS->Add_Data(0, 'JS_BLOCKS', '<script type="text/javascript" src="'.(($QF->HTTP->Secure)?'https://ssl.':'http://www.').'google-analytics.com/ga.js" ></script>');
            $QF->VIS->Add_Data(0, 'BOTT_JS', 'try {var pageTracker = _gat._getTracker(\''.$GA_ID.'\'); pageTracker._trackPageview();} catch(err) {};');
        }

        // Google web masters check code
        $GWM_CODE = $QF->Config->Get('gwebmasters_code', 'googleadds');
        if (preg_match('#^[\w\/\=\+\-]+$#D', $GWM_CODE))
        {
            $QF->VIS->Add_Data(0, 'META', '<meta name="google-site-verification" content="'.$GWM_CODE.'" />');
        }
    }
}

?>