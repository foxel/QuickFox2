<?php

// -------------------------------------------------------------------------- \\
// QuickFox kernel 2 RSA crypto algorytm provider                             \\
// -------------------------------------------------------------------------- \\


// Simple InUSE Check
if ( !defined('QF_STARTED') )
        die('Hacking attempt');

if ( defined('QF_KERNEL_TARLIB_LOADED') )
        die('Scripting error');

define('QF_KERNEL_TARLIB_LOADED', True);

class QF_RSA
{    // constructor
    function QF_RSA()
    {        list($usec, $sec) = explode(' ', microtime());
        mt_srand($sec + $usec * 100000);

        if (extension_loaded('big_int'))
            define ('QF_RSA_USEBI', true);
        elseif (extension_loaded('bcmath'))
        {
            bcscale(0);
            define ('QF_RSA_USEBC', true);
        }
    }

    // public functions

    // generates RSA keys
    //  e - public
    //  d - private
    //  m - modulo
    function Gen_Keys($len) // $len in bytes
    {
        if (defined('QF_RSA_USEBC'))
        {
            return $this->bc_genkeys($len);
        }
        elseif (defined('QF_RSA_USEBI'))
        {
            return $this->bi_genkeys($len);
        }

        return null;
    }

    // Encrypts given binary string
    function Encrypt($bs_mess, $bs_a, $bs_m)
    {
        $len = strlen($bs_m);

        if ($len<=0)
            return false;

        if (defined('QF_RSA_USEBC'))
        {
            $crypto = 'bc_crypto';
            $m = $this->bs2bc($bs_m);
            $a = $this->bs2bc($bs_a);
        }
        elseif (defined('QF_RSA_USEBI'))
        {
            $crypto = 'bi_crypto';
            $m = bi_unserialize($bs_m);
            $a = bi_unserialize($bs_a);
        }
        elseif (defined('QF_RSA_USEBS'))
        {
            $crypto = 'bs_crypto';
            $m = $bs_m;
            $a = $bs_a;
        }
        else
            return false;

        $bs_mess = '[QF_RSA['.$bs_mess.']]';
        $slen = strlen($bs_mess);
        $outmess = '';
        $i = 0;
        while ($i < $slen)
        {
            $part = substr($bs_mess, $i, $len-1);
            $i += $len-1;

            $outmess.= str_pad($this->$crypto($part, $a, $m), $len, chr(0));
        }

        return preg_replace('#\x00$#D', '', $outmess);

    }

    // Decrypts given binary string
    // String must be encrypted by RSA->Ecrypt !!!
    function Decrypt($bs_mess, $bs_a, $bs_m)
    {
        $len = strlen($bs_m);

        if ($len<=0)
            return false;

        if (defined('QF_RSA_USEBC'))
        {
            $crypto = 'bc_crypto';
            $m = $this->bs2bc($bs_m);
            $a = $this->bs2bc($bs_a);
        }
        elseif (defined('QF_RSA_USEBI'))
        {
            $crypto = 'bi_crypto';
            $m = bi_unserialize($bs_m);
            $a = bi_unserialize($bs_a);
        }
        elseif (defined('QF_RSA_USEBS'))
        {
            $crypto = 'bs_crypto';
            $m = $bs_m;
            $a = $bs_a;
        }
        else
            return false;


        $slen = strlen($bs_mess);
        $outmess = '';
        $i = 0;
        while ($i < $slen)
        {
            $part = substr($bs_mess, $i, $len);
            $i += $len;

            $outmess.= str_pad($this->$crypto($part, $a, $m), $len-1, chr(0));
        }
        $outmess = rtrim($outmess, chr(0));

        if (!preg_match('#^\[QF_RSA\[(.*?)\]\]$#sD', $outmess, $outmess))
            return null;

        $outmess = $outmess[1];
        return $outmess;
    }

    // Checks if the given binary string is prime
    function Is_Prime($bs_p)
    {        if (defined('QF_RSA_USEBC'))
        {            return $this->bc_isprime($this->bs2bc($bs_p));
        }
        elseif (defined('QF_RSA_USEBI'))
        {
            return bi_is_prime(bi_unserialize($bs_p));
        }

        return null;
    }

    // converts hex string to binary string
    function Hex_Bs($h_a)
    {        $bs = '';
        $len = strlen($h_a);
        if ($len%2)
        {            $h_a = '0'.$h_a;
            $h_a++;
        }
        $i = $len;
        while ($i > 0)
        {            $i -= 2;
            $bs.= chr(hexdec(substr($h_a, $i, 2)));
        }
        return $bs;
    }

    // converts binary string to hex string
    function Bs_Hex($bs_a)
    {
        $h = '';
        $len = strlen($bs_a);
        $i = $len;
        while ($i > 0)
        {
            $i--;
            $h.= sprintf('%02x', ord($bs_a{$i}));
        }

        return $h;
    }

    // private module functions. Please do not use.

    // generates binary string randomly with given length
    function bs_rand($length)
    {        $bs_a = chr(mt_rand(0, 127) | 128);
        $i = 1;
        while ($i < $length)
        {            $bs_a.= chr(mt_rand(0, 255));
            $i++;
        }
        return $bs_a;
    }

    // generates BCMath string randomly with given length
    function bc_rand($length)
    {
        $a = mt_rand(1, 9);
        $i = 1;
        while ($i < $length)
        {
            $a.= mt_rand(0, 9);
            $i++;
        }
        return $a;
    }

    // converts binary string to BCMath string
    function bs2bc($bs_a)
    {        $bc = '0';
        $len = strlen($bs_a);
        $i = $len;
        while ($i > 0)
        {            $i--;
            $bc = bcadd(bcmul($bc, 256), ord($bs_a{$i}));
        }

        return $bc;
    }

    // converts BCMath string to binary string
    function bc2bs($a)
    {
        $bs = '';
        while ($a != 0)
        {
            $bs.= chr(bcmod($a, 256));
            $a = bcdiv($a, 256);
        }
        return $bs;
    }

    // powmod BCMath function (for PHP4)
    function bc_powmod($a, $b, $m)
    {
        if (function_exists('bcpowmod'))
            return bcpowmod($a, $b, $m);

        $d = '1';
        $a = bcmod($a, $m);

        while ($b != 0)
        {
            if (bcmod($b, 2))
                $d = bcmod(bcmul($d, $a), $m);

            if ($b = bcdiv($b, '2'))
                $a = bcmod(bcmul($a, $a), $m);

        }
        return $d;
    }

    // BCMath prime number checking
    // TODO: slow now
    function bc_isprime($p)
    {        if (!extension_loaded('bcmath'))
            return null;

        if ($p == 2)
            return true;
        elseif (!bcmod($p, 2))
            return false;

        $len = strlen($p);

        $q = $p1 = bcsub($p, 1);
        $t = 0;
        while (!bcmod($q, 2))
        {            $q = bcdiv($q, 2);
            $t++;
        }

        for ($i = 0; $i < 20; $i++)
        {            $a = $this->bc_rand(mt_rand(1, $len-1));
            $a = $this->bc_powmod($a, $q, $p);

            if ($a == 1)
                return false;

            if ($a != 1)
            {                $j = 1;
                while ($a != $p1)
                {                    if ($j >= $t)
                        return false;
                    else
                    {
                        $a = bcmod(bcmul($a, $a), $p);
                        $j++;
                    }
                }
            }
        }

        return true;

    }

    // BCMath function finding the biggest common delimiter
    function bc_gcd($a, $b)
    {        while ($b != 0) {
            $w = bcmod($a, $b);
            $a = $b;
            $b = $w;

        }
        return $a;
    }

    // BCMath invers with modulo function
    function bc_invmod($e, $m)
    {
        $u1 = 1;
        $u2 = 0;
        $u3 = $m;
        $v1 = 0;
        $v2 = 1;
        $v3 = $e;
        while ($v3 != 0) {
            $qq = bcdiv($u3, $v3);
            $t1 = bcsub($u1, bcmul($qq, $v1));
            $t2 = bcsub($u2, bcmul($qq, $v2));
            $t3 = bcsub($u3, bcmul($qq, $v3));
            $u1 = $v1;
            $u2 = $v2;
            $u3 = $v3;
            $v1 = $t1;
            $v2 = $t2;
            $v3 = $t3;
        }
        $uu = $u1;
        $vv = $u2;

        if ($vv{0} == '-') {
            $inverse = bcadd($vv, $m);
        } else {
            $inverse = $vv;
        }

        return $inverse;
    }

    // generates RSA keys using BCMath
    function bc_genkeys($len)
    {        $p_len = floor($len / 2) + 1;
        $q_len = $len - $p_len;

        $tries = 10000;
        do {           $tries--;
           $p = $this->bs2bc($this->bs_rand($p_len));
        } while (!$this->bc_isprime($p) || !$tries);

        do {
           $tries--;
           $q = $this->bs2bc($this->bs_rand($q_len));
        } while (!$this->bc_isprime($q) || !$tries);

        if (!$tries)
            return false;

        $m = bcmul($p, $q);

        $pi = bcmul(bcsub($p, 1), bcsub($q, 1));

        do {            $e = $this->bs2bc($this->bs_rand($q_len));
        } while ($this->bc_gcd($e, $pi)!=1);

        $d = $this->bc_invmod($e, $pi);
        $e = $this->bc2bs($e);
        $d = $this->bc2bs($d);
        $m = $this->bc2bs($m);

        return Array('e' => $e, 'd' => $d, 'm' => $m);
    }

    // BCMath based crypto
    function bc_crypto($bs_mess, $a, $m)
    {        $mess = $this->bs2bc($bs_mess);
        return $this->bc2bs($this->bc_powmod($mess, $a, $m));
    }


    // Big_Int module usage functions (much faster but big_int is not so popular)
    function bi_genkeys($len)
    {        $len = $len * 8;
        $p_len = floor($len / 2) + 1;
        $q_len = $len - $p_len;

        $p = bi_next_prime(bi_set_bit(bi_rand($p_len, 'mt_rand'), $p_len - 1));
        $q = bi_next_prime(bi_set_bit(bi_rand($q_len, 'mt_rand'), $q_len - 1));

        $m = bi_mul($p, $q);

        $pi = bi_mul(bi_dec($p), bi_dec($q));
        do {
            $e = bi_rand($q_len, 'mt_rand');
        } while (!bi_is_zero(bi_dec(bi_gcd($e, $pi))));
        $d = bi_invmod($e, $pi);

        $e = bi_serialize($e);
        $d = bi_serialize($d);
        $m = bi_serialize($m);

        return Array('e' => $e, 'd' => $d, 'm' => $m);
    }

    // Big_Int based crypto
    function bi_crypto($bs_mess, $a, $m)
    {
        $mess = bi_unserialize($bs_mess);
        return bi_serialize(bi_powmod($mess, $a, $m));
    }


    // QF BS Functions - Very Slow/ But if we have no BC or BI we'll need to use them
    function bs_crypto($bs_mess, $bs_a, $bs_m)
    {
        return $this->bs_powmod($bs_mess, $bs_a, $bs_m);
    }


    function bs_powmod($bs_a, $bs_b, $bs_m)
    {
        $bs_d = chr(1);
        $bs_a = $this->bs_mod($bs_a, $bs_m);
        $len = strlen($bs_b);
        $i = 0;
        while ($i < $len)
        {
            $b = ord($bs_b{$i});
            for ($ii = 0; $ii<8; $ii++)
            {
                if (($b >> $ii) & 1)
                {
                    $bs_d = $this->bs_mul($bs_d, $bs_a);
                    $bs_d = $this->bs_mod($bs_d, $bs_m);
                }
                $bs_a = $this->bs_mul($bs_a, $bs_a);
                $bs_a = $this->bs_mod($bs_a, $bs_m);
            }
            $i++;
        }
        return $bs_d;
    }

    function bs_mod($bs_a, $bs_b0)
    {
        if (strlen($bs_a) < strlen($bs_b0))
            return $bs_a;

        $s = (strlen($bs_a) - strlen($bs_b0) + 1) * 8;
        while ($s > 0)
        {
            $s--;

            $bs_b = $this->bs_shl($bs_b0, $s);
            $bs_d = '';
            $o = 0;
            $i = 0;
            $maxlen = max(strlen($bs_a), strlen($bs_b));
            $bs_a = str_pad($bs_a, $maxlen, chr(0));
            $bs_b = str_pad($bs_b, $maxlen, chr(0));
            while ($i < $maxlen)
            {
                $a = ord($bs_a{$i});
                $b = ord($bs_b{$i});

                $d = ($a | 0x0100) - $b - $o;

                $o = 1 & ~($d >> 8);
                $d = $d & 0xFF;
                $bs_d.= chr($d);

                $i++;
            }

            if (!$o)
                $bs_a = $bs_d;
        }

        return rtrim($bs_a, chr(0));
    }

    function bs_mul($bs_a, $bs_b)
    {
        if (strlen($bs_a) < strlen($bs_b))
        {
            $bs_a0 = $bs_a;
            $bs_a = $bs_b;
            $bs_b = $bs_a0;
        }

        $maxlen = strlen($bs_b);
        $len = strlen($bs_a);
        $bs_o = str_repeat(chr(0), $len);
        $i = 0;
        while ($i < $maxlen)
        {
            $bs_d = substr($bs_o, 0, $i);
            if ($b = ord($bs_b{$i}))
            {
                $o = 0;
                $ii = 0;
                while ($ii < $len)
                {
                    $a = ord($bs_a{$ii});
                    $d = ord($bs_o{$ii+$i});
                    $d = $a * $b + $o + $d;

                    $o = $d >> 8;
                    $d = $d & 0xFF;
                    $bs_d.= chr($d);

                    $ii++;
                }
                $bs_d.= chr($o);
                $bs_o = $bs_d;
            }
            else
                $bs_o.= chr(0);

            $i++;
        }

        return $bs_o;
    }

    function bs_shl($bs_a, $times)
    {
        $bs_d = '';
        $i = 0;
        $o = 0;

        $maxlen = strlen($bs_a);
        $nulls = floor($times/8);
        $times = $times % 8;
        $bs_d.= str_repeat(chr(0), $nulls);
        while ($i < $maxlen)
        {
            $a = ord($bs_a{$i});

            $d = ($a << $times) | $o;

            $o = $d >> 8;
            $d = $d & 0xFF;
            $bs_d.= chr($d);

            $i++;
        }
        $bs_d.= chr($o);

        return $bs_d;
    }

}


?>