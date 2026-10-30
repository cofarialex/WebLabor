<?php
// lejart.php – lejárt kölcsönzések olvasónként, CSV-exporttal (?csv=1).
require __DIR__ . '/../src/bootstrap.php';
belepes_kell();

$ma = ma();
$lejartak = (new KolcsonzesRepository(db()))->lejartak($ma);

// ---------- CSV export (Műhely: php22) ----------
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lejart-' . $ma . '.csv"');
    $ki = fopen('php://output', 'w');
    fwrite($ki, "\xEF\xBB\xBF");   // BOM: az Excel így ismeri fel az ékezeteket
    fputcsv($ki, ['Olvasó', 'Könyv', 'Leltári szám', 'Kiadva', 'Határidő', 'Késés (nap)'], ';');
    foreach ($lejartak as $k) {
        fputcsv($ki, array_map('csv_cella', [
            $k['olvaso_nev'], $k['cim'], $k['leltari_szam'], $k['kiadva'], $k['hatarido'],
            (string) KolcsonzesRepository::kesesNapok($k['hatarido'], $ma),
        ]), ';');
    }
    exit;
}

// CSV-befecskendezés ellen: az =, +, - vagy @ jellel kezdődő cellát az Excel
// képletként futtatná, ezért elé teszünk egy aposztrófot.
function csv_cella(string $s): string
{
    return preg_match('/^[=+\-@]/', $s) ? "'" . $s : $s;
}

// Csoportosítás olvasónként: [olvasó id => ['nev' => ..., 'tetelek' => [...]]]
$csoportok = [];
foreach ($lejartak as $k) {
    $csoportok[$k['olvaso_id']]['nev'] = $k['olvaso_nev'];
    $csoportok[$k['olvaso_id']]['tetelek'][] = $k;
}

fejlec('Lejárt kölcsönzések');
?>
<h1>Lejárt kölcsönzések</h1>
<p class="halvany"><?= count($lejartak) ?> lejárt kölcsönzés, <?= count($csoportok) ?> olvasónál
  · <a href="lejart.php?csv=1">Letöltés CSV-ben</a></p>

<?php if (!$csoportok): ?>
  <p class="doboz ok">Nincs lejárt kölcsönzés.</p>
<?php endif; ?>

<?php foreach ($csoportok as $olvasoId => $cs): ?>
  <section class="kartya">
    <h2><a href="olvaso.php?id=<?= (int) $olvasoId ?>"><?= e($cs['nev']) ?></a>
      <span class="halvany">(<?= count($cs['tetelek']) ?> db)</span></h2>
    <ul>
      <?php foreach ($cs['tetelek'] as $k): ?>
        <li><?= e($k['cim']) ?> (<?= e($k['leltari_szam']) ?>) – határidő: <?= e(datum($k['hatarido'])) ?>,
          <strong class="piros"><?= KolcsonzesRepository::kesesNapok($k['hatarido'], $ma) ?> nap késés</strong></li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endforeach; ?>
<?php lablec();
