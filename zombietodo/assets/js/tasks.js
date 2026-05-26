'use strict';
// ================================
// tasks.js
//
// Tämä tiedosto hoitaa tehtävälistan
// toiminnan selaimessa.
//
// Huolehtii seuraavista:
// - Uuden tehtävän lisääminen
// - Tehtävän muokkaaminen
// - Tehtävän poistaminen
// - Tehtävän tilan vaihto (ei aloitettu,
//   käynnissä, valmis)
//
// Tehtävälista päivittyy automaattisesti
// ilman että koko sivu latautuu uudelleen.
//
// Kaikki pyynnöt lähetetään POST-metodilla
// ja data kulkee POST-bodyssa — URL:ssa ei
// näy toiminto- eikä id-tietoja.
// ================================

// ===========================================================
// CSRF-TOKEN
// Luetaan CSRF-token sivun head-osiossa olevasta meta-tagista
// tasks.php lisää tokenin sinne sivun latauksen yhteydessä
// Tokenia käytetään joka AJAX-pyynnössä turvallisuuden varmistamiseksi
// ===========================================================
function getCSRF() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : ''; // Jos meta-tagia ei löydy, palautetaan tyhjä merkkijono
}

// ===========================================================
// TEHTÄVÄLISTAN PÄIVITYS AJAXilla (Fetch API)
// Hakee partial-tasks.php:ltä päivitetyn tehtävälistan
// ja korvaa sivun sisällön sillä ilman sivulatausta
// ===========================================================
async function refreshTasks() {
    const prevScroll = window.scrollY; // Tallennetaan scroll-positio ennen päivitystä jotta sivu ei hyppää
    const box = document.querySelector('.todo-box');
    if (!box) return; // Jos todo-boxia ei löydy, lopetetaan

    // Safari-selaimelle erityinen korjaus — estää sivun hyppimisen päivityksen aikana
    // Safari käsittelee innerHTML-päivityksen eri tavalla kuin muut selaimet
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
    if (isSafari) { box.style.height = box.offsetHeight + 'px'; box.style.overflow = 'hidden'; }

    // Haetaan päivitetty tehtävälista palvelimelta
    // CSRF-token lähetetään headerissa koska partial-tasks.php vaatii sen
    const html = await fetch('app/partial-tasks.php', {
        headers: { 'X-CSRF-Token': getCSRF() }
    }).then(function(r) { return r.text(); });

    const form = box.querySelector('form'); // Tallennetaan lisäyslomake ennen päivitystä
    // Korvataan todo-boxin sisältö — lisäyslomake säilyy, tehtävälistat päivittyvät
    box.innerHTML = (form ? form.outerHTML : '') + html;

    // Kiinnitetään nappeihin tapahtumat uudelleen koska HTML vaihtui
    attachTaskEvents(); // Tehtävänappien tapahtumat täytyy kiinnittää uudestaan koska HTML on korvattu uudella
    setupEnterKey();
    setupFormSubmit();

    // Siirretään kursori lisäyskenttään preventScroll-asetuksella
    // jotta selain ei scrollaa kentän kohdalle
    setTimeout(function() {
        const inp = document.querySelector('.input-area input[name="task"]');
        if (inp) { try { inp.focus({ preventScroll: true }); } catch(e) { inp.focus(); } }
    }, 0);

    if (isSafari) { box.style.height = ''; box.style.overflow = ''; } // Palautetaan Safari-korjaus

    // Palautetaan scroll-positio kahden animaatioframen päästä
    // jotta selain ehtii piirtää uuden HTML:n ennen kuin scroll palautetaan
    requestAnimationFrame(function() {
        requestAnimationFrame(function() { window.scrollTo(0, prevScroll); });
    });
}



// ===========================================================
// TOIMINTANAPIT
// Kiinnitetään kaikille tehtävänapeille click-tapahtuma
// Nappi lähettää AJAXilla (Fetch API) toiminnon palvelimelle ja päivittää listan
// ===========================================================


// JUMPSCARE
// Näyttää satunnaisen zombiefektin ja estää sen toistumista liian usein
let _jumpScareSuppressRemaining = 0;
const JUMP_SCARE_SUPPRESS_COUNT = 5;

function triggerJumpScare(chance = 0.22) {
    if (_jumpScareSuppressRemaining > 0) {
        _jumpScareSuppressRemaining--;
        return;
    }

    if (Math.random() > chance) return;

    const scare = document.getElementById('jumpScare');
    if (!scare) return;

    scare.classList.remove('active');
    void scare.offsetWidth;
    scare.classList.add('active');

    _jumpScareSuppressRemaining = JUMP_SCARE_SUPPRESS_COUNT;
}

// Kiinnitetään tapahtuma kaikille tehtävänapeille
function attachTaskEvents() {
    document.querySelectorAll('.actions button').forEach(function(el) {
        el.addEventListener('click', async function(e) {
            e.preventDefault();  // Estetään oletustoiminto
            e.stopPropagation(); // Estetään tapahtuman kupliminen ylöspäin
            const action = el.dataset.action; // Luetaan mitä toimintoa nappi tekee — esim. 'start', 'delete'
            const id     = el.dataset.id;     // Luetaan minkä tehtävän id on kyseessä
            if (action === 'edit') { openEditModal(id); return; } // Muokkausnappi avaa modalin eikä lähetä pyyntöä

             // Veriroiske-animaatio kun tehtävä aloitetaan
            if (action === 'start') {

                triggerJumpScare(0.16);// Aloitettaessa on pienempi mahdollisuus jump scareen

                const task = el.closest('.task');
                task.classList.add('anim-blood-splash');
                await new Promise(function(resolve) { setTimeout(resolve, 800); });
            }

            // Mullan heitto -animaatio kun tehtävä merkataan valmiiksi
            if (action === 'done') {

                triggerJumpScare(0.20);// Valmiiksi merkatessa on hieman suurempi mahdollisuus jump scareen

                const task = el.closest('.task');
                task.classList.add('anim-grave-drop');
                await new Promise(function(resolve) { setTimeout(resolve, 1000); });
            }

            // Haudasta nouseminen kun perutaan aloitus tai valmistuminen
            if (action === 'undo_start' || action === 'undo_done') {

                triggerJumpScare(0.24);// Peruutettaessa on kohtalainen mahdollisuus jump scareen

                const task = el.closest('.task');
                task.classList.add('anim-zombie-rise');
                await new Promise(function(resolve) { setTimeout(resolve, 900); });
            }

            // Koko sivun veriroiske kun tehtävä poistetaan
            if (action === 'delete') {
                const overlay = document.getElementById('bloodOverlay');
                const task = el.closest('.task');
                if (overlay) {
                    overlay.classList.remove('active');
                    overlay.offsetHeight;                   // Pakotetaan selain huomaamaan muutos
                    overlay.classList.add('active');
                }
                task.style.transition = 'opacity 0.4s';
                task.style.opacity = '0';
                await new Promise(function(resolve) { setTimeout(resolve, 1000); });
                if (overlay) overlay.classList.remove('active');
            }

            // Lähetetään toiminto ja tehtävän id POST-bodyssa palvelimelle
            // CSRF-token lähetetään headerissa koska actions.php vaatii sen
            await fetch('app/actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': getCSRF()
                },
                body: 'action=' + action + '&id=' + id // Toiminto ja id POST-datana URL:n sijaan
            });
            refreshTasks(); // Päivitetään tehtävälista näytöllä
        });
    });
}

// ===========================================================
// ENTER-NÄPPÄIN LISÄYSKENTTÄÄN
// Kun käyttäjä painaa Enter lisäyskentässä
// lomake lähetetään ilman sivulatausta
// ===========================================================
function setupEnterKey() {
    const input = document.querySelector('.input-area input');
    if (!input) return; // Jos kenttää ei löydy, lopetetaan
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault(); // Estetään oletustoiminto
            const form = document.querySelector('.input-area');
            if (form && form.requestSubmit) form.requestSubmit(); // Lähetetään lomake
            else if (form) form.submit();
        }
    });
}

// ===========================================================
// TEHTÄVÄN LISÄYSLOMAKE
// Kun käyttäjä painaa Lisää-nappia lähetetään tehtävä AJAXilla
// eikä sivua ladata uudelleen
// action=add tulee FormData:n mukana lomakkeen piilokenttänä
// ===========================================================
function setupFormSubmit() {
    const form = document.querySelector('form.input-area');
    if (!form) return;
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        // Lähetetään lomakedata POST-pyyntönä — action=add tulee piilokenttänä FormData:n mukana
        const res = await fetch('app/actions.php', {
            method: 'POST',
            body: new FormData(e.target)
        });

        const data = await res.json();

        // Virheilmoitus jos tehtävän lisäys epäonnistui
        if (!data.success) {
            const oldErr = document.querySelector('.auth-error');
            if (oldErr) oldErr.remove();
            const err = document.createElement('div');
            err.className = 'auth-error';
            err.textContent = data.error || 'Tehtävän lisääminen epäonnistui.';
            document.querySelector('h1').insertAdjacentElement('afterend', err);
            setTimeout(function() {
                err.style.transition = 'opacity 1s';
                err.style.opacity = '0';
                setTimeout(function() { err.remove(); }, 1000);
            }, 3000);
            return;
        }
        e.target.reset();

        await refreshTasks();

        // Animoidaan uusin tehtävä — ensimmäinen ei aloitetut -listassa
        const newest = document.querySelector('.task-list .task');
        if (newest) {
            newest.classList.add('anim-zombie-spawn');
        }
    });
}

// ===========================================================
// KURSORI LISÄYSKENTTÄÄN
// Siirretään kursori lisäyskenttään sivun latautuessa
// preventScroll estää selainta scrollaamasta kentän kohdalle
// ===========================================================
function focusInput() {
    const inp = document.querySelector('.input-area input');
    if (!inp) return;
    try { inp.focus({ preventScroll: true }); } catch(e) { inp.focus(); }
}

// ===========================================================
// MUOKKAUSMODAL — ALUSTUS
// Kiinnitetään modalin sulku- ja tallennusnapit
// Tämä ajetaan kerran sivun latautuessa
// ===========================================================
let currentEditId = null; // Muokattavan tehtävän id — let koska arvo muuttuu aina kun modal avataan

// Kiinnitetään modalin napit ja sulkemistoiminnot
function setupEditModal() {
    const overlay = document.getElementById('editModal');
    if (!overlay) return; // Jos modalia ei löydy, lopetetaan
    document.getElementById('modalClose').addEventListener('click', closeEditModal);   // Sulkee X-napista
    document.getElementById('modalCancel').addEventListener('click', closeEditModal);  // Sulkee Peruuta-napista
    document.getElementById('modalSave').addEventListener('click', saveEdit);          // Tallentaa muutokset
   
}

// Sulkee muokkausmodalin ja tyhjentää muokattavan tehtävän id:n
function closeEditModal() {
    const m = document.getElementById('editModal');
    if (m) m.classList.remove('open'); // Piilotetaan modal poistamalla open-luokka
    document.body.classList.remove('modal-open'); // Vapautetaan taustasivun skrolli
    currentEditId = null;
}

// Tallentaa muokatun tehtävän palvelimelle
async function saveEdit() {
    const text = document.getElementById('editText').value.trim(); // Luetaan teksti ja poistetaan välilyönnit
    if (!text) {
        document.getElementById('modalError').textContent = '⚠️ Tehtävän kuvaus ei voi olla tyhjä.';
        return; // Lopetetaan jos teksti on tyhjä
    }
    // Muuntaa Flatpickrin päivämäärän MySQL-muotoon YYYY-MM-DD HH:MM
    function fpToMySQL(fp) {
        if (!fp || !fp.selectedDates.length) return ''; // Jos päivämäärää ei ole valittu, palautetaan tyhjä
        const d = fp.selectedDates[0];
        const pad = function(n) { return n < 10 ? '0' + n : '' + n; }; // Lisätään nolla yksittäisten numeroiden eteen
        return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate())
             + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
    // Kootaan lähetettävä data URL-enkoodattuun muotoon
    const body = new URLSearchParams({
        text:       text,
        started_at: fpToMySQL(fpStarted), // Aloitusaika Flatpickrista MySQL-muodossa
        done_at:    fpToMySQL(fpDone)     // Valmistumisaika Flatpickrista MySQL-muodossa
    });
    // Lisätään toiminto ja tehtävän id POST-bodyyn — ei URL-parametreiksi
    body.append('action', 'edit_task');     // Toiminto kertoo actions.php:lle mitä tehdään
    body.append('id', currentEditId);       // Muokattavan tehtävän id
    // Lähetetään muutokset palvelimelle POST-pyyntönä
    const res = await fetch('app/actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCSRF() },
        body: body.toString()
    });
    const data = await res.json();
    if (data.success) { closeEditModal(); refreshTasks(); } // Suljetaan modal ja päivitetään lista
    else { document.getElementById('modalError').textContent = '⚠️ ' + (data.error || 'Tallentaminen epäonnistui.'); }
}

// ===========================================================
// FLATPICKR — alustetaan VASTA kun modal avataan
// ===========================================================
let fpStarted = null; // Flatpickr-olio aloitusajalle — let koska arvo asetetaan modalin avauksessa
let fpDone = null; // Flatpickr-olio valmistumisajalle — let koska arvo asetetaan modalin avauksessa
let fpOutsideClickHandler = null; // Tapahtumankuuntelija joka sulkee Flatpickrin kun klikataan sen ulkopuolelle — tallennetaan jotta voidaan poistaa se myöhemmin

// Sulkee Flatpickrin kun käyttäjä klikkaa sen ulkopuolelle
function closeFlatpickrOnOutsideClick(instance) {
    if (!instance) return;

    if (fpOutsideClickHandler) {
        document.removeEventListener('mousedown', fpOutsideClickHandler, true);
        fpOutsideClickHandler = null;
    }

    // Määritellään tapahtumankuuntelija joka tarkistaa klikataanko Flatpickrin ulkopuolelle
    fpOutsideClickHandler = function(e) {
        const calendar = instance.calendarContainer;
        const input    = instance.input;
        const altInput = instance.altInput;
        const clickedInsideCalendar = calendar && calendar.contains(e.target);
        const clickedInput = input && input.contains(e.target);
        const clickedAltInput = altInput && altInput.contains(e.target);

        if (!clickedInsideCalendar && !clickedInput && !clickedAltInput) {
            instance.close();
        }
    };

    document.addEventListener('mousedown', fpOutsideClickHandler, true); // Kuunnellaan hiiren klikkauksia ennen kuin ne saavuttavat muut elementit (true = capture-vaihe)
}

// Alustaa Flatpickr-kalenterit modalin aloitus- ja valmistumiskenttiin
function initFlatpickr() {
    const startedInput = document.getElementById('editStarted');
    const doneInput    = document.getElementById('editDone');

    if (!startedInput || !doneInput) return;

    // Yhteiset asetukset molemmille Flatpickreille
    const commonOpts = {
        enableTime: true,
        dateFormat: 'Y-m-d H:i',
        altInput: true,
        altFormat: 'd.m.Y H:i',
        time_24hr: true,
        allowInput: false,
        disableMobile: true,

        locale: {
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
        }
    };

    // Alustetaan Flatpickr vain jos sitä ei ole vielä alustettu, muuten vanhat päivämäärät katoavat modalista
    if (!fpStarted) {
        fpStarted = flatpickr(startedInput, Object.assign({}, commonOpts, {
            onOpen: [function() { closeFlatpickrOnOutsideClick(fpStarted); }],
            onClose: [function() {
                if (fpOutsideClickHandler) {
                    document.removeEventListener('mousedown', fpOutsideClickHandler, true);
                    fpOutsideClickHandler = null;
                }
            }]
        }));
    }

    // Sama aloitus- ja valmistumiskentille — molemmille oma Flatpickr-olio jotta päivämäärät eivät sekoitu modalissa
    if (!fpDone) {
        fpDone = flatpickr(doneInput, Object.assign({}, commonOpts, {
            onOpen: [function() { closeFlatpickrOnOutsideClick(fpDone); }],
            onClose: [function() {
                if (fpOutsideClickHandler) {
                    document.removeEventListener('mousedown', fpOutsideClickHandler, true);
                    fpOutsideClickHandler = null;
                }
            }]
        }));
    }
}

// ===========================================================
// Funktio joka avaa muokkausmodalin ja hakee tehtävän tiedot palvelimelta
// Tämä on erillinen funktio koska ✏️-nappi ei lähetä AJAX-pyyntöä vaan avaa modalin suoraan
// Ja modalin avaus tapahtuu ennen kuin AJAX-pyyntö on valmis, joten Flatpickr täytyy alustaa heti modalin avauksessa eikä AJAX-pyynnön jälkeen
// ===========================================================
async function openEditModal(id) {
    currentEditId = id;

    initFlatpickr(); // Alustetaan Flatpickr modalin avauksessa jotta se on varmasti valmis ennen kuin asetetaan päivämäärät

    document.getElementById('modalError').textContent = '';

    // Haetaan tehtävän tiedot palvelimelta POST-pyyntönä
    // Toiminto ja tehtävän id lähetetään POST-bodyssa — ei URL-parametreina
    const res = await fetch('app/actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': getCSRF()
        },
        body: 'action=get_task&id=' + id // Toiminto ja id POST-datana
    });

    const data = await res.json();
    if (!data.success) return;

    const t = data.task;

    document.getElementById('editText').value = t.text || '';

    if (fpStarted) {
        if (t.started_at) fpStarted.setDate(t.started_at, false);
        else fpStarted.clear();
    }

    if (fpDone) {
        if (t.done_at) fpDone.setDate(t.done_at, false);
        else fpDone.clear();
    }

    document.getElementById('editModal').classList.add('open');
    document.body.classList.add('modal-open'); // Lukitaan taustasivun skrolli modalin ajaksi

    setTimeout(function() {
        document.getElementById('editText').focus(); // Siirretään fokus kuvauskenttään jotta käyttäjä voi heti alkaa kirjoittaa
    }, 50);
}

// ===========================================================
// KÄYNNISTYS
// Kiinnitetään tapahtumat kun sivu on latautunut
// ===========================================================
attachTaskEvents(); // Kiinnitetään toimintonapit
setupEnterKey();    // Kiinnitetään Enter-näppäin
setupFormSubmit();  // Kiinnitetään lisäyslomake
focusInput();       // Siirretään kursori lisäyskenttään
setupEditModal();   // Kiinnitetään modalin toiminnot
