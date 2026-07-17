
// ========== Notice ==========
async function loadNoticeConfig() {
    const UserId = localStorage.getItem('UserId');
    const ConfigId = localStorage.getItem('CurrentDbId') || '';
    const Tokey = localStorage.getItem('CurrentDbTokey') || '';
    if (!UserId || !ConfigId || !Tokey) return;

    try {
        const _formData = new FormData();
        _formData.append('UserId', UserId);
        _formData.append('ConfigId', ConfigId);
        _formData.append('Tokey', Tokey);
        const _res = await fetch('admin/admin_config_get.php', { method: 'POST', body: _formData });
        const _data = await _res.json();

        if (_data.code === 0) {
            const _c = _data.data;
            const Textarea = document.getElementById('NoticeContent');
            if (Textarea) Textarea.value = _c.notice_content || '';

            const SwitchEl = document.getElementById('NoticeSwitch');
            if (SwitchEl) SwitchEl.checked = (_c.notice_switch == 1);

            const ApiSwitchEl = document.getElementById('NoticeApiSwitch');
            if (ApiSwitchEl) ApiSwitchEl.checked = (_c.notice_api_switch == 1);
        }
    } catch (_err) {}
}

async function publishNotice() {
    const Toast = document.getElementById('NoticeToast');
    const Content = document.getElementById('NoticeContent').value.trim();
    const UserId = localStorage.getItem('UserId');
    const ConfigId = localStorage.getItem('CurrentDbId') || '';
    const Tokey = localStorage.getItem('CurrentDbTokey') || '';

    if (!ConfigId || !Tokey) {
        Toast.className = 'toast-inline error';
        Toast.textContent = '请先在 Admin 页面选择数据库';
        return;
    }
    if (!Content) {
        Toast.className = 'toast-inline error';
        Toast.textContent = '请输入公告内容';
        return;
    }

    const Btn = document.getElementById('BtnPublish');
    Btn.disabled = true;
    Btn.textContent = '发布中...';

    const _formData = new FormData();
    _formData.append('UserId', UserId);
    _formData.append('ConfigId', ConfigId);
    _formData.append('Tokey', Tokey);
    _formData.append('notice_content', Content);

    try {
        const _res = await fetch('admin/notice_save.php', { method: 'POST', body: _formData });
        const _data = await _res.json();
        if (_data.code === 0) {
            Toast.className = 'toast-inline success';
            Toast.textContent = '公告发布成功';
        } else {
            Toast.className = 'toast-inline error';
            Toast.textContent = _data.message || '发布失败';
        }
    } catch (_err) {
        Toast.className = 'toast-inline error';
        Toast.textContent = '网络错误';
    }

    Btn.disabled = false;
    Btn.textContent = '发 布';
}

async function saveNoticeSettings() {
    const Toast = document.getElementById('NoticeToast');
    const UserId = localStorage.getItem('UserId');
    const ConfigId = localStorage.getItem('CurrentDbId') || '';
    const Tokey = localStorage.getItem('CurrentDbTokey') || '';

    if (!ConfigId || !Tokey) {
        Toast.className = 'toast-inline error';
        Toast.textContent = '请先在 Admin 页面选择数据库';
        return;
    }

    const NoticeSwitch = document.getElementById('NoticeSwitch').checked ? 1 : 0;
    const NoticeApiSwitch = document.getElementById('NoticeApiSwitch').checked ? 1 : 0;

    const _formData = new FormData();
    _formData.append('UserId', UserId);
    _formData.append('ConfigId', ConfigId);
    _formData.append('Tokey', Tokey);
    _formData.append('notice_switch', NoticeSwitch);
    _formData.append('notice_api_switch', NoticeApiSwitch);

    try {
        const _res = await fetch('admin/notice_save.php', { method: 'POST', body: _formData });
        const _data = await _res.json();
        if (_data.code === 0) {
            Toast.className = 'toast-inline success';
            Toast.textContent = '设置保存成功';
        } else {
            Toast.className = 'toast-inline error';
            Toast.textContent = _data.message || '保存失败';
        }
    } catch (_err) {
        Toast.className = 'toast-inline error';
        Toast.textContent = '网络错误';
    }
}



