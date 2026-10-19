<?php
/**
 * 兑换卡密（子用户侧 API）
 * 仅支持 POST
 * 参数: ConfigId, ApiToken, Username, Password, Code
 *
 * 流程：
 *   1. 校验 ApiToken（受 api_token_switch 控制）
 *   2. 校验 cardkey_api_switch（兑换总开关）
 *   3. 校验子用户 + 密码 + 封号
 *   4. 原子认领卡密（WHERE used_at IS NULL），防止并发重复兑换
 *   5. 按 type 发放奖励
 *   6. 写两张兑换日志（子用户 redeem_log + admin_config.redeem_log）
 */

require_once __DIR__ . '/../../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

// 解析 custom_vars 为 name => type 映射（兼容新旧格式）
function _parseVarTypes($jsonStr) {
    $raw = json_decode($jsonStr ?: '[]', true) ?: [];
    $map = [];
    foreach ($raw as $v) {
        if (is_array($v)) {
            $map[_safeIdent($v['name'] ?? '')] = $v['type'] ?? 'string';
        } else {
            $map[_safeIdent($v)] = 'string'; // 旧格式默认 string
        }
    }
    return $map;
}

try {

    requirePost();

    $ConfigId = trim($_POST['ConfigId'] ?? '');
    $ApiToken = trim($_POST['ApiToken'] ?? '');
    $Username = trim($_POST['Username'] ?? '');
    $Password = $_POST['Password'] ?? '';
    $Code     = trim($_POST['Code'] ?? '');

    if ($ConfigId === '' || $ApiToken === '' || $Username === '' || $Password === '' || $Code === '') {
        throw new Exception('参数错误：ConfigId, ApiToken, Username, Password, Code 均为必填');
    }

    $_pdo = GetPDO();
    // 子用户表名使用原始 ConfigId（与建表 createSubUserTable / 注册 / 生成卡密保持一致）
    $_userTable = $ConfigId;

    // 验证配置
    $_stmt = $_pdo->prepare("SELECT * FROM admin_config WHERE config_id = :cid LIMIT 1");
    $_stmt->execute(['cid' => $ConfigId]);
    $_config = $_stmt->fetch();
    if (!$_config) throw new Exception('配置不存在');

    // Token 验证（受 api_token_switch 控制）
    validateApiToken($ConfigId, $ApiToken);

    // 兑换总开关
    if (intval($_config['cardkey_api_switch'] ?? 0) !== 1) {
        throw new Exception('卡密兑换功能未开启');
    }

    // 验证子用户
    $_stmt2 = $_pdo->prepare("SELECT * FROM `user_data`.`$_userTable` WHERE username = :uname LIMIT 1");
    $_stmt2->execute(['uname' => $Username]);
    $_user = $_stmt2->fetch();
    if (!$_user) throw new Exception('账号不存在');

    // 封号检查
    $_ban = $_user['ban_time'] ?? null;
    if ($_ban !== null && strtotime($_ban) > time()) {
        throw new Exception('账号已被封禁');
    }

    // 密码校验
    if (!verifyPwd($Password, $_user['password'])) throw new Exception('密码错误');

    $_uid = intval($_user['id']);
    $_ip  = getClientIp();
    $_now = date('Y-m-d H:i:s');

    // 查卡密
    $_cardTable = "`card_data`.`{$ConfigId}_cardkey`";
    $_stmtC = $_pdo->prepare("SELECT * FROM $_cardTable WHERE code = :code LIMIT 1");
    $_stmtC->execute(['code' => $Code]);
    $_card = $_stmtC->fetch();
    if (!$_card) throw new Exception('卡密不存在');
    if ($_card['used_at'] !== null) throw new Exception('卡密已被使用');

    // 原子认领：仅当 used_at 仍为 NULL 时才能认领成功（防止并发重复兑换）
    $_claim = $_pdo->prepare(
        "UPDATE $_cardTable
         SET used_at = :now, used_by = :uid, used_username = :un, used_ip = :ip
         WHERE code = :code AND used_at IS NULL"
    );
    $_claim->execute([
        'now' => $_now,
        'uid' => $_uid,
        'un'  => $Username,
        'ip'  => $_ip,
        'code' => $Code
    ]);
    if ($_claim->rowCount() !== 1) {
        throw new Exception('卡密已被使用');
    }
    $_claimed = true;

    $_granted = [];
    $_type = intval($_card['type']);

    if ($_type === 1) {
        // 金币：直接累加
        $_coin = intval($_card['coin_num']);
        $_newGold = intval($_user['gold'] ?? 0) + $_coin;
        $_stmtU = $_pdo->prepare("UPDATE `user_data`.`$_userTable` SET gold = :g WHERE id = :uid");
        $_stmtU->execute(['g' => $_newGold, 'uid' => $_uid]);
        $_granted['gold'] = $_coin;

    } elseif ($_type === 2) {
        // 会员：VIP 天数延长（已过期则从现在起算，未过期则累加）
        $_days = intval($_card['vip_days']);
        $_curVip = $_user['vip_time'] ?? null;
        $_dt = new DateTime();
        if ($_curVip !== null && strtotime($_curVip) > time()) {
            $_dt = new DateTime($_curVip);
        }
        $_dt->modify("+{$_days} days");
        $_newVip = $_dt->format('Y-m-d H:i:s');
        $_stmtU = $_pdo->prepare("UPDATE `user_data`.`$_userTable` SET vip_time = :v WHERE id = :uid");
        $_stmtU->execute(['v' => $_newVip, 'uid' => $_uid]);
        $_granted['vip_days'] = $_days;

    } elseif ($_type === 3) {
        // 自定义变量：value 型累加，string 型替换
        $_varTypes = _parseVarTypes($_config['custom_vars'] ?? '[]');
        $_valueList = json_decode($_card['value_list'] ?? '[]', true) ?: [];
        if (!is_array($_valueList)) throw new Exception('卡密奖励数据格式错误');

        foreach ($_valueList as $_item) {
            $_varName = _safeIdent($_item['name'] ?? '');
            $_varVal  = $_item['value'] ?? '';
            if ($_varName === '') continue;
            if (!array_key_exists($_varName, $_varTypes)) {
                throw new Exception('自定义变量不存在：' . $_varName);
            }
            // 列不存在则自动创建
            $_colCheck = $_pdo->query("SHOW COLUMNS FROM `user_data`.`$_userTable` LIKE '$_varName'");
            if ($_colCheck->rowCount() === 0) {
                $_pdo->exec("ALTER TABLE `user_data`.`$_userTable` ADD COLUMN `$_varName` VARCHAR(255) DEFAULT '0'");
            }

            if (($_varTypes[$_varName] ?? 'string') === 'value') {
                // 数值累加
                $_cur = is_numeric($_user[$_varName] ?? '') ? $_user[$_varName] : 0;
                $_newVal = floatval($_cur) + floatval($_varVal);
                // 整数保持整数形式
                if (is_int($_cur + 0) && is_int(floatval($_varVal) + 0) && strpos((string)$_varVal, '.') === false) {
                    $_newVal = intval($_newVal);
                }
            } else {
                // 字符串替换
                $_newVal = (string)$_varVal;
            }

            $_stmtU = $_pdo->prepare("UPDATE `user_data`.`$_userTable` SET `$_varName` = :v WHERE id = :uid");
            $_stmtU->execute(['v' => $_newVal, 'uid' => $_uid]);
            $_granted['vars'][] = ['name' => $_varName, 'value' => $_newVal];
        }

    } elseif ($_type === 4) {
        // 自定义 JSON：table=列名，col=变量名，value=值，按 key 合并进该列 JSON 对象
        // 数值累加，其余替换；保留对象中其它已有 key
        $_jsonList = json_decode($_card['json_list'] ?? '[]', true) ?: [];
        if (!is_array($_jsonList)) throw new Exception('卡密奖励数据格式错误');

        foreach ($_jsonList as $_item) {
            $_tbl = _safeIdent($_item['table'] ?? '');
            $_col = _safeIdent($_item['col'] ?? '');
            $_val = $_item['value'] ?? null;
            if ($_tbl === '' || $_col === '') continue;

            // JSON 列不存在则自动创建
            $_colCheck = $_pdo->query("SHOW COLUMNS FROM `user_data`.`$_userTable` LIKE '$_tbl'");
            if ($_colCheck->rowCount() === 0) {
                $_pdo->exec("ALTER TABLE `user_data`.`$_userTable` ADD COLUMN `$_tbl` JSON DEFAULT NULL");
            }

            // 读取当前对象
            $_stmtJ = $_pdo->prepare("SELECT `$_tbl` FROM `user_data`.`$_userTable` WHERE id = :uid");
            $_stmtJ->execute(['uid' => $_uid]);
            $_raw = $_stmtJ->fetchColumn();
            $_cur = ($_raw !== null) ? json_decode($_raw, true) : [];
            if (!is_array($_cur)) $_cur = [];

            // 按 key 合并（数值累加，其余替换）
            if (is_numeric($_val) && is_numeric($_cur[$_col] ?? null)) {
                $_nv = floatval($_cur[$_col]) + floatval($_val);
                if (strpos((string)$_val, '.') === false && strpos((string)($_cur[$_col]), '.') === false) {
                    $_nv = intval($_nv);
                }
                $_cur[$_col] = $_nv;
            } else {
                $_cur[$_col] = $_val;
            }

            $_stmtU = $_pdo->prepare("UPDATE `user_data`.`$_userTable` SET `$_tbl` = :j WHERE id = :uid");
            $_stmtU->execute(['j' => json_encode($_cur, JSON_UNESCAPED_UNICODE), 'uid' => $_uid]);
            $_granted['json'][] = ['table' => $_tbl, 'col' => $_col, 'value' => $_cur[$_col]];
        }

    } else {
        throw new Exception('不支持的卡密类型：' . $_type);
    }

    // 写子用户兑换日志 redeem_log（数组，追加）
    $_colCheck = $_pdo->query("SHOW COLUMNS FROM `user_data`.`$_userTable` LIKE 'redeem_log'");
    if ($_colCheck->rowCount() === 0) {
        $_pdo->exec("ALTER TABLE `user_data`.`$_userTable` ADD COLUMN `redeem_log` JSON DEFAULT NULL");
    }
    $_stmtRL = $_pdo->prepare("SELECT redeem_log FROM `user_data`.`$_userTable` WHERE id = :uid");
    $_stmtRL->execute(['uid' => $_uid]);
    $_rawLog = $_stmtRL->fetchColumn();
    $_logs = $_rawLog ? json_decode($_rawLog, true) : [];
    if (!is_array($_logs)) $_logs = [];
    $_logs[] = [
        'code'  => $Code,
        'type'  => $_type,
        'date'  => $_now,
        'ip'    => $_ip
    ];
    $_stmtUL = $_pdo->prepare("UPDATE `user_data`.`$_userTable` SET redeem_log = :log WHERE id = :uid");
    $_stmtUL->execute(['log' => json_encode($_logs, JSON_UNESCAPED_UNICODE), 'uid' => $_uid]);

    // 写 admin_config 兑换日志 redeem_log（以卡密 code 为 key 的对象，便于审计统计）
    $_colCheck2 = $_pdo->query("SHOW COLUMNS FROM admin_config LIKE 'redeem_log'");
    if ($_colCheck2->rowCount() === 0) {
        $_pdo->exec("ALTER TABLE admin_config ADD COLUMN redeem_log JSON DEFAULT NULL");
    }
    $_stmtCL = $_pdo->prepare("SELECT redeem_log FROM admin_config WHERE config_id = :cid");
    $_stmtCL->execute(['cid' => $ConfigId]);
    $_rawCfgLog = $_stmtCL->fetchColumn();
    $_cfgLog = $_rawCfgLog ? json_decode($_rawCfgLog, true) : [];
    if (!is_array($_cfgLog)) $_cfgLog = [];
    $_cfgLog[$Code] = [
        'user_id'  => $_uid,
        'username' => $Username,
        'date'     => $_now,
        'ip'       => $_ip
    ];
    $_stmtCU = $_pdo->prepare("UPDATE admin_config SET redeem_log = :log WHERE config_id = :cid");
    $_stmtCU->execute(['log' => json_encode($_cfgLog, JSON_UNESCAPED_UNICODE), 'cid' => $ConfigId]);

    echo json_encode([
        'code'    => 0,
        'message' => '兑换成功',
        'type'    => $_type,
        'granted' => $_granted
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // 若已认领但后续发奖/写日志失败，回滚认领状态，避免卡密被吃掉
    if (isset($_claimed) && $_claimed && isset($_pdo) && isset($Code) && isset($_uid)) {
        try {
            $_cardTable = "`card_data`.`{$ConfigId}_cardkey`";
            $_rb = $_pdo->prepare(
                "UPDATE $_cardTable
                 SET used_at = NULL, used_by = NULL, used_username = NULL, used_ip = NULL
                 WHERE code = :code AND used_by = :uid"
            );
            $_rb->execute(['code' => $Code, 'uid' => $_uid]);
        } catch (Exception $ignored) {}
    }
    http_response_code(400);
    apiError($e);
}
