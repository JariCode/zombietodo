<?php
// ================================
// admin.php — hallintasivu, jonne vain admin-käyttäjät pääsevät
// ================================

require_once 'app/session-config.php'; // Istuntoasetukset. ENSIN ennen kaikkea muuta
require_once 'app/db.php';             // Tietokantayhteys $conn-muuttujaan

// Tarkistetaan että käyttäjä on kirjautunut sisään
// Kirjautumaton käyttäjä ohjataan takaisin kirjautumissivulle  
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Tarkistetaan että käyttäjä on admin-käyttäjä
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: index.php');
    exit;
}

