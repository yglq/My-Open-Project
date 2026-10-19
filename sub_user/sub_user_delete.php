<?php
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $UserId    = intval($_POST['UserId'] ?? 0);
    $ConfigId  = trim($_POST['ConfigId'] ?? '');
    $Tokey     = trim($_POST['Tokey'] ?? '');
    $SubUserId = intval($_POST['SubUserId'] ?? 0);

    if ($UserId <= 0 || $ConfigId === '' || $Tokey === '' || $SubUserId <= 0) {
        throw new Exception('参数错误');
    }

    $_pdo = GetPDO();

    validateTokey($UserId, $ConfigId, $Tokey);

    $_stmt2 = $_pdo->prepare("SELECT avatar FROM `user_data`.`$ConfigId` WHERE id = :uid LIMIT 1");
    $_stmt2->execute(['uid' => $SubUserId]);
    $_avatar = $_stmt2->fetchColumn();
    if ($_avatar && $_avatar !== '') {
        $_path = __DIR__ . '/../sub_avatars/' . $ConfigId . '/' . $_avatar;
        if (file_exists($_path)) unlink($_path);
    }

    $_stmt3 = $_pdo->prepare("DELETE FROM `user_data`.`$ConfigId` WHERE id = :uid");
    $_stmt3->execute(['uid' => $SubUserId]);

    echo json_encode(['code' => 0, 'message' => '已删除'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}