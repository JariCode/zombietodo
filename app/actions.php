<?php
// ================================
// actions.php
//
// Tämä tiedosto hoitaa kaikki toiminnot
// mitä käyttäjä voi tehdä sovelluksessa.
//
// Esimerkiksi:
// - Rekisteröityminen ja kirjautuminen
// - Uloskirjautuminen
// - Profiilin päivitys ja poisto
// - Salasanan vaihto
// - Tehtävien lisäys, muokkaus ja poisto
// - Tehtävän tilan muutos (ei aloitettu,
//   käynnissä, valmis)
//
// Käyttäjä ei avaa tätä tiedostoa suoraan
// selaimessa — lomakkeet ja napit lähettävät
// tänne tietoa taustalla.
// ================================

require_once __DIR__ . '/session-config.php'; // Istuntoasetukset. ENSIN ennen kaikkea muuta
require_once __DIR__ . '/db.php';             // Tietokantayhteys $conn-muuttujaan

// Luetaan mitä toimintoa pyydetään
// Lomakkeet lähettävät sen piilokenttänä: $_POST['action']
// Tehtävänapit lähettävät sen osoitteen lopussa: ?action=start
$action = $_POST['action'] ?? $_GET['action'] ?? null; // Jos kumpikaan ei löydy, $action saa arvon null

// Tarkistetaan istunnon vanheneminen — vain jos käyttäjä on kirjautunut sisään
if (isset($_SESSION['user_id'])) { // isset tarkistaa onko user_id tallennettu istuntoon eli onko kirjauduttu
    if (!validateSessionTimeout()) { // validateSessionTimeout() on db.php:ssä.Palauttaa false jos tunti kulunut viimeisestä toiminnasta ja istunto vanhentunut
        $_SESSION['error'] = 'Istunto on vanhentunut. Kirjaudu uudelleen.'; // Tallennetaan virheilmoitus istuntoon
        header('Location: ../index.php'); // Ohjataan kirjautumissivulle — ../  tarkoittaa yksi kansio ylöspäin
        exit; // Pysäytetään koodin suoritus heti — ilman tätä PHP jatkaisi eteenpäin
    }
}

// Katsotaan mitä toimintoa pyydettiin ja kutsutaan oikeaa funktiota
switch ($action) { // switch on kuin monta if-else:ä peräkkäin — siistimpi tapa
    case 'register': handleRegister(); break; // Jos action on 'register', kutsutaan rekisteröintifunktiota
    case 'login':    handleLogin();    break; // Jos action on 'login', kutsutaan kirjautumisfunktiota
    default:                                  // Jos action on jotain muuta tai null
        header('Location: ../index.php');     // Ohjataan etusivulle — ei tehdä mitään
        exit; // Pysäytetään suoritus
}