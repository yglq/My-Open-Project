<?php
/**
 * 获取子用户全部公开数据
 * 支持 GET / POST
 * 参数: ConfigId, ApiToken, Username, Password
 */

require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $_REQUEST = array_merge($_GET, $_POST);

    $ConfigId = trim($_REQUEST['ConfigId'] ?? '');
    $ApiToken = trim($_REQUEST['ApiToken'] ?? '');
    $Username = trim($_REQUEST['Username'] ?? '');
    $Password = $_REQUEST['Password'] ?? '';

    if ($ConfigId === '' || $ApiToken === '' || $Username === '' || $Password === '') {
        throw new Exception('参数错误');
    }

    $_pdo = GetPDO();

    // 验证配置
    $_stmt = $_pdo->prepare("SELECT * FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ConfigId]);
    $_config = $_stmt->fetch();
    if (!$_config) throw new Exception('配置不存在');

    validateApiToken($ConfigId, $ApiToken);

    // 验证子用户
    $_stmt2 = $_pdo->prepare("SELECT * FROM `user_data`.`$ConfigId` WHERE username = :uname LIMIT 1");
    $_stmt2->execute(['uname' => $Username]);
    $_user = $_stmt2->fetch();
    if (!$_user) throw new Exception('账号不存在');
    if (!verifyPwd($Password, $_user['password'])) throw new Exception('密码错误');

    // 获取自定义变量名和表名
    $_stmt3 = $_pdo->prepare("SELECT custom_vars, custom_tables FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt3->execute(['cid' => $ConfigId]);
    $_customCfg = $_stmt3->fetch();
    $_varNames = parseCustomVarNames($_customCfg['custom_vars'] ?? '[]');
    $_tblNames = parseCustomTableDefs($_customCfg['custom_tables'] ?? '{}');

    // 构建自定义变量值
    $_customVars = [];
    foreach ($_varNames as $_name) {
        $_customVars[$_name] = $_user[$_name] ?? 0;
    }

    // 构建自定义表格值
    $_customTables = [];
    foreach ($_tblNames as $_tblName => $_cols) {
        $_raw = $_user[$_tblName] ?? null;
        $_customTables[$_tblName] = [
            'columns' => $_cols,
            'rows'    => $_raw ? json_decode($_raw, true) : []
        ];
    }

    echo json_encode([
        'code' => 0,
        'data' => [
            'nickname'     => $_user['nickname'] ?? '',
            'avatar'       => $_user['avatar'] ?? '',
            'gender'       => intval($_user['gender'] ?? 0),
            'bio'          => $_user['bio'] ?? '',
            'gold'         => intval($_user['gold'] ?? 0),
            'exp'          => intval($_user['exp'] ?? 0),
            'title'        => $_user['title'] ?? '',
            'vip_time'     => $_user['vip_time'] ?? '1999-01-01 01:00:00',
            'ban_time'     => $_user['ban_time'] ?? '1999-01-01 01:00:00',
            'sign_date'    => $_user['sign_date'] ?? '1999-01-01',
            'created_at'   => $_user['created_at'] ?? '1999-01-01 01:00:00',
            'login_at'     => $_user['login_at'] ?? '1999-01-01 01:00:00',
            'custom_vars'  => $_customVars,
            'custom_tables'=> $_customTables
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}