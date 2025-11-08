<?php
// save_comment.php - 保存评论
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

// 检查POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, '只支持POST请求');
}

// 获取数据
$postId = trim($_POST['post_id'] ?? '');
$content = trim($_POST['content'] ?? '');
$author = trim($_POST['author'] ?? '');
$uid = trim($_POST['uid'] ?? '');

// 验证数据
if (empty($postId)) {
    sendResponse(false, '帖子ID不能为空');
}
if (empty($content)) {
    sendResponse(false, '评论内容不能为空');
}
if (empty($author)) {
    sendResponse(false, '作者信息不能为空');
}
if (empty($uid)) {
    sendResponse(false, '用户ID不能为空');
}

// 检查帖子是否存在
$postFile = __DIR__ . '/' . $postId . '.txt';
if (!file_exists($postFile)) {
    sendResponse(false, '帖子不存在');
}

// 评论目录
$commentsDir = __DIR__ . '/comments/';
if (!is_dir($commentsDir)) {
    if (!mkdir($commentsDir, 0755, true)) {
        sendResponse(false, '无法创建评论目录');
    }
}

// 生成评论ID
$commentFiles = glob($commentsDir . $postId . '_*.txt');
$maxCommentId = 0;

foreach ($commentFiles as $file) {
    if (preg_match('/' . $postId . '_(\d+)\.txt$/', $file, $matches)) {
        $id = (int)$matches[1];
        if ($id > $maxCommentId) {
            $maxCommentId = $id;
        }
    }
}

$newCommentId = $maxCommentId + 1;

// 构建评论文件内容
$commentContent = "post_id = $postId\n";
$commentContent .= "comment_id = $newCommentId\n";
$commentContent .= "author = $author\n";
$commentContent .= "uid = $uid\n";
$commentContent .= "time = " . date('Y-m-d H:i:s') . "\n";
$commentContent .= "---content---\n";
$commentContent .= $content;

// 保存评论文件
$commentFileName = $commentsDir . $postId . '_' . $newCommentId . '.txt';
if (file_put_contents($commentFileName, $commentContent) !== false) {
    // 更新帖子的评论计数
    updatePostCommentCount($postId);
    
    sendResponse(true, '评论发布成功', [
        'comment_id' => $newCommentId,
        'post_id' => $postId
    ]);
} else {
    sendResponse(false, '保存评论失败');
}

// 更新帖子评论计数
function updatePostCommentCount($postId) {
    $postFile = __DIR__ . '/' . $postId . '.txt';
    if (!file_exists($postFile)) {
        return;
    }
    
    $content = file_get_contents($postFile);
    if ($content === false) {
        return;
    }
    
    // 计算评论数量
    $commentsDir = __DIR__ . '/comments/';
    $commentFiles = glob($commentsDir . $postId . '_*.txt');
    $commentCount = count($commentFiles);
    
    // 更新帖子文件中的评论计数
    $lines = explode("\n", $content);
    $newLines = [];
    $hasCommentsLine = false;
    
    foreach ($lines as $line) {
        if (strpos($line, 'comments = ') === 0) {
            $newLines[] = "comments = $commentCount";
            $hasCommentsLine = true;
        } else {
            $newLines[] = $line;
        }
    }
    
    // 如果没有评论行，在适当位置添加
    if (!$hasCommentsLine) {
        // 找到内容分隔符的位置
        $contentSeparatorIndex = array_search('---content---', $newLines);
        if ($contentSeparatorIndex !== false) {
            array_splice($newLines, $contentSeparatorIndex, 0, "comments = $commentCount");
        }
    }
    
    file_put_contents($postFile, implode("\n", $newLines));
}
?>