<?php
// csrf.php – védelem a hamisított kérések (CSRF) ellen.
//
// A probléma: egy idegen oldal (vagy egy e-mail) a MI oldalunkra küldhet kérést
// a bejelentkezett orvos böngészőjéből. A böngésző ilyenkor a mi sütinket is
// elküldi, így a szerver azt hinné, hogy az orvos maga kérte a törlést.
//
// A megoldás: minden űrlapba teszünk egy titkos, véletlen kódot (tokent).
// Ezt csak a mi oldalunk ismeri (a munkamenetben tároljuk), az idegen oldal nem.
// Ha a beküldött kód hiányzik vagy nem egyezik → 403, a kérést nem dolgozzuk fel.

function csrf_token(): string
{
    // random_bytes(32): 32 kriptográfiailag véletlen bájt → 64 hexa karakter.
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_check(): void
{
    $kuldott = $_POST['csrf'] ?? '';

    // hash_equals: mindig ugyanannyi ideig tart, akárhol tér el a két szöveg,
    // így a válaszidőből nem lehet kitalálni a tokent (időzítéses támadás).
    // A csrf_token()-nel hasonlítunk, ami sosem üres – így üres munkamenettel
    // és üres tokennel sem lehet átjutni (hash_equals('', '') igaz lenne).
    if (!is_string($kuldott) || !hash_equals(csrf_token(), $kuldott)) {
        http_response_code(403);   // 403 Forbidden
        exit('Érvénytelen kérés (CSRF). Töltsd újra az oldalt, és próbáld újra.');
    }
}
