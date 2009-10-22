<?php

// -------------------------------------------------------------------------- \\
// QuickFox kernel 2 sendmail control interface                               \\
// -------------------------------------------------------------------------- \\

// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if ( defined('QF_KERNEL_MAILER_LOADED') )
        die('Scripting error');

define('QF_KERNEL_MAILER_LOADED', True);

// recipients types
define('QF_MAILER_RECIP_TO',  0);
define('QF_MAILER_RECIP_BC',  1);
define('QF_MAILER_RECIP_BCC', 2);

// Breakline
define('MAIL_BR', "\n");

class QF_Mailer
{
    var $send_to = Array();
    var $copy_to = Array();
    var $bcc_to  = Array();
    var $from    = Array();
    var $subject = '';
    var $is_html = false;
    var $text    = '';
    var $parts   = Array();

    function QF_Mailer()
    {

    }

    function _Start()
    {
        global $QF;

        $this->send_to = Array();
        $this->copy_to = Array();
        $this->bcc_to  = Array();
        $this->from    = Array( 'no-reply@'.$QF->HTTP->SrvName, $QF->HTTP->SrvName );
        $this->subject = 'QuickFox Mail';
        $this->is_html = false;
        $this->text    = '';
        $this->parts   = Array();
    }

    function Add_Recipient($addr, $name, $type = QF_MAILER_RECIP_TO)
    {
        if (!qf_str_is_email($addr))
            return false;

        switch ($type)
        {
            case QF_MAILER_RECIP_BC:
                $list =& $this->copy_to;
                break;
            case QF_MAILER_RECIP_BCC:
                $list =& $this->bcc_to;
                break;
            default:
                $list =& $this->send_to;
        }

        $list[$addr] = (string) $name;
        return true;
    }

    function Describe($subject, $from_name = false, $from_addr = false)
    {
        global $QF;

        if (!qf_str_is_email($from_addr))
            $from_addr = 'no-reply@'.$QF->HTTP->SrvName;

        $from_name = ($from_name)
            ? (string) $from_name
            : $QF->HTTP->SrvName;

        $this->from = Array($from_addr, $from_name);
        $this->subject = ($subject)
            ? (string) $subject
            : 'no-subject';

        return true;
    }

    function Set_Body($text, $is_html = false)
    {
        $this->is_html = (bool) $is_html;

        $this->text = (string) $text;

        return true;
    }

    function Attach_File($file, $filename = '', $filemime = '')
    {
        if ($filedata = qf_file_get_contents($file))
        {
            if (!$filename)
                $filename = $file;

            if (!$filemime)
                $filemime = 'application/octet-stream';

            $filebname = qf_basename($filename);
            $FileSize = filesize($file);
            $FileTime = gmdate('D, d M Y H:i:s ', filemtime($file)).'GMT';
            // making part headers
            $data = Array(
                'Content-Type: '.$filemime.'; name="'.$filebname.'"',
                'Content-Location: '.$filename,
                'Content-Transfer-Encoding: base64',
                );

            $data = implode(MAIL_BR, $data).MAIL_BR;
            $data.= MAIL_BR; // closing headers
            $data.= chunk_split(base64_encode($filedata), 76);
            $this->parts[] = $data;
            unset($filedata, $data);
            return true;
        }

        return false;
    }

    function Compose($recode_to = '')
    {
        global $QF;

        $m_from = $QF->USTR->Str_Mime($this->from[1], $recode_to).' <'.$this->from[0].'>';
        $m_subject = $QF->USTR->Str_Mime($this->subject, $recode_to);
        $m_to = Array();
        foreach ($this->send_to as $mail=>$name)
            $m_to[] = $QF->USTR->Str_Mime($name, $recode_to).' <'.$mail.'>';
        $m_cc = Array();
        foreach ($this->copy_to as $mail=>$name)
            $m_cc[] = $QF->USTR->Str_Mime($name, $recode_to).' <'.$mail.'>';
        $m_bcc = Array();
        foreach ($this->bcc_to as $mail=>$name)
            $m_bcc[] = $QF->USTR->Str_Mime($name, $recode_to).' <'.$mail.'>';

        $m_headers = Array(
            'From: '.$m_from,
            'Message-ID: <'.md5(uniqid(time())).'@'.$QF->HTTP->SrvName.'>',
            'MIME-Version: 1.0',
            'Date: '.date('r', time()),
            'X-Priority: 3',
            'X-MSMail-Priority: Normal',
            'X-Mailer: QuickFox',
            'X-MimeOLE: QuickFox',
            );

        if (count($m_cc))
            $m_headers[] = 'Cc: '.implode(', ', $m_cc);
        if (count($m_bcc))
            $m_headers[] = 'Bcc: '.implode(', ', $m_bcc);
        if (count($m_to))
            $m_to = implode(', ', $m_to);
        else
            $m_to = 'Undisclosed-Recipients';

        if ($recode_to && $m_text = $QF->USTR->Str_Convert($this->text, $recode_to))
            $m_encoding = $recode_to;
        else
        {
            $m_text = $this->text;
            $m_encoding = QF_INTERNAL_ENCODING;
        }
        $m_type = ($this->is_html)
            ? 'text/html'
            : 'text/plain';
        if (count($this->parts)) //multipart message
        {
            $t_headers = Array(
                'Content-Type: '.$m_type.'; charset='.$m_encoding,
                'Content-Transfer-Encoding: 8bit',
                );

            $m_boundary = 'MailPart-'.uniqid(time());
            $m_headers[] = 'Content-Type: multipart/related; boundary="'.$m_boundary.'"';
            $m_boundary = '--'.$m_boundary;
            $m_body = $m_boundary.MAIL_BR;
            $m_body.= implode(MAIL_BR, $t_headers).MAIL_BR;
            $m_body.= MAIL_BR; // closing headers
            $m_body.= $m_text.MAIL_BR;
            foreach($this->parts as $part)
                $m_body.= MAIL_BR.$m_boundary.MAIL_BR.$part;
            $m_body.= MAIL_BR.$m_boundary.'--';
        }
        else
        {
            $m_headers[] = 'Content-Type: '.$m_type.'; charset='.$m_encoding;
            $m_headers[] = 'Content-Transfer-Encoding: 8bit';
            $m_body = $m_text;
        }
        $m_headers = implode(MAIL_BR, $m_headers);
        return mail($m_to, $m_subject, $m_body, $m_headers);
    }

}

?>
