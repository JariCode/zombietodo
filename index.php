<?php
// ================================
// index.php
//
// Tämä on sovelluksen pääsivu — ensimmäinen
// sivu jonka käyttäjä näkee.
//
// Tällä sivulla on:
// - Kirjautumislomake
// - Rekisteröitymislomake
// - Linkki unohtuneeseen salasanaan
// - Käyttöehtojen ja tietosuojaselosteen
//   hyväksyminen rekisteröityessä
//
// Jos käyttäjä on jo kirjautunut sisään,
// hänet ohjataan suoraan tehtävälistalle
// eikä kirjautumissivua näytetä ollenkaan.
//
// Istunto vanhenee tunnin kuluttua jolloin
// käyttäjä ohjataan takaisin kirjautumaan.
// Sisäänpääsy ei siis säily ikuisesti.
// ================================