<?php
/**
 * ========== LQ Cloud Console 安全配置 ==========
 * 
 * dev_mode: true = 开发模式，忽略加密，密码明文
 *           false = 生产模式，按下方配置执行
 * 
 * encrypt: 是否启用传输加密（xxtea）
 * encrypt_key: 加密密钥（16位以上，仅 encrypt=true 时需要）
 * hash_password: 是否哈希密码（bcrypt）
 *                true = password_hash() 存储
 *                false = 明文存储（仅开发/调试用）
 */
$CONFIG = [
    'dev_mode'      => true,
    'encrypt'       => false,
    'encrypt_key'   => '',
];
