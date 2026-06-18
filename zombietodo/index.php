<?php
// ================================
// index.php — pääsivu, joka sisältää kirjautumis- ja rekisteröitymislomakkeet
// ================================
// Etsitään zombie-config-kansio — tarkistetaan ensin yksi taso ylös, sitten kaksi
// Paikallisesti kansio on yhden tason päässä, palvelimella kahden
$cfgDir = is_dir(dirname(__DIR__) . '/zombie-config')
    ? dirname(__DIR__) . '/zombie-config'
    : dirname(dirname(__DIR__)) . '/zombie-config';
require_once $cfgDir . '/session-config.php'; // Istuntoasetukset ENSIN
require_once $cfgDir . '/db.php';             // Tietokantayhteys

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
    <link rel="canonical" href="https://jaricode.fi/zombietodo/"> <!-- Hakukoneille sivun virallinen URL -->
    <link rel="icon" type="image/png" href="assets/img/favicon.png"> <!-- Selaimen välilehden ikoni -->
    <link rel="stylesheet" href="assets/css/style.css"> <!-- Sovelluksen omat tyylit -->
</head>
<body>
    <!-- Veriantimaatio — valuu sivun yläreunasta ja häviää -->
    <div class="blood"></div>
    <!--Pääkontaineri joka sisältää kaikki sivun elementit ja toiminnallisuudet.-->
    <div class="container no-caret" >

        <!--Herokuva — suuri kuva joka esittelee sovelluksen teeman ja tunnelman.-->
        <img src="assets/img/Herokuva.webp" class="hero" alt="Zombie To-Do" width="1200" height="630"fetchpriority="high">

        <!-- WIP-banneri — näkyy sivun yläosassa mihin voi vaihtaa sopivan tekstin -->
        <div class="wip-banner">🩸 THE UNDEAD ARE ORGANIZING TASKS... 🩸</div>

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
                <button type="button" class="forgot-link" id="openResetModal">Vai unohtuiko salasanasi? 🧟</button>
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
                <div class="checkbox-wrapper no-caret">
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

<<!--SALASANAN PALAUTUS — Modal-->
<div class="legal-overlay no-caret" id="resetModal" role="dialog" aria-modal="true" aria-labelledby="resetModalTitle">
    <div class="legal-modal">
        <div class="legal-header">
            <h2 id="resetModalTitle">SALASANAN PALAUTUS 🧟</h2>
            <button class="legal-close" aria-label="Sulje">✕</button>
        </div>
        <div class="legal-body">
            <p>Syötä käyttäjänimesi ja sähköpostiosoitteesi. Jos tiedot täsmäävät, lähetämme palautuslinkin sähköpostiisi.</p>

            <form action="app/actions.php" method="POST">
                <input type="hidden" name="action" value="request_password_reset">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">

                <label for="resetUsername">Käyttäjänimi</label>
                <input type="text" id="resetUsername" name="username" required autocomplete="off" placeholder="ZombieMaster91">

                <label for="resetEmailInput">Sähköposti</label>
                <input type="email" id="resetEmailInput" name="email" required autocomplete="off" placeholder="example@domain.com">

                <button type="submit" class="legal-close-btn">LÄHETÄ LINKKI 📧</button>
            </form>
        </div>
    </div>
</div>

<!-- KÄYTTÖEHDOT — Modal -->
<div class="legal-overlay no-caret" id="legalTerms" role="dialog" aria-modal="true" aria-labelledby="legalTermsTitle">
    <div class="legal-modal">
        <div class="legal-header">
            <h2 id="legalTermsTitle">KÄYTTÖEHDOT 📜</h2>
            <button class="legal-close" aria-label="Sulje">✕</button>
        </div>
        <div class="legal-body">

            <p><small>Päivitetty viimeksi: 15.6.2026</small></p>

            <h3>1. YLEISTÄ</h3>
            <p>Zombie To-Do -palvelun käyttö edellyttää näiden käyttöehtojen hyväksymistä. Palvelun käyttäminen tarkoittaa, että käyttäjä sitoutuu noudattamaan näitä ehtoja.</p>

            <h3>2. KÄYTTÄJÄTILI</h3>
            <p>Käyttäjän tulee olla vähintään 13-vuotias (tietosuojalaki 1050/2018 § 5). Käyttäjän tulee rekisteröityä luodakseen henkilökohtaisen käyttäjätilin. Käyttäjätunnus ja salasana ovat henkilökohtaisia, eikä niitä tule luovuttaa muille. Käyttäjä vastaa kaikesta tilinsä kautta tapahtuvasta toiminnasta ja pitää tietonsa ajantasaisina profiilisivun kautta.</p>

            <h3>3. SISÄLLÖN KÄYTTÖ</h3>
            <p>Käyttäjä omistaa itse luomansa sisällön (tehtävät ja merkinnät) ja saa vapaasti kopioida, viedä tai käyttää sitä haluamallaan tavalla. Palvelun ulkoasu, koodi ja muu sovelluksen oma sisältö on suojattu tekijänoikeuksin, eikä niitä saa kopioida tai hyödyntää kaupallisesti ilman lupaa.</p>

            <h3>4. HENKILÖTIETOJEN KÄSITTELY</h3>
            <p>Rekisteröitymisen yhteydessä käyttäjä hyväksyy Zombie To-Do -palvelun tietosuojaselosteen. Tietosuojaseloste määrittelee, mitä tietoja kerätään ja miten niitä käsitellään EU:n yleisen tietosuoja-asetuksen (2016/679) mukaisesti.</p>

            <h3>5. PALVELUN KÄYTTÖ</h3>
            <p>Palvelua tulee käyttää lain ja hyvän tavan mukaisesti. Häiritsevä, haitallinen, laiton tai muulla tavoin sopimaton toiminta voi johtaa käyttäjätilin lukitsemiseen tai poistamiseen ilman erillistä varoitusta.</p>

            <h3>6. VASTUUNRAJOITUS</h3>
            <p>Palvelu tarjotaan "sellaisena kuin se on" ilman takuita saatavuudesta, virheettömyydestä tai keskeytymättömyydestä. Ylläpitäjä ei vastaa tietojen katoamisesta, palvelun käyttökatkoista tai muista vahingoista, jotka aiheutuvat palvelun käytöstä. Kuluttajan pakottavaan lainsäädäntöön perustuvat oikeudet pysyvät voimassa.</p>

            <h3>7. SOVELLETTAVA LAKI JA ERIMIELISYYDET</h3>
            <p>Näihin käyttöehtoihin sovelletaan Suomen lakia. Erimielisyydet pyritään ratkaisemaan ensisijaisesti neuvottelemalla. Riidat ratkaistaan Itä-Uudenmaan käräjäoikeudessa tai kuluttaja-asemassa olevan käyttäjän kotipaikan käräjäoikeudessa. Kuluttaja voi saattaa asian myös kuluttajariitalautakunnan ratkaistavaksi (kuluttajariita.fi).</p>

            <h3>8. MUUTOKSET EHTOIHIN</h3>
            <p>Palvelun ylläpitäjä voi päivittää käyttöehtoja tarvittaessa. Muutokset astuvat voimaan kun ne julkaistaan palvelussa. Voimassa oleva versio on aina nähtävillä palvelun etusivulla.</p>

            <h3>9. YHTEYSTIEDOT</h3>
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
<div class="legal-overlay no-caret" id="legalPrivacy" role="dialog" aria-modal="true" aria-labelledby="legalPrivacyTitle">
    <div class="legal-modal">
        <div class="legal-header">
            <h2 id="legalPrivacyTitle">Tietosuojaseloste 🔒</h2>
            <button class="legal-close" aria-label="Sulje">✕</button>
        </div>
        <div class="legal-body">
            <p><small>Päivitetty viimeksi: 15.6.2026</small></p>

            <h3>1. REKISTERINPITÄJÄ</h3>
            <p>JariCode<br>Sähköposti: jaricode@elisanet.fi<br>Kotisivu: <a href="https://jaricode.fi" target="_blank" rel="noopener">jaricode.fi</a></p>
            
            <h3>2. REKISTERIN NIMI</h3>
            <p>Zombie To-Do – käyttäjärekisteri</p>

            <h3>3. KÄSITTELYN TARKOITUS JA OIKEUSPERUSTE</h3>
            <p>Henkilötietoja käsitellään Zombie To-Do -palvelun tarjoamiseksi, käyttäjien tunnistamiseksi, kirjautumisen mahdollistamiseksi sekä palvelun turvallisuuden varmistamiseksi.</p>
            <p>Käsittely perustuu EU:n yleisen tietosuoja-asetuksen artiklan 6(1)(b) mukaiseen sopimuksen täytäntöönpanoon, joka syntyy käyttäjän rekisteröityessä palveluun. Lokitietojen käsittely perustuu artiklan 6(1)(f) mukaiseen oikeutettuun etuun palvelun turvallisuuden varmistamiseksi.</p>
            <p>Palvelu on tarkoitettu vähintään 13-vuotiaille käyttäjille (tietosuojalaki 1050/2018 § 5).</p>

            <h3>4. KÄSITELTÄVÄT HENKILÖTIEDOT</h3>
            <p>
                – Käyttäjänimi<br>
                – Sähköpostiosoite<br>
                – Salasana (tallennetaan ainoastaan suojatussa muodossa eikä sitä voi palauttaa selkokielisenä)<br>
                – Käyttäjän rooli (peruskäyttäjä tai ylläpitäjä)<br>
                – Tilin lukitustila<br>
                – Kirjautumisyritysten määrä ja mahdollinen lukitusaika<br>
                – Tilin luonti- ja päivityspäivä<br>
                – IP-osoite (lokitapahtumien yhteydessä)<br>
                – Lokitiedot kirjautumisesta ja sovelluksen toiminnoista<br>
                – Käyttäjän luomat tehtävät teksteineen ja aikaleimoineen<br>
                – Salasanan palautuspyynnöt palautuksen keston ajan
            </p>

            <h3>5. EVÄSTEET</h3>
            <p>Palvelu käyttää yhtä välttämätöntä istuntoevästettä, jonka avulla käyttäjä pysyy kirjautuneena ja palvelun turvallinen käyttö mahdollistuu. Eväste ei sisällä henkilötietoja vaan ainoastaan satunnaisen istuntotunnisteen.</p>
            <p>Eväste on välttämätön palvelun toiminnalle, joten sähköisen viestinnän palveluista annetun lain (917/2014) § 205 mukaan sen käyttö ei vaadi erillistä suostumusta. Palvelu ei käytä evästeitä seurantaan, analytiikkaan eikä mainontaan, eikä sivustolla ole kolmansien osapuolten evästeitä.</p>

            <h3>6. TIETOJEN LUOVUTUS JA SIIRTO</h3>
            <p>Tietoja ei luovuteta kolmansille osapuolille markkinointia, analytiikkaa tai muita rekisterinpitäjän omasta käytöstä riippumattomia tarkoituksia varten.</p>
            <p>Sovelluksen tekninen toteutus edellyttää, että tietoja käsittelee tietojenkäsittelijän roolissa Planeetta Internet Oy (Domainhotelli), joka tarjoaa palvelun käyttämän palvelimen, tietokannan ja sähköpostien lähetyksen salasanan palautuksen yhteydessä. Palvelin sijaitsee Suomessa Helsingissä. Tietoja ei siirretä EU:n tai ETA-alueen ulkopuolelle.</p>

            <h3>7. TIETOJEN SÄILYTYSAIKA</h3>
            <p>Käyttäjätilin tiedot, tehtävät ja salasana poistetaan välittömästi tilin poistamisen yhteydessä.</p>
            <p>Lokimerkinnät (käyttäjänimi, IP-osoite ja tapahtumatyyppi) säilytetään enintään 12 kuukautta tapahtuman jälkeen palvelun turvallisuuden ja väärinkäytösten selvittämisen vuoksi, minkä jälkeen ne poistetaan automaattisesti.</p>
            <p>Salasanan palautuslinkki vanhenee tunnissa ja sitä voi käyttää vain kerran.</p>

            <h3>8. REKISTERIN SUOJAUS</h3>
            <p>Kaikki yhteydet palveluun on suojattu HTTPS-salauksella. Salasanat tallennetaan suojatussa muodossa eikä niitä voi palauttaa selkokielisinä. Rekisterin käsittelyssä noudatetaan huolellisuutta, ja tiedot suojataan asianmukaisesti niin, ettei niihin pääse kukaan ulkopuolinen käsiksi. Henkilötietoja käsittelee ainoastaan rekisterinpitäjä, jota sitoo vaitiolovelvollisuus.</p>

            <h3>9. REKISTERÖIDYN OIKEUDET</h3>
            <p>Käyttäjällä on EU:n yleisen tietosuoja-asetuksen mukaiset oikeudet:</p>
            <p>
                – Oikeus tarkastaa omat tiedot<br>
                – Oikeus pyytää tietojen oikaisua<br>
                – Oikeus pyytää tietojen poistamista<br>
                – Oikeus rajoittaa käsittelyä<br>
                – Oikeus siirtää tiedot toiseen palveluun (tietojen siirrettävyys)<br>
                – Oikeus tehdä valitus valvontaviranomaiselle
            </p>
            <p>Käyttäjä voi tarkastella, muokata ja poistaa omat tietonsa itse palvelun profiilisivulla. Muut pyynnöt käsitellään sähköpostitse, ja niihin vastataan kuukauden kuluessa.</p>

            <h3>10. AUTOMAATTINEN PÄÄTÖKSENTEKO</h3>
            <p>Palvelu ei käytä henkilötietojen automaattista käsittelyä tai profilointia päätöksentekoon, jolla olisi vaikutuksia käyttäjään.</p>

            <h3>11. TIETOPYYNNÖT JA HENKILÖLLISYYDEN VARMISTUS</h3>
            <p>Tietopyynnöt lähetetään sähköpostitse osoitteeseen jaricode@elisanet.fi. Pyynnössä tulee mainita käyttäjänimi ja rekisteröity sähköpostiosoite. Henkilöllisyys varmistetaan tarkistamalla, että annetut tiedot täsmäävät tietokannan tietoihin.</p>
            <p>Pyytäjälle toimitettavat tiedot lähetetään aina rekisteröityyn sähköpostiosoitteeseen, jolloin oikea tilin haltija vastaanottaa tiedot myös tilanteessa, jossa pyyntö olisi tullut väärin perustein. Siirrettävyyspyynnössä tiedot toimitetaan koneluettavassa muodossa.</p>
            <p>Rekisterinpitäjä vastaa tietopyyntöihin sovelluksen tietokantaan tallennetuista tiedoista. Pyynnöt, jotka koskevat palveluntarjoajan käyttämän palvelinympäristön (Planeetta Internet Oy / Domainhotelli) teknisiä lokeja, käyttäjä ohjataan tekemään suoraan Domainhotellille heidän tietosuojakäytäntönsä mukaisesti.</p>

            <h3>12. VALVONTAVIRANOMAINEN</h3>
            <p>Tietosuojavaltuutetun toimisto<br>Käyntiosoite: Lintulahdenkuja 4, 00530 Helsinki<br>Postiosoite: PL 800, 00531 Helsinki<br>Sähköposti: tietosuoja@tietosuoja.fi<br>Puhelin: 029 566 6700<br>Verkkosivu: <a href="https://tietosuoja.fi" target="_blank" rel="noopener">tietosuoja.fi</a></p>

            <h3>13. TIETOSUOJASELOSTEEN MUUTOKSET</h3>
            <p>Rekisterinpitäjä voi päivittää tätä tietosuojaselostetta tarvittaessa. Voimassa oleva versio on aina nähtävillä palvelun etusivulla, ja päivitetty seloste tulee voimaan, kun se on julkaistu palvelussa.</p>

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
