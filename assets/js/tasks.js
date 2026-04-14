'use strict';
// ================================
// tasks.js
//
// Tämä tiedosto hoitaa tehtävälistan
// toiminnan selaimessa.
//
// Huolehtii seuraavista:
// - Uuden tehtävän lisääminen
// - Tehtävän poistaminen
// - Tehtävän tilan vaihto (ei aloitettu,
//   käynnissä, valmis)
//
// Tehtävälista päivittyy automaattisesti
// ilman että koko sivu latautuu uudelleen.
//
// 'use strict' tarkoittaa että JavaScript
// toimii tiukemmassa tilassa ja ilmoittaa
// virheistä herkemmin — hyvä käytäntö.
// ================================

// Haetaan CSRF-token <meta name="csrf-token"> -tagista
// tasks.php lisää sen sivun <head>-osioon
function getCSRF() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// ---- Tehtävälistan päivitys AJAXilla ----
// Hakee partial-tasks.php:ltä päivitetyn tehtävälistan
// ja korvaa sivun sisällön sillä — sivu ei lataudu uudelleen
async function refreshTasks() {
    const prevScroll = window.scrollY; // Tallennetaan scroll-positio ennen päivitystä
    const box = document.querySelector('.todo-box');
    if (!box) return;

    // Safari korjaus — estää sivun hyppimisen päivityksen aikana
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
    if (isSafari) { box.style.height = box.offsetHeight + 'px'; box.style.overflow = 'hidden'; }

    const html = await fetch('app/partial-tasks.php', {
        headers: { 'X-CSRF-Token': getCSRF() }
    }).then(function(r) { return r.text(); });

    const form = box.querySelector('form');
    // Korvataan todo-boxin sisältö — lomake säilyy, listat päivittyvät
    box.innerHTML = (form ? form.outerHTML : '') + html;

    // Kiinnitetään nappeihin tapahtumat uudelleen koska HTML vaihtui
    attachTaskEvents();
    setupFormSubmit();

    // Focus ensin preventScrollilla — ei scrollaa kenttään
    setTimeout(function() {
        const inp = document.querySelector('.input-area input[name="task"]');
        if (inp) { try { inp.focus({ preventScroll: true }); } catch(e) { inp.focus(); } }
    }, 0);

    if (isSafari) { box.style.height = ''; box.style.overflow = ''; }

    // Palautetaan scroll-positio kahden animaatioframen päästä
    // jotta selain ehtii piirtää uuden HTML:n ennen scrollausta
    requestAnimationFrame(function() {
        requestAnimationFrame(function() { window.scrollTo(0, prevScroll); });
    });
}

// ---- Toimintanapit ----
// Kiinnitetään kaikille tehtävänapeille click-tapahtuma
// Nappi lähettää AJAXilla toiminnon palvelimelle ja päivittää listan
function attachTaskEvents() {
    document.querySelectorAll('.actions button').forEach(function(btn) {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            const action = btn.dataset.action; // Luetaan mitä toimintoa nappi tekee — esim. 'start', 'delete'
            const id     = btn.dataset.id;     // Luetaan minkä tehtävän id on kyseessä

            // Lähetetään toiminto palvelimelle POST-pyyntönä
            await fetch('app/actions.php?action=' + action + '&id=' + id, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': getCSRF() // CSRF-token headerissa
                },
                body: ''
            });

            refreshTasks(); // Päivitetään tehtävälista näytöllä
        });
    });
}

// ---- Tehtävän lisäyslomake ----
// Kun käyttäjä painaa Lisää-nappia, lähetetään tehtävä AJAXilla
// eikä sivua ladata uudelleen
function setupFormSubmit() {
    const form = document.querySelector('form.input-area');
    if (!form) return;
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

// ---- Käynnistys ----
// Kiinnitetään tapahtumat kun sivu on latautunut
attachTaskEvents();
setupFormSubmit();

// Siirretään kursori lisäyskenttään heti sivun latautuessa
document.querySelector('.input-area input[name="task"]').focus({ preventScroll: true });