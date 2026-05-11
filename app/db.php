<?php
// ================================
// db.php — tietokantayhteyden muodostaminen ja yleiset tietokanta-asetukset
// ================================

// ===========================================================
// LADATAAN .ENV TIEDOSTO
// ===========================================================
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); //Tietokantavirheet näytetään virheilmoituksina
$envFile = __DIR__ . '/.env'; //Ladataan .env tiedosto app kansiosta jossa tietokantayhteyden tiedot
if (file_exists($envFile)) { //Tarkistetaan että .env tiedosto löytyy
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) { // Silmukka joka käy läpi .env tiedoston rivit, ohittaa tyhjät ja kommenttirivit
        $line = trim($line);// Poistaa rivin alusta ja lopusta mahdolliset välilyönnit
        if ($line === "" || str_starts_with($line, '#')) continue; // Ohitetaan tyhjät rivit ja kommenttirivit
        if (!str_contains($line, '=')) continue; // Jos rivi ei sisällä '=' merkkiä, ohitetaan se
        list($key, $value) = explode('=', $line, 2); // = merkin kohdalla rivi jaetaan kahteen, vasen osa on avain ja oikea osa on arvo
        $value = trim($value, "\"' "); // Poistaa arvosta mahdolliset lainausmerkit
        $_ENV[$key] = $value; // Tallennetaan .env tiedoston arvot muistiin muuttujana $_ENV
    }
}

// ===========================================================
// TIETOKANTA-ASETUKSET
// ===========================================================
$host   = $_ENV["DB_HOST"]; //Tietokantapalvelimen osoite .env tiedostosta
$user   = $_ENV["DB_USER"]; //Tietokantakäyttäjätunnus .env tiedostosta
$pass   = $_ENV["DB_PASS"]; //Tietokantakäyttäjätunnuksen salasana .env tiedostosta
$dbname = $_ENV["DB_NAME"]; //Tietokannan nimi .env tiedostosta
$port   = $_ENV["DB_PORT"]; //Tietokantaportti .env tiedostosta

// ===========================================================
// YHDISTETÄÄN TIETOKANTAAN
// ===========================================================
$conn = new mysqli($host, $user, $pass, $dbname, $port); //Luodaan uusi mysqli yhteys tietokantaan
if ($conn->connect_error) { //Tarkistetaan että yhteys onnistui
    error_log("Tietokantayhteyden muodostaminen epäonnistui: " . $conn->connect_error); //Kirjataan virheilmoitus lokiin
    http_response_code(500); //Näytetään käyttäjälle 500 Internal Server Error virhekoodi
    die('Palvelussa on tapahtunut virhe. Yritä myöhemmin uudestaan.'); //Die katkaisee koodin suorituksen ja näyttää käyttäjälle virheilmoituksen
}

// ===========================================================
// ASETETAAN MERKISTÖ JA KIELI
// ===========================================================
$conn->set_charset('utf8mb4'); //Asetetaan tietokannan merkistö UTF-8:ksi, joka tukee kaikkia merkkejä ja emojeita

// ===========================================================
// VANHOJEN LOKIMERKINTÖJEN AUTOMAATTINEN SIIVOUS
// ===========================================================
if (rand(1, 10) === 1) { // 10% todennäköisyydellä tapahtuva siivous joka poistaa vanhentuneet lokimerkinnät
    try {
        $conn->query("DELETE FROM logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL 12 MONTH)"); // Poistetaan yli 12 kuukautta vanhat lokimerkinnät
    } catch (Exception $e) {
        // Ohitetaan hiljaisesti — taulu ei ehkä ole vielä luotu install.php:llä
    }
}

// ===========================================================
// ISTUNNON VANHENEMISEN TARKISTUS
// ===========================================================
function validateSessionTimeout() { // Tarkistetaan onko käyttäjän istunto vanhentunut. Jos on, kirjaudutaan ulos automaattisesti.
    $timeout = 3600; // Istunto vanhenee tunnin kuluttua (3600 sekuntia)

    // Tarkistetaan onko viime toiminnosta kulunut yli tunti
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();   // Tyhjennetään kaikki istunnon tiedot muistista
        session_destroy(); // Tuhotaan istunto kokonaan palvelimelta
        return false;      // Palautetaan false — istunto on vanhentunut
    }

    $_SESSION['last_activity'] = time(); // Päivitetään viimeisen toiminnon aika
    return true; // Palautetaan true — istunto on vielä voimassa
}

// ===========================================================
// CSRF-SUOJAUS
// ===========================================================
function generateCSRFToken(bool $regenerate = false) { // Luodaan uusi CSRF-token tai palautetaan olemassa oleva
    if ($regenerate || empty($_SESSION['csrf_token'])) { // Jos tokenia ei ole tai halutaan luoda uusi satunnainen token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Luodaan 64 merkkiä pitkä satunnainen token ja tallennetaan se istuntoon
    }
    return $_SESSION['csrf_token']; // Token palautetaan, jotta se voidaan lisätä lomakkeisiin ja tarkistaa myöhemmin pyyntöjen yhteydessä
}

function verifyCSRFToken($token) { // Tarkistetaan että lomakkeesta lähetetty token vastaa istunnossa olevaa tokenia
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? ''); // Jos täsmää, pyyntö on turvallinen
}
