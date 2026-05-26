<?php
// ================================
// session-config.php — istuntoasetukset tietokantayhteydelle ja HTTP-turvaheaderit
// ================================

// Tarkistetaan onko istunto käynnissä
// Jos ei ole, tehdään seuraavat asetukset
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', 1); // Hyväksytään vain itse luodut istunnot. Ulkopuoliset istunnot hylätään.
    ini_set('session.use_only_cookies', 1); // Istunto kulkee vain evästeillä, ei näy URL-osoitteessa
    ini_set('session.cookie_httponly', 1); // Eväste ei näy JavaScriptille, suojaa XSS-hyökkäyksiltä
    ini_set('session.cookie_secure', getenv('APP_ENV') === 'production' ? 1 : 0); // Jos ollaan tuotantopalvelimella, laita eväste vain HTTPS-yhteyden yli. Jos ollaan omalla koneella kehittämässä, ei pakoteta HTTPS:ää.
    ini_set('session.cookie_samesite', 'Strict'); // Lähetä eväste vain jos pyyntö tulee samalta sivustolta — ei koskaan ulkopuolelta
    ini_set('session.gc_maxlifetime', 3600); // Istunto vanhenee tunnin kuluttua
    ini_set('session.use_trans_sid', 0); //Estetään istuntotunnuksen näkyminen osoitepalkissa
    ini_set('session.cookie_lifetime', 0); // Eväste vanhenee, kun selain suljetaan
    ini_set('session.sid_length', 48); // Istuntotunnus on 48 merkkiä pitkä, vaikeampi arvattava. Oletuksena 26 merkkiä, joka on helpompi arvattava.
    session_name('Bub');  // Vaihdetaan istuntoevästeen oletusnimi PHPSESSID -> Bub. Zombie teemaan sopiva nimi.
    session_start(); // Käynnistetään istunto. Tämä on pakollista, jotta istunto toimii ja käyttäjätiedot säilyvät sivujen välillä.

}
