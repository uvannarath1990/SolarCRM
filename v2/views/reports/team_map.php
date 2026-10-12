<?php
$pageTitle = 'Team Location';
$pageSub   = date('D d M Y', strtotime($date));
require __DIR__ . '/../layout/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">

<?php
// Slider bounds: the day's own first and last ping, so dragging covers the
// hours that actually contain data rather than a mostly-empty 24 hours.
$firstMin = $bounds['first_at'] ? (int) substr($bounds['first_at'], 0, 2) * 60 + (int) substr($bounds['first_at'], 3, 2) : 0;
$lastMin  = $bounds['last_at']  ? (int) substr($bounds['last_at'], 0, 2) * 60 + (int) substr($bounds['last_at'], 3, 2) : 1439;
if ($lastMin <= $firstMin) { $firstMin = 0; $lastMin = 1439; }
$atMin    = $time !== '' ? (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2) : $lastMin;
?>
<div class="v2-card" style="margin-top:14px;padding:12px">
    <form method="GET" action="<?= BASE_URL ?>/reports/team-map" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="route" value="reports/team-map">
        <?php if ($selected): ?><input type="hidden" name="user" value="<?= (int) $selected ?>"><?php endif; ?>
        <input type="date" class="v2-input" name="date" value="<?= htmlspecialchars($date) ?>"
               max="<?= date('Y-m-d') ?>" style="flex:1;min-width:150px" onchange="this.form.submit()">
        <input type="time" class="v2-input" name="time" id="atTime" value="<?= htmlspecialchars($time) ?>"
               style="flex:0 0 130px" onchange="this.form.submit()">
        <?php if ($time !== ''): ?>
        <a href="<?= BASE_URL ?>/reports/team-map?date=<?= htmlspecialchars($date) ?><?= $selected ? '&user=' . (int) $selected : '' ?>"
           class="v2-btn v2-btn-ghost v2-btn-sm">Now</a>
        <?php endif; ?>
        <?php if ($selected): ?>
        <a href="<?= BASE_URL ?>/reports/team-map?date=<?= htmlspecialchars($date) ?><?= $time !== '' ? '&time=' . urlencode($time) : '' ?>"
           class="v2-btn v2-btn-ghost v2-btn-sm">Clear trail</a>
        <?php endif; ?>
    </form>

    <?php if ((int) $bounds['pings'] > 0): ?>
    <?php // Drag to move the whole team back through the day. ?>
    <div style="display:flex;align-items:center;gap:10px;margin-top:12px">
        <span class="text-muted-2 tnum" style="font-size:11.5px;flex:0 0 auto">
            <?= substr((string) $bounds['first_at'], 0, 5) ?>
        </span>
        <input type="range" id="timeSlider" style="flex:1"
               min="<?= $firstMin ?>" max="<?= $lastMin ?>" step="5" value="<?= $atMin ?>">
        <span class="text-muted-2 tnum" style="font-size:11.5px;flex:0 0 auto">
            <?= substr((string) $bounds['last_at'], 0, 5) ?>
        </span>
    </div>
    <div style="display:flex;align-items:center;gap:8px;margin-top:6px">
        <span class="pill pill-info tnum" id="atLabel">
            <?= $time !== '' ? htmlspecialchars($time) : 'Now' ?>
        </span>
        <span class="text-muted-2" style="font-size:12px" id="atHint">
            <?= $time !== '' ? 'Positions as at this time' : 'Latest known positions' ?>
        </span>
    </div>
    <?php endif; ?>
</div>

<?php if (!$reps): ?>
<div class="v2-empty" style="margin-top:14px">
    <i class="bi bi-geo-alt" style="color:var(--muted)"></i>
    <div class="t">No one is being tracked</div>
    <div class="s">Switch on “Track location” for a user in Users to see them here.</div>
</div>
<?php else: ?>

<div id="map" style="height:380px;border-radius:var(--r-lg);margin-top:12px;border:1px solid var(--line);background:var(--surface-2)"></div>

<div class="v2-section">
    <h2><?= count($reps) ?> tracked</h2>
    <span class="rule"></span>
</div>

<?php foreach ($latest as $r):
    $has = $r['captured_at'] !== null;
    $age = $has ? (int) $r['age_min'] : null;
    // Freshness is shown honestly: a stale pin must not look live.
    $tone = !$has ? 'muted' : ($age <= 60 ? 'ok' : ($age <= 240 ? 'warn' : 'danger'));
    $ageText = !$has ? 'No data today'
             : ($age < 60 ? $age . ' min ago'
             : ($age < 1440 ? intdiv($age, 60) . ' hr ago' : 'Earlier'));
?>
<div class="task-card is-later" style="cursor:pointer" data-user="<?= (int) $r['user_id'] ?>">
    <div class="tc-body" style="display:flex;align-items:flex-start;gap:11px">
        <?php // Same colour as this person's dot on the map, so the two can be matched. ?>
        <span title="Map colour" style="width:14px;height:14px;border-radius:50%;margin-top:4px;flex:0 0 auto;
                     border:2px solid #fff;box-shadow:0 0 0 1px var(--line-2);
                     background:<?= htmlspecialchars(userColor((int) $r['user_id'])) ?>;
                     opacity:<?= $has ? '1' : '.4' ?>"></span>
        <div class="min-w-0" style="flex:1">
            <div class="tc-name"><?= htmlspecialchars($r['name']) ?></div>
            <div class="tc-meta" style="margin-top:4px">
                <span style="color:<?= $tone === 'ok' ? 'var(--ok)' : ($tone === 'warn' ? 'var(--brand-ink)' : ($tone === 'danger' ? 'var(--danger)' : 'var(--muted)')) ?>;font-weight:600"><?= htmlspecialchars($ageText) ?></span>
                <?php if ($has && $r['accuracy_m'] !== null): ?>
                <span class="sep">·</span><span>±<?= (int) $r['accuracy_m'] ?>m</span>
                <?php endif; ?>
                <?php if ($has && $r['source'] !== 'heartbeat'): ?>
                <span class="pill pill-ok"><?= htmlspecialchars($r['source']) ?><?= $r['lead_name'] ? ': ' . htmlspecialchars($r['lead_name']) : '' ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($has): ?>
        <a href="<?= BASE_URL ?>/reports/team-map?date=<?= htmlspecialchars($date) ?>&user=<?= (int) $r['user_id'] ?>"
           class="pill <?= $selected === (int) $r['user_id'] ? 'pill-info' : 'pill-muted' ?>" style="flex:0 0 auto">
            Trail
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if ($selected && $trail): ?>
<div class="v2-section">
    <?php
    // Was printing the first row's LEAD name before the rep's name — a leftover
    // that made the heading read "Trail · Rajesh MenonArun Krishnan".
    $selName = '';
    foreach ($reps as $rp) if ((int) $rp['id'] === $selected) $selName = $rp['name'];
    ?>
    <h2>Trail · <?= htmlspecialchars($selName) ?></h2>
    <span class="count"><?= count($trail) ?></span>
    <span class="rule"></span>
</div>

<div class="v2-card" style="padding:14px">
<?php
$prev = null;
foreach ($trail as $p):
    $isEvent = $p['source'] !== 'heartbeat';
    // A gap over two hours is shown as a gap. Interpolating across it would be
    // inventing movement that was never recorded.
    if ($prev !== null) {
        $gap = (strtotime($p['captured_at']) - strtotime($prev)) / 60;
        if ($gap > 120):
?>
    <div style="padding:8px 0 8px 13px;border-left:2px dashed var(--line-2);color:var(--muted);font-size:12.5px">
        No data for <?= intdiv((int) $gap, 60) ?> hr — app closed
    </div>
<?php   endif;
    }
    $prev = $p['captured_at'];
?>
    <div style="padding:6px 0 6px 13px;border-left:2px solid <?= $isEvent ? 'var(--ok)' : 'var(--line)' ?>">
        <div style="font-size:12px;color:<?= $isEvent ? 'var(--ok)' : 'var(--muted)' ?>">
            <?= date('H:i', strtotime($p['captured_at'])) ?>
        </div>
        <div style="font-size:13.5px">
            <?php if ($isEvent): ?>
                <?= htmlspecialchars(ucfirst($p['source'])) ?><?= $p['lead_name'] ? ' · ' . htmlspecialchars($p['lead_name']) : '' ?>
            <?php else: ?>
                Position ping
            <?php endif; ?>
            <span class="text-muted-2" style="font-size:12px">±<?= (int) $p['accuracy_m'] ?>m</span>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<script>
window.KRE_MAP = {
    reps:     <?= json_encode($latest, JSON_UNESCAPED_UNICODE) ?>,
    trail:    <?= json_encode($trail, JSON_UNESCAPED_UNICODE) ?>,
    selected: <?= (int) $selected ?>,
    date:     <?= json_encode($date) ?>,
    at:       <?= json_encode($time) ?>
};
</script>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>

<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
