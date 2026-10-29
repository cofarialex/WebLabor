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
require __DIR__ . '/csrf.php';

// A munkamenet (session) a szerveren tárol adatot látogatónként (CSRF-token, flash).
session_start([
    'cookie_httponly' => true,   // JavaScript nem olvashatja a sütit
    'cookie_samesite' => 'Lax',  // idegen oldalról indított POST-hoz nem küldi a böngésző
    'use_strict_mode' => true,   // csak a szerver által kiadott azonosítót fogadja el
]);

const CIM_MAX      = 120;
const TARTALOM_MAX = 20000;

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

// Minden POST-nál először: tőlünk jött-e az űrlap? Ha nem → 403, itt megáll.
if ($post) {
    csrf_check();
}

// Az űrlap két mezője, levágott szóközökkel. Tömböt (cim[]=x) üres szövegnek veszünk.
function urlap_adatok(): array
{
    return [
        'cim'      => trim(is_string($_POST['cim'] ?? null) ? $_POST['cim'] : ''),
        'tartalom' => trim(is_string($_POST['tartalom'] ?? null) ? $_POST['tartalom'] : ''),
    ];
}

// Ugyanaz az ellenőrzés szolgálja ki az új és a szerkesztett jegyzetet (DRY: ne ismételd magad).
// A HTML required/maxlength csak kényelmi funkció – a szerveren MINDIG újra ellenőrzünk.
// mb_strlen: karaktereket számol (az "ő" 1 karakter, de 2 bájt – a strlen 2-t mondana).
function validal(array $j): array
{
    $hibak = [];
    if ($j['cim'] === '' || mb_strlen($j['cim']) > CIM_MAX) {
        $hibak['cim'] = 'A cím 1–' . CIM_MAX . ' karakter legyen.';
    }
    if ($j['tartalom'] === '' || mb_strlen($j['tartalom']) > TARTALOM_MAX) {
        $hibak['tartalom'] = 'A tartalom nem lehet üres (legfeljebb 20 000 karakter).';
    }
    return $hibak;
}

$hibak   = [];
$jegyzet = ['id' => null, 'cim' => '', 'tartalom' => ''];

switch ($m) {
    // ---------- READ: a lista, a legutóbb módosított elöl ----------
    case 'lista':
        // Itt nincs felhasználói adat az SQL-ben, ezért elég a query() (nem kell prepare).
        $jegyzetek = db()->query(
            'SELECT id, cim, tartalom, frissitve FROM jegyzet ORDER BY frissitve DESC, id DESC'
        )->fetchAll();
        break;

    // ---------- CREATE: új jegyzet ----------
    case 'uj':
        if ($post) {
            $jegyzet = urlap_adatok() + $jegyzet;
            $hibak = validal($jegyzet);
            if (!$hibak) {
                // Prepared statement: a ? helyére az adatbázis teszi be az értéket, ADATKÉNT.
                db()->prepare('INSERT INTO jegyzet (cim, tartalom) VALUES (?, ?)')
                    ->execute([$jegyzet['cim'], $jegyzet['tartalom']]);
                flash('Jegyzet létrehozva.');
                redirect('jegyzetek.php');
            }
            // 422 Unprocessable Content: "értem a kérést, de a tartalma hibás".
            // Ugyanazt az űrlapot rajzoljuk ki újra, a beírt szöveggel és a hibákkal.
            http_response_code(422);
        }
        break;

    // ---------- UPDATE: meglévő jegyzet szerkesztése ----------
    case 'szerkeszt':
        // Nem létező (vagy nem egész) azonosító → 404, NEM egy üres űrlap 200-zal:
        // különben a felhasználó azt hinné, új jegyzetet ír.
        if ($id === null) {
            hiba(404, 'Nincs ilyen jegyzet.');
        }
        $stmt = db()->prepare('SELECT id, cim, tartalom FROM jegyzet WHERE id = ?');
        $stmt->execute([$id]);
        $jegyzet = $stmt->fetch() ?: hiba(404, 'Nincs ilyen jegyzet.');

        if ($post) {
            $jegyzet = urlap_adatok() + $jegyzet;   // az id megmarad, a cím és a tartalom az újak
            $hibak = validal($jegyzet);
            if (!$hibak) {
                // A WHERE id = ? NAGYON fontos: nélküle MINDEN jegyzet felülíródna!
                db()->prepare('UPDATE jegyzet SET cim = ?, tartalom = ?, frissitve = CURRENT_TIMESTAMP WHERE id = ?')
                    ->execute([$jegyzet['cim'], $jegyzet['tartalom'], $id]);
                flash('Jegyzet mentve.');
                redirect('jegyzetek.php');
            }
            http_response_code(422);
        }
        break;

    default:
        hiba(404, 'Ismeretlen oldal.');
}

$uzenet = flash();

// Innentől HTML. Szabály: ami a felhasználótól jön, az CSAK e()-n keresztül kerülhet ki.
// (Vigyázat: PHP-kommentben a kérdőjel + nagyobb-jel PÁROS lezárja a PHP-t,
//  ezért itt szándékosan nem írtam ki.)
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $m === 'lista' ? 'Jegyzeteim' : ($m === 'uj' ? 'Új jegyzet' : 'Jegyzet szerkesztése') ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<main>
  <nav class="fejlec">
    <a href="jegyzetek.php">Jegyzeteim</a>
    <a href="jegyzetek.php?m=uj" class="gomb">+ Új jegyzet</a>
  </nav>

  <?php if ($uzenet): ?>
    <p class="doboz ok" role="status"><?= e($uzenet) ?></p>
  <?php endif; ?>

<?php if ($m === 'lista'): ?>
  <h1>Jegyzeteim <span class="darab">(<?= count($jegyzetek) ?>)</span></h1>

  <?php if (!$jegyzetek): ?>
    <p class="ures">Még nincs jegyzeted – <a href="jegyzetek.php?m=uj">írd meg az elsőt</a>!</p>
  <?php endif; ?>

  <?php foreach ($jegyzetek as $j): ?>
    <article class="jegyzet">
      <h2><?= e($j['cim']) ?></h2>
      <!-- Előbb e(), utána nl2br(): fordítva a <br> is escape-elődne, és szövegként látszana. -->
      <p><?= nl2br(e(mb_strimwidth($j['tartalom'], 0, 200, '…'))) ?></p>
      <footer>
        <small>Módosítva: <?= e(helyi_ido($j['frissitve'])) ?></small>
        <span class="muveletek">
          <!-- Szerkesztés: sima link, mert csak MEGJELENÍT (GET). -->
          <a href="jegyzetek.php?m=szerkeszt&amp;id=<?= (int) $j['id'] ?>">Szerkesztés</a>
        </span>
      </footer>
    </article>
  <?php endforeach; ?>

<?php else: ?>
  <!-- Az új jegyzet és a szerkesztés UGYANAZ az űrlap: csak az action és a kezdőértékek mások. -->
  <h1><?= $m === 'uj' ? 'Új jegyzet' : 'Jegyzet szerkesztése' ?></h1>

  <?php if ($hibak): ?>
    <p class="doboz hiba" role="alert">Nem sikerült menteni – javítsd a pirossal jelölt mezőket.</p>
  <?php endif; ?>

  <!-- Szerkesztésnél az id-nek az action-ben a helye: nélküle a mentés nem tudná, melyik jegyzetet írja felül. -->
  <form method="post" class="urlap"
        action="jegyzetek.php?m=<?= e($m) ?><?= $jegyzet['id'] ? '&amp;id=' . (int) $jegyzet['id'] : '' ?>">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <label for="cim">Cím</label>
    <input id="cim" name="cim" maxlength="<?= CIM_MAX ?>" required
           placeholder="pl. Kardiológia – EKG alapok"
           value="<?= e($jegyzet['cim']) ?>"
           <?= isset($hibak['cim']) ? 'aria-invalid="true" aria-describedby="cim-hiba"' : '' ?>>
    <?php if (isset($hibak['cim'])): ?><p class="mezohiba" id="cim-hiba"><?= e($hibak['cim']) ?></p><?php endif; ?>

    <label for="tartalom">Tartalom</label>
    <textarea id="tartalom" name="tartalom" rows="10" maxlength="<?= TARTALOM_MAX ?>" required
              <?= isset($hibak['tartalom']) ? 'aria-invalid="true" aria-describedby="tartalom-hiba"' : '' ?>><?= e($jegyzet['tartalom']) ?></textarea>
    <?php if (isset($hibak['tartalom'])): ?><p class="mezohiba" id="tartalom-hiba"><?= e($hibak['tartalom']) ?></p><?php endif; ?>

    <div class="gombsor">
      <button type="submit">Mentés</button>
      <a href="jegyzetek.php">Mégse</a>
    </div>
  </form>
<?php endif; ?>
</main>
</body>
</html>
