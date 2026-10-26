<?php
// db.php – közös segédfüggvények:
//   db()        → kapcsolat az adatbázissal
//   e()         → szöveg biztonságos kiírása HTML-be (XSS elleni védelem)
//   helyi_ido() → az UTC-ben tárolt időpont átírása marosvásárhelyi időre
declare(strict_types=1);

const DB_FAJL = __DIR__ . '/vendegkonyv.sqlite';

function db(): PDO
{
    // "static": a változó megmarad a függvényhívások között,
    // így egy kérésen belül csak egyszer nyitunk kapcsolatot.
    static $pdo = null;

    if ($pdo === null) {
        // Ha a fájl még nem létezik, az SQLite magától létrehozza.
        $pdo = new PDO('sqlite:' . DB_FAJL, null, null, [
            // Hiba esetén kivételt dob (és látjuk, mi a baj),
            // ahelyett hogy csendben false-t adna vissza.
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // A sorokat ['nev' => ..., 'uzenet' => ...] formában kapjuk vissza.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Valódi prepared statement: az adat és az SQL külön utazik
            // az adatbázishoz, így az adat soha nem válhat SQL-kóddá.
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Létrehozza a táblát, ha még nincs (lásd schema.sql).
        $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
    }

    return $pdo;
}

// Minden felhasználótól jövő szöveget CSAK ezen keresztül írunk ki a HTML-be.
// A < > " ' & jeleket ártalmatlan "entitásokká" alakítja (pl. < → &lt;),
// így a böngésző szövegként mutatja őket, és nem futtat le beírt kódot.
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Az SQLite a CURRENT_TIMESTAMP-et UTC-ben (világidőben) tárolja.
// Megjelenítéskor átváltjuk a helyi időzónára; a nyári/téli időszámítást
// a PHP időzóna-adatbázisa intézi helyettünk.
function helyi_ido(string $utc): string
{
    $ido = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    return $ido->setTimezone(new DateTimeZone('Europe/Bucharest'))->format('Y.m.d. H:i');
}
