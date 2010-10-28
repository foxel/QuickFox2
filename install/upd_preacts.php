<?php

if ( !defined('QF_STARTED') || !defined('QF_SETUP_STARTED'))
        die('Hacking attempt');

if ($SET_step == 'data_acc' && !$SET_act && file_exists('data/cfg/database.qfc')) // updates on 28 oct 2010 
{
    $confs = qf_file_get_carray('data/cfg/database.qfc');
    $file = Array('<?php /*');
    foreach($confs as $c => $v)
        $file[] = $c.' => '.$v;
    $file[] = '*/ ?>';
    qf_file_put_contents('data/cfg/database.qfc.php', implode("\n", $file));
    chmod('data/cfg/database.qfc.php', 0600);
    unlink('data/cfg/database.qfc');
    
    unset($confs, $file);
}

if ($SET_step == 'dbase_import' && $QF->GPC->Get_String('imp_mode', QF_GPC_POST, QF_STR_WORD) == 'upd') // updates on 13 oct 2010 
{
    $QF->DBase->SQL_Query('TRUNCATE '.$QF->DBase->tbl_prefix.'file_dloads');
}

?>