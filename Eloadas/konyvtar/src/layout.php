<?php
// layout.php – az oldalak közös fejléce (menü, belépési állapot, üzenetek) és lábléce.
declare(strict_types=1);

function fejlec(string $cim): void
{
    $belepve = bejelentkezve();
    ?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($cim) ?> – Kari könyvtár</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="fejlec">
  <a href="index.php" class="logo">Kari könyvtár</a>
  <nav>
    <a href="index.php">Katalógus</a>
    <?php if ($belepve): ?>
      <a href="kolcsonzes.php">Kölcsönzés</a>
      <a href="lejart.php">Lejártak</a>
      <a href="olvasok.php">Olvasók</a>
      <form method="post" action="kilepes.php" class="inline">
        <?= csrf_mezo() ?>
        <button type="submit" class="link">Kilépés (<?= e($belepve['nev']) ?>)</button>
      </form>
    <?php else: ?>
      <a href="belepes.php">Könyvtáros belépés</a>
    <?php endif; ?>
  </nav>
</header>
<main>
  <?php foreach (flashek() as $f): ?>
    <p class="doboz <?= $f['tipus'] === 'ok' ? 'ok' : 'hiba' ?>" role="status"><?= e($f['szoveg']) ?></p>
  <?php endforeach; ?>
    <?php
}

function lablec(): void
{
    echo "</main>\n</body>\n</html>\n";
}
