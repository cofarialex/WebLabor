<?php
// belepes.php – könyvtárosi belépés. (Műhely: php05 – password_hash / password_verify)
require __DIR__ . '/../src/bootstrap.php';

$vissza = biztonsagos_vissza($_GET['vissza'] ?? $_POST['vissza'] ?? null);
$hiba = null;
$felhasznalonev = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_ellenoriz();
    $felhasznalonev = trim(is_string($_POST['felhasznalonev'] ?? null) ? $_POST['felhasznalonev'] : '');
    $jelszo = is_string($_POST['jelszo'] ?? null) ? $_POST['jelszo'] : '';

    $konyvtaros = (new KonyvtarosRepository(db()))->felhasznalonevAlapjan($felhasznalonev);

    // Az adatbázisban csak a jelszó hash-e van; a password_verify ezzel hasonlít.
    if ($konyvtaros && password_verify($jelszo, $konyvtaros['jelszo_hash'])) {
        // Új munkamenet-azonosító belépéskor (munkamenet-rögzítés ellen).
        session_regenerate_id(true);
        $_SESSION['konyvtaros'] = ['id' => $konyvtaros['id'], 'nev' => $konyvtaros['nev']];
        flash('ok', 'Sikeres belépés.');
        atiranyit($vissza);
    }
    // Általános hibaüzenet: nem áruljuk el, hogy a név vagy a jelszó volt-e rossz.
    $hiba = 'Hibás felhasználónév vagy jelszó.';
}

fejlec('Belépés');
?>
<h1>Könyvtáros belépés</h1>
<?php if ($hiba): ?><p class="doboz hiba" role="alert"><?= e($hiba) ?></p><?php endif; ?>

<form method="post" class="urlap keskeny">
  <?= csrf_mezo() ?>
  <input type="hidden" name="vissza" value="<?= e($vissza) ?>">
  <label for="felhasznalonev">Felhasználónév</label>
  <input id="felhasznalonev" name="felhasznalonev" required autocomplete="username" value="<?= e($felhasznalonev) ?>">
  <label for="jelszo">Jelszó</label>
  <input id="jelszo" name="jelszo" type="password" required autocomplete="current-password">
  <button type="submit">Belépés</button>
</form>
<?php lablec();
