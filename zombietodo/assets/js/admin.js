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

    // Liitetään käyttäjälistan hakuarvo lokipyyntöön
    const userForm = document.querySelector('input[name="user_filter"]').closest('form');
    const userSearch = userForm ? userForm.querySelector('input[name="user_search"]') : null;
    if (userSearch && userSearch.value) {
        formData.append('user_search', userSearch.value);
    }

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
// ADMIN INTRO ANIMAATIO
// ===========================================================
// Funktio joka hoitaa admin-paneelin tervetuloanimaation
function startAdminIntro() {

    // Haetaan intro-elementti HTML:stä
    const intro = document.getElementById('adminIntro');

    // Lopetetaan funktio jos intro-elementtiä ei löydy
    if (!intro) return;

    // Haetaan status-tekstielementti introsta
    const status = intro.querySelector('.admin-status');

    // Tekstit joita näytetään vuorotellen intro-animaation aikana
    const messages = [
        'Loading containment systems...',
        'Scanning infected database...',
        'Checking zombie activity...',
        'Access granted...'
    ];

    // Aloitetaan ensimmäisestä viestistä
    let i = 0;

    // Vaihdetaan status-tekstiä tietyin väliajoin
    const interval = setInterval(function() {

        // Siirrytään seuraavaan viestiin
        i++;

        // Lopetetaan animaation tekstinvaihto kun kaikki viestit on näytetty
        if (i >= messages.length) {
            clearInterval(interval);
            return;
        }

        // Päivitetään näkyvä status-teksti
        status.textContent = messages[i];

    }, 700);

    // Poistetaan intro-elementti lopuksi kokonaan DOM:ista
    setTimeout(function() {
        intro.remove();
    }, 5000);
}

// ===========================================================
// FLATPICKR-PÄIVÄMÄÄRÄVALITSIMET
// Alustetaan Flatpickr-kirjasto lokitapahtumien input-kenttiin
// ===========================================================
const finnishLocale = {
    firstDayOfWeek: 1,
    weekdays: {
        shorthand: ['Su','Ma','Ti','Ke','To','Pe','La'],
        longhand:  ['Sunnuntai','Maanantai','Tiistai','Keskiviikko','Torstai','Perjantai','Lauantai']
    },
    months: {
        shorthand: ['Tam','Hel','Maa','Huh','Tou','Kes','Hei','Elo','Syy','Lok','Mar','Jou'],
        longhand:  ['Tammikuu','Helmikuu','Maaliskuu','Huhtikuu','Toukokuu','Kesäkuu',
                    'Heinäkuu','Elokuu','Syyskuu','Lokakuu','Marraskuu','Joulukuu']
    }
};
flatpickr('#logFrom', {
    dateFormat: 'd.m.Y',
    allowInput: false,
    static: true,
    locale: finnishLocale
});

flatpickr('#logTo', {
    dateFormat: 'd.m.Y',
    allowInput: false,
    static: true,
    locale: finnishLocale
});

// ===========================================================
// ADMIN-MODALIN AVAUS
// Kun admin klikkaa HALLINTA-nappia, luetaan käyttäjän tiedot
// taulukon riviltä ja täytetään modalin kentät
// ===========================================================
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.admin-btn-edit'); // Etsitään klikattu HALLINTA-nappi
    if (!btn) return; // Jos klikattiin muualle, ei tehdä mitään

    const row = btn.closest('tr'); // Haetaan taulukon rivi jossa nappi on
    const id = btn.dataset.id; // Luetaan käyttäjän id data-attribuutista
    const username = row.querySelector('td:nth-child(1)').textContent; // Käyttäjänimi ensimmäisestä sarakkeesta
    const email = row.querySelector('td:nth-child(2)').textContent; // Sähköposti toisesta sarakkeesta
    const role = row.querySelector('td:nth-child(3)').textContent.trim().toLowerCase(); // Rooli kolmannesta sarakkeesta

    // Täytetään kohde-id:t kaikkiin lomakkeisiin
    document.getElementById('roleTargetId').value = id;   // Roolin vaihdon kohde
    document.getElementById('resetTargetId').value = id;  // Salasanan palautuksen kohde
    document.getElementById('deleteTargetId').value = id; // Tilin poiston kohde

    // Täytetään modalin näkyvät kentät
    document.getElementById('adminModalUser').textContent = username + ' — ' + email; // Kenen tiliä hallitaan
    document.getElementById('editRole').value = role === 'admin' ? 'admin' : 'user';  // Nykyinen rooli valitsimeen
    document.getElementById('resetEmail').value = '';            // Tyhjä — admin kirjoittaa itse vahvistukseksi
    document.getElementById('resetEmail').placeholder = email;   // Vihje mitä pitää kirjoittaa

    // Tilin poiston kentät — tyhjät, placeholder näyttää odotetun arvon
    document.getElementById('deleteUsername').value = '';             // Tyhjä — admin kirjoittaa itse vahvistukseksi
    document.getElementById('deleteUsername').placeholder = username; // Vihje mitä pitää kirjoittaa
    document.getElementById('deleteEmail').value = '';                // Tyhjä — admin kirjoittaa itse vahvistukseksi
    document.getElementById('deleteEmail').placeholder = email;      // Vihje mitä pitää kirjoittaa

    // Varoitusteksti tilin poistoon
    document.getElementById('deleteWarning').textContent = 'Oletko varma, että haluat poistaa käyttäjän ' + username + '? Tätä toimintoa ei voi peruuttaa.';

    // Tyhjennetään kaikki viestikentät edelliseltä avaukselta
    document.getElementById('adminModalError').textContent = '';
    document.getElementById('roleMessage').textContent = '';
    document.getElementById('resetMessage').textContent = '';
    document.getElementById('deleteMessage').textContent = '';

    // Palautetaan napit alkutilaan — piilotetaan vahvistusnapit, näytetään alkuperäiset
    resetConfirmButtons();

    // Avataan modal
    document.getElementById('adminModal').classList.add('open'); // Näytetään modal
    document.body.classList.add('modal-open'); // Lukitaan taustasivun skrollaus
});

// ===========================================================
// VAHVISTUSNAPPIEN NOLLAUS
// Palauttaa kaikki napit alkutilaan — kutsutaan modalin
// avautuessa ja sulkeutuessa
// ===========================================================
function resetConfirmButtons() {
    // Roolin vaihto — näytetään alkuperäinen, piilotetaan vahvistus
    document.getElementById('roleSubmit').style.display = '';
    document.getElementById('roleConfirm').style.display = 'none';

    // Salasanan palautus — näytetään alkuperäinen, piilotetaan vahvistus
    document.getElementById('resetSubmit').style.display = '';
    document.getElementById('resetConfirm').style.display = 'none';

    // Tilin poisto — näytetään alkuperäinen, piilotetaan vahvistus
    document.getElementById('deleteSubmit').style.display = '';
    document.getElementById('deleteConfirm').style.display = 'none';
}

// ===========================================================
// ADMIN-MODALIN SULKEMINEN
// Modal sulkeutuu X-napista, peruuta-napista, taustan
// klikkauksesta tai ESC-näppäimestä
// ===========================================================
function closeAdminModal() {
    document.getElementById('adminModal').classList.remove('open'); // Piilotetaan modal
    document.body.classList.remove('modal-open'); // Vapautetaan taustasivun skrollaus
    resetConfirmButtons(); // Palautetaan napit alkutilaan seuraavaa avausta varten
}

// X-nappi modalin oikeassa yläkulmassa
document.getElementById('adminModalClose').addEventListener('click', closeAdminModal);

// Peruuta-nappi tilin poisto -osiossa
document.getElementById('adminDeleteCancel').addEventListener('click', closeAdminModal);

// Klikkaus tummaan taustaan modalin ulkopuolelle
document.getElementById('adminModal').addEventListener('click', function(e) {
    if (e.target === this) closeAdminModal(); // Suljetaan vain jos klikattiin taustaa eikä modalin sisältöä
});

// ESC-näppäin sulkee modalin
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAdminModal();
});

// ===========================================================
// LOMAKKEIDEN VAHVISTUSLOGIIKKA
// Ensimmäinen klikkaus näyttää vahvistusviestin ja vaihtaa
// napin — toinen klikkaus lähettää lomakkeen backendiin
// ===========================================================

// ROOLIN VAIHTO — vahvistus ennen lähetystä
document.getElementById('adminRoleForm').addEventListener('submit', function(e) {
    const confirmBtn = document.getElementById('roleConfirm');

    // Jos vahvistusnappi ei ole vielä näkyvissä — estetään lähetys ja näytetään viesti
    if (confirmBtn.style.display === 'none') {
        e.preventDefault(); // Estetään lomakkeen lähetys
        const roleSelect = document.getElementById('editRole'); // Haetaan select-elementti
        const roleText = roleSelect.options[roleSelect.selectedIndex].text; // Luetaan näkyvä teksti "Käyttäjä" tai "Admin"
        const username = document.getElementById('adminModalUser').textContent.split(' — ')[0]; // Luetaan käyttäjänimi modalin otsikosta
        document.getElementById('roleMessage').textContent = 'Vaihdetaanko ' + username + ' rooliksi ' + roleText + '?'; // Näytetään vahvistusviesti
        document.getElementById('roleSubmit').style.display = 'none'; // Piilotetaan alkuperäinen nappi
        confirmBtn.style.display = ''; // Näytetään vahvistusnappi
        return;
    }
    // Vahvistusnappi painettu — lomake lähtee normaalisti backendiin
});

// SALASANAN PALAUTUS — vahvistus ennen lähetystä
document.getElementById('adminResetForm').addEventListener('submit', function(e) {
    const confirmBtn = document.getElementById('resetConfirm');

    // Jos vahvistusnappi ei ole vielä näkyvissä — estetään lähetys ja näytetään viesti
    if (confirmBtn.style.display === 'none') {
        e.preventDefault(); // Estetään lomakkeen lähetys
        const email = document.getElementById('resetEmail').value; // Luetaan syötetty sähköposti
        document.getElementById('resetMessage').textContent = 'Lähetetäänkö salasanan palautuslinkki osoitteeseen ' + email + '?'; // Näytetään vahvistusviesti
        document.getElementById('resetSubmit').style.display = 'none'; // Piilotetaan alkuperäinen nappi
        confirmBtn.style.display = ''; // Näytetään vahvistusnappi
        return;
    }
    // Vahvistusnappi painettu — lomake lähtee normaalisti backendiin
});

// TILIN POISTO — vahvistus ennen lähetystä
document.getElementById('adminDeleteForm').addEventListener('submit', function(e) {
    const confirmBtn = document.getElementById('deleteConfirm');

    // Jos vahvistusnappi ei ole vielä näkyvissä — estetään lähetys ja näytetään viesti
    if (confirmBtn.style.display === 'none') {
        e.preventDefault(); // Estetään lomakkeen lähetys
        const username = document.getElementById('adminModalUser').textContent.split(' — ')[0]; // Luetaan käyttäjänimi
        document.getElementById('deleteMessage').textContent = 'Käyttäjä ' + username + ' poistetaan pysyvästi. Vahvista toiminto.'; // Näytetään vahvistusviesti
        document.getElementById('deleteSubmit').style.display = 'none'; // Piilotetaan alkuperäinen nappi
        confirmBtn.style.display = ''; // Näytetään vahvistusnappi
        return;
    }
    // Vahvistusnappi painettu — lomake lähtee normaalisti backendiin
});
// ===========================================================
// KÄYNNISTYS
// Kiinnitetään tapahtumat kun sivu on latautunut
// ===========================================================
setupAdminForms();
startAdminIntro();
