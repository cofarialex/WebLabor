<?php
// kolcsonzes.php – kiadás és visszavétel, plusz a kint lévő könyvek listája.
// Csak belépett könyvtáros érheti el.
//
//   GET                          → űrlap + nyitott kölcsönzések
//   POST muvelet=kiad            → kiadás → 303 vissza ide
//   POST muvelet=visszavesz      → visszavétel → 303 vissza ide
require __DIR__ . '/../src/bootstrap.php';
belepes_kell();

$kolcsonzesek = new KolcsonzesRepository(db());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ellenoriz();
    $muvelet = $_POST['muvelet'] ?? '';

    // A kapott adatokat a szerveren is ellenőrizzük – a böngészőnek nem hiszünk
    // (Műhely: „Ne bízz a kliensben”, antipattern: „Csak kliensoldali validálás”).
    try {
        if ($muvelet === 'kiad') {
            $leltari = trim(is_string($_POST['leltari'] ?? null) ? $_POST['leltari'] : '');
            $olvasoId = filter_var($_POST['olvaso_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($leltari === '' || !$olvasoId) {
                throw new KolcsonzesHiba('Add meg a leltári számot, és válassz olvasót.');
            }
            $k = $kolcsonzesek->kiad($leltari, $olvasoId, ma());
            flash('ok', "Kiadva: {$k['cim']} ({$leltari}), határidő: " . datum($k['hatarido']));
        } elseif ($muvelet === 'visszavesz') {
            $id = filter_var($_POST['kolcsonzes_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) {
                throw new KolcsonzesHiba('Hibás kölcsönzés-azonosító.');
            }
            $k = $kolcsonzesek->visszavesz($id, ma());
            flash('ok', "Visszavéve: {$k['cim']} ({$k['leltari_szam']}) – "
                . ($k['keses'] > 0 ? "{$k['keses']} nap késéssel." : 'határidőn belül.'));
        } else {
            throw new KolcsonzesHiba('Ismeretlen művelet.');
        }
    } catch (KolcsonzesHiba $e) {
        flash('hiba', $e->getMessage());
    }
    // PRG: sikertől függetlenül átirányítunk, így az F5 nem küldi be újra az űrlapot.
    atiranyit('kolcsonzes.php');
}

$nyitottak = $kolcsonzesek->nyitottak();
$olvasok = (new OlvasoRepository(db()))->osszes(ma());
$elotoltott = is_string($_GET['leltari'] ?? null) ? $_GET['leltari'] : '';
$ma = ma();

fejlec('Kölcsönzés');
?>
<h1>Kölcsönzés</h1>

<form method="post" class="urlap">
  <h2>Kiadás</h2>
  <?= csrf_mezo() ?>
  <input type="hidden" name="muvelet" value="kiad">
  <label for="leltari">Példány leltári száma</label>
  <input id="leltari" name="leltari" required placeholder="pl. K-0005" value="<?= e($elotoltott) ?>" autofocus>
  <label for="olvaso_id">Olvasó</label>
  <select id="olvaso_id" name="olvaso_id" required>
    <option value="">– válassz –</option>
    <?php foreach ($olvasok as $o): ?>
      <option value="<?= (int) $o['id'] ?>"><?= e($o['nev']) ?> (<?= e($o['olvasojegy']) ?>)</option>
    <?php endforeach; ?>
  </select>
  <p class="halvany">Határidő: a mai naptól <?= KolcsonzesRepository::KOLCSONZESI_IDO_NAP ?> nap.</p>
  <button type="submit">Kiadás</button>
</form>

<h2>Kint lévő könyvek (<?= count($nyitottak) ?>)</h2>
<table>
  <thead><tr><th>Könyv</th><th>Leltári szám</th><th>Olvasó</th><th>Határidő</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($nyitottak as $k): ?>
    <?php $keses = KolcsonzesRepository::kesesNapok($k['hatarido'], $ma); ?>
    <tr>
      <td><a href="konyv.php?id=<?= (int) $k['konyv_id'] ?>"><?= e($k['cim']) ?></a></td>
      <td><?= e($k['leltari_szam']) ?></td>
      <td><a href="olvaso.php?id=<?= (int) $k['olvaso_id'] ?>"><?= e($k['olvaso_nev']) ?></a></td>
      <td class="<?= $keses > 0 ? 'piros' : '' ?>">
        <?= e(datum($k['hatarido'])) ?><?= $keses > 0 ? " ({$keses} nap késés)" : '' ?>
      </td>
      <td>
        <!-- Visszavétel: adatot módosít, ezért POST-űrlap (nem link). -->
        <form method="post" class="inline">
          <?= csrf_mezo() ?>
          <input type="hidden" name="muvelet" value="visszavesz">
          <input type="hidden" name="kolcsonzes_id" value="<?= (int) $k['id'] ?>">
          <button type="submit" class="kicsi-gomb">Visszavétel</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php lablec();
