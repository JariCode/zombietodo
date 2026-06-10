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

   // Liitetään käyttäjälistan hakuarvo lokipyyntöön — turvallisesti, jos kenttää ei löydy
    const userFilterInput = document.querySelector('input[name="user_filter"]');
    const userForm = userFilterInput ? userFilterInput.closest('form') : null;
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
const finnishLocale = { // Määritellään suomenkieliset nimet ja viikon aloituspäivä
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
    allowInput: false, //Estää manuaalisen syötteen, pakottaa käyttämään datepickeria
    static: true, // Estää datepickerin hyppimisen, pitää sen paikallaan
    disableMobile: true, // Estää mobiililaitteiden omien datepickereiden käytön
    altInput: true, 
    altFormat: 'd.m.Y',
    locale: finnishLocale // Asetetaan suomenkieliset nimet ja viikon aloituspäivä
});

flatpickr('#logTo', {
    dateFormat: 'd.m.Y',
    allowInput: false, //Estää manuaalisen syötteen, pakottaa käyttämään datepickeria
    static: true, // Estää datepickerin hyppimisen, pitää sen paikallaan
    disableMobile: true, // Estää mobiililaitteiden omien datepickereiden käytön
    altInput: true,
    altFormat: 'd.m.Y',    
    locale: finnishLocale // Asetetaan suomenkieliset nimet ja viikon aloituspäivä
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
    // Tilin lukitus — luetaan tila napista ja asetetaan teksti + napin teksti sen mukaan
    const isLocked = btn.dataset.locked === '1';
    document.getElementById('lockStatus').textContent = isLocked
        ? 'Käyttäjän ' + username + ' tili on tällä hetkellä lukittu. Käyttäjä ei voi kirjautua sisään.'
        : 'Käyttäjän ' + username + ' tili on tällä hetkellä auki. Käyttäjä voi kirjautua normaalisti.';
    document.getElementById('lockSubmit').textContent = isLocked ? 'AVAA 🔓' : 'LUKITSE 🔒';

    // Täytetään kohde-id:t kaikkiin lomakkeisiin
    document.getElementById('roleTargetId').value = id;   // Roolin vaihdon kohde
    document.getElementById('lockTargetId').value = id;   // Tilin lukituksen kohd
    document.getElementById('deleteTargetId').value = id; // Tilin poiston kohde

    // Täytetään modalin näkyvät kentät
    document.getElementById('adminModalUser').textContent = username + ' — ' + email; // Kenen tiliä hallitaan
    document.getElementById('editRole').value = role === 'admin' ? 'admin' : 'user';  // Nykyinen rooli valitsimeen

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
    document.getElementById('lockMessage').textContent = '';
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
// Käytetään CSS-luokkaa 'hidden' inline-tyylien sijaan
// CSP-yhteensopivuuden vuoksi (Content-Security-Policy
// estää inline style -attribuuttien käytön)
// ===========================================================
function resetConfirmButtons() {
    // Roolin vaihto — näytetään alkuperäinen, piilotetaan vahvistus
    document.getElementById('roleSubmit').classList.remove('hidden');
    document.getElementById('roleConfirm').classList.add('hidden');

    // Tilin lukitus — näytetään alkuperäinen, piilotetaan vahvistus
    document.getElementById('lockSubmit').classList.remove('hidden');
    document.getElementById('lockConfirm').classList.add('hidden');

    // Tilin poisto — näytetään alkuperäinen, piilotetaan vahvistus
    document.getElementById('deleteSubmit').classList.remove('hidden');
    document.getElementById('deleteConfirm').classList.add('hidden');
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
// Käytetään classList.contains/add/remove -metodeja
// inline-tyylien sijaan CSP-yhteensopivuuden vuoksi
// ===========================================================

// Admin-modalin viestien yhteinen häivytysajastin — tyhjentää kaikki kolme viestikenttää 8 s kuluttua (sama aika kuin ui.js)
let modalMessageTimer = null;
function scheduleModalMessageClear() {
    clearTimeout(modalMessageTimer);
    modalMessageTimer = setTimeout(function() {
        ['roleMessage', 'deleteMessage', 'lockMessage'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) { el.textContent = ''; el.classList.remove('is-success'); }
        });
    }, 8000);
}

// ROOLIN VAIHTO — vahvistus ennen lähetystä, sitten AJAX-lähetys
document.getElementById('adminRoleForm').addEventListener('submit', async function(e) {
    e.preventDefault(); // Estetään aina normaali lähetys — hoidetaan fetchillä
    const confirmBtn = document.getElementById('roleConfirm');
    const roleMessage = document.getElementById('roleMessage');

    // Ensimmäinen klikkaus — näytetään vahvistusviesti (punainen varoitus) ja vaihdetaan nappi
    if (confirmBtn.classList.contains('hidden')) {
        const roleSelect = document.getElementById('editRole');
        const roleText = roleSelect.options[roleSelect.selectedIndex].text;
        const username = document.getElementById('adminModalUser').textContent.split(' — ')[0];
        roleMessage.textContent = 'Vaihdetaanko ' + username + ' rooliksi ' + roleText + '?';
        document.getElementById('roleSubmit').classList.add('hidden');
        confirmBtn.classList.remove('hidden');
        return;
    }

    // Toinen klikkaus — lähetetään lomake AJAXilla
    const formData = new FormData(this);
    try {
        const res = await fetch('app/actions.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': getCSRF() },
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            roleMessage.textContent = data.message || 'Rooli vaihdettu.';
            roleMessage.classList.add('is-success'); // Vihreä onnistumiselle
        } else {
            roleMessage.textContent = data.error || 'Toiminto epäonnistui.';
            roleMessage.classList.remove('is-success'); // Punainen virheelle
        }

        scheduleModalMessageClear(); // Häivytetään viesti 8 s kuluttua
        resetConfirmButtons();

      if (data.success) {
            const userFilterInput = document.querySelector('input[name="user_filter"]');
            const userForm = userFilterInput ? userFilterInput.closest('form') : null;
            if (userForm) refreshUsers(userForm); // Päivitetään käyttäjälista taustalla

            const logFilterInput = document.querySelector('input[name="log_filter"]');
            const logForm = logFilterInput ? logFilterInput.closest('form') : null;
            if (logForm) refreshLogs(logForm); // Päivitetään lokitaulukko taustalla
        }
    } catch (err) {
        roleMessage.textContent = 'Verkkovirhe. Yritä uudelleen.';
        roleMessage.classList.remove('is-success');
        scheduleModalMessageClear();
        resetConfirmButtons();
    }
});

// TILIN LUKITUS / AVAUS — vahvistus ennen lähetystä, sitten AJAX-lähetys
document.getElementById('adminLockForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const confirmBtn = document.getElementById('lockConfirm');
    const lockMessage = document.getElementById('lockMessage');

    // Ensimmäinen klikkaus — näytetään vahvistusviesti ja vaihdetaan nappi
    if (confirmBtn.classList.contains('hidden')) {
        const username = document.getElementById('adminModalUser').textContent.split(' — ')[0];
        lockMessage.textContent = 'Vahvista tilin lukitustilan muutos käyttäjälle ' + username + '.';
        document.getElementById('lockSubmit').classList.add('hidden');
        confirmBtn.classList.remove('hidden');
        return;
    }

    // Toinen klikkaus — lähetetään AJAXilla
    const formData = new FormData(this);
    try {
        const res = await fetch('app/actions.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': getCSRF() },
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            lockMessage.textContent = data.message || 'Lukitustila muutettu.';
            lockMessage.classList.add('is-success');
        } else {
            lockMessage.textContent = data.error || 'Toiminto epäonnistui.';
            lockMessage.classList.remove('is-success');
        }

        scheduleModalMessageClear();
        resetConfirmButtons();

        if (data.success) {
            const userFilterInput = document.querySelector('input[name="user_filter"]');
            const userForm = userFilterInput ? userFilterInput.closest('form') : null;
            if (userForm) refreshUsers(userForm);

            const logFilterInput = document.querySelector('input[name="log_filter"]');
            const logForm = logFilterInput ? logFilterInput.closest('form') : null;
            if (logForm) refreshLogs(logForm);
        }
    } catch (err) {
        lockMessage.textContent = 'Verkkovirhe. Yritä uudelleen.';
        lockMessage.classList.remove('is-success');
        scheduleModalMessageClear();
        resetConfirmButtons();
    }
});

// TILIN POISTO — vahvistus ennen lähetystä, sitten AJAX-lähetys
document.getElementById('adminDeleteForm').addEventListener('submit', async function(e) {
    e.preventDefault(); // Estetään aina normaali lähetys — hoidetaan fetchillä
    const confirmBtn = document.getElementById('deleteConfirm');
    const deleteMessage = document.getElementById('deleteMessage');

    // Ensimmäinen klikkaus — näytetään vahvistusviesti ja vaihdetaan nappi
    if (confirmBtn.classList.contains('hidden')) {
        const username = document.getElementById('adminModalUser').textContent.split(' — ')[0];
        deleteMessage.textContent = 'Käyttäjä ' + username + ' poistetaan pysyvästi. Vahvista toiminto.';
        document.getElementById('deleteSubmit').classList.add('hidden');
        confirmBtn.classList.remove('hidden');
        return;
    }

    // Toinen klikkaus — lähetetään lomake AJAXilla
    const formData = new FormData(this);
    try {
        const res = await fetch('app/actions.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': getCSRF() },
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            deleteMessage.textContent = data.message || 'Käyttäjä poistettu.';
            deleteMessage.classList.add('is-success'); // Vihreä onnistumiselle

            // Käyttäjä poistettu — tyhjennetään modalin tiedot ettei ne viittaa poistettuun
            document.getElementById('adminModalUser').textContent = '';
            document.getElementById('roleTargetId').value = '';
            document.getElementById('lockTargetId').value = '';
            document.getElementById('deleteTargetId').value = '';
            document.getElementById('deleteUsername').value = '';
            document.getElementById('deleteUsername').placeholder = '';
            document.getElementById('deleteEmail').value = '';
            document.getElementById('deleteEmail').placeholder = '';

            const userFilterInput = document.querySelector('input[name="user_filter"]');
            const userForm = userFilterInput ? userFilterInput.closest('form') : null;
            if (userForm) refreshUsers(userForm); // Päivitetään käyttäjälista taustalla

            const logFilterInput = document.querySelector('input[name="log_filter"]');
            const logForm = logFilterInput ? logFilterInput.closest('form') : null;
            if (logForm) refreshLogs(logForm); // Päivitetään lokitaulukko taustalla
        } else {
            deleteMessage.textContent = data.error || 'Toiminto epäonnistui.';
            deleteMessage.classList.remove('is-success'); // Virheelle ei vihreää
        }

        scheduleModalMessageClear(); // Häivytetään viesti 8 s kuluttua
        resetConfirmButtons();
    } catch (err) {
        deleteMessage.textContent = 'Verkkovirhe. Yritä uudelleen.';
        deleteMessage.classList.remove('is-success');
        scheduleModalMessageClear();
        resetConfirmButtons();
    }
});
// ===========================================================
// KÄYNNISTYS
// Kiinnitetään tapahtumat kun sivu on latautunut
// ===========================================================
setupAdminForms();
startAdminIntro();
