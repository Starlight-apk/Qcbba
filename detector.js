// detector-dual-final.js  ← 覆盖原 detector.js
(function dualFinal() {
  const TARGET = { host: 'im.rainplay.cn', port: 12159 };
  const CHECK_PERIOD = 1000; // 1 秒

  /* =====  双 API 地址  ===== */
  const A_API = `https://api.mcsrvstat.us/2/${TARGET.host}:${TARGET.port}`; // 详情
  const B_API = `https://uapis.cn/api/v1/game/minecraft/serverstatus?server=${TARGET.host}:${TARGET.port}`; // 实时 TCP

  const $ = s => document.querySelector(s);
  const grid = $('#serverGrid');
  if (!grid) return;

  function log(msg) { console.log(`[dual-final] ${msg}`); }

  /* 渲染：含「正在启动」态 */
  function renderCard(state = 'offline', info = {}) {
    grid.innerHTML = '';
    const card = document.createElement('div');
    card.className = 'card';
    const statusTag = {
      online: '<span class="tag green">在线</span>',
      offline: '<span class="tag red">离线</span>',
      starting: '<span class="tag yellow">正在启动</span>'
    };
    const statusDotCls = { online: 'online', offline: 'offline', starting: 'starting' };

    card.innerHTML = `
      <div class="node-header">
        <span class="status-dot ${statusDotCls[state]}"></span>
        <div>
          <div class="node-name">RainNode</div>
          <div class="node-addr">${TARGET.host}:${TARGET.port}</div>
        </div>
      </div>
      <div class="node-body">
        <div class="node-row"><span>状态</span>${statusTag[state]}</div>
        ${state === 'online' ? `
          <div class="node-row"><span>版本</span><span>${info.version || '未知'}</span></div>
          <div class="node-row"><span>在线人数</span><span>${info.players?.online || 0}/${info.players?.max || '?'}</span></div>
          <div class="node-row"><span>延迟</span><span>${info.latency ?? '?'} ms</span></div>
        ` : ``}
      </div>
      <div class="node-foot">
        <button class="btn-small" onclick="navigator.clipboard.writeText('${TARGET.host}:${TARGET.port}')"><i class="fa fa-copy"></i> 复制地址</button>
        <span class="update-time">更新于 ${new Date().toLocaleTimeString()}</span>
      </div>
    `;
    grid.appendChild(card);
  }

  /* =====  A 路：mcsrvstat 拿详情  ===== */
  async function fetchA() {
    try {
      const res = await fetch(A_API);
      return await res.json();
    } catch {
      return {};
    }
  }

  /* =====  B 路：uapis.cn 实时 TCP 握手  ===== */
  async function fetchB() {
    try {
      const res = await fetch(B_API);
      const json = await res.json();
      // 接口返回示例：{"online":true,"version":"1.21.8","players":{"online":2,"max":20}}
      return json.online === true;
    } catch {
      return false;
    }
  }

  /* =====  主流程：双路合并  ===== */
  async function check() {
    const [aData, bOnline] = await Promise.all([fetchA(), fetchB()]);
    const aOnline = Boolean(aData.online);
    let state = 'offline';

    if (bOnline && aOnline) state = 'online';
    else if (bOnline && !aOnline) state = 'starting'; // TCP 通但 MC 层未就绪
    else state = 'offline';

    renderCard(state, aData);
    log(`B:TCP-${bOnline} | A:mcsrv-${aOnline} → 最终:${state}`);
  }

  /* =====  每秒双检  ===== */
  check();
  setInterval(check, CHECK_PERIOD);
  log('双 API 最终检测已启动（mcsrvstat + uapis.cn）');
})();
