<?php
/**
 * 获取子用户指定变量
 * 支持 GET / POST
 * 参数: ConfigId, ApiToken, Username, Password
 * 以及要获取的变量名: gold=true, exp=true, nickname=true ...
 * 自定义变量名: 直接传变量名
 */

require_once __DIR__ . '/../../lib/db.php';
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

    // 允许获取的公开字段及其类型
    $_publicFields = [
        'nickname'     => 'string',
        'avatar'       => 'string',
        'gender'       => 'int',
        'bio'          => 'string',
        'gold'         => 'int',
        'exp'          => 'int',
        'title'        => 'string',
        'vip_time'     => 'time',
        'ban_time'     => 'time',
        'sign_date'    => 'date',
        'created_at'   => 'time',
        'login_at'     => 'time'
    ];

    // 从 admin_config 获取自定义变量名
    $_stmt3 = $_pdo->prepare("SELECT custom_vars FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt3->execute(['cid' => $ConfigId]);
    $_customVarNames = $_stmt3->fetchColumn();
    $_customVarNames = parseCustomVarNames($_customVarNames ?? '[]');

    foreach ($_customVarNames as $_name) {
        $_publicFields[$_name] = 'string'; // 自定义变量默认 string
    }

    // 收集请求的变量
    $_result = [];
    $_result['username'] = $_user['username']; // 始终返回用户名

    foreach ($_publicFields as $_field => $_type) {
        if (isset($_REQUEST[$_field])) {
            $val = $_user[$_field] ?? null;

            switch ($_type) {
                case 'int':
                    $_result[$_field] = intval($val ?? 0);
                    break;
                case 'time':
                    $_result[$_field] = $val ?: '1999-01-01 01:00:00';
                    break;
                case 'date':
                    $_result[$_field] = $val ?: '1999-01-01';
                    break;
                default:
                    $_result[$_field] = $val ?? '';
            }
        }
    }

    echo json_encode([
        'code' => 0,
        'data' => $_result
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    apiError($e);
}