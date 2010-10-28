<?php

// -------------------------------------------------------------------------- \\
//            QuickFox 2 setup script                        (c) LION 2008    \\
// -------------------------------------------------------------------------- \\

if ( PHP_VERSION < '4.1.0' )
    die('PHP 4.1.0 required');

// this is TAR unpacker in gz compressed format (setup.php is 5 kB now instead of 14)
$TAR_GZ_Class =
'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';

define ('QF_STARTED',True);
define ('QF_SETUP_STARTED',True);

//$debug=1;

Error_Reporting(E_ALL & ~E_NOTICE);
set_time_limit(30);
if (get_magic_quotes_runtime())
    set_magic_quotes_runtime(0);

function setup_err_parse($errno, $errstr, $errfile, $errline)
{
    global $debug;
    If ($debug) {
       if ($errno!=8&&$errno!=1024)
       print 'Error '.$errno.'. '.$errstr.'<hr>File: '.$errfile.'. Line: '.$errline.'<br />';
    }
}

// Let's Set init_err_handler
set_error_handler('setup_err_parse');

$SET_step = $_POST['step'];
$SET_act  = $_POST['action'];
$error    = '';

if ($_GET['extracted'] && file_exists('index.php'))
    $SET_step = 'data_acc';

// this script only performs data extracting
// all other operations provided by install script from the package

if (!empty($SET_step) && file_exists('install/install.php'))
    include ('install/install.php');
else
{

    print '<html>
    <head>
     <title>Installing QuickFox 2</title>
    </head>
    <body>
    <form action="setup.php" method="post" name="mainform">
     <div style="text-align: center;">
    ';

    if ($SET_act == 'GO')
    {        $file = 'qf2_pack.tar.gz';
        $ufile = 'upd_pack.tar.gz';
        if (file_exists($ufile) && (!file_exists($file) || (filemtime($ufile) > filemtime($file))))
            $file = $ufile;

        eval(gzuncompress(base64_decode($TAR_GZ_Class)));

        $arch = new TGZRead($file);
        if ($arch->stream)
        {
            $arch->ExtractArchive('',True);

            if (!file_exists('index.php'))
                $error='Error extracting pack';
        }
        elseif ($chmods = file('.qf_chmods'))
        {            foreach ($chmods as $chmod)
            {
                $chmod = explode('|', $chmod);
                if (file_exists($chmod[0]))
                    @chmod($chmod[0], octdec($chmod[1]));
                else
                    $error = 'Some files are missing!';
            }
            unlink('.qf_chmods');
        }
        else
            $error='No data to install!';

        if (!$error) {
            chmod($file,octdec('600'));
            print '
            <h3>All the data is extracted and prepared!</h3>
            <input type="hidden" name="step" value="data_acc" />
            <input type="submit" name="OK" value="GO!" />';
        }
        else
            print $error;

    }
    else
    {        print '
        <h3>Now We are ready to install QuickFox 2 on this server!</h3>
        <input type="hidden" name="action" value="GO" />
        First We must extract/prepare data. <br />
        <input type="submit" name="OK" value="GO!" />';
    }

    print '
    </div>
    </form>
    </body>
    </html>';
}

?>
