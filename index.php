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
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8"> <!-- Merkistö — tukee suomen kielen merkkejä -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Skaalautuu eri laitteille -->
    <title>Zombie To-Do</title>
    <meta name="description" content="Zombie To-Do — selviä apokalypsistä hallitsemalla tehtäväsi. Kirjaudu sisään ja pidä zombit loitolla."> <!-- Selaimen ja hakukoneiden kuvausteksti -->
    <link rel="icon" type="image/png" href="assets/img/favicon.png "> <!-- Selaimen välilehden ikoni -->
    <link rel="stylesheet" href="style.css"> <!-- Sovelluksen omat tyylit -->
</head>
<body>
    <!-- Veriantimaatio — valuu sivun yläreunasta ja häviää -->
    <div class="blood"></div>

    <!--Pääkontaineri joka sisältää kaikki sivun elementit ja toiminnallisuudet.-->
    <div class="container">

    <!--Herokuva — suuri kuva joka esittelee sovelluksen teeman ja tunnelman.-->
    <img src="assets/img/Herokuva.webp" class="hero" alt="Zombie To-Do" fetchpriority="high">

    <!-- WIP-banneri — näkyy sivun yläosassa kun sovellus on kehitysvaiheessa -->
    <div class="wip-banner">🧠 WORK IN PROGRESS… BRAINS LOADING 🩸</div>

</div>
</body>
</html>