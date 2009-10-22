<?php

// -------------------------------------------------------------------------- \\
// QuickFox kernel 2 TAR filesystem lib                                       \\
// -------------------------------------------------------------------------- \\


// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if ( defined('QF_KERNEL_TARLIB_LOADED') )
        die('Scripting error');

define('QF_KERNEL_TARLIB_LOADED', True);

define('QF_TARFS_BLOCKS_BUF', 10240);
define('QF_TARFS_MAX_PARSETIME', 30); // 30 secs to get file contents

class QF_TARLib
{
    var $files = Array();

    function QF_TARLib()
    {

    }

    function _Start()
    {
        global $QF;
        $this->TARs = Array();
        $QF->Run_Module('EFS');
    }

    function OpenTAR($filename, $write_mode = false, $TGZ_try = false)
    {
        global $QF;
        static $TAR_id = 0;
        $new_mode = 0;
        $file_gz = preg_match('#(\.tgz|\.tar\.gz)$#', $filename);

        if ($write_mode)
        {
            $mode = QF_EFS_WRITE;
            if ($file_gz)
            {
                if (extension_loaded('zlib'))
                    $mode |= QF_EFS_GZIP;
                else
                {
                    trigger_error('TARLIB: can\'t start GZIP stream', E_USER_NOTICE);
                    return false;
                }
            }
            elseif($TGZ_try)
            {
                if (extension_loaded('zlib'))
                {
                    if (!$file_gz)
                        $filename = preg_replace('#(.tar)$#', '.tar.gz', $filename);
                    $mode |= QF_EFS_GZIP;
                }
                elseif ($file_gz)
                    $filename = preg_replace('#(.tgz|.tar.gz)$#', '.tar', $filename);
            }

            $new_stream = $QF->EFS->Open($filename, $mode);
            $new_file = Array(
                'TARfile'   => $filename,
                'TARstream' => $new_stream,
                'is_pack'   => true,
                'TARcont'   => Array(),
                'root_link' => false,
                );
        }
        else
        {
            $mode = 0;
            if (!file_exists($filename))
            {
                trigger_error('TARLIB: file '.$filename.' does not exist', E_USER_NOTICE);
                return false;
            }

            if ($file_gz || $TGZ_try)
            {
                if (extension_loaded('zlib'))
                    $mode |= QF_EFS_GZIP;
                else
                {
                    trigger_error('TARLIB: can\'t start GZIP stream', E_USER_NOTICE);
                    return false;
                }
            }

            $new_stream = $QF->EFS->Open($filename, $mode);
            $new_file = Array(
                'TARfile'   => $filename,
                'TARstream' => $new_stream,
                'is_pack'   => false,
                'TARcont'   => false,
                'root_link' => false,
                );
        }

        if ($new_stream)
        {
            $TAR_id++;
            $this->TARs[$TAR_id] =& $new_file;
            if (!$write_mode)
                $this->ParseContent($TAR_id);
            return $TAR_id;
        }
        else
        {
            trigger_error('TARLIB: error opening '.$filename, E_USER_NOTICE);
            return false;
        }
    }

    function CloseTAR($TAR_id)
    {
        global $QF;

        if (!isset($this->TARs[$TAR_id]))
            return false;

        extract($this->TARs[$TAR_id]);
        if ($is_pack)
            $QF->EFS->Write($TARstream, str_repeat("\x00", 512));
        $res = $QF->EFS->Close($TARstream);

        if ($res)
            unset($this->TARs[$TAR_id]);

        return $res;
    }

    // TAR pack functions
    // Sets real directory to be a root of pack filetree
    function SetRootLink($TAR_id, $dir)
    {
        if (!isset($this->TARs[$TAR_id]))
            return false;

        if (is_file($dir))
            return false;
        elseif (!file_exists($dir) && $is_pack)
            return false;

        $this->TARs[$TAR_id]['root_link'] = qf_str_path($dir);
        return true;
    }

    // Packs a real file to archive
    function PackFile($TAR_id, $filename, $pack_to = '', $force_mode = '')
    {
        global $QF;

        if (!isset($this->TARs[$TAR_id]))
            return false;
        if (!is_file($filename))
            return false;

        extract($this->TARs[$TAR_id]);
        $TARcont =& $this->TARs[$TAR_id]['TARcont'];

        if (!$is_pack)
        {
            trigger_error('TARLIB: trying to pack data into archive opened for reading', E_USER_WARNING);
            return false;
        }

        if (!$pack_to)
        {
            $pack_to = $filename;
            $pack_to = preg_replace('#^('.preg_quote($root_link, '#').')#', '', $pack_to);
        }

        $pack_to = qf_str_path($pack_to);

        if (in_array($pack_to, $TARcont))
            return false;

        if ($dir = qf_str_path(dirname($pack_to)))
            if (!in_array($dir, $TARcont))
            {
                $dir_perms = decoct(fileperms(dirname($filename)));
                $this->MakeDir($TAR_id, $dir, $dir_perms);
            }

        $filesize = filesize($filename);
        $header = Array(
            'name'  => $pack_to,
            'mode'  => decoct(fileperms($filename)),
            'uid'   => fileowner($filename),
            'gid'   => filegroup($filename),
            'size'  => $filesize,
            'time'  => filemtime($filename),
            'type'  => 0,
            );

        if (preg_match('#^[0-7]{3}$#', $force_mode))
            $header['mode'] = $force_mode;

        $header = $this->MakeRawHeader($header);

        if ($inp = fopen($filename, 'rb'))
        {
            $QF->EFS->Write($TARstream, $header);
            $bls_toread = (int) ceil($filesize/512);
            While (!feof($inp) && $bls_toread>0)
            {
                $bls_read = min($bls_toread, QF_TARFS_BLOCKS_BUF);
                $data_read = $bls_read*512;
                $data = fread($inp, $data_read);
                $data = str_pad($data, $data_read, chr(0), STR_PAD_RIGHT);
                $QF->EFS->Write($TARstream, $data);
                $bls_toread-= $bls_read;
            }
            fclose($inp);
        }

        $TARcont[] = $pack_to;
        return True;
    }

    // Packs a datastring as file
    function PackData($TAR_id, $inp, $pack_to = '', $force_mode = '')
    {
        global $QF;
        static $packd_id = 1;

        if (!isset($this->TARs[$TAR_id]))
            return false;
        if (!strlen($inp))
            return false;

        extract($this->TARs[$TAR_id]);
        $TARcont =& $this->TARs[$TAR_id]['TARcont'];

        if (!$is_pack)
        {
            trigger_error('TARLIB: trying to pack data into archive opened for reading', E_USER_WARNING);
            return false;
        }

        if (!$pack_to)
            $pack_to = 'data_'.($packd_id++).'.bin';
        else
            $pack_to = qf_str_path($pack_to);

        if (in_array($pack_to, $TARcont))
            return false;

        if ($dir = qf_str_path(dirname($pack_to)))
            if (!in_array($dir, $TARcont))
                $this->MakeDir($TAR_id, $dir);

        $filesize = strlen($inp);
        $header = Array(
            'name'  => $pack_to,
            'mode'  => '644',
            'uid'   => fileowner(__FILE__),
            'gid'   => filegroup(__FILE__),
            'size'  => $filesize,
            'time'  => time(),
            'type'  => 0,
            );

        if (preg_match('#^[0-7]{3}$#', $force_mode))
            $header['mode'] = $force_mode;

        $header = $this->MakeRawHeader($header);

        $QF->EFS->Write($TARstream, $header);

        $start = 0;
        $datasize = (int) ceil($filesize/512)*512;
        $QF->EFS->Write($TARstream, $inp);
        if ($datasize > $filesize)
            $QF->EFS->Write($TARstream, str_repeat(chr(0), ($datasize - $filesize)));

        $TARcont[] = $pack_to;
        return True;
    }

    // Packs a dir with contents
    function PackDir($TAR_id, $dirname, $pack_to = '', $force_mode = '', $force_Fmode = '', $ch_callback=false)
    {
        global $QF;

        if (!isset($this->TARs[$TAR_id]))
            return false;
        if (!is_dir($dirname))
            return false;
        if (!($odir = opendir($dirname)))
            return false;

        extract($this->TARs[$TAR_id]);
        $TARcont =& $this->TARs[$TAR_id]['TARcont'];

        if (!$is_pack)
        {
            trigger_error('TARLIB: trying to pack data into archive opened for reading', E_USER_WARNING);
            return false;
        }

        if (!$pack_to)
        {
            $pack_to = $dirname;
            $pack_to = preg_replace('#^('.preg_quote($root_link, '#').')#', '', $pack_to);
        }

        $pack_to = qf_str_path($pack_to);

        if (!in_array($pack_to, $TARcont))
        {
            $dir_perms = decoct(fileperms($dirname));
            if (preg_match('#^[0-7]{3}$#', $force_mode))
                $dir_perms = $force_mode;

            if ($dir = qf_str_path(dirname($pack_to)))
                if (!in_array($dir, $TARcont))
                    $this->MakeDir($TAR_id, $dir, $dir_perms);

            $header = Array(
                'name'  => $pack_to,
                'mode'  => $dir_perms,
                'uid'   => fileowner($dirname),
                'gid'   => filegroup($dirname),
                'size'  => 0,
                'time'  => filemtime($dirname),
                'type'  => 5,
                );

            $header = $this->MakeRawHeader($header);
            $QF->EFS->Write($TARstream, $header);
            $TARcont[] = $pack_to;
        }

        if (!is_callable($ch_callback))
            $ch_callback = null;

        while ($dfile=readdir($odir))
            if ($dfile!='.' && $dfile!='..')
            {
                $ffile = $dirname.'/'.$dfile;
                if (is_dir($ffile))
                    $this->PackDir($TAR_id, $ffile, $pack_to.'/'.$dfile, $force_mode, $force_Fmode, $ch_callback);
                elseif ($ch_callback)
                {
                    if (call_user_func($ch_callback, $ffile))
                        $this->PackFile($TAR_id, $ffile, $pack_to.'/'.$dfile, $force_Fmode);
                }
                else
                    $this->PackFile($TAR_id, $ffile, $pack_to.'/'.$dfile, $force_Fmode);
            }

        closedir($odir);

        return True;
    }

    // Makes an empty directory inside archive
    function MakeDir($TAR_id, $dirname, $force_mode = '')
    {
        global $QF;
        static $packd_id = 1;

        if (!isset($this->TARs[$TAR_id]))
            return false;
        if (!$dirname)
            return false;

        extract($this->TARs[$TAR_id]);
        $TARcont =& $this->TARs[$TAR_id]['TARcont'];

        if (!$is_pack)
        {
            trigger_error('TARLIB: trying to make dir in archive opened for reading', E_USER_WARNING);
            return false;
        }

        $dirname = qf_str_path($dirname);

        if (in_array($dirname, $TARcont))
            return true;

        if ($dir = qf_str_path(dirname($dirname)))
            if (!in_array($dir, $TARcont))
                $this->MakeDir($TAR_id, $dir, $force_mode);

        $header = Array(
            'name'  => $dirname,
            'mode'  => '755',
            'uid'   => fileowner(__FILE__),
            'gid'   => filegroup(__FILE__),
            'size'  => 0,
            'time'  => time(),
            'type'  => 5,
            );

        if (preg_match('#^[0-7]{3}$#', $force_mode))
            $header['mode'] = $force_mode;

        $header = $this->MakeRawHeader($header);

        $QF->EFS->Write($TARstream, $header);

        $TARcont[] = $dirname;
        return true;
    }

    // Unpacker functions
    // Walks trought the archive and searches for it's contents
    function ParseContent($TAR_id, $force = false)
    {
        global $QF;
        static $packd_id = 1;

        if (!isset($this->TARs[$TAR_id]))
            return false;

        extract($this->TARs[$TAR_id]);

        if ($is_pack)
        {
            trigger_error('TARLIB: trying to parse content for archive opened for writing', E_USER_WARNING);
            return false;
        }

        if (is_array($TARcont) && !$force)
            return $TARcont;

        $stoptime = time() + QF_TARFS_MAX_PARSETIME; //some archives includes too many files so we need to watch the time not to spent lots of it
        $QF->EFS->Restart($TARstream);
        $i = 0;
        while (!$QF->EFS->EOF($TARstream))
        {
            $header = $QF->EFS->Read($TARstream, 512);
            if ($header = $this->ParseRawHeader($header))
            {
                $i++;
                $pos  = $QF->EFS->Pos($TARstream);
                $size = $header['size'];
                $seek = ceil($size/512)*512;
                $QF->EFS->SeekRel($TARstream, $seek);
                $TARcont[$i] = $header + Array('seek' => $pos);
            }
            else
                break;

            if (($i%10 == 0) && (time() > $stoptime))
            {
                trigger_error('TARLIB: contents list parsing got too much time', E_USER_NOTICE);
                break;
            }

        }

        $this->TARs[$TAR_id]['TARcont'] = $TARcont;

        return $TARcont;
    }

    // unpacks archived data to file
    function UnpackFile($TAR_id, $fid, $unpack_to = '', $do_replace = false, $force_mode = '')
    {
        global $QF;

        if (!isset($this->TARs[$TAR_id]))
            return false;

        if (!$fid)
            return false;

        extract($this->TARs[$TAR_id]);

        if ($is_pack)
        {
            trigger_error('TARLIB: trying to unpack data from archive opened for writing', E_USER_WARNING);
            return false;
        }

        if (!isset($TARcont[$fid]))
            return false;

        $header = $TARcont[$fid];
        $filename = $header['name'];

        if (!$unpack_to)
            $unpack_to = $root_link.'/'.$filename;

        $unpack_to = qf_str_path($unpack_to);

        if (file_exists($unpack_to) && !$do_replace)
            return false;
        if ($dir = qf_str_path(dirname($unpack_to)))
            qf_mkdir_recursive($dir);

        $QF->EFS->Seek($TARstream, $header['seek']);

        if (preg_match('#^[0-7]{3}$#', $force_mode))
            $header['mode'] = $force_mode;

        $to_read = $header['size'];
        $type = $header['type'];
        if ($type == 0)
        {
            if ($outp = @fopen($unpack_to, 'wb'))
            {
                $bls_toread = (int) ceil($to_read/512);
                While (!$QF->EFS->EOF($TARstream) && $to_read>0)
                {
                    $bls_read = min($bls_toread, QF_TARFS_BLOCKS_BUF);
                    $data_read = $bls_read*512;
                    $data = $QF->EFS->Read($TARstream, $data_read);
                    if ($to_read < $data_read)
                        $data = substr($data, 0, $to_read);
                    fwrite($outp, $data);

                    $bls_toread-= $bls_read;
                    $to_read -= $data_read;
                }
                fclose($outp);
                if ($fmode = octdec($header['mode']))
                    chmod($unpack_to, $fmode);
                if ($ftime = $header['time'])
                    touch($unpack_to, $ftime);

                return true;
            }
        }
        elseif ($type == 5)
        {
            if (!($fmode = octdec($header['mode'])))
                $fmode = 0755;
            return mkdir($unpack_to, $fmode);
        }

        return false;
    }

    // unpacks archived data to datastring
    function UnpackData($TAR_id, $fid)
    {
        global $QF;

        if (!isset($this->TARs[$TAR_id]))
            return false;

        if (!$fid)
            return false;

        extract($this->TARs[$TAR_id]);

        if ($is_pack)
        {
            trigger_error('TARLIB: trying to unpack data from archive opened for writing', E_USER_WARNING);
            return false;
        }

        if (!isset($TARcont[$fid]))
            return false;

        $header = $TARcont[$fid];

        $QF->EFS->Seek($TARstream, $header['seek']);

        $to_read = $header['size'];
        $type = $header['type'];
        if ($type == 0)
        {
            $datastring = '';
            $bls_toread = (int) ceil($to_read/512);
            While (!$QF->EFS->EOF($TARstream) && $to_read>0)
            {
                $bls_read = min($bls_toread, QF_TARFS_BLOCKS_BUF);
                $data_read = $bls_read*512;
                $data = $QF->EFS->Read($TARstream, $data_read);
                if ($to_read < $data_read)
                    $data = substr($data, 0, $to_read);

                $datastring.= $data;
                $bls_toread-= $bls_read;
                $to_read -= $data_read;
            }
            return $datastring;
        }

        return null;
    }

    // eatracts all the erchive data to directory
    function Extract($TAR_id, $unpack_to = '', $force_Dmode = '', $force_Fmode = '', $ch_callback=false)
    {
        global $QF;

        if (!isset($this->TARs[$TAR_id]))
            return false;

        extract($this->TARs[$TAR_id]);

        if ($is_pack)
        {
            trigger_error('TARLIB: trying to unpack data from archive opened for writing', E_USER_WARNING);
            return false;
        }

        if (!$unpack_to)
            $unpack_to = $root_link;

        $unpack_to = qf_str_path($unpack_to);

        if (is_file($unpack_to))
            return false;

        $do_replace = false;

        if (!is_callable($ch_callback))
        {
            if ($ch_callback)
                $do_replace = true;
            $ch_callback = null;
        }

        qf_mkdir_recursive($unpack_to, octdec($force_Dmode));

        $QF->EFS->Restart($TARstream);
        $i = 0;
        while (!$QF->EFS->EOF($TARstream))
        {
            $header = $QF->EFS->Read($TARstream, 512);
            if ($header = $this->ParseRawHeader($header))
            {
                $i++;
                $pos  = $QF->EFS->Pos($TARstream);
                $TARcont[$i] = $header + Array('seek' => $pos);

                $filename = $unpack_to.'/'.$header['name'];
                $to_read = $header['size'];
                $bls_toread = (int) ceil($to_read/512);
                $type = $header['type'];

                $do_file = ($ch_callback) ? call_user_func($ch_callback, $filename) : (!file_exists($filename) || $do_replace);

                if ($do_file)
                {
                    if ($dir = qf_str_path(dirname($filename)))
                        qf_mkdir_recursive($dir);

                    if ($type == 0)
                    {
                        if (preg_match('#^[0-7]{3}$#', $force_Fmode))
                            $header['mode'] = $force_Fmode;

                        if ($outp = @fopen($filename, 'wb'))
                        {
                            While (!$QF->EFS->EOF($TARstream) && $to_read>0)
                            {
                                $bls_read = min($bls_toread, QF_TARFS_BLOCKS_BUF);
                                $data_read = $bls_read*512;
                                $data = $QF->EFS->Read($TARstream, $data_read);
                                if ($to_read < $data_read)
                                    $data = substr($data, 0, $to_read);
                                fwrite($outp, $data);

                                $bls_toread-= $bls_read;
                                $to_read -= $data_read;
                            }
                            fclose($outp);
                            if ($fmode = octdec($header['mode']))
                                chmod($filename, $fmode);
                            if ($ftime = $header['time'])
                                touch($filename, $ftime);
                        }
                    }
                    elseif ($type == 5)
                    {
                        if (preg_match('#^[0-7]{3}$#', $force_Dmode))
                            $header['mode'] = $force_Dmode;

                        if (!($fmode = octdec($header['mode'])))
                            $fmode = 0755;
                        qf_mkdir_recursive($filename, $fmode);
                    }

                }
                if ($bls_toread)
                    $QF->EFS->SeekRel($TARstream, $bls_toread*512);
            }
        }

        $this->TARs[$TAR_id]['TARcont'] = $TARcont;

        return true;
    }

    // finds a file id by name
    function FindFile($TAR_id, $filename, $fullpath = false)
    {
        global $QF;

        if (!isset($this->TARs[$TAR_id]))
            return false;

        extract($this->TARs[$TAR_id]);

        if ($is_pack)
        {
            trigger_error('TARLIB: trying to search within archive opened for writing', E_USER_WARNING);
            return false;
        }

        $found = 0;

        foreach ($TARcont as $fid => $file)
        {
            $file = $file['name'];
            $file = qf_str_path($file);
            if ($file == $filename)
            {
                $found = $fid + 1;
                break;
            }
            elseif (!$fullpath)
            {
                $file = qf_basename($file);
                if ($file == $filename)
                {
                    $found = $fid + 1;
                    break;
                }
            }
        }

        return $found;
    }


    function MakeRawHeader($header)
    {
        static $h_fields = Array(
            'name' => '', 'mode' => '', 'uid'   => '', 'gid'  => '',
            'size' => '', 'time' => '', 'chsum' => '', 'type' => '',
            'linkname' => '', 'magic' => 'ustar  ');

        $header += $h_fields;
        if (!$header['name'])
            return false;

        $header['name']  = qf_fix_string($header['name'], 100, "\x00", STR_PAD_RIGHT);
        $header['mode']  = qf_fix_string(preg_replace('#[^0-7]#', '', $header['mode']), 6 , '0', STR_PAD_LEFT)." \x00";
        $header['uid']   = qf_fix_string(preg_replace('#[^0-7]#', '', decoct($header['uid']) ), 6 , "0", STR_PAD_LEFT)." \x00";
        $header['gid']   = qf_fix_string(preg_replace('#[^0-7]#', '', decoct($header['gid']) ), 6 , "0", STR_PAD_LEFT)." \x00";
        $header['size']  = qf_fix_string(preg_replace('#[^0-7]#', '', decoct($header['size'])), 11, '0', STR_PAD_LEFT)." ";
        $header['time']  = qf_fix_string(preg_replace('#[^0-7]#', '', decoct($header['time'])), 11, '0', STR_PAD_LEFT)." ";
        $header['chsum'] = str_repeat(' ', 8);
        $header['type']  = ($header['type']==5) ? 5 : 0;
        $header['linkname']  = qf_fix_string($header['linkname'], 100, "\x00", STR_PAD_RIGHT);
        $header['magic']     = qf_fix_string($header['magic'], 8, "\x00", STR_PAD_RIGHT);;

        $csumm = 0;
        foreach (array_keys($h_fields) as $key)
        {
            $val = $header[$key];
            $len = strlen($val);
            for ($i=0; $i<$len; $i++)
                $csumm += ord(substr($val, $i, 1));
        }
        $header['chsum'] = qf_fix_string(decoct($csumm), 6, '0', STR_PAD_LEFT)." \x00";

        $rawheader = '';
        foreach (array_keys($h_fields) as $key)
            $rawheader.= $header[$key];
        $rawheader = qf_fix_string($rawheader, 512, chr(0), STR_PAD_RIGHT);

        return $rawheader;
    }

    function ParseRawHeader($rawheader)
    {
        static $h_fields = Array(
            'name' => '', 'mode' => '', 'uid'   => '', 'gid'  => '',
            'size' => '', 'time' => '', 'chsum' => '', 'type' => '',
            'linkname' => '', 'magic' => 'ustar  ');

        $header['name']  = trim(substr($rawheader, 0, 100));
        $header['mode']  = preg_replace('#(^0+|[^0-7])#', '', substr($rawheader, 100, 8 ));
        $header['uid']   = octdec(preg_replace('#(^0+|[^0-7])#', '', substr($rawheader, 108, 8 )));
        $header['gid']   = octdec(preg_replace('#(^0+|[^0-7])#', '', substr($rawheader, 116, 8 )));
        $header['size']  = octdec(preg_replace('#(^0+|[^0-7])#', '', substr($rawheader, 124, 12)));
        $header['time']  = octdec(preg_replace('#(^0+|[^0-7])#', '', substr($rawheader, 136, 12)));
        $rawchsum = substr($rawheader, 148, 8 );
        $header['chsum'] = preg_replace('#(^0+|[^0-7])#', '', $rawchsum);
        $header['type']  = preg_replace('#[^0-5]#', '0', substr($rawheader, 156, 1 ));
        $header['linkname']  = trim(substr($rawheader, 157, 100));
        $header['magic']     = trim(substr($rawheader, 257, 8));

        $mycsum = 0;
        $len = strlen($rawheader);
        for ($i=0; $i<$len; $i++)
            $mycsum += ord(substr($rawheader, $i, 1));

        for ($i=0; $i<8; $i++)
            $mycsum += (32 - ord(substr($rawchsum, $i, 1)));

        $chsum = octdec($header['chsum']);

        if ($chsum !== $mycsum)
            return false;
        else
            return $header;
    }
}

?>
