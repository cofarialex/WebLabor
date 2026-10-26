<?php
// index.php – a Vendégkönyv egyetlen oldala.
//
// Minden kérés ide érkezik, és két ága van:
//   POST → beküldött űrlap feldolgozása, utána MINDIG átirányítás (303)
//   GET  → az oldal megjelenítése: üzenetek, űrlap, bejegyzések listája
//
// Ez a Post/Redirect/Get (PRG) minta: mivel a POST válasza mindig átirányítás,
// az F5 (frissítés) csak egy ártalmatlan GET-et ismétel meg, és nem küldi be
// újra az űrlapot – így nem lesznek dupla bejegyzések.
declare(strict_types=1);

require __DIR__ . '/db.php';
require __DIR__ . '/csrf.php';

// A munkamenet (session) a szerveren tárol adatot látogatónként.
// A böngésző csak egy azonosítót kap sütiben (PHPSESSID).
session_start([
    'cookie_httponly' => true,   // JavaScript nem olvashatja a sütit
    'cookie_samesite' => 'Lax',  // idegen oldalról indított POST-hoz nem küldi a böngésző
    'use_strict_mode' => true,   // csak a szerver által kiadott azonosítót fogadja el
]);

const NEV_MAX    = 50;
const UZENET_MAX = 500;

$metodus = $_SERVER['REQUEST_METHOD'];

// Csak GET-et (és HEAD-et) meg POST-ot fogadunk; minden másra 405.
if (!in_array($metodus, ['GET', 'HEAD', 'POST'], true)) {
    http_response_code(405);   // 405 Method Not Allowed
    header('Allow: GET, POST');
    exit('Nem támogatott metódus.');
}

// ===================== POST: beküldés feldolgozása =====================
if ($metodus === 'POST') {
    // 1. Tőlünk jött-e az űrlap? Ha nem, itt 403-mal megáll (csrf.php).
    csrf_check();

    // 2. Beolvasás. Az is_string azért kell, mert curl-lel valaki tömböt is
    //    küldhet (nev[]=x); a trim levágja a szóközöket az elejéről és a végéről.
    $nev    = trim(is_string($_POST['nev'] ?? null) ? $_POST['nev'] : '');
    $uzenet = trim(is_string($_POST['uzenet'] ?? null) ? $_POST['uzenet'] : '');

    // 3. Ellenőrzés a SZERVEREN. A HTML required/maxlength csak kényelmi funkció:
    //    a DevToolsszal vagy curl-lel bárki megkerülheti.
    //    mb_strlen = karakterek száma. A sima strlen bájtokat számol, és egy
    //    "ő" 2 bájt, így ékezetes nevekre hamis hibát adna.
    $hibak = [];
    if ($nev === '' || mb_strlen($nev) > NEV_MAX) {
        $hibak[] = 'A név 1–50 karakter legyen.';
    }
    if ($uzenet === '' || mb_strlen($uzenet) > UZENET_MAX) {
        $hibak[] = 'Az üzenet 1–500 karakter legyen.';
    }

    if ($hibak === []) {
        // 4a. Mentés prepared statementtel: a :nev és :uzenet helyőrzők helyére
        //     az adatbázis teszi be az értéket, ADATKÉNT. Így egy ' OR 1=1 --
        //     típusú bemenet sem tud SQL-kóddá válni (SQL injection elleni védelem).
        $stmt = db()->prepare('INSERT INTO bejegyzes (nev, uzenet) VALUES (:nev, :uzenet)');
        $stmt->execute([':nev' => $nev, ':uzenet' => $uzenet]);

        // "Flash" üzenet: most eltesszük, a következő GET egyszer kiírja és törli.
        $_SESSION['flash'] = 'Köszönjük a bejegyzést!';
    } else {
        // 4b. Hiba esetén eltesszük a hibákat és a beírt értékeket,
        //     hogy a felhasználónak ne kelljen mindent újragépelnie.
        $_SESSION['hibak'] = $hibak;
        $_SESSION['regi']  = ['nev' => $nev, 'uzenet' => $uzenet];
    }

    // 5. PRG: HTML helyett átirányítás. A 303 See Other azt mondja a böngészőnek:
    //    "most kérd le ezt a címet GET-tel".
    header('Location: /index.php', true, 303);
    exit;   // Fontos! Nélküle a szkript tovább futna, és kirajzolná az oldalt is.
}

// ===================== GET: az oldal megjelenítése =====================

// A flash adatokat kiolvassuk, majd azonnal töröljük → csak egyszer látszanak.
$flash = $_SESSION['flash'] ?? null;
$hibak = $_SESSION['hibak'] ?? [];
$regi  = $_SESSION['regi']  ?? ['nev' => '', 'uzenet' => ''];
unset($_SESSION['flash'], $_SESSION['hibak'], $_SESSION['regi']);

// A legutóbbi 50 bejegyzés, a legújabb elöl. Itt nincs felhasználói adat
// az SQL-ben, ezért elég a sima query() (nem kell prepare).
$bejegyzesek = db()->query(
    'SELECT nev, uzenet, letrehozva FROM bejegyzes ORDER BY letrehozva DESC, id DESC LIMIT 50'
)->fetchAll();

// Innentől HTML. A "<?=" kezdetű rövid címke jelentése: "írd ki ezt az értéket".
// Szabály: ami a felhasználótól jön, az CSAK e()-n keresztül kerülhet ki.
// (Vigyázat: PHP-kommentben a kérdőjel + nagyobb-jel PÁROS lezárja a PHP-t,
//  ezért itt szándékosan nem írtam ki.)
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vendégkönyv – UMFST nyílt nap</title>
  <link rel="stylesheet" href="/style.css">
</head>
<body>
<main>
  <h1>Vendégkönyv</h1>
  <p class="alcim">UMFST nyílt nap – írd le, hogy tetszett!</p>

  <?php if ($flash): ?>
    <p class="doboz ok" role="status"><?= e($flash) ?></p>
  <?php endif; ?>

  <?php if ($hibak): ?>
    <div class="doboz hiba" role="alert">
      <p>Nem sikerült menteni:</p>
      <ul>
        <?php foreach ($hibak as $h): ?>
          <li><?= e($h) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" action="/index.php" class="urlap">
    <!-- A rejtett mező viszi vissza a CSRF tokent a szervernek. -->
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <label for="nev">Név</label>
    <input id="nev" name="nev" maxlength="<?= NEV_MAX ?>" required autocomplete="name"
           value="<?= e($regi['nev']) ?>">

    <label for="uzenet">Üzenet</label>
    <textarea id="uzenet" name="uzenet" maxlength="<?= UZENET_MAX ?>" rows="4" required><?= e($regi['uzenet']) ?></textarea>
    <p class="segito">Legfeljebb <?= UZENET_MAX ?> karakter.</p>

    <button type="submit">Beküldés</button>
  </form>

  <section aria-labelledby="lista-cim">
    <h2 id="lista-cim">Legutóbbi bejegyzések</h2>

    <?php if (!$bejegyzesek): ?>
      <p class="ures">Még nincs bejegyzés – légy te az első!</p>
    <?php endif; ?>

    <?php foreach ($bejegyzesek as $b): ?>
      <article class="bejegyzes">
        <header>
          <strong><?= e($b['nev']) ?></strong>
          <small><?= e(helyi_ido($b['letrehozva'])) ?></small>
        </header>
        <!-- Sorrend: ELŐBB e() (escape), UTÁNA nl2br() (sortörés → <br>).
             Fordítva a <br> is escape-elődne, és szövegként látszana. -->
        <p><?= nl2br(e($b['uzenet'])) ?></p>
      </article>
    <?php endforeach; ?>
  </section>
</main>
</body>
</html>
