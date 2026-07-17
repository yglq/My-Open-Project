<?php
/**
 * ========== 密码处理 ==========
 * 主用户密码：始终 bcrypt 哈希
 * 子用户密码：由 admin_config.sub_pwd_hash 控制，创建时可手动选择
 * 验证时自动检测：$2y$ 开头 → password_verify，否则 → 明文比较
 */

/**
 * 哈希密码（主用户专用，始终 bcrypt）
 */
function hashPwd($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * 哈希子用户密码（根据开关决定）
 * $useHash: 1/true = bcrypt，0/false = 明文
 */
function hashSubPwd($password, $useHash) {
    if ($useHash) {
        return password_hash($password, PASSWORD_BCRYPT);
    }
    return $password;
}

/**
 * 验证密码（自动检测 bcrypt/明文）
 * $input: 用户输入的密码
 * $stored: 数据库中存储的密码
 */
function verifyPwd($input, $stored) {
    // 自动检测：bcrypt 哈希以 $2y$ 开头
    if (strpos($stored, '$2y$') === 0) {
        return password_verify($input, $stored);
    }
    // 明文比较
    return $input === $stored;
}