<?php
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
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$author = trim($_POST['author'] ?? '');
$uid = trim($_POST['uid'] ?? '');
$postType = trim($_POST['type'] ?? 'text');

// 验证数据
if (empty($title)) {
    sendResponse(false, '帖子标题不能为空');
}
if (empty($content)) {
    sendResponse(false, '帖子内容不能为空');
}
if (empty($author)) {
    sendResponse(false, '作者信息不能为空');
}
if (empty($uid)) {
    sendResponse(false, '用户ID不能为空');
}

// 处理图片
$imagePath = '';
if ($postType === 'image' && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = $_FILES['image']['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        sendResponse(false, '只允许上传JPEG、PNG、GIF和WebP格式的图片');
    }
    
    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        sendResponse(false, '图片大小不能超过5MB');
    }
    
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileExtension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $fileName = uniqid() . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
        $imagePath = 'uploads/' . $fileName;
    } else {
        sendResponse(false, '图片上传失败');
    }
}

// 生成帖子ID
$postsDir = __DIR__ . '/';
$maxId = 0;
$files = scandir($postsDir);
foreach ($files as $file) {
    if (preg_match('/^(\d+)\.txt$/', $file, $matches)) {
        $id = (int)$matches[1];
        if ($id > $maxId) $maxId = $id;
    }
}

$newId = $maxId + 1;

// 构建文件内容
$fileContent = "name = $title\n";
$fileContent .= "user = $author\n";
$fileContent .= "uid = $uid\n";
$fileContent .= "type = $postType\n";
$fileContent .= "time = " . date('Y-m-d H:i:s') . "\n";
if (!empty($imagePath)) {
    $fileContent .= "image = $imagePath\n";
}
$fileContent .= "---content---\n";
$fileContent .= $content;

// 保存文件
$fileName = $postsDir . $newId . '.txt';
if (file_put_contents($fileName, $fileContent) !== false) {
    sendResponse(true, '帖子发布成功', ['post_id' => $newId]);
} else {
    if (!empty($imagePath) && file_exists(__DIR__ . '/' . $imagePath)) {
        unlink(__DIR__ . '/' . $imagePath);
    }
    sendResponse(false, '保存帖子失败');
}
?>