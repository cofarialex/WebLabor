<?php
// jegyzetek.php – Jegyzetek CRUD egyetlen belépési ponttal (front controller).
//
// CRUD = Create (létrehozás), Read (olvasás), Update (módosítás), Delete (törlés).
// Minden kérés ide érkezik; hogy mit kell csinálni, azt az "m" (művelet) paraméter mondja meg:
//
//   Művelet           Metódus  URL                       Siker          Hiba
//   lista             GET      jegyzetek.php             200            –
//   új – űrlap        GET      ?m=uj                     200            –
//   új – mentés       POST     ?m=uj                     303 → lista    422 (hibás mező)
//   szerkesztés       GET      ?m=szerkeszt&id=5         200            404 (nincs ilyen)
//   szerkesztés–mentés POST    ?m=szerkeszt&id=5         303 → lista    422, 404
//   törlés            POST     ?m=torol (id a törzsben)  303 → lista    405 (GET-tel), 404
//   bármelyik POST    POST     CSRF-token nélkül         –              403
//   ismeretlen m      –        ?m=valami                 –              404
//
// Szabály: GET csak OLVAS, ami adatot módosít, az mindig POST (+ CSRF-token).
// Egy GET-linket a böngésző előtöltése, egy keresőrobot vagy egy idegen oldalon
// elhelyezett <img> is lekérhet – a felhasználó tudta nélkül.
declare(strict_types=1);

require __DIR__ . '/db.php';

$metodus = $_SERVER['REQUEST_METHOD'];
if (!in_array($metodus, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, POST');
    hiba(405, 'Nem támogatott metódus.');
}
$post = $metodus === 'POST';

// A művelet neve. Az is_string azért kell, mert a böngészőből tömb is jöhet (?m[]=x).
$m = is_string($_GET['m'] ?? null) ? $_GET['m'] : 'lista';

// Az azonosító: csak pozitív egész számot fogadunk el. Minden más ("abc", "-3",
// "1 OR 1=1", tömb) → null, amire később 404 a válasz.
$id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$id = $id === false ? null : $id;

switch ($m) {
    // ---------- READ: a lista, a legutóbb módosított elöl ----------
    case 'lista':
        // Itt nincs felhasználói adat az SQL-ben, ezért elég a query() (nem kell prepare).
        $jegyzetek = db()->query(
            'SELECT id, cim, tartalom, frissitve FROM jegyzet ORDER BY frissitve DESC, id DESC'
        )->fetchAll();
        break;

    default:
        hiba(404, 'Ismeretlen oldal.');
}

// Innentől HTML. Szabály: ami a felhasználótól jön, az CSAK e()-n keresztül kerülhet ki.
// (Vigyázat: PHP-kommentben a kérdőjel + nagyobb-jel PÁROS lezárja a PHP-t,
//  ezért itt szándékosan nem írtam ki.)
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Jegyzeteim</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<main>
  <nav class="fejlec">
    <a href="jegyzetek.php">Jegyzeteim</a>
  </nav>

<?php if ($m === 'lista'): ?>
  <h1>Jegyzeteim <span class="darab">(<?= count($jegyzetek) ?>)</span></h1>

  <?php if (!$jegyzetek): ?>
    <p class="ures">Még nincs jegyzeted.</p>
  <?php endif; ?>

  <?php foreach ($jegyzetek as $j): ?>
    <article class="jegyzet">
      <h2><?= e($j['cim']) ?></h2>
      <!-- Előbb e(), utána nl2br(): fordítva a <br> is escape-elődne, és szövegként látszana. -->
      <p><?= nl2br(e(mb_strimwidth($j['tartalom'], 0, 200, '…'))) ?></p>
      <footer>
        <small>Módosítva: <?= e(helyi_ido($j['frissitve'])) ?></small>
      </footer>
    </article>
  <?php endforeach; ?>

<?php endif; ?>
</main>
</body>
</html>
