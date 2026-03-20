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