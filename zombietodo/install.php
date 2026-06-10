<?php
// ================================
// install.php
//
// Tämä tiedosto luo tietokantataulut
// sovelluksen asennuksen yhteydessä.
//
// Ajetaan kerran selaimessa:
// sivusto.fi/install.php
//
// TÄRKEÄÄ: Poista tämä tiedosto palvelimelta
// asennuksen jälkeen. Jos tiedosto jää
// palvelimelle, kuka tahansa voi avata sen
// selaimessa.
// ================================
// Etsitään zombie-config-kansio — tarkistetaan ensin yksi taso ylös, sitten kaksi
// Paikallisesti kansio on yhden tason päässä, palvelimella kahden
$cfgDir = is_dir(dirname(__DIR__) . '/zombie-config')
    ? dirname(__DIR__) . '/zombie-config'
    : dirname(dirname(__DIR__)) . '/zombie-config';
require_once $cfgDir . '/session-config.php'; // Istuntoasetukset ENSIN
require_once $cfgDir . '/db.php';             // Tietokantayhteys

// Tarkistetaan onko taulut jo luotu
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows > 0) {
    die('Taulut on jo luotu. Poista install.php palvelimelta.');
}

// ===========================================================
// LUODAAN TIETOKANTATAULUT
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
    admin_locked TINYINT(1) NOT NULL DEFAULT 0,  -- Adminin asettama lukitus: 0 = ei lukittu, 1 = lukittu. Estää kirjautumisen kunnes admin avaa.
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
    user_id INT NULL,                           -- Kuka teki toiminnon, NULL jos käyttäjä poistettu
    username VARCHAR(50) NULL,                  -- Käyttäjänimi tapahtuman hetkellä — säilyy vaikka käyttäjä poistetaan
    event ENUM(
        'register',                             -- Käyttäjä rekisteröityi
        'login',                                -- Käyttäjä kirjautui sisään
        'logout',                               -- Käyttäjä kirjautui ulos
        'account_updated',                      -- Käyttäjä päivitti tilitietojaan
        'password_changed',                     -- Käyttäjä vaihtoi salasanansa profiilisivulta
        'account_deleted_user',                 -- Käyttäjä poisti oman tilinsä
        'password_reset_requested',             -- Käyttäjä pyysi salasanan palautusta
        'password_reset_completed',             -- Käyttäjä vaihtoi salasanan palautuslinkin kautta
        'account_locked_admin',                 -- Admin lukitsi käyttäjän tilin
        'account_unlocked_admin',               -- Admin avasi lukituksen käyttäjän tililtä
        'account_deleted_admin',                -- Admin poisti käyttäjän tilin
        'role_changed'                          -- Admin vaihtoi käyttäjän roolia
    ) NOT NULL,
    ip_address VARCHAR(45) NULL,                -- Käyttäjän IP-osoite tapahtuman hetkellä
    target_user_id INT NULL,                    -- Ketä toiminto koski admin-toiminnoissa, NULL jos ei kohdistunut kehenkään
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP, -- Milloin tapahtuma tapahtui, täyttyy automaattisesti
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,   -- Jos käyttäjä poistetaan, user_id muuttuu NULL:ksi mutta lokimerkintä säilyy
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL -- Jos kohdehenkilö poistetaan, target_user_id muuttuu NULL:ksi mutta lokimerkintä säilyy
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

// Ilmoitetaan onnistumisesta
echo 'Taulut luotu onnistuneesti. Poista tämä tiedosto palvelimelta.';
