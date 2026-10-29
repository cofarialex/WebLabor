<?php
// db.php – közös segédfüggvények:
//   db()        → kapcsolat az adatbázissal
//   e()         → szöveg biztonságos kiírása HTML-be (XSS elleni védelem)
//   helyi_ido() → az UTC-ben tárolt időpont átírása marosvásárhelyi időre
//   flash()     → egyszer megjelenő üzenet ("Jegyzet létrehozva.") a következő oldalon
//   redirect()  → 303-as átirányítás + kilépés (a PRG minta második fele)
//   hiba()      → hibaoldal a megfelelő HTTP-státuszkóddal (403, 404, 405)
declare(strict_types=1);

const DB_FAJL = __DIR__ . '/jegyzetek.sqlite';

function db(): PDO
{
    // "static": a változó megmarad a függvényhívások között,
    // így egy kérésen belül csak egyszer nyitunk kapcsolatot.
    static $pdo = null;

    if ($pdo === null) {
        // Ha a fájl még nem létezik, az SQLite magától létrehozza.
        // (MySQL-lel: new PDO('mysql:host=localhost;dbname=umfst;charset=utf8mb4', 'app', 'jelszo', [...]))
        $pdo = new PDO('sqlite:' . DB_FAJL, null, null, [
            // Hiba esetén kivételt dob (és látjuk, mi a baj),
            // ahelyett hogy csendben false-t adna vissza.
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // A sorokat ['cim' => ..., 'tartalom' => ...] formában kapjuk vissza.
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

// flash('szöveg') → eltesszük a munkamenetbe;
// flash()         → kiolvassuk ÉS töröljük, így csak egyszer jelenik meg.
function flash(?string $uzenet = null): ?string
{
    if ($uzenet !== null) {
        $_SESSION['flash'] = $uzenet;
        return null;
    }
    $u = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $u;
}

// 303 See Other: "most kérd le ezt a címet GET-tel". Így az F5 (frissítés) csak
// egy ártalmatlan GET-et ismétel meg, és nem küldi be újra az űrlapot.
// A "never" visszatérési típus jelzi: ez a függvény sosem tér vissza.
function redirect(string $hova): never
{
    header('Location: ' . $hova, true, 303);
    exit;   // Fontos! Nélküle a szkript tovább futna, és kirajzolná az oldalt is.
}

// Hibaoldal a megfelelő státuszkóddal: a böngésző, a kereső és a curl is
// a kódból tudja meg, mi történt (a szöveg csak az embernek szól).
function hiba(int $kod, string $uzenet): never
{
    http_response_code($kod);
    exit('<!doctype html><html lang="hu"><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . $kod . ' – Jegyzeteim</title><link rel="stylesheet" href="style.css">'
        . '<main><h1>' . $kod . '</h1><p class="doboz hiba">' . e($uzenet) . '</p>'
        . '<p><a href="jegyzetek.php">← Vissza a jegyzetekhez</a></p></main></html>');
}
