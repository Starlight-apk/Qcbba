<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$user = $_POST['username'] ?? '';
$pass = $_POST['password'] ?? '';

if (!$user || !$pass) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => '缺少参数']));
}

$file = __DIR__ . '/users/' . $user . '.txt';
if (!file_exists($file)) {
    http_response_code(404);
    exit(json_encode(['success' => false, 'message' => '用户不存在']));
}

$lines  = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$last   = trim(end($lines));          // 取最后一行
$role   = (strpos($last, 'role=admin') === 0) ? 'admin' : 'user';

// 第一行是密码（明文或 md5 均可，按你原来规则）
$pwdLine = trim($lines[0]);
$inputPwd = (strlen($pwdLine) === 32) ? md5($pass) : $pass;

if ($inputPwd !== $pwdLine) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => '密码错误']));
}

// 写 session
$_SESSION['uid']      = $user;
$_SESSION['username'] = $user;
$_SESSION['role']     = $role;
$_SESSION['token']    = bin2hex(random_bytes(16));   // 简单 token，可按需改

echo json_encode([
    'success' => true,
    'message' => '登录成功',
    'uid'     => $user,
    'username'=> $user,
    'token'   => $_SESSION['token'],
    'role'    => $role
    error_reporting(E_ALL); ini_set('display_errors', 1);
]);
?>
