'use strict';
// ================================
// ui.js
//
// Tämä tiedosto hoitaa käyttöliittymän
// pienet toiminnot selaimessa.
//
// Huolehtii seuraavista:
// - Salasanakentän silmäpainike jonka avulla
//   voi näyttää tai piilottaa salasanan
// - Käyttöehtojen ja tietosuojaselosteen
//   avaaminen ponnahdusikkunassa
// - Ilmoitusviestin automaattinen häivytys
//   muutaman sekunnin kuluttua
//
// Tämä tiedosto ladataan jokaisella sivulla
// koska nämä toiminnot tarvitaan kaikkialla.
// ================================

// ===========================================================
// SALASANAN NÄYTTÖ / PIILOTUS
// ===========================================================

// Haetaan kaikki silmäpainikkeet sivulta
document.querySelectorAll('.password-field .password-eye').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const input = btn.parentElement.querySelector('input'); // Haetaan saman password-field divin sisällä oleva input-kenttä
        if (!input) return; // Jos kenttää ei löydy, ei tehdä mitään. Estää kaatumisen

        const isHidden = input.type === 'password'; // Tarkistetaan onko salasana piilotettu
        input.type = isHidden ? 'text' : 'password'; // Vaihdetaan tyyppiä. Text näyttää, password piilottaa

        // Päivitetään aria-label saavutettavuutta varten. Ruudunlukijat kertovat tilan
        btn.setAttribute('aria-label', isHidden ? 'Piilota salasana' : 'Näytä salasana');
    });
});

// ===========================================================
// VIRHE- JA ONNISTUMISVIESTIEN AUTOMAATTINEN HÄIVYTYS
// ===========================================================

// Häivytetään virhe- ja onnistumisviestit automaattisesti muutaman sekunnin kuluttua
document.querySelectorAll('.auth-error, .auth-success').forEach(function(msg) {
    setTimeout(function() {
        msg.style.transition = 'opacity 1s'; // Häivytys kestää 1 sekunnin
        msg.style.opacity = '0';             // Aloitetaan häivytys
        setTimeout(function() {
            if (msg && msg.parentNode) {
                msg.parentNode.removeChild(msg); // Poistetaan elementti kokonaan DOM:sta
            }
        }, 1000); // Poistetaan 1 sekunnin häivytyksen jälkeen
    }, 8000); // Odotetaan 8 sekuntia ennen häivytystä
});

// ===========================================================
// KÄYTTÖEHDOT JA TIETOSUOJASELOSTE SEKÄ UNOHTUNEEN SALASANAN PALAUTUS — MODALIEN AVAUS
// ===========================================================
document.querySelectorAll('.link-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const type = btn.textContent.trim().startsWith('käyttöehdot') ? 'terms' : 'privacy';
        openLegalModal(type);
    });
});

// Salasanan palautus -modalin avaus
const openResetBtn = document.getElementById('openResetModal');
if (openResetBtn) {
    openResetBtn.addEventListener('click', function() {
        const overlay = document.getElementById('resetModal');
        if (!overlay) return;
        overlay.classList.add('open');
        document.body.classList.add('modal-open');
    });
}

// Avaa käyttöehdot- tai tietosuojaseloste-modalin
function openLegalModal(type) {
    const id = type === 'terms' ? 'legalTerms' : 'legalPrivacy'; // Valitaan oikea modal
    const overlay = document.getElementById(id);
    if (!overlay) return;
    overlay.classList.add('open'); // Näytetään modal
    document.body.classList.add('modal-open'); // Lukitaan taustasivun skrolli

    // Scrollataan sisältö alkuun jos modal on avattu aiemmin ja scrollattu alas
    const body = overlay.querySelector('.legal-body');
    if (body) body.scrollTop = 0;
}

// Sulkee legal-modalin
function closeLegalModal(overlay) {
    if (!overlay) return;
    overlay.classList.remove('open'); // Piilotetaan modal
    document.body.classList.remove('modal-open'); // Vapautetaan taustasivun skrolli
}

// Kiinnitetään sulkemistapahtumat kaikkiin legal-modaleihin
document.querySelectorAll('.legal-overlay').forEach(function(overlay) {
    // X-nappi headerissa
    const closeBtn = overlay.querySelector('.legal-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() { closeLegalModal(overlay); });
    }
    // SULJE 🔒 nappi footerissa
    const closeFooterBtn = overlay.querySelector('.legal-close-btn');
    if (closeFooterBtn) {
        closeFooterBtn.addEventListener('click', function() { closeLegalModal(overlay); });
    }
    // Klikkaus taustan päälle sulkee modalin
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeLegalModal(overlay);
    });
});

// ESC-näppäin sulkee legal-modalin
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.legal-overlay.open').forEach(function(overlay) {
            closeLegalModal(overlay);
        });
    }
});