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

    // Poimitaan päivitetyt päätehtävävalikon optiot vastauksesta ja päivitetään
    // muokkausmodalin valikon varasto (template), jotta valikko pysyy ajan tasalla.
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const optsUpdate = tmp.querySelector('#parentOptionsUpdate');
    const store = document.getElementById('parentOptionsStore');
    if (optsUpdate && store) {
        // Template-elementin sisältö on .content-fragmentissa; luetaan se ja
        // asetetaan varaston sisällöksi. Fallback innerHTML jos content puuttuu.
        const fresh = optsUpdate.content ? optsUpdate.content : optsUpdate;
        store.innerHTML = '';
        Array.prototype.forEach.call(fresh.querySelectorAll ? fresh.querySelectorAll('option') : [], function(opt) {
            store.content.appendChild(opt.cloneNode(true));
        });
    }

    // Korvataan todo-boxin sisältö — lisäyslomake säilyy, tehtävälistat päivittyvät
    box.innerHTML = (form ? form.outerHTML : '') + html;

    // Kiinnitetään nappeihin tapahtumat uudelleen koska HTML vaihtui
    attachTaskEvents(); // Tehtävänappien tapahtumat täytyy kiinnittää uudestaan koska HTML on korvattu uudella
    setupEnterKey();
    setupFormSubmit();
    applySectionLimits(); // "Näytä lisää" -rajaus uudelleen jokaisen osion tuoreelle sisällölle — säilyttää laajennustilan (ks. sectionExpanded)

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
            showLoading(); // ui.js — näytetään latausindikaattori palvelinkutsun ajaksi
            try {
                await fetch('app/actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': getCSRF()
                    },
                    body: 'action=' + action + '&id=' + id // Toiminto ja id POST-datana URL:n sijaan
                });
                await refreshTasks(); // Päivitetään tehtävälista näytöllä
            } finally {
                hideLoading(); // Piilotetaan aina, myös virheen sattuessa
            }
        });
    });
}

// ===========================================================
// OSIOKOHTAINEN "NÄYTÄ LISÄÄ" — rajoittaa kunkin osion (Ei aloitetut /
// Käynnissä / Valmiit) oletuksena näkyvien tehtävien määrän, jottei sivu
// veny loputtomiin kun tehtäviä on paljon. Puhtaasti selainpuolen
// näyttölogiikkaa — palvelin lähettää kaikki tehtävät kuten ennenkin,
// JS vain piilottaa 6. tehtävästä eteenpäin per osio (.task-hidden, ks. style.css).
//
// Laajennustila (auki/kiinni) muistetaan sectionExpanded-oliossa osion
// section-title-luokan (not-started / in-progress / done-title) mukaan.
// Koska tämä on tasks.js:n moduulitason muuttuja eikä DOM:iin sidottu tila,
// se säilyy sellaisenaan yli refreshTasks()-kutsujen — vaikka koko
// .todo-boxin sisältö korvataan uudella HTML:llä, muuttuja itsessään ei
// katoa. applySectionLimits() kutsutaan uudelleen jokaisen refreshTasks()-
// päivityksen jälkeen (ks. yllä) ja sovittaa tallennetun laajennustilan
// tuoreeseen DOM:iin, joten laajennettu osio ei romahda takaisin viiteen
// kun tehtävän lisää/muokkaa/poistaa.
// ===========================================================
const SECTION_VISIBLE_LIMIT = 5;
const sectionExpanded = { 'not-started': false, 'in-progress': false, 'done-title': false };

// Tunnistaa .task-list-elementtiä edeltävän <h2 class="section-title ...">-otsikon
// perusteella mistä osiosta on kyse. Sama kolme luokkaa kaikissa tehtävälistan
// tuottavissa PHP-tiedostoissa (tasks.php ja partial-tasks.php).
function sectionKeyFor(list) {
    const heading = list.previousElementSibling;
    if (!heading) return null;
    if (heading.classList.contains('not-started')) return 'not-started';
    if (heading.classList.contains('in-progress')) return 'in-progress';
    if (heading.classList.contains('done-title')) return 'done-title';
    return null;
}

// Piilottaa/näyttää yhden osion tehtävät nykyisen laajennustilan mukaan ja
// lisää/poistaa/päivittää osion oman "Näytä lisää" -napin tarpeen mukaan.
function applySectionLimit(list) {
    const key = sectionKeyFor(list);
    if (!key) return;

    // Poistetaan edellinen nappi ennen uudelleenlaskentaa — lisätään tarvittaessa uusi alle
    const existingBtn = list.nextElementSibling;
    if (existingBtn && existingBtn.classList && existingBtn.classList.contains('section-toggle-btn')) {
        existingBtn.remove();
    }

    // Vain tämän osion suorat .task-elementit — alitehtävät näkyvät kortin
    // sisällä tageina (tm_cardMeta), eivät omina .task-riveinään, joten
    // sisäkkäisyyttä ei tarvitse huomioida.
    const tasks = Array.prototype.filter.call(list.children, function(el) { return el.classList.contains('task'); });
    const total = tasks.length;

    if (total <= SECTION_VISIBLE_LIMIT) {
        tasks.forEach(function(t) { t.classList.remove('task-hidden'); }); // Kaikki näkyvät, ei nappia
        return;
    }

    const expanded = !!sectionExpanded[key];
    tasks.forEach(function(t, i) {
        t.classList.toggle('task-hidden', !expanded && i >= SECTION_VISIBLE_LIMIT);
    });

    const hiddenCount = total - SECTION_VISIBLE_LIMIT;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'section-toggle-btn';
    btn.textContent = expanded ? 'Näytä vähemmän 🪦' : 'Näytä lisää (' + hiddenCount + ') 🧟';
    btn.addEventListener('click', function() {
        sectionExpanded[key] = !sectionExpanded[key];
        applySectionLimit(list);
    });
    list.insertAdjacentElement('afterend', btn);
}

// Sovittaa "Näytä lisää" -rajauksen kaikkiin kolmeen osioon. Kutsutaan sivun
// alkulatauksessa (ks. KÄYNNISTYS) ja jokaisen refreshTasks()-päivityksen jälkeen.
function applySectionLimits() {
    document.querySelectorAll('.todo-box > .task-list').forEach(applySectionLimit);
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
        showLoading(); // ui.js — näytetään latausindikaattori tehtävän lisäyksen ajaksi
        try {
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
        } finally {
            hideLoading(); // Piilotetaan aina, myös virheen sattuessa
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

    // Päätehtävävalikon overlay-teksti päivittyy heti kun käyttäjä valitsee toisen
    // vaihtoehdon avatusta pudotusvalikosta (ks. updateParentLabel).
    const parentSelEl = document.getElementById('editParent');
    if (parentSelEl) parentSelEl.addEventListener('change', updateParentLabel);

    // Klikkaus taustan päälle sulkee modalin — sama tapa kuin legal-modaaleissa
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeEditModal(); // Sulkee vain jos klikataan taustaa, ei modalin sisältöä
    });

    // ESC-näppäin sulkee modalin — sama tapa kuin legal-modaaleissa
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && overlay.classList.contains('open')) {
            closeEditModal(); // Suljetaan vain jos modal on auki
        }
    });
}

// Päivittää #editParentLabel-overlayn tekstin valitun option-tekstin mukaan.
// Option-teksti on tahallaan sisennetty (nbsp-merkit, ks. tm_parentOptions) —
// tästä poistetaan vain alun sisennys, ↳-etuliite jätetään näkyviin. textContent
// on turvallinen (ei innerHTML), joten mitään ei tarvitse erikseen escapata.
function updateParentLabel() {
    const sel = document.getElementById('editParent');
    const label = document.getElementById('editParentLabel');
    if (!sel || !label) return;
    const opt = sel.options[sel.selectedIndex];
    const raw = opt ? opt.textContent : '';
    label.textContent = raw.replace(/^ +/, '');
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
    // Luetaan tunnit ja päätehtävä muokkausmodalista
    const hours  = document.getElementById('editHours').value.trim();
    const parent = document.getElementById('editParent').value;
    // Kootaan lähetettävä data URL-enkoodattuun muotoon
    const body = new URLSearchParams({
        text:       text,
        hours:      hours,                // Käsin syötetyt tunnit
        parent_id:  parent,               // Päätehtävän valinta (0 = ei päätehtävää)
        started_at: fpToMySQL(fpStarted), // Aloitusaika Flatpickrista MySQL-muodossa
        done_at:    fpToMySQL(fpDone)     // Valmistumisaika Flatpickrista MySQL-muodossa
    });
    // Lisätään toiminto ja tehtävän id POST-bodyyn — ei URL-parametreiksi
    body.append('action', 'edit_task');     // Toiminto kertoo actions.php:lle mitä tehdään
    body.append('id', currentEditId);       // Muokattavan tehtävän id
    // Lähetetään muutokset palvelimelle POST-pyyntönä
    showLoading(); // ui.js — näytetään latausindikaattori tallennuksen ajaksi
    try {
        const res = await fetch('app/actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCSRF() },
            body: body.toString()
        });
        const data = await res.json();
        if (data.success) { closeEditModal(); await refreshTasks(); } // Suljetaan modal ja päivitetään lista
        else { document.getElementById('modalError').textContent = '⚠️ ' + (data.error || 'Tallentaminen epäonnistui.'); }
    } finally {
        hideLoading(); // Piilotetaan aina, myös virheen sattuessa
    }
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
    showLoading(); // ui.js — näytetään latausindikaattori haun ajaksi
    let data;
    try {
        const res = await fetch('app/actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': getCSRF()
            },
            body: 'action=get_task&id=' + id // Toiminto ja id POST-datana
        });
        data = await res.json();
    } finally {
        hideLoading(); // Piilotetaan aina, myös virheen sattuessa
    }
    if (!data.success) return;

    const t = data.task;

    document.getElementById('editText').value = t.text || '';

    // Täytetään tunnit — 0 näytetään tyhjänä, piste pilkuksi
    const hoursVal = parseFloat(t.hours || 0);
    document.getElementById('editHours').value = hoursVal ? String(hoursVal).replace('.', ',') : '';

    // Päätehtävävalikko: rakennetaan optiot uudelleen varastosta (template) joka avauksella,
    // ja jätetään muokattava tehtävä itse pois (ei voi olla oma päätehtävänsä).
    const parentSel = document.getElementById('editParent');
    const store = document.getElementById('parentOptionsStore');
    parentSel.innerHTML = ''; // Tyhjennetään edellinen sisältö
    Array.prototype.forEach.call(store.content.querySelectorAll('option'), function(opt) {
        if (opt.getAttribute('data-id') === String(id)) return; // Ohitetaan tehtävä itse
        parentSel.appendChild(opt.cloneNode(true)); // Kopioidaan optio valikkoon
    });
    parentSel.value = (t.parent_id !== null && t.parent_id !== undefined) ? String(t.parent_id) : '0';
    updateParentLabel(); // Päivitetään suljetun napin siisti (sisennykseton) teksti valinnan mukaan

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
applySectionLimits(); // "Näytä lisää" -rajaus osioihin heti alkulatauksessa
focusInput();       // Siirretään kursori lisäyskenttään
setupEditModal();   // Kiinnitetään modalin toiminnot
setupKoosteModal(); // Kiinnitetään koostemodalin toiminnot
setupOperationBar(); // Kiinnitetään operaatiovalitsimen ja -modalin toiminnot

// ===========================================================
// OPERAATIOVALITSIN — pudotusvalikko aktiivisen operaation valintaan,
// sekä uuden operaation luonti/muokkaus/poisto samantyylisessä modalissa
// kuin tehtävän muokkausmodal.
// ===========================================================
let operationEditingId = null; // null = luodaan uusi, muuten muokataan tätä id:tä

// Päivittää valitsimen vasemman reunan väripalkin (+ hienovarainen hehku) valitun operaation värin mukaan.
// Sama idea kuin tehtäväkorttien alitehtäväsirujen vasemman reunan statusväri.
function updateOperationColor() {
    const select = document.getElementById('operationSelect');
    if (!select) return;
    const opt = select.options[select.selectedIndex];
    const color = (opt && opt.dataset.color) ? opt.dataset.color : '#880000';
    // CSP-turvallinen: JS asettaa värin CSSOM:n kautta (el.style), ei inline-attribuuttina HTML:ssä
    select.style.borderLeftColor = color;
    select.style.boxShadow = '0 0 6px ' + color;
}

// Avaa operaatiomodalin joko tyhjänä (uusi) tai esitäytettynä (muokkaus)
function openOperationModal(mode, data) {
    operationEditingId = mode === 'edit' ? data.id : null;
    document.getElementById('operationModalTitle').textContent = mode === 'edit' ? '✏️ Muokkaa operaatiota' : '🧟 Uusi operaatio';
    document.getElementById('operationModalError').textContent = '';
    document.getElementById('opName').value = mode === 'edit' ? (data.name || '') : '';
    document.getElementById('opDescription').value = mode === 'edit' ? (data.description || '') : '';
    // Uudelle operaatiolle ei oleteta valmiiksi väriä (harmaa placeholder, ei valittua palikkaa);
    // muokattaessa käytetään operaation nykyistä väriä sellaisenaan (tyhjä = ei väriä asetettu).
    const color = mode === 'edit' ? (data.color || '') : '';
    document.getElementById('opColorHex').value = color;
    markSelectedSwatch(color);
    updateColorPreview(color);

    document.getElementById('operationModal').classList.add('open');
    document.body.classList.add('modal-open');
    setTimeout(function() { document.getElementById('opName').focus(); }, 50);
}

function closeOperationModal() {
    const m = document.getElementById('operationModal');
    if (m) m.classList.remove('open');
    document.body.classList.remove('modal-open');
    operationEditingId = null;
}

// Merkitsee valitun väripalikan aktiiviseksi (luokan kautta, ei inline-tyylinä)
function markSelectedSwatch(color) {
    document.querySelectorAll('#opColorRow .op-swatch').forEach(function(sw) {
        sw.classList.toggle('selected', sw.dataset.color.toLowerCase() === String(color).toLowerCase());
    });
}

// Päivittää hex-kentän vieressä olevan esikatselupalikan värin. CSP-turvallinen:
// väri asetetaan JS:llä el.style-kautta, ei inline-attribuuttina HTML:ssä.
// Jos arvo ei täsmää hex-muotoon, esikatselu jätetään ennalleen (himmeäksi) eikä kaadeta mitään.
function updateColorPreview(color) {
    const preview = document.getElementById('opColorPreview');
    if (!preview) return;
    if (/^#[0-9a-fA-F]{6}$/.test(color)) {
        preview.style.backgroundColor = color;
    } else {
        preview.style.backgroundColor = '#777777'; // Harmaa oletus (sama sävy kuin placeholder) kun väriä ei ole (vielä) asetettu
    }
}

// Tallentaa uuden tai muokatun operaation palvelimelle
async function saveOperation() {
    const name = document.getElementById('opName').value.trim();
    if (!name) {
        document.getElementById('operationModalError').textContent = '⚠️ Operaation nimi ei voi olla tyhjä.';
        return;
    }
    const description = document.getElementById('opDescription').value.trim();
    // Hex-kentän arvo lähetetään sellaisenaan — palvelin validoi muodon (^#[0-9a-fA-F]{6}$)
    // ja hylkää virheellisen värin; tyhjä kenttä tallentuu "ei väriä" (NULL).
    const color = document.getElementById('opColorHex').value.trim();

    const body = new URLSearchParams({ name: name, description: description, color: color });
    body.append('action', operationEditingId ? 'edit_operation' : 'add_operation');
    if (operationEditingId) body.append('id', operationEditingId);

    showLoading(); // ui.js — näytetään latausindikaattori tallennuksen ajaksi
    let data;
    try {
        const res = await fetch('app/actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCSRF() },
            body: body.toString()
        });
        data = await res.json();
    } finally {
        hideLoading(); // Piilotetaan aina, myös virheen sattuessa — ei peitä onnistumisen jälkeistä sivulatausta
    }
    if (data.success) {
        closeOperationModal();
        window.location.reload(); // Yksinkertaisin tapa päivittää valikko, tehtävälistat ja otsikot ajan tasalle
    } else {
        document.getElementById('operationModalError').textContent = '⚠️ ' + (data.error || 'Tallentaminen epäonnistui.');
    }
}

// Poistaa aktiivisen operaation — sallittu vain jos se on tyhjä (palvelin varmistaa)
async function deleteActiveOperation() {
    const select = document.getElementById('operationSelect');
    if (!select) return;
    const id = select.value;

    showLoading(); // ui.js — näytetään latausindikaattori poiston ajaksi
    let data;
    try {
        const res = await fetch('app/actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCSRF() },
            body: 'action=delete_operation&id=' + encodeURIComponent(id)
        });
        data = await res.json();
    } finally {
        hideLoading(); // Piilotetaan aina, myös virheen sattuessa — jättää tilaa veriroiske-animaatiolle onnistuessa
    }
    if (data.success) {
        // Sama koko sivun veriroiske-animaatio kuin tehtävän poistossa (ks. attachTaskEvents)
        const overlay = document.getElementById('bloodOverlay');
        const bar = document.querySelector('.operation-bar');
        if (overlay) {
            overlay.classList.remove('active');
            overlay.offsetHeight; // Pakotetaan selain huomaamaan muutos
            overlay.classList.add('active');
        }
        if (bar) {
            bar.style.transition = 'opacity 0.4s';
            bar.style.opacity = '0';
        }
        await new Promise(function(resolve) { setTimeout(resolve, 1000); });
        window.location.reload();
    } else {
        const oldErr = document.querySelector('.auth-error');
        if (oldErr) oldErr.remove();
        const err = document.createElement('div');
        err.className = 'auth-error';
        err.textContent = data.error || 'Operaation poisto epäonnistui.';
        document.querySelector('h1').insertAdjacentElement('afterend', err);
        setTimeout(function() {
            err.style.transition = 'opacity 1s';
            err.style.opacity = '0';
            setTimeout(function() { err.remove(); }, 1000);
        }, 3000);
    }
}

function setupOperationBar() {
    const select = document.getElementById('operationSelect');
    const editBtn = document.getElementById('operationEditBtn');
    const deleteBtn = document.getElementById('operationDeleteBtn');
    const createFirstBtn = document.getElementById('operationCreateFirst');
    const overlay = document.getElementById('operationModal');

    if (select) {
        updateOperationColor();
        let previousValue = select.value;

        select.addEventListener('change', async function() {
            if (select.value === '__new__') {
                select.value = previousValue; // Palautetaan valikko ennalleen — modal hoitaa luonnin
                openOperationModal('create', {});
                return;
            }
            showLoading(); // ui.js — näytetään latausindikaattori operaation vaihdon ajaksi
            let data;
            try {
                const res = await fetch('app/actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCSRF() },
                    body: 'action=set_operation&operation_id=' + encodeURIComponent(select.value)
                });
                data = await res.json();
            } finally {
                hideLoading(); // Piilotetaan aina — onnistuessa sivu latautuu joka tapauksessa heti uudelleen
            }
            if (data.success) {
                previousValue = select.value;
                window.location.reload(); // Vaihdetaan koko näkymä uuden operaation tehtäviin
            } else {
                select.value = previousValue; // Epäonnistui — palautetaan edellinen valinta
            }
        });
    }

    if (editBtn) {
        editBtn.addEventListener('click', function() {
            if (!select) return;
            const opt = select.options[select.selectedIndex];
            if (!opt || opt.value === '__new__') return;
            openOperationModal('edit', {
                id: opt.value,
                name: opt.textContent.trim(),
                description: opt.dataset.description || '',
                color: opt.dataset.color || '#cc0000'
            });
        });
    }

    if (deleteBtn) {
        deleteBtn.addEventListener('click', deleteActiveOperation);
    }

    if (createFirstBtn) {
        createFirstBtn.addEventListener('click', function() { openOperationModal('create', {}); });
    }

    if (overlay) {
        document.getElementById('operationModalClose').addEventListener('click', closeOperationModal);
        document.getElementById('operationModalCancel').addEventListener('click', closeOperationModal);
        document.getElementById('operationModalSave').addEventListener('click', saveOperation);
        overlay.addEventListener('click', function(e) { if (e.target === overlay) closeOperationModal(); });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && overlay.classList.contains('open')) closeOperationModal();
        });

        document.querySelectorAll('#opColorRow .op-swatch').forEach(function(sw) {
            sw.addEventListener('click', function() {
                document.getElementById('opColorHex').value = sw.dataset.color;
                markSelectedSwatch(sw.dataset.color);
                updateColorPreview(sw.dataset.color);
            });
        });
        document.getElementById('opColorHex').addEventListener('input', function() {
            markSelectedSwatch(this.value);
            updateColorPreview(this.value);
        });
    }
}

// ===========================================================
// KOOSTEMODAL — avautuu koostebkoksin napista
// Hakee tehtävät summary-actionista ja rakentaa tuntikoosteen + aikajanan.
// ===========================================================
function k_fmtHours(h) { h = Math.round(parseFloat(h || 0) * 100) / 100; return String(h).replace('.', ','); }

function k_fmtDur(secs) {
    const mins = Math.round(secs / 60), h = Math.floor(mins / 60), m = mins % 60;
    if (h > 0 && m > 0) return h + ' t ' + m + ' min';
    if (h > 0) return h + ' t';
    return m + ' min';
}

function k_esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

function k_subtree(id, childrenByParent, byId, visited) {
    visited = visited || {};
    if (visited[id]) return 0; // Silmukkasuoja — ei lasketa samaa kahdesti
    visited[id] = true;
    let sum = parseFloat(byId[id].hours || 0);
    (childrenByParent[id] || []).forEach(function(k) { sum += k_subtree(k.id, childrenByParent, byId, visited); });
    return sum;
}

// Piirtää tehtävän kaikki alitehtävät rekursiivisesti rajattomaan syvyyteen.
// $depth kasvaa tasoittain — käytetään sisennykseen (data-depth, ks. k_build:in loppu).
// $visited on sama silmukkasuoja-periaate kuin k_subtreessä — estää ikuisen luupin.
function k_renderChildren(parentId, depth, childrenByParent, byId, visited) {
    let html = '';
    (childrenByParent[parentId] || []).forEach(function(kid) {
        if (visited[kid.id]) return; // Silmukkasuoja — sama tehtävä ei toistu
        visited[kid.id] = true;
        html += '<div class="kooste-subrow" data-depth="' + depth + '"><span class="kooste-subname">↳ ' + k_esc(kid.text) +
                '</span><span class="kooste-subhours">' + k_fmtHours(kid.hours) + ' h</span></div>';
        html += k_renderChildren(kid.id, depth + 1, childrenByParent, byId, visited);
    });
    return html;
}

function k_build(tasks) {
    const byId = {}, childrenByParent = {};
    tasks.forEach(function(t) { byId[t.id] = t; });
    tasks.forEach(function(t) {
        if (t.parent_id !== null && t.parent_id !== undefined)
            (childrenByParent[t.parent_id] = childrenByParent[t.parent_id] || []).push(t);
    });
    const roots = tasks.filter(function(t) { return t.parent_id === null || t.parent_id === undefined; });

    let grand = 0;
    roots.forEach(function(r) { grand += k_subtree(r.id, childrenByParent, byId); });

    let html = '<div class="kooste-total">Selviytymisaika yhteensä: <strong>' + k_fmtHours(grand) + ' h</strong></div>';

    html += '<h3 class="kooste-heading in-progress">⏳ Tuntikooste</h3>';
    if (roots.length === 0) {
        html += '<p class="empty-hint">Ei tehtäviä koostettavaksi.</p>';
    } else {
        html += '<div class="kooste-list">';
        roots.forEach(function(root) {
            const total = k_fmtHours(k_subtree(root.id, childrenByParent, byId));
            html += '<div class="kooste-row"><div class="kooste-name">' + k_esc(root.text) + '</div><div class="kooste-hours">';
            html += '<span class="k-total">' + total + ' h</span>';
            html += '</div></div>';
            // Silmukkasuoja per juuri — päätehtävä merkitään käsitellyksi ennen alipuun piirtoa
            const visited = {}; visited[root.id] = true;
            const childrenHtml = k_renderChildren(root.id, 1, childrenByParent, byId, visited);
            if (childrenHtml) {
                html += '<div class="kooste-children">' + childrenHtml + '</div>';
            }
        });
        html += '</div>';
    }

    html += '<h3 class="kooste-heading done-title">🩸 Aikajana</h3>';
    const DAY = 24 * 60 * 60 * 1000; // Yksi päivä millisekunteina
    const DAY_PX = 60;               // Yhden päivän leveys pikseleinä aikajanalla
    // Nollaa kellonajan — palauttaa päivän alun (keskiyö) millisekunteina
    const dayStart = function(str) {
        const d = new Date(str.replace(' ', 'T'));
        return new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
    };
    const tl = [];
    let minDay = null, maxDay = null;
    tasks.forEach(function(t) {
        if (!t.started_at) return; // Ilman aloituspäivää ei sijoiteta aikajanalle
        const startDay = dayStart(t.started_at);
        // Loppupäivä: valmistumispäivä jos on, muuten tänään. Inklusiivinen (+1 päivä leveyteen).
        const endDay = t.done_at ? dayStart(t.done_at) : dayStart(new Date().toISOString());
        tl.push({
            id: t.id,
            text: t.text,
            status: t.status,
            hours: k_subtree(t.id, childrenByParent, byId), // Sama summautunut tuntimäärä kuin tuntikoosteessa (omat + kaikki alitehtävät)
            startDay: startDay,
            endDay: Math.max(startDay, endDay),
            doneAt: t.done_at ? dayStart(t.done_at) : null // Järjestystä varten — ks. tl.sort alla
        });
        if (minDay === null || startDay < minDay) minDay = startDay;
        if (maxDay === null || endDay > maxDay) maxDay = endDay;
    });

    // Järjestys: käynnissä olevat ylimpänä (uusin aloitettu ensin), valmiit alempana
    // (uusin valmistunut ensin). Aloittamattomat eivät koskaan päädy tänne (ks. yllä).
    tl.sort(function(a, b) {
        if (a.status !== b.status) {
            if (a.status === 'in_progress') return -1;
            if (b.status === 'in_progress') return 1;
            return 0;
        }
        if (a.status === 'in_progress') {
            return b.startDay - a.startDay; // Suurempi (uudempi) startDay ensin
        }
        const aDone = a.doneAt !== null ? a.doneAt : a.endDay;
        const bDone = b.doneAt !== null ? b.doneAt : b.endDay;
        return bDone - aDone; // Suurempi (uudempi) valmistumispäivä ensin
    });

    if (tl.length === 0) {
        html += '<p class="empty-hint">Ei aloitettuja tehtäviä aikajanalle.</p>';
    } else {
        const spanDays = Math.round((maxDay - minDay) / DAY) + 1; // Päivien lukumäärä (inklusiivinen)
        const totalWidth = spanDays * DAY_PX;                     // Aikajanan kokonaisleveys pikseleinä
        const fd = function(ts) { const d = new Date(ts), p = function(n){return n<10?'0'+n:n;}; return p(d.getDate())+'.'+p(d.getMonth()+1)+'.'; };

        // Sisältö kääritään vaakasuunnassa vieritettävään laatikkoon
        html += '<div class="timeline-scroll"><div class="timeline" data-width="' + totalWidth + '">';

        // Päivämääräotsikot: yksi sarake per päivä
        html += '<div class="tl-days">';
        for (let d = 0; d < spanDays; d++) {
            html += '<div class="tl-day" data-i="' + d + '">' + fd(minDay + d * DAY) + '</div>';
        }
        html += '</div>';

        // Tehtävärivit — palkki sijoitetaan pikseleinä (CSP: leveys asetetaan JS:llä DOM:iin)
        tl.forEach(function(t, i) {
            const startOffsetDays = Math.round((t.startDay - minDay) / DAY);
            const durationDays = Math.round((t.endDay - t.startDay) / DAY) + 1; // Inklusiivinen
            const leftPx  = startOffsetDays * DAY_PX;
            const widthPx = durationDays * DAY_PX;
            const label = k_fmtHours(t.hours) + ' h'; // Palkin sisällä vain tunnit
            const barTip = fd(t.startDay) + '–' + fd(t.endDay); // Hover: pelkkä päivämääräväli — tunnit näkyvät jo palkissa
            const tipCenter = leftPx + Math.round(widthPx / 2); // Vihje keskitetään palkin päälle
            // .tl-bar:lla on overflow:hidden (rajaa sisällä olevan tuntitekstin), joten teemavihje
            // ei voi olla sen oma pseudoelementti — se on sen sijaan sisarelementti .tl-track:ssa,
            // samaa tyyliä kuin toimintonappien [data-tooltip] (ks. tyylitiedoston "10. TOIMINTONAPPIEN TOOLTIP").
            html += '<div class="tl-item">' +
                    '<div class="tl-label status-' + k_esc(t.status) + '">' + k_esc(t.text) + '</div>' +
                    '<div class="tl-track" data-width="' + totalWidth + '">' +
                    '<div class="tl-bar status-' + k_esc(t.status) + '" data-left="' + leftPx +
                    '" data-barwidth="' + widthPx +
                    '"><span class="tl-dur">' + label + '</span></div>' +
                    '<div class="tl-bar-tip" data-left="' + tipCenter + '">' + k_esc(barTip) + '</div>' +
                    '</div></div>';
        });
        html += '</div></div>';

        // Tulostusta varten: sama aikajana yksinkertaisena listana (näkyy vain printissä).
        // Ei palkkeja eikä scrollia — kietoutuu luonnostaan usealle riville/sivulle.
        html += '<div class="timeline-print">';
        tl.forEach(function(t) {
            const range = fd(t.startDay) + '–' + fd(t.endDay);
            html += '<div class="tlp-row status-' + k_esc(t.status) + '">' +
                    '<span class="tlp-name">' + k_esc(t.text) + '</span>' +
                    '<span class="tlp-range">' + range + '</span>' +
                    '<span class="tlp-hours">' + k_fmtHours(t.hours) + ' h</span></div>';
        });
        html += '</div>';
    }

    document.getElementById('koosteContent').innerHTML = html;

    // Asetetaan leveydet, sisennykset ja palkkien sijainnit DOM:in kautta (CSP-sallittua, toisin kuin
    // style-attribuutti HTML-merkkijonossa).
    // Tuntikoosteen alitehtävien sisennys — syvyys tulee data-depth-attribuutista (ks. k_renderChildren).
    // Rajaton syvyys, joten marginaali lasketaan JS:llä eikä kiinteillä CSS-luokilla.
    document.querySelectorAll('#koosteContent .kooste-subrow[data-depth]').forEach(function(el) {
        const depth = parseInt(el.getAttribute('data-depth'), 10) || 1;
        el.style.marginLeft = (depth * 18) + 'px';
    });
    document.querySelectorAll('#koosteContent .timeline, #koosteContent .tl-track').forEach(function(el) {
        const w = el.getAttribute('data-width');
        if (w) el.style.width = w + 'px';
    });
    document.querySelectorAll('#koosteContent .tl-days .tl-day').forEach(function(el) {
        el.style.width = DAY_PX + 'px';
    });
    document.querySelectorAll('#koosteContent .tl-bar').forEach(function(bar) {
        bar.style.left  = bar.getAttribute('data-left') + 'px';
        bar.style.width = bar.getAttribute('data-barwidth') + 'px';
    });
    document.querySelectorAll('#koosteContent .tl-bar-tip').forEach(function(tip) {
        // Keskitetään palkin päälle, mutta rajataan näkyvän vierityskehyksen (.timeline-scroll)
        // sisään ettei vihje leikkaudu sen reunalla. Rajaus lasketaan .timeline-scroll:n
        // leveyden mukaan eikä .tl-track:n oman data-width:n mukaan, koska .tl-track itse ei
        // rajaa mitään (vain .timeline-scroll:lla on overflow) — lyhyen aikajanan (vähän
        // päiviä) track voi olla kapeampi kuin itse vihjeteksti, jolloin trackWidth-halfW
        // menisi halfW:n alle ja kääntäisi rajauksen väärinpäin (vihje työntyisi reunan yli).
        const center = parseInt(tip.getAttribute('data-left'), 10) || 0;
        const scrollEl = tip.closest('.timeline-scroll');
        const halfW = tip.offsetWidth / 2;
        let left = center;
        if (scrollEl) {
            const viewMin = scrollEl.scrollLeft + halfW;
            const viewMax = Math.max(viewMin, scrollEl.scrollLeft + scrollEl.clientWidth - halfW);
            left = Math.min(Math.max(center, viewMin), viewMax);
        }
        tip.style.left = left + 'px';
    });
    return; // k_build päättyy tähän — sisältö on jo asetettu
}

async function openKooste() {
    const content = document.getElementById('koosteContent');
    content.innerHTML = '<p class="empty-hint">Ladataan…</p>';

    // Näytetään aktiivisen operaation nimi koosteen otsikossa — luetaan valitsimesta,
    // joka on jo sivulla (ei erillistä palvelinkutsua).
    const opNameEl = document.getElementById('koosteOpName');
    if (opNameEl) {
        const select = document.getElementById('operationSelect');
        const opt = select ? select.options[select.selectedIndex] : null;
        const opText = opt ? opt.text : '';
        opNameEl.textContent = opText; // textContent escapee automaattisesti, ei tarvita k_escia
        opNameEl.hidden = !opText;
        // Sama väri kuin operaatiovalitsimen reunapalkissa (ks. updateOperationColor) —
        // CSP-turvallinen: väri tulee jo sivulla olevasta data-color-attribuutista, asetetaan el.style:llä.
        const color = (opt && opt.dataset.color) ? opt.dataset.color : '#cc8833';
        opNameEl.style.borderLeftColor = color;
        opNameEl.style.boxShadow = '0 0 6px ' + color;
    }

    document.getElementById('koosteModal').classList.add('open');
    document.body.classList.add('modal-open');
    showLoading(); // ui.js — näytetään latausindikaattori koosteen haun ajaksi
    try {
        const res = await fetch('app/actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCSRF() },
            body: 'action=summary'
        });
        const data = await res.json();
        if (data.success) k_build(data.tasks);
        else content.innerHTML = '<p class="empty-hint">Koosteen lataus epäonnistui.</p>';
    } catch (e) {
        content.innerHTML = '<p class="empty-hint">Koosteen lataus epäonnistui.</p>';
    } finally {
        hideLoading(); // Piilotetaan aina, myös virheen sattuessa
    }
}

function closeKooste() {
    const m = document.getElementById('koosteModal');
    if (m) m.classList.remove('open');
    document.body.classList.remove('modal-open');
}

function setupKoosteModal() {
    const openBtn = document.getElementById('openKooste');
    const overlay = document.getElementById('koosteModal');
    if (!openBtn || !overlay) return;
    openBtn.addEventListener('click', openKooste);
    document.getElementById('koosteClose').addEventListener('click', closeKooste);
    document.getElementById('koosteCancel').addEventListener('click', closeKooste);
    const printBtn = document.getElementById('koostePrint');
    if (printBtn) printBtn.addEventListener('click', function() { window.print(); }); // Selaimen tulostus → voi tallentaa PDF:ksi
    overlay.addEventListener('click', function(e) { if (e.target === overlay) closeKooste(); });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && overlay.classList.contains('open')) closeKooste();
    });
}