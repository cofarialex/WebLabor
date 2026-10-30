<?php
// bootstrap.php – minden oldal első sora ezt tölti be.
// Itt van minden közös dolog: beállítások, osztálybetöltés, munkamenet,
// segédfüggvények (escape, CSRF, flash, átirányítás, belépés-ellenőrzés).
declare(strict_types=1);

define('PROJEKT', dirname(__DIR__));
// Az adatbázis a public/ mappán KÍVÜL van, így a böngészőből nem tölthető le.
// Teszteléshez a KONYVTAR_DB környezeti változóval másik fájl adható meg.
define('DB_FAJL', getenv('KONYVTAR_DB') ?: PROJEKT . '/konyvtar.db');

date_default_timezone_set('Europe/Bucharest');

// Osztálybetöltés: a "KonyvRepository" osztályt a src/KonyvRepository.php-ból tölti be.
spl_autoload_register(function (string $osztaly): void {
    $fajl = __DIR__ . '/' . $osztaly . '.php';
    if (is_file($fajl)) {
        require $fajl;
    }
});

session_start([
    'cookie_httponly' => true,   // JavaScript nem olvashatja a munkamenet-sütit
    'cookie_samesite' => 'Lax',  // más oldalról indított POST-hoz nem küldi el a böngésző
    'use_strict_mode' => true,
]);

require __DIR__ . '/layout.php';

function db(): PDO
{
    return Db::kapcsolat();
}

// Kimenet escape-elése: minden adatbázisból vagy felhasználótól jövő szöveg
// CSAK ezen keresztül kerülhet a HTML-be (XSS ellen).
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ma(): string
{
    return date('Y-m-d');
}

// 2026-10-30 → 2026.10.30.
function datum(?string $iso): string
{
    return $iso ? str_replace('-', '.', $iso) . '.' : '–';
}

// ---------- CSRF (Műhely: php01, php04) ----------
// Minden POST-űrlapba bekerül egy titkos token; a szerver ellenőrzi, hogy
// a beküldött token egyezik-e a munkamenetben tárolttal.
function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_mezo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ellenoriz(): void
{
    $kuldott = $_POST['csrf'] ?? '';
    if (!is_string($kuldott) || !hash_equals(csrf_token(), $kuldott)) {
        hiba_oldal(403, 'Érvénytelen kérés (CSRF). Töltsd újra az oldalt, és próbáld újra.');
    }
}

// ---------- PRG: flash üzenet + 303 átirányítás (Műhely: php01) ----------
function flash(string $tipus, string $szoveg): void
{
    $_SESSION['flash'][] = ['tipus' => $tipus, 'szoveg' => $szoveg];
}

function flashek(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function atiranyit(string $hova): never
{
    header('Location: ' . $hova, true, 303);
    exit;
}

// Az adatot módosító oldalak csak POST-ot fogadnak el
// (antipattern: „GET kérés állapotváltoztatásra”).
function csak_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        hiba_oldal(405, 'Ez a művelet csak POST kéréssel érhető el.');
    }
    csrf_ellenoriz();
}

function hiba_oldal(int $kod, string $uzenet): never
{
    http_response_code($kod);
    fejlec((string) $kod);
    echo '<p class="doboz hiba">' . e($uzenet) . '</p><p><a href="index.php">← Vissza a katalógushoz</a></p>';
    lablec();
    exit;
}

// ---------- Belépés ----------
function bejelentkezve(): ?array
{
    return $_SESSION['konyvtaros'] ?? null;
}

// A védett oldalak első sora. Belépés nélkül 302-vel a belépő oldalra küld,
// és megjegyzi, honnan jött a felhasználó, hogy utána oda vigye vissza.
function belepes_kell(): void
{
    if (!bejelentkezve()) {
        $vissza = basename($_SERVER['SCRIPT_NAME']);
        header('Location: belepes.php?vissza=' . urlencode($vissza), true, 302);
        exit;
    }
}

// Csak a saját oldalainkra engedünk visszairányítani (különben egy
// belepes.php?vissza=https://gonosz.example link máshova vinné a felhasználót).
function biztonsagos_vissza(?string $hova): string
{
    return is_string($hova) && preg_match('/^[a-z_]+\.php$/', $hova) ? $hova : 'kolcsonzes.php';
}
