<?php
$file = __DIR__ . '/公告.txt';
if (!file_exists($file)) {
    $demo = "**服务器政策**已更新！\n\n现在每周开放\n\n'''\n/fly\n'''\n快来体验吧～";
    file_put_contents($file, $demo, LOCK_EX);
}
$src = file_get_contents($file);
$src = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
$src = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $src);
$src = preg_replace_callback(
    "/'''(.*?)'''/s",
    function ($m) {
        $code = htmlspecialchars(trim($m[1]));
        $id   = 'block-' . substr(md5($code), 0, 8);
        return <<<HTML
<div class="code-box">
  <div class="code-header"><span>CODE</span><button onclick="copyCode('{$id}')">复制</button></div>
  <pre id="{$id}">{$code}</pre>
</div>
HTML;
    },
    $src
);
$src = nl2br($src);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>航站公告</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{
  --bg:#f0f2f5;
  --card:#ffffff;
  --text:#222;
  --accent:#00c6ff;
  --glass:rgba(255,255,255,.25);
  --shadow:0 8px 32px rgba(0,0,0,.08);
  --radius:16px;
}
@media (prefers-color-scheme: dark){
  :root{
    --bg:#0f1116;
    --card:#1a1d24;
    --text:#e6e6e6;
    --glass:rgba(26,29,36,.45);
    --shadow:0 8px 32px rgba(0,0,0,.25);
  }
}
*{
  box-sizing:border-box;
  margin:0;
  padding:0;
}
body{
  font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,'PingFang SC','Microsoft YaHei',sans-serif;
  background:var(--bg);
  color:var(--text);
  display:flex;
  align-items:center;
  justify-content:center;
  min-height:100vh;
  padding:2rem;
  animation:fadeIn .8s ease;
}
@keyframes fadeIn{
  from{
    opacity:0;
    transform:translateY(20px)
  }
  to{
    opacity:1;
    transform:translateY(0)
  }
}
.container{
  width:100%;
  max-width:680px;
  background:var(--glass);
  backdrop-filter:blur(20px);
  border:1px solid rgba(255,255,255,.1);
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  padding:2.5rem 3rem;
  animation:up 1s ease;
}
@keyframes up{
  from{
    transform:translateY(40px);
    opacity:0
  }
  to{
    transform:translateY(0);
    opacity:1
  }
}
h1{
  font-size:2rem;
  margin-bottom:1.5rem;
  background:linear-gradient(135deg,var(--accent),#0072ff);
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
  display:inline-block;
  position:relative;
}
h1::after{
  content:'';
  position:absolute;
  left:0;
  bottom:-4px;
  height:3px;
  width:100%;
  background:linear-gradient(90deg,var(--accent),#0072ff);
  border-radius:2px;
}
strong{
  color:var(--accent);
}
.code-box{
  margin:1.2rem 0;
  border-radius:12px;
  overflow:hidden;
  box-shadow:0 4px 16px rgba(0,0,0,.1);
}
.code-header{
  display:flex;
  justify-content:space-between;
  align-items:center;
  background:#161b22;
  color:#c9d1d9;
  padding:.5rem 1rem;
  font-size:.85rem;
}
.code-header button{
  background:var(--accent);
  color:#fff;
  border:none;
  border-radius:4px;
  padding:4px 10px;
  cursor:pointer;
  transition:background .2s;
}
.code-header button:hover{
  background:#0072ff;
}
pre{
  background:#0d1117;
  color:#58a6ff;
  padding:1rem;
  overflow-x:auto;
  font-size:.9rem;
  line-height:1.5;
}
@media (max-width:600px){
  body{
    padding:1rem
  }
  .container{
    padding:1.5rem
  }
}
</style>
</head>
<body>
  <div class="container">
    <h1>航站公告</h1>
    <div class="content"><?php echo $src; ?></div>
  </div>

<script>
function copyCode(id){
  const pre=document.getElementById(id);
  const text=pre.textContent;
  navigator.clipboard
    ? navigator.clipboard.writeText(text).then(()=>feedback(event.target))
    : fallbackCopy(text,event.target);
}
function fallbackCopy(text,btn){
  const ta=document.createElement('textarea');
  ta.value=text;ta.style.position='fixed';ta.style.opacity='0';
  document.body.appendChild(ta);ta.select();document.execCommand('copy');
  document.body.removeChild(ta);feedback(btn);
}
function feedback(btn){
  const orig=btn.textContent;btn.textContent='已复制!';
  btn.style.background='#4CAF50';
  setTimeout(()=>{btn.textContent=orig;btn.style.background='var(--accent)';},1500);
}
</script>
</body>
</html>
