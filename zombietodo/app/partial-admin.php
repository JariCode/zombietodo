<?php
// ================================
// partial-admin.php — Palauttaa käyttäjä- tai lokitaulukon HTML:n AJAX-pyynnöllä
// admin.js kutsuu tätä kun suodattimia käytetään
// ================================

// Etsitään zombie-config-kansio — tarkistetaan ensin kaksi tasoa ylös, sitten kolme
// App-kansio on yhden tason syvemmällä kuin juuritiedostot
$cfgDir = is_dir(dirname(dirname(__DIR__)) . '/zombie-config')
    ? dirname(dirname(__DIR__)) . '/zombie-config'
    : dirname(dirname(dirname(__DIR__))) . '/zombie-config';
require_once $cfgDir . '/session-config.php'; // Istuntoasetukset ENSIN
require_once $cfgDir . '/db.php';             // Tietokantayhteys

// Tarkistetaan kirjautuminen
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

// Tarkistetaan admin-rooli
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit;
}

// Tarkistetaan CSRF-token — admin.js lähettää sen headerissa
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCSRFToken($csrfToken)) {
    http_response_code(403);
    exit;
}

// Apufunktio XSS-suojaukseen
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// Luetaan mikä osio haetaan — 'users' tai 'logs'
$type = $_POST['type'] ?? '';

// ===========================================================
// KÄYTTÄJÄLISTA
// ===========================================================
if ($type === 'users') {

    // Luetaan suodattimet POST-datasta
    $filterSearch = $_POST['user_search'] ?? '';
    $filterRole   = $_POST['user_role']   ?? '';

    // Rakennetaan WHERE-ehto dynaamisesti
    $userWhere  = "WHERE 1=1";
    $userParams = [];
    $userTypes  = '';

    if ($filterSearch !== '') {
        $userWhere   .= " AND (username LIKE ? OR email LIKE ?)";
        $searchTerm   = '%' . $filterSearch . '%';
        $userParams[] = $searchTerm;
        $userParams[] = $searchTerm;
        $userTypes   .= 'ss';
    }

    if ($filterRole !== '') {
        $userWhere   .= " AND role = ?";
        $userParams[] = $filterRole;
        $userTypes   .= 's';
    }

    // Haetaan käyttäjät
    $userStmt = $conn->prepare("
        SELECT id, username, email, role, admin_locked, created_at
        FROM users
        $userWhere
        ORDER BY created_at DESC
        LIMIT 200
    ");
    if (!empty($userParams)) {
        $userStmt->bind_param($userTypes, ...$userParams);
    }
    $userStmt->execute();
    $users = $userStmt->get_result();
    $userStmt->close();
?>
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
            <?php while ($user = $users->fetch_assoc()): ?>
                <tr data-id="<?= intval($user['id']) ?>">
                    <td><?= e($user['username']) ?></td>
                    <td><?= e($user['email']) ?></td>
                    <td class="<?= $user['role'] === 'admin' ? 'role-admin' : 'role-user' ?>">
                        <?= $user['role'] === 'admin' ? 'Admin' : 'Käyttäjä' ?>
                    </td>
                    <td>
                        <button type="button" class="admin-btn-edit" data-id="<?= intval($user['id']) ?>">HALLINTA 🧟</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
    </tbody>
</table>
<?php
}

// ===========================================================
// LOKITAPAHTUMAT
// ===========================================================
if ($type === 'logs') {
    // Luetaan suodattimet POST-datasta
    $filterEvent = $_POST['log_event'] ?? '';
    $filterFrom  = $_POST['log_from']  ?? '';
    $filterTo    = $_POST['log_to']    ?? '';
    $filterSearch = $_POST['user_search'] ?? ''; // Käyttäjähaku — admin.js lähettää mukana

    // Rakennetaan WHERE-ehto dynaamisesti
    $logWhere  = "WHERE 1=1";
    $logParams = [];
    $logTypes  = '';

    if ($filterEvent !== '') {
        $logWhere   .= " AND l.event = ?";
        $logParams[] = $filterEvent;
        $logTypes   .= 's';
    }

    if ($filterFrom !== '') {
        $fromDate = DateTime::createFromFormat('d.m.Y', $filterFrom);
        if ($fromDate) {
            $logWhere   .= " AND l.timestamp >= ?";
            $logParams[] = $fromDate->format('Y-m-d') . ' 00:00:00';
            $logTypes   .= 's';
        }
    }

    if ($filterTo !== '') {
        $toDate = DateTime::createFromFormat('d.m.Y', $filterTo);
        if ($toDate) {
            $logWhere   .= " AND l.timestamp <= ?";
            $logParams[] = $toDate->format('Y-m-d') . ' 23:59:59';
            $logTypes   .= 's';
        }
    }

    // Lisätään käyttäjäsuodatin jos käyttäjälista on suodatettu
    // $filterSearch on sama arvo joka on jo käytössä käyttäjälistan haussa
    if ($filterSearch !== '') {
        $logWhere   .= " AND l.username LIKE ?";
        $logParams[] = '%' . $filterSearch . '%';
        $logTypes   .= 's';
    }

    // Haetaan lokitapahtumat
    $dataStmt = $conn->prepare("
        SELECT l.timestamp, l.username, l.event, tu.username AS target_username
        FROM logs l
        LEFT JOIN users tu ON tu.id = l.target_user_id
        $logWhere
        ORDER BY l.timestamp DESC
        LIMIT 200
    ");
    if (!empty($logParams)) {
        $dataStmt->bind_param($logTypes, ...$logParams);
    }
    $dataStmt->execute();
    $logs = $dataStmt->get_result();
    $dataStmt->close();
?>
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
                    <td><?= e($log['target_username'] ?? $log['username'] ?? 'Poistettu käyttäjä') ?></td>
                    <td><?= e($eventLabels[$log['event']] ?? $log['event']) ?></td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
    </tbody>
</table>
<?php
}
