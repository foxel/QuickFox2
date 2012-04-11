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
    {
        global $QF, $FOX;

        // Google analytics script adding
        $GA_ID = $QF->Config->Get('ganalytics_id', 'googleadds');
        if (preg_match('#^UA-\d+-\d+$#iD', $GA_ID))
        {
            $GA_ID = strtoupper($GA_ID);
            $QF->VIS->Add_Data(0, 'HEAD_JS', "
                var _gaq = _gaq || [];
                _gaq.push(['_setAccount', '{$GA_ID}']);
                ".(($domain = ltrim($QF->Config->Get('cookie_domain'), '.')) ? "_gaq.push(['_setDomainName', '.{$domain}']);
                " : '')."_gaq.push(['_trackPageview']);
                (function() {
                  var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
                  ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
                  var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
                })();"
            );
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