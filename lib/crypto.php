<?php
/**
 * ========== 传输加密/解密 ==========
 * dev_mode=true 时跳过加密，直接返回原文
 */

// XXTEA 加密
function xxtea_encrypt($str, $key) {
    if ($str == '') return '';
    $v = _xxtea_str2long($str, false);
    $k = _xxtea_str2long($key, false);
    if (count($k) < 4) {
        $k = array_pad($k, 4, 0);
    }
    $n = count($v) - 1;
    if ($n < 1) return '';
    $v = _xxtea_encrypt($v, $k);
    return _xxtea_long2str($v);
}

function xxtea_decrypt($str, $key) {
    if ($str == '') return '';
    $v = _xxtea_str2long($str, false);
    $k = _xxtea_str2long($key, false);
    if (count($k) < 4) {
        $k = array_pad($k, 4, 0);
    }
    $n = count($v) - 1;
    if ($n < 1) return '';
    $v = _xxtea_decrypt($v, $k);
    return _xxtea_long2str($v);
}

function _xxtea_encrypt($v, $k) {
    $n = count($v) - 1;
    $z = $v[$n];
    $y = $v[0];
    $delta = 0x9E3779B9;
    $sum = 0;
    $q = 6 + 52 / ($n + 1);
    while ($q-- > 0) {
        $sum = _xxtea_int32($sum + $delta);
        $e = $sum >> 2 & 3;
        for ($p = 0; $p < $n; $p++) {
            $y = $v[$p + 1];
            $mx = _xxtea_int32((($z >> 5 & 0x07FFFFFF) ^ $y << 2) + (($y >> 3 & 0x1FFFFFFF) ^ $z << 4)) ^ _xxtea_int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
            $v[$p] = _xxtea_int32($v[$p] + $mx);
            $z = $v[$p];
        }
        $y = $v[0];
        $mx = _xxtea_int32((($z >> 5 & 0x07FFFFFF) ^ $y << 2) + (($y >> 3 & 0x1FFFFFFF) ^ $z << 4)) ^ _xxtea_int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
        $v[$n] = _xxtea_int32($v[$n] + $mx);
        $z = $v[$n];
    }
    return $v;
}

function _xxtea_decrypt($v, $k) {
    $n = count($v) - 1;
    $z = $v[$n];
    $y = $v[0];
    $delta = 0x9E3779B9;
    $q = 6 + 52 / ($n + 1);
    $sum = _xxtea_int32($q * $delta);
    while ($sum != 0) {
        $e = $sum >> 2 & 3;
        for ($p = $n; $p > 0; $p--) {
            $z = $v[$p - 1];
            $mx = _xxtea_int32((($z >> 5 & 0x07FFFFFF) ^ $y << 2) + (($y >> 3 & 0x1FFFFFFF) ^ $z << 4)) ^ _xxtea_int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
            $v[$p] = _xxtea_int32($v[$p] - $mx);
            $y = $v[$p];
        }
        $z = $v[$n];
        $mx = _xxtea_int32((($z >> 5 & 0x07FFFFFF) ^ $y << 2) + (($y >> 3 & 0x1FFFFFFF) ^ $z << 4)) ^ _xxtea_int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
        $v[0] = _xxtea_int32($v[0] - $mx);
        $y = $v[0];
        $sum = _xxtea_int32($sum - $delta);
    }
    return $v;
}

function _xxtea_str2long($s, $w) {
    $v = unpack('V*', $s . str_repeat("\0", (4 - strlen($s) % 4) & 3));
    $v = array_values($v);
    return $v;
}

function _xxtea_long2str($v) {
    $s = '';
    foreach ($v as $x) {
        $s .= pack('V', $x);
    }
    return $s;
}

function _xxtea_int32($n) {
    while ($n >= 2147483648) $n -= 4294967296;
    while ($n <= -2147483649) $n += 4294967296;
    return (int)$n;
}

// ========== 对外统一接口 ==========

/**
 * 加密数据（dev_mode 时直接返回原文）
 */
function encrypt($data) {
    global $CONFIG;
    if ($CONFIG['dev_mode']) return $data;
    if (!$CONFIG['encrypt']) return $data;
    $encrypted = xxtea_encrypt($data, $CONFIG['encrypt_key']);
    return base64_encode($encrypted);
}

/**
 * 解密数据（dev_mode 时直接返回原文）
 * 自动检测是否加密：如果 $data 不是 base64 编码，当作明文返回
 */
function decrypt($data) {
    global $CONFIG;
    if ($CONFIG['dev_mode']) return $data;
    if (!$CONFIG['encrypt']) return $data;
    $decoded = @base64_decode($data, true);
    if ($decoded === false) return $data; // 明文
    $result = xxtea_decrypt($decoded, $CONFIG['encrypt_key']);
    if ($result === '' && $data !== '') return $data; // 解密失败，当作明文
    return $result;
}