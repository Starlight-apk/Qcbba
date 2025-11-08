// 通用选择器
const $ = id => document.getElementById(id);

/* ---------- 轻量 toast ---------- */
function toast(msg) {
  const t = document.createElement('div');
  t.className = 'toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 2500);
}

/* ---------- 后端地址 ---------- */
const API = 'http://123.60.174.101:48053/login/api.php';

/* ---------- 登录 ---------- */
$('loginForm').addEventListener('submit', async e => {
  e.preventDefault();
  const user = $('username').value.trim();
  const key  = $('key').value.trim();
  if (!user || !key) return toast('请填写完整');

  const res = await fetch(`${API}?action=login`, {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({username:user, key:key})
  }).then(r=>r.json());

  if (res.ok) {
    ['token','uid','username','role'].forEach(k=>localStorage.setItem(k,res[k]||user));
    alert('登录成功！');
    location.href = 'http://123.60.174.101:48053/index.html';
  } else {
    toast(res.msg);
  }
});

/* ---------- 注册 ---------- */
$('regLink').addEventListener('click', async e => {
  e.preventDefault();
  const user = $('username').value.trim();
  if (!user) return toast('请输入用户名');
  const res = await fetch(`${API}?action=register`, {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({username:user})
  }).then(r=>r.json());

  if (res.ok) {
    alert('注册成功！请联系管理员获取密钥。');
    toast('注册成功，请联系管理员获取密钥！');
    $('key').value = '';
  } else {
    toast(res.msg);
  }
});

/* ---------- 打开改密弹窗 ---------- */
$('changePwdLink').addEventListener('click', e => {
  e.preventDefault();
  $('changePwdDialog').showModal();
});

/* ---------- 提交改密 ---------- */
$('changePwdForm').addEventListener('submit', async e => {
  e.preventDefault();
  const user  = $('cpUser').value.trim();
  const oldPwd= $('cpOld').value.trim();
  const newPwd= $('cpNew').value.trim();
  const newPwd2=$('cpNew2').value.trim();

  if (!user || !oldPwd || !newPwd || !newPwd2) return toast('请填写完整');
  if (newPwd !== newPwd2)               return toast('两次新密码不一致');
  if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d]{8,}$/.test(newPwd))
                                        return toast('新密码至少 8 位，且包含大小写字母+数字');

  const res = await fetch(`${API}?action=changePwd`, {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({username:user, old:oldPwd, new:newPwd})
  }).then(r=>r.json());

  if (res.ok) {
    toast('密码修改成功，请用新密码重新登录！');
    $('changePwdDialog').close();
    $('changePwdForm').reset();
  } else {
    toast(res.msg);
  }
});
