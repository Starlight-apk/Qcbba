// userhtml.js - 用户中心页面专用
(function () {
  const userNameEl = document.getElementById('userName');
  const userIdEl = document.getElementById('userId');
  const roleBadgeEl = document.getElementById('roleBadge');

  const username = localStorage.getItem('username');
  const uid = localStorage.getItem('uid');
  const token = localStorage.getItem('token');

  // 如果未登录，跳转到登录页
  if (!token || !username || !uid) {
    window.location.href = 'http://123.60.174.101:48053/login.html';
    return;
  }

  // 显示用户信息
  userNameEl.textContent = username;
  userIdEl.textContent = uid;

  // 判断是否为管理员（根据 uid 判断）
  const isAdmin = uid === '1'; // uid 为 1 是管理员
  if (isAdmin) {
    roleBadgeEl.className = 'admin-badge';
    roleBadgeEl.textContent = '管理员';
  } else {
    roleBadgeEl.className = 'user-badge';
    roleBadgeEl.textContent = '普通用户';
  }
})();