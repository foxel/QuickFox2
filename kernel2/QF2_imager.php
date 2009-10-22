<?php

// ---------------------------------------------------------------------- \\
// Image processing module for kernel 2                                   \\
// ---------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
    die('Hacking attempt');


if ( defined('QF_KERNEL_IMAGER_LOADED') )
    die('Scripting error');

define('QF_KERNEL_IMAGER_LOADED', True);

// +analysing GD
if (function_exists('imagetypes') && ((imagetypes() & 0x7) != 0)) // check if GD can parse any of GIF|JPEG|PNG formats
{
    if (PHP_VERSION >= '4.3.0' && ($gdinfo = @gd_info()))
    {
        $gdver = $gdinfo['GD Version'];
        if (preg_match('#(bundled)?\W+([2-9]\.\d+\.\d+)#', $gdver, $gdver))
        {
            define('PHP_GD_VERSION', $gdver[2]);
            define('QF_IMAGER_USE_GD', true);
            define('QF_IMAGER_GIF_WRITE', $gdinfo['GIF Create Support']);
        }
    }
    else
    {
        if (function_exists('imagecreatetruecolor') &&
            function_exists('imageistruecolor') &&
            function_exists('imagesavealpha') &&
            function_exists('imagetruecolortopalette') &&
            function_exists('imagecopyresampled')) // stupid checkup :)
        {
            define('QF_IMAGER_USE_GD', true);
            define('QF_IMAGER_GIF_WRITE', (bool) (imagetypes() & IMG_GIF)); // even more stupid :)
        }
        else
            define('QF_IMAGER_USE_GD', false);
    }
}
else
    define('QF_IMAGER_USE_GD', false);
// -analysing GD


// action constants
define('QF_IMAGER_DO_RESIZE'   ,   1);
define('QF_IMAGER_DO_PALETTIZE',   2);
define('QF_IMAGER_DO_DEPALETTIZE', 4);
define('QF_IMAGER_DO_DROPALPHA',   8);
define('QF_IMAGER_DO_WATERMARK',  16);
define('QF_IMAGER_DO_DROPANIM',   32); // GD allways drops
define('QF_IMAGER_DO_STRIP',     256); // strips comments and other info (on GD it's allways stripped)

define('QF_IMAGER_WMARK_CENTER', 1);
define('QF_IMAGER_WMARK_RIGHT',  2);
define('QF_IMAGER_WMARK_MIDDLE', 4);
define('QF_IMAGER_WMARK_BOTTOM',  8);

define('QF_IMAGER_RSIZE_FORCE', 0); // force to stretch image nonproportionally
define('QF_IMAGER_RSIZE_FIT',   1); // new image is not exactly of given size
define('QF_IMAGER_RSIZE_BRDR',  2); // fit and add borders
define('QF_IMAGER_RSIZE_TBRDR', 3); // fit and add transparent borders


class QF_Imager
{

    var $IM_Bin = 'convert';
    var $IM_Acc = false;
    var $images = Array();

    function QF_Imager()
    {
        global $QF;

        if (!QF_IMAGER_USE_GD)
            trigger_error('Kernel->Imager: GD2 is not accessable', E_USER_WARNING);

        $this->IM_Bin = 'convert';
        if (($im_path = $QF->Config->Get('IM_BinPath', 'imager')) && is_dir($im_path))
            $this->IM_Bin = rtrim($im_path, '/').'/'.$this->IM_Bin;

        if (is_null($this->IM_Acc = $QF->Config->Get('IM_Accessable', 'imager')))
        {
			$check = qf_exec(Array($this->IM_Bin, '-version'));
			$check = $check[1];
			$this->IM_Acc = (bool) preg_match('#Version: ImageMagick 6\.\d\.\d#', $check);
			$QF->Config->Set('IM_Accessable', $this->IM_Acc, 'imager', true);
        }

        $this->def_params = Array(
            'back_col'   => 0xFFFFFF, 'resample' => true,
            'force_type' => 0, 'width' => 0, 'height' => 0, 'rsize_mode' => QF_IMAGER_RSIZE_FORCE,
            'rsize_brdr' => 0, 'alpha_br' => 10, 'p_colors' => 256, 'wmark_x' => 0,
            'wmark_y' => 0, 'wmark_pl' => 0, 'wmark_al' => 0,
            'wmark_img' => '', 'wmark_scl' => 0,
            );

        $this->def_exts = Array(
            IMAGETYPE_GIF  => 'gif',  IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',  IMAGETYPE_SWF  => 'swf',
            IMAGETYPE_PSD  => 'psd',  IMAGETYPE_BMP  => 'bmp',
            IMAGETYPE_WBMP => 'wbmp', IMAGETYPE_XBM  => 'xbm',
            IMAGETYPE_TIFF_II => 'tiff', IMAGETYPE_TIFF_MM => 'tiff',
            IMAGETYPE_IFF => 'iff', IMAGETYPE_JB2 => 'jb2',
            IMAGETYPE_JPC => 'jpc', IMAGETYPE_JP2 => 'jp2',
            IMAGETYPE_JPX => 'jpx', IMAGETYPE_SWC => 'swc',
            );

        $this->use_IM = (QF_IMAGER_USE_GD) ? false : $this->IM_Acc;
        $this->GIF_IM = false; // use IMagick for GIF->GIF operations

        if ($this->IM_Acc && !is_null($im_conf = $QF->Config->Get('IM_Usage', 'imager')))
        {            if ($im_conf < 0)
                $this->GIF_IM = $this->use_IM = $this->IM_Acc = false; // totally blocked
            elseif ($im_conf == 1)
                $this->GIF_IM = true; // only for GIF->GIF
            elseif ($im_conf > 1)
                $this->use_IM = true; // use IM
            else
                $this->GIF_IM = $this->use_IM = false; // dont use
        }
    }

    // returns list of flags or bool if $flag specifyed
    function Check($flag = false)
    {
        static $flags = false;
        if (!QF_IMAGER_USE_GD)
            return false;

        if (!$flags)
            $flags = Array(
                'GIFw' => (imagetypes() & IMG_GIF) && QF_IMAGER_GIF_WRITE,
                'GIF' => (bool) (imagetypes() & IMG_GIF), // gd information
                'JPG' => (bool) (imagetypes() & IMG_JPG),
                'PNG' => (bool) (imagetypes() & IMG_PNG),
                );

        if (isset($flags[$flag]))
            return $flags[$flag];
        else
            return $flags;
    }
// ------------------ NEW PROCESING FUNCTIONS -------------- \\

    function LoadImage($filename)
    {        if (!($from_info = getimagesize($file)))
            return false;

        $from_type = $from_info[2];

        $new_img = Array(
            'f' => $filename,
            'w' => $from_info[0],
            'h' => $from_info[1],
            );

        $id = false;
        if (($this->use_IM && false)
            || (($from_type == IMAGETYPE_GIF) && $this->GIF_IM && false))
        {            $id = count($this->images)+1;
            $this->images[$id] = Array(0, $filename, 0, $new_img);
        }
        elseif ($img = $this->_gd_load($filename))
        {            $id = count($this->images)+1;
            $this->images[$id] = Array(1, $filename, &$img, $new_img);
        }

        return $id;
    }


// ---------------------- INTERNALS --------------------\\

    function _im_check($filename)
    {
    }

// ---------------------- CURRENT ONES ------------------- \\

    function Configure($params, $im_conf = null)
    {
        if (is_array($params))
            $this->def_params = $params + $this->def_params;

        if ($im_conf === 'G')
            $this->GIF_IM = $this->IM_Acc;
        elseif ($im_conf)
            $this->use_IM = $this->IM_Acc;
        elseif (!is_null($im_conf))
        {
            $this->use_IM = false;
            $this->GIF_IM = false;
        }

        return true;
    }

    function Check_IType($file) // returns data like getimagesize() but only if the image might be processed by the module
    {
        if (!file_exists($file))
            return false;

        $data = getimagesize($file);
        if (!$data || !$data[2])
            return false;

        $from_type = $data[2];

        $data['def_ext'] = (isset($this->def_exts[$from_type])) ? $this->def_exts[$from_type] : '';
        $i_can = false;

        if (QF_IMAGER_USE_GD)
            switch ($from_type) // needs to be reviewed
            {
                case IMAGETYPE_GIF:
                    if (imagetypes() & IMG_GIF)
                    {
                        $data['mime'] = 'image/gif';
                        $i_can = true;
                    }
                    break;
                case IMAGETYPE_JPEG:
                    if (imagetypes() & IMG_JPG)
                    {
                        $data['mime'] = 'image/jpeg';
                        $i_can = true;
                    }
                    break;
                case IMAGETYPE_PNG:
                    if (imagetypes() & IMG_PNG)
                    {
                        $data['mime'] = 'image/png';
                        $i_can = true;
                    }
                    break;
            }

        if (!$i_can && $this->IM_Acc)
        {
            $comm = Array($this->IM_Bin, $file, '-format', ':%wx%h:image/%m', 'info:-');
            $mystring = ':'.$data[0].'x'.$data[1].':image/';
            if ((list($st, $out) = qf_exec($comm))
                && $st && preg_match('#^'.preg_quote($mystring, '#').'([\w\-]+)#', $out, $out1))
            {
                $data['mime'] = 'image/'.strtolower($out1[1]);
                $i_can = true;
            }
        }

        return ($i_can) ? $data : false;
    }

    function Parse_Image($file, $to_file, $to_type, $flags = 0, $params = Array())
    {
        if (!($from_info = getimagesize($file)))
            return false;

        if (!is_array($params))
            $params = $this->def_params;
        else
            $params += $this->def_params;

        $done = false;
        if ($this->use_IM || ($this->GIF_IM && $from_info[2] == IMAGETYPE_GIF && $to_type == IMAGETYPE_GIF))
            $done = $this->_im_parse_image($file, $to_file, $to_type, $flags, $params);

        if (!$done && QF_IMAGER_USE_GD) // if there is GD2 module linked
            $done = $this->_gd_parse_image($file, $to_file, $to_type, $flags, $params);

        if (!$done && !$this->use_IM && $this->IM_Acc) // if GD2 module failed to process
            $done = $this->_im_parse_image($file, $to_file, $to_type, $flags, $params);

        return $done;
    }

    function _im_parse_image($file, $to_file, $to_type, $flags = 0, $params = Array())
    {
        global $QF;
        $postwork = $cmd = Array();
        $keep_anim = $has_no_alpha = false; // some inner behaviour flags
        $IM_bin = 'convert';

        if (!($from_info = getimagesize($file)))
            return false;

        if (!is_array($params))
            $params = $this->def_params;
        else
            $params += $this->def_params;

        // required actions for output formats
        switch ($to_type)
        {
            case IMAGETYPE_GIF:
                $flags |= QF_IMAGER_DO_PALETTIZE;
                $out_format = 'gif';
                $params['resample'] = false;
                break;
            case IMAGETYPE_JPEG:
                $flags |= QF_IMAGER_DO_DROPALPHA | QF_IMAGER_DO_DEPALETTIZE;
                $out_format = 'jpeg';
                break;
            case IMAGETYPE_PNG:
                $out_format = ($flags & QF_IMAGER_DO_PALETTIZE) ? 'png8' : 'png';
                break;
            default: // all the other formats will be converted to jpeg
                $flags |= QF_IMAGER_DO_DROPALPHA | QF_IMAGER_DO_DEPALETTIZE;
                $out_format = 'jpeg';
                //return false;
        }

        if ($flags & QF_IMAGER_DO_STRIP)
            $cmd[] = '-strip';
        $from_type = $from_info[2];
        $iw = $from_info[0];
        $ih = $from_info[1];

        // some input type optimisations
        if ($from_type == IMAGETYPE_GIF)
        {
            $cmd[] = '-coalesce';
            if (($to_type == IMAGETYPE_GIF) && !($flags & QF_IMAGER_DO_DROPANIM))
            {
                //$postwork[] = '-layers|optimize';
                //$postwork[] = '-deconstruct';
                $keep_anim = true;
            }
            else
                $file.='[0]';
                //$cmd[] = '-composite';

        }
        if ($from_type == IMAGETYPE_JPEG) // JPEG does not contain transparency
        {
            $params['alpha_br'] = 0;
            $has_no_alpha = true;
            $flags &= ~QF_IMAGER_DO_DROPALPHA;

        }
        elseif ($flags & QF_IMAGER_DO_DROPALPHA)
            $params['alpha_br'] = 0;

        // resizing
        if (($flags & QF_IMAGER_DO_RESIZE) && ($params['width'] > 0) && ($params['height'] > 0))
        {
            if (!$params['resample'])
                $cmd[] = '-filter|point';

            $rs_mode = $params['rsize_mode'];
            if ($rs_mode == QF_IMAGER_RSIZE_FIT)
            {
                if (($params['width'] < $iw) || ($params['height'] < $ih)) // pic does not fit
                {
                    $scl = min($params['width']/$iw, $params['height']/$ih);
                    $iw = round($scl*$iw);
                    $ih = round($scl*$ih);
                    $cmd[] = '-resize|'.$iw.'x'.$ih.'!';
                }
            }
            elseif (($rs_mode == QF_IMAGER_RSIZE_BRDR) || ($rs_mode == QF_IMAGER_RSIZE_TBRDR))
            {
                $back_rgb = (int) $params['back_col'] & 0xFFFFFF;
                if ($rs_mode == QF_IMAGER_RSIZE_TBRDR)
                {
                    $back_r = ($back_rgb >> 16) & 0xFF;
                    $back_g = ($back_rgb >> 8) & 0xFF;
                    $back_b = $back_rgb & 0xFF;
                    $back_rgb = 'rgba('.$back_r.','.$back_g.','.$back_b.',0)';
                    $postwork[] = '+background';
                    $params['alpha_br'] = 1;
                    $has_no_alpha = false;
                }
                else
                    $back_rgb = '#'.str_pad(strtoupper(dechex($back_rgb)), 6, '0', STR_PAD_LEFT);


                $brdr_wdth = $params['rsize_brdr'];
                $brdr_wdth = (int) min($brdr_wdth, $params['width']/4, $params['height']/4);
                $putw = (int) ($params['width']) - 2*$brdr_wdth;
                $puth = (int) ($params['height']) - 2*$brdr_wdth;

                if (($putw < $iw) || ($puth < $ih)) // pic fits the size allready
                {
                    $scl = min($params['width']/$iw, $params['height']/$ih);
                    $iw = round($scl*$iw) - 2*$brdr_wdth;
                    $ih = round($scl*$ih) - 2*$brdr_wdth;
                    $cmd[] = '-resize|'.$iw.'x'.$ih.'!';
                }
                $iw = $params['width'];
                $ih = $params['height'];
                $cmd[] = '-background|'.$back_rgb.'|-gravity|center|-extent|'.$iw.'x'.$ih.'!';

                if ($rs_mode == QF_IMAGER_RSIZE_BRDR)
                    $flags |= QF_IMAGER_DO_DROPALPHA;
            }
            else
            {
                $iw = (int) $params['width'];
                $ih = (int) $params['height'];
                $cmd[] = '-resize|'.$iw.'x'.$ih.'!';
            }
        }

        // watermark placing
        if (($flags & QF_IMAGER_DO_WATERMARK) && $params['wmark_img'] && ($w_info = getimagesize($params['wmark_img'])))
        {
            $ww = $w_info[0];
            $wh = $w_info[1];
            $cmd[] = '(';
            $cmd[] = $params['wmark_img'];
            if (($wa = (float) $params['wmark_scl']) && ($wa != 1))
            {
                $cmd[] = '-resize|'.round($wa*100, 1).'%';
                $ww = $ww*$wa;
                $wh = $wh*$wa;
            }

            $wx = (int) $params['wmark_x'];
            $wy = (int) $params['wmark_y'];
            $wp = (int) $params['wmark_pl'];
            if ($wp & QF_IMAGER_WMARK_CENTER)
                $wx = (int) (($iw - $ww)/2 + $wx);
            elseif ($wp & QF_IMAGER_WMARK_RIGHT)
                $wx = (int) ($iw - $ww - $wx);
            if ($wp & QF_IMAGER_WMARK_MIDDLE)
                $wy = (int) (($ih - $wh)/2 + $wy);
            elseif ($wp & QF_IMAGER_WMARK_BOTTOM)
                $wy = (int) ($ih - $wh - $wy);

            //$wx = ($wx >= 0) ? '+'.$wx : $wx;
            //$wy = ($wy >= 0) ? '+'.$wy : $wy;
            //$cmd[] = '-geometry|'.$wx.$wy;

            if ($params['wmark_al'])
            {
                $alpha = round((127 - ($params['wmark_al'] & 0x7F))/127, 3);
                $cmd[] = '-channel|A|-fx|u*'.$alpha;
            }

            $cmd[] = '-write|mpr:wmark|+delete|)';
            $cmd[] = '-gravity|NorthWest|-draw';
            $cmd[] = 'image over '.$wx.','.$wy.' 0,0 \'mpr:wmark\'';

        }

        // palettizing/alpha-drop
        if (($flags & QF_IMAGER_DO_PALETTIZE) && ($colors = (int) $params['p_colors']) && ($colors >= 4) && ($colors <= 256))
        {
            $back_rgb = str_pad(strtoupper(dechex((int) $params['back_col'] & 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
            $alpha = (int) $params['alpha_br'] & 0x7F;
            if ($alpha > 0)
            {
                $alpha = round((127 - $alpha)/1.27, 1);
                if (!$keep_anim)
                {
                    $cmd[] = '(|+clone|-channel|A|-threshold|'.$alpha.'%|-channel|RGB|-fx|#'.$back_rgb.'|-write|mpr:back|+delete|)';
                    $cmd[] = '-gravity|NorthWest|-draw|image dst-over 0,0 0,0 \'mpr:back\'';
                }
                $cmd[] = '-channel|A|-threshold|'.$alpha.'%|+channel';
                //$postwork[] = '-transparent-color|#'.$back_rgb;
                $colors -= 1;
            }
            elseif (!$has_no_alpha)
            {
                $cmd[] = '(|-size|'.$iw.'x'.$ih.'|xc:#'.$back_rgb.'|-write|mpr:back|+delete|)';
                $cmd[] = '-draw|image dst-over 0,0 0,0 \'mpr:back\'';
            }
            $postwork[] = '-colors|'.$colors;
        }
        elseif ($flags & QF_IMAGER_DO_DROPALPHA)
        {
            $back_rgb = str_pad(strtoupper(dechex((int) $params['back_col'] & 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
            $cmd[] = '(|-size|'.$iw.'x'.$ih.'|xc:#'.$back_rgb.'|-write|mpr:back|+delete|)';
            $cmd[] = '-draw|image dst-over 0,0 0,0 \'mpr:back\'';
        }


        $cmd = array_merge(Array($this->IM_Bin, $file), $cmd, $postwork);

        $cmd[] = $out_format.':'.$to_file;

        $ocmd = Array();
        foreach($cmd as $part)
            $ocmd = array_merge($ocmd, explode('|', $part));

        list($status) = qf_exec($ocmd);
        if (!$status)
			$QF->Config->Set('IM_Accessable', null, 'imager', true);

        return $status;
    }

    function _gd_parse_image($file, $to_file, $to_type, $flags = 0, $params = Array())
    {
        // GD parsing starts here
        if (!QF_IMAGER_USE_GD) // if there is no GD2 module linked
            return false;
        $error = false;

        if (!($from_type = getimagesize($file)))
            return false;
        $from_type = $from_type[2];


        if (!is_array($params))
            $params = $this->def_params;
        else
            $params += $this->def_params;

        // required actions for output formats
        switch ($to_type)
        {
            case IMAGETYPE_GIF:
                if (!(imagetypes() & IMG_GIF) || !QF_IMAGER_GIF_WRITE)
                    return false;
                $flags |= QF_IMAGER_DO_PALETTIZE;
                //$params['resample'] = false;
                break;
            case IMAGETYPE_JPEG:
                if (!(imagetypes() & IMG_JPG))
                    return false;
                $flags |= QF_IMAGER_DO_DROPALPHA | QF_IMAGER_DO_DEPALETTIZE;
                $flags &= ~QF_IMAGER_DO_PALETTIZE;
                break;
            case IMAGETYPE_PNG:
                if (!(imagetypes() & IMG_PNG))
                    return false;
                break;
            default:
                return false;
        }

        if ($img = $this->_gd_load($file))
        {
            // transparency options check
            if ($from_type == IMAGETYPE_JPEG) // JPEG does not contain transparency
            {
                if ($params['rsize_mode'] != QF_IMAGER_RSIZE_TBRDR)
                    $params['alpha_br'] = 0;
                $flags &= ~QF_IMAGER_DO_DROPALPHA;
            }
            elseif ($flags & QF_IMAGER_DO_DROPALPHA)
                $params['alpha_br'] = 0;

            // resizing optimization
            if (!imageistruecolor($img) && ($flags & QF_IMAGER_DO_RESIZE) && !($flags & QF_IMAGER_DO_DEPALETTIZE))
                $flags |= QF_IMAGER_DO_DEPALETTIZE | QF_IMAGER_DO_PALETTIZE;

            // depalettizing
            if (!imageistruecolor($img) && ($flags & QF_IMAGER_DO_DEPALETTIZE))
                if ($this->_gd_depalettize($img, ($flags & QF_IMAGER_DO_DROPALPHA), $params['back_col']))
                    $flags &= ~QF_IMAGER_DO_DROPALPHA;

            // resizing
            if ($flags & QF_IMAGER_DO_RESIZE)
            {
                if ($this->_gd_resize($img, $params['width'], $params['height'], $params['resample'], $params['rsize_mode'], $params['rsize_brdr'], $params['back_col'], ($flags & QF_IMAGER_DO_DROPALPHA)))
                    $flags &= ~QF_IMAGER_DO_DROPALPHA;
                else
                {
                    imagedestroy($img);
                    return false;
                }
            }

            // watermark placing
            if (($flags & QF_IMAGER_DO_WATERMARK) && $params['wmark_img'] && ($wimg = $this->_gd_load($params['wmark_img'])))
            {
                $ww = imagesx($wimg);
                $wh = imagesy($wimg);
                if (($wa = (float) $params['wmark_scl']) && ($wa != 1))
                {
                    $this->_gd_resize($wimg, $wa*$ww, $wa*$wh, $params['resample']);
                    $ww = imagesx($wimg);
                    $wh = imagesy($wimg);
                }

                $iw = imagesx($img);
                $ih = imagesy($img);
                $wx = (int) $params['wmark_x'];
                $wy = (int) $params['wmark_y'];
                $wp = (int) $params['wmark_pl'];
                if ($wp & QF_IMAGER_WMARK_CENTER)
                    $wx = (int) (($iw - $ww)/2 + $wx);
                elseif ($wp & QF_IMAGER_WMARK_RIGHT)
                    $wx = (int) ($iw - $ww - $wx);
                if ($wp & QF_IMAGER_WMARK_MIDDLE)
                    $wy = (int) (($ih - $wh)/2 + $wy);
                elseif ($wp & QF_IMAGER_WMARK_BOTTOM)
                    $wy = (int) ($ih - $wh - $wy);

                $this->_gd_watermark($img, $wimg, $wx, $wy, $params['wmark_al'], $params['back_col']);
                imagedestroy($wimg);
            }

            // palettizing/alpha-drop
            if (imageistruecolor($img) && ($flags & QF_IMAGER_DO_PALETTIZE))
                if ($this->_gd_palettize($img, $params['p_colors'], $params['back_col'], $params['alpha_br']))
                    $flags &= ~QF_IMAGER_DO_DROPALPHA;

            if ($flags & QF_IMAGER_DO_DROPALPHA)
                $this->_gd_drop_alpha($img, $params['back_col']);

            // finally saving
            if ($this->_gd_save($img, $to_file, $to_type))
            {
                imagedestroy($img);
                return true;
            }
        }

        return false;
    }

    function _gd_load($file, $try_type = 0)
    {
        if (!file_exists($file))
            return null;

        if ($typearr = getimagesize($file))
            $type = $typearr[2];
        else
            $type = $try_type;

        $img = null;
        switch ($type)
        {
            case IMAGETYPE_GIF:
                $img = (imagetypes() & IMG_GIF) ? imagecreatefromgif($file) : false;
                break;
            case IMAGETYPE_JPEG:
                $img = (imagetypes() & IMG_JPG) ? imagecreatefromjpeg($file) : false;
                break;
            case IMAGETYPE_PNG:
                $img = (imagetypes() & IMG_PNG) ? imagecreatefrompng($file) : false;
                if ($img && imageistruecolor($img))
                    imagesavealpha($img, true);
                break;
        }

        return $img;
    }


    function _gd_save($img, $file, $type)
    {
        if (!imagesx($img))
            return false;

        $OK = false;
        switch ($type)
        {
            case IMAGETYPE_GIF:
                $OK = ((imagetypes() & IMG_GIF) && QF_IMAGER_GIF_WRITE) ? imagegif($img, $file) : false;
                break;
            case IMAGETYPE_JPEG:
                $OK = (imagetypes() & IMG_JPG) ? imagejpeg($img, $file, 80) : false;
                break;
            case IMAGETYPE_PNG:
                $OK = (imagetypes() & IMG_PNG) ? imagepng($img, $file) : false;
                break;
        }

        return $OK;

    }

    function _gd_resize(&$img, $width, $height, $do_resample = false, $rs_mode = QF_IMAGER_RSIZE_FORCE, $brdr_wdth = 0, $back_rgb = 0xFFFFFF, $drop_alpha = false)
    {
        if (!is_resource($img) || !($width > 0) || !($height > 0))
            return false;

        if (!($sx = imagesx($img)) || !($sy = imagesy($img)))
            return false;

        if (($width * $height) > 0x400000) // we'll not create so big images
            return false;

        $putw = $width;
        $puth = $height;
        $putx = $puty = 0;
        if ($rs_mode == QF_IMAGER_RSIZE_FIT)
        {
            if (($width >= $sx) && ($height >= $sy)) // pic fits the size allready
                return true;

            $scl = min($width/$sx, $height/$sy);
            $putw = $width = round($scl*$sx);
            $puth = $height = round($scl*$sy);
        }
        elseif (($rs_mode == QF_IMAGER_RSIZE_BRDR) || ($rs_mode == QF_IMAGER_RSIZE_TBRDR))
        {
            $brdr_wdth = (int) min($brdr_wdth, $width/4, $height/4);
            $putw = (int) ($width) - 2*$brdr_wdth;
            $puth = (int) ($height) - 2*$brdr_wdth;

            if (($putw >= $sx) && ($puth >= $sy)) // pic fits the size allready
            {
                $putw = $sx;
                $puth = $sy;
            }
            else
            {
                $scl = min($width/$sx, $height/$sy);
                $putw = round($scl*$sx) - 2*$brdr_wdth;
                $puth = round($scl*$sy) - 2*$brdr_wdth;
            }
            $putx = (int) (($width - $putw)/2);
            $puty = (int) (($height - $puth)/2);
            if ($rs_mode == QF_IMAGER_RSIZE_BRDR)
                $drop_alpha = true;
        }

        $back_rgb = (int) $back_rgb & 0xFFFFFF;
        if (imageistruecolor($img))
        {
            $nimg = imagecreatetruecolor($width, $height);
            if ($drop_alpha)
            {
                imagealphablending($nimg, true);
                imagefilledrectangle($nimg, 0, 0, $width-1, $height-1, $back_rgb);
            }
            else
            {
                imagealphablending($nimg, false);
                imagefilledrectangle($nimg, 0, 0, $width-1, $height-1, $back_rgb | 0x7F000000);
                if ($rs_mode == QF_IMAGER_RSIZE_TBRDR)
                    imagesavealpha($nimg, true);
            }
        }
        else
        {
            $nimg = imagecreate($width, $height);
            if ($drop_alpha)
            {
                $back_r = ($back_rgb >> 16) & 0xFF;
                $back_g = ($back_rgb >> 8) & 0xFF;
                $back_b = $back_rgb & 0xFF;
                $back = imagecolorallocate($nimg, $back_r, $back_g, $back_b);
            }
            else
            {
                $cid = imagecolortransparent($img);
                if ($cid != -1)
                {
                    $rgb = imagecolorsforindex($img, $cid);
                    $back = imagecolorallocate($nimg, $rgb['red'], $rgb['green'], $rgb['blue']);
                    imagecolortransparent($nimg, $back);
                }
                elseif ($rs_mode == QF_IMAGER_RSIZE_TBRDR)
                {
                    $back = imagecolorallocate($nimg, $back_r, $back_g, $back_b);
                    imagecolortransparent($nimg, $back);
                }
            }
            $do_resample = false; // do not resample paletted pics
        }

        if ($do_resample)
            $OK = imagecopyresampled($nimg, $img, $putx, $puty, 0, 0, $putw, $puth, $sx, $sy);
        else
            $OK = imagecopyresized($nimg, $img, $putx, $puty, 0, 0, $putw, $puth, $sx, $sy);

        if ($OK)
        {
            imagedestroy($img);
            $img = $nimg;
            return true;
        }
        elseif ($nimg)
            imagedestroy($nimg);

        return false;
    }

    function _gd_drop_alpha(&$img, $back_rgb = 0xFFFFFF)
    {
        if (!is_resource($img))
            return false;

        if (!($sx = imagesx($img)) || !($sy = imagesy($img)))
            return false;

        $back_rgb = (int) $back_rgb & 0xFFFFFF;
        $back_r = ($back_rgb >> 16) & 0xFF;
        $back_g = ($back_rgb >> 8) & 0xFF;
        $back_b = $back_rgb & 0xFF;
        if (imageistruecolor($img))
        {

            if ($nimg = imagecreatetruecolor($sx, $sy))
            {
                imagefilledrectangle($nimg, 0, 0, $sx-1, $sy-1, $back_rgb);
                imagealphablending($nimg, true);
                imagecopy($nimg, $img, 0, 0, 0, 0, $sx, $sy);
                imagedestroy($img);
                $img = $nimg;
            }

            $tcid = imagecolortransparent($img);
            if ($tcid != -1)
                imagecolortransparent($img, -1);
        }
        else
        {
            $tcid = imagecolortransparent($img);
            if ($tcid != -1)
            {
                //imagecolortransparent($img, -1); // strange but if we'll next ask current trans color it will return '0'
                $nimg = imagecreate($sx, $sy); // ... so we need to recreate image
                imagecolorallocate($nimg, $back_r, $back_g, $back_b);
                imagecopy($nimg, $img, 0, 0, 0, 0, $sx, $sy);
                imagedestroy($img);
                $img = $nimg;
            }
        }

        imagesavealpha($img, false);
        return true;

    }

    function _gd_palettize(&$img, $colors = 256, $back_rgb = 0xFFFFFF, $alpha = 0)
    {
        if (!is_resource($img))
            return false;

        if ($colors > 256 || $colors < 4)
            return false;

        if (!($sx = imagesx($img)) || !($sy = imagesy($img)))
            return false;

        if (!imageistruecolor($img))
            return true;

        if (($sx * $sy) > 0x200000) // we'll not pass so big images with smart actions
            return imagetruecolortopalette($img, false, $colors);

        $back_rgb = (int) $back_rgb & 0xFFFFFF;
        $alpha = (int) $alpha & 0x7F;
        if ($alpha > 0) // we need to save alpha
        {
            $nimg = imagecreatetruecolor($sx, $sy);
            imagefilledrectangle($nimg, 0, 0, $sx-1, $sy-1, $back_rgb);
            imagealphablending($nimg, true);
            imagecopy($nimg, $img, 0, 0, 0, 0, $sx, $sy);
            imagetruecolortopalette($nimg, false, $colors - 1);
            $trc = imagecolorallocate($nimg, 0, 0, 0);
            imagecolortransparent($nimg, $trc);
            for ($x = 0; $x < $sx; $x++)
                for ($y = 0; $y < $sy; $y++)
                {
                    $rgb = imagecolorat($img, $x, $y);
                    $a = ($rgb >> 24) & 0x7F;
                    if (($a + $alpha) > 0x7F)
                    {
                        imagesetpixel($nimg, $x, $y, $trc);
                    }
                }
            imagedestroy($img);
            $img = $nimg;
            return true;
        }
        elseif ($back_rgb) // we need to set background
        {
            $nimg = imagecreatetruecolor($sx, $sy);
            imagefilledrectangle($nimg, 0, 0, $sx-1, $sy-1, $back_rgb);
            imagecopy($nimg, $img, 0, 0, 0, 0, $sx, $sy);
            imagetruecolortopalette($nimg, false, $colors);
            imagedestroy($img);
            $img = $nimg;
            return true;
        }
        else // simple palettisation
            return imagetruecolortopalette($img, false, $colors);

    }

    function _gd_depalettize(&$img, $drop_alpha = false, $back_rgb = 0xFFFFFF)
    {
        if (!is_resource($img))
            return false;

        if (!($sx = imagesx($img)) || !($sy = imagesy($img)))
            return false;

        if (imageistruecolor($img))
            return true;

        $nimg = imagecreatetruecolor($sx, $sy);
        $tcid = imagecolortransparent($img);
        if ($tcid != -1)
        {
            $tcid = imagecolorsforindex($img, $tcid);
            $tcid = $tcid['blue'] | ($tcid['green'] << 8) | ($tcid['red'] << 16) | (0x7F << 24);
            if ($drop_alpha)
                $tcid = $back_rgb & 0xFFFFFF;

            imagealphablending($nimg, false);
            imagefilledrectangle($nimg, 0, 0, $sx-1, $sy-1, $tcid);
            imagesavealpha($nimg, true);
        }
        imagealphablending($nimg, true);
        if (imagecopy($nimg, $img, 0, 0, 0, 0, $sx, $sy))
        {
            imagedestroy($img);
            $img = $nimg;
            return true;
        }

        return false;
    }

    function _gd_watermark(&$img, $wimg, $tx, $ty, $alpha = 0, $back_rgb = 0xFFFFFF)
    {
        if (!is_resource($img) || !is_resource($wimg))
            return false;

        if (!($sx = imagesx($img)) || !($sy = imagesy($img)))
            return false;
        if (!($wx = imagesx($wimg)) || !($wy = imagesy($wimg)))
            return false;

        $wx = min($wx, $sx - $tx);
        $wy = min($wy, $sy - $ty);
        if (!($wx > 0) || !($wy > 0))
            return true; // watermark is out of the image

        $back_rgb = (int) $back_rgb & 0xFFFFFF;
        $alpha = (int) (($alpha & 0x7F)/1.27);
        if (imageistruecolor($wimg))
        {
            $paletted = false;
            if (!imageistruecolor($img))
            {
                $tcid = imagecolortransparent($img);
                $tcid = ($tcid != -1);
                $this->_gd_depalettize($img);
                $paletted = true;
            }

            imagealphablending($img, true);
            if ($alpha > 0)
            {
                $alpha = 1 - $alpha/100;
                for ($x = max(0, -$tx); $x < $wx; $x++)
                  for ($y = max(0, -$ty); $y < $wy; $y++)
                    {
                        $wrgb = imagecolorat($wimg, $x, $y);
                        $wa = ($wrgb >> 24) & 0x7F;
                        $wr = ($wrgb >> 16) & 0xFF;
                        $wg = ($wrgb >> 8) & 0xFF;
                        $wb = $wrgb & 0xFF;
                        $wa = (int) (127 - $alpha*(127-$wa));
                        $wrgb = ($wrgb & 0xFFFFFF) | ($wa << 24);
                        imagesetpixel($img, $x + $tx, $y + $ty, $wrgb);
                    }
            }
            else
                imagecopy($img, $wimg, $tx, $ty, 0, 0, $wx, $wy);

            if ($paletted)
            {
                $this->_gd_palettize($img, 256, $back_rgb, (($tcid) ? 10 : 0));
            }

            return true;
        }
        else
        {
            return imagecopymerge($img, $wimg, $tx, $ty, 0, 0, $wx, $wy, 100 - $alpha);
        }
    }


}

// define imagetype constants for PHP version older then 4.3.0
if (!defined('IMAGETYPE_GIF'))
{
    define('IMAGETYPE_GIF',  1);
    define('IMAGETYPE_JPEG', 2);
    define('IMAGETYPE_PNG',  3);
    define('IMAGETYPE_SWF',  4);
    define('IMAGETYPE_PSD',  5);
    define('IMAGETYPE_BMP',  6);
    define('IMAGETYPE_TIFF_II', 7);
    define('IMAGETYPE_TIFF_MM', 8);
    define('IMAGETYPE_JPC',  9);
    define('IMAGETYPE_JP2', 10);
    define('IMAGETYPE_JPX', 11);
    define('IMAGETYPE_JB2', 12);
    define('IMAGETYPE_SWC', 13);
    define('IMAGETYPE_IFF', 14);
    define('IMAGETYPE_WBMP', 15);
    define('IMAGETYPE_XBM', 16);
}


?>
