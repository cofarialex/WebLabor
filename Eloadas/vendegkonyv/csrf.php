<?php
// csrf.php – védelem a hamisított űrlapbeküldés (CSRF) ellen.
//
// A probléma: egy idegen weboldal is tartalmazhat olyan rejtett űrlapot, ami
// a MI oldalunkra küld POST kérést, a látogató böngészőjéből. A böngésző ilyenkor
// a mi sütinket is elküldi, így a szerver azt hinné, hogy a látogató maga küldte.
//
// A megoldás: minden űrlapba teszünk egy titkos, véletlen kódot (tokent).
// Ezt csak a mi oldalunk ismeri (a munkamenetben tároljuk), az idegen oldal nem.
// Ha a beküldött kód nem egyezik a tárolttal → 403, nem dolgozzuk fel.
declare(strict_types=1);

function csrf_token(): string
{
    // "??=": ha még nincs token a munkamenetben, most létrehozzuk.
    // random_bytes(32): 32 kriptográfiailag véletlen bájt → 64 hexa karakter.
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_check(): void
{
    $kuldott = $_POST['csrf'] ?? '';

    // hash_equals: mindig ugyanannyi ideig tart, akárhol tér el a két szöveg,
    // így a válaszidőből nem lehet kitalálni a tokent (időzítéses támadás).
    //
    // Figyelem: a csrf_token()-nel hasonlítunk, NEM a $_SESSION['csrf'] ?? ''-vel.
    // Ha üres munkamenettel és üres tokennel jönne valaki, a hash_equals('', '')
    // igazat adna, és átjutna. A csrf_token() sosem üres, így ez nem fordulhat elő.
    if (!is_string($kuldott) || !hash_equals(csrf_token(), $kuldott)) {
        http_response_code(403);   // 403 Forbidden: "tilos"
        exit('Érvénytelen kérés (CSRF). Töltsd újra az oldalt, és próbáld újra.');
    }
}
