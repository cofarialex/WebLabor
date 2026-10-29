<?php
// Séma (egyszer futtasd, lásd schema.sql):
// CREATE TABLE jegyzetek (id INT AUTO_INCREMENT PRIMARY KEY, orvos_id INT NOT NULL,
//   beteg VARCHAR(100) NOT NULL, szoveg TEXT NOT NULL, letrehozva TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
// A kapcsolat címe (DSN) a DB_DSN környezeti változóval felülírható: a teszt.sh így
// futtatja az alkalmazást egy ideiglenes SQLite adatbázison, MySQL nélkül.
$dsn = getenv('DB_DSN') ?: 'mysql:host=localhost;dbname=rendelo;charset=utf8mb4';
$pdo = new PDO($dsn, 'rendelo_app', getenv('DB_PASS') ?: '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
// A munkamenet-süti beállításai:
//   SameSite=Lax → más oldalról indított POST-hoz és <img>-kéréshez a böngésző nem küldi el
//   HttpOnly     → JavaScript nem olvashatja (egy XSS nem tudja ellopni)
//   strict mode  → csak a szerver által kiadott munkamenet-azonosítót fogadjuk el
session_start([
    'cookie_samesite' => 'Lax',
    'cookie_httponly' => true,
    'use_strict_mode' => true,
]);
// Demó: a belépés egyszerűsítve – ?orvos=2 paraméterrel válthatsz orvost
if (isset($_GET['orvos'])) { $_SESSION['orvos_id'] = (int) $_GET['orvos']; }
$_SESSION['orvos_id'] = $_SESSION['orvos_id'] ?? 1;

// e() – szöveg biztonságos kiírása HTML-be (XSS elleni védelem).
// A < > & " ' karaktereket HTML-entitásra cseréli (&lt; &gt; &amp; &quot; &#039;),
// így a felhasználó szövege mindig SZÖVEG marad, sosem válik HTML-lé vagy JavaScriptté.
// ENT_QUOTES: az idézőjeleket is átírja → attribútumból (value="...") sem lehet kitörni.
function e($szoveg): string
{
    return htmlspecialchars((string) $szoveg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
