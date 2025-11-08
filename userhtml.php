<?php
/* 云盘 - 手机全屏 + 深色 + 面包屑 + 进入文件夹 + 拖拽上传 + 动画 + 分享链接 + 高速上传 */
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

// 优化PHP配置 - 提高上传性能
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');
@ini_set('post_max_size', '600M');
@ini_set('upload_max_filesize', '500M');
@ini_set('max_input_time', '300');

/* ------- 取 UID + 路径 ------- */
$uid   = $_POST['uid'] ?? $_GET['uid'] ?? '';
$guest = $uid === '';
$baseDir = __DIR__ . "/cloud/user/$uid/";
$path    = isset($_GET['path']) ? trim($_GET['path'], '/') : '';
$currDir = $guest ? '' : ($baseDir . ($path ? $path . '/' : ''));

/* ------- 动态空间配置 ------- */
function getUserSpaceLimit($uid) {
    $configFile = __DIR__ . '/space_config.json';
    if (!file_exists($configFile)) {
        return 1024; // 默认 1024 MB
    }
    
    $config = json_decode(file_get_contents($configFile), true);
    if (!$config) {
        return 1024; // 默认 1024 MB
    }
    
    $defaultSpace = isset($config['default_space']) ? $config['default_space'] : 1024;
    
    if (isset($config['user_spaces'][$uid])) {
        return $config['user_spaces'][$uid]; // 直接返回 MB 数值
    }
    
    return $defaultSpace;
}

// 获取用户空间限制（MB）
$TOTAL_SPACE_MB = getUserSpaceLimit($uid);
// 转换为字节用于存储计算
$TOTAL_SPACE = $TOTAL_SPACE_MB * 1048576;

/* ------- 分享链接功能 ------- */
if (!$guest && isset($_GET['share'])) {
    $shareItem = basename($_GET['share']);
    $sharePath = $path ? $path . '/' . $shareItem : $shareItem;
    
    // 生成分享链接
    $shareToken = base64_encode($uid . '|' . $sharePath);
    $shareUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
                . "://$_SERVER[HTTP_HOST]$_SERVER[PHP_SELF]" 
                . "?share=$shareToken";
    
    header('Content-Type: application/json');
    echo json_encode(['url' => $shareUrl]);
    exit;
}

/* ------- 访问分享链接 ------- */
if (isset($_GET['share']) && !isset($_GET['uid'])) {
    $shareToken = $_GET['share'];
    $decoded = base64_decode($shareToken);
    $parts = explode('|', $decoded, 2);
    
    if (count($parts) === 2) {
        $shareUid = $parts[0];
        $sharePath = $parts[1];
        $shareFile = basename($sharePath);
        $shareDir = dirname($sharePath);
        
        // 直接显示分享文件预览页面
        $previewUrl = "preview.php?uid=$shareUid&path=" . rawurlencode($shareDir) . "&file=" . rawurlencode($shareFile) . "&share=1";
        header("Location: $previewUrl");
        exit;
    }
}

if (!$guest && !is_dir($baseDir)) {
    mkdir($baseDir, 0777, true);
    mkdir($baseDir . '默认文件夹', 0777, true);
}

/* ------- 常量 ------- */
const MAX_FILES = 5;
const MAX_SIZE  = 500 * 1024 * 1024;
const CHUNK_SIZE = 512 * 1024;

function usedBytes(string $dir): int
{
    if (!is_dir($dir)) return 0;
    $total = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) $total += $f->getSize();
    return $total;
}

/* ------- 清理过期临时文件 -------- */
function cleanExpiredChunks(string $dir): void
{
    if (!is_dir($dir)) return;
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $filePath = $dir . '/' . $file;
        
        // 清理所有临时分片文件（超过30分钟）
        if (strpos($file, '.part_') !== false && is_file($filePath)) {
            if (time() - filemtime($filePath) > 1800) { // 30分钟过期
                unlink($filePath);
            }
        }
        
        // 清理未完成的上传文件（超过1小时）
        if (strpos($file, '.uploading') !== false && is_file($filePath)) {
            if (time() - filemtime($filePath) > 3600) {
                unlink($filePath);
            }
        }
        
        if (is_dir($filePath)) {
            cleanExpiredChunks($filePath);
        }
    }
}

// 每次访问都清理过期临时文件
if (!$guest) {
    cleanExpiredChunks($baseDir);
}

/* ------- 清理分片文件的辅助函数 -------- */
function cleanupChunkFiles($filePath, $chunks) {
    // 清理所有分片文件
    for ($i = 0; $i < $chunks; $i++) {
        $chunkFile = $filePath . '.part_' . $i;
        if (file_exists($chunkFile)) {
            unlink($chunkFile);
        }
    }
    
    // 额外清理可能存在的其他分片文件
    $chunkFiles = glob($filePath . '.part_*');
    foreach ($chunkFiles as $chunkFile) {
        if (file_exists($chunkFile)) {
            unlink($chunkFile);
        }
    }
}

/* ------- 分片上传处理 ------- */
if (!$guest && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chunk'])) {
    $chunk = (int)$_POST['chunk'];
    $chunks = (int)$_POST['chunks'];
    $fileName = basename($_POST['name']);
    $filePath = $currDir . $fileName;
    
    // 创建上传标记文件
    $uploadingFlag = $filePath . '.uploading';
    if ($chunk === 0) {
        file_put_contents($uploadingFlag, time());
    }
    
    // 创建临时分片文件
    $chunkPath = $filePath . '.part_' . $chunk;
    
    // 移动上传的分片
    if (move_uploaded_file($_FILES['file']['tmp_name'], $chunkPath)) {
        // 如果是最后一个分片，合并文件
        if ($chunk == $chunks - 1) {
            // 检查总空间
            $used = usedBytes($baseDir);
            $totalSize = 0;
            
            // 计算总文件大小
            for ($i = 0; $i < $chunks; $i++) {
                $chunkFile = $filePath . '.part_' . $i;
                if (file_exists($chunkFile)) {
                    $totalSize += filesize($chunkFile);
                }
            }
            
            if ($used + $totalSize > $TOTAL_SPACE) {
                // 清理所有分片文件和标记
                cleanupChunkFiles($filePath, $chunks);
                if (file_exists($uploadingFlag)) {
                    unlink($uploadingFlag);
                }
                die('no_space');
            }
            
            // 合并文件
            $out = @fopen($filePath, "wb");
            if ($out) {
                $mergeSuccess = true;
                for ($i = 0; $i < $chunks; $i++) {
                    $chunkFile = $filePath . '.part_' . $i;
                    $in = @fopen($chunkFile, "rb");
                    if ($in) {
                        while ($buff = fread($in, 4096)) {
                            fwrite($out, $buff);
                        }
                        @fclose($in);
                    } else {
                        $mergeSuccess = false;
                        break;
                    }
                }
                @fclose($out);
                
                if ($mergeSuccess) {
                    // 合并成功后才删除分片文件
                    cleanupChunkFiles($filePath, $chunks);
                    // 删除上传标记文件
                    if (file_exists($uploadingFlag)) {
                        unlink($uploadingFlag);
                    }
                    echo 'ok';
                } else {
                    // 合并失败，只清理分片文件，不删除目标文件
                    cleanupChunkFiles($filePath, $chunks);
                    if (file_exists($uploadingFlag)) {
                        unlink($uploadingFlag);
                    }
                    echo 'merge_error';
                }
            } else {
                // 无法创建目标文件，清理所有分片
                cleanupChunkFiles($filePath, $chunks);
                if (file_exists($uploadingFlag)) {
                    unlink($uploadingFlag);
                }
                echo 'merge_error';
            }
        } else {
            echo 'chunk_ok';
        }
    } else {
        echo 'upload_error';
    }
    exit;
}

/* ------- 清理上传取消的分片 -------- */
if (!$guest && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup'])) {
    $fileName = basename($_POST['cleanup']);
    $filePath = $currDir . $fileName;
    
    // 清理该文件的所有分片和标记
    cleanupChunkFiles($filePath, 1000); // 使用足够大的数字确保清理所有分片
    $uploadingFlag = $filePath . '.uploading';
    if (file_exists($uploadingFlag)) {
        unlink($uploadingFlag);
    }
    echo 'cleaned';
    exit;
}

/* ------- 传统上传（兼容小文件） -------- */
if (!$guest && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    $files = $_FILES['files'];
    $count = count($files['name']);
    if ($count > MAX_FILES) die('too_many');
    
    $used = usedBytes($baseDir);
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i]) continue;
        if ($files['size'][$i] > MAX_SIZE) die('big_file');
        $used += $files['size'][$i];
        if ($used > $TOTAL_SPACE) die('no_space');
        $target = $currDir . basename($files['name'][$i]);
        
        // 使用流式写入提高大文件上传性能
        $src = fopen($files['tmp_name'][$i], 'rb');
        $dst = fopen($target, 'wb');
        if ($src && $dst) {
            while (!feof($src)) {
                fwrite($dst, fread($src, 8192));
            }
            fclose($src);
            fclose($dst);
        } else {
            move_uploaded_file($files['tmp_name'][$i], $target);
        }
    }
    exit('ok');
}

/* ------- 新建文件夹 -------- */
if (!$guest && isset($_POST['newFolder'])) {
    $name = trim($_POST['newFolder']);
    if ($name !== '') mkdir($currDir . basename($name), 0777, true);
    header("Location: ?uid=$uid&path=" . rawurlencode($path));
    exit;
}

/* ------- 重命名 -------- */
if (!$guest && isset($_POST['rename'])) {
    $oldName = basename($_POST['oldName'] ?? '');
    $newName = basename($_POST['newName'] ?? '');
    if ($oldName && $newName && $oldName !== $newName) {
        $oldPath = $currDir . $oldName;
        $newPath = $currDir . $newName;
        if (file_exists($oldPath) && !file_exists($newPath)) {
            rename($oldPath, $newPath);
        }
    }
    header("Location: ?uid=$uid&path=" . rawurlencode($path));
    exit;
}

/* ------- 删除（文件 & 文件夹递归） -------- */
if (!$guest && isset($_GET['del'])) {
    $item = $currDir . basename($_GET['del']);
    if (is_file($item)) {
        unlink($item);
    } elseif (is_dir($item)) {
        $it = new RecursiveDirectoryIterator($item, FilesystemIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $f) {
            if ($f->isDir()) {
                rmdir($f->getPathname());
            } else {
                unlink($f->getPathname());
            }
        }
        rmdir($item);
    }
    header("Location: ?uid=$uid&path=" . rawurlencode($path));
    exit;
}

/* ------- 下载 -------- */
if (isset($_GET['down'])) {
    $file = $currDir . basename($_GET['down']);
    if (is_file($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurldecode(basename($file)) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

/* ------- 获取文件列表（过滤临时分片文件） -------- */
function getFilteredFiles(string $dir): array
{
    if (!is_dir($dir)) return [];
    
    $files = scandir($dir);
    $filtered = [];
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        // 过滤临时分片文件和上传标记文件
        if (strpos($file, '.part_') !== false || strpos($file, '.uploading') !== false) {
            continue;
        }
        
        $filtered[] = $file;
    }
    
    return $filtered;
}

$files  = $guest || !is_dir($currDir) ? [] : getFilteredFiles($currDir);
$usedMB = round(usedBytes($baseDir) / 1048576, 2);
$percent= min(100, round($usedMB / $TOTAL_SPACE_MB * 100));
$breadcrumbs = $guest ? [] : explode('/', $path);
$breadPath = '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
  <title>云盘 - Qcbba</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="animations.css">
  <style>
    :root{--bg:#f5f7fa;--card:#fff;--text:#222;--primary:#00c6ff;--radius:16px;--shadow:0 8px 32px rgba(0,0,0,.08);--transition:all .3s ease}
    [data-theme="dark"]{--bg:#0f1116;--card:#1a1d24;--text:#e6e6e6;--shadow:0 8px 32px rgba(0,0,0,.25)}
    html,body{margin:0;height:100%;font-family:'Inter',sans-serif;background:var(--bg);color:var(--text)}
    body{display:flex;justify-content:center;align-items:center;padding:0 10px;transition:var(--transition)}
    .fullscreen{width:100%;height:100%;display:flex;flex-direction:column}
    header{flex-shrink:0;display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:var(--card);box-shadow:var(--shadow)}
    main{flex:1;overflow-y:auto;padding:20px}
    h1{font-size:1.6rem;margin:0}
    .header-actions{display:flex;align-items:center;gap:12px}
    .dark-toggle{font-size:1.2rem;cursor:pointer}
    .home-btn{background:var(--primary);color:#fff;padding:8px 14px;border-radius:var(--radius);text-decoration:none;font-weight:600;font-size:.9rem;transition:var(--transition);display:flex;align-items:center;gap:6px}
    .home-btn:hover{filter:brightness(1.1)}
    .info{font-size:.9rem;opacity:.8;margin-top:4px}
    .bar{height:6px;background:var(--bg);border-radius:3px;overflow:hidden;margin:10px 0 20px}
    .bar div{height:100%;background:var(--primary);width:<?=$percent?>%;transition:width .5s ease}
    .bread{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:15px;font-size:.9rem}
    .bread a{color:var(--primary)}.bread span{opacity:.6}
    .toolbar{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap}
    .btn{padding:10px 14px;border:none;border-radius:var(--radius);background:var(--primary);color:#fff;font-weight:600;cursor:pointer;transition:var(--transition);display:inline-flex;align-items:center;gap:6px}
    .btn:hover{filter:brightness(1.1)}.btn-secondary{background:var(--bg);color:var(--text)}
    .file-input-wrapper{position:relative;overflow:hidden;display:inline-block}
    .file-input-wrapper input[type="file"]{position:absolute;left:0;top:0;opacity:0;width:100%;height:100%;cursor:pointer}
    
    /* 列表样式 */
    .file-list-container{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden}
    .file-list-header{display:grid;grid-template-columns:3fr 1fr 1fr 1fr;gap:15px;padding:15px 20px;background:var(--bg);font-weight:600;font-size:0.9rem;border-bottom:1px solid var(--bg)}
    .file-list-items{max-height:500px;overflow-y:auto}
    .file-list-item{display:grid;grid-template-columns:3fr 1fr 1fr 1fr;gap:15px;padding:12px 20px;border-bottom:1px solid var(--bg);transition:var(--transition);align-items:center}
    .file-list-item:hover{background:var(--bg)}
    .file-list-item:last-child{border-bottom:none}
    .file-item-main{display:flex;align-items:center;gap:12px}
    .file-icon{font-size:1.2rem;width:24px;text-align:center}
    .file-name{font-weight:500;cursor:pointer;flex:1}
    .file-name:hover{color:var(--primary)}
    .file-actions{display:flex;gap:8px;justify-content:flex-end}
    .file-action-btn{color:var(--primary);background:none;border:none;cursor:pointer;padding:6px;border-radius:4px;transition:var(--transition)}
    .file-action-btn:hover{background:var(--primary);color:#fff}
    .file-size,.file-time{font-size:0.85rem;opacity:0.8;text-align:left}
    
    /* 上传进度样式 */
    #progressOverlay{position:fixed;inset:0;background:rgba(0,0,0,.6);display:none;place-items:center;z-index:999}
    #progressBox{background:var(--card);padding:2rem;border-radius:var(--radius);width:90%;max-width:500px;text-align:center}
    #progressBar{width:100%;height:8px;background:var(--bg);border-radius:4px;overflow:hidden;margin-top:1rem}
    #progressBar div{height:100%;background:var(--primary);width:0%;transition:width .2s linear}
    #progressPercent{margin-top:.5rem;font-size:.9rem}
    .upload-speed{font-size:0.8rem;opacity:0.7;margin-top:5px}
    .upload-status{display:flex;justify-content:space-between;align-items:center;margin-top:10px;font-size:0.8rem}
    .upload-file-list{margin-top:15px;max-height:200px;overflow-y:auto}
    .upload-file-item{display:flex;justify-content:space-between;align-items:center;padding:8px;margin:5px 0;background:var(--bg);border-radius:var(--radius)}
    .file-progress{flex:1;height:6px;background:var(--card);border-radius:3px;margin:0 10px;overflow:hidden}
    .file-progress-bar{height:100%;background:var(--primary);transition:width 0.3s ease;width:0%}
    .file-percent{font-size:0.8rem;min-width:40px;text-align:right}
    
    /* 分片进度指示器 */
    .chunk-indicators{display:flex;justify-content:center;gap:15px;margin:15px 0}
    .chunk-indicator{width:60px;height:60px;border:2px solid var(--bg);border-radius:8px;position:relative;overflow:hidden;background:var(--card)}
    .chunk-waves{position:absolute;bottom:0;left:0;width:100%;height:0%;background:linear-gradient(to top, var(--primary), #00a8ff);transition:height 0.3s ease}
    .chunk-waves::before,.chunk-waves::after{content:'';position:absolute;width:200%;height:200%;top:-50%;border-radius:40%;background:rgba(255,255,255,0.3);animation:wave 2s infinite linear}
    .chunk-waves::before{left:-50%;animation-delay:0s}
    .chunk-waves::after{right:-50%;animation-delay:-1s}
    @keyframes wave{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
    
    /* 拖拽 */
    .drop-zone{border:2px dashed var(--bg);border-radius:var(--radius);padding:2rem;text-align:center;margin-bottom:20px;transition:var(--transition)}
    .drop-zone.dragover{border-color:var(--primary);background:var(--bg)}
    
    /* 空状态 */
    .empty-state{text-align:center;padding:60px 20px;color:var(--text);opacity:0.6}
    .empty-state i{font-size:4rem;margin-bottom:20px;opacity:0.4}
    .empty-state div{font-size:1.1rem}
    
    @media (max-width:768px){
        .file-list-header{grid-template-columns:2fr 1fr 1fr;padding:12px 15px}
        .file-list-item{grid-template-columns:2fr 1fr 1fr;padding:10px 15px;gap:10px}
        .file-time{display:none}
        .file-actions{gap:6px}
        .file-action-btn{padding:4px}
    }
    
    @media (max-width:600px){
        h1{font-size:1.4rem}
        .btn{font-size:.9rem;padding:8px 12px}
        .header-actions{gap:8px}
        .home-btn{padding:6px 10px;font-size:.8rem}
        .chunk-indicator{width:50px;height:50px}
        .file-list-header{grid-template-columns:1fr 1fr;font-size:0.8rem}
        .file-list-item{grid-template-columns:1fr 1fr;gap:8px}
        .file-size{display:none}
    }
  </style>
</head>
<body class="page-load">
<div class="fullscreen fade-in-up">
  <header class="fade-in-down">
    <h1><i class="fas fa-cloud"></i> 云盘</h1>
    <div class="header-actions">
      <a href="http://123.60.174.101:48053/" class="home-btn fade-in-right delay-100">
        <i class="fas fa-home"></i> 主页
      </a>
      <span class="dark-toggle" onclick="document.body.dataset.theme=document.body.dataset.theme==='dark'?'auto':'dark'" title="切换深色"><i class="fas fa-moon"></i></span>
    </div>
  </header>

  <main class="fade-in-up delay-200">
    <div class="info fade-in-left delay-300">用户：<?=$guest?'未绑定':htmlspecialchars($uid)?> | <?=$usedMB?> MB / <?=$TOTAL_SPACE_MB?> MB</div>
    <div class="bar fade-in-left delay-400"><div></div></div>

    <? if ($guest): ?>
      <div class="fade-in-up delay-500"><span class="guest">⚠️ 未绑定用户，仅演示目录</span> <a href="/login.html">去登录</a></div>
    <? else: ?>
      <div class="bread fade-in-up delay-500">
        <a href="?uid=<?=htmlspecialchars($uid)?>"><i class="fas fa-home"></i> 根</a>
        <? foreach ($breadcrumbs as $crumb):
            $breadPath .= ($breadPath ? '/' : '') . $crumb; ?>
          <span>/</span>
          <a href="?uid=<?=htmlspecialchars($uid)?>&path=<?=rawurlencode($breadPath)?>"><?=htmlspecialchars($crumb)?></a>
        <? endforeach; ?>
      </div>
    <? endif; ?>

    <form class="toolbar fade-in-up delay-600" id="upForm">
      <input type="hidden" name="uid" value="<?=htmlspecialchars($uid)?>">
      <div class="file-input-wrapper">
        <label class="btn <?= $guest ? 'btn-secondary' : '' ?>">
          <i class="fas fa-upload"></i> 选择文件
          <input type="file" id="fileInput" multiple accept="*" <?= $guest ? 'disabled' : '' ?>>
        </label>
      </div>
      <button type="button" class="btn btn-secondary" <?= $guest ? 'disabled' : '' ?>
              onclick="const f=prompt('文件夹名称');if(f){fetch('?uid=<?=htmlspecialchars($uid)?>&path=<?=rawurlencode($path)?>',{method:'POST',body:new URLSearchParams({newFolder:f})}).then(()=>location.reload())}">
        <i class="fas fa-folder-plus"></i> 新建文件夹
      </button>
      <a href="?uid=<?=htmlspecialchars($uid)?>&path=<?=rawurlencode($path)?>" class="btn btn-secondary"><i class="fas fa-sync-alt"></i> 刷新</a>
    </form>

    <div class="drop-zone fade-in-up delay-700" id="dropZone">
      <i class="fas fa-cloud-upload-alt" style="font-size:2.5rem;opacity:.6"></i>
      <div style="margin-top:8px">拖拽文件到此处上传</div>
      <div style="font-size:.8rem;opacity:.7">单次最多 5 个，单文件 ≤ 500 MB，总空间 <?=$TOTAL_SPACE_MB?> MB</div>
      <div style="font-size:.7rem;opacity:.6;margin-top:5px">支持分片上传，大文件也能快速上传</div>
    </div>

    <? if ($guest || !$files): ?>
      <div class="empty-state fade-in-up delay-800">
        <i class="fas fa-folder-open"></i>
        <div>空空如也</div>
      </div>
    <? else: ?>
      <div class="file-list-container fade-in-up delay-800">
        <!-- 列表头部 -->
        <div class="file-list-header">
          <div>名称</div>
          <div>大小</div>
          <div>修改时间</div>
          <div>操作</div>
        </div>
        
        <!-- 列表内容 -->
        <div class="file-list-items">
          <? foreach ($files as $f):
            $itemPath = $currDir . $f;
            $isDir    = is_dir($itemPath);
            $size     = $isDir ? '-' : round(filesize($itemPath)/1048576,2).' MB';
            $time     = date("Y-m-d H:i", filemtime($itemPath));
            $extension = $isDir ? '' : strtolower(pathinfo($f, PATHINFO_EXTENSION));
            $previewable = !$isDir && in_array($extension, ['txt','php','html','htm','css','js','json','xml','md','log','ini','conf','sql','pdf','doc','docx','ppt','pptx','xls','xlsx','jpg','jpeg','png','gif','bmp','webp','svg','mp3','wav','ogg','mp4','avi','mov','zip']);
          ?>
            <div class="file-list-item slide-up">
              <div class="file-item-main">
                <div class="file-icon">
                  <i class="fas fa-<?=$isDir?'folder':'file'?>" style="color:<?=$isDir?'#ffa940':'var(--primary)'?>"></i>
                </div>
                <div class="file-name" ondblclick="<?= $isDir ? "location.href='?uid=".htmlspecialchars($uid)."&path=".rawurlencode($path ? $path.'/'.$f : $f)."'" : "startRename('".htmlspecialchars($f)."')" ?>" title="<?= $isDir ? '点击进入' : '双击重命名' ?>">
                  <?=htmlspecialchars($f)?>
                </div>
              </div>
              <div class="file-size"><?=$size?></div>
              <div class="file-time"><?=$time?></div>
              <div class="file-actions">
                <? if ($isDir): ?>
                  <a href="?uid=<?=htmlspecialchars($uid)?>&path=<?=rawurlencode($path ? $path.'/'.$f : $f)?>" class="file-action-btn" title="进入">
                    <i class="fas fa-arrow-right"></i>
                  </a>
                <? else: ?>
                  <? if ($previewable): ?>
                    <a href="preview.php?uid=<?=htmlspecialchars($uid)?>&path=<?=rawurlencode($path)?>&file=<?=rawurlencode($f)?>" class="file-action-btn" title="预览">
                      <i class="fas fa-eye"></i>
                    </a>
                  <? endif; ?>
                  <a href="?uid=<?=htmlspecialchars($uid)?>&path=<?=rawurlencode($path)?>&down=<?=rawurlencode($f)?>" class="file-action-btn" title="下载">
                    <i class="fas fa-download"></i>
                  </a>
                <? endif ?>
                <? if (!$guest): ?>
                  <a href="javascript:void(0)" onclick="shareFile('<?=htmlspecialchars($f)?>')" class="file-action-btn" title="分享">
                    <i class="fas fa-share-alt"></i>
                  </a>
                  <a href="?uid=<?=htmlspecialchars($uid)?>&path=<?=rawurlencode($path)?>&del=<?=rawurlencode($f)?>" onclick="return confirm('确定要删除 <?=htmlspecialchars($f)?> 吗？此操作不可恢复！')" class="file-action-btn" title="删除">
                    <i class="fas fa-trash"></i>
                  </a>
                <? endif ?>
              </div>
            </div>
          <? endforeach; ?>
        </div>
      </div>
    <? endif; ?>
  </main>
</div>

<div id="progressOverlay">
  <div id="progressBox" class="zoom-in">
  </div>
</div>

<script>
if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) document.body.dataset.theme = 'dark';
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => document.body.dataset.theme = e.matches ? 'dark' : 'auto');

(function(){
  const uid = localStorage.getItem('uid');
  if (!uid) return;
  if (location.search.includes('uid=')) return;
  location.replace('?uid='+uid);
})();

const dropZone = document.getElementById('dropZone');
const fileIn   = document.getElementById('fileInput');
const overlay  = document.getElementById('progressOverlay');
const CHUNK_SIZE = 512 * 1024;
const CONCURRENT_CHUNKS = 3;

let uploadQueue = [];
let currentUpload = null;
let uploadStartTime = 0;
let activeChunks = 0;

function formatSpeed(bytes, seconds) {
    if (seconds === 0) return '计算中...';
    const speed = bytes / seconds;
    if (speed > 1024 * 1024) {
        return (speed / (1024 * 1024)).toFixed(2) + ' MB/s';
    } else if (speed > 1024) {
        return (speed / 1024).toFixed(2) + ' KB/s';
    } else {
        return speed.toFixed(2) + ' B/s';
    }
}

function formatTime(seconds) {
    if (seconds < 60) return Math.ceil(seconds) + '秒';
    const minutes = Math.floor(seconds / 60);
    const secs = Math.ceil(seconds % 60);
    return minutes + '分' + secs + '秒';
}

function createFileItem(file) {
    const item = document.createElement('div');
    item.className = 'upload-file-item';
    item.innerHTML = `
        <div class="file-name" style="flex:1;text-align:left;font-size:0.8rem">${file.name}</div>
        <div class="file-progress"><div class="file-progress-bar"></div></div>
        <div class="file-percent">0%</div>
    `;
    return item;
}

function updateChunkIndicator(index, progress) {
    const waveElement = document.getElementById(`chunkWave${index}`);
    if (waveElement) {
        waveElement.style.height = progress + '%';
    }
}

function resetChunkIndicators() {
    for (let i = 0; i < CONCURRENT_CHUNKS; i++) {
        updateChunkIndicator(i, 0);
    }
}

function uploadFiles(files) {
    if (files.length === 0) return;
    if (files.length > 5) { alert('一次最多 5 个'); return; }
    
    overlay.style.display = 'grid';
    document.getElementById('progressBox').innerHTML = `
        <div style="font-weight:600;margin-bottom:15px"><i class="fas fa-upload"></i> 正在上传文件...</div>
        <div class="upload-file-list" id="uploadFileList"></div>
        <div class="upload-status">
            <div id="uploadSpeed">准备上传...</div>
            <div id="uploadTime">预计时间: 计算中...</div>
        </div>
        <div class="chunk-indicators">
            <div class="chunk-indicator">
                <div class="chunk-waves" id="chunkWave0"></div>
            </div>
            <div class="chunk-indicator">
                <div class="chunk-waves" id="chunkWave1"></div>
            </div>
            <div class="chunk-indicator">
                <div class="chunk-waves" id="chunkWave2"></div>
            </div>
        </div>
        <div id="progressBar"><div></div></div>
        <div id="progressPercent">0%</div>
        <button onclick="cancelUpload()" class="btn btn-secondary" style="margin-top:15px;padding:8px 16px">
            <i class="fas fa-times"></i> 取消上传
        </button>
    `;
    
    resetChunkIndicators();
    
    const fileList = document.getElementById('uploadFileList');
    uploadQueue = [];
    
    Array.from(files).forEach(file => {
        const item = createFileItem(file);
        fileList.appendChild(item);
        uploadQueue.push({
            file: file,
            element: item,
            progressBar: item.querySelector('.file-progress-bar'),
            percentText: item.querySelector('.file-percent'),
            uploadedBytes: 0,
            totalBytes: file.size,
            uploadedChunks: 0,
            totalChunks: Math.ceil(file.size / CHUNK_SIZE)
        });
    });
    
    uploadStartTime = Date.now();
    processUploadQueue();
}

function processUploadQueue() {
    if (uploadQueue.length === 0 && (!currentUpload || currentUpload.uploadedChunks >= currentUpload.totalChunks)) {
        document.getElementById('progressBox').innerHTML = `
            <div style="text-align:center;color:var(--primary)">
                <i class="fas fa-check-circle" style="font-size:3rem;margin-bottom:15px"></i>
                <div style="font-weight:600;font-size:1.1rem">上传完成！</div>
            </div>
        `;
        setTimeout(() => {
            overlay.style.display = 'none';
            location.reload();
        }, 1500);
        return;
    }
    
    if (!currentUpload || currentUpload.uploadedChunks >= currentUpload.totalChunks) {
        currentUpload = uploadQueue.shift();
        if (currentUpload) {
            currentUpload.uploadedChunks = 0;
            uploadFileChunks();
        }
    } else {
        uploadFileChunks();
    }
}

function uploadFileChunks() {
    if (!currentUpload) return;
    
    const chunksToUpload = Math.min(
        CONCURRENT_CHUNKS - activeChunks,
        currentUpload.totalChunks - currentUpload.uploadedChunks
    );
    
    for (let i = 0; i < chunksToUpload; i++) {
        const chunkIndex = currentUpload.uploadedChunks + i;
        uploadChunk(chunkIndex);
    }
}

function uploadChunk(chunkIndex) {
    if (!currentUpload || chunkIndex >= currentUpload.totalChunks) return;
    
    const file = currentUpload.file;
    const start = chunkIndex * CHUNK_SIZE;
    const end = Math.min(start + CHUNK_SIZE, file.size);
    const chunk = file.slice(start, end);
    
    const formData = new FormData();
    formData.append('uid', '<?=htmlspecialchars($uid)?>');
    formData.append('name', file.name);
    formData.append('chunk', chunkIndex);
    formData.append('chunks', currentUpload.totalChunks);
    formData.append('file', chunk);
    
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '', true);
    activeChunks++;
    
    let chunkIndicatorIndex = -1;
    for (let i = 0; i < CONCURRENT_CHUNKS; i++) {
        const waveElement = document.getElementById(`chunkWave${i}`);
        if (waveElement && parseInt(waveElement.style.height) === 0) {
            chunkIndicatorIndex = i;
            break;
        }
    }
    
    if (chunkIndicatorIndex !== -1) {
        updateChunkIndicator(chunkIndicatorIndex, 10);
    }
    
    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable && chunkIndicatorIndex !== -1) {
            const progress = 10 + (e.loaded / e.total) * 80;
            updateChunkIndicator(chunkIndicatorIndex, progress);
        }
    };
    
    xhr.onload = function() {
        activeChunks--;
        
        if (chunkIndicatorIndex !== -1) {
            updateChunkIndicator(chunkIndicatorIndex, 0);
        }
        
        if (xhr.status === 200) {
            currentUpload.uploadedChunks++;
            
            const fileProgress = (currentUpload.uploadedChunks / currentUpload.totalChunks) * 100;
            currentUpload.progressBar.style.width = fileProgress + '%';
            currentUpload.percentText.textContent = Math.round(fileProgress) + '%';
            currentUpload.uploadedBytes = (currentUpload.uploadedChunks / currentUpload.totalChunks) * file.size;
            
            updateGlobalProgress();
            
            setTimeout(() => {
                processUploadQueue();
            }, 100);
        } else {
            alert('上传失败: ' + xhr.responseText);
            cancelUpload();
        }
    };
    
    xhr.onerror = function() {
        activeChunks--;
        if (chunkIndicatorIndex !== -1) {
            updateChunkIndicator(chunkIndicatorIndex, 0);
        }
        alert('网络错误，上传失败');
        cancelUpload();
    };
    
    xhr.send(formData);
}

function updateGlobalProgress() {
    const elapsedTime = (Date.now() - uploadStartTime) / 1000;
    let totalUploaded = 0;
    let totalSize = 0;
    
    if (currentUpload) {
        totalUploaded += currentUpload.uploadedBytes;
        totalSize += currentUpload.totalBytes;
    }
    
    uploadQueue.forEach(item => {
        totalSize += item.totalBytes;
    });
    
    const progress = totalSize > 0 ? (totalUploaded / totalSize) * 100 : 0;
    document.getElementById('progressBar').querySelector('div').style.width = progress + '%';
    document.getElementById('progressPercent').textContent = Math.round(progress) + '%';
    
    const speedElement = document.getElementById('uploadSpeed');
    const timeElement = document.getElementById('uploadTime');
    
    if (elapsedTime > 0) {
        const speed = totalUploaded / elapsedTime;
        speedElement.textContent = `速度: ${formatSpeed(totalUploaded, elapsedTime)}`;
        
        if (speed > 0) {
            const remainingBytes = totalSize - totalUploaded;
            const remainingTime = remainingBytes / speed;
            timeElement.textContent = `预计时间: ${formatTime(remainingTime)}`;
        }
    }
}

function cancelUpload() {
    // 彻底清理服务器上的临时分片文件
    if (currentUpload) {
        const formData = new FormData();
        formData.append('uid', '<?=htmlspecialchars($uid)?>');
        formData.append('cleanup', currentUpload.file.name);
        
        fetch('', {
            method: 'POST',
            body: formData
        }).finally(() => {
            location.reload();
        });
    } else {
        location.reload();
    }
}

// 页面卸载时清理未完成的上传
window.addEventListener('beforeunload', function() {
    if (currentUpload || uploadQueue.length > 0) {
        cancelUpload();
    }
});

// 拖拽上传
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    if (!'<?=$guest?>') uploadFiles(e.dataTransfer.files);
});

// 文件选择上传
fileIn.addEventListener('change', e => {
    if (!'<?=$guest?>') uploadFiles(e.target.files);
    e.target.value = '';
});

/* ------ 重命名 ------ */
function startRename(oldName) {
    const item = event.target.closest('.file-list-item');
    const nameElement = item.querySelector('.file-name');
    const oldText = oldName;
    nameElement.innerHTML = `<input class="name-input" value="${oldText}" style="width:100%;padding:4px;border:1px solid var(--primary);border-radius:4px;background:var(--bg);color:var(--text)" onkeydown="finishRename(event,'${oldText}')" onblur="finishRename(null,'${oldText}')">`;
    nameElement.querySelector('input').select();
}
function finishRename(e, oldName) {
    if (e && e.key !== 'Enter') return;
    const newName = e ? e.target.value : e.target.value;
    if (newName && newName !== oldName) {
        fetch('?uid=<?=htmlspecialchars($uid)?>&path=<?=rawurlencode($path)?>', {
            method: 'POST',
            body: new URLSearchParams({ rename: '', oldName, newName })
        }).then(() => location.reload());
    } else {
        location.reload();
    }
}

/* ------ 分享 ------ */
function shareFile(file) {
    fetch('?uid=<?=htmlspecialchars($uid)?>&path=<?=rawurlencode($path)?>&share=' + encodeURIComponent(file))
        .then(r => r.json())
        .then(data => {
            const url = data.url;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => alert('分享链接已复制到剪贴板：' + url));
            } else {
                prompt('分享链接（请手动复制）', url);
            }
        });
}
</script>
</body>
</html>