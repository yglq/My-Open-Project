
    // ========== Config ==========
    let CurrentConfigId = '';

    async function loadDatabaseList() {
        const UserId = localStorage.getItem('UserId');
        if (!UserId) return;

        const _formData = new FormData();
        _formData.append('UserId', UserId);

        try {
            const _res = await fetch('admin/admin_databases.php', { method: 'POST', body: _formData });
            const _data = await _res.json();

            if (_data.code === 0 && _data.list.length > 0) {
                const _select = document.getElementById('ConfigDatabase');
                if (!_select) return;
                _select.innerHTML = '';
                _data.list.forEach(_db => {
                    const _opt = document.createElement('option');
                    _opt.value = _db.table;
                    _opt.textContent = _db.name + (_db.role === 0 ? ' [创建者]' : _db.role === 1 ? ' [员工]' : ' [私有]');
                    _select.appendChild(_opt);
                });
                CurrentConfigId = _data.list[0].table;
                loadConfig(CurrentConfigId);
            }
        } catch (_err) {
            console.log('加载数据库列表失败');
        }
    }

    function switchConfigDB() {
        const _select = document.getElementById('ConfigDatabase');
        if (!_select) return;
        CurrentConfigId = _select.value;
        loadConfig(CurrentConfigId);
    }

async function loadConfig() {
    const UserId = localStorage.getItem('UserId');
    const { configId, tokey } = getCurrentDbInfo();
    if (!UserId || !configId) return;

    try {
        const _formData = new FormData();
        _formData.append('UserId', UserId);
        _formData.append('ConfigId', configId);
        _formData.append('Tokey', tokey);
        const _res = await fetch('admin/admin_config_get.php', { method: 'POST', body: _formData });
        const _data = await _res.json();

        if (_data.code === 0) {
            const _c = _data.data;
            setToggle('LoginSwitch', _c.login_switch);
            setToggle('RegisterSwitch', _c.register_switch);
            setToggle('ChangeNameSwitch', _c.change_name_switch);
            setToggle('ChangeNickSwitch', _c.change_nick_switch);
            setToggle('ChangeAvatarSwitch', _c.change_avatar_switch);
            setToggle('ApiScoreSwitch', _c.api_score_switch);
            setVal('NameMinLen', _c.name_min_len);
            setVal('NameMaxLen', _c.name_max_len);
            setVal('PwdMinLen', _c.pwd_min_len);
            setVal('PwdMaxLen', _c.pwd_max_len);
            setVal('NickMinLen', _c.nick_min_len);
            setVal('NickMaxLen', _c.nick_max_len);
            setVal('IpRegisterLimit', _c.ip_register_limit);
            setVal('RegisterGold', _c.register_gold);
            setVal('RegisterExp', _c.register_exp);
            setVal('RegisterVipMinutes', _c.register_vip_minutes);
            setVal('SignGold', _c.sign_gold);
            setVal('SignExp', _c.sign_exp);
            setVal('CardkeyLength', _c.cardkey_length);
            setVal('CardkeyType', _c.cardkey_type);
            setToggle('CardkeyApiSwitch', _c.cardkey_api_switch);
            setToggle('CardkeyGenerateSwitch', _c.cardkey_generate_switch);
            setToggle('DocsApiSwitch', _c.docs_api_switch);
            setToggle('StatsSwitch', _c.stats_switch);
            setVal('ServerStatus', _c.server_status);
            setToggle('ApiTokenSwitch', _c.api_token_switch);
            setVal('ApiToken', _c.api_token);
            setToggle('SubPwdHash', _c.sub_pwd_hash);
        }
    } catch (_err) {
        console.log('加载配置失败');
    }
}
    function setToggle(id, val) {
        const _el = document.getElementById(id);
        if (_el) _el.checked = (val == 1);
    }

    function setVal(id, val) {
        const _el = document.getElementById(id);
        if (_el) _el.value = val;
    }

    
async function saveConfig() {
    const Toast = document.getElementById('ConfigToast');
    const UserId = localStorage.getItem('UserId');
    const { configId, tokey } = getCurrentDbInfo();

    const Btn = document.getElementById('BtnSave');
    Btn.disabled = true; Btn.textContent = '保存中...';

    const _formData = new FormData();
    _formData.append('UserId', UserId);
    _formData.append('ConfigId', configId);
    _formData.append('Tokey', tokey);

    _formData.append('login_switch', getToggle('LoginSwitch'));
    _formData.append('register_switch', getToggle('RegisterSwitch'));
    _formData.append('change_name_switch', getToggle('ChangeNameSwitch'));
    _formData.append('change_nick_switch', getToggle('ChangeNickSwitch'));
    _formData.append('change_avatar_switch', getToggle('ChangeAvatarSwitch'));
    _formData.append('api_score_switch', getToggle('ApiScoreSwitch'));
    _formData.append('name_min_len', getVal('NameMinLen'));
    _formData.append('name_max_len', getVal('NameMaxLen'));
    _formData.append('pwd_min_len', getVal('PwdMinLen'));
    _formData.append('pwd_max_len', getVal('PwdMaxLen'));
    _formData.append('nick_min_len', getVal('NickMinLen'));
    _formData.append('nick_max_len', getVal('NickMaxLen'));
    _formData.append('ip_register_limit', getVal('IpRegisterLimit'));
    _formData.append('register_gold', getVal('RegisterGold'));
    _formData.append('register_exp', getVal('RegisterExp'));
    _formData.append('register_vip_minutes', getVal('RegisterVipMinutes'));
    _formData.append('sign_gold', getVal('SignGold'));
    _formData.append('sign_exp', getVal('SignExp'));
    _formData.append('cardkey_length', getVal('CardkeyLength'));
    _formData.append('cardkey_type', getVal('CardkeyType'));
    _formData.append('cardkey_api_switch', getToggle('CardkeyApiSwitch'));
    _formData.append('cardkey_generate_switch', getToggle('CardkeyGenerateSwitch'));
    _formData.append('docs_api_switch', getToggle('DocsApiSwitch'));
    _formData.append('stats_switch', getToggle('StatsSwitch'));
    _formData.append('server_status', getVal('ServerStatus'));
    _formData.append('api_token_switch', getToggle('ApiTokenSwitch'));
    _formData.append('api_token', getVal('ApiToken'));
    _formData.append('sub_pwd_hash', getToggle('SubPwdHash'));

    try {
        const _res = await fetch('admin/admin_config_save.php', { method: 'POST', body: _formData });
        const _data = await _res.json();
        if (_data.code === 0) {
            Toast.className = 'toast-inline success';
            Toast.textContent = '配置保存成功';
        } else {
            Toast.className = 'toast-inline error';
            Toast.textContent = _data.message || '保存失败';
        }
    } catch (_err) {
        Toast.className = 'toast-inline error';
        Toast.textContent = '网络错误';
    }
    Btn.disabled = false; Btn.textContent = '保存配置';
}


    function getToggle(id) {
        const _el = document.getElementById(id);
        return _el && _el.checked ? 1 : 0;
    }

    function getVal(id) {
        const _el = document.getElementById(id);
        return _el ? _el.value : '';
    }
