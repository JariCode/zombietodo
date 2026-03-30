<?php
// ================================
// db.php
//
// Tämä tiedosto yhdistää sovelluksen tietokantaan.
// Siellä säilytetään
// käyttäjät, tehtävät ja kirjautumistapahtumat.
//
// Tämä tiedosto myös:
// - Lukee salasanat .env-tiedostosta eikä kirjoita
//   niitä suoraan koodiin
// - Luo tietokantataulut automaattisesti jos niitä
//   ei vielä ole olemassa
// - Huolehtii että vanhat lokimerkinnät siivotaan
//   pois silloin tällöin
// - Sisältää apufunktiot istunnon tarkistukseen
//   ja turvatokenien luontiin
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
// LUODAAN TIETOKANTATAULUT JOS NIITÄ EI OLE
// ===========================================================

// Käyttäjätaulu — tallentaa rekisteröityneet käyttäjät
$conn->query("
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,          -- Käyttäjätunniste, joka kasvaa automaattisesti
    username VARCHAR(50) NOT NULL,              -- Käyttäjätunnus, joka ei saa olla tyhjä. Maksimi 50 merkkiä.
    email VARCHAR(100) NOT NULL UNIQUE,         -- Sähköpostiosoite, uniikki — ei saa olla sama kuin toisella käyttäjällä
    password VARCHAR(255) NOT NULL,             -- Salasana, tallennetaan hashattuna. Maksimi 255 merkkiä.
    role ENUM('user','admin') NOT NULL DEFAULT 'user', -- Käyttäjätaso, oletuksena 'user'
    login_attempts INT NOT NULL DEFAULT 0,      -- Väärät kirjautumisyritykset — nollataan onnistuneen kirjautumisen jälkeen
    login_locked_until DATETIME NULL,           -- Kirjautumislukituksen päättymisaika — NULL tarkoittaa ei lukittu
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Käyttäjätiedot luotiin, asetetaan automaattisesti nykyhetkeen
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP -- Käyttäjätiedot päivitettiin, asetetaan automaattisesti nykyhetkeen
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Tehtävätaulu — tallentaa käyttäjien tehtävät
$conn->query("
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,          -- Jokaiselle tehtävälle oma numero, kasvaa automaattisesti
    user_id INT NOT NULL,                       -- Minkä käyttäjän tehtävä on, pakollinen
    text VARCHAR(255) NOT NULL,                 -- Tehtävän teksti, maksimissaan 255 merkkiä, pakollinen
    status ENUM('not_started','in_progress','done') NOT NULL DEFAULT 'not_started', -- Tehtävän tila, oletuksena ei aloitettu
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Milloin tehtävä luotiin, täyttyy automaattisesti
    started_at DATETIME NULL,                   -- Milloin tehtävä aloitettiin, täytetään kun tila muuttuu käynnissä olevaksi
    done_at DATETIME NULL,                      -- Milloin tehtävä valmistui, täytetään kun tila muuttuu valmiiksi
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE -- Jos käyttäjä poistetaan, poistetaan myös kaikki hänen tehtävänsä
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Lokitaulu — tallentaa käyttäjien tapahtumat
$conn->query("
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,          -- Jokaiselle lokimerkinnälle oma numero, kasvaa automaattisesti
    user_id INT NOT NULL,                       -- Minkä käyttäjän tapahtuma on, pakollinen
    event ENUM(
        'register',                             -- Käyttäjä rekisteröityi
        'login',                                -- Käyttäjä kirjautui sisään
        'logout',                               -- Käyttäjä kirjautui ulos
        'account_updated',                      -- Käyttäjä päivitti tilitietojaan
        'account_deleted_user',                 -- Käyttäjä poisti oman tilinsä
        'password_reset_requested',             -- Käyttäjä pyysi salasanan palautusta
        'password_reset_completed',             -- Käyttäjä vaihtoi salasanan palautuslinkin kautta
        'account_deleted_admin',                -- Admin poisti käyttäjän tilin
        'role_changed'                          -- Admin vaihtoi käyttäjän roolia
    ) NOT NULL,
    ip_address VARCHAR(45) NULL,                -- Käyttäjän IP-osoite tapahtuman hetkellä
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP, -- Milloin tapahtuma tapahtui, täyttyy automaattisesti
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE -- Jos käyttäjä poistetaan, poistetaan myös hänen lokimerkintänsä
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Salasanan palautustaulu — tallentaa salasanan palautuspyynnöt
$conn->query("
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,          -- Jokaiselle palautuspyynnölle oma numero, kasvaa automaattisesti
    user_id INT NOT NULL,                       -- Minkä käyttäjän palautuspyyntö on, pakollinen
    token CHAR(64) NOT NULL,                    -- Satunnainen 64 merkkiä pitkä avain joka lähetetään sähköpostiin
    expires_at DATETIME NOT NULL,               -- Milloin palautuslinkki vanhenee, tunnin kuluttua luomisesta
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE -- Jos käyttäjä poistetaan, poistetaan myös hänen palautuspyyntönsä
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ===========================================================
// VANHOJEN LOKIMERKINTÖJEN AUTOMAATTINEN SIIVOUS
// ===========================================================
if (rand(1, 10) === 1) { // 10% todennäköisyydellä tapahtuva siivous joka poistaa vanhentuneet lokimerkinnät
    $conn->query("DELETE FROM logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL 12 MONTH)"); // Poistetaan yli 12 kuukautta vanhat lokimerkinnät
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