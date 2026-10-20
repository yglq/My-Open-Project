
// ========== Send Page Popup Menu ==========
function togglePopupMenu() {
    const menu = document.getElementById('PopupMenu');
    if (menu) {
        menu.classList.toggle('show');
    }
}

function closePopupMenu() {
    const menu = document.getElementById('PopupMenu');
    if (menu) {
        menu.classList.remove('show');
    }
}

// 点击页面其他地方关闭弹窗
document.addEventListener('click', function(e) {
    const menu = document.getElementById('PopupMenu');
    const addBtn = document.querySelector('.add-btn');
    if (menu && menu.classList.contains('show') && !menu.contains(e.target) && !addBtn.contains(e.target)) {
        menu.classList.remove('show');
    }
});


async function loadMsgList() {
    const UserId = localStorage.getItem('UserId');
    if (!UserId) return;

    const ListEl = document.getElementById('MsgList');
    if (!ListEl) return;

    try {
        const _formData = new FormData();
        _formData.append('UserId', UserId);
        const _res = await apiFetch('/chat/chat_list.php', { method: 'POST', body: _formData });
        const _data = await _res.json();

        if (_data.code === 0 && _data.data.length > 0) {
  ListEl.innerHTML = _data.data.map(_item => `
    <div class="msg-item" onclick="parent.openChat('${_item.chat_id}')">
                 <img src="${_item.is_group ? 'group_avatars/' + _item.avatar : 'avatars/' + _item.avatar}" 
                         class="msg-avatar" onerror="this.src='/images/icon.png'">
                    <div class="msg-info">
                        <div class="msg-name">${_item.name}</div>
                        <div class="msg-text">${_item.last_msg || '暂无消息'}</div>
                    </div>
                    ${_item.unread > 0 ? `<span class="msg-badge">${_item.unread}</span>` : ''}
                </div>
            `).join('');
        } else {
            ListEl.innerHTML = '<div class="empty-msg">暂无消息</div>';
        }
    } catch (_err) {
        ListEl.innerHTML = '<div class="empty-msg">加载失败</div>';
    }
}

function openChat(chatId) {
    console.log('Open chat:', chatId);
}


// 轻量刷新当前聊天（WebSocket 收到新消息时调用，不重载子页面）
async function refreshCurrentChat() {
    if (!CurrentChatId) return;
    if (!document.getElementById('ChatMessages')) return;

    const _formData = new FormData();
    _formData.append('UserId', localStorage.getItem('UserId'));
    _formData.append('ChatId', CurrentChatId);
    _formData.append('Offset', '0');
    _formData.append('Limit', String(ChatLimit));

    try {
        const _res = await apiFetch('/chat/chat_detail.php', { method: 'POST', body: _formData });
        const _data = await _res.json();
        if (_data.code === 0) {
            ChatData = _data.data;
            renderMessages(_data.data.Messages, _data.data.ReadList || {}, _data.data.IsGroup);
            const _c = document.getElementById('ChatMessages');
            if (_c) _c.scrollTop = _c.scrollHeight;
        }
    } catch (_err) {}
}

// ========== Chat Detail ==========
let CurrentChatId = '';
let CurrentOffset = 0;
const ChatLimit = 20;
let ChatData = null;

async function openChatDetail(chatId) {
   await loadServerConfig();
    CurrentChatId = chatId;
    CurrentOffset = 0;

    const _formData = new FormData();
    _formData.append('UserId', localStorage.getItem('UserId'));
    _formData.append('ChatId', chatId);
    _formData.append('Offset', '0');
    _formData.append('Limit', String(ChatLimit));

    try {
        const _res = await apiFetch('/chat/chat_detail.php', { method: 'POST', body: _formData });
        const _data = await _res.json();

        if (_data.code === 0) {
            ChatData = _data.data;
            loadSubPage('Send_Chat.html').then(() => {
                setTimeout(() => renderChatDetail(_data.data), 100);
            });
        }
    } catch (_err) {
        console.log('加载失败');
    }
}
// ========== 全局配置 ==========
let ServerConfig = null;

async function loadServerConfig() {
    if (ServerConfig) return ServerConfig;
    try {
        const _res = await apiFetch('/admin/server_config_get.php');
        const _data = await _res.json();
        if (_data.code === 0) ServerConfig = _data.data;
        return ServerConfig;
    } catch (_err) { return null; }
}

// 根据人数获取颜色 class
function getGroupColorClass(memberCount) {
    if (!ServerConfig || !ServerConfig.group_color) return 'white';
    const _colors = ServerConfig.group_color;
    const _sorted = Object.entries(_colors).sort((a, b) => parseInt(b[0]) - parseInt(a[0]));
    for (const [threshold, colorIdx] of _sorted) {
        if (memberCount >= parseInt(threshold)) {
            const _map = {0: 'white', 1: 'blue', 2: 'purple', 3: 'red'};
            return _map[colorIdx] || 'white';
        }
    }
    return 'white';
}

// 根据人数获取颜色值（用于气泡内名字）
function getGroupColorValue(memberCount) {
    if (!ServerConfig || !ServerConfig.group_color) return '#8b7355';
    const _colors = ServerConfig.group_color;
    const _sorted = Object.entries(_colors).sort((a, b) => parseInt(b[0]) - parseInt(a[0]));
    for (const [threshold, colorIdx] of _sorted) {
        if (memberCount >= parseInt(threshold)) {
            const _map = {0: '#8b7355', 1: '#2563eb', 2: '#7c3aed', 3: '#dc2626'};
            return _map[colorIdx] || '#8b7355';
        }
    }
    return '#8b7355';
}

// ========== 渲染聊天详情 ==========
function renderChatDetail(data) {
    const UserId = parseInt(localStorage.getItem('UserId'));
    const NameEl = document.getElementById('ChatName');
    const CountEl = document.getElementById('ChatCount');
    const DetailBtn = document.getElementById('ChatDetailBtn');

    if (data.IsGroup) {
        NameEl.textContent = data.GroupName || '群聊';
        CountEl.textContent = (data.GroupMemberCount || 0) + '/' + (data.GroupMax || '∞');
        CountEl.style.display = 'inline';
        DetailBtn.style.display = 'flex';
        NameEl.className = 'chat-name ' + getGroupColorClass(data.GroupMemberCount || 0);
    } else {
        const Members = data.Members || {};
        for (const [mid, mname] of Object.entries(Members)) {
            if (parseInt(mid) !== UserId) {
                NameEl.textContent = mname;
                break;
            }
        }
        CountEl.style.display = 'none';
        DetailBtn.style.display = 'none';
        NameEl.className = 'chat-name white';
    }

    renderMessages(data.Messages, data.ReadList || {}, data.IsGroup);

    const Input = document.getElementById('ChatInput');
    if (Input) {
        Input.onkeydown = function(e) {
            if (e.key === 'Enter') sendTextMessage();
        };
    }
}

// ========== 渲染消息列表 ==========
function renderMessages(messages, readList, isGroup) {
  const MemberVipStatus = ChatData.MemberVipStatus || {};
    const Container = document.getElementById('ChatMessages');
    if (!Container) return;
    const UserId = parseInt(localStorage.getItem('UserId'));

    if (!messages || messages.length === 0) {
        Container.innerHTML = '<div class="empty-chat">暂无消息</div>';
        return;
    }

    const Members = ChatData.Members || {};
    const MemberAvatars = ChatData.MemberAvatars || {};
   
    
    Container.innerHTML = messages.map(msg => {
        const isMine = parseInt(msg.SenderId) === UserId;
        const MsgId = msg.MsgId;
        const senderName = Members[msg.SenderId] || '已退出用户';
        const senderAvatar = senderName === '已退出用户' ? '/images/icon.png' : (MemberAvatars[msg.SenderId] || '/images/icon.png');
        const rowClass = isMine ? 'msg-row mine' : 'msg-row';
        const Time = formatTime(msg.Time || '');
// 发送人名字颜色：VIP/SVIP
let senderColor = '#8b7355';
const _vip = MemberVipStatus[msg.SenderId] || 'none';
if (_vip === 'svip') senderColor = '#dc2626';
else if (_vip === 'vip') senderColor = '#d4a017';

        let readCount = 0;
        if (isGroup && readList) {
            for (const [uid, rid] of Object.entries(readList)) {
                if (parseInt(rid) >= MsgId) readCount++;
       
            }
        }

        let body = '';
        switch (msg.Type) {
            
            case 'Message':
                body = `<div class="msg-text">${escapeHtml(msg.Text || '')}</div>`;
                break;
            case 'Image':
                if (msg.ImagePath) {
                    body = `<img src="${msg.ImagePath}" class="msg-image" onerror="this.outerHTML='<div class=\\'msg-text\\' style=\\'color:#c84628;\\'>图片不存在</div>'">`;
                } else {
                    body = `<div class="msg-text" style="color:#c84628;">图片不存在</div>`;
                }
                break;
            case 'SendCoin':
            case 'RequestCoin':
                const isDone = msg.IsReceive;
                const isSend = msg.Type === 'SendCoin';
                const coinLabel = isSend ? '赠送' : '索要';
                const coinColor = isSend ? '#4caf50' : '#ff9800';
                body = `
                    <div class="msg-coin">
                        <div style="font-size:11px;font-weight:600;color:${coinColor};margin-bottom:2px;">${coinLabel}</div>
                        <span class="msg-coin-icon">🪙</span>
                        <span class="msg-coin-num">${msg.Num || 0} 金币</span>
                        ${!isMine ? `<button class="msg-coin-btn ${isDone ? 'done' : ''}" onclick="parent.acceptCoin('${MsgId}', '${msg.Type}', ${msg.Num || 0})">${isDone ? '已领取' : '接受'}</button>` : ''}
                    </div>`;
                break;
            case 'InviteStudio':
                const isAlreadyIn = ChatData.MyTables && ChatData.MyTables.includes(msg.ConfigId || '');
                body = `
                    <div class="msg-studio">
                        <img src="icon.png" class="msg-studio-avatar" onerror="this.src='/images/icon.png'">
                        <div class="msg-studio-info">
                            <span class="msg-studio-name">${escapeHtml(msg.StudioName || '')}</span>
                            <span class="msg-studio-id">${escapeHtml(msg.ConfigId || '')}</span>
                        </div>
                        ${!isMine ? `<button class="msg-studio-btn ${isAlreadyIn ? 'done' : ''}" onclick="${isAlreadyIn ? '' : `parent.acceptStudio('${msg.ConfigId || ''}', '${msg.MsgId || ''}', '${(ChatData.ChatId || CurrentChatId || '')}')`}">${isAlreadyIn ? '已加入' : '加入'}</button>` : ''}
                    </div>`;
                break;
        }

        return `
            <div class="${rowClass}">
                <img src="avatars/${senderAvatar}" class="msg-avatar" onerror="this.src='/images/icon.png'">
                <div class="msg-col">
                    <div class="msg-bubble">
           ${!isMine ? `<div style="font-size:11px;font-weight:600;color:${senderColor};margin-bottom:3px;">${senderName}</div>` : ''}  
                        ${body}
                    </div>
                    <div class="msg-time">${Time}</div>
                    ${isGroup && readCount > 0 ? `<div class="msg-read ${readCount <= 1 ? 'zero' : ''}" onclick="parent.showReadList('${MsgId}')">${readCount}人已读</div>` : ''}
                </div>
            </div>`;
    }).join('');
}


function formatTime(timeStr) {
    if (!timeStr) return '';
    const parts = timeStr.split('-');
    if (parts.length < 6) return timeStr;
    const now = new Date();
    const y = parseInt(parts[0]), m = parseInt(parts[1]), d = parseInt(parts[2]);
    const h = parts[3], min = parts[4];
    if (y === now.getFullYear()) {
        return `${m}-${d} ${h}:${min}`;
    }
    return `${y}-${m}-${d} ${h}:${min}`;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// 发送文字
async function sendTextMessage() {
    const Input = document.getElementById('ChatInput');
    if (!Input) return;
    const Text = Input.value.trim();
    if (!Text) return;

    Input.value = '';
    await parentSendMsg('Message', { Text });
}

async function parentSendMsg(type, extraData) {
    const UserId = localStorage.getItem('UserId');
    const _formData = new FormData();
    _formData.append('UserId', UserId);
    _formData.append('ChatId', CurrentChatId);
    _formData.append('Type', type);

    for (const [k, v] of Object.entries(extraData)) {
        _formData.append(k, v);
    }

    try {
        const _res = await apiFetch('/chat/chat_send.php', { method: 'POST', body: _formData });
        const _data = await _res.json();
        if (_data.code === 0) {
            CurrentOffset = 0;
            openChatDetail(CurrentChatId);
        } else {
            alert(_data.message || '发送失败');
        }
    } catch (_err) {
        alert('网络错误');
    }
}
// Plus 菜单
function togglePlusMenu() {
    const _menu = document.getElementById('PlusMenu');
    const _overlay = document.getElementById('PlusOverlay');
    if (_menu) _menu.classList.toggle('show');
    if (_overlay) _overlay.classList.toggle('show');
}

function closePlusMenu() {
    const _menu = document.getElementById('PlusMenu');
    const _overlay = document.getElementById('PlusOverlay');
    if (_menu) _menu.classList.remove('show');
    if (_overlay) _overlay.classList.remove('show');
}

// 发送图片
function sendImage() {
    closePlusMenu();
    document.getElementById('ImageInput').click();
}

async function handleImageSend(event) {
    const _file = event.target.files[0];
    if (!_file) return;

    const UserId = localStorage.getItem('UserId');
    const _formData = new FormData();
    _formData.append('UserId', UserId);
    _formData.append('ChatId', CurrentChatId);
    _formData.append('Type', 'Image');
    _formData.append('Image', _file);

    try {
        const _res = await apiFetch('/chat/chat_send.php', { method: 'POST', body: _formData });
        const _data = await _res.json();
        if (_data.code === 0) {
            CurrentOffset = 0;
            openChatDetail(CurrentChatId);
        }
    } catch (_err) {}

    event.target.value = '';
}

// 金币相关（预留弹窗，暂用 prompt）
function sendCoinMenu() {
    closePlusMenu();
    const Num = prompt('请输入赠送金币数量：');
    if (Num && parseInt(Num) > 0) {
        parentSendMsg('SendCoin', { Num: parseInt(Num) });
    }
}

function requestCoinMenu() {
    closePlusMenu();
    const Num = prompt('请输入索要金币数量：');
    if (Num && parseInt(Num) > 0) {
        parentSendMsg('RequestCoin', { Num: parseInt(Num) });
    }
}

// 邀请工作室 — 弹出列表卡片
async function inviteStudioMenu() {
    const UserId = localStorage.getItem('UserId');
    if (!UserId) return;

    const _formData = new FormData();
    _formData.append('UserId', UserId);

    try {
        const _res = await apiFetch('/database/user_databases.php', { method: 'POST', body: _formData });
        const _data = await _res.json();

        if (_data.code === 0 && _data.data.length > 0) {
            showStudioPicker(_data.data);
        } else {
            alert('你没有可邀请的工作室');
        }
    } catch (_err) {
        alert('获取工作室列表失败');
    }
}

function showStudioPicker(studios) {
    // 移除旧弹窗
    const _old = document.getElementById('StudioPickerOverlay');
    if (_old) _old.remove();

    const _overlay = document.createElement('div');
    _overlay.id = 'StudioPickerOverlay';
    _overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.3);z-index:300;display:flex;align-items:flex-end;justify-content:center;';
    _overlay.onclick = function(e) { if (e.target === _overlay) _overlay.remove(); };

    let _html = '<div style="background:#fff;border-radius:20px 20px 0 0;padding:20px 16px 30px;width:100%;max-width:500px;max-height:60vh;overflow-y:auto;">';
    _html += '<div style="font-size:16px;font-weight:700;text-align:center;margin-bottom:16px;color:#1a1a1a;">选择工作室</div>';

    studios.forEach(s => {
        const _roleText = s.role === 0 ? '创建者' : (s.role === 1 ? '成员' : '个人');
        const _roleColor = s.role === 0 ? '#c84628' : (s.role === 1 ? '#4f6ef7' : '#999');
        _html += `
            <div style="display:flex;align-items:center;gap:12px;padding:14px;border-radius:14px;cursor:pointer;margin-bottom:6px;background:#faf7f2;"
                 onclick="selectStudio('${s.table}', '${s.name}')">
                <div style="width:44px;height:44px;border-radius:12px;background:#e8e3d9;display:flex;align-items:center;justify-content:center;font-size:20px;">🏢</div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:15px;font-weight:600;color:#1a1a1a;">${s.name}</div>
                    <div style="font-size:11px;color:#b8a48c;">${s.table}</div>
                </div>
                <span style="font-size:11px;font-weight:600;color:${_roleColor};background:${_roleColor}15;padding:4px 10px;border-radius:8px;">${_roleText}</span>
            </div>`;
    });

    _html += '<div style="text-align:center;margin-top:12px;"><button onclick="document.getElementById(\'StudioPickerOverlay\').remove()" style="padding:10px 40px;background:#e8e3d9;border:none;border-radius:12px;font-size:14px;cursor:pointer;">取消</button></div>';
    _html += '</div>';

    _overlay.innerHTML = _html;
    document.body.appendChild(_overlay);
}

function selectStudio(configId, studioName) {
    document.getElementById('StudioPickerOverlay').remove();
    parentSendMsg('InviteStudio', { ConfigId: configId, StudioName: studioName });
}
async function acceptCoin(msgId, type, num) {
    const label = type === 'SendCoin' ? '红包' : '索要';
    if (!confirm(`确定接受${label}的 ${num} 金币吗？`)) return;

    const _formData = new FormData();
    _formData.append('UserId', localStorage.getItem('UserId'));
    _formData.append('ChatId', CurrentChatId);
    _formData.append('MsgId', msgId);

    try {
        const _res = await apiFetch('/admin/coin_accept.php', { method: 'POST', body: _formData });
        const _data = await _res.json();
        if (_data.code === 0) {
            alert(`成功获得 ${_data.num} 金币！`);
            CurrentOffset = 0;
            openChatDetail(CurrentChatId);
        } else {
            alert(_data.message || '操作失败');
        }
    } catch (_err) {
        alert('网络错误');
    }
}


// 接受工作室邀请
async function acceptStudio(configId, msgId, chatId) {
    if (!confirm('确定加入该工作室吗？')) return;

    const _formData = new FormData();
    _formData.append('UserId', localStorage.getItem('UserId'));
    _formData.append('ConfigId', configId);
    _formData.append('ChatId', chatId);
    _formData.append('MsgId', msgId);

    try {
        const _res = await apiFetch('/database/studio_join.php', { method: 'POST', body: _formData });
        const _data = await _res.json();
        if (_data.code === 0) {
            alert('加入成功！');
        } else {
            alert(_data.message || '加入失败');
        }
    } catch (_err) {
        alert('网络错误');
    }
}

function showReadList(msgId) {
    if (!ChatData || !ChatData.ReadList || !ChatData.ReadUserList) return;

    const ReadList = ChatData.ReadList;
    const UserList = ChatData.ReadUserList;

    // 统计已读此消息的用户
    const ReadUsers = [];
    for (const [uid, lastReadId] of Object.entries(ReadList)) {
        if (parseInt(lastReadId) >= parseInt(msgId)) {
            ReadUsers.push(uid);
        }
    }

    if (ReadUsers.length === 0) {
        alert('暂无已读用户');
        return;
    }

    // 弹窗
    const _old = document.getElementById('ReadListPopup');
    if (_old) _old.remove();

    const _overlay = document.createElement('div');
    _overlay.id = 'ReadListPopup';
    _overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.3);z-index:300;display:flex;align-items:center;justify-content:center;';
    _overlay.onclick = function(e) { if (e.target === _overlay) _overlay.remove(); };

    let _html = '<div style="background:#fff;border-radius:20px;padding:20px;width:90%;max-width:320px;max-height:60vh;overflow-y:auto;">';
    _html += '<div style="font-size:16px;font-weight:700;text-align:center;margin-bottom:16px;">已读 · ' + ReadUsers.length + '人</div>';

    ReadUsers.forEach(uid => {
        const u = UserList[uid] || { name: '未知', avatar: '/images/icon.png' };
        _html += `
            <div style="display:flex;align-items:center;gap:10px;padding:10px 0;">
                <img src="avatars/${u.avatar}" style="width:36px;height:36px;border-radius:10px;object-fit:cover;background:#e8e3d9;" onerror="this.src='/images/icon.png'">
                <span style="font-size:14px;font-weight:500;">${u.name}</span>
            </div>`;
    });

    _html += '<div style="text-align:center;margin-top:12px;"><button onclick="document.getElementById(\'ReadListPopup\').remove()" style="padding:8px 32px;background:#e8e3d9;border:none;border-radius:10px;font-size:14px;cursor:pointer;">关闭</button></div>';
    _html += '</div>';

    _overlay.innerHTML = _html;
    document.body.appendChild(_overlay);
}
// 打开群聊详情（预留）
function openGroupDetail() {
    alert('群聊详情功能后续完善');
}

// 修改 Main_Send 的消息点击跳转
function openChat(chatId) {
    openChatDetail(chatId);
}

// 返回 Send 列表并刷新
function backToSendList() {
    delete subPageCache['Main_Send.html'];
    loadSubPage('Main_Send.html').then(() => {
        loadMsgList();
    });
}

