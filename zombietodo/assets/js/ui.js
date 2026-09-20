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

// ===========================================================
// LATAUSINDIKAATTORI (loading-overlay) — JAETTU KAIKILLE SIVUILLE
//
// PHP-puolen operaatiot (salasanan hashays, tietokantakyselyt,
// sähköpostin lähetys) voivat kestää hetken. Tämä näyttää koko
// sivun peittävän ylälaidan, jotta käyttäjä näkee jotain tapahtuvan
// eikä klikkaa nappia moneen kertaan.
//
// Overlay-elementti injektoidaan tänne DOM:iin createElementillä
// (ei innerHTML:llä) CSP-turvallisesti — mitään inline-tyylejä tai
// -skriptejä ei käytetä, kaikki tyylit tulevat style.css:stä.
// ===========================================================

// Luo overlay-elementin DOM:iin jos sitä ei vielä ole, ja palauttaa sen
function ensureLoadingOverlay() {
    let overlay = document.getElementById('loadingOverlay');
    if (overlay) return overlay;

    overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.className = 'loading-overlay';
    overlay.setAttribute('aria-hidden', 'true');
    overlay.setAttribute('role', 'status');

    const spinner = document.createElement('div');
    spinner.className = 'loading-spinner';

    const text = document.createElement('div');
    text.className = 'loading-text';
    text.textContent = 'Ladataan...';

    overlay.appendChild(spinner);
    overlay.appendChild(text);
    document.body.appendChild(overlay);

    return overlay;
}

// Kynnysaika (ms) jonka verran odotetaan ennen overlayn näyttämistä.
// Näin alle kynnysajan valmistuvat AJAX-toiminnot eivät välähdytä overlayta.
const LOADING_SHOW_DELAY_MS = 350;

// Käynnissä olevan viive-ajastimen id, jotta hideLoading voi perua sen
let loadingShowTimeoutId = null;

// Näyttää latausindikaattorin — kutsutaan ennen hidasta toimintoa
// (lomakkeen submit tai AJAX-fetch).
// immediate=true näyttää overlayn heti ilman viivettä (käytetään
// sivun uudelleenlataaville lomake-submiteille, joissa viive tekisi
// näyttämisestä epäluotettavaa).
function showLoading(immediate) {
    // Ettei kaksi ajastinta pyöri päällekkäin jos showLoading kutsutaan
    // uudestaan ennen edellisen piilotusta
    if (loadingShowTimeoutId) {
        clearTimeout(loadingShowTimeoutId);
        loadingShowTimeoutId = null;
    }

    const overlay = ensureLoadingOverlay();

    if (immediate) {
        overlay.classList.add('active');
        overlay.setAttribute('aria-hidden', 'false');
        return;
    }

    loadingShowTimeoutId = setTimeout(function() {
        loadingShowTimeoutId = null;
        overlay.classList.add('active');
        overlay.setAttribute('aria-hidden', 'false');
    }, LOADING_SHOW_DELAY_MS);
}

// Piilottaa latausindikaattorin — kutsutaan AINA AJAX-toiminnon
// finally-lohkossa, ettei overlay jää jumiin virheen sattuessa.
// Peruu myös odottavan viive-ajastimen, jotta nopea pyyntö ei
// ehdi näyttää overlayta lainkaan.
function hideLoading() {
    if (loadingShowTimeoutId) {
        clearTimeout(loadingShowTimeoutId);
        loadingShowTimeoutId = null;
    }
    const overlay = document.getElementById('loadingOverlay');
    if (!overlay) return;
    overlay.classList.remove('active');
    overlay.setAttribute('aria-hidden', 'true');
}

// Varmistetaan heti sivun latautuessa ettei overlay jää päälle
// (esim. jos selain palauttaa sivun bfcache-välimuistista taaksepäin-napilla)
ensureLoadingOverlay();
window.addEventListener('pageshow', hideLoading);

// ===========================================================
// LOMAKKEIDEN SUBMIT — EI-AJAX-SIVUT
//
// Kiinnitetään showLoading() jokaiseen lomakkeeseen jolla on
// data-loading-attribuutti. Attribuutti lisätään vain niihin
// lomakkeisiin joita EI käsitellä AJAXilla (JS ei tee niille
// preventDefaultia) — sivu siis oikeasti latautuu uudelleen,
// ja overlay jää näkyviin siihen asti.
//
// 'submit'-tapahtuma ei laukea lainkaan jos selaimen oma
// pakollisuus-/muotovalidointi (required, minlength, type=email...)
// estää lähetyksen — joten overlay ei näy virheellisen lomakkeen
// kohdalla, aivan kuten pitääkin.
//
// Näytetään overlay HETI (immediate=true) ilman AJAX-polun viivettä:
// sivu latautuu joka tapauksessa uudelleen, joten flash-riskiä ei ole,
// ja viive tekisi näyttämisestä vain epäluotettavaa.
// ===========================================================
document.querySelectorAll('form[data-loading]').forEach(function(form) {
    form.addEventListener('submit', function() {
        showLoading(true);
    });
});