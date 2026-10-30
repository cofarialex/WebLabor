<?php
// index.php – katalógus: keresés címre, szerzőre vagy ISBN-re, lapozással.
// Belépés nélkül is elérhető. (Műhely: php10 – keresés és lapozás)
require __DIR__ . '/../src/bootstrap.php';

const OLDALANKENT = 10;

$q = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');

$konyvek = new KonyvRepository(db());
$talalat = $konyvek->talalatokSzama($q);
$oldalak = max(1, (int) ceil($talalat / OLDALANKENT));

// A lapszám csak 1 és az utolsó oldal közötti egész lehet („beszorítás”).
$oldal = filter_var($_GET['oldal'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);
$oldal = min(max(1, $oldal), $oldalak);

$lista = $konyvek->keres($q, OLDALANKENT, ($oldal - 1) * OLDALANKENT);

fejlec('Katalógus');
?>
<h1>Katalógus</h1>

<form method="get" class="kereso">
  <label for="q">Keresés címre, szerzőre vagy ISBN-re</label>
  <div class="sor">
    <input id="q" name="q" value="<?= e($q) ?>" placeholder="pl. Netter, anatómia, 978-0-00">
    <button type="submit">Keresés</button>
  </div>
</form>

<p class="halvany"><?= $talalat ?> találat<?= $q !== '' ? ' erre: „' . e($q) . '”' : '' ?></p>

<?php if ($lista): ?>
<table>
  <thead><tr><th>Cím</th><th>Szerző</th><th>ISBN</th><th>Elérhető</th></tr></thead>
  <tbody>
  <?php foreach ($lista as $k): ?>
    <tr>
      <td><a href="konyv.php?id=<?= (int) $k['id'] ?>"><?= e($k['cim']) ?></a></td>
      <td><?= e($k['szerzo']) ?></td>
      <td class="kicsi"><?= e($k['isbn']) ?></td>
      <td class="<?= $k['elerheto'] > 0 ? 'zold' : 'piros' ?>"><?= (int) $k['elerheto'] ?> / <?= (int) $k['osszes'] ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php if ($oldalak > 1): ?>
<nav class="lapozo" aria-label="Lapozás">
  <?php for ($i = 1; $i <= $oldalak; $i++): ?>
    <?php if ($i === $oldal): ?>
      <span aria-current="page"><?= $i ?></span>
    <?php else: ?>
      <a href="?<?= e(http_build_query(['q' => $q, 'oldal' => $i])) ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>
</nav>
<?php endif; ?>
<?php lablec();
