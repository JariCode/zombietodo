<?php
// ================================
// partial-tasks.php - Sisältää tehtävät jotka haetaan AJAX-pyynnöllä tasks.js:stä.
// ================================

// Etsitään zombie-config-kansio — tarkistetaan ensin kaksi tasoa ylös, sitten kolme
// App-kansio on yhden tason syvemmällä kuin juuritiedostot
$cfgDir = is_dir(dirname(dirname(__DIR__)) . '/zombie-config')
    ? dirname(dirname(__DIR__)) . '/zombie-config'
    : dirname(dirname(dirname(__DIR__))) . '/zombie-config';
require_once $cfgDir . '/session-config.php'; // Istuntoasetukset ENSIN
require_once $cfgDir . '/db.php';             // Tietokantayhteys
require_once __DIR__ . '/task-meta.php';      // Jaettu kortti-lisätieto

// Tarkistetaan kirjautuminen
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

// Tarkistetaan CSRF-token — tasks.js lähettää sen headerissa
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCSRFToken($csrfToken)) {
    http_response_code(403);
    exit;
}

$uid = intval($_SESSION['user_id']); // Kirjautuneen käyttäjän id

// Aktiivinen operaatio luetaan sessiosta. Tarkistetaan erikseen kannasta että se
// todella kuuluu kirjautuneelle käyttäjälle — ei luoteta sessioarvoon sellaisenaan.
$activeOperationId = null;
if (!empty($_SESSION['active_operation'])) {
    $candidate = intval($_SESSION['active_operation']);
    $chk = $conn->prepare('SELECT id FROM operations WHERE id=? AND user_id=?');
    $chk->bind_param('ii', $candidate, $uid);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) { $activeOperationId = $candidate; }
    $chk->close();
}

if ($activeOperationId === null) {
    // Ei kelvollista aktiivista operaatiota — ei näytetä tehtäviä
    echo '<h2 class="section-title not-started">🧠 Ei aloitetut</h2><div class="task-list"></div>';
    echo '<h2 class="section-title in-progress">🪓 Käynnissä</h2><div class="task-list"></div>';
    echo '<h2 class="section-title done-title">🪦 Valmiit</h2><div class="task-list"></div>';
    echo '<template id="parentOptionsUpdate"><option value="0">— Ei päätehtävää —</option></template>';
    exit;
}

$meta = tm_loadAll($conn, $uid, $activeOperationId); // Hakurakenteet kortti-lisätietoa varten

// Apufunktio XSS-suojaukseen
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// Haetaan ei aloitetut tehtävät
$notStarted = $conn->prepare("SELECT id, text, created_at FROM tasks WHERE user_id=? AND operation_id=? AND status='not_started' ORDER BY id DESC");
$notStarted->bind_param('ii', $uid, $activeOperationId);
$notStarted->execute();
$notStarted = $notStarted->get_result();

// Haetaan käynnissä olevat tehtävät
$inProgress = $conn->prepare("SELECT id, text, created_at, started_at FROM tasks WHERE user_id=? AND operation_id=? AND status='in_progress' ORDER BY id DESC");
$inProgress->bind_param('ii', $uid, $activeOperationId);
$inProgress->execute();
$inProgress = $inProgress->get_result();

// Haetaan valmiit tehtävät
$doneTasks = $conn->prepare("SELECT id, text, created_at, started_at, done_at FROM tasks WHERE user_id=? AND operation_id=? AND status='done' ORDER BY id DESC");
$doneTasks->bind_param('ii', $uid, $activeOperationId);
$doneTasks->execute();
$doneTasks = $doneTasks->get_result();
?>

<!-- EI ALOITETUT -->
<h2 class="section-title not-started">🧠 Ei aloitetut</h2>
<div class="task-list">
<?php while ($task = $notStarted->fetch_assoc()): ?>
    <div class="task">
        <div class="task-info">
            <span class="task-text"><?= e($task['text']) ?></span>
            <small class="timestamp">Lisätty: <?= date('d.m.Y H:i', strtotime($task['created_at'])) ?></small>
            <?php tm_cardMeta($task['id'], $meta); ?>
        </div>
        <div class="actions">
            <button type="button" data-action="start" data-tooltip="Aloita"  data-id="<?= $task['id'] ?>">⚔️</button>
            <button type="button" data-action="edit" data-tooltip="Muokkaa"   data-id="<?= $task['id'] ?>">✏️</button>
            <button type="button" data-action="delete" data-tooltip="Poista" data-id="<?= $task['id'] ?>">🗑</button>
        </div>
    </div>
<?php endwhile; ?>
</div>

<!-- KÄYNNISSÄ -->
<h2 class="section-title in-progress">🪓 Käynnissä</h2>
<div class="task-list">
<?php while ($task = $inProgress->fetch_assoc()): ?>
    <div class="task">
        <div class="task-info">
            <span class="task-text"><?= e($task['text']) ?></span>
            <small class="timestamp">
                Lisätty: <?= date('d.m.Y H:i', strtotime($task['created_at'])) ?>
                <br>Aloitettu: <?= date('d.m.Y H:i', strtotime($task['started_at'])) ?>
            </small>
            <?php tm_cardMeta($task['id'], $meta); ?>
        </div>
        <div class="actions">
            <button type="button" data-action="done" data-tooltip="Merkitse valmiiksi"       data-id="<?= $task['id'] ?>">✓</button>
            <button type="button" data-action="undo_start" data-tooltip="Peru aloitus" data-id="<?= $task['id'] ?>">☠️</button>
            <button type="button" data-action="edit" data-tooltip="Muokkaa"   data-id="<?= $task['id'] ?>">✏️</button>
            <button type="button" data-action="delete" data-tooltip="Poista"     data-id="<?= $task['id'] ?>">🗑</button>
        </div>
    </div>
<?php endwhile; ?>
</div>

<!-- VALMIIT -->
<h2 class="section-title done-title">🪦 Valmiit</h2>
<div class="task-list">
<?php while ($task = $doneTasks->fetch_assoc()): ?>
    <div class="task done">
        <div class="task-info">
            <span class="task-text"><?= e($task['text']) ?></span>
            <small class="timestamp">
                Lisätty: <?= date('d.m.Y H:i', strtotime($task['created_at'])) ?>
                <?php if (!empty($task['started_at'])): ?>
                    <br>Aloitettu: <?= date('d.m.Y H:i', strtotime($task['started_at'])) ?>
                <?php endif; ?>
                <?php if (!empty($task['done_at'])): ?>
                    <br>Valmis: <?= date('d.m.Y H:i', strtotime($task['done_at'])) ?>
                <?php endif; ?>
            </small>
            <?php tm_cardMeta($task['id'], $meta); ?>
        </div>
        <div class="actions">
            <button type="button" data-action="undo_done" data-tooltip="Peru valmistuminen" data-id="<?= $task['id'] ?>">☠️</button>
            <button type="button" data-action="edit" data-tooltip="Muokkaa"   data-id="<?= $task['id'] ?>">✏️</button>
            <button type="button" data-action="delete" data-tooltip="Poista"    data-id="<?= $task['id'] ?>">🗑</button>
        </div>
    </div>
<?php endwhile; ?>
</div>
<!-- Päivitetyt päätehtävävalikon optiot. tasks.js poimii tämän ja päivittää
     muokkausmodalin valikon varaston, jotta valikko pysyy ajan tasalla ilman sivun latausta. -->
<template id="parentOptionsUpdate"><?php
    echo '<option value="0">— Ei päätehtävää —</option>';
    tm_parentOptions($meta);
?></template>