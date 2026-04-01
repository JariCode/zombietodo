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
    }, 4000); // Odotetaan 4 sekuntia ennen häivytystä
});

