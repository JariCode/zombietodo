<?php
// ================================
// session-config.php
//
// Istunto tarkoittaa että palvelin muistaa
// kuka olet kun liikut sivulta toiselle.
// Esimerkiksi että olet kirjautunut sisään.
//
// Tämä tiedosto pitää ladata ENNEN kuin
// istunto käynnistetään — siksi se on omana
// tiedostonaan ja ladataan aina ensimmäisenä.
//
// Turvallisuusasetukset lyhyesti:
// - Istunto toimii vain evästeillä, ei osoitepalkissa
// - Eväste ei näy selaimen JavaScriptille
// - Istunto vanhenee tunnin kuluttua
// - Suojaus ulkopuolisille sivuille tehtyjä huijauksia vastaan
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
    session_name('sid');  // Vaihdetaan istuntoevästeen oletusnimi PHPSESSID -> sid. Ei paljasta suoraan että kyseessä on PHP-sovellus
}