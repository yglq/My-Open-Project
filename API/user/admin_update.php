<?php
/**
 * API 修改积分/会员/称号（需要 api_score_switch 开启）
 * 仅支持 POST
 * 参数: ConfigId, ApiToken, AdminUsername, AdminPassword, TargetUsername, Gold, Exp, Title, VipMinutes
 */

require_once __DIR__ . '/../../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    requirePost();

    $ConfigId        = trim($_POST['ConfigId'] ?? '');
    $ApiToken        = trim($_POST['ApiToken'] ?? '');
    $AdminUsername   = trim($_POST['AdminUsername'] ?? '');
    $AdminPassword   = $_POST['AdminPassword'] ?? '';
    $TargetUsername  = trim($_POST['TargetUsername'] ?? '');

    if ($ConfigId === '' || $ApiToken === '' || $AdminUsername === '' || $AdminPassword === '' || $TargetUsername === '') {
        throw new Exception('参数错误');
    }

    $_pdo = GetPDO();

    // 验证配置
    $_stmt = $_pdo->prepare("SELECT * FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ConfigId]);
    $_config = $_stmt->fetch();
    if (!$_config) throw new Exception('配置不存在');

    validateApiToken($ConfigId, $ApiToken);

    if (intval($_config['api_score_switch'] ?? 0) !== 1) throw new Exception('API 修改积分/会员/称号功能未开启');

    // 验证操作者（需要是后台用户，不是子用户）
    $_stmtAdmin = $_pdo->prepare("SELECT user_id, password, status FROM users WHERE username = :uname LIMIT 1");
    $_stmtAdmin->execute(['uname' => $AdminUsername]);
    $_admin = $_stmtAdmin->fetch();
    if (!$_admin) throw new Exception('后台账号不存在');
    if ($_admin['status'] == 1) throw new Exception('后台账号已被封禁');
    if (!verifyPwd($AdminPassword, $_admin['password'])) throw new Exception('后台账号密码错误');

    // 验证后台用户是否有权限操作这个 ConfigId
    $_stmtReg = $_pdo->prepare("SELECT table_data FROM data_tables_registry WHERE user_id = :uid");
    $_stmtReg->execute(['uid' => $_admin['user_id']]);
    $_regData = json_decode($_stmtReg->fetchColumn(), true) ?: [];
    $_hasAccess = false;
    foreach ($_regData as $_item) {
        if (($_item['table'] ?? '') === $ConfigId) { $_hasAccess = true; break; }
    }
    if (!$_hasAccess) throw new Exception('无权操作该数据库');

    // 验证目标子用户
    $_stmt2 = $_pdo->prepare("SELECT id FROM `user_data`.`$ConfigId` WHERE username = :uname LIMIT 1");
    $_stmt2->execute(['uname' => $TargetUsername]);
    $_target = $_stmt2->fetch();
    if (!$_target) throw new Exception('目标用户不存在');

    // 构建更新
    $_sets = [];
    $_params = [];

    if (isset($_POST['Gold'])) {
        $_sets[] = 'gold = :gold';
        $_params['gold'] = intval($_POST['Gold']);
    }
    if (isset($_POST['Exp'])) {
        $_sets[] = 'exp = :exp';
        $_params['exp'] = intval($_POST['Exp']);
    }
    if (isset($_POST['Title'])) {
        $_sets[] = 'title = :title';
        $_params['title'] = trim($_POST['Title']);
    }
    if (isset($_POST['VipMinutes'])) {
        $_minutes = intval($_POST['VipMinutes']);
        $_vipTime = $_minutes > 0 ? date('Y-m-d H:i:s', strtotime("+{$_minutes} minutes")) : date('Y-m-d H:i:s');
        $_sets[] = 'vip_time = :vip';
        $_params['vip'] = $_vipTime;
    }

    if (empty($_sets)) throw new Exception('没有要更新的内容');

    $_params['uid'] = $_target['id'];
    $_sql = "UPDATE `user_data`.`$ConfigId` SET " . implode(', ', $_sets) . " WHERE id = :uid";
    $_stmt3 = $_pdo->prepare($_sql);
    $_stmt3->execute($_params);

    echo json_encode(['code' => 0, 'message' => '更新成功'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}