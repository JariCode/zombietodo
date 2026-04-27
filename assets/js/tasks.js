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
// TEHTÄVÄLISTAN PÄIVITYS AJAXilla
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
    attachTaskEvents();
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
// Nappi lähettää AJAXilla toiminnon palvelimelle ja päivittää listan
// ===========================================================
function attachTaskEvents() {
    document.querySelectorAll('.actions button').forEach(function(el) {
        el.addEventListener('click', async function(e) {
            e.preventDefault();  // Estetään oletustoiminto
            e.stopPropagation(); // Estetään tapahtuman kupliminen ylöspäin
            const action = el.dataset.action; // Luetaan mitä toimintoa nappi tekee — esim. 'start', 'delete'
            const id     = el.dataset.id;     // Luetaan minkä tehtävän id on kyseessä
            if (action === 'edit') { openEditModal(id); return; } // Muokkausnappi avaa modalin eikä lähetä pyyntöä
            // Lähetetään toiminto palvelimelle POST-pyyntönä
            // CSRF-token lähetetään headerissa koska actions.php vaatii sen
            await fetch('app/actions.php?action=' + action + '&id=' + id, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': getCSRF()
                },
                body: ''
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
// ===========================================================
function setupFormSubmit() {
    const form = document.querySelector('form.input-area');
    if (!form) return; // Jos lomaketta ei löydy, lopetetaan
    form.addEventListener('submit', async function(e) {
        e.preventDefault(); // Estetään lomakkeen normaali lähetys joka lataisi sivun uudelleen
        await fetch('app/actions.php?action=add', {
            method: 'POST',
            body: new FormData(e.target) // FormData kerää lomakkeen kentät automaattisesti
        });
        e.target.reset();  // Tyhjennetään tekstikenttä lisäyksen jälkeen
        refreshTasks();    // Päivitetään tehtävälista
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
// MUOKKAUSMODAL
// Modal avautuu kun käyttäjä klikkaa ✏️-nappia
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
    // Lähetetään muutokset palvelimelle
    const res = await fetch('app/actions.php?action=edit_task&id=' + currentEditId, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCSRF() },
        body: body.toString()
    });
    const data = await res.json();
    if (data.success) { closeEditModal(); refreshTasks(); } // Suljetaan modal ja päivitetään lista
    else { document.getElementById('modalError').textContent = '⚠️ Tallentaminen epäonnistui.'; }
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

// ===========================================================
// FLATPICKR — alustetaan VASTA kun modal avataan
// ===========================================================
let fpStarted = null;
let fpDone = null;
let fpOutsideClickHandler = null;

function closeFlatpickrOnOutsideClick(instance) {
    if (!instance) return;

    if (fpOutsideClickHandler) {
        document.removeEventListener('mousedown', fpOutsideClickHandler, true);
        fpOutsideClickHandler = null;
    }

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

    document.addEventListener('mousedown', fpOutsideClickHandler, true);
}

function initFlatpickr() {
    const startedInput = document.getElementById('editStarted');
    const doneInput    = document.getElementById('editDone');

    if (!startedInput || !doneInput) return;

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

    // 🔥 estetään tupla-init
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

    initFlatpickr(); // 🔥 TÄRKEIN KORJAUS

    document.getElementById('modalError').textContent = '';

    const res = await fetch('app/actions.php?action=get_task&id=' + id, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': getCSRF()
        },
        body: ''
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
        document.getElementById('editText').focus();
    }, 50);
}
