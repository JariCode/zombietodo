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
            <div class="operation-select-wrap" id="operationSelectWrap">
                <select id="operationSelect" class="operation-select" aria-label="Aktiivinen operaatio">
                    <?php foreach ($operations as $op): ?>
                    <option value="<?= (int)$op['id'] ?>"
                            data-color="<?= clean($op['color'] ?: '#880000') ?>"
                            data-description="<?= clean($op['description'] ?? '') ?>"
                            <?= ((int)$op['id'] === (int)$activeOperationId) ? 'selected' : '' ?>><?= clean($op['name']) ?></option>
                    <?php endforeach; ?>
                    <option value="__new__">➕ Uusi operaatio</option>
                </select>
            </div>
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
                <div>
                    <h2 id="koosteTitle">📊 Kooste</h2>
                    <p id="koosteOpName" class="kooste-op-name"></p>
                </div>
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

    <!-- Bub AI Assistant -->
    <div class="bub-widget" id="bub-widget">
        <button type="button" class="bub-small" id="bub-small" aria-label="Avaa Bub">
            <svg class="bub-svg bub-svg-small" viewBox="0 0 320 430" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <use href="#bub-character"></use>
            </svg>
        </button>

        <div class="bub-overlay" id="bub-overlay" aria-hidden="true">
            <div class="bub-stage">
                <section class="bub-chat-window" role="dialog" aria-modal="true" aria-label="Bub AI Assistant">
                    <button type="button" class="bub-close" id="bub-close" aria-label="Sulje Bub">×</button>

                    <div class="bub-chat-header">
                        <div>
                            <strong>BUB</strong>
                            <span>Kun aivot loppuvat, Bub auttaa</span>
                        </div>
                    </div>

                    <div class="bub-messages" id="bub-messages"></div>

                    <form class="bub-form" id="bub-form">
                        <input
                            type="text"
                            id="bub-message"
                            name="message"
                            placeholder="Kysy Bubilta..."
                            autocomplete="off"
                            maxlength="2000"
                        >
                        <button type="submit">Lähetä</button>
                    </form>
                </section>

                <div class="bub-large" aria-hidden="true">
                    <svg class="bub-svg bub-svg-large" viewBox="0 0 320 430" xmlns="http://www.w3.org/2000/svg">
                        <use href="#bub-character"></use>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <svg class="bub-svg-defs" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <defs>
            <radialGradient id="bub-skin" cx="50%" cy="38%" r="68%">
                <stop offset="0%" stop-color="#858c70"/>
                <stop offset="45%" stop-color="#686f58"/>
                <stop offset="78%" stop-color="#505744"/>
                <stop offset="100%" stop-color="#363b30"/>
            </radialGradient>

            <linearGradient id="bub-shirt" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="#20281e"/>
                <stop offset="55%" stop-color="#111710"/>
                <stop offset="100%" stop-color="#050705"/>
            </linearGradient>

            <radialGradient id="bub-eye" cx="50%" cy="45%" r="60%">
                <stop offset="0%" stop-color="#b54437"/>
                <stop offset="45%" stop-color="#79251f"/>
                <stop offset="100%" stop-color="#260908"/>
            </radialGradient>

            <linearGradient id="bub-beard" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="#777a69"/>
                <stop offset="50%" stop-color="#5c5f51"/>
                <stop offset="100%" stop-color="#3d4036"/>
            </linearGradient>

            <filter id="bub-shadow" x="-40%" y="-30%" width="180%" height="180%">
                <feDropShadow dx="0" dy="10" stdDeviation="8" flood-color="#000000" flood-opacity=".85"/>
            </filter>

            <filter id="bub-eye-glow" x="-100%" y="-100%" width="300%" height="300%">
                <feGaussianBlur stdDeviation="3"/>
            </filter>

            <symbol id="bub-character" viewBox="0 0 320 430">
                <ellipse cx="160" cy="414" rx="94" ry="11" fill="#000000" opacity=".65"/>

                <!-- Vartalo -->
                <path
                    d="M54 430
                    C57 380 67 342 91 319
                    C109 302 133 294 160 294
                    C187 294 211 302 229 319
                    C253 342 263 380 266 430Z"
                    fill="url(#bub-shirt)"
                    stroke="#090c08"
                    stroke-width="5"
                    filter="url(#bub-shadow)"
                />

                <!-- Pää -->
                <path
                    d="M92 279
                    C78 263 73 240 73 215
                    C73 183 77 150 84 120
                    C93 86 120 62 160 60
                    C200 62 227 86 236 120
                    C243 150 247 183 247 215
                    C247 240 242 263 228 279
                    C211 296 187 304 160 304
                    C133 304 109 296 92 279Z"
                    fill="url(#bub-skin)"
                    stroke="#30362b"
                    stroke-width="4"
                    filter="url(#bub-shadow)"
                />

                <!-- Otsan kevyt ihotekstuuri -->
                <path
                    d="M105 105
                    C119 76 138 63 160 62
                    C182 63 201 76 215 105"
                    fill="none"
                    stroke="#92997e"
                    stroke-width="4"
                    opacity=".16"
                />

                <!-- Kulmakarvat -->
                <path
                    d="M91 148
                    C108 134 132 130 151 138"
                    fill="none"
                    stroke="#343a2d"
                    stroke-width="12"
                    stroke-linecap="round"
                />

                <path
                    d="M169 138
                    C188 130 212 134 229 148"
                    fill="none"
                    stroke="#343a2d"
                    stroke-width="12"
                    stroke-linecap="round"
                />

                <!-- Silmien ympärysten varjot -->
                <ellipse cx="119" cy="177" rx="34" ry="27" fill="#30352a" opacity=".9"/>
                <ellipse cx="201" cy="177" rx="34" ry="27" fill="#30352a" opacity=".9"/>

                <!-- Silmien hehku -->
                <ellipse
                    cx="120"
                    cy="178"
                    rx="21"
                    ry="17"
                    fill="#3b0b09"
                    opacity=".65"
                    filter="url(#bub-eye-glow)"
                />

                <ellipse
                    cx="200"
                    cy="178"
                    rx="21"
                    ry="17"
                    fill="#3b0b09"
                    opacity=".65"
                    filter="url(#bub-eye-glow)"
                />

                <ellipse cx="120" cy="178" rx="17" ry="14" fill="url(#bub-eye)"/>
                <ellipse cx="200" cy="178" rx="17" ry="14" fill="url(#bub-eye)"/>

                <ellipse cx="120" cy="178" rx="6" ry="9" fill="#120403"/>
                <ellipse cx="200" cy="178" rx="6" ry="9" fill="#120403"/>

                <circle cx="116" cy="174" r="2.5" fill="#d9d0b4"/>
                <circle cx="196" cy="174" r="2.5" fill="#d9d0b4"/>

                <!-- Poskien varjot -->
                <path
                    d="M89 203
                    C103 220 119 229 139 232"
                    fill="none"
                    stroke="#3c4233"
                    stroke-width="6"
                    stroke-linecap="round"
                    opacity=".5"
                />

                <path
                    d="M231 203
                    C217 220 201 229 181 232"
                    fill="none"
                    stroke="#3c4233"
                    stroke-width="6"
                    stroke-linecap="round"
                    opacity=".5"
                />

                <!-- Nenä -->
                <path
                    d="M153 169
                    C150 190 146 213 150 229
                    C152 237 168 237 170 229
                    C174 213 170 190 167 169"
                    fill="#59604d"
                    stroke="#363c30"
                    stroke-width="3"
                />

                <path
                    d="M148 228
                    C153 233 167 233 172 228"
                    fill="none"
                    stroke="#34392e"
                    stroke-width="3"
                    stroke-linecap="round"
                />

<!-- Suu ja parta -->
<g id="bub-mouth">
    <path
        class="bub-mouth-opening"
        d="M116 246
        C130 237 145 234 160 234
        C175 234 190 237 204 246
        C198 264 183 275 160 277
        C137 275 122 264 116 246Z"
        fill="#252a23"
        stroke="#34392e"
        stroke-width="3"
    />

    <!-- Parta -->
    <path
        d="M122 250
        C133 263 146 269 160 270
        C174 269 187 263 198 250
        C193 276 178 291 160 294
        C142 291 127 276 122 250Z"
        fill="url(#bub-beard)"
        opacity=".9"
    />

    <path
        d="M132 258 L138 280
        M144 261 L148 287
        M156 263 L160 290
        M168 263 L172 287
        M180 261 L182 280
        M191 257 L186 276"
        fill="none"
        stroke="#4b4e42"
        stroke-width="3"
        stroke-linecap="round"
        opacity=".8"
    />
</g>

            <!-- Korvat -->
            <path
                d="M81 163
                C72 159 65 164 63 175
                C61 188 64 202 71 207
                C79 204 84 193 85 180
                C86 171 84 165 81 163Z"
                fill="url(#bub-skin)"
                stroke="#343a2d"
                stroke-width="4"
                transform="translate(-7 -25) rotate(-8 73 184)"
            />

            <path
                d="M239 163
                C248 159 255 164 257 175
                C259 188 256 202 249 207
                C241 204 236 193 235 180
                C234 171 236 165 239 163Z"
                fill="url(#bub-skin)"
                stroke="#343a2d"
                stroke-width="4"
                transform="translate(7 -25) rotate(8 247 184)"
            />

            <path
                d="M74 169
                C69 178 69 190 73 200"
                fill="none"
                stroke="#4b5240"
                stroke-width="3"
                stroke-linecap="round"
                opacity=".8"
                transform="translate(-7 -25) rotate(-8 73 184)"
            />

            <path
                d="M246 169
                C251 178 251 190 247 200"
                fill="none"
                stroke="#4b5240"
                stroke-width="3"
                stroke-linecap="round"
                opacity=".8"
                transform="translate(7 -25) rotate(8 247 184)"
            />
                <!-- Kaula -->
                <path
                    d="M111 293
                    C125 306 142 312 160 312
                    C178 312 195 306 209 293
                    L217 318
                    C199 330 181 336 160 336
                    C139 336 121 330 103 318Z"
                    fill="#4a5141"
                    opacity=".75"
                />

                <!-- Paita -->
                <path
                    d="M92 319
                    C111 308 135 304 160 304
                    C185 304 209 308 228 319
                    C247 343 258 381 261 430
                    L59 430
                    C62 381 73 343 92 319Z"
                    fill="url(#bub-shirt)"
                    stroke="#090c08"
                    stroke-width="5"
                />

                <!-- Paidan kevyt rakenne -->
                <path
                    d="M108 328
                    C123 338 141 344 160 344
                    C179 344 197 338 212 328"
                    fill="none"
                    stroke="#263025"
                    stroke-width="5"
                    opacity=".7"
                />
            </symbol>
        </defs>
    </svg>   
    

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
    <script src="assets/js/bub.js"></script> <!-- AI-assistantti -->
    
</body>
</html>