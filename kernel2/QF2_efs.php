<?php

// -------------------------------------------------------------------------- \\
// QuickFox kernel 2 Extended File System lib                                 \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if ( defined('QF_KERNEL_EFS_LOADED') )
        die('Scripting error');

define('QF_KERNEL_EFS_LOADED', True);

// defining some usefull constants
// EFS stream types
define('QF_EFS_WRITE', 1);
define('QF_EFS_GZIP' , 2);
define('QF_EFS_GZIP_TRY', 4);

class QF_EFS
{    var $streams = Array();

    function QF_EFS()
    {        $this->streams = Array();
    }

    function Open($filename, $Try_mode = 0, $GZ_try_ext = '.gz')
    {        static $stream_id = 0;
        $new_mode = 0;
        $new_stream = null;

        if ($Try_mode & QF_EFS_WRITE)
        {            $new_mode |= QF_EFS_WRITE;

            if ($Try_mode & QF_EFS_GZIP_TRY)
            {
                if (extension_loaded('zlib'))
                {
                    $filename.= $GZ_try_ext;
                    $new_mode |= QF_EFS_GZIP;
                }
            }
            elseif ($Try_mode & QF_EFS_GZIP)
            {                if (extension_loaded('zlib'))
                    $new_mode |= QF_EFS_GZIP;
                else
                {
                    trigger_error('EFS: can\'t start GZIP stream', E_USER_NOTICE);
                    return false;
                }
            }
            qf_mkdir_recursive(dirname($filename));

            if ($new_mode & QF_EFS_GZIP)
                $new_stream = gzopen($filename, 'wb9');
            else
                $new_stream = fopen($filename, 'wb');
        }
        else
        {            if (!file_exists($filename))
            {                trigger_error('EFS: file '.$filename.' does not exist', E_USER_NOTICE);
                return false;
            }

            if ($Try_mode & QF_EFS_GZIP_TRY)
            {
                if (preg_match('#'.preg_quote($GZ_try_ext, '#').'$#i', $filename))
                {
                    $new_mode |= QF_EFS_GZIP;
                }
            }
            elseif ($Try_mode & QF_EFS_GZIP)
            {
                if (extension_loaded('zlib'))
                    $new_mode |= QF_EFS_GZIP;
                else
                {
                    trigger_error('EFS: can\'t start GZIP stream', E_USER_NOTICE);
                    return false;
                }
            }

            if ($new_mode & QF_EFS_GZIP && extension_loaded('zlib'))
                $new_stream = gzopen($filename, 'rb');
            else
                $new_stream = fopen($filename, 'rb');
        }

        if ($new_stream)
        {            $stream_id++;
            $this->streams[$stream_id] = Array($new_stream, $new_mode, $filename);
            return $stream_id;
        }
        else
        {
            trigger_error('EFS: error opening '.$filename, E_USER_NOTICE);
            return false;
        }
    }

    function Write($stream_id, $data, $length = null)
    {        if (!isset($this->streams[$stream_id]))
            return false;

        list($stream, $mode) = $this->streams[$stream_id];

        if (!($mode & QF_EFS_WRITE))
        {            trigger_error('EFS: trying to write to stream opened for reading', E_USER_WARNING);
            return false;
        }

        if (!is_scalar($data))
        {
            trigger_error('EFS: trying to write non scalar data', E_USER_WARNING);
            return false;
        }

        $length = (int) $length;
        if ($length <= 0)
            $length = strlen($data);

        if ($mode & QF_EFS_GZIP)
            return gzwrite($stream, $data, $length);
        else
            return fwrite($stream, $data, $length);
    }

    function Read($stream_id, $length = 256)
    {
        if (!isset($this->streams[$stream_id]))
            return false;

        $length = (int) $length;

        if ($length<=0)
            return '';

        list($stream, $mode) = $this->streams[$stream_id];

        if ($mode & QF_EFS_WRITE)
        {
            trigger_error('EFS: trying to read from stream opened for writing', E_USER_WARNING);
            return false;
        }

        if ($mode & QF_EFS_GZIP)
            return gzread($stream, $length);
        else
            return fread($stream, $length);
    }

    function Get_Cont($stream_id)
    {        if (!isset($this->streams[$stream_id]))
            return false;

        list($stream, $mode) = $this->streams[$stream_id];

        if ($mode & QF_EFS_WRITE)
        {
            trigger_error('EFS: trying to read from stream opened for writing', E_USER_WARNING);
            return false;
        }

        if (!(($mode & QF_EFS_GZIP) ? gzrewind($stream) : rewind($stream)))
            return false;

        $eof = ($mode & QF_EFS_GZIP) ? gzeof($stream) : feof($stream);
        $data = '';

        while (!$eof)
        {
            if ($mode & QF_EFS_GZIP)
                $data.= gzread($stream, 10240);
            else
                $data.= fread($stream, 10240);

            $eof = ($mode & QF_EFS_GZIP) ? gzeof($stream) : feof($stream);
        }

        if ($mode & QF_EFS_GZIP)
            gzrewind($stream);
        else
            rewind($stream);

        return $data;
    }

    function EOF($stream_id)
    {        if (!isset($this->streams[$stream_id]))
            return false;

        list($stream, $mode) = $this->streams[$stream_id];

        if ($mode & QF_EFS_GZIP)
            return gzeof($stream);
        else
            return feof($stream);
    }

    function Pos($stream_id)
    {
        if (!isset($this->streams[$stream_id]))
            return false;

        list($stream, $mode) = $this->streams[$stream_id];

        if ($mode & QF_EFS_GZIP)
            return gztell($stream);
        else
            return ftell($stream);
    }

    function Restart($stream_id)
    {
        if (!isset($this->streams[$stream_id]))
            return false;

        list($stream, $mode) = $this->streams[$stream_id];

        if ($mode & QF_EFS_GZIP)
            return gzrewind($stream);
        else
            return rewind($stream);
    }

    function Seek($stream_id, $offset = 0)
    {
        if (!isset($this->streams[$stream_id]))
            return false;

        $offset = (int) $offset;

        if ($offset<0)
            return false;

        list($stream, $mode) = $this->streams[$stream_id];

        $curoff = ($mode & QF_EFS_GZIP) ? gztell($stream) : ftell($stream);

        if ($offset < $curoff)
        {            if ($mode & QF_EFS_GZIP)
                gzrewind($stream);
            else
                rewind($stream);
        }
        elseif ($offset = $curoff)
            return true;

        if ($offset)
        {
            if ($mode & QF_EFS_GZIP)
                return (gzseek($stream, $offset) == 0);
            else
                return (fseek($stream, $offset) == 0);
        }
    }

    function SeekRel($stream_id, $offset = 0)
    {
        if (!isset($this->streams[$stream_id]))
            return false;

        $offset = (int) $offset;

        if ($offset<=0)
            return false;

        list($stream, $mode) = $this->streams[$stream_id];

        $curoff = ($mode & QF_EFS_GZIP) ? gztell($stream) : ftell($stream);

        if ($mode & QF_EFS_GZIP)
        {
            //gzrewind($stream);
            return (gzseek($stream, $curoff + $offset) == 0);
        }
        else
        {
            //rewind($stream);
            return (fseek($stream, $curoff + $offset) == 0);
        }
    }

    function Close($stream_id)
    {
        if (!isset($this->streams[$stream_id]))
            return false;

        list($stream, $mode) = $this->streams[$stream_id];

        if ($mode & QF_EFS_GZIP)
            $res = gzclose($stream);
        else
            $res = fclose($stream);

        if ($res)
            unset($this->streams[$stream_id]);

        return $res;
    }

    function CheckMode($stream_id)
    {        if (!isset($this->streams[$stream_id]))
            return false;

        list($stream, $mode) = $this->streams[$stream_id];

        return $mode;
    }

    function CheckName($stream_id)
    {
        if (!isset($this->streams[$stream_id]))
            return false;

        list($stream, $mode, $filename) = $this->streams[$stream_id];

        return $filename;
    }

    function ParseCallback($stream_id, $func, $by_length = 10240)
    {        if (!isset($this->streams[$stream_id]))
            return false;

        $by_length = (int) $by_length;

        if ($by_length<=0)
            return '';

        if (!is_callable($func))
            return false;

        list($stream, $mode) = $this->streams[$stream_id];

        if ($mode & QF_EFS_WRITE)
        {
            trigger_error('EFS: trying to read from stream opened for writing', E_USER_WARNING);
            return false;
        }

        if (!(($mode & QF_EFS_GZIP) ? gzrewind($stream) : rewind($stream)))
            return false;

        $eof = ($mode & QF_EFS_GZIP) ? gzeof($stream) : feof($stream);

        while (!$eof)
        {            if ($mode & QF_EFS_GZIP)
                $data_block = gzread($stream, $by_length);
            else
                $data_block = fread($stream, $by_length);

            $eof = ($mode & QF_EFS_GZIP) ? gzeof($stream) : feof($stream);

            call_user_func_array($func, Array($data_block, &$eof));
        }

        if ($mode & QF_EFS_GZIP)
            gzrewind($stream);
        else
            rewind($stream);

        return true;

    }
}

?>