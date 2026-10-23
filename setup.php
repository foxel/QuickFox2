<?php

// -------------------------------------------------------------------------- \\
//            QuickFox 2 setup script                  (c) LION 2008, 2026    \\
// -------------------------------------------------------------------------- \\

if ( PHP_VERSION < '4.1.0' )
    die('PHP 4.1.0 required');

// this is TAR unpacker in gz compressed format (setup.php is 5 kB now instead of 14)
$TAR_GZ_Class =
'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
qQ==';

define ('QF_STARTED',True);
define ('QF_SETUP_STARTED',True);

//$debug=1;

Error_Reporting(E_ALL & ~E_NOTICE);
set_time_limit(30);

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
    {
        $file = 'qf2_pack.tar.gz';
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
        {
            foreach ($chmods as $chmod)
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
    {
        print '
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
