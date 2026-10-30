<?php
declare(strict_types=1);

// Egyetlen PDO-kapcsolat kérésenként. Ha az adatbázisfájl még nem létezik,
// létrehozza, és betölti a sémát meg a mintaadatokat.
final class Db
{
    private static ?PDO $pdo = null;

    public static function kapcsolat(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $uj = !file_exists(DB_FAJL);

        $pdo = new PDO('sqlite:' . DB_FAJL, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,   // ha épp más ír, legfeljebb 5 mp-ig vár
        ]);

        // SQLite-ban az idegen kulcsok ellenőrzése alapból ki van kapcsolva,
        // és kapcsolatonként kell bekapcsolni.
        $pdo->exec('PRAGMA foreign_keys = ON');

        if ($uj) {
            $pdo->exec(file_get_contents(PROJEKT . '/schema.sql'));
            $pdo->exec(file_get_contents(PROJEKT . '/seed.sql'));
        }

        return self::$pdo = $pdo;
    }
}
