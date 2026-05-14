<?php
// ================================
// index.php — pääsivu, joka sisältää kirjautumis- ja rekisteröitymislomakkeet
// ================================
require_once 'app/session-config.php'; // Istuntoasetukset. ENSIN ennen kaikkea muuta
require_once 'app/db.php'; // Tietokantayhteys $conn-muuttujaan

// Haetaan mahdollisesti tallennetut kenttien arvot sessiosta
// Nämä täytetään takaisin lomakkeelle jos rekisteröinti tai kirjautuminen epäonnistui
$form_username    = $_SESSION['form_username']    ?? ''; // Käyttäjänimi palautetaan rekisteröintikentttään
$form_email       = $_SESSION['form_email']       ?? ''; // Sähköposti palautetaan rekisteröintikenttään
$form_login_email = $_SESSION['form_login_email'] ?? ''; // Sähköposti palautetaan kirjautumiskenttään virheen jälkeen
unset($_SESSION['form_username'], $_SESSION['form_email'], $_SESSION['form_login_email']); // Poistetaan sessiosta heti ettei näy enää seuraavalla latauksella

// ===========================================================
// HORROR QUOTE API
// ===========================================================
$apiKey = $_ENV['API_NINJAS_KEY'] ?? ''; // Haetaan API-avain .env tiedostosta $_ENV muuttujan kautta. Jos avainta ei löydy, käytetään tyhjää merkkijonoa.
$quoteText = "The signal has been lost..."; // Oletusteksti joka näytetään jos API-haku epäonnistuu.
$curl = curl_init(); // Alustetaan cURL-yhteys ulkoista API-pyyntöä varten.


curl_setopt_array($curl, [ // Määritellään cURL-asetukset taulukossa.
    CURLOPT_URL => "https://api.api-ninjas.com/v1/quotes", // API-osoite josta haetaan satunnainen sitaatti.
    CURLOPT_RETURNTRANSFER => true, // Palauttaa API-vastauksen tekstinä muuttujaan tulostamisen sijaan.
    CURLOPT_TIMEOUT => 3, // Aikakatkaisu 3 sekuntia jotta sivu ei jää lataamaan loputtomasti jos API ei vastaa.
    CURLOPT_HTTPHEADER => [ // Lähetetään HTTP-headerit API-palvelulle.
        "X-Api-Key: $apiKey" // Lähetetään API-avain headerissa API-palvelun tunnistautumista varten.
    ]
]);


$response = curl_exec($curl); // Suoritetaan API-pyyntö ja tallennetaan vastaus muuttujaan.
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE); // Haetaan API-palvelimen palauttama HTTP-statuskoodi.
curl_close($curl); // Suljetaan cURL-yhteys kun pyyntö on valmis.

// Tarkistetaan että API palautti vastauksen onnistuneesti JA HTTP-statuskoodi on 200 OK.
if ($response !== false && $httpCode === 200) {
    $quoteData = json_decode($response, true); // Muutetaan JSON-muotoinen vastaus PHP-taulukoksi.

    // Tarkistetaan että vastauksessa löytyy quote-kenttä.
    if (!empty($quoteData[0]['quote'])) {
        $quoteText = $quoteData[0]['quote']; // Tallennetaan API:lta saatu sitaatti muuttujaan.
    }
}

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

        <!-- Sitaattilaatikko — hakee ja näyttää satunnaisen sitaatin ulkoisesta API:sta -->
        <div class="quote-box">
            <div class="quote-title">☠ SURVIVOR MESSAGE ☠</div>
            <div id="quoteText">
                "<?= htmlspecialchars($quoteText) ?>"<!-- Näytä API:lta haettu sitaatti. htmlspecialchars estää mahdolliset haitalliset merkit-->
            </div>
        </div>

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
                        <button type="button" class="link-btn">käyttöehdot</button> ja
                        <button type="button" class="link-btn">tietosuojaselosteen</button>.
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