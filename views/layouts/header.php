<div class="topbar">
    <div class="topbar-left">
        <button class="menu-toggle" id="menu-toggle"><i class="fas fa-bars"></i></button>
        <h1 class="topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></h1>
    </div>
    <div class="topbar-right">
        <span class="text-muted" style="font-size:12px;">
            <i class="fas fa-calendar-alt"></i> <?= date('Y년 m월 d일') ?>
        </span>
    </div>
</div>
