<?php
// get_posts.php - 获取帖子列表（修改版）
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

function sendResponse($success, $message = '', $posts = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'posts' => $posts
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$postsDir = __DIR__ . '/';
$posts = [];

if (!is_dir($postsDir)) {
    sendResponse(true, '帖子目录不存在', []);
}

$files = scandir($postsDir);
foreach ($files as $file) {
    if (preg_match('/^(\d+)\.txt$/', $file, $matches)) {
        $filePath = $postsDir . $file;
        if (!is_readable($filePath)) continue;
        
        $content = file_get_contents($filePath);
        if ($content === false) continue;
        
        $postData = parsePostFile($content, $matches[1]);
        if ($postData) {
            // 获取点赞和评论数量
            $postData['likes'] = getPostLikeCount($postData['id']);
            $postData['comments'] = getPostCommentCount($postData['id']);
            $posts[] = $postData;
        }
    }
}

// 按时间倒序
usort($posts, function($a, $b) {
    return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
});

sendResponse(true, '获取帖子成功', $posts);

function parsePostFile($content, $fileId) {
    $lines = explode("\n", $content);
    $post = [
        'id' => (int)$fileId,
        'title' => '',
        'author' => '',
        'uid' => '',
        'type' => 'text',
        'image' => '',
        'date' => '',
        'content' => '',
        'timestamp' => 0,
        'likes' => 0,
        'comments' => 0
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
            if (preg_match('/^name\s*=\s*(.+)$/', $line, $matches)) {
                $post['title'] = trim($matches[1]);
            } elseif (preg_match('/^user\s*=\s*(.+)$/', $line, $matches)) {
                $post['author'] = trim($matches[1]);
            } elseif (preg_match('/^uid\s*=\s*(.+)$/', $line, $matches)) {
                $post['uid'] = trim($matches[1]);
            } elseif (preg_match('/^type\s*=\s*(.+)$/', $line, $matches)) {
                $post['type'] = trim($matches[1]);
            } elseif (preg_match('/^image\s*=\s*(.+)$/', $line, $matches)) {
                $imagePath = trim($matches[1]);
                if (!empty($imagePath) && file_exists(__DIR__ . '/' . $imagePath)) {
                    $post['image'] = 'http://123.60.174.101:48053/forum/' . $imagePath;
                }
            } elseif (preg_match('/^time\s*=\s*(.+)$/', $line, $matches)) {
                $post['date'] = trim($matches[1]);
                $post['timestamp'] = strtotime($post['date']);
            } elseif (preg_match('/^likes\s*=\s*(.+)$/', $line, $matches)) {
                $post['likes'] = (int)trim($matches[1]);
            } elseif (preg_match('/^comments\s*=\s*(.+)$/', $line, $matches)) {
                $post['comments'] = (int)trim($matches[1]);
            }
        } else {
            $contentLines[] = $line;
        }
    }
    
    $post['content'] = implode("\n", $contentLines);
    
    if (empty($post['title']) || empty($post['author']) || empty($post['date'])) {
        return null;
    }
    
    return $post;
}

// 获取帖子点赞数量
function getPostLikeCount($postId) {
    $likesDir = __DIR__ . '/likes/';
    $count = 0;
    
    if (!is_dir($likesDir)) {
        return 0;
    }
    
    $userFiles = glob($likesDir . '*.txt');
    
    foreach ($userFiles as $file) {
        $content = file_get_contents($file);
        if ($content !== false) {
            $userLikes = explode(',', trim($content));
            if (in_array($postId, $userLikes)) {
                $count++;
            }
        }
    }
    
    return $count;
}

// 获取帖子评论数量
function getPostCommentCount($postId) {
    $commentsDir = __DIR__ . '/comments/';
    
    if (!is_dir($commentsDir)) {
        return 0;
    }
    
    $commentFiles = glob($commentsDir . $postId . '_*.txt');
    return count($commentFiles);
}
?>