<?php
// 零校验，只看文件
$uid = $_GET['uid'] ?? '';
header('Content-Type: application/json; charset=utf-8');
$isAdmin = file_exists(__DIR__."/../login/users/$uid.txt") 
           && substr(trim(file(__DIR__."/../login/users/$uid.txt")[0]), -9) === 'role=admin';
echo json_encode(['is_admin' => $isAdmin]);
