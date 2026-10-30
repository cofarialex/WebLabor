<?php
// konyv.php – egy könyv adatai és a példányai: melyik elérhető, melyik van kint.
require __DIR__ . '/../src/bootstrap.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$konyvek = new KonyvRepository(db());
$konyv = $id ? $konyvek->egy($id) : null;
if (!$konyv) {
    hiba_oldal(404, 'Nincs ilyen könyv.');
}
$peldanyok = $konyvek->peldanyok($id);
$belepve = bejelentkezve();

fejlec($konyv['cim']);
?>
<p><a href="index.php">← Katalógus</a></p>
<h1><?= e($konyv['cim']) ?></h1>
<p><?= e($konyv['szerzo']) ?> · ISBN <?= e($konyv['isbn']) ?></p>

<h2>Példányok</h2>
<table>
  <thead><tr><th>Leltári szám</th><th>Állapot</th><?php if ($belepve): ?><th></th><?php endif; ?></tr></thead>
  <tbody>
  <?php foreach ($peldanyok as $p): ?>
    <tr>
      <td><?= e($p['leltari_szam']) ?></td>
      <?php if ($p['hatarido'] === null): ?>
        <td class="zold">Elérhető</td>
        <?php if ($belepve): ?>
          <td><a href="kolcsonzes.php?leltari=<?= urlencode($p['leltari_szam']) ?>">Kiadás</a></td>
        <?php endif; ?>
      <?php else: ?>
        <td class="piros">Kikölcsönözve, határidő: <?= e(datum($p['hatarido'])) ?></td>
        <?php if ($belepve): ?>
          <!-- Hogy kinél van, az személyes adat: csak a könyvtáros láthatja. -->
          <td><a href="olvaso.php?id=<?= (int) $p['olvaso_id'] ?>"><?= e($p['olvaso_nev']) ?></a></td>
        <?php endif; ?>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php lablec();
