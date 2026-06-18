<?php
// ================================
// profile.php — käyttäjän profiilisivu, jossa käyttäjä voi muuttaa tietojaan, vaihtaa salasanansa tai poistaa tilinsä
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
    $_SESSION['error'] = 'Istunto on vanhentunut. Kirjaudu uudelleen.';
    header('Location: index.php');
    exit;
}

// Luodaan tai palautetaan CSRF-token sivun latauksen yhteydessä
generateCSRFToken();

// Apufunktio — suojaa XSS-hyökkäyksiltä muuttamalla erikoismerkit turvalliseen muotoon
function clean($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// Haetaan kirjautuneen käyttäjän tiedot
$uid = intval($_SESSION['user_id']);
$user = $conn->prepare("SELECT username, email FROM users WHERE id=?");
$user->bind_param('i', $uid);
$user->execute();
$result = $user->get_result();
$userData = $result->fetch_assoc();

$username = $userData['username'] ?? '';
$email = $userData['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8"> <!-- Merkistö — tukee suomen kielen merkkejä -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Skaalautuu eri laitteille -->
    <title>Zombie Profile</title>
    <meta name="description" content="Zombie To-Do — muokkaa profiiliasi, vaihda salasana tai poista tilisi."> <!-- Selaimen ja hakukoneiden kuvausteksti -->
    <link rel="icon" type="image/png" href="assets/img/favicon.png"> <!-- Selaimen välilehden ikoni -->
    <link rel="stylesheet" href="assets/css/style.css"> <!-- Sovelluksen omat tyylit -->
</head>
<body>

    <!-- Veriantimaatio — valuu sivun yläreunasta ja häviää -->
    <div class="blood"></div>

    <!-- Pääkontaineri joka sisältää kaikki sivun elementit -->
    <div class="container no-caret">

        <!-- Herokuva — suuri kuva joka esittelee sovelluksen teeman -->
        <img src="assets/img/Herokuva.webp" class="hero" alt="Zombie To-Do" width="1200" height="630" fetchpriority="high">

        <!-- Yläpalkki — tervetuloviesti ja navigointilinkit. Sama rakenne kuin tasks.php:ssä -->
        <div class="header-bar">
            <span class="welcome-text">Tervetuloa, <?= htmlspecialchars($_SESSION['username']) ?>!</span> <!-- Näytetään kirjautuneen käyttäjän nimi turvallisesti -->
            <div class="header-links">
                <a href="tasks.php" class="header-link">Tehtävälista&nbsp;⚔️</a> <!-- Profiilisivulla tämä vie takaisin tehtävälistalle — tasks.php:ssä samassa paikassa on linkki profiilisivulle -->
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><!-- Näytetään admin-linkki vain jos käyttäjällä on admin-rooli -->
                    <a href="admin.php" class="header-link">Admin&#8209;paneeli&nbsp;⚙️</a>
                <?php endif; ?>
                <form method="POST" action="app/actions.php"> <!-- POST koska uloskirjautuminen muuttaa istunnon tilaa -->
                    <input type="hidden" name="action" value="logout"> <!-- Toiminto POST-datana URL:n sijaan -->
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>"> <!-- CSRF-suojaus — estää ulkopuolisen kirjaamasta käyttäjän ulos -->
                    <button type="submit" class="header-link">Kirjaudu&nbsp;ulos&nbsp;❌</button>
                </form>
            </div>
        </div>

        <!-- Pääotsikko -->
        <h1>ZOMBIE UPDATE</h1>

        <!-- Virheilmoitus — näytetään vain jos virheitä on -->
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="auth-error">
                <?= htmlspecialchars($_SESSION['error']); ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Onnistumisilmoitus — näytetään vain jos toiminto onnistui -->
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="auth-success">
                <?= htmlspecialchars($_SESSION['success']); ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <!-- PROFIILIN TIEDOT — Käyttäjä voi muokata käyttäjänimeään ja sähköpostia -->
        <div class="auth-box">
            <h2 class="auth-title">Zombie Profiili</h2>

            <form method="POST" action="app/actions.php" autocomplete="off"> <!-- Toiminto kulkee nyt piilokenttänä POST-bodyssa eikä URL-parametrina -->
                <input type="hidden" name="action" value="update_profile"> <!-- Toiminto POST-datana URL:n sijaan — yhtenäinen tyyli muiden lomakkeiden kanssa -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>"> <!-- CSRF-suojaus — estää lomakkeen väärennöksen ulkopuoliselta sivulta -->

                <label>Käyttäjänimi</label>
                <input type="text" name="username" placeholder="<?= clean($username) ?>" required autocomplete="off" minlength="3" maxlength="30"> <!-- Nykyinen käyttäjänimi näytetään placeholder-tekstinä. Pituusrajoitus selainpuolella, sama kuin palvelimella -->

                <label>Sähköposti</label>
                <input type="email" name="email" placeholder="<?= clean($email) ?>" required autocomplete="off"> <!-- Nykyinen sähköposti näytetään placeholder-tekstinä. type=email validoi muodon selaimessa -->

                <button type="submit">Tallenna muutokset 🧠</button>
            </form>
        </div>

        <!-- SALASANAN VAIHTO — Käyttäjä voi vaihtaa salasanansa -->
        <div class="auth-box">
            <h2 class="auth-title">Vaihda salasana 🔒</h2>

            <form method="POST" action="app/actions.php"> <!-- Toiminto kulkee nyt piilokenttänä POST-bodyssa eikä URL-parametrina -->
                <input type="hidden" name="action" value="change_password"> <!-- Toiminto POST-datana URL:n sijaan — yhtenäinen tyyli muiden lomakkeiden kanssa -->
                 <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>"> <!-- CSRF-suojaus — estää lomakkeen väärennöksen ulkopuoliselta sivulta -->

                <label>Vanha salasana</label>
                <div class="password-field">
                    <input type="password" name="old_password" placeholder="********" required autocomplete="off"> <!-- Nykyinen salasana varmentamiseksi. Ei pituusrajaa, koska tämä on olemassa oleva salasana -->
                    <button type="button" class="password-eye" aria-label="Näytä salasana">👁️</button> <!-- Silmäpainike salasanan näyttöä/piilotusta varten -->
                </div>

                <label>Uusi salasana</label>
                <div class="password-field">
                    <input type="password" name="new_password" placeholder="********" required autocomplete="off" minlength="10" maxlength="72"> <!-- Uusi salasana. Min 10 merkkiä, max 72 bcryptin takia — sama kuin rekisteröinnissä -->
                    <button type="button" class="password-eye" aria-label="Näytä salasana">👁️</button> <!-- Silmäpainike salasanan näyttöä/piilotusta varten -->
                </div>

                <label>Uusi salasana uudelleen</label>
                <div class="password-field">
                    <input type="password" name="new_password2" placeholder="********" required autocomplete="off" minlength="10" maxlength="72"> <!-- Uuden salasanan varmistus — salasanan oltava sama kuin yllä. Samat rajat -->
                    <button type="button" class="password-eye" aria-label="Näytä salasana">👁️</button> <!-- Silmäpainike salasanan näyttöä/piilotusta varten -->
                </div>

                <button type="submit">Vaihda salasana 🔒</button>
            </form>
        </div>

        <!-- KÄYTTÄJÄTILIN POISTO — Pysyvä toimenpide, jonka jälkeen kaikki tiedot poistetaan -->
        <div class="auth-box">
            <h2 class="auth-title">Poista tili 🪦</h2>

            <form method="POST" action="app/actions.php" autocomplete="off"> <!-- Toiminto kulkee nyt piilokenttänä POST-bodyssa eikä URL-parametrina -->
                <input type="hidden" name="action" value="delete_account"> <!-- Toiminto POST-datana URL:n sijaan — yhtenäinen tyyli muiden lomakkeiden kanssa -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>"> <!-- CSRF-suojaus — estää lomakkeen väärennöksen ulkopuoliselta sivulta -->

                <label>Käyttäjänimi</label>
                <input type="text" name="confirm_username" placeholder="<?= clean($username) ?>" required autocomplete="off" minlength="3" maxlength="30"> <!-- Käyttäjä vahvistaa omalla käyttäjänimellään. Sama pituusraja kuin muualla -->

                <label>Sähköposti</label>
                <input type="email" name="confirm_email" placeholder="<?= clean($email) ?>" required autocomplete="off"> <!-- Käyttäjä vahvistaa omalla sähköpostillaan. type=email validoi muodon -->

                <label>Vahvista salasana</label>
                <div class="password-field">
                    <input type="password" name="confirm_password" placeholder="********" required autocomplete="off"> <!-- Salasana varmentaa että käyttäjä todella haluaa poistaa tilin. Ei pituusrajaa, koska tämä on olemassa oleva salasana -->
                    <button type="button" class="password-eye" aria-label="Näytä salasana">👁️</button> <!-- Silmäpainike salasanan näyttöä/piilotusta varten -->
                </div>

                <button type="submit">Poista tili pysyvästi 🩸</button>
            </form>
        </div>

    </div> <!-- .container loppuu -->

    <!-- JavaScriptit — UI-toiminnallisuudet (salasananäyttö, ilmoitusten häivytys) -->
    <script src="assets/js/ui.js"></script>

</body>
</html>