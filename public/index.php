<?php
declare(strict_types=1);

use SPFPU\Controllers\AppController;
use SPFPU\Core\{Auth, Config, Http, Router};

$root=dirname(__DIR__);require $root.'/vendor/autoload.php';Config::load($root);
$secure=(($_SERVER['HTTPS']??'')==='on')||(($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');
session_name('SPFPU_SESSION');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);session_start();
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: same-origin');header("Permissions-Policy: camera=(), microphone=(), geolocation=()");header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
if(isset($_SESSION['last_activity'])&&time()-(int)$_SESSION['last_activity']>(int)Config::get('SESSION_IDLE_SECONDS','28800')){Auth::logout();session_start();Http::flash('error','Sesi tamat selepas lapan jam tanpa aktiviti.');Http::redirect('/login');}if(isset($_SESSION['user_id']))$_SESSION['last_activity']=time();
$r=new Router();$c=AppController::class;
$r->get('/login',[$c,'loginForm']);$r->post('/login',[$c,'login']);$r->post('/logout',[$c,'logout']);$r->get('/',[$c,'dashboard']);
$r->post('/kategori',[$c,'createCategory']);$r->get('/kategori/{id}',[$c,'category']);$r->post('/kategori/{id}/kemas-kini',[$c,'editCategory']);$r->post('/kategori/{categoryId}/fail',[$c,'createFolder']);
$r->get('/fail/{id}',[$c,'folder']);$r->post('/fail/{id}/kemas-kini',[$c,'editFolder']);$r->post('/fail/{folderId}/jilid',[$c,'nextVolume']);$r->post('/fail/{folderId}/nombor-jilid',[$c,'shiftVolumeNumbers']);$r->post('/fail/{folderId}/akses',[$c,'grant']);$r->post('/arkib/{type}/{id}',[$c,'archiveBranch']);
$r->post('/jilid/{volumeId}/entri',[$c,'createEntry']);$r->post('/entri/{id}/kemas-kini',[$c,'editEntry']);$r->post('/entri/{id}/arkib',[$c,'archiveEntry']);
$r->post('/jilid/{volumeId}/import',[$c,'importPreview']);$r->post('/import/sahkan',[$c,'importConfirm']);
$r->get('/carian',[$c,'search']);$r->get('/carian/eksport',[$c,'export']);
$r->get('/admin/pengguna',[$c,'users']);$r->post('/admin/pengguna',[$c,'createUser']);$r->post('/admin/pengguna/{id}',[$c,'userAction']);$r->get('/admin/audit',[$c,'audit']);$r->post('/admin/sandaran',[$c,'backup']);
$r->get('/profil',[$c,'profile']);$r->post('/profil',[$c,'updateProfile']);$r->post('/profil/kata-laluan',[$c,'changePassword']);
$r->dispatch($_SERVER['REQUEST_METHOD'],$_SERVER['REQUEST_URI']);
