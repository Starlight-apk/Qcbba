/* user.js - 主页登录状态渲染 */
(function(){
const loginBtn = document.getElementById('loginBtn');
const userInfo = document.getElementById('userInfo');
const userName = document.getElementById('userName');
const userId   = document.getElementById('userId');
const logoutBtn= document.getElementById('logoutBtn');

function render(){
  const t = localStorage.getItem('token'),
        u = localStorage.getItem('username'),
        i = localStorage.getItem('uid');
  if(t&&u&&i){
    loginBtn.style.display = 'none';
    userInfo.style.display = 'flex';
    userName.textContent = u;
    userId.textContent = i;
    logoutBtn.style.display = 'inline-block';
  }else{
    loginBtn.style.display = 'inline-flex';
    userInfo.style.display = 'none';
    logoutBtn.style.display = 'none';
  }
}

window.logout = function(){
  localStorage.clear();
  render();
  const toast = document.createElement('div');
  toast.className = 'toast pop-modal';
  toast.textContent = '已退出';
  document.body.appendChild(toast);
  setTimeout(()=>toast.remove(),2000);
};

render();
})();
