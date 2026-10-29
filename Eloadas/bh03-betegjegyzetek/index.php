<?php
require 'db.php';

if (isset($_GET['torol'])) {
    // Csak pozitív egész számot fogadunk el azonosítónak – a "0 OR 1=1" már itt elakad.
    $id = filter_var($_GET['torol'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        http_response_code(400);   // 400 Bad Request
        exit('Hibás jegyzet-azonosító.');
    }
    // Prepared statement: az id ADATKÉNT megy az adatbázishoz, sosem válhat SQL-kóddá.
    $stmt = $pdo->prepare('DELETE FROM jegyzetek WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
      <a href="?torol=<?= $s['id'] ?>">Törlés</a></li>
<?php endforeach; ?>
</ul>
<h2>Új jegyzet</h2>
<form method="post">
  <label for="beteg">Beteg</label> <input id="beteg" name="beteg" required>
  <label for="szoveg">Jegyzet</label> <textarea id="szoveg" name="szoveg" required></textarea>
  <button>Mentés</button>
</form>
</body>
</html>
