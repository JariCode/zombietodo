<?php
// ================================
// reset-password.php — Salasanan nollaus-sivu
// ================================

// Etsitään zombie-config-kansio — tarkistetaan ensin yksi taso ylös, sitten kaksi
// Paikallisesti kansio on yhden tason päässä, palvelimella kahden
$cfgDir = is_dir(dirname(__DIR__) . '/zombie-config')
    ? dirname(__DIR__) . '/zombie-config'
    : dirname(dirname(__DIR__)) . '/zombie-config';
require_once $cfgDir . '/session-config.php'; // Istuntoasetukset ENSIN
require_once $cfgDir . '/db.php';             // Tietokantayhteys

// Luetaan token URL:sta
$token = $_GET['token'] ?? '';

// Tarkistetaan token: olemassa, ei vanhentunut
$validToken = false;
$tokenUserId = null;

if ($token !== '') {
    $stmt = $conn->prepare('SELECT user_id, expires_at FROM password_resets WHERE token = ?');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && strtotime($row['expires_at']) > time()) {
        $validToken = true;          // Token kelpaa
        $tokenUserId = $row['user_id'];
    }
}

// Haetaan mahdollinen virhe/onnistumisviesti sessiosta (lomakkeen lähetyksen jälkeen)
$resetError   = $_SESSION['reset_error']   ?? '';
$resetSuccess = $_SESSION['reset_success'] ?? '';
unset($_SESSION['reset_error'], $_SESSION['reset_success']);

?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salasanan palautus - Zombie To-Do</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Veriantimaatio -->
    <div class="blood"></div>

    <div class="container no-caret">
        <img src="assets/img/Herokuva.webp" class="hero" alt="Zombie To-Do" width="1200" height="630" fetchpriority="high">

        <h1>ZOMBIE RESET</h1>

        <?php if ($resetError !== ''): ?>
            <div class="auth-error"><?= htmlspecialchars($resetError) ?></div>
        <?php endif; ?>

        <?php if ($resetSuccess !== ''): ?>
            <div class="auth-success"><?= htmlspecialchars($resetSuccess) ?></div>
        <?php endif; ?>

        <?php if ($validToken): ?>
            <!-- Token kelpaa — näytetään salasanan vaihtolomake -->
            <div class="auth-box">
                <h2 class="auth-title">Aseta uusi salasana</h2>
                <form method="POST" action="app/actions.php" autocomplete="off">
                    <input type="hidden" name="action" value="complete_password_reset">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                    <label>Uusi salasana</label>
                    <div class="password-field">
                        <input type="password" name="password" placeholder="********" required minlength="10" maxlength="72" autocomplete="off">
                        <button type="button" class="password-eye" aria-label="Näytä salasana">👁️</button>
                    </div>

                    <label>Toista uusi salasana</label>
                    <div class="password-field">
                        <input type="password" name="password_confirm" placeholder="********" required minlength="10" maxlength="72" autocomplete="off">
                        <button type="button" class="password-eye" aria-label="Näytä salasana">👁️</button>
                    </div>

                    <button type="submit">Vaihda salasana 🔑</button>
                </form>
            </div>
        <?php else: ?>
            <!-- Token ei kelpaa tai puuttuu -->
            <div class="auth-box reset-invalid">
                <div class="reset-invalid-icon">🧟</div>
                <h2 class="auth-title">Linkki ei kelpaa</h2>
                <p>Palautuslinkki on vanhentunut tai jo käytetty.</p>
                <p>Voit pyytää uuden linkin kirjautumissivulta.</p>
                <a href="index.php" class="reset-back-btn">↩ Takaisin kirjautumiseen</a>
            </div>
        <?php endif; ?>
    </div>
<!-- JavaScript -->
    <script src="assets/js/ui.js"></script>
</body>
</html>
