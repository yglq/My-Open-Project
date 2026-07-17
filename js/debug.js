// ========== Debug Panel ==========
(function () {
  'use strict';

  // --- 数据存储 ---
  const logs = [];
  const requests = [];
  const errors = [];

  // --- 创建 UI ---
  const panel = document.createElement('div');
  panel.id = 'debug-panel';
  Object.assign(panel.style, {
    position: 'fixed',
    top: '0',
    left: '0',
    right: '0',
    bottom: '0',
    zIndex: '99999',
    background: '#1a1a2e',
    color: '#eee',
    fontFamily: 'monospace',
    fontSize: '12px',
    display: 'none',
    flexDirection: 'column',
    overflow: 'hidden'
  });

  const header = document.createElement('div');
  Object.assign(header.style, {
    padding: '8px 12px',
    background: '#16213e',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderBottom: '1px solid #0f3460'
  });
  header.innerHTML = `
    <div style="display:flex;gap:8px;" id="debug-tabs">
      <span data-tab="log" class="debug-tab active" style="padding:4px 12px;border-radius:6px;cursor:pointer;background:#0f3460;">日志</span>
      <span data-tab="request" class="debug-tab" style="padding:4px 12px;border-radius:6px;cursor:pointer;">请求</span>
      <span data-tab="error" class="debug-tab" style="padding:4px 12px;border-radius:6px;cursor:pointer;">错误</span>
      <span data-tab="var" class="debug-tab" style="padding:4px 12px;border-radius:6px;cursor:pointer;">变量</span>
    </div>
    <span id="debug-close" style="cursor:pointer;font-size:18px;padding:0 8px;">✕</span>
  `;

  const body = document.createElement('div');
  body.id = 'debug-body';
  Object.assign(body.style, {
    flex: '1',
    overflow: 'auto',
    padding: '8px 12px',
    whiteSpace: 'pre-wrap',
    wordBreak: 'break-all'
  });

  panel.appendChild(header);
  panel.appendChild(body);
  document.body.appendChild(panel);

  // --- 悬浮开关 ---
  const toggle = document.createElement('div');
  toggle.id = 'debug-toggle';
  toggle.textContent = '调试';
  Object.assign(toggle.style, {
    position: 'fixed',
    top: '6px',
    right: '10px',
    zIndex: '99998',
    background: '#4f6ef7',
    color: '#fff',
    padding: '4px 10px',
    borderRadius: '6px',
    fontSize: '12px',
    cursor: 'pointer',
    fontWeight: 'bold',
    userSelect: 'none',
    boxShadow: '0 2px 8px rgba(0,0,0,0.3)'
  });
  document.body.appendChild(toggle);

  // --- 事件 ---
  let visible = false;
  toggle.addEventListener('click', () => {
    visible = !visible;
    panel.style.display = visible ? 'flex' : 'none';
    if (visible) refreshTab(currentTab);
  });
  document.getElementById('debug-close').addEventListener('click', () => {
    visible = false;
    panel.style.display = 'none';
  });

  let currentTab = 'log';
  document.getElementById('debug-tabs').addEventListener('click', (e) => {
    if (e.target.classList.contains('debug-tab')) {
      currentTab = e.target.dataset.tab;
      document.querySelectorAll('.debug-tab').forEach(t => t.classList.remove('active'));
      e.target.classList.add('active');
      refreshTab(currentTab);
    }
  });

  // --- Hook console ---
  const _console = { log: console.log, error: console.error, warn: console.warn };
  function addLog(level, ...args) {
    const msg = args.map(a => (typeof a === 'object' ? JSON.stringify(a, null, 2) : String(a))).join(' ');
    logs.push({ time: new Date().toISOString().slice(11, 23), level, msg });
    if (logs.length > 500) logs.shift();
    if (visible && currentTab === 'log') refreshTab('log');
  }
  console.log = (...args) => { _console.log.apply(console, args); addLog('log', ...args); };
  console.error = (...args) => {
    _console.error.apply(console, args);
    const msg = args.map(a => (typeof a === 'object' ? JSON.stringify(a, null, 2) : String(a))).join(' ');
    errors.push({ time: new Date().toISOString().slice(11, 23), msg });
    if (errors.length > 200) errors.shift();
    addLog('error', ...args);
    if (visible && (currentTab === 'log' || currentTab === 'error')) refreshTab(currentTab);
  };
  console.warn = (...args) => { _console.warn.apply(console, args); addLog('warn', ...args); };

  // --- Hook fetch ---
  const _fetch = window.fetch;
  window.fetch = async function (url, options) {
    const start = performance.now();
    const method = (options && options.method) || 'GET';
    const reqBody = (options && options.body) ? tryParseBody(options.body) : null;
    let resp = null;
    let error = null;
    let status = 0;
    try {
      resp = await _fetch.apply(this, arguments);
      status = resp.status;
    } catch (e) {
      error = e.message;
    }
    const elapsed = Math.round(performance.now() - start);
    let respClone = null;
    try {
      respClone = resp ? resp.clone() : null;
    } catch (_) {}
    let respBody = null;
    if (respClone) {
      try { respBody = await respClone.text(); } catch (_) {}
      try { const j = JSON.parse(respBody); respBody = JSON.stringify(j, null, 2); } catch (_) {}
    }
    const entry = { time: new Date().toISOString().slice(11, 23), method, url: String(url), status, elapsed, reqBody, respBody, error };
    requests.unshift(entry);
    if (requests.length > 100) requests.pop();
    if (visible && currentTab === 'request') refreshTab('request');
    return resp;
  };

  function tryParseBody(body) {
    if (!body) return null;
    if (typeof body === 'string') return body;
    if (body instanceof FormData) {
      const obj = {};
      for (const [k, v] of body.entries()) obj[k] = v instanceof File ? `[File: ${v.name}]` : v;
      return JSON.stringify(obj);
    }
    return String(body);
  }

  // --- 全局错误 ---
  window.addEventListener('error', (e) => {
    errors.push({ time: new Date().toISOString().slice(11, 23), msg: `${e.message} at ${e.filename}:${e.lineno}` });
    if (errors.length > 200) errors.shift();
    if (visible && currentTab === 'error') refreshTab('error');
  });
  window.addEventListener('unhandledrejection', (e) => {
    errors.push({ time: new Date().toISOString().slice(11, 23), msg: `Unhandled: ${e.reason}` });
    if (errors.length > 200) errors.shift();
    if (visible && currentTab === 'error') refreshTab('error');
  });

  // --- 渲染 ---
  function refreshTab(tab) {
    const body = document.getElementById('debug-body');
    if (tab === 'log') {
      body.innerHTML = logs.map(l => {
        const color = l.level === 'error' ? '#dc2626' : l.level === 'warn' ? '#f59e0b' : '#93c5fd';
        return `<div style="margin-bottom:4px;border-bottom:1px solid #333;padding:2px 0;">
          <span style="color:#888;">${l.time}</span>
          <span style="color:${color};margin:0 6px;">[${l.level.toUpperCase()}]</span>
          <span>${escapeHtml(l.msg)}</span>
        </div>`;
      }).reverse().join('');
      body.scrollTop = body.scrollHeight;
    } else if (tab === 'request') {
      body.innerHTML = requests.map(r => `<div style="margin-bottom:8px;border-bottom:1px solid #333;padding:4px 0;">
        <div><span style="color:#888;">${r.time}</span> <span style="color:#f59e0b;">${r.method}</span> <span style="color:#93c5fd;">${r.url}</span> <span style="color:${r.status >= 400 ? '#dc2626' : '#22c55e'};">${r.status || 'ERR'} ${r.elapsed}ms</span>${r.error ? ' <span style="color:#dc2626;">'+escapeHtml(r.error)+'</span>' : ''}</div>
        ${r.reqBody ? '<div style="margin-left:12px;color:#aaa;">▶ Body: '+escapeHtml(String(r.reqBody).slice(0,300))+'</div>' : ''}
        ${r.respBody ? '<div style="margin-left:12px;color:#6b7280;">◀ Res: '+escapeHtml(String(r.respBody).slice(0,500))+'</div>' : ''}
      </div>`).join('');
    } else if (tab === 'error') {
      body.innerHTML = errors.length === 0 ? '<div style="color:#888;">无错误</div>' : errors.map(e => `<div style="margin-bottom:4px;border-bottom:1px solid #333;padding:2px 0;">
        <span style="color:#888;">${e.time}</span> <span style="color:#dc2626;">${escapeHtml(e.msg)}</span>
      </div>`).reverse().join('');
    } else if (tab === 'var') {
      let html = '<div style="margin-bottom:12px;font-weight:bold;color:#f59e0b;">localStorage</div>';
      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        let val = localStorage.getItem(key);
        if (val && val.length > 200) val = val.slice(0, 200) + '...';
        html += `<div style="margin-bottom:3px;"><span style="color:#93c5fd;">${escapeHtml(key)}</span> = <span style="color:#6b7280;">${escapeHtml(val)}</span></div>`;
      }
      body.innerHTML = html;
    }
  }

  function escapeHtml(s) {
    return String(s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"');
  }

  // 初始日志
  addLog('log', '调试面板已就绪');
})();