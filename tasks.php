<?php
// ================================
// tasks.php
//
// Tämä on tehtäväsivu — näkyy vain
// kirjautuneelle käyttäjälle.
//
// Tällä sivulla käyttäjä voi:
// - Nähdä kaikki omat tehtävänsä
// - Lisätä uusia tehtäviä
// - Muokata, poistaa ja muuttaa
//   tehtävien tilaa
//
// Kirjautumaton käyttäjä ohjataan
// automaattisesti takaisin etusivulle.
// ================================
require_once 'app/session-config.php'; // Istuntoasetukset. ENSIN ennen kaikkea muuta
require_once 'app/db.php';             // Tietokantayhteys $conn-muuttujaan

// Tarkistetaan että käyttäjä on kirjautunut sisään
// Kirjautumaton käyttäjä ohjataan takaisin kirjautumissivulle
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Tarkistetaan istunnon vanheneminen
// Jos tunti on kulunut ilman toimintaa, kirjaudutaan ulos automaattisesti
if (!validateSessionTimeout()) {
    $_SESSION['error'] = 'Istunto on vanhentunut. Kirjaudu uudelleen.'; // Tallennetaan virheilmoitus sessioon
    header('Location: index.php');
    exit;
}

// Luodaan tai palautetaan CSRF-token sivun latauksen yhteydessä
generateCSRFToken();

// Haetaan kirjautuneen käyttäjän id — käytetään kaikissa tietokantakyselyissä
$uid = intval($_SESSION['user_id']); // intval muuttaa arvon kokonaisluvuksi — suojaa SQL-injektiolta

// Apufunktio — suojaa XSS-hyökkäyksiltä muuttamalla erikoismerkit turvalliseen muotoon
function clean($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// Haetaan käyttäjän tehtävät kolmessa ryhmässä tietokannasta
// Ei aloitetut tehtävät — uusimmat ensin
$notStarted = $conn->prepare("SELECT id, text, created_at FROM tasks WHERE user_id=? AND status='not_started' ORDER BY id DESC");
$notStarted->bind_param('i', $uid); // 'i' = integer eli kokonaisluku
$notStarted->execute();
$notStarted = $notStarted->get_result(); // Haetaan kyselyn tulos

// Käynnissä olevat tehtävät — uusimmat ensin
$inProgress = $conn->prepare("SELECT id, text, created_at, started_at FROM tasks WHERE user_id=? AND status='in_progress' ORDER BY id DESC");
$inProgress->bind_param('i', $uid);
$inProgress->execute();
$inProgress = $inProgress->get_result();

// Valmiit tehtävät — uusimmat ensin
$doneTasks = $conn->prepare("SELECT id, text, created_at, started_at, done_at FROM tasks WHERE user_id=? AND status='done' ORDER BY id DESC");
$doneTasks->bind_param('i', $uid);
$doneTasks->execute();
$doneTasks = $doneTasks->get_result();
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8"> <!-- Merkistö — tukee suomen kielen merkkejä -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Skaalautuu eri laitteille -->
    <meta name="csrf-token" content="<?= clean(generateCSRFToken()) ?>"> <!-- CSRF-token tasks.js:ää varten -->
    <title>Zombie To-Do</title>
    <meta name="description" content="Zombie To-Do — hallitse tehtäväsi ja selviä apokalypsistä.">
    <link rel="icon" type="image/png" href="assets/img/favicon.png"> <!-- Selaimen välilehden ikoni -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css"> <!-- Flatpickr päivämäärävalitsimen tyylit -->
    <link rel="stylesheet" href="style.css"> <!-- Sovelluksen omat tyylit -->
</head>
<body>

    <!-- Veriantimaatio — valuu sivun yläreunasta ja häviää -->
    <div class="blood"></div>

    <!-- Pääkontaineri joka sisältää kaikki sivun elementit -->
    <div class="container">

        <!-- Herokuva — suuri kuva joka esittelee sovelluksen teeman -->
        <img src="assets/img/Herokuva.webp" class="hero" alt="Zombie To-Do" fetchpriority="high">

        <!-- Yläpalkki — tervetuloviesti ja navigointilinkit -->
        <div class="header-bar">
            <span class="welcome-text">Tervetuloa, <?= clean($_SESSION['username']) ?>!</span> <!-- Näytetään kirjautuneen käyttäjän nimi turvallisesti -->
            <div class="header-links">
                <a href="profile.php" class="header-link">Muokkaa&nbsp;tietoja&nbsp;🧟‍♀️</a> <!-- Linkki profiilisivulle — GET on ok koska vain avataan sivu -->
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><!-- Näytetään admin-linkki vain jos käyttäjällä on admin-rooli -->
                    <a href="admin.php" class="header-link">Admin&#8209;paneeli&nbsp;⚙️</a> <!-- Linkki admin-sivulle — GET on ok koska vain avataan sivu -->
                <?php endif; ?>
                <form method="POST" action="app/actions.php"> <!-- POST koska uloskirjautuminen muuttaa istunnon tilaa -->
                    <input type="hidden" name="action" value="logout"> <!-- Toiminto POST-datana URL:n sijaan -->
                    <input type="hidden" name="csrf_token" value="<?= clean(generateCSRFToken()) ?>"> <!-- CSRF-suojaus — estää ulkopuolisen kirjaamasta käyttäjän ulos -->
                    <button type="submit" class="header-link">Kirjaudu&nbsp;ulos&nbsp;❌</button>
                </form>
            </div>
        </div>

        <!-- Pääotsikko -->
        <h1>ZOMBIE TO-DO</h1>

        <!-- Virheilmoitus. Näytetään vain jos virheitä on -->
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="auth-error">
                <?= clean($_SESSION['error']); ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Onnistumisilmoitus. Esim. tehtävä lisätty -->
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="auth-success">
                <?= clean($_SESSION['success']); ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <!-- Tehtävälista -->
        <div class="todo-box">

            <!-- Uuden tehtävän lisäyslomake -->
            <form class="input-area" action="app/actions.php?action=add" method="POST">
                <input type="hidden" name="csrf_token" value="<?= clean(generateCSRFToken()) ?>"> <!-- CSRF-suojaus -->
                <input type="text" name="task" placeholder="Lisää tehtävä... ennen kuin kuolleet nousevat!" required autocomplete="off">
                <button type="submit">Lisää</button>
            </form>

            <!-- EI ALOITETUT -->
            <h2 class="section-title not-started">🧠 Ei aloitetut</h2>
            <div class="task-list">
            <?php while ($task = $notStarted->fetch_assoc()): ?>
                <div class="task">
                    <div class="task-info">
                        <span class="task-text"><?= clean($task['text']) ?></span>
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
                        <span class="task-text"><?= clean($task['text']) ?></span>
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
                        <span class="task-text"><?= clean($task['text']) ?></span>
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

        </div><!-- .todo-box loppuu -->

    </div><!-- .container loppuu-->

  <!-- Muokkausmodal — avautuu kun käyttäjä klikkaa ✏️-nappia -->
    <div class="modal-overlay" id="editModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modalTitle">✏️ Muokkaa tehtävää</h2>
                <button class="modal-close" id="modalClose" aria-label="Sulje">✕</button>
            </div>
            <div class="modal-body">
                <div class="modal-error" id="modalError"></div> <!-- Virheilmoitus modalin sisällä -->
                <div>
                    <label for="editText">Tehtävä 🧠</label>
                    <textarea id="editText" placeholder="Tehtävän kuvaus..." maxlength="255" required></textarea>
                </div>
                <div class="modal-field-row">
                    <div>
                        <label for="editStarted">Aloitettu</label>
                        <input type="text" id="editStarted" placeholder="pp.kk.vvvv hh:mm" autocomplete="off" readonly>
                    </div>
                    <div>
                        <label for="editDone">Valmis</label>
                        <input type="text" id="editDone" placeholder="pp.kk.vvvv hh:mm" autocomplete="off" readonly>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" id="modalCancel">Peruuta</button>
                <button class="btn-save"   id="modalSave">Tallenna 🩸</button>
            </div>
        </div>
    </div>  

    <!-- JavaScriptit ladataan sivun lopussa jotta HTML on valmis ennen scriptejä -->
    <script src="assets/js/ui.js"></script>   <!-- Yleiset UI-toiminnot -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="assets/js/tasks.js"></script> <!-- Tehtävälogiikka -->
    

</body>
</html>