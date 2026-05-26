<?php
// ================================
// reset-password.php — Salasanan nollaus-sivu
// ================================

// Etsitään zombie-config-kansio — tarkistetaan ensin yksi taso ylös, sitten kaksi
// Paikallisesti kansio on yhden tason päässä, palvelimella kahden
$cfgDir = is_dir(dirname(__DIR__) . '/zombie-config')
    ? dirname(__DIR__) . '/zombie-config'
    : dirname(dirname(__DIR__)) . '/zombie-config';
require_once $cfgDir . '/session-config.php'; // Istuntoasetukset ENSIN
require_once $cfgDir . '/db.php';             // Tietokantayhteys
