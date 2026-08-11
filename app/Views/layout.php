<?php use SPFPU\Core\{Csrf,View}; ?>
<!doctype html><html lang="ms"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=View::e($title??'SPFPU')?> · SPFPU</title><link rel="stylesheet" href="/assets/app.css"><script src="/assets/app.js" defer></script></head>
<body class="<?=$user?'app-shell':'auth-shell'?>">
<?php if($user): ?>
<a class="skip-link" href="#kandungan">Langkau ke kandungan</a>
<header class="topbar"><a class="brand" href="/" aria-label="SPFPU, halaman utama"><span class="brand-mark"><img src="/assets/uthm-logo.png" alt="UTHM"></span><span><strong>SPFPU</strong><small>PPUU · UTHM</small></span></a><button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="primary-nav">Menu</button><nav id="primary-nav"><a href="/"<?=($_SERVER['REQUEST_URI']==='/'?' aria-current="page"':'')?>>Kategori</a><a href="/carian">Carian</a><?php if($user['role']==='Admin'):?><a href="/admin/pengguna">Pengguna</a><a href="/admin/audit">Audit</a><?php endif?><a href="/profil"><?=View::e($user['fullname'])?></a><form method="post" action="/logout"><?=Csrf::field()?><button class="text-button">Log keluar</button></form></nav></header>
<?php if($user['reset_warning']):?><div class="warning-banner" role="alert"><strong>Kata laluan sementara masih digunakan.</strong> <a href="/profil#kata-laluan">Tukar sekarang</a> untuk melindungi akaun anda.</div><?php endif?>
<?php endif?>
<main id="kandungan" class="<?=$user?'workspace':'auth-main'?>">
<?php foreach($flashes as $flash):?><div class="flash flash-<?=View::e($flash['type'])?>" role="status"><?=View::e($flash['message'])?></div><?php endforeach?>
<?=$content?>
</main>
<?php if($user):?><footer><span>SPFPU · Sistem metadata surat PPUU</span><span>Asia/Kuala Lumpur · <?=date('d.m.Y H:i')?></span></footer><?php endif?>
</body></html>
