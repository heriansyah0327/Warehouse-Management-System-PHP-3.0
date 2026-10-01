<?php
$flash = flash_get();
$me = current_user();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? 'Management') ?></title>
<link rel="stylesheet" href="<?= e($base) ?>assets/css/style.css">
</head>
<body>
<div class="app-shell">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <div class="main-area">
        <div class="topbar">
            <button type="button" class="menu-toggle" data-sidebar-toggle aria-label="Buka menu" aria-expanded="false">☰</button>
            <div class="page-title"><?= e($page_header ?? '') ?></div>
            <div class="user-chip">
                <span class="user-info"><?= e($me['full_name']) ?> · <?= e(role_label($me['role'])) ?></span>
                <div class="avatar"><?= e(strtoupper(substr($me['full_name'] ?? '?', 0, 1))) ?></div>
                <a class="btn btn-outline user-action" href="<?= e($base) ?>ganti_password.php">🔑 Ganti Password</a>
                <a class="btn btn-outline user-action" href="<?= e($base) ?>logout.php">Keluar</a>
            </div>
        </div>

        <div class="content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
            <?php endif; ?>
