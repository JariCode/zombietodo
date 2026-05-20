'use strict';
// ================================
// admin.js - Tämä tiedosto hoitaa admin-paneelin
// ================================

// ===========================================================
// CSRF-TOKEN
// Luetaan CSRF-token sivun head-osiossa olevasta meta-tagista
// admin.php lisää tokenin sinne sivun latauksen yhteydessä
// ===========================================================
function getCSRF() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// ===========================================================
// KÄYTTÄJÄLISTAN PÄIVITYS AJAXilla
// Hakee partial-admin.php:ltä suodatetun käyttäjätaulukon
// ja korvaa taulukon sisällön ilman sivulatausta
// ===========================================================
async function refreshUsers(form) {
    const scroll = document.querySelector('.admin-user-scroll'); // Scrollaava alue jossa taulukko on
    if (!scroll) return;

    const prevScroll = window.scrollY; // Tallennetaan scroll-positio

    // Safari-korjaus — estää sivun hyppimisen päivityksen aikana
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
    if (isSafari) { scroll.style.height = scroll.offsetHeight + 'px'; scroll.style.overflow = 'hidden'; }

    // Kootaan suodattimet FormDataan ja lisätään type-parametri
    const formData = new FormData(form);
    formData.append('type', 'users'); // Kertoo partial-admin.php:lle mitä osiota haetaan

    // Lähetetään suodattimet POST-pyyntönä
    const res = await fetch('app/partial-admin.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': getCSRF() },
        body: formData
    });

    if (isSafari) { scroll.style.height = ''; scroll.style.overflow = ''; }// Safari-korjaus palautus

    const html = await res.text();
    scroll.innerHTML = html; // Korvataan taulukon sisältö

    // Palautetaan scroll-positio
    requestAnimationFrame(function() {
        requestAnimationFrame(function() { window.scrollTo(0, prevScroll); });
    });
}

// ===========================================================
// LOKITAPAHTUMIEN PÄIVITYS AJAXilla
// Hakee partial-admin.php:ltä suodatetun lokitaulukon
// ja korvaa taulukon sisällön ilman sivulatausta
// ===========================================================
async function refreshLogs(form) {
    const scroll = document.querySelector('.admin-log-scroll'); // Scrollaava alue jossa taulukko on
    if (!scroll) return;

    const prevScroll = window.scrollY; // Tallennetaan scroll-positio

    // Safari-korjaus — estää sivun hyppimisen päivityksen aikana
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
    if (isSafari) { scroll.style.height = scroll.offsetHeight + 'px'; scroll.style.overflow = 'hidden'; }

    // Kootaan suodattimet FormDataan ja lisätään type-parametri
    const formData = new FormData(form);
    formData.append('type', 'logs'); // Kertoo partial-admin.php:lle mitä osiota haetaan

    // Lähetetään suodattimet POST-pyyntönä
    const res = await fetch('app/partial-admin.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': getCSRF() },
        body: formData
    });

    if (isSafari) { scroll.style.height = ''; scroll.style.overflow = ''; }// Safari-korjaus palautus

    const html = await res.text();
    scroll.innerHTML = html; // Korvataan taulukon sisältö

    // Palautetaan scroll-positio
    requestAnimationFrame(function() {
        requestAnimationFrame(function() { window.scrollTo(0, prevScroll); });
    });
}

// ===========================================================
// LOMAKKEIDEN KIINNITYS
// Kaapataan HAE-nappien lomakkeet ja lähetetään AJAXilla
// ===========================================================
function setupAdminForms() {
    // Etsitään lomakkeet piilotetun tunnistekentän perusteella
    const forms = document.querySelectorAll('form');

    forms.forEach(function(form) {
        // Käyttäjäsuodatuslomake — tunnistetaan user_filter-piilokentästä
        if (form.querySelector('input[name="user_filter"]')) {
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Estetään normaali sivulataus
                refreshUsers(form);
            });
        }

        // Lokisuodatuslomake — tunnistetaan log_filter-piilokentästä
        if (form.querySelector('input[name="log_filter"]')) {
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Estetään normaali sivulataus
                refreshLogs(form);
            });
        }
    });
}

// ===========================================================
// KÄYNNISTYS
// Kiinnitetään tapahtumat kun sivu on latautunut
// ===========================================================
setupAdminForms();
