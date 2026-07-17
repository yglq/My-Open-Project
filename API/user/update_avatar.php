<?php
/**
 * 修改子用户头像
 * 仅支持 POST
 * 参数: ConfigId, ApiToken, Username, Password, Avatar (文件)
 */

require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('仅支持 POST 请求');

    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $ApiToken = trim($_POST['ApiToken'] ?? '');
    $Username = trim($_POST['Username'] ?? '');
    $Password = $_POST['Password'] ?? '';

    if ($ConfigId === '' || $ApiToken === '' || $Username === '' || $Password === '') {
        throw new Exception('参数错误');
    }
    if (empty($_FILES['Avatar']) || $_FILES['Avatar']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('请选择头像文件');
    }

    $_pdo = GetPDO();

    // 验证配置
    $_stmt = $_pdo->prepare("SELECT api_token, api_token_switch, change_avatar_switch FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ConfigId]);
    $_config = $_stmt->fetch();
    if (!$_config) throw new Exception('配置不存在');

    validateApiToken($ConfigId, $ApiToken);

    if (intval($_config['change_avatar_switch'] ?? 1) !== 1) throw new Exception('修改头像功能未开启');

    // 验证子用户
    $_stmt2 = $_pdo->prepare("SELECT id, password, avatar FROM `user_data`.`$ConfigId` WHERE username = :uname LIMIT 1");
    $_stmt2->execute(['uname' => $Username]);
    $_user = $_stmt2->fetch();
    if (!$_user) throw new Exception('账号不存在');
    if (!verifyPwd($Password, $_user['password'])) throw new Exception('密码错误');

    $_file = $_FILES['Avatar'];
    $_imgInfo = @getimagesize($_file['tmp_name']);
    if ($_imgInfo === false) throw new Exception('不是有效的图片');
    if ($_file['size'] > 2 * 1024 * 1024) throw new Exception('头像不能超过 2MB');

    $_avatarDir = __DIR__ . '/../../sub_avatars/' . $ConfigId;
    if (!is_dir($_avatarDir)) mkdir($_avatarDir, 0755, true);

    // 生成新文件名
    $_newName = '';
    for ($_i = 0; $_i < 10; $_i++) {
        $_newName = randomString(21) . '.png';
        if (!file_exists($_avatarDir . '/' . $_newName)) break;
    }
    if ($_newName === '') throw new Exception('文件名生成失败');

    // 删除旧头像
    if ($_user['avatar'] && $_user['avatar'] !== '') {
        $_oldPath = $_avatarDir . '/' . $_user['avatar'];
        if (file_exists($_oldPath)) unlink($_oldPath);
    }

    if (!move_uploaded_file($_file['tmp_name'], $_avatarDir . '/' . $_newName)) {
        throw new Exception('头像上传失败');
    }

    // 更新
    $_stmt3 = $_pdo->prepare("UPDATE `user_data`.`$ConfigId` SET avatar = :av WHERE id = :uid");
    $_stmt3->execute(['av' => $_newName, 'uid' => $_user['id']]);

    echo json_encode(['code' => 0, 'message' => '头像修改成功', 'avatar' => $_newName], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}