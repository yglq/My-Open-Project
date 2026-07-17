<?php
/**
 * 获取公告
 * 支持 GET 和 POST
 * 参数: ConfigId, ApiToken
 */

require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // 同时支持 GET 和 POST
    $_REQUEST = array_merge($_GET, $_POST);

    $ConfigId = trim($_REQUEST['ConfigId'] ?? '');
    $ApiToken = trim($_REQUEST['ApiToken'] ?? '');

    if ($ConfigId === '' || $ApiToken === '') throw new Exception('参数错误');

    $_pdo = GetPDO();

    $_stmt = $_pdo->prepare("SELECT notice_content, notice_switch, api_token, api_token_switch FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ConfigId]);
    $_config = $_stmt->fetch();

    if (!$_config) throw new Exception('配置不存在');

    // Token 验证：只有 api_token_switch = 1 时才验证
    validateApiToken($ConfigId, $ApiToken);

    if (intval($_config['notice_switch']) !== 1) throw new Exception('公告未开启');

    echo json_encode([
        'code'    => 0,
        'content' => $_config['notice_content'] ?? ''
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}