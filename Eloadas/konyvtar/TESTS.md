# Tesztesetek

Indítás: `php -S localhost:8000 -t public` (friss adatbázissal: előtte töröld a `konyvtar.db`-t).
A curl-es tesztekhez a `B=http://localhost:8000` rövidítést használom.

| # | Teszt | Lépések | Elvárt eredmény |
|---|---|---|---|
| 1 | Katalógus és lapozás | Nyisd meg a főoldalt. | „20 találat”, 10 könyv, alul 1–2. oldal. |
| 2 | Keresés | Keress rá: `Netter`, majd `Fonyó`, majd `978-0-00-000013`. | 1–1 találat (cím, szerző, ISBN szerint). A Netter-atlasznál „2 / 5” elérhető. |
| 3 | SQL injection a keresőben | Keress rá: `%' OR 1=1 --` | „0 találat”, nincs hiba – a bemenet csak szöveg. |
| 4 | XSS a keresőben | Keress rá: `<script>alert(1)</script>` | Nem ugrik fel ablak, a szöveg betűről betűre látszik. |
| 5 | Lapszám beszorítása | `?oldal=999`, majd `?oldal=abc` | Az utolsó (2.), illetve az 1. oldal jelenik meg. |
| 6 | Védett oldal belépés nélkül | `curl -i $B/kolcsonzes.php` | `HTTP/1.1 302 Found`, `Location: belepes.php?vissza=kolcsonzes.php`. Ugyanez a `lejart.php`, `olvasok.php`, `olvaso.php` oldalakra. |
| 7 | Hibás belépés | Felhasználónév `konyvtaros`, jelszó `rossz`. | „Hibás felhasználónév vagy jelszó.” – nem derül ki, melyik volt rossz. |
| 8 | Helyes belépés | `konyvtaros` / `Konyvtar2026!` | Átirányít a Kölcsönzés oldalra, a menüben megjelenik a Kilépés. |
| 9 | Kiadás | Leltári szám `K-0002`, olvasó: bárki → Kiadás. | „Kiadva: … (K-0002), határidő: <ma + 14 nap>”. A Netter-atlasznál most „1 / 5” elérhető. |
| 10 | Kiadott példány újbóli kiadása | Ugyanaz: `K-0002`, másik olvasó. | „Ez a példány már ki van adva (határidő: …)”, nem jön létre új kölcsönzés. |
| 11 | Nem létező példány | Leltári szám `K-9999`. | „Nincs „K-9999” leltári számú példány.” |
| 12 | Egyidejű kiadás (versenyhelyzet) | Két böngészőfülben nyisd meg a Kölcsönzés oldalt, mindkettőben `K-0008`, két különböző olvasó, és küldd el mindkettőt gyorsan egymás után. | Csak az egyik sikerül, a másik: „Ez a példány már ki van adva”. (Automatizálva 12 párhuzamos curl-kéréssel is kipróbáltam: pontosan 1 nyitott kölcsönzés jött létre.) |
| 13 | Visszavétel késéssel | A „Kint lévő könyvek” listában a `K-0003` sorában: Visszavétel. | „Visszavéve: … – 16 nap késéssel.” A sor eltűnik a listából. |
| 14 | Kétszeres visszavétel | Ugyanazt a visszavételt még egyszer elküldeni (pl. a böngésző Vissza gombjával és újraküldéssel). | „Ez a kölcsönzés nem létezik, vagy már visszahozták.” |
| 15 | Lejártak olvasónként | Lejártak oldal (a 13. teszt után). | 4 lejárt kölcsönzés, 3 olvasónál, olvasónként csoportosítva, késési napokkal. |
| 16 | CSV-export | Lejártak → „Letöltés CSV-ben”. | `lejart-<dátum>.csv` letöltődik, Excelben ékezethelyesen nyílik, fejléc + 4 sor. |
| 17 | Olvasó előzménye | Olvasók → Kovács Anna. | 4 kölcsönzés (visszahozottak is), a késések pirossal. |
| 18 | CSRF | `curl -i -X POST -d "muvelet=kiad&leltari=K-0005&olvaso_id=1" $B/kolcsonzes.php` (token nélkül, belépett sütivel vagy anélkül) | 403 vagy 302 a belépésre – kiadás nem történik. |
| 19 | Rossz azonosító | `curl -i "$B/konyv.php?id=999"` és `"$B/konyv.php?id=1%20OR%201=1"` | `404 Not Found` mindkettőre. |
| 20 | Kilépés GET-tel | `curl -i $B/kilepes.php` | `405 Method Not Allowed`, `Allow: POST`. |
| 21 | Az adatbázis nem tölthető le | `curl -i $B/konyvtar.db` | `404` – a fájl a `public/` mappán kívül van. |
| 22 | Idegen kulcs az adatbázisban | `sqlite3 konyvtar.db "PRAGMA foreign_keys=ON; INSERT INTO kolcsonzes (peldany_id, olvaso_id, kiadva, hatarido) VALUES (1, 999, '2026-01-01', '2026-01-15');"` | `FOREIGN KEY constraint failed` – nem létező olvasóhoz nem lehet kölcsönzést írni. |
| 23 | Egyedi index az adatbázisban | `sqlite3 konyvtar.db "INSERT INTO kolcsonzes (peldany_id, olvaso_id, kiadva, hatarido) VALUES (1, 1, '2026-01-01', '2026-01-15');"` (az 1-es példány ki van adva) | `UNIQUE constraint failed` – az adatbázis is megakadályozza a dupla kiadást. |
