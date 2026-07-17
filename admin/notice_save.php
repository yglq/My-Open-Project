<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('请求方式错误');

    $UserId   = intval($_POST['UserId'] ?? 0);
    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $Tokey    = trim($_POST['Tokey'] ?? '');

    if ($UserId <= 0 || $ConfigId === '' || $Tokey === '') throw new Exception('参数错误');

    $_pdo = GetPDO();

    // 验证 tokey
    validateTokey($UserId, $ConfigId, $Tokey);

    $_sets = []; $_params = [];
    if (isset($_POST['notice_content'])) {
        $_sets[] = 'notice_content = :content';
        $_params['content'] = trim($_POST['notice_content']);
    }
    if (isset($_POST['notice_switch'])) {
        $_sets[] = 'notice_switch = :nswitch';
        $_params['nswitch'] = intval($_POST['notice_switch']);
    }
    if (isset($_POST['notice_api_switch'])) {
        $_sets[] = 'notice_api_switch = :api';
        $_params['api'] = intval($_POST['notice_api_switch']);
    }
    if (empty($_sets)) throw new Exception('没有要更新的字段');

    $_params['cid'] = $ConfigId;
    $_sql = "UPDATE admin_config SET " . implode(', ', $_sets) . " WHERE config_id = :cid";
    $_stmt2 = $_pdo->prepare($_sql);
    $_stmt2->execute($_params);

    echo json_encode(['code' => 0, 'message' => '保存成功'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}