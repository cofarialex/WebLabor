<?php
require 'db.php';
require 'csrf.php';

// Adatot módosító művelet (mentés, törlés) CSAK POST lehet. Egy GET-linket a böngésző
// a felhasználó tudta nélkül is lekér – pl. egy e-mailbe rejtett <img src="...?torol=5"> miatt.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Tőlünk jött-e az űrlap? Ha a CSRF-token hiányzik vagy nem egyezik → 403, itt megáll.
    csrf_check();

    if (isset($_POST['torol'])) {
        // Csak pozitív egész számot fogadunk el azonosítónak – a "0 OR 1=1" már itt elakad.
        $id = filter_var($_POST['torol'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            http_response_code(400);   // 400 Bad Request
            exit('Hibás jegyzet-azonosító.');
        }
        // Prepared statement: az id ADATKÉNT megy az adatbázishoz, sosem válhat SQL-kóddá.
        // AND orvos_id = ? → csak a SAJÁT jegyzetét törölheti az orvos (IDOR elleni védelem).
        // Az orvos azonosítója a munkamenetből jön, nem a kérésből.
        $stmt = $pdo->prepare('DELETE FROM jegyzetek WHERE id = ? AND orvos_id = ?');
        $stmt->execute([$id, $_SESSION['orvos_id']]);
        if ($stmt->rowCount() === 0) {
            // Nem áruljuk el, hogy a jegyzet létezik-e – csak annyit, hogy itt nincs ilyen.
            http_response_code(404);
            exit('Nincs ilyen jegyzet (vagy nem a tiéd).');
        }
        // 303 See Other: "most kérd le ezt a címet GET-tel".
        // Az exit KÖTELEZŐ: a header() csak beállít egy fejlécet, a szkript magától
        // tovább futna (lekérdezés + az egész oldal kiírása az átirányító válaszba).
        header('Location: index.php', true, 303);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO jegyzetek (orvos_id, beteg, szoveg) VALUES (?, ?, ?)');
    $stmt->execute([$_SESSION['orvos_id'], trim($_POST['beteg']), trim($_POST['szoveg'])]);
    header('Location: index.php', true, 303);
    exit;
}

$q = $_GET['q'] ?? '';
$sql = "SELECT * FROM jegyzetek WHERE orvos_id = " . $_SESSION['orvos_id']
     . " AND beteg LIKE '%" . $q . "%' ORDER BY id DESC";
$sorok = $pdo->query($sql)->fetchAll();
?>
<!doctype html>
<html lang="hu">
<head><meta charset="utf-8"><title>Betegjegyzetek</title></head>
<body>
<h1>Betegjegyzetek (orvos #<?= (int) $_SESSION['orvos_id'] ?>)</h1>
<form method="get">
  <label for="q">Keresés betegnévre</label>
  <input id="q" name="q" value="<?= $q ?>">
  <button>Keresés</button>
</form>
<ul>
<?php foreach ($sorok as $s): ?>
  <li><b><?= htmlspecialchars($s['beteg']) ?></b>: <?= $s['szoveg'] ?>
      <!-- Törlés POST-űrlappal: a rejtett mezők viszik a jegyzet azonosítóját és a CSRF-tokent. -->
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="torol" value="<?= (int) $s['id'] ?>">
        <button>Törlés</button>
      </form></li>
<?php endforeach; ?>
</ul>
<h2>Új jegyzet</h2>
<form method="post">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <label for="beteg">Beteg</label> <input id="beteg" name="beteg" required>
  <label for="szoveg">Jegyzet</label> <textarea id="szoveg" name="szoveg" required></textarea>
  <button>Mentés</button>
</form>
</body>
</html>
