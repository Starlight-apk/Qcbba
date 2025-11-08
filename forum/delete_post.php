<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

// 简单防 CSRF，复用前端传来的 token
$token = $_POST['token'] ?? '';
if ($token !== ($_SESSION['token'] ?? '')) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'token 无效']));
}

$postId = (int)($_POST['post_id'] ?? 0);
$uid    = $_SESSION['uid']   ?? '';
$role   = $_SESSION['role'] ?? 'user';

if (!$postId || !$uid) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => '参数缺失']));
}

// 读帖子信息（假设你原来用 json 文件存帖子）
$postFile = __DIR__ . '/posts/' . $postId . '.json';
if (!file_exists($postFile)) {
    http_response_code(404);
    exit(json_encode(['success' => false, 'message' => '帖子不存在']));
}

$post = json_decode(file_get_contents($postFile), true);
if (!$post) {
    exit(json_encode(['success' => false, 'message' => '帖子数据损坏']));
}

$isAuthor = ($post['uid'] ?? '') === $uid;
$isAdmin  = $role === 'admin';

if (!$isAuthor && !$isAdmin) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => '无权删除']));
}

// 执行删除
unlink($postFile);
// 如有其他关联数据（评论、点赞）可在此处一并清理

echo json_encode(['success' => true, 'message' => '删除成功']);
?>
