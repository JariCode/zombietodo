<?php
// ================================
// admin.php — hallintasivu, jonne vain admin-käyttäjät pääsevät
// ================================

require_once 'app/session-config.php'; // Istuntoasetukset. ENSIN ennen kaikkea muuta
require_once 'app/db.php';             // Tietokantayhteys $conn-muuttujaan

// Tarkistetaan että käyttäjä on kirjautunut sisään
// Kirjautumaton käyttäjä ohjataan takaisin kirjautumissivulle
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Tarkistetaan että käyttäjä on admin-käyttäjä
// Tavallinen käyttäjä ohjataan takaisin etusivulle
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

// Tarkistetaan istunnon vanheneminen
// Jos tunti on kulunut ilman toimintaa, kirjaudutaan ulos automaattisesti
if (!validateSessionTimeout()) {
    $_SESSION['error'] = 'Istunto on vanhentunut. Kirjaudu uudelleen.';
    header('Location: index.php');
    exit;
}

// Luodaan tai palautetaan CSRF-token sivun latauksen yhteydessä
generateCSRFToken();

// Apufunktio — suojaa XSS-hyökkäyksiltä muuttamalla erikoismerkit turvalliseen muotoon
function clean($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// Haetaan kirjautuneen admin-käyttäjän id
$adminId = intval($_SESSION['user_id']); // intval muuttaa arvon kokonaisluvuksi — suojaa SQL-injektiolta

// ===========================================================
// HAETAAN KÄYTTÄJÄT TIETOKANNASTA
// Admin voi suodattaa käyttäjiä nimen/emailin ja roolin perusteella
// Suodattimet lähetetään POST-lomakkeella CSRF-tokenin kanssa
// ===========================================================

// Alustetaan suodattimet tyhjiksi — täytetään vain jos lomake on lähetetty
$filterSearch = ''; // Käyttäjänimi/email-suodatin — tyhjä = kaikki
$filterRole   = ''; // Rooli-suodatin — tyhjä = kaikki

// Luetaan suodattimet POST-parametreista vain jos lomake on lähetetty ja CSRF-token on validi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_filter'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { // Tarkistetaan CSRF-token
        $_SESSION['error'] = 'Virheellinen CSRF-token.';
        header('Location: admin.php');
        exit;
    }
    $filterSearch = $_POST['user_search'] ?? ''; // Käyttäjänimi/email POST-datasta
    $filterRole   = $_POST['user_role']   ?? ''; // Rooli POST-datasta
}

// Rakennetaan WHERE-ehto dynaamisesti suodattimien perusteella
$userWhere  = "WHERE 1=1"; // 1=1 mahdollistaa AND-ehtojen lisäämisen helposti
$userParams = []; // Prepared statementin parametrit
$userTypes  = ''; // Parametrityypit bind_param-funktiota varten

// Lisätään hakusuodatin jos annettu — etsii käyttäjänimestä TAI emailista
if ($filterSearch !== '') {
    $userWhere   .= " AND (username LIKE ? OR email LIKE ?)"; // Etsii molemmista
    $searchTerm = '%' . $filterSearch . '%'; // Prosenttisignit SQL LIKE:lle
    $userParams[] = $searchTerm;
    $userParams[] = $searchTerm;
    $userTypes   .= 'ss'; // Kaksi stringiä
}

// Lisätään rooli-suodatin jos valittu
if ($filterRole !== '') {
    $userWhere   .= " AND role = ?";
    $userParams[] = $filterRole;
    $userTypes   .= 's';
}

// Haetaan käyttäjät — korkeintaan 200, järjestetty uusimmasta vanhimpaan
$userStmt = $conn->prepare("
    SELECT id, username, email, role, created_at
    FROM users
    $userWhere
    ORDER BY created_at DESC
    LIMIT 200
");
if (!empty($userParams)) { // Sidotaan parametrit vain jos suodattimia on käytössä
    $userStmt->bind_param($userTypes, ...$userParams); // Spread-operaattori purkaa taulukon
}
$userStmt->execute();
$users = $userStmt->get_result();
$userStmt->close();

// ===========================================================
// HAETAAN LOKITAPAHTUMAT TIETOKANNASTA
// Admin voi suodattaa tapahtumia tyypillä ja päivämäärällä
// Suodattimet lähetetään POST-lomakkeella CSRF-tokenin kanssa
// ===========================================================

// Alustetaan suodattimet tyhjiksi — täytetään vain jos lomake on lähetetty
$filterEvent = ''; // Tapahtumatyyppisuodatin — tyhjä = kaikki
$filterFrom  = ''; // Alkamispäivämäärä — tyhjä = ei rajausta
$filterTo    = ''; // Loppumispäivämäärä — tyhjä = ei rajausta

// Luetaan suodattimet POST-parametreista vain jos lomake on lähetetty ja CSRF-token on validi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_filter'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { // Tarkistetaan CSRF-token — estää ulkopuolisen lomakkeen väärennöksen
        $_SESSION['error'] = 'Virheellinen CSRF-token.';
        header('Location: admin.php');
        exit;
    }
    $filterEvent = $_POST['log_event'] ?? ''; // Tapahtumatyyppisuodatin POST-datasta
    $filterFrom  = $_POST['log_from']  ?? ''; // Alkamispäivämäärä POST-datasta
    $filterTo    = $_POST['log_to']    ?? ''; // Loppumispäivämäärä POST-datasta
}

// Rakennetaan WHERE-ehto dynaamisesti suodattimien perusteella
$logWhere  = "WHERE 1=1"; // 1=1 mahdollistaa AND-ehtojen lisäämisen helposti
$logParams = []; // Prepared statementin parametrit — lisätään sitä mukaa kun suodattimia on
$logTypes  = ''; // Parametrityypit bind_param-funktiota varten — 's' = string

// Lisätään tapahtumatyyppisuodatin jos valittu
if ($filterEvent !== '') {
    $logWhere   .= " AND l.event = ?"; // Lisätään ehto WHERE-lauseeseen
    $logParams[] = $filterEvent;        // Lisätään parametri listaan
    $logTypes   .= 's';                // Merkitään tyyppi merkkijonoksi
}

// Lisätään alkamispäivämääräsuodatin jos annettu
// Muunnetaan suomalainen pp.kk.vvvv muoto MySQL:n YYYY-MM-DD muotoon
if ($filterFrom !== '') {
    $fromDate = DateTime::createFromFormat('d.m.Y', $filterFrom); // Parsitaan suomalainen päivämäärä
    if ($fromDate) { // Tarkistetaan että päivämäärä on validi
        $logWhere   .= " AND l.timestamp >= ?"; // Haetaan tapahtumat tästä päivästä eteenpäin
        $logParams[] = $fromDate->format('Y-m-d') . ' 00:00:00'; // Päivän alku
        $logTypes   .= 's';
    }
}

// Lisätään loppumispäivämääräsuodatin jos annettu
if ($filterTo !== '') {
    $toDate = DateTime::createFromFormat('d.m.Y', $filterTo); // Parsitaan suomalainen päivämäärä
    if ($toDate) { // Tarkistetaan että päivämäärä on validi
        $logWhere   .= " AND l.timestamp <= ?"; // Haetaan tapahtumat tähän päivään asti
        $logParams[] = $toDate->format('Y-m-d') . ' 23:59:59'; // Päivän loppu
        $logTypes   .= 's';
    }
}

// Haetaan lokitapahtumat — korkeintaan 200 uusinta, taulukko skrollataan selaimessa
// Username haetaan suoraan logs-taulusta — säilyy vaikka käyttäjä poistetaan
$dataStmt = $conn->prepare("
    SELECT l.timestamp, l.username, l.event
    FROM logs l
    $logWhere
    ORDER BY l.timestamp DESC
    LIMIT 200
");
if (!empty($logParams)) { // Sidotaan parametrit vain jos suodattimia on käytössä
    $dataStmt->bind_param($logTypes, ...$logParams); // Spread-operaattori purkaa taulukon yksittäisiksi parametreiksi
}
$dataStmt->execute();
$logs = $dataStmt->get_result();
$dataStmt->close();

// ===========================================================
// TAPAHTUMIEN NIMET SUOMEKSI
// Käytetään taulukossa ja suodattimen vaihtoehdoissa
// ===========================================================
$eventLabels = [
    'register'                => 'Rekisteröityminen',
    'login'                   => 'Kirjautuminen',
    'logout'                  => 'Uloskirjautuminen',
    'account_updated'         => 'Profiilin muokkaus käyttäjän toimesta',
    'password_changed'        => 'Salasanan vaihto',
    'account_deleted_user'    => 'Tilin poistaminen käyttäjän toimesta',
    'password_reset_requested'=> 'Salasanan palautuspyyntö',
    'password_reset_completed'=> 'Salasanan palautus suoritettu',
    'account_deleted_admin'   => 'Tilin poistaminen adminin toimesta',
    'role_changed'            => 'Roolin vaihto',
];
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8"> <!-- Merkistö — tukee suomen kielen merkkejä -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Skaalautuu eri laitteille -->
    <title>Zombie Admin</title>
    <meta name="description" content="Zombie To-Do — admin-paneeli käyttäjien hallintaan."> <!-- Selaimen ja hakukoneiden kuvausteksti -->
    <link rel="icon" type="image/png" href="assets/img/favicon.png"> <!-- Selaimen välilehden ikoni -->
    <link rel="stylesheet" href="style.css"> <!-- Sovelluksen omat tyylit -->
</head>
<body>

    <!-- Veriantimaatio — valuu sivun yläreunasta ja häviää -->
    <div class="blood"></div>

    <!-- Pääkontaineri — admin-page-luokka mahdollistaa admin-sivun omien tyylien kohdistamisen -->
    <div class="container admin-page">

        <!-- Herokuva — suuri kuva joka esittelee sovelluksen teeman -->
        <img src="assets/img/Herokuva.webp" class="hero" alt="Zombie To-Do" fetchpriority="high">

        <!-- Yläpalkki — tervetuloviesti ja navigointilinkit. Sama rakenne kuin tasks.php:ssä ja profile.php:ssä -->
        <div class="header-bar">
            <span class="welcome-text">Tervetuloa, <?= clean($_SESSION['username']) ?>!</span> <!-- Näytetään kirjautuneen käyttäjän nimi turvallisesti -->
            <div class="header-links">
                <a href="tasks.php" class="header-link">Tehtävälista&nbsp;⚔️</a> <!-- Linkki tehtävälistalle -->
                <a href="profile.php" class="header-link">Muokkaa&nbsp;tietoja&nbsp;🧟‍♀️</a> <!-- Linkki profiilisivulle -->
                <form method="POST" action="app/actions.php"> <!-- POST koska uloskirjautuminen muuttaa istunnon tilaa -->
                    <input type="hidden" name="action" value="logout"> <!-- Toiminto POST-datana URL:n sijaan -->
                    <input type="hidden" name="csrf_token" value="<?= clean(generateCSRFToken()) ?>"> <!-- CSRF-suojaus — estää ulkopuolisen kirjaamasta käyttäjän ulos -->
                    <button type="submit" class="header-link">Kirjaudu&nbsp;ulos&nbsp;❌</button>
                </form>
            </div>
        </div>

        <!-- Pääotsikko -->
        <h1>ZOMBIE MASTER</h1>

        <!-- Virheilmoitus — näytetään vain jos virheitä on -->
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="auth-error">
                <?= clean($_SESSION['error']); ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Onnistumisilmoitus — näytetään vain jos toiminto onnistui -->
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="auth-success">
                <?= clean($_SESSION['success']); ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <!-- ============================================================ -->
        <!-- KÄYTTÄJÄLISTA — hakusuodatin, roolisuodatin ja taulukko      -->
        <!-- Käyttää samaa POST-lomakerakennetta kuin lokitapahtumat      -->
        <!-- ============================================================ -->
        <div class="auth-box">
            <h2 class="auth-title">Käyttäjälista 🧟</h2>

            <!-- Käyttäjäsuodatuslomake — lähetetään POST-pyyntönä samalle sivulle -->
            <form method="POST" action="admin.php">
                <input type="hidden" name="csrf_token" value="<?= clean(generateCSRFToken()) ?>">
                <input type="hidden" name="user_filter" value="1"> <!-- Tunnistetaan käyttäjäsuodatuslomake muista POST-pyynnöistä -->

                <!-- Hakukenttä — etsii käyttäjänimestä ja emailista -->
                <input type="text" name="user_search" placeholder="Hae käyttäjänimellä tai sähköpostilla..." value="<?= clean($filterSearch) ?>" autocomplete="off">

                <!-- Roolisuodatin — vaalea alasvetovalikko -->
                <select name="user_role" class="admin-select">
                    <option value="">Kaikki roolit</option>
                    <option value="user" <?= $filterRole === 'user' ? 'selected' : '' ?>>Käyttäjä</option>
                    <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>

                <!-- Hae-nappi — auth-box button -tyyli, punainen ja täysleveä -->
                <button type="submit">HAE 💀</button>
            </form>

            <!-- Käyttäjätaulukko scrollaavassa divissä — otsikkorivi pysyy paikallaan -->
            <div class="admin-user-scroll">
                <table class="admin-table" id="userTable">
                    <thead>
                        <tr>
                            <th>Käyttäjänimi</th>
                            <th>Sähköposti</th>
                            <th>Rooli</th>
                            <th>Toiminnot</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users->num_rows === 0): ?>
                            <tr>
                                <td colspan="4" class="admin-empty">Ei käyttäjiä.</td>
                            </tr>
                        <?php else: ?>
                            <?php while ($user = $users->fetch_assoc()): ?> <!-- Käydään läpi jokainen käyttäjä ja näytetään taulukossa -->
                                <tr data-id="<?= intval($user['id']) ?>">
                                    <td><?= clean($user['username']) ?></td> <!--Käyttäjänimi näytetään taulukossa -->
                                    <td><?= clean($user['email']) ?></td><!-- Sähköposti näytetään taulukossa -->
                                    <td class="<?= $user['role'] === 'admin' ? 'role-admin' : 'role-user' ?>"><!-- Rooli näytetään taulukossa, adminit oranssinpunaisina ja tavalliset harmaana -->
                                        <?= $user['role'] === 'admin' ? 'Admin' : 'User' ?>
                                    </td>
                                    <td>
                                        <!-- Muokkausnappi avaa modalin — data-id kertoo minkä käyttäjän tiedot ladataan -->
                                        <button type="button" class="admin-btn-edit" data-id="<?= intval($user['id']) ?>">HALLINTA 🧟</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div><!-- .admin-user-scroll loppuu -->
        </div><!-- .auth-box käyttäjälista loppuu -->

        <!-- ============================================================ -->
        <!-- LOKITAPAHTUMAT — suodatin, päivämäärähaku ja taulukko         -->
        <!-- ============================================================ -->
        <div class="auth-box">
            <h2 class="auth-title">Lokitapahtumat 📋</h2>

            <!-- Lokisuodatuslomake — lähetetään POST-pyyntönä samalle sivulle -->
           <form method="POST" action="admin.php">
                <input type="hidden" name="csrf_token" value="<?= clean(generateCSRFToken()) ?>">
                <input type="hidden" name="log_filter" value="1"> <!-- Tunnistetaan lokisuodatuslomake muista POST-pyynnöistä -->

                <!-- Tapahtumatyyppisuodatin — vaalea alasvetovalikko -->
                <select name="log_event" class="admin-select">
                    <option value="">Kaikki tapahtumat</option>
                    <?php foreach ($eventLabels as $key => $label): ?>
                        <option value="<?= clean($key) ?>" <?= $filterEvent === $key ? 'selected' : '' ?>>
                            <?= clean($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Päivämääräsuodattimet vierekkäin -->
                <div class="admin-date-row">
                    <div>
                        <label class="admin-date-label">Alkamispäivämäärä</label>
                        <input type="text" name="log_from" placeholder="pp.kk.vvvv" value="<?= clean($filterFrom) ?>" autocomplete="off">
                    </div>
                    <div>
                        <label class="admin-date-label">Loppumispäivämäärä</label>
                        <input type="text" name="log_to" placeholder="pp.kk.vvvv" value="<?= clean($filterTo) ?>" autocomplete="off">
                    </div>
                </div>

                <!-- Hae-nappi — auth-box button -tyyli, punainen ja täysleveä -->
                <button type="submit">HAE 💀</button>
            </form>

            <!-- Lokitaulukko scrollaavassa divissä — otsikkorivi pysyy paikallaan -->
            <div class="admin-log-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Aika</th>
                            <th>Käyttäjänimi</th>
                            <th>Tapahtuma</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs->num_rows === 0): ?>
                            <tr>
                                <td colspan="3" class="admin-empty">Ei lokitapahtumia.</td>
                            </tr>
                        <?php else: ?>
                            <?php while ($log = $logs->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date('d.m.Y H:i', strtotime($log['timestamp'])) ?></td>
                                    <td><?= clean($log['username'] ?? 'Poistettu käyttäjä') ?></td>
                                    <td><?= clean($eventLabels[$log['event']] ?? $log['event']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div><!-- .admin-log-scroll loppuu -->

        </div><!-- .auth-box lokitapahtumat loppuu -->

    </div><!-- .container loppuu -->

    <!-- ============================================================ -->
    <!-- MUOKKAUSMODAL — avautuu kun admin klikkaa MUOKKAA-nappia      -->
    <!-- Kolme osiota: roolin vaihto, salasanan palautus, tilin poisto -->
    <!-- ============================================================ -->
    <div class="modal-overlay" id="adminModal" role="dialog" aria-modal="true" aria-labelledby="adminModalTitle">
        <div class="modal">

            <!-- Modalin otsikko ja sulje-nappi -->
            <div class="modal-header">
                <h2 id="adminModalTitle">Hallitse käyttäjää 🪓</h2>
                <button class="modal-close" id="adminModalClose" aria-label="Sulje">✕</button>
            </div>

            <div class="modal-body">

                <!-- Virheilmoitus modalin sisällä -->
                <div class="modal-error" id="adminModalError"></div>

                <!-- Käyttäjän tiedot — näytetään kenen tiliä hallitaan -->
                <p class="admin-modal-user" id="adminModalUser"></p>

                <!-- ROOLIN VAIHTO — admin voi vaihtaa käyttäjän roolin -->
                <form id="adminRoleForm" method="POST" action="app/actions.php" autocomplete="off">
                    <input type="hidden" name="action" value="admin_change_role"> <!-- Toiminto POST-datana -->
                    <input type="hidden" name="csrf_token" value="<?= clean(generateCSRFToken()) ?>"> <!-- CSRF-suojaus -->
                    <input type="hidden" name="target_user_id" id="roleTargetId" value=""> <!-- Kohdekayttajan id — täytetään JavaScriptillä -->

                    <label>Rooli</label>
                    <select name="role" id="editRole" class="admin-select">
                        <option value="user">Käyttäjä</option>
                        <option value="admin">Admin</option>
                    </select>

                    <div class="modal-footer">
                        <button type="submit" class="btn-save">VAIHDA ROOLI 👑</button>
                    </div>
                </form>

                <!-- SALASANAN PALAUTUS — admin lähettää palautuslinkin käyttäjän sähköpostiin -->
                <div class="admin-modal-section">
                    <h3 class="admin-section-title">Unohtunut salasana 📧</h3>
                    <form id="adminResetForm" method="POST" action="app/actions.php" autocomplete="off">
                        <input type="hidden" name="action" value="admin_reset_password"> <!-- Toiminto POST-datana -->
                        <input type="hidden" name="csrf_token" value="<?= clean(generateCSRFToken()) ?>"> <!-- CSRF-suojaus -->
                        <input type="hidden" name="target_user_id" id="resetTargetId" value=""> <!-- Kohdekayttajan id -->

                        <input type="email" name="email" id="resetEmail" placeholder="Sähköposti" required autocomplete="off" readonly> <!-- Readonly — näkee sähköpostin mutta ei muokkaa -->

                        <div class="modal-footer">
                            <button type="submit" class="btn-save">LÄHETÄ 📧</button>
                        </div>
                    </form>
                </div>

                <!-- TILIN POISTO — admin poistaa käyttäjän tilin pysyvästi -->
                <div class="admin-modal-section">
                    <h3 class="admin-section-title admin-section-danger">Tilin poistaminen 🔒</h3>
                    <form id="adminDeleteForm" method="POST" action="app/actions.php" autocomplete="off">
                        <input type="hidden" name="action" value="admin_delete_user"> <!-- Toiminto POST-datana -->
                        <input type="hidden" name="csrf_token" value="<?= clean(generateCSRFToken()) ?>"> <!-- CSRF-suojaus -->
                        <input type="hidden" name="target_user_id" id="deleteTargetId" value=""> <!-- Kohdekayttajan id -->

                        <!-- Varoitusteksti ennen pysyvää poistoa -->
                        <p class="admin-delete-warning" id="deleteWarning"></p>

                        <!-- Poista ja peruuta napit -->
                        <div class="modal-footer">
                            <button type="button" class="btn-cancel" id="adminDeleteCancel">Peruuta</button>
                            <button type="submit" class="btn-save admin-btn-danger">POISTA 🩸</button>
                        </div>
                    </form>
                </div>

            </div><!-- .modal-body loppuu -->
        </div><!-- .modal loppuu -->
    </div><!-- .modal-overlay loppuu -->
    
    <!-- JavaScriptit — UI-toiminnallisuudet -->
    <script src="assets/js/ui.js"></script> <!-- Yleiset UI-toiminnot — viestien häivytys -->
    <script src="assets/js/admin.js"></script> <!-- Admin-sivun omat toiminnot — haku, suodatus, modal -->

</body>
</html>
