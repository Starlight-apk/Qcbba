<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

/* ====== 基础配置 ====== */
$usersDir = __DIR__ . '/user/';
$keysDir  = __DIR__ . '/动态密钥/';
@mkdir($usersDir, 0777, true);
@mkdir($keysDir,  0777, true);

$act = $_GET['action'] ?? '';

/* ====== 工具函数 ====== */
function parseFile(string $file): array
{
    $info = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        [$k, $v] = array_map('trim', explode('=', $l, 2));
        $info[$k] = $v;
    }
    return $info;
}

function genToken(int $uid, string $role): string
{
    $secret = $_ENV['SESSION_SECRET'] ?? 'ChangeMeTo256BitSecret!';
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode(['uid' => $uid, 'role' => $role, 'iat' => time()]));
    $sig  = base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
    return "$header.$payload.$sig";
}

/* ====== 注册 ====== */
if ($act === 'register') {
    $user = trim($_POST['username'] ?? '');
    if ($user === '') exit(json_encode(['ok' => 0, 'msg' => '用户名空']));

    foreach (glob($usersDir . '*.txt') as $f) {
        if (parseFile($f)['name'] === $user) {
            exit(json_encode(['ok' => 0, 'msg' => '用户名已存在']));
        }
    }

    for ($id = 100000; file_exists($usersDir . "user{$id}.txt"); $id++) {}
    $key = bin2hex(random_bytes(10)); // 20 位

    file_put_contents($usersDir . "user{$id}.txt", "name=$user\nuid=$id\nrole=user");
    file_put_contents($keysDir  . "user{$id}.txt", $key);
    file_put_contents(__DIR__ . '/待发送密钥.txt', date('Y-m-d H:i:s') . "  用户：$user  编号：$id  密钥：$key\n", FILE_APPEND | LOCK_EX);

    exit(json_encode(['ok' => 1, 'uid' => $id, 'key' => $key]));
}

/* ====== 登录 ====== */
if ($act === 'login') {
    $user = trim($_POST['username'] ?? '');
    $key  = trim($_POST['key']  ?? '');
    if ($user === '' || $key === '') exit(json_encode(['ok' => 0, 'msg' => '参数空']));

    foreach (glob($usersDir . '*.txt') as $f) {
        $info = parseFile($f);
        if ($info['name'] === $user) {
            $uid      = (int)$info['uid'];
            $keyFile  = $keysDir . "user{$uid}.txt";
            $realKey  = trim(file_get_contents($keyFile));
            if (hash_equals($realKey, $key) || hash_equals($realKey, $key)) { // 兼容首次用密钥
                $role = (strpos(file_get_contents($f), 'role=admin') !== false) ? 'admin' : 'user';
                $_SESSION['uid']      = $uid;
                $_SESSION['username'] = $user;
                $_SESSION['role']     = $role;
                exit(json_encode(['ok' => 1, 'token' => genToken($uid, $role), 'uid' => $uid, 'role' => $role]));
            }
            exit(json_encode(['ok' => 0, 'msg' => '密钥错误']));
        }
    }
    exit(json_encode(['ok' => 0, 'msg' => '用户名不存在']));
}

/* ====== 修改密码 ====== */
if ($act === 'changePwd') {
    $user  = trim($_POST['username'] ?? '');
    $old   = trim($_POST['old']      ?? '');
    $new   = trim($_POST['new']      ?? '');

    if ($user === '' || $old === '' || $new === '') {
        exit(json_encode(['ok' => 0, 'msg' => '参数空']));
    }
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d]{8,}$/', $new)) {
        exit(json_encode(['ok' => 0, 'msg' => '新密码至少 8 位，且包含大小写字母+数字']));
    }

    foreach (glob($usersDir . '*.txt') as $f) {
        $info = parseFile($f);
        if ($info['name'] === $user) {
            $uid     = (int)$info['uid'];
            $keyFile = $keysDir . "user{$uid}.txt";
            $realKey = trim(file_get_contents($keyFile));
            if (!hash_equals($realKey, $old)) {
                exit(json_encode(['ok' => 0, 'msg' => '旧密钥/密码错误']));
            }
            // 更新密钥文件（新密码即新密钥）
            file_put_contents($keyFile, $new);
            exit(json_encode(['ok' => 1, 'msg' => '密码已更新']));
        }
    }
    exit(json_encode(['ok' => 0, 'msg' => '用户名不存在']));
}

/* ====== 退出 ====== */
if ($act === 'logout') {
    session_destroy();
    exit(json_encode(['ok' => 1]));
}

/* ====== 默认 ====== */
exit(json_encode(['ok' => 0, 'msg' => '未知 action']));
