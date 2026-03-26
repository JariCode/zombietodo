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

    <!-- Pääotsikko-->
    <h1>ZOMBIE LOGIN</h1>

    <!-- Kirjautumislomake -->
    <div class="auth-box">
        <h2 class="auth-title">Kirjaudu sisään</h2>
        <form method="POST" action="app/actions.php?action=login" autocomplete="off">
            <label>Sähköposti</label>
            <input type="email" name="email" placeholder="example@domain.com" required autocomplete="off">
            <label>Salasana</label>
            <div class="password-field">
                <input type="password" name="password" placeholder="********" required autocomplete="off">
                <button type="button" class="password-eye" aria-label="Näytä salasana">👁️</button>
            </div>
            <button type="submit">Kirjaudu sisään 🔑</button>
            <a href="#" class="forgot-link">Vai unohtuiko salasanasi? 🧟</a>
        </form>
    </div>

    <!--Lomakkeiden väliin erotin teksti-->
    <div class="auth-separator">TAI LUO TILI</div>

    <!-- Rekisteröitymislomake -->
     <div class="auth-box">
        <h2 class="auth-title">Rekisteröidy</h2>
        <form method="POST" action="app/actions.php?action=register" autocomplete="off">
            <label>Käyttäjänimi</label>
            <input type="text" name="username" placeholder="ZombieMaster91" required autocomplete="off">
            <label>Sähköposti</label>
            <input type="email" name="email" placeholder="example@domain.com" required autocomplete="off">
            <label>Salasana</label>
            <div class="password-field">
                <input type="password" name="password" placeholder="********" required autocomplete="off">
                <button type="button" class="password-eye" aria-label="Näytä salasana">👁️</button>
            </div>
            <label>Toista salasana</label>
            <div class="password-field">
                <input type="password" name="password_confirm" placeholder="********" required autocomplete="off">
                <button type="button" class="password-eye" aria-label="Näytä salasana">👁️</button>
            </div>
            <div class="checkbox-wrapper">
                <label for="acceptPrivacyPolicy" class="checkbox-label">
                    <input type="checkbox" id="acceptPrivacyPolicy" name="terms" required>
                    Hyväksyn
                    <button type="button" class="link-btn" onclick="openLegalModal('terms')">käyttöehdot</button> ja
                    <button type="button" class="link-btn" onclick="openLegalModal('privacy')">tietosuojaselosteen</button>.
                </label>
            </div>
            <button type="submit">Rekisteröidy 🧟‍♂️</button>
        </form>
    </div>
</div>
</body>
</html>