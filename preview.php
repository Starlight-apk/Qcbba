<?php
/* 文件预览器 - 支持多种格式预览 + ZIP浏览 + 响应式设计 */
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

/* ---------- 取参数 ---------- */
$uid   = $_GET['uid'] ?? '';
$path  = isset($_GET['path'])  ? trim($_GET['path'], '/')  : '';
$file  = $_GET['file']  ?? '';
$zip_path = $_GET['zip_path'] ?? ''; // ZIP内部路径
$zip_file = $_GET['zip_file'] ?? ''; // ZIP内部文件
$isShared = isset($_GET['share']);
$ajaxMode = isset($_GET['ajax']);
$baseDir  = __DIR__ . "/cloud/user/$uid/";
$filePath = $baseDir . ($path ? $path . '/' : '') . $file;

// 安全检查
if ((!$uid || !$file || !file_exists($filePath) || !is_file($filePath)) && !$isShared) {
    die('文件不存在或无权访问');
}

$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$fileName  = basename($file);

/* ---------- 类型表 ---------- */
$textTypes   = ['txt','php','html','htm','css','js','json','xml','csv','md','log','ini','conf','sql','yaml','yml'];
$codeTypes   = ['php','html','htm','css','js','json','xml','sql','yaml','yml'];
$officeTypes = ['doc','docx','ppt','pptx','xls','xlsx'];
$imageTypes  = ['jpg','jpeg','png','gif','bmp','webp','svg','ico'];
$audioTypes  = ['mp3','wav','ogg','m4a','flac'];
$videoTypes  = ['mp4','avi','mov','wmv','flv','webm','mkv'];
$pdfTypes    = ['pdf'];

$isText  = in_array($extension, $textTypes);
$isCode  = in_array($extension, $codeTypes);
$isOffice= in_array($extension, $officeTypes);
$isImage = in_array($extension, $imageTypes);
$isAudio = in_array($extension, $audioTypes);
$isVideo = in_array($extension, $videoTypes);
$isPdf   = in_array($extension, $pdfTypes);
$isZip   = ($extension === 'zip');
$isEditable = $isText && !$isShared;

/* ---------- 临时文件管理 ---------- */
$tempDir = __DIR__ . '/temp/';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

// 清理过期的临时文件（超过1分钟）
$cleanupTime = time() - 60;
foreach (glob($tempDir . "preview_*") as $tempFile) {
    if (filemtime($tempFile) < $cleanupTime) {
        unlink($tempFile);
    }
}

/* ---------- ZIP 文件处理 ---------- */
$zipContents = [];
$currentZipPath = $zip_path;
$tempFilePath = '';

if ($isZip && class_exists('ZipArchive') && empty($zip_file)) {
    $zip = new ZipArchive();
    if ($zip->open($filePath) === TRUE) {
        $seenItems = [];
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) continue;

            $name = $stat['name'];
            $size = $stat['size'];
            $compressedSize = $stat['comp_size'] ?: 0;
            $ratio = $size > 0 ? round(100 - ($compressedSize / $size * 100), 1) : 0;
            
            // 根据您的判定标准：以斜杠结尾的是文件夹，否则是文件
            $is_dir = substr($name, -1) === '/';
            
            // 处理当前路径
            if ($currentZipPath) {
                // 只显示当前目录的直接子项
                if (strpos($name, $currentZipPath) !== 0) continue;
                
                $relativePath = substr($name, strlen($currentZipPath));
                if ($relativePath === '') continue;
                
                // 检查是否是直接子级
                $parts = explode('/', rtrim($relativePath, '/'));
                if (count($parts) > 1) {
                    // 是多级路径，创建文件夹项
                    $firstPart = $parts[0];
                    $folderPath = $currentZipPath . $firstPart . '/';
                    
                    if (!isset($seenItems[$folderPath])) {
                        $seenItems[$folderPath] = true;
                        $zipContents[] = [
                            'name' => $folderPath,
                            'size' => 0,
                            'compressed_size' => 0,
                            'ratio' => 0,
                            'is_dir' => true,
                            'display_name' => $firstPart,
                            'ext' => ''
                        ];
                    }
                    continue;
                }
            } else {
                // 根目录：只显示第一级
                $parts = explode('/', rtrim($name, '/'));
                if (count($parts) > 1) {
                    $firstPart = $parts[0];
                    $folderPath = $firstPart . '/';
                    
                    if (!isset($seenItems[$folderPath])) {
                        $seenItems[$folderPath] = true;
                        $zipContents[] = [
                            'name' => $folderPath,
                            'size' => 0,
                            'compressed_size' => 0,
                            'ratio' => 0,
                            'is_dir' => true,
                            'display_name' => $firstPart,
                            'ext' => ''
                        ];
                    }
                    continue;
                }
            }

            // 处理显示名称
            if ($currentZipPath) {
                $displayName = $is_dir ? 
                    rtrim(substr($name, strlen($currentZipPath)), '/') : 
                    basename($name);
            } else {
                $displayName = $is_dir ? rtrim($name, '/') : $name;
            }

            $zipContents[] = [
                'name' => $name,
                'size' => $size,
                'compressed_size' => $compressedSize,
                'ratio' => $ratio,
                'is_dir' => $is_dir,
                'display_name' => $displayName,
                'ext' => !$is_dir ? strtolower(pathinfo($name, PATHINFO_EXTENSION)) : ''
            ];
        }
        $zip->close();

        // 排序：文件夹在前
        usort($zipContents, function($a, $b) {
            if ($a['is_dir'] && !$b['is_dir']) return -1;
            if (!$a['is_dir'] && $b['is_dir']) return 1;
            return strcmp($a['display_name'], $b['display_name']);
        });
    }
}

/* ---------- ZIP 内部文件预览 ---------- */
$zipFileContent = '';
if ($isZip && $zip_file && class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($filePath) === TRUE) {
        $content = $zip->getFromName($zip_file);
        if ($content !== false) {
            $zipFileContent = $content;
            
            // 创建临时文件用于预览
            $tempFileName = 'preview_' . md5($filePath . $zip_file) . '_' . basename($zip_file);
            $tempFilePath = $tempDir . $tempFileName;
            file_put_contents($tempFilePath, $content);
            
            // 根据扩展名设置预览类型
            $zip_ext = strtolower(pathinfo($zip_file, PATHINFO_EXTENSION));
            $isText = in_array($zip_ext, $textTypes);
            $isCode = in_array($zip_ext, $codeTypes);
            $isImage = in_array($zip_ext, $imageTypes);
            $isAudio = in_array($zip_ext, $audioTypes);
            $isVideo = in_array($zip_ext, $videoTypes);
            $isPdf = in_array($zip_ext, $pdfTypes);
            $isOffice = in_array($zip_ext, $officeTypes);
        }
        $zip->close();
    }
}

/* ---------- Ajax 响应 ---------- */
if ($ajaxMode && $isZip) {
    ob_clean();
    foreach ($zipContents as $item):
        $click = $item['is_dir'] ? 
            "enterZipFolder('".rawurlencode($item['name'])."')" : 
            "previewZipFile('".rawurlencode($item['name'])."')";
        ?>
        <div class="zip-file-item <?= $item['is_dir']?'folder':'' ?>" onclick="<?= $click ?>">
            <div class="zip-file-icon">
                <i class="fas fa-<?= $item['is_dir']?'folder':'file' ?>"></i>
            </div>
            <div class="zip-file-name"><?= htmlspecialchars($item['display_name']) ?></div>
            <?php if (!$item['is_dir']): ?>
                <div class="zip-file-size"><?= $item['size']>0?formatFileSize($item['size']):'0 B' ?></div>
                <div class="zip-file-ratio"><?= $item['ratio'] ?>%</div>
            <?php else: ?>
                <div class="zip-file-size">文件夹</div>
                <div class="zip-file-ratio">-</div>
            <?php endif; ?>
        </div>
    <?php endforeach;
    exit;
}

/* ---------- 文本保存 ---------- */
if (isset($_POST['saveContent']) && $isEditable) {
    if ($zip_file) {
        // 保存ZIP内部文件（需要复杂处理，这里简化）
        die('暂不支持编辑ZIP内部文件');
    } else {
        file_put_contents($filePath, $_POST['content']);
    }
    header("Location: preview.php?uid=$uid&path=" . rawurlencode($path) . "&file=" . rawurlencode($file));
    exit;
}

/* ---------- 读取内容 ---------- */
if ($isText) {
    if ($zip_file && $tempFilePath && file_exists($tempFilePath)) {
        $content = file_get_contents($tempFilePath);
    } else {
        $content = file_get_contents($filePath);
    }
} else {
    $content = '';
}

/* ---------- 文件信息 ---------- */
$fileSize = filesize($filePath);
$fileTime = date("Y-m-d H:i:s", filemtime($filePath));

/* ---------- 下载 URL ---------- */
if ($isShared) {
    $fileUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
               . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
} else {
    $fileUrl = "?uid=" . htmlspecialchars($uid) 
               . "&path=" . rawurlencode($path) 
               . "&file=" . rawurlencode($file);
}

// 如果是ZIP内部文件预览，使用临时文件的URL
if ($zip_file && $tempFilePath && file_exists($tempFilePath)) {
    $tempFileUrl = "?temp_file=" . urlencode(basename($tempFilePath)) . "&uid=" . htmlspecialchars($uid);
} else {
    $tempFileUrl = $fileUrl;
}

/* ---------- 辅助函数 ---------- */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
  <title>预览 <?= htmlspecialchars($zip_file ?: $fileName) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/github-dark.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
  <style>
    :root{--bg:#f5f7fa;--card:#fff;--text:#222;--primary:#00c6ff;--radius:16px;--shadow:0 8px 32px rgba(0,0,0,.08);--transition:all .3s ease}
    [data-theme="dark"]{--bg:#0f1116;--card:#1a1d24;--text:#e6e6e6;--shadow:0 8px 32px rgba(0,0,0,.25)}
    *{margin:0;padding:0;box-sizing:border-box}
    body{margin:0;font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);padding:0;transition:var(--transition)}
    .preview-container{max-width:1200px;margin:0 auto;padding:15px}
    .preview-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap;gap:10px;background:var(--card);padding:15px 20px;border-radius:var(--radius);box-shadow:var(--shadow)}
    .preview-title{font-size:1.4rem;font-weight:600;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .preview-actions{display:flex;gap:10px;flex-wrap:wrap}
    .btn{padding:8px 16px;border:none;border-radius:var(--radius);background:var(--primary);color:#fff;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-size:0.9rem;transition:var(--transition)}
    .btn:hover{filter:brightness(1.1)}.btn-secondary{background:var(--bg);color:var(--text)}
    .file-info{font-size:0.85rem;opacity:0.7;margin-top:5px}
    .preview-content{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;min-height:400px}
    textarea,pre{width:100%;min-height:400px;border:none;background:var(--card);color:var(--text);padding:20px;font-family:monospace;font-size:14px;line-height:1.5;resize:vertical;margin:0}
    iframe{width:100%;height:600px;border:none}
    .edit-mode{display:none}
    .media-preview{display:flex;justify-content:center;align-items:center;padding:20px;min-height:400px}
    .media-preview img,.media-preview video,.media-preview audio{max-width:100%;max-height:70vh;border-radius:var(--radius)}
    .unsupported{padding:40px;text-align:center;font-size:1.1rem;opacity:0.7}
    .code-editor{position:relative}
    .editor-actions{position:absolute;top:10px;right:10px;z-index:10;display:flex;gap:5px}
    .editor-actions .btn{padding:6px 12px;font-size:0.8rem}
    .office-preview{height:600px;background:#f8f9fa}
    .office-preview iframe{height:100%}
    .archive-content{padding:20px}
    .archive-item{padding:10px;border-bottom:1px solid var(--bg);display:flex;justify-content:space-between;align-items:center}
    .archive-item:last-child{border-bottom:none}
    .file-icon{margin-right:10px;color:var(--primary)}
    .loading{display:flex;justify-content:center;align-items:center;height:200px;flex-direction:column;gap:15px}
    .loading-spinner{width:40px;height:40px;border:4px solid var(--bg);border-top:4px solid var(--primary);border-radius:50%;animation:spin 1s linear infinite}
    @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
    .hljs{background:transparent!important;padding:0!important}
    pre code{background:var(--card)!important;color:var(--text)!important}
    [data-theme="dark"] pre code{background:var(--card)!important;color:var(--text)!important}
    
    /* ZIP 样式 */
    .zip-preview{padding:20px}
    .zip-breadcrumb{display:flex;align-items:center;gap:8px;margin-bottom:15px;padding:10px;background:var(--bg);border-radius:var(--radius);flex-wrap:wrap}
    .zip-breadcrumb a{color:var(--primary);text-decoration:none}
    .zip-breadcrumb span{opacity:0.6}
    .zip-stats{display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:15px;margin-bottom:20px;padding:15px;background:var(--bg);border-radius:var(--radius)}
    .stat-item{text-align:center}
    .stat-value{font-size:1.3rem;font-weight:600;color:var(--primary)}
    .stat-label{font-size:0.8rem;opacity:0.7}
    .zip-contents{max-height:500px;overflow-y:auto}
    .zip-file-item{display:flex;align-items:center;padding:10px 12px;margin:5px 0;background:var(--bg);border-radius:var(--radius);transition:var(--transition);cursor:pointer}
    .zip-file-item:hover{background:var(--primary);color:#fff}
    .zip-file-item.folder{font-weight:600}
    .zip-file-icon{width:20px;margin-right:12px;text-align:center}
    .zip-file-name{flex:1;word-break:break-all}
    .zip-file-size{font-size:0.8rem;opacity:0.8;min-width:80px;text-align:right}
    .zip-file-ratio{font-size:0.7rem;opacity:0.6;min-width:50px;text-align:right;margin-left:10px}
    .zip-empty{padding:40px;text-align:center;opacity:0.6}
    .shared-badge{background:#ff6b6b;color:white;padding:2px 8px;border-radius:12px;font-size:0.7rem;margin-left:8px}
    .temp-badge{background:#4CAF50;color:white;padding:2px 8px;border-radius:12px;font-size:0.7rem;margin-left:8px}
    
    /* 移动端适配 */
    @media (max-width: 768px) {
        .preview-container{padding:10px}
        .preview-header{padding:12px 15px;flex-direction:column;align-items:stretch}
        .preview-title{font-size:1.2rem}
        .preview-actions{justify-content:center}
        .zip-stats{grid-template-columns:1fr 1fr}
        .zip-file-item{flex-wrap:wrap}
        .zip-file-name{flex-basis:100%;order:1;margin-top:5px}
        .zip-file-size,.zip-file-ratio{flex:1;text-align:left}
        .media-preview{padding:10px}
        .media-preview img,.media-preview video{max-height:50vh}
    }
    
    @media (max-width: 480px) {
        .preview-title{font-size:1.1rem}
        .btn{padding:6px 12px;font-size:0.8rem}
        .zip-stats{grid-template-columns:1fr}
        .zip-preview{padding:10px}
    }
  </style>
</head>
<body>
<div class="preview-container">
  <div class="preview-header">
    <div>
      <div class="preview-title">
        <i class="fas fa-file"></i> 
        <?= htmlspecialchars($zip_file ? basename($zip_file) : $fileName) ?>
        <? if ($isShared): ?><span class="shared-badge">分享文件</span><? endif; ?>
        <? if ($zip_file): ?><span class="temp-badge">临时预览</span><? endif; ?>
      </div>
      <div class="file-info">
        大小: <?= formatFileSize($fileSize) ?> | 
        修改时间: <?= $fileTime ?> | 
        类型: <?= strtoupper($zip_file ? pathinfo($zip_file, PATHINFO_EXTENSION) : $extension) ?>
        <? if ($zip_file): ?> | <span style="color:#4CAF50">临时文件将在1分钟后自动删除</span><? endif; ?>
      </div>
    </div>
    <div class="preview-actions">
      <? if ($zip_file): ?>
        <a href="?<?= $isShared ? "share=".$_GET['share'] : "uid=$uid&path=".rawurlencode($path)."&file=".rawurlencode($file) ?>&zip_path=<?= rawurlencode($currentZipPath) ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> 返回ZIP</a>
      <? elseif (!$isShared): ?>
        <a href="userhtml.php?uid=<?= htmlspecialchars($uid) ?>&path=<?= rawurlencode($path) ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> 返回</a>
      <? endif; ?>
      <a href="<?= $zip_file ? $tempFileUrl . '&down=1' : $fileUrl . '&down=1' ?>" class="btn"><i class="fas fa-download"></i> 下载</a>
      <?php if ($isEditable && !$zip_file): ?>
      <button onclick="toggleEdit()" class="btn" id="editBtn"><i class="fas fa-edit"></i> 编辑文本</button>
      <button onclick="saveFile()" class="btn" id="saveBtn" style="display:none"><i class="fas fa-save"></i> 保存</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="preview-content">
    <?php if ($zip_file && $isText): ?>
      <!-- ZIP内部文本文件预览 -->
      <div class="code-editor">
        <pre id="previewContent"><code class="language-<?= $isCode ? pathinfo($zip_file, PATHINFO_EXTENSION) : 'text' ?>"><?= htmlspecialchars($content) ?></code></pre>
      </div>

    <?php elseif ($isText): ?>
      <!-- 普通文本文件预览 -->
      <div class="code-editor">
        <?php if ($isEditable): ?>
        <div class="editor-actions">
          <button onclick="toggleEdit()" class="btn btn-secondary" id="viewBtn" style="display:none"><i class="fas fa-eye"></i> 预览</button>
        </div>
        <?php endif; ?>
        <?php if ($isEditable): ?>
        <form id="editForm" method="post" class="edit-mode">
          <textarea name="content" id="fileContent" placeholder="文件内容..."><?= htmlspecialchars($content) ?></textarea>
          <input type="hidden" name="saveContent" value="1">
        </form>
        <?php endif; ?>
        <pre id="previewContent"><code class="language-<?= $isCode ? $extension : 'text' ?>"><?= htmlspecialchars($content) ?></code></pre>
      </div>

    <?php elseif ($isZip && class_exists('ZipArchive') && empty($zip_file)): ?>
      <!-- ZIP文件浏览 -->
      <div class="zip-preview">
        <div class="zip-breadcrumb" id="zipBreadcrumb">
          <a href="javascript:void(0)" onclick="enterZipFolder('')"><i class="fas fa-home"></i> 根目录</a>
          <?php
          $zipPath = '';
          if ($currentZipPath) {
              $parts = explode('/', rtrim($currentZipPath, '/'));
              foreach ($parts as $part) {
                  if ($part) {
                      $zipPath .= $part . '/';
                      echo '<span>/</span>';
                      echo '<a href="javascript:void(0)" onclick="enterZipFolder(\''.rawurlencode($zipPath).'\')">'.htmlspecialchars($part).'/</a>';
                  }
              }
          }
          ?>
        </div>

        <div class="zip-stats">
          <div class="stat-item"><div class="stat-value"><?= count($zipContents) ?></div><div class="stat-label">项目总数</div></div>
          <div class="stat-item"><div class="stat-value"><?= formatFileSize($fileSize) ?></div><div class="stat-label">压缩包大小</div></div>
          <?php
            $totalOriginalSize   = array_sum(array_column($zipContents, 'size'));
            $totalCompressedSize = array_sum(array_column($zipContents, 'compressed_size'));
            $overallRatio = $totalOriginalSize > 0 ? round(100 - ($totalCompressedSize / $totalOriginalSize * 100), 1) : 0;
          ?>
          <div class="stat-item"><div class="stat-value"><?= $overallRatio ?>%</div><div class="stat-label">压缩率</div></div>
        </div>

        <div class="zip-contents" id="zipContents">
          <?php if (!empty($zipContents)): ?>
            <?php foreach ($zipContents as $item): ?>
              <div class="zip-file-item <?= $item['is_dir'] ? 'folder' : '' ?>" 
                onclick="<?= $item['is_dir'] ? "enterZipFolder('".rawurlencode($item['name'])."')" : "previewZipFile('".rawurlencode($item['name'])."')" ?>">
                <div class="zip-file-icon"><i class="fas fa-<?= $item['is_dir'] ? 'folder' : 'file' ?>"></i></div>
                <div class="zip-file-name"><?= htmlspecialchars($item['display_name']) ?></div>
                <?php if (!$item['is_dir']): ?>
                  <div class="zip-file-size"><?= $item['size'] > 0 ? formatFileSize($item['size']) : '0 B' ?></div>
                  <div class="zip-file-ratio"><?= $item['ratio'] ?>%</div>
                <?php else: ?>
                  <div class="zip-file-size">文件夹</div><div class="zip-file-ratio">-</div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="zip-empty"><i class="fas fa-inbox" style="font-size:3rem;margin-bottom:15px"></i><div>文件夹为空</div></div>
          <?php endif; ?>
        </div>
      </div>

    <?php elseif ($isOffice): ?>
      <div class="office-preview"><iframe src="https://view.officeapps.live.com/op/embed.aspx?src=<?= urlencode($zip_file ? $tempFileUrl : $fileUrl) ?>"></iframe></div>
    <?php elseif ($isPdf): ?>
      <iframe src="https://docs.google.com/gview?url=<?= urlencode($zip_file ? $tempFileUrl : $fileUrl) ?>&embedded=true"></iframe>
    <?php elseif ($isImage): ?>
      <div class="media-preview"><img src="<?= $zip_file ? $tempFileUrl : $fileUrl ?>" alt="<?= htmlspecialchars($zip_file ?: $fileName) ?>" onclick="toggleFullscreen(this)" style="cursor:zoom-in"></div>
    <?php elseif ($isAudio): ?>
      <div class="media-preview"><audio controls style="width:80%"><source src="<?= $zip_file ? $tempFileUrl : $fileUrl ?>" type="audio/mpeg">您的浏览器不支持音频播放</audio></div>
    <?php elseif ($isVideo): ?>
      <div class="media-preview"><video controls style="max-width:90%"><source src="<?= $zip_file ? $tempFileUrl : $fileUrl ?>" type="video/mp4">您的浏览器不支持视频播放</video></div>
    <?php else: ?>
      <div class="unsupported">
        <i class="fas fa-file-excel" style="font-size:3rem;margin-bottom:20px"></i>
        <div>不支持预览此文件类型</div>
        <div style="font-size:0.9rem;margin-top:10px">文件格式: <?= strtoupper($zip_file ? pathinfo($zip_file, PATHINFO_EXTENSION) : $extension) ?></div>
        <div style="margin-top:20px"><a href="<?= $zip_file ? $tempFileUrl . '&down=1' : $fileUrl . '&down=1' ?>" class="btn"><i class="fas fa-download"></i> 下载文件</a></div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- 图片全屏 -->
<div id="imageModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);z-index:1000;justify-content:center;align-items:center">
  <img id="fullscreenImage" src="" style="max-width:90%;max-height:90%;object-fit:contain">
  <button onclick="closeFullscreen()" style="position:absolute;top:20px;right:20px;background:none;border:none;color:white;font-size:2rem;cursor:pointer">×</button>
</div>

<script>
/* ===== 代码高亮 ===== */
document.addEventListener('DOMContentLoaded', () => typeof hljs !== 'undefined' && hljs.highlightAll());

<?php if ($isEditable && !$zip_file): ?>
function toggleEdit() {
  const editForm = document.getElementById('editForm');
  const previewContent = document.getElementById('previewContent');
  const editBtn = document.getElementById('editBtn');
  const saveBtn = document.getElementById('saveBtn');
  const viewBtn = document.getElementById('viewBtn');
  const isEdit = editForm.style.display !== 'none';
  editForm.style.display = isEdit ? 'none' : 'block';
  previewContent.style.display = isEdit ? 'block' : 'none';
  editBtn.style.display = isEdit ? 'inline-flex' : 'none';
  saveBtn.style.display = isEdit ? 'none' : 'inline-flex';
  viewBtn.style.display = isEdit ? 'none' : 'inline-flex';
  if (!isEdit && typeof hljs !== 'undefined') hljs.highlightAll();
}
function saveFile() { document.getElementById('editForm').submit(); }
document.getElementById('editForm').style.display = 'none';
<?php endif; ?>

/* ===== ZIP：局部刷新 ===== */
function enterZipFolder(folderPath) {
  const box = document.getElementById('zipContents');
  box.style.opacity = '.6';
  
  const url = new URL(window.location);
  url.searchParams.set('zip_path', folderPath);
  url.searchParams.set('ajax', '1');
  
  fetch(url)
    .then(r => r.text())
    .then(html => {
        box.style.opacity = '1';
        box.innerHTML = html;
        buildBreadcrumb(folderPath);
    })
    .catch(err => {
        box.style.opacity = '1';
        console.error('加载失败:', err);
    });
}

function previewZipFile(innerPath) {
  const url = new URL(window.location);
  url.searchParams.set('zip_file', innerPath);
  window.location.href = url.toString();
}

/* 面包屑动态生成 */
function buildBreadcrumb(folderPath) {
  let html = '<a href="javascript:void(0)" onclick="enterZipFolder(\'\')"><i class="fas fa-home"></i> 根目录</a>';
  if (!folderPath) { 
    document.getElementById('zipBreadcrumb').innerHTML = html; 
    return; 
  }
  
  let parts = folderPath.replace(/\/$/,'').split('/');
  let cum = '';
  parts.forEach(p => {
      if (p) {
          cum += (cum ? '/' : '') + p + '/';
          html += '<span>/</span><a href="javascript:void(0)" onclick="enterZipFolder(\'' + encodeURIComponent(cum) + '\')">' + p + '/</a>';
      }
  });
  document.getElementById('zipBreadcrumb').innerHTML = html;
}

/* 图片全屏 */
function toggleFullscreen(img) {
  const modal = document.getElementById('imageModal');
  document.getElementById('fullscreenImage').src = img.src;
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeFullscreen() {
  document.getElementById('imageModal').style.display = 'none';
  document.body.style.overflow = 'auto';
}

document.getElementById('imageModal').addEventListener('click', e => { 
  if (e.target === e.currentTarget) closeFullscreen(); 
});

document.addEventListener('keydown', e => { 
  if (e.key === 'Escape') closeFullscreen(); 
});

/* 深色模式 */
if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
  document.body.dataset.theme = 'dark';
}

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
  document.body.dataset.theme = e.matches ? 'dark' : 'light';
});
</script>
</body>
</html>