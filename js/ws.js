// ========== 实时消息推送（WebSocket） ==========
// 连接 tools/ws_server.php 守护进程（端口 2081），登录后自动建立；
// 断线自动重连（指数退避），切回前台时自动补连。
var LqWS = (function () {
    var ws = null;
    var retryMs = 1000;
    var hbTimer = null;
    var wsPort = 2081;

    function buildUrl() {
        var proto = location.protocol === 'https:' ? 'wss://' : 'ws://';
        return proto + location.hostname + ':' + wsPort + '/ws'
            + '?uid=' + encodeURIComponent(localStorage.getItem('UserId') || '')
            + '&token=' + encodeURIComponent(localStorage.getItem('SessionToken') || '');
    }

    function connect() {
        if (!localStorage.getItem('UserId') || !localStorage.getItem('SessionToken')) {
            setTimeout(connect, 5000); // 未登录，稍后再试
            return;
        }
        if (ws && (ws.readyState === 0 || ws.readyState === 1)) return;
        try {
            ws = new WebSocket(buildUrl());
        } catch (e) {
            schedule();
            return;
        }
        ws.onopen = function () {
            retryMs = 1000;
            startHeartbeat();
            resync(); // 重连成功：拉取断线期间漏掉的消息
        };
        ws.onmessage = function (e) {
            var d;
            try { d = JSON.parse(e.data); } catch (_) { return; }
            dispatch(d);
        };
        ws.onclose = function () {
            stopHeartbeat();
            schedule();
        };
        ws.onerror = function () {
            try { ws.close(); } catch (_) {}
        };
    }

    function schedule() {
        setTimeout(connect, retryMs);
        retryMs = Math.min(retryMs * 2, 30000);
    }

    function startHeartbeat() {
        stopHeartbeat();
        hbTimer = setInterval(function () {
            if (ws && ws.readyState === 1) ws.send('{"t":"ping"}');
        }, 25000);
    }

    function stopHeartbeat() {
        if (hbTimer) { clearInterval(hbTimer); hbTimer = null; }
    }

    // 断线补偿：直接从服务端拉最新数据，补回漏掉的推送
    function resync() {
        if (typeof refreshCurrentChat === 'function' && document.getElementById('ChatMessages')
            && typeof CurrentChatId !== 'undefined' && CurrentChatId) {
            refreshCurrentChat();
        }
        if (typeof loadMsgList === 'function' && document.getElementById('MsgList')) {
            loadMsgList();
        }
    }

    // 事件分发：当前打开的聊天 → 刷新消息区；否则刷新会话列表
    function dispatch(d) {
        if (!d || typeof d !== 'object') return;
        var cid = d.chat_id || '';
        var viewing = (typeof CurrentChatId !== 'undefined') && CurrentChatId && cid === CurrentChatId
            && document.getElementById('ChatMessages');
        if ((d.type === 'new_msg' || d.type === 'chat_refresh') && viewing) {
            if (typeof refreshCurrentChat === 'function') refreshCurrentChat();
            return;
        }
        if (typeof loadMsgList === 'function' && document.getElementById('MsgList')) {
            loadMsgList();
        }
    }

    // 切回前台：连接活着也要补拉一次（后台时推送可能已丢），断了就重连
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) return;
        if (!ws || ws.readyState === 3 || ws.readyState === 2) {
            retryMs = 1000;
            connect();
        } else {
            resync();
        }
    });

    return { connect: connect };
})();
