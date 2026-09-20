<?php
// ================================
// tasks.php - Tehtävien hallintasivu, jossa käyttäjät näkevät ja hallitsevat omia tehtäviään
// ================================
// Etsitään zombie-config-kansio — tarkistetaan ensin yksi taso ylös, sitten kaksi
// Paikallisesti kansio on yhden tason päässä, palvelimella kahden
$cfgDir = is_dir(dirname(__DIR__) . '/zombie-config')
    ? dirname(__DIR__) . '/zombie-config'
    : dirname(dirname(__DIR__)) . '/zombie-config';
require_once $cfgDir . '/session-config.php'; // Istuntoasetukset ENSIN
require_once $cfgDir . '/db.php';             // Tietokantayhteys

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

// ===========================================================
// OPERAATIOT — käyttäjän projektit joiden alle tehtävät ryhmitellään
// ===========================================================
// Haetaan käyttäjän kaikki operaatiot valikkoa varten
$opStmt = $conn->prepare('SELECT id, name, description, color FROM operations WHERE user_id=? ORDER BY id ASC');
$opStmt->bind_param('i', $uid);
$opStmt->execute();
$operations = $opStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$opStmt->close();

// Aktiivinen operaatio tallennetaan sessioon. Tarkistetaan AINA erikseen kannasta
// että sessiossa oleva id todella kuuluu kirjautuneelle käyttäjälle — session-arvoon
// ei luoteta sellaisenaan (esim. jos käyttäjä on poistanut operaation toisessa välilehdessä).
$activeOperationId = null;
if (!empty($_SESSION['active_operation'])) {
    $candidate = intval($_SESSION['active_operation']);
    foreach ($operations as $op) {
        if ((int)$op['id'] === $candidate) { $activeOperationId = $candidate; break; }
    }
}
// Jos sessiossa ei ollut kelvollista operaatiota (esim. juuri kirjauduttu sisään),
// luetaan käyttäjän viimeksi valitsema operaatio kannasta. Sekin validoidaan AINA
// kuuluvaksi käyttäjälle vertaamalla $operations-listaan — kannan arvoon ei luoteta
// sokeasti, jos operaatioita on ehditty muuttaa toisaalla.
if ($activeOperationId === null && !empty($operations)) {
    $lastOpStmt = $conn->prepare('SELECT last_operation_id FROM users WHERE id=?');
    $lastOpStmt->bind_param('i', $uid);
    $lastOpStmt->execute();
    $lastOpId = $lastOpStmt->get_result()->fetch_assoc()['last_operation_id'] ?? null;
    $lastOpStmt->close();

    if ($lastOpId !== null) {
        $lastOpId = (int)$lastOpId;
        foreach ($operations as $op) {
            if ((int)$op['id'] === $lastOpId) { $activeOperationId = $lastOpId; break; }
        }
    }
}
// Vasta jos kumpikaan ei kelvannut, valitaan ensimmäinen olemassa oleva
if ($activeOperationId === null && !empty($operations)) {
    $activeOperationId = (int)$operations[0]['id'];
}
$_SESSION['active_operation'] = $activeOperationId; // Päivitetään sessio validoituun arvoon (tai nulliin)

// Haetaan käyttäjän tehtävät kolmessa ryhmässä tietokannasta — vain aktiivisesta operaatiosta
if ($activeOperationId !== null) {
    // Ei aloitetut tehtävät — uusimmat ensin
    $notStarted = $conn->prepare("SELECT id, text, created_at FROM tasks WHERE user_id=? AND operation_id=? AND status='not_started' ORDER BY id DESC");
    $notStarted->bind_param('ii', $uid, $activeOperationId);
    $notStarted->execute();
    $notStarted = $notStarted->get_result(); // Haetaan kyselyn tulos

    // Käynnissä olevat tehtävät — uusimmat ensin
    $inProgress = $conn->prepare("SELECT id, text, created_at, started_at FROM tasks WHERE user_id=? AND operation_id=? AND status='in_progress' ORDER BY id DESC");
    $inProgress->bind_param('ii', $uid, $activeOperationId);
    $inProgress->execute();
    $inProgress = $inProgress->get_result();

    // Valmiit tehtävät — uusimmat ensin
    $doneTasks = $conn->prepare("SELECT id, text, created_at, started_at, done_at FROM tasks WHERE user_id=? AND operation_id=? AND status='done' ORDER BY id DESC");
    $doneTasks->bind_param('ii', $uid, $activeOperationId);
    $doneTasks->execute();
    $doneTasks = $doneTasks->get_result();

    require_once __DIR__ . '/app/task-meta.php'; // Jaettu kortti-lisätieto ja tuntisummaus
    $meta = tm_loadAll($conn, $uid, $activeOperationId); // Hakurakenteet: byId, children, roots
} else {
    require_once __DIR__ . '/app/task-meta.php';
    $meta = ['byId' => [], 'children' => [], 'roots' => []];
}
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
    <link rel="stylesheet" href="assets/css/flatpickr.min.css"> <!-- Flatpickr päivämäärävalitsimen oletustyylit -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/style.css') ?>"> <!-- Sovelluksen omat tyylit. Tämä oltava viimeisenä. Versioparametri estää selainta näyttämästä vanhaa CSS:ää välimuistista tiedoston muuttuessa -->
</head>
<body>
    <!-- Veri-overlay — peittää sivun punaisella efektillä, käytetään kun tehtävä merkitään valmiiksi -->
    <div class="blood-overlay" id="bloodOverlay"></div>
    
    <!-- Veriantimaatio — valuu sivun yläreunasta ja häviää -->
    <div class="blood"></div>

    <!-- Pääkontaineri joka sisältää kaikki sivun elementit -->
    <div class="container no-caret">

        <!-- Herokuva — suuri kuva joka esittelee sovelluksen teeman -->
        <img src="assets/img/Herokuva.webp" class="hero" alt="Zombie To-Do" width="1200" height="630" fetchpriority="high">

        <!-- Yläpalkki — tervetuloviesti ja navigointilinkit -->
        <div class="header-bar">
            <span class="welcome-text">Tervetuloa, <?= clean($_SESSION['username']) ?>!</span> <!-- Näytetään kirjautuneen käyttäjän nimi turvallisesti -->
            <div class="header-links">
                <a href="profile.php" class="header-link">Muokkaa&nbsp;tietoja&nbsp;🧟‍♀️</a> <!-- Linkki profiilisivulle — GET on ok koska vain avataan sivu -->
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><!-- Näytetään admin-linkki vain jos käyttäjällä on admin-rooli -->
                    <a href="admin.php" class="header-link">Admin&#8209;paneeli&nbsp;⚙️</a> <!-- Linkki admin-sivulle — GET on ok koska vain avataan sivu -->
                <?php endif; ?>
                <form method="POST" action="app/actions.php" data-loading> <!-- POST koska uloskirjautuminen muuttaa istunnon tilaa -->
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

        <!-- OPERAATIOVALITSIN — valitsee mitkä tehtävät näkyvät alla -->
        <?php if (!empty($operations)): ?>
        <div class="operation-bar">
            <select id="operationSelect" class="operation-select" aria-label="Aktiivinen operaatio">
                <?php foreach ($operations as $op): ?>
                <option value="<?= (int)$op['id'] ?>"
                        data-color="<?= clean($op['color'] ?: '#880000') ?>"
                        data-description="<?= clean($op['description'] ?? '') ?>"
                        <?= ((int)$op['id'] === (int)$activeOperationId) ? 'selected' : '' ?>><?= clean($op['name']) ?></option>
                <?php endforeach; ?>
                <option value="__new__">➕ Uusi operaatio</option>
            </select>
            <div class="operation-actions">
                <button type="button" id="operationEditBtn" class="op-icon-btn" data-tooltip="Muokkaa operaatiota" aria-label="Muokkaa operaatiota">✏️</button>
                <button type="button" id="operationDeleteBtn" class="op-icon-btn" data-tooltip="Poista operaatio" aria-label="Poista operaatio">🗑</button>
            </div>
        </div>
        <?php else: ?>
        <div class="operation-empty">
            <p>Ei operaatioita vielä.</p>
            <p>Luo ensimmäinen operaatio ennen kuin voit lisätä tehtäviä.</p>
            <button type="button" id="operationCreateFirst" class="kooste-btn">➕ Luo operaatio</button>
        </div>
        <?php endif; ?>

        <?php if ($activeOperationId !== null): ?>
        <!-- Tehtävälista -->
        <div class="todo-box">

            <!-- Uuden tehtävän lisäyslomake -->
            <form class="input-area" action="app/actions.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?= clean(generateCSRFToken()) ?>"><!-- CSRF-suojaus — estää ulkopuolisen lisäämästä tehtäviä käyttäjälle -->
                <input type="text" name="task" placeholder="Lisää tehtävä... ennen kuin kuolleet nousevat!" required autocomplete="off" maxlength="255">
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
                        <span class="task-text"><?= clean($task['text']) ?></span>
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

        </div><!-- .todo-box loppuu -->

        <!-- Kooste-nappi — todo-boxin ulkopuolella jotta AJAX-päivitys ei poista sitä -->
        <button type="button" id="openKooste" class="kooste-btn">📊 Näytä kooste</button>
        <?php endif; ?>

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
                        <label for="editHours">Tunnit ⏳</label>
                        <input type="text" id="editHours" placeholder="esim. 2 tai 2,5" autocomplete="off" inputmode="decimal">
                    </div>
                    <div>
                        <label for="editParent">Päätehtävä</label>
                        <!-- Valitsin pidetään sisennettynä (avattu pudotusvalikko), mutta suljetun
                             napin oma teksti piilotetaan CSS:llä (ks. .modal-select-wrap) ja sen
                             päällä näytetään siisti, sisennyksetön teksti #editParentLabelissa. -->
                        <div class="modal-select-wrap">
                            <select id="editParent" class="modal-select"></select>
                            <span id="editParentLabel" class="modal-select-label" aria-hidden="true"></span>
                        </div>
                        <!-- Kaikki mahdolliset päätehtäväoptiot varastossa. JS kopioi nämä valikkoon
                             joka avauksella ja jättää muokattavan tehtävän itsensä pois. -->
                        <template id="parentOptionsStore">
                            <option value="0">— Ei päätehtävää —</option>
                            <?php tm_parentOptions($meta); ?>
                        </template>
                    </div>
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
    <!-- Muokkausmodal loppuu --> 

    <!-- Koostemodal — avautuu koostebkoksin napista. Sisältö täytetään JS:llä. -->
    <div class="modal-overlay" id="koosteModal" role="dialog" aria-modal="true" aria-labelledby="koosteTitle">
        <div class="modal modal-wide">
            <div class="modal-header">
                <h2 id="koosteTitle">📊 Kooste</h2>
                <button class="modal-close" id="koosteClose" aria-label="Sulje">✕</button>
            </div>
            <div class="modal-body">
                <div id="koosteContent"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-print" id="koostePrint">🖨️ Tulosta</button>
                <button class="btn-cancel" id="koosteCancel">Sulje</button>
            </div>
        </div>
    </div>
    <!-- Koostemodal loppuu -->

    <!-- Operaatiomodal — avautuu uuden operaation luonnissa tai olemassa olevan muokkauksessa -->
    <div class="modal-overlay" id="operationModal" role="dialog" aria-modal="true" aria-labelledby="operationModalTitle">
        <div class="modal">
            <div class="modal-header">
                <h2 id="operationModalTitle">🧟 Uusi operaatio</h2>
                <button class="modal-close" id="operationModalClose" aria-label="Sulje">✕</button>
            </div>
            <div class="modal-body">
                <div class="modal-error" id="operationModalError"></div> <!-- Virheilmoitus modalin sisällä -->
                <div>
                    <label for="opName">Operaation nimi</label>
                    <input type="text" id="opName" placeholder="esim. Kotihautausmaan siivous" maxlength="100" required autocomplete="off">
                </div>
                <div>
                    <label for="opDescription">Kuvaus (valinnainen)</label>
                    <textarea id="opDescription" placeholder="Lyhyt kuvaus operaatiosta..." maxlength="255"></textarea>
                </div>
                <div>
                    <label for="opColorHex">Väri</label>
                    <div class="op-color-row" id="opColorRow">
                        <button type="button" class="op-swatch op-swatch-1" data-color="#cc0000" aria-label="Verenpunainen"></button>
                        <button type="button" class="op-swatch op-swatch-2" data-color="#cc6a00" aria-label="Liekkioranssi"></button>
                        <button type="button" class="op-swatch op-swatch-3" data-color="#4a8f3d" aria-label="Myrkkyvihreä"></button>
                        <button type="button" class="op-swatch op-swatch-4" data-color="#3d7a9e" aria-label="Ruumissininen"></button>
                        <button type="button" class="op-swatch op-swatch-5" data-color="#8a3d9e" aria-label="Myrkkyvioletti"></button>
                        <span class="op-color-preview" id="opColorPreview" aria-hidden="true"></span>
                        <input type="text" id="opColorHex" class="op-color-hex" placeholder="#rrggbb" maxlength="7" autocomplete="off" aria-label="Oma väri hex-muodossa, esim. #cc0000">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" id="operationModalCancel">Peruuta</button>
                <button class="btn-save"   id="operationModalSave">Tallenna 🩸</button>
            </div>
        </div>
    </div>
    <!-- Operaatiomodal loppuu -->

    <!-- Jumpscare-elementti — aluksi piilossa, näytetään satunnaisesti kun tehtäviä aloitetaan tai merkitään valmiiksi -->
    <div id="jumpScare" class="no-caret">
        <div class="zombie-wrapper">
            <div class="zombie-face">
            <div class="eye left"></div>
            <div class="eye right"></div>
            <div class="mouth"></div>
            </div>
        </div>
    </div>

    <!-- JavaScriptit ladataan sivun lopussa jotta HTML on valmis ennen scriptejä -->
    <script src="assets/js/ui.js"></script><!-- Yleiset UI-toiminnot -->
    <script src="assets/js/flatpickr.min.js"></script><!-- Flatpickr-kirjasto päivämäärävalitsimia varten — ladataan paikallisesti -->
    <script src="assets/js/tasks.js"></script> <!-- Tehtävälogiikka -->
    
</body>
</html>