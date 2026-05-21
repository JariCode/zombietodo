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

if ($apiKey !== '') { // Tehdään API-kutsu vain jos avain on asetettu .env-tiedostossa
    $curl = curl_init(); // Alustetaan cURL-yhteys ulkoista API-pyyntöä varten.

    curl_setopt_array($curl, [ // Määritellään cURL-asetukset taulukossa.
        CURLOPT_URL => "https://api.api-ninjas.com/v2/randomquotes", // API-osoite josta haetaan satunnainen sitaatti.
        CURLOPT_RETURNTRANSFER => true, // Palauttaa API-vastauksen tekstinä muuttujaan tulostamisen sijaan.
        CURLOPT_TIMEOUT => 5, // Enintään 5 sekuntia — sen jälkeen näytetään oletusteksti eikä sivu jumitu.
        CURLOPT_HTTPHEADER => [ // Lähetetään HTTP-headerit API-palvelulle.
            "X-Api-Key: $apiKey" // Lähetetään API-avain headerissa API-palvelun tunnistautumista varten.
        ]
    ]);

    $response = curl_exec($curl); // Suoritetaan API-pyyntö ja tallennetaan vastaus muuttujaan.
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE); // Tarkistetaan HTTP-statuskoodi — 200 tarkoittaa onnistunutta vastausta.
    curl_close($curl); // Suljetaan cURL-yhteys kun pyyntö on valmis.

    if ($response !== false && $httpCode === 200) { // Tarkistetaan että API palautti onnistuneen vastauksen.
        $quoteData = json_decode($response, true); // Muutetaan JSON-muotoinen vastaus PHP-taulukoksi.

        if (!empty($quoteData[0]['quote'])) { // Tarkistetaan että vastauksessa löytyy quote-kenttä.
            $quoteText = $quoteData[0]['quote']; // Tallennetaan API:lta saatu sitaatti muuttujaan.
        }
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
    <link rel="stylesheet" href="assets/css/style.css"> <!-- Sovelluksen omat tyylit -->
</head>
<body>
    <!-- Veriantimaatio — valuu sivun yläreunasta ja häviää -->
    <div class="blood"></div>

    <!--Pääkontaineri joka sisältää kaikki sivun elementit ja toiminnallisuudet.-->
    <div class="container">

        <!--Herokuva — suuri kuva joka esittelee sovelluksen teeman ja tunnelman.-->
        <img src="assets/img/Herokuva.webp" class="hero" alt="Zombie To-Do" width="1200" height="630"fetchpriority="high">

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

    <!-- KÄYTTÖEHDOT — Modal -->
<div class="legal-overlay" id="legalTerms" role="dialog" aria-modal="true" aria-labelledby="legalTermsTitle">
    <div class="legal-modal">
        <div class="legal-header">
            <h2 id="legalTermsTitle">KÄYTTÖEHDOT 📜</h2>
            <button class="legal-close" aria-label="Sulje">✕</button>
        </div>
        <div class="legal-body">

        <p><small>Päivitetty viimeksi: 20.5.2026</small></p>
            <h3>1. YLEISTÄ</h3>
            <p>Zombie To-Do -palvelun käyttö edellyttää näiden käyttöehtojen hyväksymistä. Palvelun käyttäminen tarkoittaa, että käyttäjä sitoutuu noudattamaan näitä ehtoja.</p>

            <h3>2. KÄYTTÄJÄTILI</h3>
            <p>Käyttäjän tulee rekisteröityä luodakseen henkilökohtaisen käyttäjätilin. Käyttäjätunnus ja salasana ovat henkilökohtaisia, eikä niitä tule luovuttaa muille. Käyttäjä vastaa kaikesta tilinsä kautta tapahtuvasta toiminnasta.</p>

            <h3>3. SISÄLLÖN KÄYTTÖ</h3>
            <p>Käyttäjä omistaa itse luomansa sisällön (tehtävät ja merkinnät) ja saa vapaasti kopioida, viedä tai käyttää sitä haluamallaan tavalla. Palvelun ulkoasu, koodi ja muu sovelluksen oma sisältö on suojattu tekijänoikeuksin, eikä niitä saa kopioida tai hyödyntää kaupallisesti ilman lupaa.</p>

            <h3>4. HENKILÖTIETOJEN KÄSITTELY</h3>
            <p>Rekisteröitymisen yhteydessä käyttäjä hyväksyy Zombie To-Do -palvelun tietosuojaselosteen. Tietosuojaseloste määrittelee, mitä tietoja kerätään ja miten niitä käsitellään.</p>

            <h3>5. PALVELUN KÄYTTÖ</h3>
            <p><p>Palvelua tulee käyttää lain ja hyvän tavan mukaisesti. Häiritsevä, haitallinen, laiton tai muulla tavoin sopimaton toiminta voi johtaa käyttäjätilin sulkemiseen ilman erillistä varoitusta.</p></p>

            <h3>6. VASTUUNRAJOITUS</h3>
            <p>Palvelu tarjotaan "sellaisena kuin se on" ilman takuita saatavuudesta, virheettömyydestä tai keskeytymättömyydestä. Ylläpitäjä ei vastaa tietojen katoamisesta, palvelun käyttökatkoista tai muista vahingoista, jotka aiheutuvat palvelun käytöstä.</p>

            <h3>7. MUUTOKSET EHTOIHIN</h3>
            <p>Palvelun ylläpitäjä voi päivittää käyttöehtoja tarvittaessa. Muutokset astuvat voimaan kun ne julkaistaan palvelussa. Käyttäjän suositellaan tarkistavan ehdot säännöllisesti.</p>

            <h3>8. YHTEYSTIEDOT</h3>
            <p>Kysymyksissä tai epäselvyyksissä voi ottaa yhteyttä palvelun ylläpitäjään:</p>
            <p>JariCode<br>Sähköposti: jaricode@elisanet.fi</p>

        </div>
        <div class="legal-footer">
            <button class="legal-close-btn">SULJE 🔒</button>
        </div>
    </div>
</div>

<!-- ============================================
     TIETOSUOJASELOSTE — Modal
     Avautuu kun käyttäjä klikkaa "tietosuojaseloste"-linkkiä rekisteröintilomakkeessa
     ============================================ -->
<div class="legal-overlay" id="legalPrivacy" role="dialog" aria-modal="true" aria-labelledby="legalPrivacyTitle">
    <div class="legal-modal">
        <div class="legal-header">
            <h2 id="legalPrivacyTitle">Tietosuojaseloste 🔒</h2>
            <button class="legal-close" aria-label="Sulje">✕</button>
        </div>
        <div class="legal-body">
            <p><small>Päivitetty viimeksi: 20.5.2026</small></p>
            <h3>REKISTERINPITÄJÄ</h3>
            <p>JariCode<br>Sähköposti: jaricode@elisanet.fi<br>Kotisivu: <a href="https://www.jaricode.fi" target="_blank" rel="noopener">www.jaricode.fi</a></p>

            <h3>REKISTERIN NIMI</h3>
            <p>Zombie To-Do – käyttäjärekisteri</p>

            <h3>HENKILÖTIETOJEN KÄSITTELYN TARKOITUS</h3>
            <p>Henkilötietoja käsitellään Zombie To-Do -palvelun tarjoamiseksi, käyttäjien tunnistamiseksi, kirjautumisen mahdollistamiseksi sekä palvelun turvallisuuden varmistamiseksi.</p>

            <h3>KÄSITTELYN OIKEUSPERUSTE</h3>
            <p>Henkilötietojen käsittely perustuu EU:n yleisen tietosuoja-asetuksen (GDPR) artiklan 6(1)(b) mukaiseen sopimuksen täytäntöönpanoon: käsittely on tarpeen palvelun tarjoamiseksi käyttäjälle rekisteröitymisen yhteydessä syntyvän käyttösopimuksen perusteella. Lokitietojen käsittely perustuu artiklan 6(1)(f) mukaiseen oikeutettuun etuun palvelun turvallisuuden varmistamiseksi.</p>

            <h3>KÄSITELTÄVÄT HENKILÖTIEDOT</h3>
            <p>
                – Käyttäjänimi<br>
                – Sähköpostiosoite<br>
                – Salasana (tallennetaan ainoastaan salattuna)<br>
                – IP-osoite (tallennetaan lokitapahtumien yhteydessä)<br>
                – Rekisteröitymispäivämäärä<br>
                – Kirjautumis- ja käyttölogit<br>
                – Käyttäjän luomat tehtävät
            </p>

            <h3>EVÄSTEET JA TUNNISTAUTUMINEN</h3>
            <p>Palvelu käyttää evästeitä kirjautumisen ja suojatun käytön mahdollistamiseksi. Evästeitä käytetään istunnon ylläpitämiseen, käyttäjän tunnistamiseen ja palvelun turvallisuuden varmistamiseen. Evästeet ovat välttämättömiä palvelun toiminnalle eikä niitä käytetä seurantaan tai mainontaan.</p>

            <h3>TIETOJEN LUOVUTUS JA SIIRTO</h3>
            <p>Tietoja ei luovuteta kolmansille osapuolille eikä siirretä EU-/ETA-alueen ulkopuolelle.</p>

            <h3>TIETOJEN SÄILYTYSAIKA</h3>
            <p>Käyttäjätilin tiedot, tehtävät ja salasana poistetaan välittömästi tilin poistamisen yhteydessä. Lokimerkinnät (käyttäjänimi, IP-osoite ja tapahtumatyyppi) säilytetään enintään 12 kuukautta tilin poistamisen jälkeen palvelun turvallisuuden ja väärinkäytösten selvittämisen vuoksi, minkä jälkeen ne poistetaan automaattisesti.</p>

            <h3>REKISTERIN SUOJAUS</h3>
            <p>Kaikki tiedot tallennetaan suojattuun tietokantaan ja käyttö edellyttää kirjautumista. Salasanat tallennetaan salattuna eikä niitä voi palauttaa selkokielisiksi. Kaikki yhteydet palveluun on suojattu HTTPS-salauksella.</p>

            <h3>REKISTERÖIDYN OIKEUDET</h3>
            <p>
                – Oikeus tarkastaa omat tiedot<br>
                – Oikeus pyytää tietojen oikaisua<br>
                – Oikeus pyytää tietojen poistamista<br>
                – Oikeus rajoittaa käsittelyä<br>
                – Oikeus siirtää tiedot toiseen palveluun (tietojen siirrettävyys)<br>
                – Oikeus tehdä valitus valvontaviranomaiselle
            </p>

            <h3>VALVONTAVIRANOMAINEN</h3>
            <p>Tietosuojavaltuutetun toimisto<br>Käyntiosoite: Lintulahdenkuja 4, 00530 Helsinki<br>Postiosoite: PL 800, 00531 Helsinki<br>Sähköposti: tietosuoja@tietosuoja.fi<br>Puhelin: 029 566 6700<br>Verkkosivu: <a href="https://tietosuoja.fi" target="_blank" rel="noopener">tietosuoja.fi</a></p>

            <h3>TIETOPYYNNÖT</h3>
            <p>Tietopyynnöt tulee lähettää sähköpostitse osoitteeseen: jaricode@elisanet.fi. Pyyntöihin vastataan kuukauden kuluessa.</p>

        </div>
        <div class="legal-footer">
            <button class="legal-close-btn">SULJE 🔒</button>
        </div>
    </div>
</div>
<!--JavaScriptit. Lomakkeiden toiminnallisuudet-->
<script src="assets/js/ui.js"></script>
</body>
</html>
