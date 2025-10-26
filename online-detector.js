// online-and-status.js  ← 引入即可，3 秒刷新玩家+顺带状态
(function refreshBoth() {
  const BACKEND = 'http://localhost:3001/ping'; // 你的后端聚合接口
  const REFRESH = 3000; // 3 秒

  const $ = s => document.querySelector(s);
  const playerGrid = $('#playerGrid');
  const serverGrid = $('#serverGrid');
  if (!playerGrid || !serverGrid) return;

  function log(msg) { console.log(`[both] ${msg}`); }

  /* =====  1. 玩家列表渲染  ===== */
  function renderPlayers(list = []) {
    playerGrid.innerHTML = '';
    if (!Array.isArray(list) || list.length === 0) {
      playerGrid.innerHTML = '<div class="card no-player">当前无乘员在线</div>';
      return;
    }
    list.forEach(name => {
      const card = document.createElement('div');
      card.className = 'card player-card';
      card.innerHTML = `
        <img class="player-avatar" src="https://minotar.net/avatar/${name}/48.png" alt="${name}" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
        <div class="player-head-fallback" style="background:#${intToRGB(hashCode(name))}; display:none;"></div>
        <span class="player-name">${name}</span>
      `;
      playerGrid.appendChild(card);
    });
  }

  /* =====  2. 服务器状态渲染（与之前一致） ===== */
  function renderStatus(data = {}) {
    const { online = false, starting = false, version = '未知', players = [], latency = '?' } = data;
    let state = online ? 'online' : (starting ? 'starting' : 'offline');
    serverGrid.innerHTML = '';
    const card = document.createElement('div');
    card.className = 'card';
    const statusTag = {
      online: '<span class="tag green">在线</span>',
      starting: '<span class="tag yellow">正在启动</span>',
      offline: '<span class="tag red">离线</span>'
    };
    const statusDotCls = { online: 'online', starting: 'starting', offline: 'offline' };

    card.innerHTML = `
      <div class="node-header">
        <span class="status-dot ${statusDotCls[state]}"></span>
        <div>
          <div class="node-name">RainNode</div>
          <div class="node-addr">im.rainplay.cn:12159</div>
        </div>
      </div>
      <div class="node-body">
        <div class="node-row"><span>状态</span>${statusTag[state]}</div>
        ${state === 'online' ? `
          <div class="node-row"><span>版本</span><span>${version}</span></div>
          <div class="node-row"><span>在线人数</span><span>${players.length}/${data.playersMax || '?'}</span></div>
          <div class="node-row"><span>延迟</span><span>${latency} ms</span></div>
        ` : `<div class="offline-tip">该服务器未开启</div>`}
      </div>
      <div class="node-foot">
        <button class="btn-small" onclick="navigator.clipboard.writeText('im.rainplay.cn:12159')"><i class="fa fa-copy"></i> 复制地址</button>
        <span class="update-time">更新于 ${new Date().toLocaleTimeString()}</span>
      </div>
    `;
    serverGrid.appendChild(card);
  }

  /* =====  3. 一次请求，回来拆两份  ===== */
  async function refreshBoth() {
    try {
      const res = await fetch(BACKEND);
      const data = await res.json();
      renderPlayers(data.players || []);     // 填玩家栏
      renderStatus(data);                    // 填状态卡片
      log(`一次请求完成 - 在线:${data.online} | 玩家:${data.players?.length || 0} 人`);
    } catch (e) {
      log(`请求失败 - ${e.message}`);
      renderPlayers([]);
      renderStatus({ online: false, players: [] });
    }
  }

  /* =====  4. 3 秒循环  ===== */
  refreshBoth();
  setInterval(refreshBoth, REFRESH);
  log('一次请求 3 秒刷新已启动（玩家+状态一起更新）');
})();
