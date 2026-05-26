<?php
// ================================
// actions.php
//
// Tämä tiedosto hoitaa kaikki toiminnot
// mitä käyttäjä voi tehdä sovelluksessa.
//
// Esimerkiksi:
// - Rekisteröityminen ja kirjautuminen
// - Uloskirjautuminen
// - Profiilin päivitys ja poisto
// - Salasanan vaihto
// - Tehtävien lisäys, muokkaus ja poisto
// - Tehtävän tilan muutos (ei aloitettu,
//   käynnissä, valmis)
//
// Käyttäjä ei avaa tätä tiedostoa suoraan
// selaimessa — lomakkeet ja napit lähettävät
// tänne tietoa taustalla.
// ================================

// Etsitään zombie-config-kansio — tarkistetaan ensin kaksi tasoa ylös, sitten kolme
// App-kansio on yhden tason syvemmällä kuin juuritiedostot
$cfgDir = is_dir(dirname(dirname(__DIR__)) . '/zombie-config')
    ? dirname(dirname(__DIR__)) . '/zombie-config'
    : dirname(dirname(dirname(__DIR__))) . '/zombie-config';
require_once $cfgDir . '/session-config.php'; // Istuntoasetukset ENSIN
require_once $cfgDir . '/db.php';             // Tietokantayhteys

// ===========================================================
// TOIMINNON REITITYS
// ===========================================================
// Luetaan mitä toimintoa pyydetään
// Lomakkeet lähettävät sen piilokenttänä: $_POST['action']
// Tehtävänapit lähettävät sen osoitteen lopussa: ?action=start
$action = $_POST['action'] ?? $_GET['action'] ?? null; // Jos kumpikaan ei löydy, $action saa arvon null

// Tarkistetaan istunnon vanheneminen — vain jos käyttäjä on kirjautunut sisään
if (isset($_SESSION['user_id'])) { // isset tarkistaa onko user_id tallennettu istuntoon eli onko kirjauduttu
    if (!validateSessionTimeout()) { // validateSessionTimeout() on db.php:ssä. Palauttaa false jos tunti kulunut viimeisestä toiminnasta ja istunto vanhentunut
        $_SESSION['error'] = 'Istunto on vanhentunut. Kirjaudu uudelleen.'; // Tallennetaan virheilmoitus istuntoon
        header('Location: ../index.php'); // Ohjataan kirjautumissivulle kun istunto on vanhentunut
        exit; // Pysäytetään koodin suoritus heti — ilman tätä PHP jatkaisi eteenpäin
    }
}

// Katsotaan mitä toimintoa pyydettiin ja kutsutaan oikeaa funktiota
switch ($action) { // switch on kuin monta if-else:ä peräkkäin — siistimpi tapa
    case 'register': handleRegister(); break; // Jos action on 'register', kutsutaan rekisteröintifunktiota
    case 'login':    handleLogin();    break; // Jos action on 'login', kutsutaan kirjautumisfunktiota
    case 'logout':   handleLogout();   break; // Jos action on 'logout', kutsutaan uloskirjautumisfunktiota
    case 'update_profile': handleUpdateProfile(); break; // Jos action on 'update_profile', kutsutaan profiilin päivitysfunktiota
    case 'change_password': handleChangePassword(); break; // Jos action on 'change_password', kutsutaan salasanan vaihtofunktiota
    case 'delete_account':  handleDeleteAccount();  break; // Jos action on 'delete_account', kutsutaan tilin poiston funktiota
    default:                                  // Jos action on jotain muuta, käsitellään tehtävätoiminnot
        handleTaskAction($action);            // Kutsutaan tehtävätoimintofunktiota — add, start, done, undo, delete
        break;
}

// ===========================================================
// APUFUNKTIO — KENTTIEN TALLENNUS VIRHEEN VARALLE
// ===========================================================
function saveFormData($username, $email) {
    $_SESSION['form_username'] = $username; // Tallennetaan käyttäjänimi sessioon jotta se palautuu lomakkeelle
    $_SESSION['form_email']    = $email;    // Tallennetaan sähköposti sessioon jotta se palautuu lomakkeelle
    // Salasanoja ei koskaan tallenneta sessioon tietoturvan takia
}

// ===========================================================
// REKISTERÖINTI
// ===========================================================
function handleRegister() {
    global $conn; // Otetaan tietokantayhteys käyttöön. global-sana tarkoittaa että $conn viittaa siihen samaan $conn-muuttujaan joka on määritetty db.php:ssä

    // Tarkistetaan että pyyntö tulee lomakkeelta eikä suoraan osoitteesta
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // 405 = Method Not Allowed. Tämä kertoo selaimelle että GET-pyyntö ei ole sallittu
        exit('Method Not Allowed'); // Pysäytetään suoritus
    }

    // Tarkistetaan CSRF-token — varmistetaan että pyyntö tulee oikealta sivulta
    // Ilman tätä ulkopuolinen sivu voisi lähettää lomakkeen käyttäjän nimissä
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { // ?? '' tarkoittaa: jos token puuttuu kokonaan, käytä tyhjää merkkijonoa
        http_response_code(403); // 403 = Forbidden — pääsy kielletty
        $_SESSION['error'] = 'Turvallisuusvirhe. Yritä uudelleen.';
        header('Location: ../index.php');
        exit;
    }

    // Luetaan lomakkeen kentät ja siivotaan välilyönnit reunoilta
    $username         = trim($_POST['username']         ?? ''); // trim() poistaa turhat välilyönnit alusta ja lopusta
    $email            = trim($_POST['email']            ?? ''); // ?? '' tarkoittaa: jos kenttä puuttuu, käytä tyhjää merkkijonoa
    $password         = $_POST['password']              ?? ''; // Salasanaa ei trimmata. Välilyönti voi olla tarkoituksellinen
    $password_confirm = $_POST['password_confirm']      ?? ''; // Salasanan toisto vahvistusta varten
    $terms            = $_POST['terms']                 ?? ''; // Käyttöehtojen ja tietosuojaselosteen hyväksyntä

    // Tarkistetaan että kaikki kentät on täytetty
    if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
        saveFormData($username, $email); // Tallennetaan täytetyt kentät jotta ne palautuvat lomakkeelle
        $_SESSION['error'] = 'Täytä kaikki kentät.';
        header('Location: ../index.php');
        exit;
    }

    // Tarkistetaan että käyttöehdot ja tietosuojaseloste on hyväksytty. Sama ruksi kattaa molemmat
    if (empty($terms)) {
        saveFormData($username, $email); // Tallennetaan täytetyt kentät jotta ne palautuvat lomakkeelle
        $_SESSION['error'] = 'Sinun täytyy hyväksyä käyttöehdot ja tietosuojaseloste ennen rekisteröitymistä.';
        header('Location: ../index.php');
        exit;
    }

    // Tarkistetaan sähköpostin muoto — filter_var tarkistaa että se on oikea sähköpostiosoite
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        saveFormData($username, $email); // Tallennetaan täytetyt kentät jotta ne palautuvat lomakkeelle
        $_SESSION['error'] = 'Virheellinen sähköpostiosoite.';
        header('Location: ../index.php');
        exit;
    }

    // Tarkistetaan käyttäjänimen pituus — pitää olla 3–30 merkkiä
    if (strlen($username) < 3 || strlen($username) > 30) {
        saveFormData($username, $email); // Tallennetaan täytetyt kentät jotta ne palautuvat lomakkeelle
        $_SESSION['error'] = 'Käyttäjänimen pituuden pitää olla 3–30 merkkiä.';
        header('Location: ../index.php');
        exit;
    }

    // Tarkistetaan käyttäjänimen merkit — sallitaan vain kirjaimet, numerot, alaviiva ja viiva
    // Tämä estää myös <script>-tagit ja muun haitallisen syötteen käyttäjänimessä
    if (!preg_match('/^[a-zA-Z0-9_äöåÄÖÅ-]+$/u', $username)) {
        saveFormData($username, $email); // Tallennetaan täytetyt kentät jotta ne palautuvat lomakkeelle
        $_SESSION['error'] = 'Käyttäjänimessä on kiellettyjä merkkejä. Sallittu: kirjaimet, numerot, - ja _';
        header('Location: ../index.php');
        exit;
    }

    // Tarkistetaan salasanan pituus — pitää olla vähintään 10 merkkiä
    if (strlen($password) < 10) {
        saveFormData($username, $email); // Tallennetaan täytetyt kentät jotta ne palautuvat lomakkeelle
        $_SESSION['error'] = 'Salasanan pitää olla vähintään 10 merkkiä.';
        header('Location: ../index.php');
        exit;
    }

    // Tarkistetaan että salasanassa on vähintään yksi iso kirjain (A-Z ja Ä, Ö, Å)
    if (!preg_match('/[A-ZÄÖÅ]/u', $password)) {
        saveFormData($username, $email); // Tallennetaan täytetyt kentät jotta ne palautuvat lomakkeelle
        $_SESSION['error'] = 'Salasanassa pitää olla vähintään yksi iso kirjain.';
        header('Location: ../index.php');
        exit;
    }

    // Tarkistetaan että salasanassa on vähintään yksi pieni kirjain (a-z ja ä, ö, å)
    if (!preg_match('/[a-zäöå]/u', $password)) {
        saveFormData($username, $email); // Tallennetaan täytetyt kentät jotta ne palautuvat lomakkeelle
        $_SESSION['error'] = 'Salasanassa pitää olla vähintään yksi pieni kirjain.';
        header('Location: ../index.php');
        exit;
    }

    // Tarkistetaan että salasanassa on vähintään yksi numero (0-9)
    if (!preg_match('/[0-9]/', $password)) {
        saveFormData($username, $email); // Tallennetaan täytetyt kentät jotta ne palautuvat lomakkeelle
        $_SESSION['error'] = 'Salasanassa pitää olla vähintään yksi numero.';
        header('Location: ../index.php');
        exit;
    }

    // Tarkistetaan että salasanassa on vähintään yksi sallittu erikoismerkki
    // Sallitut erikoismerkit: ! @ # $ % ^ & * ( ) - _ = + ? . , ; : / \ | ~ ` ' "
    if (!preg_match('/[!@#$%^&*()\-_=+?.,:;\\/|~`\'"]/', $password)) {
        saveFormData($username, $email); // Tallennetaan täytetyt kentät jotta ne palautuvat lomakkeelle
        $_SESSION['error'] = 'Salasanassa pitää olla vähintään yksi erikoismerkki: ! @ # $ % ^ & * ( ) - _ = + ? . , ; : / | ~ ` \' "';
        header('Location: ../index.php');
        exit;
    }

    // Tarkistetaan että salasana ja sen toisto täsmäävät
    if ($password !== $password_confirm) {
        saveFormData($username, $email); // Tallennetaan täytetyt kentät jotta ne palautuvat lomakkeelle
        $_SESSION['error'] = 'Salasanat eivät täsmää.';
        header('Location: ../index.php');
        exit;
    }

    // Tarkistetaan onko sähköposti jo käytössä tietokannassa
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?'); // Prepared statement suojaa SQL-injektiolta
    $stmt->bind_param('s', $email); // 's' tarkoittaa string eli merkkijono
    $stmt->execute();
    $stmt->store_result(); // Tallennetaan tulos muistiin jotta voidaan laskea rivit
    if ($stmt->num_rows > 0) { // Jos rivejä löytyy, sähköposti on jo käytössä
        saveFormData($username, $email); // Tallennetaan täytetyt kentät jotta ne palautuvat lomakkeelle
        $_SESSION['error'] = 'Käyttäjänimi tai sähköposti on jo käytössä.';
        $stmt->close();
        header('Location: ../index.php');
        exit;
    }
    $stmt->close();

    // Tarkistetaan onko käyttäjänimi jo käytössä tietokannassa
    $stmt = $conn->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) { // Jos rivejä löytyy, käyttäjänimi on jo käytössä
        saveFormData($username, $email); // Tallennetaan täytetyt kentät jotta ne palautuvat lomakkeelle
        $_SESSION['error'] = 'Käyttäjänimi tai sähköposti on jo käytössä.';
        $stmt->close();
        header('Location: ../index.php');
        exit;
    }
    $stmt->close();

    // Hashataan salasana. Ei koskaan tallenneta selkokielisenä tietokantaan
    // PASSWORD_DEFAULT käyttää bcrypt-algoritmia joka on tällä hetkellä turvallisin vaihtoehto
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Tallennetaan uusi käyttäjä tietokantaan
    $stmt = $conn->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $username, $email, $hash); // 'sss' = kolme merkkijonoa
    $stmt->execute();
    $newId = $conn->insert_id; // Haetaan juuri luodun käyttäjän id — insert_id antaa viimeksi lisätyn rivin numeron
    $stmt->close();

    // Uudistetaan session ID heti rekisteröinnin jälkeen
    // Tämä suojaa session fixation -hyökkäykseltä jossa hyökkääjä yrittää käyttää valmista session ID:tä
    session_regenerate_id(true); // true tarkoittaa että vanha sessiotiedosto poistetaan palvelimelta

    // Luodaan uusi CSRF-token rekisteröinnin jälkeen — vanha token ei enää kelpaa
    // Näin estetään CSRF-tokenin uudelleenkäyttö kun käyttäjän tila muuttuu anonyymistä kirjautuneeksi
    generateCSRFToken(true); // true pakottaa uuden tokenin luomisen

    // Kirjataan käyttäjä automaattisesti sisään rekisteröityessä ja tallennetaan tiedot sessioon
    $_SESSION['user_id']       = $newId;     // Käyttäjän id tietokannasta. Tätä käytetään kaikissa kyselyissä
    $_SESSION['username']      = $username;  // Käyttäjänimi näytetään tervetuloviestissä
    $_SESSION['role']          = 'user';     // Rooli — oletuksena 'user'. Tarvitaan admin-toiminnoissa vaiheessa 3
    $_SESSION['last_activity'] = time();     // Käynnistetään timeout-laskuri. Aika tallennetaan sekunteina

     // Kirjataan tapahtuma lokiin
    $stmt = $conn->prepare("INSERT INTO logs (user_id, username, event, ip_address) VALUES (?, ?, 'register', ?)");
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt->bind_param('iss', $newId, $username, $ip); // $newId koska juuri luotu käyttäjä
    $stmt->execute();
    $stmt->close();

    // Ohjataan tehtäväsivulle jossa tehtävälista näkyy kirjautuneelle käyttäjälle
    header('Location: ../tasks.php');
    exit;
}

// ===========================================================
// KIRJAUTUMINEN
// ===========================================================
function handleLogin() {
    global $conn; // Otetaan tietokantayhteys käyttöön

    // Tarkistetaan että pyyntö tulee lomakkeelta eikä suoraan osoitteesta
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // 405 = Method Not Allowed
        exit('Method Not Allowed'); // Pysäytetään suoritus
    }

    // Tarkistetaan CSRF-token — varmistetaan että pyyntö tulee oikealta sivulta
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { // Jos token puuttuu tai ei täsmää, estetään pääsy
        http_response_code(403); // 403 = Forbidden. Pääsy kielletty koska token ei kelpaa
        $_SESSION['error'] = 'Turvallisuusvirhe. Yritä uudelleen.';
        header('Location: ../index.php');
        exit;
    }

    // Luetaan lomakkeen kentät
    $email    = trim($_POST['email']    ?? ''); // trim() poistaa turhat välilyönnit
    $password = $_POST['password']      ?? ''; // Salasanaa ei trimmata

    // Tarkistetaan että kentät on täytetty
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = 'Täytä sähköposti ja salasana.';
        header('Location: ../index.php');
        exit;
    }

    // Tarkistetaan sähköpostin muoto
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Virheellinen sähköpostiosoite.';
        header('Location: ../index.php');
        exit;
    }

    // Haetaan käyttäjä tietokannasta sähköpostin perusteella
    // Haetaan myös brute force -sarakkeet lukituksen tarkistusta varten
    $stmt = $conn->prepare('SELECT id, username, password, role, login_attempts, login_locked_until FROM users WHERE email = ?'); // Haetaan myös lukitustiedot
    $stmt->bind_param('s', $email); // 's' = string eli merkkijono
    $stmt->execute();
    $result = $stmt->get_result(); // Haetaan kyselyn tulos
    $user   = $result->fetch_assoc(); // Haetaan rivi assosiatiivisena taulukkona
    $stmt->close();

    // ===========================================================
    // BRUTE FORCE -SUOJA
    // Tarkistetaan onko kirjautuminen lukittu liian monen väärän yrityksen takia
    // ===========================================================

    // Tarkistetaan onko käyttäjä olemassa ja onko lukitus voimassa
    if ($user && !empty($user['login_locked_until'])) {
        $lockedUntil = strtotime($user['login_locked_until']); // strtotime muuttaa päivämäärämerkkijonon sekunteina vertailua varten

        if (time() < $lockedUntil) { // Jos nykyinen aika on ennen lukituksen päättymistä, tili on vielä lukittu
            $_SESSION['error'] = 'Liian monta kirjautumisyritystä. Yritä myöhemmin uudelleen.'; // Ei paljasteta tarkkaa syytä tai odotusaikaa
            $_SESSION['form_login_email'] = $email; // Palautetaan sähköposti kenttään
            header('Location: ../index.php');
            exit;
        }

        // Lukitusaika on mennyt — nollataan laskuri automaattisesti
        $stmt = $conn->prepare('UPDATE users SET login_attempts = 0, login_locked_until = NULL WHERE email = ?');
        $stmt->bind_param('s', $email); // 's' = string
        $stmt->execute();
        $stmt->close();
        $user['login_attempts']     = 0;    // Päivitetään myös muistissa oleva arvo
        $user['login_locked_until'] = null; // Poistetaan lukitus muistissa olevasta arvosta
    }

    // Tarkistetaan käyttäjä ja salasana
    // Tärkeää: sama virheilmoitus molemmille tilanteille jotta ei paljasteta onko sähköposti olemassa
    if (!$user || !password_verify($password, $user['password'])) {

        // Kasvatetaan yritystenlaskuria vain jos käyttäjä löytyi tietokannasta
        // Jos käyttäjää ei löydy, ei tehdä tietokantakyselyä jottei paljasteta onko sähköposti olemassa
        if ($user) {
            $newAttempts = $user['login_attempts'] + 1; // Kasvatetaan laskuria yhdellä

            if ($newAttempts >= 5) { // Viides tai useampi väärä yritys — lukitaan tili
                $lockedUntil = date('Y-m-d H:i:s', time() + 15 * 60); // Lasketaan lukituksen päättymisaika — 15 minuuttia nykyhetkestä
                $stmt = $conn->prepare('UPDATE users SET login_attempts = ?, login_locked_until = ? WHERE email = ?');
                $stmt->bind_param('iss', $newAttempts, $lockedUntil, $email); // 'iss' = integer, string, string
                $stmt->execute();
                $stmt->close();
            } else { // Alle 5 yritystä — kasvatetaan laskuria mutta ei vielä lukita
                $stmt = $conn->prepare('UPDATE users SET login_attempts = ? WHERE email = ?');
                $stmt->bind_param('is', $newAttempts, $email); // 'is' = integer, string
                $stmt->execute();
                $stmt->close();
            }
        }

        // Kaikki saavat saman viestin — ei paljasteta onko sähköposti olemassa tai montako yritystä on jäljellä
        $_SESSION['error'] = 'Väärä sähköposti tai salasana.';
        $_SESSION['form_login_email'] = $email; // Palautetaan sähköposti kenttään jotta käyttäjän ei tarvitse kirjoittaa sitä uudelleen
        header('Location: ../index.php');
        exit;
    }

    // Kirjautuminen onnistui — nollataan yritystenlaskuri ja lukitus
    $stmt = $conn->prepare('UPDATE users SET login_attempts = 0, login_locked_until = NULL WHERE id = ?');
    $stmt->bind_param('i', $user['id']); // 'i' = integer
    $stmt->execute();
    $stmt->close();

    // Uudistetaan session ID kirjautumisen jälkeen
    // Tämä suojaa session fixation -hyökkäykseltä jossa hyökkääjä yrittää käyttää valmista session ID:tä
    session_regenerate_id(true); // true poistaa vanhan sessiotiedoston palvelimelta

    // Tallennetaan käyttäjän tiedot sessioon — käyttäjä on nyt kirjautunut sisään
    $_SESSION['user_id']       = $user['id'];       // Käyttäjän id — käytetään kaikissa tietokantakyselyissä
    $_SESSION['username']      = $user['username']; // Käyttäjänimi näytetään tervetuloviestissä
    $_SESSION['role']          = $user['role'];     // Rooli — tarvitaan admin-toiminnoissa vaiheessa 3
    $_SESSION['last_activity'] = time();            // Käynnistetään timeout-laskuri. Aika tallennetaan sekunteina

    // Luodaan uusi CSRF-token kirjautumisen jälkeen — vanha token ei enää kelpaa
    // Näin estetään CSRF-tokenin uudelleenkäyttö kirjautumisen jälkeen
    generateCSRFToken(true); // true pakottaa uuden tokenin luomisen

     // Kirjataan tapahtuma lokiin
    $stmt = $conn->prepare("INSERT INTO logs (user_id, username, event, ip_address) VALUES (?, ?, 'login', ?)");
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt->bind_param('iss', $user['id'], $user['username'], $ip); // $user['id'] koska haettu tietokannasta
    $stmt->execute();
    $stmt->close();

    // Ohjataan tehtäväsivulle jossa tehtävälista näkyy kirjautuneelle käyttäjälle
    header('Location: ../tasks.php');
    exit;
}

// ===========================================================
// ULOSKIRJAUTUMINEN
// ===========================================================
function handleLogout() {
   global $conn; // Otetaan tietokantayhteys käyttöön

    // Tarkistetaan CSRF-token — estää ulkopuolista kirjaamasta käyttäjän ulos
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403); // 403 = Forbidden — pääsy kielletty
        $_SESSION['error'] = 'Turvallisuusvirhe. Yritä uudelleen.';
        header('Location: ../index.php');
        exit;
    }

     // Kirjataan tapahtuma lokiin ENNEN istunnon tuhoamista
    // session_unset() tyhjentää $_SESSION['user_id'] joten lokitus pitää tehdä ensin
    $stmt = $conn->prepare("INSERT INTO logs (user_id, username, event, ip_address) VALUES (?, ?, 'logout', ?)");
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $uid = intval($_SESSION['user_id']);
    $username = $_SESSION['username'];
    $stmt->bind_param('iss', $uid, $username, $ip);
    $stmt->execute();
    $stmt->close();

    session_unset();   // Tyhjennetään kaikki istunnon tiedot muistista
    session_destroy(); // Tuhotaan istunto kokonaan palvelimelta

    // Poistetaan eväste selaimesta asettamalla vanhenemisaika menneisyyteen
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);

    // Käynnistetään uusi istunto onnistumisviestiä varten
    session_start();
    $_SESSION['success'] = 'Uloskirjautuminen onnistui. Nähdään taas! 🧟';

    header('Location: ../index.php'); // Ohjataan kirjautumissivulle
    exit;
}

// ===========================================================
// PROFIILIN PÄIVITYS — käyttäjänimi ja sähköposti
// ===========================================================
function handleUpdateProfile() {
    global $conn; // Otetaan tietokantayhteys käyttöön

    // Tarkistetaan että pyyntö tulee lomakkeelta eikä suoraan osoitteesta
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // 405 = Method Not Allowed
        exit('Method Not Allowed');
    }

    // Tarkistetaan kirjautuminen — kirjautumaton käyttäjä ei pääse muokkaamaan profiilia
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../index.php');
        exit;
    }

    // Tarkistetaan CSRF-token — varmistetaan että pyyntö tulee oikealta sivulta
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403); // 403 = Forbidden — pääsy kielletty
        $_SESSION['error'] = 'Turvallisuusvirhe. Yritä uudelleen.';
        header('Location: ../profile.php');
        exit;
    }

    $uid = intval($_SESSION['user_id']); // Kirjautuneen käyttäjän id

    // Luetaan lomakkeen kentät ja siivotaan välilyönnit reunoilta
    $username = trim($_POST['username'] ?? ''); // trim() poistaa turhat välilyönnit alusta ja lopusta
    $email    = trim($_POST['email']    ?? ''); // ?? '' tarkoittaa: jos kenttä puuttuu, käytä tyhjää merkkijonoa

    // Tarkistetaan että molemmat kentät on täytetty
    if (empty($username) || empty($email)) {
        $_SESSION['error'] = 'Täytä kaikki kentät.';
        header('Location: ../profile.php');
        exit;
    }

    // Tarkistetaan sähköpostin muoto — filter_var tarkistaa että se on oikea sähköpostiosoite
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Virheellinen sähköpostiosoite.';
        header('Location: ../profile.php');
        exit;
    }

    // Tarkistetaan käyttäjänimen pituus — pitää olla 3–30 merkkiä
    if (strlen($username) < 3 || strlen($username) > 30) {
        $_SESSION['error'] = 'Käyttäjänimen pituuden pitää olla 3–30 merkkiä.';
        header('Location: ../profile.php');
        exit;
    }

    // Tarkistetaan käyttäjänimen merkit — sallitaan vain kirjaimet, numerot, alaviiva ja viiva
    // Sama regex kuin rekisteröinnissä — estää myös haitallisen syötteen
    if (!preg_match('/^[a-zA-Z0-9_äöåÄÖÅ-]+$/u', $username)) {
        $_SESSION['error'] = 'Käyttäjänimessä on kiellettyjä merkkejä. Sallittu: kirjaimet, numerot, - ja _';
        header('Location: ../profile.php');
        exit;
    }

    // Tarkistetaan onko sähköposti jo käytössä toisella käyttäjällä
    // AND id != ? jättää oman tilin pois hausta — saa pitää saman sähköpostin
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->bind_param('si', $email, $uid); // 'si' = string, integer
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['error'] = 'Sähköposti on jo käytössä toisella käyttäjällä.';
        $stmt->close();
        header('Location: ../profile.php');
        exit;
    }
    $stmt->close();

    // Tarkistetaan onko käyttäjänimi jo käytössä toisella käyttäjällä
    $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
    $stmt->bind_param('si', $username, $uid); // Oma tili jätetään pois hausta
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['error'] = 'Käyttäjänimi on jo käytössä toisella käyttäjällä.';
        $stmt->close();
        header('Location: ../profile.php');
        exit;
    }
    $stmt->close();

    // Päivitetään tiedot tietokantaan
    $stmt = $conn->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?');
    $stmt->bind_param('ssi', $username, $email, $uid); // 'ssi' = string, string, integer
    $stmt->execute();
    $stmt->close();

    // Päivitetään sessioon tallennettu käyttäjänimi — näkyy heti tervetuloviestissä ja yläpalkissa
    $_SESSION['username'] = $username;

    // Kirjataan tapahtuma lokiin
    $stmt = $conn->prepare("INSERT INTO logs (user_id, username, event, ip_address) VALUES (?, ?, 'account_updated', ?)");
    $ip = $_SERVER['REMOTE_ADDR'] ?? null; // Käyttäjän IP-osoite tapahtuman hetkellä
    $stmt->bind_param('iss', $uid, $username, $ip); // 'iss' = integer, string, string
    $stmt->execute();
    $stmt->close();

    // Onnistumisviesti ja uudelleenohjaus profiilisivulle
    $_SESSION['success'] = 'Tiedot päivitetty onnistuneesti! 🧟';
    header('Location: ../profile.php');
    exit;
}

// ===========================================================
// SALASANAN VAIHTO
// ===========================================================
function handleChangePassword() {
    global $conn; // Otetaan tietokantayhteys käyttöön

    // Tarkistetaan että pyyntö tulee lomakkeelta eikä suoraan osoitteesta
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // 405 = Method Not Allowed
        exit('Method Not Allowed');
    }

    // Tarkistetaan kirjautuminen — kirjautumaton käyttäjä ei pääse vaihtamaan salasanaa
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../index.php');
        exit;
    }

    // Tarkistetaan CSRF-token — varmistetaan että pyyntö tulee oikealta sivulta
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403); // 403 = Forbidden — pääsy kielletty
        $_SESSION['error'] = 'Turvallisuusvirhe. Yritä uudelleen.';
        header('Location: ../profile.php');
        exit;
    }

    $uid = intval($_SESSION['user_id']); // Kirjautuneen käyttäjän id

    // Luetaan lomakkeen kentät — salasanoja ei trimmata koska välilyönti voi olla tarkoituksellinen
    $old_password     = $_POST['old_password']  ?? '';
    $new_password     = $_POST['new_password']  ?? '';
    $new_password2    = $_POST['new_password2'] ?? '';

    // Tarkistetaan että kaikki kolme kenttää on täytetty
    if (empty($old_password) || empty($new_password) || empty($new_password2)) {
        $_SESSION['error'] = 'Täytä kaikki kentät.';
        header('Location: ../profile.php');
        exit;
    }

    // Haetaan nykyinen salasanahash tietokannasta
    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->bind_param('i', $uid); // 'i' = integer eli kokonaisluku
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // Jos käyttäjää ei löydy tietokannasta — istunto on vanhentunut tai tili poistettu
    if (!$user) {
        session_unset();   // Tyhjennetään istunnon tiedot
        session_destroy(); // Tuhotaan istunto palvelimelta
        header('Location: ../index.php');
        exit;
    }

    // Tarkistetaan vanha salasana — käyttäjä todistaa henkilöllisyytensä
    if (!password_verify($old_password, $user['password'])) {
        $_SESSION['error'] = 'Vanha salasana on väärin.';
        header('Location: ../profile.php');
        exit;
    }

    // Tarkistetaan ettei uusi salasana ole sama kuin vanha
    if (password_verify($new_password, $user['password'])) {
        $_SESSION['error'] = 'Uusi salasana ei saa olla sama kuin vanha salasana.';
        header('Location: ../profile.php');
        exit;
    }

    // Tarkistetaan salasanan pituus — pitää olla vähintään 10 merkkiä
    if (strlen($new_password) < 10) {
        $_SESSION['error'] = 'Salasanan pitää olla vähintään 10 merkkiä.';
        header('Location: ../profile.php');
        exit;
    }

    // Tarkistetaan että salasanassa on vähintään yksi iso kirjain (A-Z ja Ä, Ö, Å)
    if (!preg_match('/[A-ZÄÖÅ]/u', $new_password)) {
        $_SESSION['error'] = 'Salasanassa pitää olla vähintään yksi iso kirjain.';
        header('Location: ../profile.php');
        exit;
    }

    // Tarkistetaan että salasanassa on vähintään yksi pieni kirjain (a-z ja ä, ö, å)
    if (!preg_match('/[a-zäöå]/u', $new_password)) {
        $_SESSION['error'] = 'Salasanassa pitää olla vähintään yksi pieni kirjain.';
        header('Location: ../profile.php');
        exit;
    }

    // Tarkistetaan että salasanassa on vähintään yksi numero (0-9)
    if (!preg_match('/[0-9]/', $new_password)) {
        $_SESSION['error'] = 'Salasanassa pitää olla vähintään yksi numero.';
        header('Location: ../profile.php');
        exit;
    }

    // Tarkistetaan että salasanassa on vähintään yksi sallittu erikoismerkki
    // Sama lista kuin rekisteröinnissä: ! @ # $ % ^ & * ( ) - _ = + ? . , ; : / \ | ~ ` ' "
    if (!preg_match('/[!@#$%^&*()\-_=+?.,:;\\/|~`\'"]/', $new_password)) {
        $_SESSION['error'] = 'Salasanassa pitää olla vähintään yksi erikoismerkki: ! @ # $ % ^ & * ( ) - _ = + ? . , ; : / | ~ ` \' "';
        header('Location: ../profile.php');
        exit;
    }

    // Tarkistetaan että uusi salasana ja sen toisto täsmäävät
    if ($new_password !== $new_password2) {
        $_SESSION['error'] = 'Uudet salasanat eivät täsmää.';
        header('Location: ../profile.php');
        exit;
    }

    // Hashataan uusi salasana — ei koskaan tallenneta selkokielisenä tietokantaan
    $hash = password_hash($new_password, PASSWORD_DEFAULT);

    // Tallennetaan uusi salasana tietokantaan
    $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->bind_param('si', $hash, $uid); // 'si' = string, integer
    $stmt->execute();
    $stmt->close();

     // Kirjataan tapahtuma lokiin
    $stmt = $conn->prepare("INSERT INTO logs (user_id, username, event, ip_address) VALUES (?, ?, 'password_changed', ?)");
    $ip = $_SERVER['REMOTE_ADDR'] ?? null; // Käyttäjän IP-osoite tapahtuman hetkellä
    $username = $_SESSION['username'];
    $stmt->bind_param('iss', $uid, $username, $ip);
    $stmt->execute();
    $stmt->close();

    // Uudistetaan session ID salasanan vaihdon jälkeen turvallisuussyistä
    // Jos vanha istunto on vuotanut, se ei enää toimi
    session_regenerate_id(true); // true poistaa vanhan sessiotiedoston palvelimelta

    // Luodaan uusi CSRF-token — vanha ei enää kelpaa uudistetun istunnon kanssa
    generateCSRFToken(true); // true pakottaa uuden tokenin luomisen

    $_SESSION['success'] = 'Salasana vaihdettu onnistuneesti! 🔐';
    header('Location: ../profile.php');
    exit;
}

// ===========================================================
// TILIN POISTO
// ===========================================================
function handleDeleteAccount() {
    global $conn; // Otetaan tietokantayhteys käyttöön

    // Tarkistetaan että pyyntö tulee lomakkeelta eikä suoraan osoitteesta
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // 405 = Method Not Allowed
        exit('Method Not Allowed');
    }

    // Tarkistetaan kirjautuminen — kirjautumaton käyttäjä ei pääse poistamaan tiliä
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../index.php');
        exit;
    }

    // Tarkistetaan CSRF-token — varmistetaan että pyyntö tulee oikealta sivulta
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403); // 403 = Forbidden — pääsy kielletty
        $_SESSION['error'] = 'Turvallisuusvirhe. Yritä uudelleen.';
        header('Location: ../profile.php');
        exit;
    }

    $uid = intval($_SESSION['user_id']); // Kirjautuneen käyttäjän id

    // Luetaan lomakkeen kentät
    $confirm_username = trim($_POST['confirm_username'] ?? ''); // Käyttäjänimi vahvistukseksi
    $confirm_email    = trim($_POST['confirm_email']    ?? ''); // Sähköposti vahvistukseksi
    $confirm_password = $_POST['confirm_password']      ?? ''; // Salasanaa ei trimmata

    // Tarkistetaan että kaikki kentät on täytetty
    if (empty($confirm_username) || empty($confirm_email) || empty($confirm_password)) {
        $_SESSION['error'] = 'Täytä kaikki kentät.';
        header('Location: ../profile.php');
        exit;
    }

    // Haetaan käyttäjän tiedot tietokannasta
    $stmt = $conn->prepare('SELECT username, email, password FROM users WHERE id = ?');
    $stmt->bind_param('i', $uid); // 'i' = integer eli kokonaisluku
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // Jos käyttäjää ei löydy tietokannasta — istunto on vanhentunut tai tili poistettu
    if (!$user) {
        session_unset();
        session_destroy();
        header('Location: ../index.php');
        exit;
    }

    // Tarkistetaan että käyttäjänimi täsmää — käyttäjä vahvistaa henkilöllisyytensä
    if ($confirm_username !== $user['username']) {
        $_SESSION['error'] = 'Käyttäjänimi ei täsmää.';
        header('Location: ../profile.php');
        exit;
    }

    // Tarkistetaan että sähköposti täsmää
    if ($confirm_email !== $user['email']) {
        $_SESSION['error'] = 'Sähköposti ei täsmää.';
        header('Location: ../profile.php');
        exit;
    }

    // Tarkistetaan salasana — viimeinen vahvistus ennen pysyvää poistoa
    if (!password_verify($confirm_password, $user['password'])) {
        $_SESSION['error'] = 'Väärä salasana. Tiliä ei poistettu.';
        header('Location: ../profile.php');
        exit;
    }

   // Kirjataan tapahtuma lokiin ENNEN käyttäjän poistoa
    // ON DELETE SET NULL muuttaa user_id:n NULL:ksi mutta lokimerkintä ja username säilyvät
    $stmt = $conn->prepare("INSERT INTO logs (user_id, username, event, ip_address) VALUES (?, ?, 'account_deleted_user', ?)");
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt->bind_param('iss', $uid, $user['username'], $ip);
    $stmt->execute();
    $stmt->close();

    // Poistetaan käyttäjä tietokannasta
    // ON DELETE CASCADE poistaa käyttäjän tehtävät, ON DELETE SET NULL säilyttää lokimerkinnät
    $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->close();

    // Tuhotaan istunto — sama koodi kuin handleLogout():ssa
    session_unset();   // Tyhjennetään kaikki istunnon tiedot muistista
    session_destroy(); // Tuhotaan istunto kokonaan palvelimelta

    // Poistetaan eväste selaimesta asettamalla vanhenemisaika menneisyyteen
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);

    // Käynnistetään uusi istunto onnistumisviestiä varten
    session_start();
    $_SESSION['success'] = 'Tilisi on poistettu pysyvästi. Nähdään hautuumaalla. 💀';

    header('Location: ../index.php'); // Ohjataan etusivulle jossa viesti näkyy
    exit;
}

// ===========================================================
// TEHTÄVÄTOIMINNOT — vain kirjautuneille käyttäjille
// ===========================================================
function handleTaskAction($action) {
    header('Content-Type: application/json; charset=utf-8'); // Kerrotaan selaimelle että vastaus on JSON-dataa eikä HTML — estää selainta tulkitsemasta ja suorittamasta vastauksen sisältöä HTML:nä
    global $conn; // Otetaan tietokantayhteys käyttöön

    // Tarkistetaan että pyyntö tulee POST-metodilla — GET-pyynnöt eivät ole sallittuja tilanmuutoksiin
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // 405 = Method Not Allowed
        echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
        exit;
    }

    // Tarkistetaan kirjautuminen — kirjautumaton käyttäjä ei pääse tekemään mitään
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403); // 403 = Forbidden — pääsy kielletty
        echo json_encode(['success' => false, 'error' => 'Ei kirjautunut']);
        exit;
    }

    // CSRF luetaan POST-bodysta tai X-CSRF-Token-headerista
    // tasks.js lähettää tokenin headerissa, lomakkeet POST-bodyssa
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF TOKEN INVALID']);
        exit;
    }

    $user_id = intval($_SESSION['user_id']); // Kirjautuneen käyttäjän id
    $id = intval($_POST['id'] ?? 0);    //Tehtävä id POST-datasta.

    // ===========================================================
    // LISÄÄ TEHTÄVÄ
    // Luetaan teksti POST-datasta, tarkistetaan ettei ole tyhjä
    // ja tallennetaan tietokantaan ei aloitettu -tilassa
    // ===========================================================
    if ($action === 'add') {
        $task = trim($_POST['task'] ?? ''); // Luetaan teksti ja poistetaan välilyönnit reunoilta
        if ($task === '') { echo json_encode(['success' => false]); exit; } // Tyhjiä tehtäviä ei sallita

          // Tarkistetaan ettei tehtävä ylitä tietokannan 255 merkin rajaa
        if (mb_strlen($task) > 255) {
            echo json_encode(['success' => false, 'error' => 'Tehtävä saa olla enintään 255 merkkiä.']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO tasks (user_id, text, status, created_at) VALUES (?, ?, 'not_started', NOW())");
        $stmt->bind_param('is', $user_id, $task); // 'is' = integer, string
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        exit;
    }

    // ===========================================================
    // HAE TEHTÄVÄ MUOKKAUSTA VARTEN
    // Haetaan tehtävän tiedot tietokannasta JSON-muodossa
    // tasks.js täyttää tiedot muokkausmodaliin
    // ===========================================================
    if ($action === 'get_task' && $id > 0) {
        $stmt = $conn->prepare('SELECT id, text, status, started_at, done_at FROM tasks WHERE id=? AND user_id=?');
        $stmt->bind_param('ii', $id, $user_id); // user_id tarkistus — käyttäjä ei voi hakea toisen tehtäviä
        $stmt->execute();
        $result = $stmt->get_result();
        $task = $result->fetch_assoc(); // Haetaan tehtävä  taulukkona
        $stmt->close();
        if (!$task) { // Jos tehtävää ei löydy tai se kuuluu toiselle käyttäjälle
            echo json_encode(['success' => false, 'error' => 'Tehtävää ei löydy']);
            exit;
        }
        echo json_encode(['success' => true, 'task' => $task]); // Palautetaan tehtävän tiedot JSON-muodossa
        exit;
    }

    // ===========================================================
    // MUOKKAA TEHTÄVÄ
    // Päivitetään tehtävän teksti ja aikaleimät tietokantaan
    // Tyhjä arvo tallennetaan NULL:na
    // ===========================================================
    if ($action === 'edit_task' && $id > 0) {
        $text       = trim($_POST['text']       ?? ''); // Luetaan teksti ja poistetaan välilyönnit
        $started_at = trim($_POST['started_at'] ?? ''); // Aloitusaika Flatpickrista
        $done_at    = trim($_POST['done_at']    ?? ''); // Valmistumisaika Flatpickrista

        if ($text === '') { // Tarkistetaan että teksti ei ole tyhjä
            echo json_encode(['success' => false, 'error' => 'Teksti ei voi olla tyhjä']);
            exit;
        }

        // Tarkistetaan päivämäärien muoto — sallitaan vain tyhjä tai YYYY-MM-DD HH:MM
        function isValidDatetime($val) {
            if ($val === '') return true; // Tyhjä on ok — tarkoittaa ei asetettu
            $d = DateTime::createFromFormat('Y-m-d H:i', $val);
            return $d && $d->format('Y-m-d H:i') === $val; // Tarkistetaan että muoto täsmää
        }

        if (!isValidDatetime($started_at) || !isValidDatetime($done_at)) {
            echo json_encode(['success' => false, 'error' => 'Virheellinen päivämäärä.']);
            exit;
        }

        // Tarkistetaan ettei valmistumisaika ole ennen aloitusaikaa
        if ($started_at !== '' && $done_at !== '' && $done_at < $started_at) {
            echo json_encode(['success' => false, 'error' => 'Valmistumisaika ei voi olla ennen aloitusaikaa.']);
            exit;
        }
        
        // Tyhjä arvo tallennetaan NULL:na tietokantaan — ei tallenneta tyhjää merkkijonoa
        $started_at = $started_at !== '' ? $started_at : null;
        $done_at    = $done_at    !== '' ? $done_at    : null;

        // Päätellään tehtävän tila aikaleimoista, jotta muutos näkyy heti oikeassa listassa.
        if ($done_at !== null) {
            $status = 'done';
        } elseif ($started_at !== null) {
            $status = 'in_progress';
        } else {
            $status = 'not_started';
        }

        $stmt = $conn->prepare('UPDATE tasks SET text=?, started_at=?, done_at=?, status=? WHERE id=? AND user_id=?');
        $stmt->bind_param('ssssii', $text, $started_at, $done_at, $status, $id, $user_id); // user_id tarkistus — käyttäjä ei voi muokata toisen tehtäviä
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        exit;
    }

    // ===========================================================
    // ALOITA TEHTÄVÄ
    // Siirretään ei aloitetusta käynnissä olevaksi
    // Tallennetaan aloitusaika automaattisesti
    // ===========================================================
    if ($action === 'start' && $id > 0) {
        $stmt = $conn->prepare("UPDATE tasks SET status='in_progress', started_at=NOW() WHERE id=? AND user_id=?");
        $stmt->bind_param('ii', $id, $user_id); // user_id tarkistus — käyttäjä ei voi muokata toisen tehtäviä
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        exit;
    }

    // ===========================================================
    // MERKITSE VALMIIKSI
    // Siirretään käynnissä olevasta valmiiksi
    // Tallennetaan valmistumisaika automaattisesti
    // ===========================================================
    if ($action === 'done' && $id > 0) {
        $stmt = $conn->prepare("UPDATE tasks SET status='done', done_at=NOW() WHERE id=? AND user_id=?");
        $stmt->bind_param('ii', $id, $user_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        exit;
    }

    // ===========================================================
    // UNDO START
    // Palautetaan käynnissä oleva takaisin ei aloitetuksi
    // Tyhjennetään aloitusaika
    // ===========================================================
    if ($action === 'undo_start' && $id > 0) {
        $stmt = $conn->prepare("UPDATE tasks SET status='not_started', started_at=NULL WHERE id=? AND user_id=?");
        $stmt->bind_param('ii', $id, $user_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        exit;
    }

    // ===========================================================
    // UNDO DONE
    // Palautetaan valmis takaisin käynnissä olevaksi
    // Tyhjennetään valmistumisaika
    // ===========================================================
    if ($action === 'undo_done' && $id > 0) {
        $stmt = $conn->prepare("UPDATE tasks SET status='in_progress', done_at=NULL WHERE id=? AND user_id=?");
        $stmt->bind_param('ii', $id, $user_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        exit;
    }

    // ===========================================================
    // POISTA TEHTÄVÄ
    // Poistetaan tehtävä pysyvästi tietokannasta
    // ===========================================================
    if ($action === 'delete' && $id > 0) {
        $stmt = $conn->prepare('DELETE FROM tasks WHERE id=? AND user_id=?');
        $stmt->bind_param('ii', $id, $user_id); // user_id tarkistus — käyttäjä ei voi poistaa toisen tehtäviä
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        exit;
    }

    // Tuntematon toiminto — ohjataan etusivulle
    header('Location: ../index.php');
    exit;
}

// ===========================================================
// ADMIN-TOIMINNOT — vain admin-roolille
// ===========================================================

//===========================================================
//ROOLIN VAIHTAMINEN
//Admin voi vaihtaa käyttäjälle roolin user tai admin — mutta ei itseään
//===========================================================

//===========================================================
//UNOHTUNEEN SALASANAN PALAUTUS LINKIN LÄHETTÄMINEN
//Admin voi lähettää käyttäjälle sähköpostitse linkin jolla hän voi asettaa uuden salasanan
//===========================================================

//===========================================================
//KÄYTTÄJÄN POISTAMINEN
//Admin voi poistaa käyttäjän ja kaikki hänen tietonsa — mutta ei itseään
//===========================================================
