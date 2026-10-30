<?php
// olvaso.php – egy olvasó kölcsönzési előzménye (a visszahozottak is), késésekkel.
require __DIR__ . '/../src/bootstrap.php';
belepes_kell();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$olvaso = $id ? (new OlvasoRepository(db()))->egy($id) : null;
if (!$olvaso) {
    hiba_oldal(404, 'Nincs ilyen olvasó.');
}
$elozmeny = (new KolcsonzesRepository(db()))->elozmeny($id);
$ma = ma();

fejlec($olvaso['nev']);
?>
<p><a href="olvasok.php">← Olvasók</a></p>
<h1><?= e($olvaso['nev']) ?></h1>
<p class="halvany">Olvasójegy: <?= e($olvaso['olvasojegy']) ?> · <?= e($olvaso['email']) ?></p>

<h2>Kölcsönzési előzmény (<?= count($elozmeny) ?>)</h2>
<?php if (!$elozmeny): ?>
  <p class="halvany">Még nem kölcsönzött.</p>
<?php else: ?>
<table>
  <thead><tr><th>Könyv</th><th>Leltári szám</th><th>Kiadva</th><th>Határidő</th><th>Visszahozva</th><th>Késés</th></tr></thead>
  <tbody>
  <?php foreach ($elozmeny as $k): ?>
    <?php $keses = KolcsonzesRepository::kesesNapok($k['hatarido'], $k['visszahozva'] ?? $ma); ?>
    <tr>
      <td><a href="konyv.php?id=<?= (int) $k['konyv_id'] ?>"><?= e($k['cim']) ?></a></td>
      <td><?= e($k['leltari_szam']) ?></td>
      <td><?= e(datum($k['kiadva'])) ?></td>
      <td><?= e(datum($k['hatarido'])) ?></td>
      <td><?= $k['visszahozva'] ? e(datum($k['visszahozva'])) : '<em>még kint van</em>' ?></td>
      <td class="<?= $keses > 0 ? 'piros' : '' ?>"><?= $keses > 0 ? "{$keses} nap" : '–' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php lablec();
