<?php
// ================================
// partial-tasks.php
//
// Tämä tiedosto hakee käyttäjän tehtävät
// tietokannasta ja näyttää ne sivulla.
//
// Tehtävät on jaettu kolmeen ryhmään:
// - Ei aloitetut
// - Käynnissä olevat
// - Valmiit
//
// Tätä tiedostoa ei avata suoraan selaimessa.
// Se ladataan taustalla kun tehtävälista
// pitää päivittää — esimerkiksi kun lisäät
// uuden tehtävän tai merkkaat sen valmiiksi.
// Näin sivu ei lataudu kokonaan uudelleen.
// ================================
require_once __DIR__ . '/session-config.php'; // Istuntoasetukset ENSIN
require_once __DIR__ . '/db.php';             // Tietokantayhteys

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

// Apufunktio XSS-suojaukseen
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// Haetaan ei aloitetut tehtävät
$notStarted = $conn->prepare("SELECT id, text, created_at FROM tasks WHERE user_id=? AND status='not_started' ORDER BY id DESC");
$notStarted->bind_param('i', $uid);
$notStarted->execute();
$notStarted = $notStarted->get_result();

// Haetaan käynnissä olevat tehtävät
$inProgress = $conn->prepare("SELECT id, text, created_at, started_at FROM tasks WHERE user_id=? AND status='in_progress' ORDER BY id DESC");
$inProgress->bind_param('i', $uid);
$inProgress->execute();
$inProgress = $inProgress->get_result();

// Haetaan valmiit tehtävät
$doneTasks = $conn->prepare("SELECT id, text, created_at, started_at, done_at FROM tasks WHERE user_id=? AND status='done' ORDER BY id DESC");
$doneTasks->bind_param('i', $uid);
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
        </div>
        <div class="actions">
            <button type="button" data-action="edit"   data-id="<?= $task['id'] ?>">✏️</button>
            <button type="button" data-action="start"  data-id="<?= $task['id'] ?>">⚔️</button>
            <button type="button" data-action="delete" data-id="<?= $task['id'] ?>">🗑</button>
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
        </div>
        <div class="actions">
            <button type="button" data-action="edit"   data-id="<?= $task['id'] ?>">✏️</button>
            <button type="button" data-action="done"       data-id="<?= $task['id'] ?>">✓</button>
            <button type="button" data-action="undo_start" data-id="<?= $task['id'] ?>">☠️</button>
            <button type="button" data-action="delete"     data-id="<?= $task['id'] ?>">🗑</button>
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
        </div>
        <div class="actions">
            <button type="button" data-action="edit"   data-id="<?= $task['id'] ?>">✏️</button>
            <button type="button" data-action="undo_done" data-id="<?= $task['id'] ?>">☠️</button>
            <button type="button" data-action="delete"    data-id="<?= $task['id'] ?>">🗑</button>
        </div>
    </div>
<?php endwhile; ?>
</div>