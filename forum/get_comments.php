<?php
// get_comments.php - 获取评论列表
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

function sendResponse($success, $message = '', $comments = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'comments' => $comments
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取帖子ID
$postId = $_GET['post_id'] ?? '';

if (empty($postId)) {
    sendResponse(false, '缺少帖子ID参数', []);
}

// 评论目录
$commentsDir = __DIR__ . '/comments/';
$comments = [];

if (!is_dir($commentsDir)) {
    sendResponse(true, '评论目录不存在', []);
}

// 获取该帖子的所有评论文件
$commentFiles = glob($commentsDir . $postId . '_*.txt');

foreach ($commentFiles as $file) {
    if (!is_readable($file)) {
        continue;
    }
    
    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }
    
    $commentData = parseCommentFile($content);
    if ($commentData) {
        $comments[] = $commentData;
    }
}

// 按时间正序排列（最早的在前）
usort($comments, function($a, $b) {
    return ($a['timestamp'] ?? 0) - ($b['timestamp'] ?? 0);
});

sendResponse(true, '获取评论成功', $comments);

// 解析评论文件
function parseCommentFile($content) {
    $lines = explode("\n", $content);
    $comment = [
        'post_id' => '',
        'comment_id' => '',
        'author' => '',
        'uid' => '',
        'date' => '',
        'content' => '',
        'timestamp' => 0
    ];
    
    $inContent = false;
    $contentLines = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if ($line === '---content---') {
            $inContent = true;
            continue;
        }
        
        if (!$inContent) {
            if (preg_match('/^post_id\s*=\s*(.+)$/', $line, $matches)) {
                $comment['post_id'] = trim($matches[1]);
            } elseif (preg_match('/^comment_id\s*=\s*(.+)$/', $line, $matches)) {
                $comment['comment_id'] = trim($matches[1]);
            } elseif (preg_match('/^author\s*=\s*(.+)$/', $line, $matches)) {
                $comment['author'] = trim($matches[1]);
            } elseif (preg_match('/^uid\s*=\s*(.+)$/', $line, $matches)) {
                $comment['uid'] = trim($matches[1]);
            } elseif (preg_match('/^time\s*=\s*(.+)$/', $line, $matches)) {
                $comment['date'] = trim($matches[1]);
                $comment['timestamp'] = strtotime($comment['date']);
            }
        } else {
            $contentLines[] = $line;
        }
    }
    
    $comment['content'] = implode("\n", $contentLines);
    
    // 验证必要字段
    if (empty($comment['post_id']) || empty($comment['author']) || empty($comment['date'])) {
        return null;
    }
    
    return $comment;
}
?>