<?php
/**
 * 修改子用户密码
 * 仅支持 POST
 * 参数: ConfigId, ApiToken, Username, OldPassword, NewPassword
 */

require_once __DIR__ . '/../../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $ConfigId    = trim($_POST['ConfigId'] ?? '');
    $ApiToken    = trim($_POST['ApiToken'] ?? '');
    $Username    = trim($_POST['Username'] ?? '');
    $OldPassword = $_POST['OldPassword'] ?? '';
    $NewPassword = $_POST['NewPassword'] ?? '';

    if ($ConfigId === '' || $ApiToken === '' || $Username === '' || $OldPassword === '' || $NewPassword === '') {
        throw new Exception('参数错误');
    }

    $_pdo = GetPDO();

    // 验证配置
    $_stmt = $_pdo->prepare("SELECT * FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ConfigId]);
    $_config = $_stmt->fetch();
    if (!$_config) throw new Exception('配置不存在');

    validateApiToken($ConfigId, $ApiToken);

    $_pwdMin = intval($_config['pwd_min_len'] ?? 8);
    $_pwdMax = intval($_config['pwd_max_len'] ?? 30);
    if (strlen($NewPassword) < $_pwdMin || strlen($NewPassword) > $_pwdMax) {
        throw new Exception("密码长度需在 {$_pwdMin}~{$_pwdMax} 之间");
    }

    // 验证子用户
    $_stmt2 = $_pdo->prepare("SELECT id, password, can_change_pwd FROM `user_data`.`$ConfigId` WHERE username = :uname LIMIT 1");
    $_stmt2->execute(['uname' => $Username]);
    $_user = $_stmt2->fetch();
    if (!$_user) throw new Exception('账号不存在');
    if (!verifyPwd($OldPassword, $_user['password'])) throw new Exception('原密码错误');
    if (intval($_user['can_change_pwd'] ?? 1) !== 1) throw new Exception('该账号不允许修改密码');

    // 更新
    $_stmt3 = $_pdo->prepare("UPDATE `user_data`.`$ConfigId` SET password = :pwd WHERE id = :uid");
    $_stmt3->execute(['pwd' => hashSubPwd($NewPassword, intval($_config['sub_pwd_hash'] ?? 0)), 'uid' => $_user['id']]);

    echo json_encode(['code' => 0, 'message' => '密码修改成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}