<?php
/**
 * 获取服务器配置（群聊人数上限、颜色等级等）
 * 无需登录验证，公开读取
 */
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $_pdo = GetPDO();
    $_stmt = $_pdo->query("SELECT studio_limit, group_color, group_limit FROM server_config LIMIT 1");
    $_config = $_stmt->fetch();

    if (!$_config) {
        throw new Exception('配置不存在');
    }

    // JSON 字段解码为数组
    $_config['studio_limit'] = json_decode($_config['studio_limit'], true);
    $_config['group_color']  = json_decode($_config['group_color'], true);
    $_config['group_limit']  = json_decode($_config['group_limit'], true);

    echo json_encode([
        'code' => 0,
        'data' => $_config
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    apiError($e);
}