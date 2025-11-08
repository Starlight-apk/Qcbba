<?php
// friends_system.php - 一站式好友系统
session_start();

// 如果是API请求，返回JSON；否则返回HTML页面
if (isset($_POST['action']) || (isset($_GET['action']) && $_GET['action'] === 'get_messages')) {
    header('Content-Type: application/json; charset=utf-8');
    handleApiRequest();
} else {
    header('Content-Type: text/html; charset=utf-8');
    displayHtmlPage();
}

function handleApiRequest() {
    global $currentUserId, $currentUsername, $friendsDir, $usersDir;
    
    // 检查用户是否登录
    if (!isset($_SESSION['uid']) || !isset($_SESSION['username'])) {
        echo json_encode(['success' => false, 'message' => '未登录']);
        exit;
    }

    $currentUserId = $_SESSION['uid'];
    $currentUsername = $_SESSION['username'];

    // 好友系统文件路径
    $friendsDir = $_SERVER['DOCUMENT_ROOT'] . '/Q/Q/Friends/';
    $usersDir = '/login/user/';

    // 确保好友目录存在
    if (!is_dir($friendsDir)) {
        mkdir($friendsDir, 0777, true);
    }

    $action = $_POST['action'] ?? $_GET['action'];
    
    switch ($action) {
        case 'send_friend_request':
            sendFriendRequest();
            break;
        case 'accept_friend_request':
            acceptFriendRequest();
            break;
        case 'reject_friend_request':
            rejectFriendRequest();
            break;
        case 'send_message':
            sendMessage();
            break;
        case 'get_messages':
            getMessages();
            break;
        case 'get_friend_list':
            getFriendList();
            break;
        case 'get_pending_requests':
            getPendingRequests();
            break;
        case 'get_available_friends':
            getAvailableFriends();
            break;
        default:
            echo json_encode(['success' => false, 'message' => '未知操作']);
    }
}

function displayHtmlPage() {
    // 检查用户是否登录
    if (!isset($_SESSION['uid']) || !isset($_SESSION['username'])) {
        header('Location: http://123.60.174.101:48053/login.html');
        exit;
    }
    
    $currentUserId = $_SESSION['uid'];
    $currentUsername = $_SESSION['username'];
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>好友列表 · Qcbba</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --bg: #f5f7fa;
    --card: #fff;
    --text: #222;
    --primary: #00c6ff;
    --radius: 14px;
    --shadow: 0 8px 32px rgba(0,0,0,.08);
    --transition: all .3s ease;
}
[data-theme="dark"] {
    --bg: #0f1116;
    --card: #1a1d24;
    --text: #e6e6e6;
    --shadow: 0 8px 32px rgba(0,0,0,.25);
}
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    user-select: none;
}
body {
    font-family: 'Inter', sans-serif;
    background: var(--bg);
    color: var(--text);
    transition: var(--transition);
    font-size: 16px;
    line-height: 1.5;
    overflow-x: hidden;
}
a { color: inherit; text-decoration: none; }
button {
    cursor: pointer;
    font: inherit;
    border: none;
    background: none;
    -webkit-appearance: none;
    border-radius: 0;
}
.navbar {
    position: sticky;
    top: 0;
    z-index: 100;
    backdrop-filter: blur(12px);
    background: rgba(255,255,255,.7);
    border-bottom: 1px solid rgba(0,0,0,.08);
    padding: 0.5rem 0;
}
.nav-container {
    max-width: 1000px;
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 1rem;
}
.logo {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-weight: 700;
    color: var(--primary);
    font-size: 1.1rem;
}
#userRom {
    display: flex;
    align-items: center;
    gap: .6rem;
}
.menu-toggle, .theme-toggle {
    font-size: 1.3rem;
    color: var(--text);
    padding: 0.5rem;
    min-width: 44px;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.side-menu {
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    width: 280px;
    max-width: 85vw;
    background: var(--card);
    box-shadow: var(--shadow);
    transform: translateX(-100%);
    transition: transform .3s;
    z-index: 200;
}
.side-menu.active { transform: translateX(0); }
.side-menu ul {
    list-style: none;
    padding: 4rem 1.5rem 2rem;
}
.side-menu li {
    margin-bottom: 1rem;
    position: relative;
}
.side-menu a {
    display: flex;
    align-items: center;
    gap: .8rem;
    padding: 1rem 0;
    transition: color .2s;
    font-size: 1.1rem;
    min-height: 50px;
}
.side-menu a.active {
    color: var(--primary);
    font-weight: 600;
}
.overlay {
    position: fixed;
    inset: 0;
    background: #0003;
    opacity: 0;
    visibility: hidden;
    transition: opacity .3s;
    z-index: 150;
}
.overlay.show { opacity: 1; visibility: visible; }
.friend-badge {
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    background: #ff4757;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.friends-container {
    max-width: 1000px;
    margin: 1rem auto;
    padding: 0 1rem;
}
.friends-header {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.friends-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.header-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.header-buttons button {
    padding: 0.8rem 1rem;
    border-radius: var(--radius);
    font-size: 0.9rem;
    min-height: 44px;
    flex: 1;
    min-width: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}
#addFriendBtn, #availableFriendsBtn {
    background: var(--primary);
    color: white;
}
#viewRequestsBtn {
    background: var(--bg);
    color: var(--text);
    border: 1px solid rgba(0,0,0,0.1);
}
.friends-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.friend-card {
    background: var(--card);
    border-radius: var(--radius);
    padding: 1.2rem;
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: var(--transition);
    cursor: pointer;
    min-height: 80px;
}
.friend-card:active { transform: scale(0.98); }
.avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 700;
    font-size: 1.3rem;
    flex-shrink: 0;
}
.info {
    flex: 1;
    min-width: 0;
}
.name {
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 0.3rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.status {
    font-size: 0.9rem;
    opacity: .7;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.online { background: #4caf50; box-shadow: 0 0 6px #4caf50; }
.offline { background: #9e9e9e; }
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text);
    opacity: 0.7;
}
.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}
.footer {
    margin-top: 4rem;
    padding: 2rem 1rem;
    text-align: center;
    font-size: 0.9rem;
    opacity: .6;
}
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    transition: opacity .35s;
    padding: 1rem;
}
.modal-overlay.show { opacity: 1; visibility: visible; }
.modal-box {
    position: relative;
    width: 100%;
    max-width: 400px;
    max-height: 80vh;
    background: var(--card);
    border-radius: var(--radius);
    padding: 1.5rem;
    box-shadow: 0 12px 40px rgba(0,0,0,.25);
    transform: scale(.65);
    transition: transform .45s cubic-bezier(.68,-.55,.27,1.55);
    overflow-y: auto;
}
.modal-overlay.show .modal-box { transform: scale(1); }
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}
.modal-header h3 { font-size: 1.2rem; font-weight: 600; }
.modal-close {
    font-size: 1.5rem;
    color: var(--text);
    transition: transform .2s;
    padding: 0.5rem;
    min-width: 44px;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 1000;
    position: relative;
}
.modal-close:active { transform: rotate(90deg) scale(1.1); }
.input-group {
    display: flex;
    align-items: center;
    gap: .6rem;
    background: var(--bg);
    border: 1px solid rgba(0,0,0,.1);
    border-radius: var(--radius);
    padding: 1rem;
    margin-bottom: 1rem;
    transition: var(--transition);
}
.input-group:focus-within { border-color: var(--primary); }
.input-group i { color: var(--primary); }
.input-group input {
    flex: 1;
    border: none;
    outline: none;
    background: transparent;
    color: var(--text);
    font-size: 1rem;
    -webkit-user-select: text;
    user-select: text;
}
.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: .8rem;
    margin-top: 1.5rem;
}
.modal-actions button {
    padding: 1rem 1.5rem;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 1rem;
    min-height: 44px;
    min-width: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.btn-primary { background: var(--primary); color: #fff; }
.btn-secondary { background: var(--bg); color: var(--text); border: 1px solid rgba(0,0,0,0.1); }
.request-list {
    max-height: 50vh;
    overflow-y: auto;
    margin-bottom: 1rem;
}
.request-item {
    background: var(--bg);
    border-radius: var(--radius);
    padding: 1.2rem;
    margin-bottom: 0.8rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}
.request-info { flex: 1; min-width: 0; }
.request-username {
    font-weight: 600;
    margin-bottom: 0.3rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.request-time { font-size: 0.8rem; opacity: 0.7; }
.request-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}
.request-accept, .request-reject {
    padding: 0.8rem 1rem;
    border-radius: var(--radius);
    font-size: 0.9rem;
    min-height: 44px;
    min-width: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.request-accept { background: #4CAF50; color: white; }
.request-reject { background: #f44336; color: white; }
/* 聊天弹窗 */
.chat-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: opacity .35s ease, visibility .35s ease;
    padding: 1rem;
}
.chat-modal-overlay.show { opacity: 1; visibility: visible; }
.chat-modal-box {
    position: relative;
    width: 95%;
    max-width: 500px;
    height: 80vh;
    max-height: 700px;
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: 0 12px 40px rgba(0,0,0,.25);
    overflow: hidden;
    transform: scale(.65);
    transition: transform .45s cubic-bezier(.68,-.55,.27,1.55);
    display: flex;
    flex-direction: column;
}
.chat-modal-overlay.show .chat-modal-box { transform: scale(1); }
.chat-modal-box.hide {
    transform: scale(.65);
    opacity: 0;
    transition: transform .35s cubic-bezier(.55,.06,.68,.19), opacity .25s ease;
}
.chat-header {
    background: var(--primary);
    color: white;
    padding: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.chat-user-info {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}
.chat-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
}
.chat-user-details .chat-username { font-weight: 600; font-size: 1.1rem; }
.chat-user-details .chat-status { font-size: 0.8rem; opacity: 0.9; }
.chat-close {
    background: rgba(255,255,255,0.2);
    color: white;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background .2s;
}
.chat-close:hover { background: rgba(255,255,255,0.3); }
.chat-messages {
    flex: 1;
    padding: 1rem;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.message {
    max-width: 80%;
    padding: 0.8rem 1rem;
    border-radius: 18px;
    position: relative;
    word-wrap: break-word;
}
.message.self {
    align-self: flex-end;
    background: var(--primary);
    color: white;
    border-bottom-right-radius: 6px;
}
.message.other {
    align-self: flex-start;
    background: var(--bg);
    color: var(--text);
    border-bottom-left-radius: 6px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}
.message-content {
    margin: 0;
    white-space: pre-wrap;
    word-break: break-word;
    font-family: inherit;
}
.message-time {
    font-size: 0.7rem;
    opacity: 0.7;
    margin-top: 0.3rem;
    text-align: right;
}
.message.other .message-time { text-align: left; }
.chat-input-area {
    background: var(--bg);
    padding: 1rem;
    border-top: 1px solid rgba(0,0,0,0.1);
    display: flex;
    gap: 0.8rem;
    align-items: flex-end;
}
.chat-input {
    flex: 1;
    padding: 0.8rem 1rem;
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 24px;
    background: var(--card);
    color: var(--text);
    font-size: 1rem;
    resize: none;
    min-height: 44px;
    max-height: 120px;
    font-family: inherit;
}
.chat-send {
    background: var(--primary);
    color: white;
    border: none;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    transition: transform .2s;
}
.chat-send:active { transform: scale(0.95); }
@media (max-width: 768px) {
    .nav-container { padding: 0 0.8rem; }
    .friends-container { padding: 0 0.8rem; }
    .friend-card { padding: 1rem; }
    .header-buttons { flex-direction: column; }
    .header-buttons button { min-width: auto; }
    .modal-box, .chat-modal-box { margin: 0.5rem; padding: 1.2rem; }
    .modal-actions { flex-direction: column; }
    .modal-actions button { width: 100%; }
    .side-menu { width: 280px; max-width: 85vw; }
    .message { max-width: 90%; }
    .chat-modal-box { width: 98%; height: 85vh; }
}
@media (max-width: 480px) {
    .friend-card { flex-direction: column; text-align: center; gap: 0.8rem; }
    .info { width: 100%; }
    .request-item { flex-direction: column; text-align: center; gap: 1rem; }
    .request-actions { width: 100%; justify-content: center; }
    .side-menu { width: 260px; max-width: 90vw; }
    .chat-header { padding: 1rem; }
}
.modal-overlay * { pointer-events: auto; }
.modal-overlay { pointer-events: auto; }
</style>
</head>
<body data-theme="auto" class="page-load">

<nav class="navbar navbar-slide">
  <div class="nav-container">
    <div id="userRom">
      <a id="userInfo" href="http://123.60.174.101:48053/userhtml.php" style="display:flex;align-items:center;gap:.4rem;color:var(--primary);font-weight:600;">
        <i class="fas fa-user-circle"></i>
        <span id="userName"><?php echo htmlspecialchars($currentUsername); ?></span>
        <span>(#<span id="userId"><?php echo $currentUserId; ?></span>)</span>
      </a>
      <button id="logoutBtn" onclick="logout()" title="退出" style="margin-left:.4rem;padding:.6rem;border-radius:var(--radius);background:var(--primary);color:#fff;">
        <i class="fas fa-sign-out-alt"></i>
      </button>
    </div>
    <div class="logo">
      <i class="fas fa-rocket"></i>
      <span>Qcbba</span>
    </div>
    <div style="display:flex;align-items:center;gap:.5rem;">
      <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon"></i>
      </button>
      <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
      </button>
    </div>
  </div>
</nav>

<aside class="side-menu" id="sideMenu">
  <ul>
    <li>
      <a href="http://123.60.174.101:48053/forum.html" target="_blank" class="menu-link">
        <i class="fas fa-comments"></i> 论坛
      </a>
    </li>
    <li>
      <a href="http://123.60.174.101:48053" class="menu-link">
        <i class="fas fa-home"></i> 首页
      </a>
    </li>
    <li>
      <a href="friends_system.php" class="menu-link active">
        <i class="fas fa-user-friends"></i> 好友
        <span class="friend-badge" id="requestBadge" style="display:none">0</span>
      </a>
    </li>
    <li>
      <a href="http://123.60.174.101:48053/about.html" class="menu-link">
        <i class="fas fa-info-circle"></i> 关于
      </a>
    </li>
    <li>
      <a href="http://123.60.174.101:48053/user.html" class="menu-link">
        <i class="fas fa-users"></i> 贡献者
      </a>
    </li>
  </ul>
</aside>
<div class="overlay" id="overlay"></div>

<div class="friends-container">
  <div class="friends-header">
    <h2><i class="fas fa-user-friends"></i> 好友列表</h2>
    <div class="header-buttons">
      <button id="viewRequestsBtn">
        <i class="fas fa-bell"></i> 
        好友申请
        <span id="requestCount" style="background:#ff4757;color:white;border-radius:50%;width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;font-size:.7rem;margin-left:.3rem;display:none">
          0
        </span>
      </button>
      <button id="addFriendBtn">
        <i class="fas fa-user-plus"></i> 添加好友
      </button>
      <button id="availableFriendsBtn">
        <i class="fas fa-search"></i> 
        发现好友
        <span id="availableCount" style="background:rgba(255,255,255,0.3);padding:0.2rem 0.5rem;border-radius:10px;font-size:0.8rem;">
          0
        </span>
      </button>
    </div>
  </div>
  
  <div id="friendsList" class="friends-list">
    <div class="empty-state">
      <i class="fas fa-user-friends"></i>
      <h3>加载中...</h3>
      <p>正在获取好友列表</p>
    </div>
  </div>
</div>

<!-- 弹窗部分 -->
<div id="addModal" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <h3>添加好友</h3>
      <button class="modal-close" id="closeAddModal">&times;</button>
    </div>
    <div class="input-group">
      <i class="fas fa-user"></i>
      <input id="addInput" type="number" placeholder="输入对方 UID" pattern="[0-9]*" inputmode="numeric">
    </div>
    <div class="modal-actions">
      <button class="btn-secondary" id="cancelAddModal">取消</button>
      <button class="btn-primary" onclick="sendFriendRequest()">发送申请</button>
    </div>
  </div>
</div>

<div id="requestsModal" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <h3>好友申请</h3>
      <button class="modal-close" id="closeRequestsModal">&times;</button>
    </div>
    <div class="request-list" id="requestsList">
      <div class="empty-state" style="padding: 2rem 1rem;">
        <i class="fas fa-bell-slash"></i>
        <p>加载中...</p>
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn-secondary" id="closeRequestsModalBtn">关闭</button>
    </div>
  </div>
</div>

<div id="availableFriendsModal" class="modal-overlay">
  <div class="modal-box" style="max-width: 500px;">
    <div class="modal-header">
      <h3><i class="fas fa-search"></i> 发现好友</h3>
      <button class="modal-close" id="closeAvailableFriendsModal">&times;</button>
    </div>
    <div class="request-list" id="availableFriendsList">
      <div class="empty-state" style="padding: 2rem 1rem;">
        <i class="fas fa-user-friends"></i>
        <p>加载中...</p>
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn-secondary" id="closeAvailableFriendsModalBtn">关闭</button>
    </div>
  </div>
</div>

<!-- Q弹聊天弹窗 -->
<div id="chatModal" class="chat-modal-overlay">
  <div class="chat-modal-box">
    <div class="chat-header">
      <div class="chat-user-info">
        <div class="chat-avatar" id="chatAvatar"></div>
        <div class="chat-user-details">
          <div class="chat-username" id="chatUserName"></div>
          <div class="chat-status" id="chatUserStatus">在线</div>
        </div>
      </div>
      <button class="chat-close" id="chatCloseBtn">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="chat-messages" id="chatMessages"></div>
    <div class="chat-input-area">
      <textarea class="chat-input" id="chatInput" placeholder="输入消息..." maxlength="500" rows="1"></textarea>
      <button class="chat-send" onclick="sendChatMessage()">
        <i class="fas fa-paper-plane"></i>
      </button>
    </div>
  </div>
</div>

<footer class="footer">
  <p>Qcbba 自 2025 年早春运行 · 公益不商</p>
</footer>

<script>
/* ---------- 全局变量 ---------- */
const API_URL = 'friends_system.php';
let currentChatFriend = null;
let currentChatUsername = null;
let chatRefreshInterval = null;

/* ---------- 聊天相关 ---------- */
function openChat(friendUid, friendName) {
    currentChatFriend = friendUid;
    currentChatUsername = friendName;
    document.getElementById('chatUserName').textContent = `${friendName} (#${friendUid})`;
    document.getElementById('chatAvatar').textContent = friendName.charAt(0).toUpperCase();
    const chatModal = document.getElementById('chatModal');
    chatModal.classList.add('show');
    loadChatMessages();
    setTimeout(() => document.getElementById('chatInput').focus(), 300);
    startChatRefresh();
}

function closeChatModal() {
    const chatModal = document.getElementById('chatModal');
    const chatBox = chatModal.querySelector('.chat-modal-box');
    chatBox.classList.add('hide');
    setTimeout(() => {
        chatModal.classList.remove('show');
        chatBox.classList.remove('hide');
        currentChatFriend = null;
        currentChatUsername = null;
        document.getElementById('chatMessages').innerHTML = '';
        document.getElementById('chatInput').value = '';
        stopChatRefresh();
    }, 350);
}

function startChatRefresh() {
    chatRefreshInterval = setInterval(() => {
        if (currentChatFriend) loadChatMessages();
    }, 3000);
}

function stopChatRefresh() {
    if (chatRefreshInterval) { clearInterval(chatRefreshInterval); chatRefreshInterval = null; }
}

function loadChatMessages() {
    if (!currentChatFriend) return;
    
    const formData = new FormData();
    formData.append('action', 'get_messages');
    formData.append('friend_uid', currentChatFriend);
    
    fetch(API_URL, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) displayMessages(data.messages);
        })
        .catch(console.error);
}

function displayMessages(messages) {
    const box = document.getElementById('chatMessages');
    box.innerHTML = '';
    
    messages.forEach(msg => {
        const div = document.createElement('div');
        div.className = `message ${msg.is_self ? 'self' : 'other'}`;
        
        // 格式化时间显示
        const timeParts = msg.timestamp.split('-');
        const displayTime = timeParts.length >= 5 ? `${timeParts[3]}:${timeParts[4]}` : msg.timestamp;
        
        div.innerHTML = `
            <div class="message-content">${escapeHtml(msg.message)}</div>
            <div class="message-time">${displayTime}</div>
        `;
        box.appendChild(div);
    });
    
    box.scrollTop = box.scrollHeight;
}

function sendChatMessage() {
    if (!currentChatFriend) return alert('未选择聊天对象');
    
    const input = document.getElementById('chatInput');
    const msg = input.value.trim();
    
    if (!msg) return alert('请输入消息内容');
    
    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('friend_uid', currentChatFriend);
    formData.append('message', msg);
    
    fetch(API_URL, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) { 
                input.value = ''; 
                loadChatMessages(); 
            } else {
                alert('发送失败：' + data.message);
            }
        })
        .catch(() => alert('发送失败，请检查网络'));
}

/* 转义HTML特殊字符 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/* ---------- 页面初始化 ---------- */
document.addEventListener('DOMContentLoaded', () => {
    loadFriendList();
    loadRequestCount();
    loadAvailableFriendsCount();

    /* 菜单 & 主题 */
    const menuToggle = document.getElementById('menuToggle');
    const sideMenu = document.getElementById('sideMenu');
    const overlay = document.getElementById('overlay');
    
    function toggleMenu(open) {
        const flag = open !== undefined ? open : !sideMenu.classList.contains('active');
        sideMenu.classList.toggle('active', flag);
        overlay.classList.toggle('show', flag);
    }
    
    menuToggle.addEventListener('click', e => { e.stopPropagation(); toggleMenu(); });
    overlay.addEventListener('click', () => toggleMenu(false));

    const themeToggle = document.getElementById('themeToggle');
    
    function setTheme(t) {
        document.body.setAttribute('data-theme', t);
        localStorage.setItem('theme', t);
        themeToggle.innerHTML = t === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
    }
    
    themeToggle.addEventListener('click', () => {
        const cur = document.body.getAttribute('data-theme');
        setTheme(cur === 'dark' ? 'light' : 'dark');
    });
    
    const saved = localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    setTheme(saved);

    /* 弹窗开关 - 修复关闭动画问题 */
    document.getElementById('addFriendBtn').addEventListener('click', () => {
        document.getElementById('addModal').classList.add('show');
    });
    
    document.getElementById('viewRequestsBtn').addEventListener('click', () => { 
        loadRequestsList(); 
        document.getElementById('requestsModal').classList.add('show'); 
    });
    
    document.getElementById('availableFriendsBtn').addEventListener('click', () => { 
        loadAvailableFriendsList(); 
        document.getElementById('availableFriendsModal').classList.add('show'); 
    });

    // 修复关闭按钮 - 使用统一的关闭函数
    function setupModalClose(closeBtnId, modalId) {
        const closeBtn = document.getElementById(closeBtnId);
        const modal = document.getElementById(modalId);
        
        if (closeBtn && modal) {
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                modal.classList.remove('show');
            });
        }
    }

    // 设置所有模态框的关闭按钮
    setupModalClose('closeAddModal', 'addModal');
    setupModalClose('cancelAddModal', 'addModal');
    setupModalClose('closeRequestsModal', 'requestsModal');
    setupModalClose('closeRequestsModalBtn', 'requestsModal');
    setupModalClose('closeAvailableFriendsModal', 'availableFriendsModal');
    setupModalClose('closeAvailableFriendsModalBtn', 'availableFriendsModal');
    
    // 聊天弹窗关闭按钮
    document.getElementById('chatCloseBtn').addEventListener('click', (e) => {
        e.stopPropagation();
        closeChatModal();
    });

    /* 点击弹窗外部关闭 */
    document.querySelectorAll('.modal-overlay').forEach(ol => {
        ol.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });
    });
    
    document.getElementById('chatModal').addEventListener('click', function(e) {
        if (e.target === this) closeChatModal();
    });

    /* 聊天输入框回车发送 */
    document.getElementById('chatInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { 
            e.preventDefault(); 
            sendChatMessage(); 
        }
    });
    
    /* 输入框高度自适应 */
    document.getElementById('chatInput').addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
});

/* ---------- 业务函数 ---------- */
function loadFriendList() {
    const formData = new FormData();
    formData.append('action', 'get_friend_list');
    
    fetch(API_URL, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            const box = document.getElementById('friendsList');
            if (data.success && data.friends.length) {
                box.innerHTML = '';
                data.friends.forEach(f => {
                    const card = document.createElement('div');
                    card.className = 'friend-card';
                    card.onclick = () => openChat(f.uid, f.username);
                    card.innerHTML = `
                        <div class="avatar">${f.username.charAt(0).toUpperCase()}</div>
                        <div class="info">
                            <div class="name">${escapeHtml(f.username)} (#${f.uid})</div>
                            <div class="status"><span class="status-dot offline"></span><span>${escapeHtml(f.last_message)}</span></div>
                        </div>
                    `;
                    box.appendChild(card);
                });
            } else {
                box.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-user-friends"></i>
                        <h3>暂无好友</h3>
                        <p>添加好友开始聊天吧</p>
                    </div>
                `;
            }
        })
        .catch(console.error);
}

function loadRequestCount() {
    const formData = new FormData();
    formData.append('action', 'get_pending_requests');
    
    fetch(API_URL, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            const count = (data.success ? data.requests.length : 0);
            const countEl = document.getElementById('requestCount');
            const badge = document.getElementById('requestBadge');
            
            if (count) {
                countEl.textContent = count;
                countEl.style.display = 'inline-flex';
                badge.textContent = count;
                badge.style.display = 'flex';
            } else {
                countEl.style.display = 'none';
                badge.style.display = 'none';
            }
        })
        .catch(console.error);
}

function loadAvailableFriendsCount() {
    const formData = new FormData();
    formData.append('action', 'get_available_friends');
    
    fetch(API_URL, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            const count = (data.success ? data.available_friends.length : 0);
            document.getElementById('availableCount').textContent = count;
        })
        .catch(console.error);
}

function loadRequestsList() {
    const formData = new FormData();
    formData.append('action', 'get_pending_requests');
    
    fetch(API_URL, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            const box = document.getElementById('requestsList');
            if (data.success && data.requests.length) {
                box.innerHTML = '';
                data.requests.forEach(req => {
                    const item = document.createElement('div');
                    item.className = 'request-item';
                    item.innerHTML = `
                        <div class="request-info">
                            <div class="request-username">${escapeHtml(req.from_username)} (#${req.from_uid})</div>
                            <div class="request-time">${req.time}</div>
                        </div>
                        <div class="request-actions">
                            <button class="request-accept" onclick="handleFriendRequest(${req.from_uid}, 'accept')">接受</button>
                            <button class="request-reject" onclick="handleFriendRequest(${req.from_uid}, 'reject')">拒绝</button>
                        </div>
                    `;
                    box.appendChild(item);
                });
            } else {
                box.innerHTML = `
                    <div class="empty-state" style="padding: 2rem 1rem;">
                        <i class="fas fa-bell-slash"></i>
                        <p>暂无好友申请</p>
                    </div>
                `;
            }
        })
        .catch(console.error);
}

function loadAvailableFriendsList() {
    const formData = new FormData();
    formData.append('action', 'get_available_friends');
    
    fetch(API_URL, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            const box = document.getElementById('availableFriendsList');
            if (data.success && data.available_friends.length) {
                box.innerHTML = '';
                data.available_friends.forEach(f => {
                    const item = document.createElement('div');
                    item.className = 'request-item';
                    const roleBadge = f.role === 'admin' ? '<span style="background:var(--primary);color:white;padding:0.2rem 0.5rem;border-radius:var(--radius);font-size:0.7rem;margin-left:0.5rem;">管理员</span>' : '';
                    item.innerHTML = `
                        <div class="request-info">
                            <div class="request-username">${escapeHtml(f.username)} (#${f.uid}) ${roleBadge}</div>
                            <div class="request-time">UID: ${f.uid}</div>
                        </div>
                        <div class="request-actions">
                            <button class="request-accept" onclick="addFriendFromList(${f.uid})">添加</button>
                        </div>
                    `;
                    box.appendChild(item);
                });
            } else {
                box.innerHTML = `
                    <div class="empty-state" style="padding: 2rem 1rem;">
                        <i class="fas fa-user-friends"></i>
                        <p>暂无推荐好友</p>
                        <p style="font-size:0.9rem;margin-top:0.5rem;">所有用户都已经是您的好友了</p>
                    </div>
                `;
            }
        })
        .catch(console.error);
}

window.sendFriendRequest = function() {
    const uid = document.getElementById('addInput').value.trim();
    if (!uid || isNaN(uid)) return alert('请输入有效的用户UID');
    
    const formData = new FormData();
    formData.append('action', 'send_friend_request');
    formData.append('target_uid', uid);
    
    fetch(API_URL, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                document.getElementById('addModal').classList.remove('show');
                document.getElementById('addInput').value = '';
                loadRequestCount();
                loadAvailableFriendsCount();
            }
        })
        .catch(() => alert('发送失败，请重试'));
};

window.addFriendFromList = function(uid) {
    const formData = new FormData();
    formData.append('action', 'send_friend_request');
    formData.append('target_uid', uid);
    
    fetch(API_URL, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                document.getElementById('availableFriendsModal').classList.remove('show');
                loadRequestCount();
                loadAvailableFriendsCount();
            }
        })
        .catch(() => alert('发送失败，请重试'));
};

window.handleFriendRequest = function(fromUid, action) {
    const formData = new FormData();
    formData.append('action', action + '_friend_request');
    formData.append('from_uid', fromUid);
    
    fetch(API_URL, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                document.getElementById('requestsModal').classList.remove('show');
                loadFriendList();
                loadRequestCount();
                loadAvailableFriendsCount();
            }
        })
        .catch(() => alert('操作失败，请重试'));
};

window.logout = function() {
    if (confirm('确定要退出登录吗？')) {
        location.href = 'http://123.60.174.101:48053/login.html?action=logout';
    }
};
</script>
</body>
</html>
    <?php
}

// API功能函数
function userExists($uid) {
    global $usersDir;
    $userFile = $_SERVER['DOCUMENT_ROOT'] . $usersDir . "user{$uid}.txt";
    return file_exists($userFile);
}

function parseFile($file) {
    $info = [];
    if (!file_exists($file)) {
        return $info;
    }
    
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $info[trim($parts[0])] = trim($parts[1]);
        }
    }
    return $info;
}

function getUsernameById($uid) {
    global $usersDir;
    $userFile = $_SERVER['DOCUMENT_ROOT'] . $usersDir . "user{$uid}.txt";
    
    if (file_exists($userFile)) {
        $info = parseFile($userFile);
        return $info['name'] ?? "用户#{$uid}";
    }
    
    return "用户#{$uid}";
}

function sendFriendRequest() {
    global $currentUserId, $currentUsername, $friendsDir;
    
    $targetUid = (int)$_POST['target_uid'];
    
    if ($targetUid === $currentUserId) {
        echo json_encode(['success' => false, 'message' => '不能添加自己为好友']);
        exit;
    }
    
    if (!userExists($targetUid)) {
        echo json_encode(['success' => false, 'message' => '用户不存在']);
        exit;
    }
    
    $friendDir1 = $friendsDir . "[{$currentUserId}][{$targetUid}]";
    $friendDir2 = $friendsDir . "[{$targetUid}][{$currentUserId}]";
    if (is_dir($friendDir1) || is_dir($friendDir2)) {
        echo json_encode(['success' => false, 'message' => '已经是好友了']);
        exit;
    }
    
    $requestFile = $friendsDir . "request_{$targetUid}_{$currentUserId}.txt";
    if (file_exists($requestFile)) {
        echo json_encode(['success' => false, 'message' => '已发送过好友申请']);
        exit;
    }
    
    // 创建好友申请文件
    if (file_put_contents($requestFile, "from_uid=$currentUserId\nfrom_username=$currentUsername\ntime=" . date('Y-m-d H:i:s')) === false) {
        echo json_encode(['success' => false, 'message' => '无法创建申请文件']);
        exit;
    }
    
    // 创建特殊文件记录谁先发送申请
    $initiatorFile = $friendsDir . "initiator_{$currentUserId}_{$targetUid}.txt";
    file_put_contents($initiatorFile, "initiator=$currentUserId\ntarget=$targetUid\ntime=" . date('Y-m-d H:i:s'));
    
    echo json_encode(['success' => true, 'message' => '好友申请已发送']);
    exit;
}

function acceptFriendRequest() {
    global $currentUserId, $friendsDir;
    
    $fromUid = (int)$_POST['from_uid'];
    
    // 查找特殊文件来确定谁先发送申请
    $initiatorFile1 = $friendsDir . "initiator_{$fromUid}_{$currentUserId}.txt";
    $initiatorFile2 = $friendsDir . "initiator_{$currentUserId}_{$fromUid}.txt";
    
    $chatDirName = "";
    
    // 检查特殊文件来确定正确的文件夹名格式
    if (file_exists($initiatorFile1)) {
        // fromUid 先发送申请，使用 [fromUid][currentUserId] 文件夹
        $chatDirName = "[{$fromUid}][{$currentUserId}]";
        unlink($initiatorFile1);
    } elseif (file_exists($initiatorFile2)) {
        // currentUserId 先发送申请，使用 [currentUserId][fromUid] 文件夹
        $chatDirName = "[{$currentUserId}][{$fromUid}]";
        unlink($initiatorFile2);
    } else {
        // 如果没有特殊文件，默认使用 [先发送者][后发送者] 格式
        $chatDirName = "[{$fromUid}][{$currentUserId}]";
    }
    
    // 创建聊天文件夹
    $friendDir = $friendsDir . $chatDirName;
    if (!is_dir($friendDir)) {
        if (!mkdir($friendDir, 0777, true)) {
            echo json_encode(['success' => false, 'message' => '无法创建聊天文件夹']);
            exit;
        }
    }
    
    // 删除好友申请文件
    $requestFile = $friendsDir . "request_{$currentUserId}_{$fromUid}.txt";
    if (file_exists($requestFile)) {
        unlink($requestFile);
    }
    
    echo json_encode(['success' => true, 'message' => '好友添加成功']);
    exit;
}

function rejectFriendRequest() {
    global $currentUserId, $friendsDir;
    
    $fromUid = (int)$_POST['from_uid'];
    
    // 删除好友申请文件
    $requestFile = $friendsDir . "request_{$currentUserId}_{$fromUid}.txt";
    if (file_exists($requestFile)) {
        unlink($requestFile);
    }
    
    // 删除特殊文件
    $initiatorFile1 = $friendsDir . "initiator_{$fromUid}_{$currentUserId}.txt";
    $initiatorFile2 = $friendsDir . "initiator_{$currentUserId}_{$fromUid}.txt";
    if (file_exists($initiatorFile1)) unlink($initiatorFile1);
    if (file_exists($initiatorFile2)) unlink($initiatorFile2);
    
    echo json_encode(['success' => true, 'message' => '已拒绝好友申请']);
    exit;
}

function sendMessage() {
    global $currentUserId, $friendsDir;
    
    $friendUid = (int)$_POST['friend_uid'];
    $message = trim($_POST['message']);
    
    if ($message === '') {
        echo json_encode(['success' => false, 'message' => '消息不能为空']);
        exit;
    }
    
    // 查找聊天文件夹
    $friendDir1 = $friendsDir . "[{$currentUserId}][{$friendUid}]";
    $friendDir2 = $friendsDir . "[{$friendUid}][{$currentUserId}]";
    
    $targetDir = null;
    if (is_dir($friendDir1)) {
        $targetDir = $friendDir1;
    } elseif (is_dir($friendDir2)) {
        $targetDir = $friendDir2;
    } else {
        echo json_encode(['success' => false, 'message' => '好友关系不存在']);
        exit;
    }
    
    // 确保目录存在且有写权限
    if (!is_dir($targetDir)) {
        echo json_encode(['success' => false, 'message' => '聊天目录不存在']);
        exit;
    }
    
    // 获取时间戳 - 格式：年-月-日-时-分-秒
    $timestamp = date('Y-n-j-G-i-s');
    
    // 构建文件名： [时间戳][用户编号].txt
    $fileName = "[{$timestamp}][{$currentUserId}].txt";
    $filePath = $targetDir . '/' . $fileName;
    
    // 创建消息文件，内容就是消息内容
    $result = file_put_contents($filePath, $message);
    
    if ($result !== false) {
        echo json_encode(['success' => true, 'message' => '消息发送成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '文件写入失败']);
    }
    exit;
}

function getMessages() {
    global $currentUserId, $friendsDir;
    
    $friendUid = (int)$_POST['friend_uid'];
    
    $friendDir1 = $friendsDir . "[{$currentUserId}][{$friendUid}]";
    $friendDir2 = $friendsDir . "[{$friendUid}][{$currentUserId}]";
    
    $targetDir = null;
    if (is_dir($friendDir1)) {
        $targetDir = $friendDir1;
    } elseif (is_dir($friendDir2)) {
        $targetDir = $friendDir2;
    }
    
    $messages = [];
    if ($targetDir && is_dir($targetDir)) {
        // 获取文件夹中所有消息文件，按文件名排序（时间顺序）
        $messageFiles = glob($targetDir . '/*.txt');
        if ($messageFiles) {
            // 按文件名排序（时间顺序）
            sort($messageFiles);
            
            foreach ($messageFiles as $file) {
                $filename = basename($file);
                
                // 解析文件名格式: [时间戳][发送者].txt
                if (preg_match('/^\[(\d+-\d+-\d+-\d+-\d+-\d+)\]\[(\d+)\]\.txt$/', $filename, $matches)) {
                    $messageContent = file_get_contents($file);
                    if ($messageContent !== false) {
                        $messages[] = [
                            'timestamp' => $matches[1],
                            'sender' => (int)$matches[2],
                            'message' => $messageContent,
                            'is_self' => ((int)$matches[2] === $currentUserId)
                        ];
                    }
                }
            }
        }
    }
    
    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
}

function getFriendList() {
    global $currentUserId, $friendsDir;
    
    $friends = [];
    
    // 查找所有好友文件夹
    $friendDirs = glob($friendsDir . "*[{$currentUserId}]*", GLOB_ONLYDIR);
    
    foreach ($friendDirs as $dir) {
        if (preg_match('/\[(\d+)\]\[(\d+)\]$/', basename($dir), $matches)) {
            $uid1 = (int)$matches[1];
            $uid2 = (int)$matches[2];
            
            if ($uid1 === $currentUserId || $uid2 === $currentUserId) {
                $friendId = ($uid1 === $currentUserId) ? $uid2 : $uid1;
                $friendUsername = getUsernameById($friendId);
                
                // 获取最后一条消息
                $lastMessage = '暂无消息';
                $messageFiles = glob($dir . '/*.txt');
                if (!empty($messageFiles)) {
                    rsort($messageFiles);
                    $latestFile = $messageFiles[0];
                    $content = file_get_contents($latestFile);
                    if ($content !== false) {
                        $lastMessage = $content;
                    }
                }
                
                $friends[] = [
                    'uid' => $friendId,
                    'username' => $friendUsername,
                    'last_message' => $lastMessage
                ];
            }
        }
    }
    
    echo json_encode(['success' => true, 'friends' => $friends]);
    exit;
}

function getPendingRequests() {
    global $currentUserId, $friendsDir;
    
    $requests = [];
    $files = glob($friendsDir . "request_{$currentUserId}_*.txt");
    
    foreach ($files as $file) {
        if (preg_match('/request_'.$currentUserId.'_(\d+)\.txt$/', $file, $matches)) {
            $fromUid = (int)$matches[1];
            $requestInfo = parseFile($file);
            $requests[] = [
                'from_uid' => $fromUid,
                'from_username' => $requestInfo['from_username'] ?? getUsernameById($fromUid),
                'time' => $requestInfo['time'] ?? '未知时间'
            ];
        }
    }
    
    echo json_encode(['success' => true, 'requests' => $requests]);
    exit;
}

function getAvailableFriends() {
    global $currentUserId, $usersDir, $friendsDir;
    
    $available = [];
    $userFiles = glob($_SERVER['DOCUMENT_ROOT'] . $usersDir . 'user*.txt');
    
    foreach ($userFiles as $userFile) {
        if (preg_match('/user(\d+)\.txt$/', basename($userFile), $matches)) {
            $uid = (int)$matches[1];
            if ($uid === $currentUserId) continue;
            
            $isFriend = false;
            $friendDirs = glob($friendsDir . "[{$currentUserId}][{$uid}]", GLOB_ONLYDIR);
            $friendDirs = array_merge($friendDirs, glob($friendsDir . "[{$uid}][{$currentUserId}]", GLOB_ONLYDIR));
            
            if (!empty($friendDirs)) {
                $isFriend = true;
            }
            
            $hasPendingRequest = file_exists($friendsDir . "request_{$uid}_{$currentUserId}.txt") || 
                                file_exists($friendsDir . "request_{$currentUserId}_{$uid}.txt");
            
            if (!$isFriend && !$hasPendingRequest) {
                $userInfo = parseFile($userFile);
                $available[] = [
                    'uid' => $uid,
                    'username' => $userInfo['name'] ?? "用户#{$uid}",
                    'role' => $userInfo['role'] ?? 'user'
                ];
            }
        }
    }
    
    echo json_encode(['success' => true, 'available_friends' => $available]);
    exit;
}
?>