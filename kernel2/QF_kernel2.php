<?php

// -------------------------------------------------------------------------- \\
// This is the main QF kernel 2 file. Provides main class implementation      \\
//                                                            (c) LION 2007   \\
// -------------------------------------------------------------------------- \\

// needs PHP 4.1.0
// needs MySQL 4.1.0
// PHP 4.3.0 or later recommended

// Simple InUSE Check
if ( !defined('QF_STARTED') )
    die('Hacking attempt');

if ( defined('QF_KERNEL_LOADED') )
    die('Scripting error');

// let's check the kernel requirements
if ( PHP_VERSION < '4.1.0' )
    die('PHP 4.1.0 required');
if (!extension_loaded('pcre'))
    die('PCRE extension is required to run the kernel');

// QuickFox kernel2 initialization
define('QF_KERNEL_LOADED', True);
define('QF_KERNEL_DIR', dirname(__FILE__).'/');
define('QF_INDEX', basename($_SERVER['PHP_SELF']));

if (!defined('QF_SITE_ROOT'))
    define('QF_SITE_ROOT', getcwd().'/');

if (!defined('QF_DATA_ROOT'))
    define('QF_DATA_ROOT', QF_SITE_ROOT.'data/');

// this will add E_STRICT on PHP 4
if (!defined('E_STRICT'))
    define('E_STRICT', 2048);

//
// Sets some initial running parameters
//
Error_Reporting(0 & ~(E_NOTICE | E_USER_NOTICE | E_STRICT) );
set_magic_quotes_runtime(0);
set_time_limit(30);  // not '0' - once i had my script running for a couple of hours collecting GBytes of errors :)
setlocale(LC_ALL, 'EN'); // english locale
if ( PHP_VERSION < '4.2.0' )
    srand(time()); // regenereting randomizer in PHP < 4.2.0

// Sequrity system - deleting all user posted data from globals if they were set by 'register_globals'
if (ini_get('register_globals'))
{
    $glob_keys = array_keys($_REQUEST + $_ENV + $_FILES + $_SERVER);
    foreach ($glob_keys as $rvar)
        unset ($$rvar);
    unset($glob_keys, $rvar);
    define('QF_GLOBALS_CLEARED', true);
}
// we need to turn off output buffering
if (ini_get('output_buffering'))
    ob_end_clean();

// Initial error catcher
// After init catcher changes
function init_err_parse($errno, $errstr, $errfile = 'n/a', $errline = 'n/a')
{
    global $debug;
    static $logfile;
    static $ERR_TYPES = Array(
        E_ERROR        => 'PHP ERROR',
        E_WARNING      => 'PHP WARNING',
        E_NOTICE       => 'PHP NOTICE',
        E_USER_ERROR   => 'QF ERROR',
        E_USER_WARNING => 'QF WARNING',
        E_USER_NOTICE  => 'QF NOTICE',
        E_STRICT       => 'PHP5 STRICT',
        );

    if (!$logfile) {
        $logfile = fopen('init_err.log', 'ab');
    }

    if ( $errno & ~(E_NOTICE | E_USER_NOTICE | E_STRICT) )
    {
        if ($logfile)
            fwrite($logfile,date('[d M Y H:i]').' '.$ERR_TYPES[$errno].': '.$errstr.'. File: '.$errfile.'. Line: '.$errline.".\r\n");
    }

    if ( $errno & ~(E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING | E_STRICT) )
    {
        qf_ob_free();
        if ($debug)
            die ($ERR_TYPES[$errno].': '.$errstr.'. <hr>File: '.$errfile.'. Line: '.$errline.'<br />');
        else
            die ('<html><head><title>Error</title></head><body><h1>There is an error!!!</h1>Sorry but this page can not be displayed. Please try visit us later.</body></html>');
    }
    elseIf ($debug) {
        if ( $errno & ~(E_NOTICE | E_USER_NOTICE | E_STRICT) )
            print $ERR_TYPES[$errno].': '.$errstr.'. <hr>File: '.$errfile.'. Line: '.$errline.'<br />';
    }

}

// Lets Set init_err_handler
set_error_handler('init_err_parse');

// Lets include some base kernel classes implementation files
$base_modules_files = Array(
    QF_KERNEL_DIR.'QF2_flib.php',          // kernel 2 functions library
    QF_KERNEL_DIR.'QF2_base.php',          // kernel 2 basic classes
    QF_KERNEL_DIR.'QF2_cache.php',         // kernel 2 caching system
    QF_KERNEL_DIR.'QF2_mysql.php',         // kernel 2 MySQL class implementation
    QF_KERNEL_DIR.'QF2_configs.php',       // kernel 2 configs implementation
    QF_KERNEL_DIR.'QF2_sess.php',          // kernel 2 session manager
    QF_KERNEL_DIR.'QF2_lang.php',          // kernel 2 language manager
);
// well do some trick with caching base modules in one file
$base_modules_stats = Array();
foreach ($base_modules_files as $fname)
    $base_modules_stats[] = implode('|', stat($fname));
$base_modules_stats = md5(implode('|', $base_modules_stats));
$base_modules_file  = QF_KERNEL_DIR.'bases-'.$base_modules_stats.'.krninc';
if (file_exists($base_modules_file))
    require_once($base_modules_file);
else
{
    $d = opendir(QF_KERNEL_DIR);
    while (false !== ($fname = readdir()))
        if (preg_match('#^bases\-[0-9a-fA-F]{32}\.krninc$#', $fname))
            unlink(QF_KERNEL_DIR.$fname);
    closedir($d);
    $base_modules_eval = '';
    foreach ($base_modules_files as $fname)
        $base_modules_eval.= preg_replace('#^\s*\<\?php|^\s*\<\?|\?\>\s*$|(?<=\n)\s+#D', '', implode('', file($fname)));
    if ($stream = fopen($base_modules_file, 'wb'))
    {
        $result = fwrite($stream, "<?php\n".$base_modules_eval."\n?>");
        fclose($stream);
        eval($base_modules_eval);
        unset($base_modules_eval, $stream);
    }
    else
    {
        trigger_error('Kernel: Error creating base modules cache.', E_USER_WARNING);
        foreach (base_modules_files as $fname)
            require_once($fname);
    }
}
// finished including basic modules


class QuickFox_kernel2
{
    var $mod_files   = Array();    //list of files for modules
    var $mod_classes = Array();    //list of clases for modules
    var $need_close  = Array();

    function QuickFox_kernel2()
    {
        global $QF;
        $QF =& $this;

        // Creating initial QF structure
        $this->USTR     = new QF_UString();              // String parsing module for multiencoding work
        $this->Timer    = new QF_Timer();                // timer
        $this->Events   = new QF_Events();               // events system
        $this->GPC      = new QF_GPC_Request();          // Get, Post, Cookie data input
        $this->HTTP     = new QF_HTTP();                 // HTTP interface
        $this->DBase    = new QF_SQL_DBase();            // Database driver
        $this->Cache    = new QF_Cacher();               // file cache driver
        $this->Config   = new QF_Configs();              // configs interface
        $this->Session  = new QF_Session();              // Session manager
        $this->LNG      = new QF_Lang();                 // HTTP interface
        //$this->Parser   = new QF_BB_Parser();            // bb code parser
        //$this->VIS      = new QF_Visual();               // VIS system

        $this->Timer->Time_Log('QF kernel2 class constructed');
    }

    function Initialize()
    {
        $this->LNG->_Start();

        if ($DB_Config = qf_file_get_carray(QF_DATA_ROOT.'cfg/database.qfc'))
        {
            $flags = 0;
            if (defined('QF_SETUP_STARTED'))
            	$flags = QF_DBASE_WARNONLY;
            if (!$this->DBase->Connect($DB_Config, $flags))
                trigger_error('Kernel: Error connecting database. Please check your database configureation', E_USER_ERROR);
	    }

        $this->GPC->_Start();
        $this->HTTP->_Start();


        if ($CL_Config = qf_file_get_carray(QF_KERNEL_DIR.'modules.qfc'))
        {
            foreach ($CL_Config as $mod => $cfg)
            {
                $cfg = explode('|', $cfg);
                if ($cfg[1])
                {
                    $this->mod_files[$mod] = QF_KERNEL_DIR.trim($cfg[1]);
                    $this->mod_classes[$mod] = trim($cfg[0]);
                }
                else
                {
                    $this->mod_files[$mod] = QF_KERNEL_DIR.trim($cfg[0]);
                    $this->mod_classes[$mod] = 'QF_'.$mod;
                }

            }
        }

        register_shutdown_function(array(&$this, 'Close'));
        $this->Timer->Time_Log('QF kernel2 initialized');
    }

    function Register_Module($mod_name, $mod_file, $mod_class = '')
    {
        if (!$mod_name || !$mod_file)
            return false;

        if (!$mod_class)
            $mod_class = $mod_name;

        $this->mod_files[$mod_name] = $mod_file;
        $this->mod_classes[$mod_name] = $mod_class;

    }

    function Run_Module($mod_name)
    {
        if (preg_match('#\W#', $mod_name))
            return false;

        if (isset($this->mod_files[$mod_name]) && isset($this->mod_classes[$mod_name]))
            $mod_file = $this->mod_files[$mod_name];
        else
            return false;

        $mod_class = $this->mod_classes[$mod_name];

        if (isset($this->$mod_name))
            return qf_is_a($this->$mod_name, $mod_class);

        if (!class_exists($mod_class))
        {
            if (file_exists($mod_file))
                include_once($mod_file);
            else
                trigger_error('Kernel: can\'t locate "'.$mod_file.'" module file', E_USER_ERROR);
        }

        if (class_exists($mod_class))
        {
            $object = new $mod_class();
            $this->$mod_name =& $object;
            if (method_exists($object, '_Start'))
                $object->_Start();
            if (method_exists($object, '_Close'))
                $this->need_close[] = Array(&$object, '_Close');

            return true;
        }

        trigger_error('Kernel: can\'t create "'.$mod_name.'" module object', E_USER_ERROR);
        return false; // just in case ))
    }

    // checks if given module is running
    function Check_Module($mod_name)
    {
        if (preg_match('#\W#', $mod_name))
            return false;

        if (isset($this->mod_classes[$mod_name]))
            $mod_class = $this->mod_classes[$mod_name];
        else
            return false;

        return (isset($this->$mod_name) && qf_is_a($this->$mod_name, $mod_class));
    }


    // Must be executed by the end of script.
    // Actually closes all connections and saves all data
    function Close()
    {
        for ($i=count($this->need_close); $i>0; $i--)
            call_user_func($this->need_close[$i-1]);

        $this->Session->_Close();
        $this->HTTP->_Close();
        $this->Config->_Close();
        $this->Cache->_Close();
        $this->DBase->Close();
    }
}


$QF = new QuickFox_kernel2();
$QF->Initialize();

?>
