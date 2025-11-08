<?php
// like_post.php - 点赞帖子（修复版）
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

function sendResponse($success, $message = '', $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查帖子是否存在
function postExists($postId) {
    $postFile = __DIR__ . '/' . $postId . '.txt';
    return file_exists($postFile);
}

// 检查POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, '只支持POST请求');
}

// 获取数据
$postId = trim($_POST['post_id'] ?? '');
$uid = trim($_POST['uid'] ?? '');

// 验证数据
if (empty($postId)) {
    sendResponse(false, '帖子ID不能为空');
}
if (empty($uid)) {
    sendResponse(false, '用户ID不能为空');
}

// 检查帖子是否存在
if (!postExists($postId)) {
    sendResponse(false, '帖子不存在');
}

// 点赞记录目录
$likesDir = __DIR__ . '/likes/';
if (!is_dir($likesDir)) {
    if (!mkdir($likesDir, 0755, true)) {
        sendResponse(false, '无法创建点赞目录');
    }
}

// 用户点赞记录文件
$userLikeFile = $likesDir . $uid . '.txt';

// 读取用户点赞记录
$userLikes = [];
if (file_exists($userLikeFile)) {
    $content = file_get_contents($userLikeFile);
    if ($content !== false) {
        $userLikes = array_filter(explode(',', trim($content)), function($id) {
            return !empty($id) && postExists($id);
        });
    }
}

// 检查是否已经点赞
$isLiked = in_array($postId, $userLikes);

if ($isLiked) {
    // 取消点赞
    $userLikes = array_diff($userLikes, [$postId]);
    $message = '取消点赞成功';
} else {
    // 添加点赞
    $userLikes[] = $postId;
    $message = '点赞成功';
}

// 保存用户点赞记录
if (file_put_contents($userLikeFile, implode(',', $userLikes)) !== false) {
    // 更新帖子的点赞计数
    updatePostLikeCount($postId);
    
    sendResponse(true, $message, [
        'post_id' => $postId,
        'is_liked' => !$isLiked,
        'like_count' => getPostLikeCount($postId)
    ]);
} else {
    sendResponse(false, '操作失败');
}

// 更新帖子点赞计数
function updatePostLikeCount($postId) {
    $postFile = __DIR__ . '/' . $postId . '.txt';
    if (!file_exists($postFile)) {
        return;
    }
    
    $content = file_get_contents($postFile);
    if ($content === false) {
        return;
    }
    
    // 计算点赞数量
    $likesCount = getPostLikeCount($postId);
    
    // 更新帖子文件中的点赞计数
    $lines = explode("\n", $content);
    $newLines = [];
    $hasLikesLine = false;
    
    foreach ($lines as $line) {
        if (strpos($line, 'likes = ') === 0) {
            $newLines[] = "likes = $likesCount";
            $hasLikesLine = true;
        } else {
            $newLines[] = $line;
        }
    }
    
    // 如果没有点赞行，在适当位置添加
    if (!$hasLikesLine) {
        // 找到内容分隔符的位置
        $contentSeparatorIndex = array_search('---content---', $newLines);
        if ($contentSeparatorIndex !== false) {
            array_splice($newLines, $contentSeparatorIndex, 0, "likes = $likesCount");
        }
    }
    
    file_put_contents($postFile, implode("\n", $newLines));
}

// 获取帖子点赞数量（修复版，只计算存在的帖子）
function getPostLikeCount($postId) {
    $likesDir = __DIR__ . '/likes/';
    $count = 0;
    
    if (!is_dir($likesDir)) {
        return 0;
    }
    
    // 扫描所有用户的点赞文件
    $userFiles = glob($likesDir . '*.txt');
    
    foreach ($userFiles as $file) {
        $content = file_get_contents($file);
        if ($content !== false) {
            $userLikes = array_filter(explode(',', trim($content)), function($id) {
                return !empty($id) && postExists($id);
            });
            if (in_array($postId, $userLikes)) {
                $count++;
            }
        }
    }
    
    return $count;
}
?>