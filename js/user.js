
    // ========== Logout ==========
    function doLogout() {
        if (confirm('确定要退出登录吗？')) {
        localStorage.clear();  // 全部清除，包括 Password
            window.location.href = '/pages/Login.html';
        }
    }

    // ========== Back to My Page ==========
    function backToMy() {
        delete pageCache['my'];
        loadPage('my').then(() => loadMyData());
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        const myNav = document.querySelector('[data-page="my"]');
        if (myNav) myNav.classList.add('active');
    }

    // ========== Load My Data ==========
    async function loadMyData() {
        const UserId = localStorage.getItem('UserId');
        const UserName = localStorage.getItem('UserName');
        if (!UserId) return;

        try {
            const _formData = new FormData();
            _formData.append('UserId', UserId);

            const _response = await fetch('/user/user_info.php', {
                method: 'POST',
                body: _formData
            });

            const _result = await _response.json();

            if (_result.code === 0) {
                const _data = _result.data;

                const NickEl = document.getElementById('MyNickname');
                if (NickEl) NickEl.textContent = _data.nickname || _data.username;

                const AvatarEl = document.getElementById('MyAvatar');
                if (AvatarEl && _data.avatar) {
                    AvatarEl.src = 'avatars/' + _data.avatar;
                }

                const BioEl = document.getElementById('MyBio');
                if (BioEl) BioEl.textContent = _data.bio || '这个人很懒，什么都没写';

                const Badge = document.getElementById('MyBadge');
                if (Badge) {
                    const Now = new Date();
                    const SvipTime = new Date(_data.svip_time);
                    const VipTime = new Date(_data.vip_time);

                    if (_data.svip_time && SvipTime > Now) {
                        Badge.textContent = 'SVIP';
                        Badge.className = 'profile-badge badge-svip';
                    } else if (_data.vip_time && VipTime > Now) {
                        Badge.textContent = 'VIP';
                        Badge.className = 'profile-badge badge-vip';
                    } else {
                        Badge.className = 'profile-badge';
                    }
                }

                const Exp = parseInt(_data.exp) || 0;
                const Gold = parseInt(_data.gold) || 0;
                const Lv = Math.floor(Exp / 1000);
                const LvDisplay = Math.max(Lv, 1);
                const ExpInLv = Exp % 1000;
                const ExpPercent = Math.min((ExpInLv / 1000) * 100, 100);

                const LevelEl = document.getElementById('MyLevel');
                if (LevelEl) {
                    LevelEl.textContent = 'Lv.' + LvDisplay;
                    LevelEl.className = 'profile-level';
                    if (Lv >= 21) LevelEl.classList.add('lv-red');
                    else if (Lv >= 16) LevelEl.classList.add('lv-gold');
                    else if (Lv >= 11) LevelEl.classList.add('lv-purple');
                    else if (Lv >= 6) LevelEl.classList.add('lv-blue');
                    else LevelEl.classList.add('lv-white');
                }

                const ExpBar = document.getElementById('MyExpBar');
                if (ExpBar) ExpBar.style.width = ExpPercent + '%';

                const GoldEl = document.getElementById('MyGold');
                if (GoldEl) GoldEl.textContent = Gold;

            } else {
                const NickEl = document.getElementById('MyNickname');
                if (NickEl) NickEl.textContent = UserName || '用户';
            }
        } catch (_err) {
            const NickEl = document.getElementById('MyNickname');
            if (NickEl) NickEl.textContent = UserName || '用户';
        }


        // 绑定数据库管理点击（防 fetch 子页面 onclick 失效）
setTimeout(() => {
    const _dbItem = document.querySelector('.menu-item .menu-icon');
    if (_dbItem) {
        const _parent = _dbItem.parentElement;
        if (_parent && _parent.querySelector('.menu-label').textContent.includes('数据库管理')) {
            _parent.onclick = function() {
                delete subPageCache['My_Database.html'];
                loadSubPage('My_Database.html').then(() => setTimeout(() => loadDbList(), 300));
            };
            _parent.style.cursor = 'pointer';
        }
    }
}, 100);
    }

    // ========== Update Nickname ==========
    async function updateNickname() {
        const Toast = document.getElementById('NicknameToast');
        const NewNickname = document.getElementById('NewNickname').value.trim();
        const UserId = localStorage.getItem('UserId');

        if (!NewNickname || NewNickname.length < 1) {
            Toast.className = 'toast-inline error';
            Toast.textContent = '昵称不能为空';
            return;
        }

        const Btn = document.getElementById('BtnNickname');
        Btn.disabled = true;
        Btn.textContent = '保存中...';

        const _formData = new FormData();
        _formData.append('UserId', UserId);
        _formData.append('Nickname', NewNickname);

        try {
            const _res = await fetch('/user/update_nickname.php', { method: 'POST', body: _formData });
            const _data = await _res.json();

            if (_data.code === 0) {
                Toast.className = 'toast-inline success';
                Toast.textContent = '昵称修改成功';
                localStorage.setItem('UserName', NewNickname);
                delete pageCache['my'];
                delete subPageCache['My_Account.html'];
            } else {
                Toast.className = 'toast-inline error';
                Toast.textContent = _data.message || '修改失败';
            }
        } catch (_err) {
            Toast.className = 'toast-inline error';
            Toast.textContent = '网络错误';
        }

        Btn.disabled = false;
        Btn.textContent = '保存昵称';
    }

    // ========== Update Password ==========
    async function updatePassword() {
        const Toast = document.getElementById('PasswordToast');
        const OldPassword = document.getElementById('OldPassword').value;
        const NewPassword = document.getElementById('NewPassword').value;
        const UserId = localStorage.getItem('UserId');

        if (!OldPassword) {
            Toast.className = 'toast-inline error';
            Toast.textContent = '请输入原密码';
            return;
        }
        if (NewPassword.length < 8) {
            Toast.className = 'toast-inline error';
            Toast.textContent = '新密码至少8位';
            return;
        }
        if (!/[a-zA-Z]/.test(NewPassword) || !/[0-9]/.test(NewPassword)) {
            Toast.className = 'toast-inline error';
            Toast.textContent = '新密码必须包含字母和数字';
            return;
        }

        const Btn = document.getElementById('BtnPassword');
        Btn.disabled = true;
        Btn.textContent = '修改中...';

        const _formData = new FormData();
        _formData.append('UserId', UserId);
        _formData.append('OldPassword', OldPassword);
        _formData.append('NewPassword', NewPassword);

        try {
            const _res = await fetch('/user/update_password.php', { method: 'POST', body: _formData });
            const _data = await _res.json();

            if (_data.code === 0) {
                Toast.className = 'toast-inline success';
                Toast.textContent = '密码修改成功';
    localStorage.setItem('Password', NewPassword);  // 新增：同步密码
                document.getElementById('OldPassword').value = '';
                document.getElementById('NewPassword').value = '';
            } else {
                Toast.className = 'toast-inline error';
                Toast.textContent = _data.message || '修改失败';
            }
        } catch (_err) {
            Toast.className = 'toast-inline error';
            Toast.textContent = '网络错误';
        }

        Btn.disabled = false;
        Btn.textContent = '修改密码';
    }

    // ========== Back to Home ==========
    function backToHome() {
        loadPage('home');
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        const homeNav = document.querySelector('[data-page="home"]');
        if (homeNav) homeNav.classList.add('active');
    }
