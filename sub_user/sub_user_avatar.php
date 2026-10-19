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
    if (empty($_FILES['Avatar']) || $_FILES['Avatar']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('请选择头像');
    }

    $_pdo = GetPDO();

    validateTokey($UserId, $ConfigId, $Tokey);

    $_file = $_FILES['Avatar'];
    $_imgInfo = @getimagesize($_file['tmp_name']);
    if ($_imgInfo === false) throw new Exception('不是有效的图片');
    if ($_file['size'] > 2 * 1024 * 1024) throw new Exception('头像不能超过 2MB');

    $_stmt2 = $_pdo->prepare("SELECT avatar FROM `user_data`.`$ConfigId` WHERE id = :uid LIMIT 1");
    $_stmt2->execute(['uid' => $SubUserId]);
    $_oldAvatar = $_stmt2->fetchColumn();

    $_avatarDir = __DIR__ . '/../sub_avatars/' . $ConfigId;
    if (!is_dir($_avatarDir)) mkdir($_avatarDir, 0755, true);

    $_newName = '';
    for ($_i = 0; $_i < 10; $_i++) {
        $_newName = randomString(21) . '.png';
        if (!file_exists($_avatarDir . '/' . $_newName)) break;
    }
    if ($_newName === '') throw new Exception('文件生成失败');

    if ($_oldAvatar && $_oldAvatar !== '') {
        $_oldPath = $_avatarDir . '/' . $_oldAvatar;
        if (file_exists($_oldPath)) unlink($_oldPath);
    }

    if (!move_uploaded_file($_file['tmp_name'], $_avatarDir . '/' . $_newName)) {
        throw new Exception('头像上传失败');
    }

    $_stmt3 = $_pdo->prepare("UPDATE `user_data`.`$ConfigId` SET avatar = :av WHERE id = :uid");
    $_stmt3->execute(['av' => $_newName, 'uid' => $SubUserId]);

    echo json_encode(['code' => 0, 'avatar' => $_newName], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}