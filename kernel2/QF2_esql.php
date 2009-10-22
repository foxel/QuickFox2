<?php
// -------------------------------------------------------------------------- \\
// QuickFox kernel 2 MySQL 4 database SQL special operations lib              \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if ( defined('QF_KERNEL_ESQL_LOADED') )
        die('Scripting error');

define('QF_KERNEL_ESQL_LOADED', True);

class QF_SQL_Dumper
{
    var $struct = Array();
    var $fstream, $filename, $fgzip;
    var $tblslist = Array();
    var $tblsinfo = Array();
    var $dbase;

    function QF_SQL_Dumper()
    {

    }

    function _Start()
    {
        $this->SQL_ReInit();
    }

    function SQL_ReInit($sel_db = '')
    {
        global $QF;

        $this->tblslist = Array();
        $result = $QF->DBase->SQL_Query('SHOW TABLE STATUS');
        if ($result)
            while($tbl = $QF->DBase->SQL_FetchRow($result))
            {
                $tblname = $tbl['Name'];
                $this->tblslist[] = $tblname;
                $this->tblsinfo[$tblname] = $tbl;
            }

        $this->dbase = $sel_db;

    }

    function Get_Table_Struct($table)
    {
        global $QF;

        $dbkey = $QF->DBase->tbl_prefix;
        $sel_db = $this->dbase;

        if (!in_array($table, $this->tblslist)) return false;

        $field_query = 'SHOW FULL COLUMNS FROM `'.$table.'`';
        $key_query = 'SHOW KEYS FROM `'.$table.'`';

        $tblstruct = Array();

        if ($sel_db)
            $result = $QF->DBase->SQL_DBQuery($sel_db, $field_query);
        else
            $result = $QF->DBase->SQL_Query($field_query);

        if(!$result)
                trigger_error('Failed in get_table_def (show fields) '.$field_query, E_USER_WARNING);

        $fields = Array();

        while ($row = $QF->DBase->SQL_FetchRow($result))
        {
            $fname = $row['Field'];
            if (in_array(substr($row['Type'], -4), Array('blob', 'text')) )
                $row['Default'] = null;

            $fstruct = Array(
                'type'  => $row['Type'],
                'collate' => ($row['Collation'] != 'NULL') ? $row['Collation'] : null,
                'null'  => ($row['Null'] == 'YES') ? true : false,
                'def'   => ($row['Default'] == 'NULL' && $row['Null'] == 'YES') ? null : $row['Default'],
                'extra' => $row['Extra'] );

            $fields[$fname] = $fstruct;
        }


        if ($sel_db)
            $result = $QF->DBase->SQL_DBQuery($sel_db, $key_query);
        else
            $result = $QF->DBase->SQL_Query($key_query);

        if(!$result)
                trigger_error('FAILED IN get_table_def (show keys) '.$key_query, E_USER_WARNING);

        $keys = Array();

        while($row = $QF->DBase->SQL_FetchRow($result))
        {
            $kname = $row['Key_name'];

            if(!isset($keys[$kname]))
            {
                $ktype = $row['Index_type'];
                if ($ktype=='BTREE') {
                    if ($kname == 'PRIMARY')
                        $ktype = 'PRIMARY';
                    elseif ($row['Non_unique'] == 0)
                        $ktype = 'UNIQUE';
                    else
                        $ktype = 'INDEX';
                }

                $keys[$kname] = Array(
                    'type' => $ktype,
                    'cols' => Array() );

            }
            $keys[$kname]['cols'][] = $row['Column_name'];
        }


        $tblstruct = Array(
            'fields' => $fields,
            'keys'   => $keys );

        $tblstruct['name'] = $table;
        if (isset($this->tblsinfo[$table]))
        {
            $tblinfo = $this->tblsinfo[$table];
            $tblstruct['engine'] = $tblinfo['Engine'];
            $tblstruct['collate'] = ($tblinfo['Collation']!='NULL') ? $tblinfo['Collation'] : false;
        }

        $this->struct[$table] = $tblstruct;

        return $tblstruct;
    }

    function Combine_Create_Table($tblstruct, $dropfirst = false)
    {
        if (!is_array($tblstruct)) return false;

        $tblname = $tblstruct['name'];

        $tblquery = "# Table definition for $tblname \n";
        if ($dropfirst)
            $tblquery.= "DROP TABLE IF EXISTS `$tblname` ;\n";
        $tblquery.= "CREATE TABLE `$tblname` ( \n";

        $fields = $tblstruct['fields'];
        if ($tbcollate = $tblstruct['collate'])
            foreach ($fields as $name => $fdata)
                if ($fdata['collate'] == $tbcollate)
                    $fields[$name]['collate'] = false;

        $flist = Array();
        if (is_array($fields))
            foreach ($fields as $name => $fdata)
                if (is_array($fdata)) {
                    $field='`'.$name.'` '.$fdata['type'];
                    if ($fdata['collate']) $field.=' COLLATE '.$fdata['collate'];
                    if (!$fdata['null']) $field.=' NOT NULL';
                    if (!is_null($fdata['def'])) $field.=' DEFAULT \''.addslashes($fdata['def']).'\'';
                    elseif ($fdata['null']) $field.=' DEFAULT NULL';
                    if (strlen($fdata['extra'])>0) $field.=' '.$fdata['extra'];
                    $flist[]=$field;
                }

        $flist=implode(", \n    ",$flist);

        $keys = $tblstruct['keys'];
        $klist = Array();
        if (is_array($keys))
            foreach ($keys as $name => $data)
                if (is_array($data)) {
                    foreach ($data['cols'] as $num => $col)
                        $data['cols'][$num] = '`'.$col.'`';
                    if ($data['type']=='PRIMARY')
                        $klist[]='PRIMARY KEY ('.implode(', ', $data['cols']).') ';
                    else
                        $klist[]=$data['type'].' `'.$name.'` ('.implode(', ', $data['cols']).') ';
                }

        $klist=implode(", \n    ",$klist);

        $tblquery.='    '.$flist;
        if (!empty($klist))
            $tblquery.=", \n    ".$klist;
        $tblquery.= " \n)";

        if (strlen($tblstruct['engine'])>0)
            $tblquery.= ' ENGINE = '.$tblstruct['engine'];
        if ($tblstruct['collate'])
            $tblquery.= ' COLLATE '.$tblstruct['collate'];

        $tblquery.= "; \n\n";

        return $tblquery;
    }

    function Get_Table_Struct_SQL($table, $dropfirst = false, $repldbkey = false)
    {
        global $QF;
        $dbkey = $QF->DBase->tbl_prefix;

        if(!is_array($this->struct[$table]))
            $tblstruct = $this->Get_Table_Struct($table);
        else
            $tblstruct = $this->struct[$table];

        if ($repldbkey && strpos($table, $dbkey) === 0)
            $tblname = preg_replace('#^'.preg_quote($dbkey, '#').'#', '{DBKEY}', $table, 1);
        else
            $tblname = $table;

        $tblstruct['name'] = $tblname;

        return $this->Combine_Create_Table($tblstruct, $dropfirst);
    }

    function Get_Table_Cont_SQL($table, $do_replace = false, $EFS_stream = false, $repldbkey = false, $sql_where = false)
    {
        global $QF;
        $dbkey = $QF->DBase->tbl_prefix;
        $sel_db = $this->dbase;

        $do_efs = false;
        $ret_res = '';

        $comm = ($do_replace) ? 'REPLACE INTO ' : 'INSERT INTO ';

        if (!in_array($table, $this->tblslist))
            return false;

        if ($repldbkey && strpos($table, $dbkey) === 0)
            $tblname = preg_replace('#^'.preg_quote($dbkey, '#').'#', '{DBKEY}', $table, 1);
        else
            $tblname = $table;

        if ($EFS_stream && isset($QF->EFS))
            if ($do_efs = ($QF->EFS->CheckMode($EFS_stream)&QF_EFS_WRITE))
                $ret_res = 0;

        $query = $QF->DBase->QC_Simple_Select($table, '*', $sql_where, '', QF_SQL_NOPREFIX);
        if ($sel_db)
            $result = $QF->DBase->SQL_DBQuery($sel_db, $query);
        else
            $result = $QF->DBase->SQL_Query($query);

        if (!$result)
                trigger_error("Failed in Get_Table_Cont_SQL (select *) SELECT * FROM $table", E_USER_WARNING);

        if ($row = $QF->DBase->SQL_FetchRow($result))
        {
            if ($do_efs)
                $ret_res += $QF->EFS->Write($EFS_stream, "# Data content for $tblname \n");
            else
                $ret_res.= "# Data content for $tblname \n";

            $field_names = array();

            // Grab the list of field names.
            $num_fields = $QF->DBase->SQL_NumFields($result);
            for ($j = 0; $j < $num_fields; $j++)
                $field_names[$j] = $QF->DBase->SQL_FieldName($j, $result);

            $table_list = '(`'.implode('`, `', $field_names).'`)';

            do
            {
                // Start building the SQL statement.
                $dump = $comm." `$tblname` $table_list \n    VALUES(";

                for ($j = 0; $j < $num_fields; $j++)
                {
                    $dump.= ($j > 0) ? ', ' : '';

                    if (!isset($row[$field_names[$j]]) || is_null($row[$field_names[$j]]))
                        $dump.= 'NULL';

                    elseif ($row[$field_names[$j]] != '')
                        $dump.= '\'' . $QF->DBase->_Escape_String($row[$field_names[$j]]) . '\'';

                    else
                        $dump.= '\'\'';
                }

                $dump.= ");\n";

                if ($do_efs)
                    $ret_res += $QF->EFS->Write($EFS_stream, $dump);
                else
                    $ret_res.= $dump;
            }
            while ($row = $QF->DBase->SQL_FetchRow($result));

        }

        if (isset($this->tblsinfo[$table]))
        {
            $tblinfo = $this->tblsinfo[$table];
            if (!is_null($tblinfo['Auto_increment']))
            {
                $a_inc = (int) $tblinfo['Auto_increment'];
                $query = 'ALTER TABLE `'.$tblname.'` AUTO_INCREMENT = '.$a_inc.";\n";
                if ($do_efs)
                    $ret_res += $QF->EFS->Write($EFS_stream, $query);
                else
                    $ret_res.= $query;

            }
        }

        if ($do_efs)
            $ret_res += $QF->EFS->Write($EFS_stream, "\n\n");
        else
            $ret_res.= "\n\n";

        return $ret_res;
    }

    function Dump_Tables($filename, $try_gzip = false, $sets=Array())
    {
        global $QF;
        static $conf_defs = Array(
            'all_tables' => false,
            'nostruct'   => false,
            'nocontent'  => false,
            'dropfirst'  => true,
            'repldbkey'  => false);

        $dbkey = $QF->DBase->tbl_prefix;

        if (is_array($sets))
            extract($sets, EXTR_SKIP);

        extract($conf_defs, EXTR_SKIP);

        if (!$QF->Run_Module('EFS'))
            return false;
        $EFS_mode = QF_EFS_WRITE;
        if ($try_gzip)
            $EFS_mode |= QF_EFS_GZIP_TRY;

        $EFS_stream = $QF->EFS->Open($filename, $EFS_mode);

        $QF->EFS->Write($EFS_stream, "#\n# QuickFox mysql database dump file \n#\n\n\n");

        foreach ($this->tblslist as $tblname)
            if ((strpos($tblname, $dbkey)===0) || $all_tables)
            {
                if (!$nostruct)
                    $QF->EFS->Write($EFS_stream, $this->Get_Table_Struct_SQL($tblname, $dropfirst, $repldbkey));
                if (!$nocontent)
                    $this->Get_Table_Cont_SQL($tblname, false, $EFS_stream, $repldbkey);
            }

        $filename = $QF->EFS->CheckName($EFS_stream);
        $QF->EFS->Close($EFS_stream);

        return $filename;
    }

}

class QF_SQL_Importer
{
    var $status = Array();
    var $errlog = Array();

    function QF_SQL_Importer()
    {

    }

    function _Start()
    {
        $this->SQL_ReInit();
    }

    function SQL_ReInit($sel_db = '')
    {
        global $QF;

        $this->tblslist = Array();
        $result = $QF->DBase->SQL_Query('SHOW TABLES');
        if ($result)
            while($tbl = $QF->DBase->SQL_FetchRow($result, false))
            {
                list($tblname) = $tbl;
                $this->tblslist[] = $tblname;
            }

        $this->status = Array(
            'char'    => '',
            'dchar'   => chr(0),
            'in_str'  => '',
            'in_comm' => 0,
            'cur_sql' => '',
            'sel_db'  => $sel_db,
            );

    }

    function SQL_Parse($sql_text, $no_continue = true)
    {
        global $QF;

        $len = strlen($sql_text);
        extract($this->status, EXTR_OVERWRITE);

        $time0 = time();

        for ($sql_pos=0; $sql_pos<$len; $sql_pos++)
        {
            $char = $sql_text[$sql_pos] ;
            $dchar = substr($dchar.$char, -2);

            if ($in_comm) {
                switch ($in_comm) {
                    case 2:
                        if ($dchar == '*/')
                            $in_comm = 0;
                        break;
                    default:
                        if ($char == "\n")
                            $in_comm = 0;
                }
            }

            elseif ($in_str)
            {
                $cur_sql.= $char;
                if ($char == $in_str)
                {
                    if ($in_str == '`' || $dchar{0} != '\\')
                        $in_str = '';
                    else // one or more Backslashes before the presumed end of string...
                    {
                        // ... first checks for escaped backslashes
                        $j = strlen($cur_sql) - 3;
                        $escaped_backslash = false;
                        while ($j >= 0 && $cur_sql{$j} == '\\')
                        {
                            $escaped_backslash = !$escaped_backslash;
                            $j--;
                        }
                        // ... if escaped backslashes: it's really the end of the
                        // string -> exit the loop
                        if ($escaped_backslash)
                            $in_str = '';
                    }
                }
            }

            elseif (($char == '"') || ($char == '\'') || ($char == '`'))
            {
                $cur_sql.= $char;
                $in_str = $char;
            }

            elseif ($char == '#')
                $in_comm = 1;
            elseif (($dchar == '/*') || ($dchar == '--'))
            {
                $in_comm = ($dchar == '/*') ? 2 : 1;
                $cur_sql = substr($cur_sql, 0, strlen($sql)-1);
            }

            elseif ($char == ';')
            {
                $cur_sql = trim($cur_sql);
                if (!empty($cur_sql)) {
                    if ($sel_db)
                        $QF->DBase->SQL_DBQuery($sel_db, $cur_sql);
                    else
                        $QF->DBase->SQL_Query($cur_sql);

                    $err = $QF->DBase->SQL_Error();
                    if ($err['code'])
                        $this->errlog[]=$err;
                }

                $cur_sql = '';
            }

            else
                $cur_sql.= $char;

            if (!($sql_pos%1000))
            {
                $time1     = time();
                if ($time1 >= $time0 + 30)
                {
                    $time0 = $time1;
                    header('X-QFPing: Pong');
                }
            }

        };

        if ($no_continue)
        {
            if ($in_str)
                $cur_sql.= $in_str;

            $cur_sql = trim($cur_sql);
            if (!empty($cur_sql))
            {
                if ($sel_db)
                    $QF->DBase->SQL_DBQuery($sel_db, $cur_sql);
                else
                    $QF->DBase->SQL_Query($cur_sql);

                $err = $QF->DBase->SQL_Error();
                if ($err['code'])
                    $this->errlog[]=$err;
            }

            $this->SQL_ReInit($sel_db);
        }
        else
        {
            $this->status = compact(array_keys($this->status));
        }

        return true;
    }

    function Parse_SQL_File($filename, $try_gz = true)
    {
        global $QF;
        $mode = QF_EFS_READ;
        if ($try_gz)
            $mode |= QF_EFS_GZIP_TRY;

        $QF->Run_Module('EFS');
        if ($stream = $QF->EFS->Open($filename, $mode))
        {
            $QF->EFS->ParseCallback($stream, Array(&$this, 'SQL_Parse'));
            $QF->EFS->Close($stream);
            return true;
        }
        else
            return false;
    }

    function Apply_Table_Struct($tblstruct, $drop_extra_fields = true)
    {
        global $QF;
        $dbkey = $QF->DBase->tbl_prefix;
        $sel_db = $this->status['sel_db'];

        if (!is_array($tblstruct)) return false;
        if (!$tblstruct['name']) return false;
        $tblstruct['name'] = preg_replace('#^\{DBKEY\}#', $dbkey, $tblstruct['name']);
        $table = $tblstruct['name'];

        $QF->Run_Module('DBDump');

        $dumper_dbase = $QF->DBDump->dbase;
        $QF->DBDump->SQL_ReInit($sel_db);
        $oldstruct = $QF->DBDump->Get_Table_Struct($table);
        $QF->DBDump->SQL_ReInit($dumper_dbase);

        if (!is_array($oldstruct)) { // we must create table
            $query = $QF->DBDump->combine_create_table($tblstruct);
            if ($sel_db)
                $QF->DBase->SQL_DBQuery($sel_db, $query, true);
            else
                $QF->DBase->SQL_Query($query, true);
            $err = $QF->DBase->SQL_Error();
            if ($err['code'])
                $this->errlog[]=$err;
            return true;
        }

        if ($oldstruct['name']!=$table)
            return false;

        $fields = $tblstruct['fields'];
        $keys   = $tblstruct['keys'];
        if (!is_array($fields))
            return false;
        if (!is_array($keys))
            $keys=Array();


        $old_fields = $oldstruct['fields'];
        $old_keys   = $oldstruct['keys'];
        if (!is_array($old_fields))
            $old_fields=Array();
        if (!is_array($old_keys))
            $old_fields=Array();

        $keys_add  = Array();
        $keys_drop = Array();
        $commands  = Array();

        foreach ($keys as $kname => $kdata)
        {
            if (!is_array($old_keys[$kname]))
                $keys_add[] = $kname;
            else {
                $do_correct = false;
                if ($old_keys[$kname]['type'] != $kdata['type'])
                    $do_correct = true;
                if (sizeof($kdata['cols']) != sizeof($old_keys[$kname]['cols']))
                    $do_correct = true;
                foreach ($kdata['cols'] as $col)
                    if (!in_array($col, $old_keys[$kname]['cols']))
                        $do_correct = true;
                if ($do_correct)
                {
                    $keys_drop[] = $kname;
                    $keys_add[] = $kname;
                }
            }
        }

        foreach ($old_keys as $kname => $kdata)
            if (!is_array($keys[$kname]))
                $keys_drop[] = $kname;

        // Droping keys first
        foreach ($keys_drop as $kname) {
            if ($kname == 'PRIMARY')
                $commands[] = 'DROP PRIMARY KEY';
            else
                $commands[] = 'DROP INDEX `'.$kname.'`';
        }

        // Comparing fields
        $prev_field = ''; // previous field - for inserting operations

        foreach ($fields as $fname => $fdata)
        {
            $do_correct = false;
            $do_add = false;

            if (!is_array($old_fields[$fname]))
            {
                $do_correct = true;
                $do_add = true;
            }
            else
            {
                foreach ($fdata as $param => $val)
                    if ($old_fields[$fname][$param] != $val)
                        $do_correct = true;
            }

            if (!empty($do_correct))
            {
                $extra = '';
                $field = '`'.$fname.'` '.$fdata['type'];
                if ($fdata['collate']) $field.=' COLLATE '.$fdata['collate'];
                if (!$fdata['null']) $field.=' NOT NULL';
                if (!is_null($fdata['def'])) $field.= ' default \''.$QF->DBase->_Escape_String($fdata['def']).'\'';
                elseif ($fdata['null']) $field.= ' default NULL';
                if (strlen($fdata['extra'])>0)
                {
                    $field.= ' '.$fdata['extra'];
                    if (preg_match('#^auto_(.*?)$#i', $fdata['extra']) && !in_array('PRIMARY', $keys_add))
                    {
                        $field.= ' PRIMARY KEY';
                        if (is_array($old_keys['PRIMARY']) && !in_array('PRIMARY', $keys_drop))
                            $commands[]='DROP PRIMARY KEY';
                    }
                }

                if ($do_add)
                {
                    $query = ' ADD '.$field;
                    $query.= (empty($prev_field)) ? ' FIRST' : ' AFTER `'.$prev_field.'`';
                }
                else
                    $query = ' CHANGE `'.$fname.'` '.$field;

                $commands[] = $query;
            }

            $prev_field = $fname;
        }

        // Droping extra fields
        if ($drop_extra_fields)
            foreach ($old_fields as $fname => $fdata)
                if (!is_array($fields[$fname]))
                    $commands[] = 'DROP `'.$fname.'`';

        // Adding keys
        foreach ($keys_add as $kname)
        {
            $kdata = $keys[$kname];

            foreach ($kdata['cols'] as $num => $col)
                $kdata['cols'][$num] = '`'.$col.'`';

            if ($kdata['type'] == 'PRIMARY')
                $key = 'PRIMARY KEY ('.implode(', ', $kdata['cols']).') ';
            else
                $key = $kdata['type'].' `'.$kname.'` ('.implode(', ', $kdata['cols']).') ';

            $commands[] = 'ADD '.$key;
        }

        if (count($commands)>0)
        {
            $query = 'ALTER TABLE `'.$table.'` '.implode(', ', $commands);

            if ($sel_db)
                $QF->DBase->SQL_DBQuery($sel_db, $query, true);
            else
                $QF->DBase->SQL_Query($query, true);
            $err = $QF->DBase->SQL_Error();
            if ($err['code'])
                $this->errlog[]=$err;
        }
    }

    function Parse_STRFile($filename, $drop_extra_fields = true, $try_gz = true)
    {
        global $QF;
        $mode = QF_EFS_READ;
        if ($try_gz)
            $mode |= QF_EFS_GZIP_TRY;

        if ($stream = $QF->EFS->Open($filename, $mode))
        {
            $data = $QF->EFS->Get_Cont($stream);
            $QF->EFS->Close($stream);
            eval('$bases = '.$data.';');
            foreach ($bases as $name=>$struct)
                $this->Apply_Table_Struct($struct, $drop_extra_fields);
            return true;
        }
        else
            return false;
    }

}

