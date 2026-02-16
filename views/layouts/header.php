<div class="topbar">
    <div class="topbar-left">
        <button class="menu-toggle" id="menu-toggle"><i class="fas fa-bars"></i></button>
        <h1 class="topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></h1>
    </div>
    <div class="topbar-right">
        <div class="digital-clock" id="digitalClock">
            <i class="fas fa-clock clock-icon"></i>
            <span class="clock-date" id="clockDate"><?= date('Y.m.d') ?></span>
            <span class="clock-sep">|</span>
            <span class="clock-time" id="clockTime"><?= date('H:i:s') ?></span>
        </div>
    </div>
</div>

<style>
/* ===== Digital Clock Style ===== */
.digital-clock {
    font-family: 'Orbitron', monospace;
    font-size: 16px;
    font-weight: 500;
    color: var(--cyan-accent);
    letter-spacing: 1.5px;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}
.digital-clock .clock-icon {
    font-size: 14px;
    color: var(--cyan-accent);
    opacity: 0.8;
}
.digital-clock .clock-date {
    color: var(--text-muted);
    font-size: 16px;
    font-weight: 400;
}
.digital-clock .clock-sep {
    color: rgba(255,255,255,0.15);
    margin: 0 4px;
    font-weight: 300;
}
.digital-clock .clock-time {
    color: var(--cyan-accent);
    font-weight: 600;
}
</style>

<script>
(function() {
    function updateDigitalClock() {
        var now = new Date();
        var y = now.getFullYear();
        var m = String(now.getMonth() + 1).padStart(2, '0');
        var d = String(now.getDate()).padStart(2, '0');
        var hh = String(now.getHours()).padStart(2, '0');
        var mm = String(now.getMinutes()).padStart(2, '0');
        var ss = String(now.getSeconds()).padStart(2, '0');
        
        var dateEl = document.getElementById('clockDate');
        var timeEl = document.getElementById('clockTime');
        if (dateEl) dateEl.textContent = y + '.' + m + '.' + d;
        if (timeEl) timeEl.textContent = hh + ':' + mm + ':' + ss;
    }
    
    updateDigitalClock();
    setInterval(updateDigitalClock, 1000);
})();
</script>
