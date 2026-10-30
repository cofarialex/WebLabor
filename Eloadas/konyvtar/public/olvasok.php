<?php
// olvasok.php – az olvasók listája a nyitott és lejárt kölcsönzéseik számával.
require __DIR__ . '/../src/bootstrap.php';
belepes_kell();

$olvasok = (new OlvasoRepository(db()))->osszes(ma());

fejlec('Olvasók');
?>
<h1>Olvasók</h1>
<table>
  <thead><tr><th>Név</th><th>Olvasójegy</th><th>Kint lévő</th><th>Ebből lejárt</th></tr></thead>
  <tbody>
  <?php foreach ($olvasok as $o): ?>
    <tr>
      <td><a href="olvaso.php?id=<?= (int) $o['id'] ?>"><?= e($o['nev']) ?></a></td>
      <td><?= e($o['olvasojegy']) ?></td>
      <td><?= (int) $o['nyitott'] ?></td>
      <td class="<?= $o['lejart'] > 0 ? 'piros' : '' ?>"><?= (int) $o['lejart'] ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php lablec();
