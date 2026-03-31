<?php
// ================================
// index.php
//
// Tämä on sovelluksen pääsivu — ensimmäinen
// sivu jonka käyttäjä näkee.
//
// Tällä sivulla on:
// - Kirjautumislomake
// - Rekisteröitymislomake
// - Linkki unohtuneeseen salasanaan
// - Käyttöehtojen ja tietosuojaselosteen
//   hyväksyminen rekisteröityessä
//
// Jos käyttäjä on jo kirjautunut sisään,
// hänet ohjataan suoraan tehtävälistalle
// eikä kirjautumissivua näytetä ollenkaan.
//
// Istunto vanhenee tunnin kuluttua jolloin
// käyttäjä ohjataan takaisin kirjautumaan.
// Sisäänpääsy ei siis säily ikuisesti.
// ================================
require_once 'app/session-config.php'; // Istuntoasetukset. ENSIN ennen kaikkea muuta
require_once 'app/db.php'; // Tietokantayhteys $conn-muuttujaan

// Haetaan mahdollisesti tallennetut kenttien arvot sessiosta
// Nämä täytetään takaisin lomakkeelle jos rekisteröinti tai kirjautuminen epäonnistui
$form_username    = $_SESSION['form_username']    ?? ''; // Käyttäjänimi palautetaan rekisteröintikentttään
$form_email       = $_SESSION['form_email']       ?? ''; // Sähköposti palautetaan rekisteröintikenttään
$form_login_email = $_SESSION['form_login_email'] ?? ''; // Sähköposti palautetaan kirjautumiskenttään virheen jälkeen
unset($_SESSION['form_username'], $_SESSION['form_email'], $_SESSION['form_login_email']); // Poistetaan sessiosta heti ettei näy enää seuraavalla latauksella
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8"> <!-- Merkistö — tukee suomen kielen merkkejä -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Skaalautuu eri laitteille -->
    <title>Zombie To-Do</title>
    <meta name="description" content="Zombie To-Do — selviä apokalypsistä hallitsemalla tehtäväsi. Kirjaudu sisään ja pidä zombit loitolla."> <!-- Selaimen ja hakukoneiden kuvausteksti -->
    <link rel="icon" type="image/png" href="assets/img/favicon.png"> <!-- Selaimen välilehden ikoni -->
    <link rel="stylesheet" href="style.css"> <!-- Sovelluksen omat tyylit -->
</head>
<body>
    <!-- Veriantimaatio — valuu sivun yläreunasta ja häviää -->
    <div class="blood"></div>

    <!--Pääkontaineri joka sisältää kaikki sivun elementit ja toiminnallisuudet.-->
    <div class="container">

        <!--Herokuva — suuri kuva joka esittelee sovelluksen teeman ja tunnelman.-->
        <img src="assets/img/Herokuva.webp" class="hero" alt="Zombie To-Do" fetchpriority="high">

        <!-- WIP-banneri — näkyy sivun yläosassa kun sovellus on kehitysvaiheessa -->
        <div class="wip-banner">🧠 WORK IN PROGRESS… BRAINS LOADING 🩸</div>

        <!-- Pääotsikko-->
        <h1>ZOMBIE LOGIN</h1>

        <!-- Virheilmoitus. Näytetään vain jos virheitä on -->
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="auth-error">
                <?= htmlspecialchars($_SESSION['error']); ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Onnistumisilmoitus. Esim. rekisteröinti onnistui -->
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="auth-success">
                <?= htmlspecialchars($_SESSION['success']); ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <!-- Kirjautumislomake -->
        <div class="auth-box">
            <h2 class="auth-title">Kirjaudu sisään</h2>
            <form method="POST" action="app/actions.php" autocomplete="off">
                <input type="hidden" name="action" value="login"> <!-- Toiminto POST-datana URL:n sijaan -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>"> <!-- CSRF-suojaus. Estää lomakkeen väärennöksen ulkopuoliselta sivulta -->
                <label>Sähköposti</label>
                <input type="email" name="email" value="<?= htmlspecialchars($form_login_email) ?>" placeholder="example@domain.com" required autocomplete="off"> <!-- Arvo palautetaan kenttään kirjautumisvirheen jälkeen -->
                <label>Salasana</label>
                <div class="password-field">
                    <input type="password" name="password" placeholder="********" required minlength="10" maxlength="72" autocomplete="off"> <!-- Min 10 merkkiä, max 72 bcryptin takia -->
                    <button type="button" class="password-eye" aria-label="Näytä salasana">👁️</button>
                </div>
                <button type="submit">Kirjaudu sisään 🔑</button>
                <a href="#" class="forgot-link">Vai unohtuiko salasanasi? 🧟</a>
            </form>
        </div>

        <!--Lomakkeiden väliin erotin teksti-->
        <div class="auth-separator">TAI LUO TILI</div>

        <!-- Rekisteröitymislomake -->
        <div class="auth-box">
            <h2 class="auth-title">Rekisteröidy</h2>
            <form method="POST" action="app/actions.php" autocomplete="off">
                <input type="hidden" name="action" value="register"> <!-- Toiminto POST-datana URL:n sijaan -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>"> <!-- CSRF-suojaus. Estää lomakkeen väärennöksen ulkopuoliselta sivulta -->
                <label>Käyttäjänimi</label>
                <input type="text" name="username" value="<?= htmlspecialchars($form_username) ?>" placeholder="ZombieMaster91" required minlength="3" maxlength="30" autocomplete="off"> <!-- Arvo palautetaan kentälle virheen jälkeen. Min 3, max 30 merkkiä -->
                <label>Sähköposti</label>
                <input type="email" name="email" value="<?= htmlspecialchars($form_email) ?>" placeholder="example@domain.com" required autocomplete="off"> <!-- Arvo palautetaan kentälle virheen jälkeen -->
                <label>Salasana</label>
                <div class="password-field">
                    <input type="password" name="password" placeholder="********" required minlength="10" maxlength="72" autocomplete="off"> <!-- Min 10 merkkiä, max 72 bcryptin takia -->
                    <button type="button" class="password-eye" aria-label="Näytä salasana">👁️</button>
                </div>
                <label>Toista salasana</label>
                <div class="password-field">
                    <input type="password" name="password_confirm" placeholder="********" required minlength="10" maxlength="72" autocomplete="off"> <!-- Samat rajat kuin salasanakentässä -->
                    <button type="button" class="password-eye" aria-label="Näytä salasana">👁️</button>
                </div>
                <div class="checkbox-wrapper">
                    <label for="acceptPrivacyPolicy" class="checkbox-label">
                        <input type="checkbox" id="acceptPrivacyPolicy" name="terms" required>
                        Hyväksyn
                        <button type="button" class="link-btn" onclick="openLegalModal('terms')">käyttöehdot</button> ja
                        <button type="button" class="link-btn" onclick="openLegalModal('privacy')">tietosuojaselosteen</button>.
                    </label>
                </div>
                <button type="submit">Rekisteröidy 🧟‍♂️</button>
            </form>
        </div>
    </div>
<!--JavaScriptit. Lomakkeiden toiminnallisuudet-->
<script src="assets/js/ui.js"></script>
</body>
</html>