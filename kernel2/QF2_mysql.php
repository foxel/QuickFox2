<?php

// -------------------------------------------------------------------------- \\
// QuickFox kernel 2 MySQL database driver via mysqli                         \\
// (c) LION 2006, 2026                                                        \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if (defined('QF_KERNEL_SQL_DRIVER'))
    die('Scripting error');

define('QF_KERNEL_SQL_DRIVER', 'mysqli');
qf_define('QF_KERNEL_SQL_EXPLAINS', false);

// Dbase Conf Constants
define('QF_DBASE_NOPREFIX', 1);
define('QF_DBASE_AUTOCHK',  2);
define('QF_DBASE_WARNONLY', 4);
define('QF_DBASE_DEFAULT',  0);  // default cofig

// Sql_Query_Constants
define('QF_SQL_NOESCAPE',  1);
define('QF_SQL_USEFUNCS',  2);
define('QF_SQL_WHERE_OR',  4);
define('QF_SQL_SELECTALL', 8);
define('QF_SQL_NOPREFIX', 16);
define('QF_SQL_LEFTJOIN', 32);

class QF_SQL_DBase
{

    var $db_connect_id = false;
    var $transaction   = false;

    var $server        = '';
    var $database      = '';
    var $codepage      = 'utf8';

    var $cur_dbname    = '';

    var $tbl_prefix    = 'qf_';
    var $auto_prefix   = false;
    var $auto_dbcheck  = true;

    var $query_result  = null;
    var $do_warnings   = false;

    var $row           = array();
    var $rowset        = array();

    var $num_queries   = 0;
    var $queries_time  = 0;
    var $history       = array();

    var $logfile       = null;

    // Constructor
    function __construct()
    {
        if (!extension_loaded('mysqli'))
            die('MySQLi extension is required');

        if (QF_KERNEL_SQL_EXPLAINS)
            $this->logfile = fopen('sql_explains.log', 'wb');

        $this->do_warnings = true; // we don't need errors when we did not even tried to connect (neede by installer)
    }

    function Check()
    {
        return ($this->db_connect_id) ? true : false;
    }

    function Connect($conn_config, $c_params = QF_DBASE_DEFAULT, $persistency = true)
    {
        static $conn_defs = Array(
            'location' => 'localhost',
            'database' => '', 'password' => '', 'username' => '',
            'codepage' => 'utf8', 'prefix' => 'qf_',
            );
        $conn_config += $conn_defs; // avoiding undefined indexes

        $this->persistency = $persistency;

        $this->server      = $conn_config['location'];
        $this->database    = $conn_config['database'];
        $password          = $conn_config['password'];
        $username          = $conn_config['username'];

        $this->auto_prefix  = ( $c_params & QF_DBASE_NOPREFIX ) ? false : true;
        $this->auto_dbcheck = ( $c_params & QF_DBASE_AUTOCHK ) ? true : false;
        $this->do_warnings  = ( ($c_params & QF_DBASE_WARNONLY) ) ? true : false;

        $this->codepage    = ( $conn_config['codepage'] ) ? $conn_config['codepage'] : 'utf8';
        $this->tbl_prefix  = ( $conn_config['prefix'] ) ? $conn_config['prefix'] : 'qf_';

        $this->_Do_Connect($username, $password);

        if( $this->db_connect_id && $this->database)
            if ($this->auto_dbcheck && !$this->Fast_DbCheck($this->database))
                return ($this->db_connect_id = false);

        return $this->db_connect_id;

    }

    // Other base methods
    function Select_DB($database = '', $global = false)
    {
        if( $this->db_connect_id )
        {
            if ($database && mysqli_select_db($this->db_connect_id, $database) )
            {
                $this->cur_dbname = $database;
            }
            elseif (mysqli_select_db($this->db_connect_id, $this->database))
            {
                $this->cur_dbname = $this->database;
            }
            else
                return ($this->db_connect_id = false);

            if ($this->auto_dbcheck && !$this->Fast_DbCheck($database))
                return ($this->db_connect_id = false);

            return true;
        }

    }

    function Close($commit = true)
    {
        if ($this->logfile)
            fclose($this->logfile);

        $connect = $this->db_connect_id;
        $this->db_connect_id = false;
        if ( $connect ) {
            if ($this->transaction) {
                $tquer = ($commit) ? 'COMMIT' : 'ROLLBACK';
                mysqli_query($connect, $tquer);
            }
            return mysqli_close($connect);
        }
        else
            return false;
    }

    // Transaction control functions

    // Begining transaction
    function Tr_Begin()
    {
        if( $this->db_connect_id )
        {
            if ($this->transaction)
                mysqli_query($this->db_connect_id, 'COMMIT');

            return mysqli_query($this->db_connect_id, 'BEGIN');
        }
    }

    // commiting transaction
    function Tr_Commit()
    {
        if( $this->db_connect_id )
        {
            if ($this->transaction)
                return mysqli_query($this->db_connect_id, 'COMMIT');
            else
                return true;
        }
    }

    // rolling back transaction
    function Tr_Rollback()
    {
        if( $this->db_connect_id )
        {
            if ($this->transaction)
                return mysqli_query($this->db_connect_id, 'ROLLBACK');
            else
                return true;
        }
    }

    // Query constructors
    // Simple onetable select
    function QC_Simple_Select ($table, $fields = Array(), $where = '', $other = '', $flags = 0)
    {
        $where = $this->_Where_Parse($where, $flags);
        $other = $this->_Parse_Other($other, $flags);

        if ($this->auto_prefix && !($flags & QF_SQL_NOPREFIX))
            $table = $this->tbl_prefix.$table;

        $query = 'SELECT ';

        if (is_array($fields)) {
            if (count($fields))
            {
                foreach ($fields as $id => $fname)
                {
                    $fields[$id] = '`'.$fname.'`';
                }
                $fields = implode(', ', $fields).' ';
            }
            else
                $fields = '*';
        }

        if (empty($fields))
            $fields = '*';

        $query.=$fields.' ';

        $query.='FROM `'.$table.'` '.$where.' '.strval($other);

        return $query;
    }

    // complex multitable select
    // $tqueries = Array (
    //     'table1_name' => Array('fields' => '*', 'where' => '...', 'prefix' => 't1_'),
    //     'table2_name' => Array('fields' => '*', 'where' => '...', 'prefix' => 't2_', 'join' => Array('[table2_filed_name]' => '[main_table_field_name]', ...) ),
    //     ...
    //     )
    function QC_Multitable_Select ($tqueries, $other = '', $flags = 0)
    {
        $qc_fields = $qc_where = $qc_order = Array();
        $qc_tables = '';

        if (!is_array($tqueries))
            return '';

        $ti = 0;
        foreach ($tqueries as $table => $params)
        {
            if ($this->auto_prefix && !($flags & QF_SQL_NOPREFIX))
                $table = $this->tbl_prefix.$table;

            $tl = 't'.$ti;

            if ($ti > 0)
            {
                $join_by = Array();
                if (isset($params['join']) && is_array($params['join']) && count($params['join']))
                {
                    $join_to = 0;
                    if (isset($params['join_to']) && ($join_to = (int) $params['join_to']))
                        $join_to = min(max(0, $join_to), $ti - 1);

                    foreach($params['join'] as $tfield => $mtfield)
                    {
                        $join_by[] = $tl.'.`'.$tfield.'` = t'.$join_to.'.`'.$mtfield.'`';
                    }
                }

                if ($flags & QF_SQL_LEFTJOIN && count($join_by))
                    $qc_tables.= ' LEFT JOIN `'.$table.'` '.$tl;
                else
                    $qc_tables.= ' JOIN `'.$table.'` '.$tl;

                if (count($join_by))
                    $qc_tables.= ' ON ('.implode(', ', $join_by).')';
            }
            else
                $qc_tables = '`'.$table.'` '.$tl;

            if (isset($params['fields']))
            {
                $ifields = $params['fields'];
                if (is_array($ifields))
                {
                    $prefix = (isset($params['prefix']))
                        ? preg_replace('#[^A-Za-z_]#', '', $params['prefix'])
                        : false;
                    if (count($ifields))
                        foreach ($ifields as $fkey => $fname)
                        {
                            if (is_int($fkey))
                            {
                                $field = $tl.'.`'.$fname.'`';
                                if ($prefix)
                                    $field.= ' AS `'.$prefix.$fname.'`';
                            }
                            else
                                $field = $tl.'.`'.$fkey.'` AS `'.$fname.'`';

                            $qc_fields[] = $field;
                        }
                    else
                        $qc_fields[] = $tl.'.*';
                }
                elseif ($ifields == '*')
                    $qc_fields[] = $tl.'.*';
                elseif ($ifields)
                    $qc_fields[] = $tl.'.`'.$ifields.'`';
            }

            if (isset($params['order']))
            {
                $order = $params['order'];
                if (is_array($order))
                {
                    foreach($order as $fkey => $fname)
                    {
                        if (is_int($fkey))
                            $field = $tl.'.`'.$fname.'` ASC';
                        else
                        {
                            $type = strtolower($fname);
                            $field = $tl.'.`'.$fkey.'`'.(($type == 'desc' || $type == '-1') ? ' DESC' : ' ASC');
                        }
                        $qc_order[] = $field;
                    }
                }
                elseif ($order)
                    $qc_order[] = $tl.'.`'.$order.'`';
            }

            if (isset($params['where']) && $where = $this->_Where_Parse($params['where'], $flags, $tl)) {
                $qc_where[] = preg_replace('#^WHERE\s#i', '', $where);
            }
            $ti++;
        }

        if (count($qc_fields))
            $qc_fields = implode(', ', $qc_fields);
        else
            $qc_fields = '*';

        if (count($qc_where))
            $qc_where = 'WHERE '.implode(' AND ', $qc_where);
        else
            $qc_where = '';

        if (count($qc_order))
            $qc_order = 'ORDER BY '.implode(', ', $qc_order);
        else
            $qc_order = '';

        $query ='SELECT '.$qc_fields.' FROM '.$qc_tables.' '.$qc_where.' '.$qc_order.' '.strval($other);

        return $query;
    }

    // High level queries

    // simple one table select
    function Do_Select ($table, $fields = Array(), $where = '', $other = '', $flags = 0)
    {
        $ret = Array();
        $query = $this->QC_Simple_Select ($table, $fields, $where, $other, $flags);
        if ($result = $this->SQL_Query($query, true))
        {
            if ($flags & QF_SQL_SELECTALL)
            {
                $ret = Array();
                if ($this->SQL_NumFields() == 1)
                    while (list($res) = $this->SQL_FetchRow($result, false))
                        $ret[] = $res;
                else
                    while ($res = $this->SQL_FetchRow($result))
                        $ret[] = $res;
            }
            elseif ($this->SQL_NumFields() == 1)
                list($ret) = $this->SQL_FetchRow($result, false);
            else
                $ret = $this->SQL_FetchRow($result);

            $this->SQL_FreeResult($result);

            if (QF_KERNEL_SQL_EXPLAINS && ($result = $this->SQL_Query('EXPLAIN '.$query, true)))
            {
                $explains = Array();
                while ($res = $this->SQL_FetchRow($result))
                    $explains[] = $res;

                $this->SQL_FreeResult($result);
                $file = md5($query).'.exp';
                fwrite($this->logfile, $query."\n\n".qf_array_definition($explains)."\n\n");
            }

            return $ret;
        }
        else
            return false;
    }

    // simple one table select all records
    function Do_Select_All ($table, $fields = Array(), $where = '', $other = '', $flags = 0)
    {
        return $this->Do_Select ($table, $fields, $where, $other, $flags | QF_SQL_SELECTALL);
    }

    // select query with callback for every row
    function Do_Select_Callback ($func_link, $table, $fields = Array(), $where = '', $other = '', $flags = 0)
    {
        if (!is_callable($func_link))
            return false;

        $query = $this->QC_Simple_Select ($table, $fields, $where, $other, $flags);
        if ($result = $this->SQL_Query($query, true))
        {
            $ret = 0;

            while ($res = $this->SQL_FetchRow($result))
            {
                $ret++;
                if (call_user_func($func_link, $res))
                    break;
            }

            if (QF_KERNEL_SQL_EXPLAINS && ($result = $this->SQL_Query('EXPLAIN '.$query, true)))
            {
                $explains = Array();
                while ($res = $this->SQL_FetchRow($result))
                    $explains[] = $res;

                $this->SQL_FreeResult($result);
                $file = md5($query).'.exp';
                fwrite($this->logfile, $query."\n\n".qf_array_definition($explains)."\n\n");
            }

            $this->SQL_FreeResult($result);

            return $ret;
        }
        else
            return false;

    }

    // complex multitable select
    function Do_Multitable_Select ($tqueries, $other = '', $flags = 0)
    {
        $ret = Array();
        $query = $this->QC_Multitable_Select ($tqueries, $other, $flags);
        if ($result = $this->SQL_Query($query, true))
        {
            if ($flags & QF_SQL_SELECTALL)
            {
                $ret = Array();
                if ($this->SQL_NumFields() == 1)
                    while (list($res) = $this->SQL_FetchRow($result, false))
                        $ret[] = $res;
                else
                    while ($res = $this->SQL_FetchRow($result))
                        $ret[] = $res;
            }
            elseif ($this->SQL_NumFields() == 1)
                list($ret) = $this->SQL_FetchRow($result, false);
            else
                $ret = $this->SQL_FetchRow($result);

            $this->SQL_FreeResult($result);

            if (QF_KERNEL_SQL_EXPLAINS && ($result = $this->SQL_Query('EXPLAIN '.$query, true)))
            {
                $explains = Array();
                while ($res = $this->SQL_FetchRow($result))
                    $explains[] = $res;

                $this->SQL_FreeResult($result);
                $file = md5($query).'.exp';
                fwrite($this->logfile, $query."\n\n".qf_array_definition($explains)."\n\n");
            }

            return $ret;
        }
        else
            return false;

    }
    // insert function - please use this one instead of direct query? 'cause this will correctly escape the strings
    function Do_Insert ($table, $data = Array(), $replace = false, $flags = 0)
    {
        $query = ($replace) ? 'REPLACE INTO ' : 'INSERT INTO ';

        if ($this->auto_prefix && !($flags & QF_SQL_NOPREFIX))
            $table = $this->tbl_prefix.$table;

        $query.= '`'.$table.'` ';

        if (is_array($data) && count($data)) {
            $names = $vals = Array();
            foreach ($data AS $field=>$val)
            {
                $names[] = '`'.$field.'`';
                if (is_scalar($val))
                {
                    if (is_bool($val))
                        $val = (int) $val;
                    elseif (is_string($val))
                    {
                        if (!($flags & QF_SQL_NOESCAPE) && !is_numeric($val))
                            $val = $this->_Escape_String($val);
                        $val = '"'.$val.'"';
                    }
                    else
                        $val = (string) $val;

                    $vals[] = $val;
                }
                elseif (is_null($val))
                    $vals[] = 'NULL';
                else
                    $vals[] = '""';
            }

            $query.='('.implode(', ', $names).') VALUES ('.implode(', ', $vals).')';

            if ($this->SQL_Query($query, true))
            {
                if ($NID = $this->SQL_ThisId())
                    return $NID;
                else
                    return true;
            }
            else
                return false;
        }
        else
            return false;
    }

    // replace function
    function Do_Replace ($table, $data = Array(), $flags = 0)
    {
         return $this->Do_Insert($table, $data, true, $flags);
    }

    // update function - please use this one instead of direct query? 'cause this will correctly escape the strings
    function Do_Update ($table, $data = Array(), $where = '', $flags = false)
    {
        $where = $this->_Where_Parse($where, $flags);

        if ($this->auto_prefix && !($flags & QF_SQL_NOPREFIX))
            $table = $this->tbl_prefix.$table;

        $query = 'UPDATE `'.$table.'` SET ';

        if (is_array($data) && count($data)) {
            $names = $vals = Array();
            foreach ($data AS $field=>$val)
            {
                if (($flags & QF_SQL_USEFUNCS) && $part = $this->_Parse_FieldFunc($field, $val, false))
                    $fields[] = $part;
                elseif (is_scalar($val))
                {
                    $names[] = '`'.$field.'`';

                    if (is_bool($val))
                        $val = (int) $val;
                    elseif (is_string($val))
                    {
                        if (!($flags & QF_SQL_NOESCAPE) && !is_numeric($val))
                            $val = $this->_Escape_String($val);
                        $val = '"'.$val.'"';
                    }
                    else
                        $val = (string) $val;

                    $fields[] = '`'.$field.'` = '.$val;
                }
                elseif (is_null($val))
                    $fields[] = '`'.$field.'` = NULL';
            }
            $query.= implode(', ', $fields);
            $query.= ' '.$where;

            if ($this->SQL_Query($query, true))
                return $this->SQL_AffectedRows(); // || true
            else
                return false;
        }
        else
            return false;
    }

    // update function - please use this one instead of direct query? 'cause this will correctly escape the strings
    function Do_Delete ($table, $where = '', $flags = false)
    {
        $where = $this->_Where_Parse($where, $flags);

        if ($this->auto_prefix && !($flags & QF_SQL_NOPREFIX))
            $table = $this->tbl_prefix.$table;

        $query = 'DELETE FROM `'.$table.'` '.$where;

        if ($this->SQL_Query($query, true))
            return $this->SQL_AffectedRows();
        else
            return false;
    }

    // sql methods

    // mysqli_result objects cannot be used directly as array keys.
    // Use a stable string key derived from the object instead.
    function _res_key($query_id)
    {
        if (is_object($query_id))
            return spl_object_hash($query_id);
        return strval($query_id);
    }

    // Base direct query method
    function SQL_Query ($query = '', $noprefixrepl = false)
    {
        if (!$this->db_connect_id)
        {
            trigger_error('MySQL: DB Server is not connected - cant perform queries', ($this->do_warnings) ? E_USER_WARNING : E_USER_ERROR);
            return false;
        }

        if( empty($query) )
            return false;

        $stime = explode(' ',microtime());
        $start_time=$stime[1]+$stime[0];

        unset($this->query_result);

        if ($this->auto_prefix && !$noprefixrepl)
            $query = preg_replace('#(?<=\W|^)(`?)\{DBKEY\}(\w+)(\\1)(?=\s|$|\n|\r)#s', '`'.$this->tbl_prefix.'$2`', $query);

        //mysqli_ping($this->db_connect_id);
        $this->query_result = mysqli_query($this->db_connect_id, $query);

        // if server is 'gone away' we'll reconnect and repeat the query
        if (mysqli_errno($this->db_connect_id) == 2006 && $this->_Do_Connect())
            $this->query_result = mysqli_query($this->db_connect_id, $query);

        if( $this->query_result )
        {
            $res_key = $this->_res_key($this->query_result);
            unset($this->row[$res_key]);
            unset($this->rowset[$res_key]);
        }
        else
        {
            $this->query_result = false;
            if ($error['code'] = mysqli_errno($this->db_connect_id))
            {
                $error['message'] = mysqli_error($this->db_connect_id);
                $this->Fast_DbCheck('', true);
                trigger_error('MYSQL error '.$error['code'].': '.$error['message'].' in '.$query, ($this->do_warnings) ? E_USER_WARNING : E_USER_ERROR);
            }
        }

        $stime = explode(' ',microtime());
        $stop_time = $stime[1]+$stime[0];
        $query_time = $stop_time - $start_time;

        $this->num_queries++;
        $this->queries_time += $query_time;
        $this->history[] = Array('query' => $query, 'time' => $query_time);

        return $this->query_result;
    }

    function SQL_DBQuery($database, $query, $noprefix = false )
    {
        if (!$this->db_connect_id)
        {
            trigger_error('MySQL: DB Server is not connected - cant perform queries', ($this->do_warnings) ? E_USER_WARNING : E_USER_ERROR);
            return false;
        }

        $result = false;

        $old_db = $this->database;
        if (mysqli_select_db($this->db_connect_id, $database) )
        {
            $this->database = $database;
            if ($this->auto_dbcheck)
                $this->Fast_DbCheck($database);

            $result = $this->SQL_Query($query, $noprefix );

            $this->database = $old_db;
        }

        mysqli_select_db($this->db_connect_id, $old_db);

        return $result;
    }

    // Other query methods
    function SQL_NumRows ($query_id = 0)
    {
        if( !$query_id )
            $query_id = $this->query_result;

        return ( $query_id ) ? mysqli_num_rows($query_id) : false;
    }

    function SQL_AffectedRows ()
    {
        return ( $this->db_connect_id ) ? mysqli_affected_rows($this->db_connect_id) : false;
    }

    function SQL_NumFields ($query_id = 0)
    {
        if( !$query_id )
            $query_id = $this->query_result;

        return ( $query_id ) ? mysqli_num_fields($query_id) : false;
    }

    function SQL_FieldName($offset, $query_id = 0)
    {
        if( !$query_id )
            $query_id = $this->query_result;

        if( !$query_id )
            return false;

        $field = mysqli_fetch_field_direct($query_id, $offset);
        return ( $field ) ? $field->name : false;
    }

    function SQL_FieldType($offset, $query_id = 0)
    {
        if( !$query_id )
            $query_id = $this->query_result;

        if( !$query_id )
            return false;

        return $this->_Field_Type($query_id, $offset);
    }

    function SQL_FetchRow($query_id = 0, $assoc=true)
    {
        $style=($assoc) ? MYSQLI_ASSOC : MYSQLI_NUM ;

        if( !$query_id )
            $query_id = $this->query_result;

        if( $query_id )
        {
            $res_key = $this->_res_key($query_id);
            $this->row[$res_key] = mysqli_fetch_array($query_id, $style);
            return $this->row[$res_key];
        }
        else
            return false;
    }

    function SQL_FetchRowset ($query_id = 0, $field_name = '')
    {
        if( !$query_id )
            $query_id = $this->query_result;

        if( $query_id )
        {
            $res_key = $this->_res_key($query_id);
            unset($this->rowset[$res_key]);
            unset($this->row[$res_key]);

            while($this->rowset[$res_key] = mysqli_fetch_array($query_id, MYSQLI_ASSOC))
            {
                if ($field_name) $result[$this->rowset[$res_key][$field_name]] = $this->rowset[$res_key];
                else $result[] = $this->rowset[$res_key];
            }

            return $result;
        }
        else
            return false;
    }

    function SQL_FetchField($field, $rownum = -1, $query_id = 0)
    {
        if( !$query_id )
            $query_id = $this->query_result;

        if( $query_id )
        {
            $res_key = $this->_res_key($query_id);
            if( $rownum > -1 )
            {
                mysqli_data_seek($query_id, $rownum);
                $row = mysqli_fetch_array($query_id, MYSQLI_BOTH);
                $result = $row[$field];
            }
            else
            {
                if( empty($this->row[$res_key]) && empty($this->rowset[$res_key]) )
                {
                    if( $this->sql_fetchrow() )
                    {
                        $result = $this->row[$res_key][$field];
                    }
                }
                else
                {
                    if( $this->rowset[$res_key] )
                    {
                        $result = $this->rowset[$res_key][0][$field];
                    }
                    else if( $this->row[$res_key] )
                    {
                        $result = $this->row[$res_key][$field];
                    }
                }
            }

            return $result;
        }
        else
            return false;
    }

    function SQL_RowSeek($rownum, $query_id = 0)
    {
        if( !$query_id )
            $query_id = $this->query_result;

        return ( $query_id ) ? mysqli_data_seek($query_id, $rownum) : false;
    }

    function SQL_ThisId()
    {
        return ( $this->db_connect_id ) ? mysqli_insert_id($this->db_connect_id) : false;
    }

    function SQL_FreeResult($query_id = 0)
    {
        if( !$query_id )
            $query_id = $this->query_result;

        if ( $query_id )
        {
            $res_key = $this->_res_key($query_id);
            unset($this->row[$res_key]);
            unset($this->rowset[$res_key]);

            mysqli_free_result($query_id);

            return true;
        }
        else
            return false;
    }

    // server methods
    function SQL_Info()
    {
        return ($this->db_connect_id) ? mysqli_info($this->db_connect_id) : false;
    }

    function SQL_Error()
    {
        if( !$this->db_connect_id )
            return false;

        $result['message'] = mysqli_error($this->db_connect_id);
        $result['code'] = mysqli_errno($this->db_connect_id);

        return $result;
    }

    function Srv_Info()
    {
        return ($this->db_connect_id) ? 'MySQL. Version '.mysqli_get_server_info($this->db_connect_id) : 'Unconnected';
    }


    // Private functions

    // actually connects to database
    function _Do_Connect($nusername = false, $npassword = false)
    {
        static $username = '', $password = '';
        // we'll store username and password for possible reconnections
        // but we store this info in inner static variables
        if ($nusername) $username = $nusername;
        if ($npassword) $password = $npassword;

        if ( $this->db_connect_id )
            mysqli_close($this->db_connect_id);

        $server = ($this->persistency)
                  ? 'p:'.$this->server
                  : $this->server;

        $this->db_connect_id = mysqli_connect($server, $username, $password);

        if( $this->db_connect_id )
        {
            mysqli_query($this->db_connect_id, 'SET NAMES '.$this->codepage);

            if( $this->database )
            {
                $dbselect = mysqli_select_db($this->db_connect_id, $this->database);

                if( !$dbselect )
                {
                    mysqli_close($this->db_connect_id);
                    $this->db_connect_id = $dbselect;
                }
                $this->cur_dbname = $this->database;
            }

            return $this->db_connect_id;
        }
        else
            return ($this->db_connect_id = false);
    }

    // constructs simple WHERE with AND/OR construction
    function _Where_Parse ($where, $flags = 0, $tbl_pref = '')
    {
        if (empty($where))
            return '';

        if (is_array($where))
        {
            $parts = Array();
            foreach ($where AS $field=>$val) {
                $field = '`'.$field.'`';
                if ($tbl_pref)
                    $field = $tbl_pref.'.'.$field;

                if (($flags & QF_SQL_USEFUNCS) && ($part = $this->_Parse_FieldFunc($field, $val, true)))
                    $parts[] = $part;
                elseif (is_scalar($val))
                {
                    if (is_bool($val))
                        $val = (int) $val;
                    elseif (is_string($val))
                    {
                        if (!($flags & QF_SQL_NOESCAPE) && !is_numeric($val))
                            $val = $this->_Escape_String($val);
                        $val = '"'.$val.'"';
                    }
                    else
                        $val = (string) $val;

                    $parts[] = $field.' = '.$val;
                }
                elseif (is_array($val) && count($val))
                {
                    $nvals = Array();
                    foreach ($val as $id => $sub)
                    {
                        if (is_bool($sub))
                            $sub = (int) $sub;
                        elseif (is_string($sub))
                        {
                            if (!($flags & QF_SQL_NOESCAPE) && !is_numeric($sub))
                                $sub = $this->_Escape_String($sub);
                            $sub = '"'.$sub.'"';
                        }
                        elseif (is_null($sub))
                            $sub = 'NULL';

                        if (is_scalar($sub))
                            $nvals[$id] = $sub;
                    }

                    if (count($nvals))
                        $parts[] = $field.' IN ('.implode(', ', $nvals).')';
                }
                elseif (is_null($val))
                    $parts[] = $field.' = NULL';
            }
            if (count($parts))
                return 'WHERE '.implode(($flags & QF_SQL_WHERE_OR) ? ' OR ' : ' AND ', $parts);
            else
                return 'WHERE false';
        }
        elseif (empty($tbl_pref))
        {
            $where = trim(strval($where));
            if (!preg_match('#^WHERE\s#i', $where))
                $where = 'WHERE '.$where;
            return $where;
        }
        else
            return '';
    }

    // constructs simple ORDER and LIMIT
    function _Parse_Other($other, $flags = 0, $tbl_pref = '')
    {
        if (empty($other))
            return '';

        if (is_array($other))
        {
            $parts = Array();

            if (isset($other['order']))
            {
                $order = $other['order'];
                if (is_array($order))
                {
                    $order_by = Array();
                    foreach($order as $fkey => $fname)
                    {
                        if (is_int($fkey))
                            $field = '`'.$fname.'` ASC';
                        else
                        {
                            $type = strtolower($fname);
                            $field = '`'.$fkey.'`'.(($type == 'desc' || $type == '-1') ? ' DESC' : ' ASC');
                        }
                        if ($tbl_pref)
                            $field = $tbl_pref.'.'.$field;
                        $order_by[] = $field;
                    }
                    if (count($order_by))
                        $parts[] = 'ORDER BY '.implode(', ', $order_by);
                }
                elseif (empty($tbl_pref))
                    $parts[] = 'ORDER BY '.$order;
            }

            if (isset($other['limit']))
            {
                $limit = $other['limit'];
                if (!is_array($limit))
                    $limit = preg_split('#\D#', $limit, -1, PREG_SPLIT_NO_EMPTY);
                $limit = array_slice($limit, 0, 2);
                $lim_count = (int) array_pop($limit);
                $lim_first = (int) array_pop($limit);

                if ($lim_count > 0)
                    $parts[] = 'LIMIT '.$lim_first.', '.$lim_count;
            }

            if (count($parts))
                return implode(' ', $parts);
            else
                return '';
        }
        elseif (empty($tbl_pref))
        {
            $other = trim(strval($other));
            return $other;
        }
        else
            return '';
    }

    function _Parse_FieldFunc($field, $data, $is_compare = false)
    {
        static $set_funcs = Array(
            '++' => '%1$s = %1$s + %2$s',
            '--' => '%1$s = %1$s - %2$s',
            );

        static $cmp_funcs = Array(
            '<'  => '%1$s < %2$s',  '<=' => '%1$s <= %2$s',
            '>'  => '%1$s > %2$s',  '>=' => '%1$s >= %2$s',
            '<>' => '%1$s <> %2$s', '!=' => '%1$s != %2$s',
            'LIKE' => '%1$s LIKE %2$s',
            );

        if (!is_string($data))
            return false;

        $funcs_set = ($is_compare) ? $cmp_funcs : $set_funcs;
        $expr = explode(' ', $data, 2);
        if (count($expr) == 2)
        {

            if (isset($funcs_set[$expr[0]]))
            {
                $val = $expr[1];
                if (is_bool($val))
                    $val = (int) $val;
                elseif (is_string($val))
                    {
                        if (!is_numeric($val))
                            $val = $this->_Escape_String($val);
                        $val = '"'.$val.'"';
                    }
                else
                    $val = '"'.$val.'"';

                $out = sprintf($funcs_set[$expr[0]], $field, $val);
                return $out;
            }
            else
                return false;
        }
        else
            return false;
    }

    function _Field_Type($query_id, $offset)
    {
        $field = mysqli_fetch_field_direct($query_id, $offset);
        if (!$field) return false;

        switch ($field->type) {
            case MYSQLI_TYPE_TINY:
            case MYSQLI_TYPE_SHORT:
            case MYSQLI_TYPE_LONG:
            case MYSQLI_TYPE_LONGLONG:
            case MYSQLI_TYPE_INT24:
                return 'int';
            case MYSQLI_TYPE_FLOAT:
            case MYSQLI_TYPE_DOUBLE:
            case MYSQLI_TYPE_DECIMAL:
            case MYSQLI_TYPE_NEWDECIMAL:
                return 'real';
            case MYSQLI_TYPE_TIMESTAMP:
                return 'timestamp';
            case MYSQLI_TYPE_DATE:
            case MYSQLI_TYPE_NEWDATE:
                return 'date';
            case MYSQLI_TYPE_TIME:
                return 'time';
            case MYSQLI_TYPE_YEAR:
                return 'year';
            case MYSQLI_TYPE_DATETIME:
                return 'datetime';
            case MYSQLI_TYPE_ENUM:
                return 'enum';
            case MYSQLI_TYPE_SET:
                return 'set';
            case MYSQLI_TYPE_TINY_BLOB:
            case MYSQLI_TYPE_MEDIUM_BLOB:
            case MYSQLI_TYPE_LONG_BLOB:
            case MYSQLI_TYPE_BLOB:
                return 'blob';
            case MYSQLI_TYPE_NULL:
                return 'null';
            case MYSQLI_TYPE_VAR_STRING:
            case MYSQLI_TYPE_STRING:
            case MYSQLI_TYPE_GEOMETRY:
            default:
                return 'string';
        }
    }

    function _Escape_String($string) // in PHP 4.3.0 there is new better func
    {
        if (!strlen($string) || !$this->db_connect_id)
            return $string;

        return mysqli_real_escape_string($this->db_connect_id, $string);
    }

    // Maintenance functions
    function Optimize($dbase = '')
    {
        static $Got_Optimized = Array();

        if (!$dbase)
            $dbase = $this->database;

        if (isset($Got_Optimized[$dbase]))
            return $Got_Optimized[$dbase];

        if (!($result = mysqli_query($this->db_connect_id, 'SHOW TABLES FROM '.$dbase)))
            return ($Got_Optimized[$dbase] = false);

        $tbls = Array();
        while (list($tbl) = mysqli_fetch_array($result, MYSQLI_NUM))
            $tbls[] = '`'.$tbl.'`';
        mysqli_free_result($result);

        if (count($tbls) == 0)
            return ($Got_Optimized[$dbase] = true);

        $query = 'OPTIMIZE TABLE '.implode(', ', $tbls);
        if (!($result = mysqli_query($this->db_connect_id, $query)))
            {
                trigger_error('MYSQL FastCheck: Query error while optimizing: '.mysqli_error($this->db_connect_id), E_USER_ERROR);
                return ($Got_Optimized[$dbase] = false);
            }

        mysqli_free_result($result);
        return ($Got_Optimized[$dbase] = true);
    }

    function Fast_DbCheck($dbase = '', $no_quick = false)
    {
        static $Got_Checked = Array();

        if (!$dbase)
            $dbase = $this->database;

        if (isset($Got_Checked[$dbase]))
            return $Got_Checked[$dbase];

        if (!($result = mysqli_query($this->db_connect_id, 'SHOW TABLES FROM '.$dbase)))
            return ($Got_Checked[$dbase] = false);

        $tbls = Array();
        while (list($tbl) = mysqli_fetch_array($result, MYSQLI_NUM))
            $tbls[] = '`'.$tbl.'`';
        mysqli_free_result($result);

        if (count($tbls) == 0)
            return ($Got_Checked[$dbase] = true);

        $query = 'CHECK TABLE '.implode(', ', $tbls).(($no_quick) ? '' : ' QUICK');
        if (!($result = mysqli_query($this->db_connect_id, $query)))
            {
                trigger_error('MYSQL FastCheck: Query error while checking: '.mysqli_error($this->db_connect_id), E_USER_ERROR);
                return ($Got_Checked[$dbase] = false);
            }

        $tbls = Array();
        while ($tbl = mysqli_fetch_array($result, MYSQLI_ASSOC))
            if ($tbl['Msg_type'] == 'error')
            {
                trigger_error('MYSQL FastCheck: Table "'.$tbl['Table'].'" is corrupted: '.$tbl['Msg_text'], E_USER_WARNING);
                $tbls[] = str_replace($dbase.'.', $dbase.'.`', $tbl['Table']).'`';
            }
        mysqli_free_result($result);
        if (count($tbls) == 0)
            return ($Got_Checked[$dbase] = true);

        $query = 'REPAIR TABLE '.implode(', ', array_unique($tbls)).' EXTENDED';
        if (!($result = mysqli_query($this->db_connect_id, $query)))
            {
                trigger_error('MYSQL FastCheck: Query error while repeiring: '.mysqli_error($this->db_connect_id), E_USER_ERROR);
                return ($Got_Checked[$dbase] = false);
            }

        while ($tbl = mysqli_fetch_array($result, MYSQLI_ASSOC))
            if ($tbl['Msg_type'] == 'error')
            {
                trigger_error('MYSQL FastCheck: Table "'.$tbl['Table'].'" is corrupted and was not fixed automatically: '.$tbl['Msg_text'], E_USER_ERROR);
                return ($Got_Checked[$dbase] = false);
            }

        mysqli_free_result($result);
        return ($Got_Checked[$dbase] = true);
    }

} // class sql_db

?>