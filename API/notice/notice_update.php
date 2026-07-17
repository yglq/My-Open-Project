<?php
/**
 * 修改公告
 * 支持 GET 和 POST
 * 参数: ConfigId, Tokey, Content
 */

require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // 同时支持 GET 和 POST
    $_REQUEST = array_merge($_GET, $_POST);

    $ConfigId = trim($_REQUEST['ConfigId'] ?? '');
    $Tokey    = trim($_REQUEST['Tokey'] ?? '');
    $Content  = trim($_REQUEST['Content'] ?? '');

    if ($ConfigId === '' || $Tokey === '' || $Content === '') throw new Exception('参数错误');

    $_pdo = GetPDO();

    // 验证 notice_api_switch
    $_stmt = $_pdo->prepare("SELECT notice_api_switch FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ConfigId]);
    $_config = $_stmt->fetch();
    if (!$_config) throw new Exception('配置不存在');
    if (intval($_config['notice_api_switch']) !== 1) throw new Exception('API 修改公告未开启');

    validateTokeyGlobal($ConfigId, $Tokey);

    // 更新公告
    $_stmt3 = $_pdo->prepare("UPDATE admin_config SET notice_content = :content WHERE config_id = :cid");
    $_stmt3->execute(['content' => $Content, 'cid' => $ConfigId]);

    echo json_encode(['code' => 0, 'message' => '公告已更新'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}