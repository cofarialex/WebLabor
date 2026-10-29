# Betegjegyzetek – a kiszivárgó rendelő – hibariport (BH03)

Repó: https://github.com/cofarialex/WebLabor/tree/main/Eloadas/bh03-betegjegyzetek
Készítette: cofarialex, <csoport>
Kiinduló (hibás) kód: [b0a0429](https://github.com/cofarialex/WebLabor/commit/b0a0429fa1b1f841aa4a324bfe60ef1786bd55f7) · Tesztek: [08a9866](https://github.com/cofarialex/WebLabor/commit/08a9866236f6e0c777739250338234e829396988) (`teszt.sh`)

**Tesztelés röviden:** `bash teszt.sh` – saját PHP szervert indít ideiglenes SQLite adatbázissal, és két orvos-munkamenettel (két sütitárolóval) végigpróbálja a támadásokat. Az eredeti kódon 3 PASS / 7 FAIL, a végén 10 PASS / 0 FAIL.

## 1. hiba – SQL injection a törlésben
- **Hiba leírása és reprodukálása:** Orvos #1-ként megnyitva `index.php?torol=0%20OR%201=1` → az adatbázisból **minden** jegyzet eltűnik (minden orvosé). `curl -b sutik "http://localhost:8000/index.php?torol=0%20OR%201=1"`
- **Gyökérok:** `index.php` 5. sor: `$pdo->exec('DELETE FROM jegyzetek WHERE id = ' . $_GET['torol'])` – a bemenet összefűzve kerül az SQL-be, így a lekérdezés `DELETE ... WHERE id = 0 OR 1=1` lesz. OWASP A03:2021 Injection, CWE-89.
- **Javítás:** [7b8d85d](https://github.com/cofarialex/WebLabor/commit/7b8d85d958dc44dff6729ba06886ffd1caa96177)
  - Előtte: `$pdo->exec('DELETE FROM jegyzetek WHERE id = ' . $_GET['torol']);`
  - Utána: `$id = filter_var(..., FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);` (nem szám → 400), majd `$pdo->prepare('DELETE FROM jegyzetek WHERE id = ?')->execute([$id])`
- **Hogyan teszteltem:** `teszt.sh` T1: ugyanaz a payload GET-tel és POST-tal is → a jegyzet megmarad (javítás előtt FAIL, után PASS).
- **Tanulság / alapelv:** Soha ne bízz a bemenetben – SQL-be csak prepared statementtel kerülhet adat.

## 2. hiba – IDOR: bárki bármelyik jegyzetet törölheti
- **Hiba leírása és reprodukálása:** Orvos #1 jegyzetének azonosítója pl. 5. Orvos #2-ként (`?orvos=2`) megnyitva `?torol=5` → orvos #1 jegyzete törlődik.
- **Gyökérok:** `index.php` 5. sor: a törlés csak az `id`-t nézi, a tulajdonost nem. OWASP A01:2021 Broken Access Control, CWE-639.
- **Javítás:** [20b05eb](https://github.com/cofarialex/WebLabor/commit/20b05ebaf474f3dbb448acd35c910c829f5a3045)
  - Előtte: `DELETE FROM jegyzetek WHERE id = ?`
  - Utána: `DELETE FROM jegyzetek WHERE id = ? AND orvos_id = ?` a munkamenet orvos-azonosítójával; ha `rowCount() === 0` → 404 („Nincs ilyen jegyzet (vagy nem a tiéd).”)
- **Hogyan teszteltem:** T2 – két orvos-fiókos teszt: orvos #2 orvos #1 jegyzetét próbálja törölni (GET-tel és saját tokenes POST-tal) → orvos #1 listájában megmarad.
- **Tanulság / alapelv:** Legkisebb jogosultság – a jogosultságot a szerveren, minden kérésnél ellenőrizni kell; az URL-ben lévő azonosító nem jogosultság.

## 3. hiba – hiányzó exit a header('Location') után
- **Hiba leírása és reprodukálása:** `curl -i -b sutik "http://localhost:8000/index.php?torol=5"` → a válasz `302 Found`, de a törzsében ott a **teljes oldal** (lista, jegyzetek).
- **Gyökérok:** `index.php` 6. sor: a `header()` csak fejlécet állít be, a szkript tovább fut (lekérdezés + HTML kiírás). Ha előtte jogosultság-ellenőrzés lenne, a védett tartalom is kimenne. CWE-698 (Execution After Redirect).
- **Javítás:** [05b0ee8](https://github.com/cofarialex/WebLabor/commit/05b0ee8f75d1442e459f7a090de2a47a15e93ef6)
  - Előtte: `header('Location: index.php');`
  - Utána: `header('Location: index.php', true, 303); exit;`
- **Hogyan teszteltem:** T3: a törlés átirányító (3xx) válaszának törzse üres (nincs benne `<h1`).
- **Tanulság / alapelv:** Post/Redirect/Get – átirányítás után mindig `exit`.

## 4. hiba – CSRF: törlés GET-linkkel, token nélkül
- **Hiba leírása és reprodukálása:** Egy e-mailben vagy idegen oldalon elhelyezett `<img src="http://localhost:8000/index.php?torol=5">` megnyitásakor a böngésző elküldi a kérést az orvos sütijével együtt → a jegyzet törlődik.
- **Gyökérok:** `index.php` 4. és 34. sor: állapotot módosító művelet GET-tel (`<a href="?torol=...">`), CSRF-token nélkül. OWASP A01:2021, CWE-352.
- **Javítás:** [412e7b5](https://github.com/cofarialex/WebLabor/commit/412e7b5ff73cd8853ca91c9a34fa1579f592755a)
  - Előtte: `if (isset($_GET['torol'])) { ... }` és `<a href="?torol=<?= $s['id'] ?>">Törlés</a>`
  - Utána: minden módosítás POST, `csrf_check()` (új `csrf.php`: `random_bytes` token a munkamenetben, `hash_equals` ellenőrzés → 403); a Törlés gomb egy POST-űrlap rejtett `torol` + `csrf` mezővel; az új jegyzet űrlapja is kap tokent; a munkamenet-süti `SameSite=Lax`, `HttpOnly`, `use_strict_mode`.
- **Hogyan teszteltem:** T4: orvos #1 sütijével (a) GET `?torol=ID` (mint egy img), (b) POST token nélkül, (c) POST hamis tokennel → a jegyzet megmarad. F2: a rendes, tokenes törlés továbbra is működik.
- **Tanulság / alapelv:** GET soha ne módosítson adatot; módosításhoz POST + CSRF-token (a SameSite csak plusz védelmi réteg).

## 5. hiba – SQL injection a keresésben
- **Hiba leírása és reprodukálása:** Orvos #2-ként a keresőbe: `' OR 1=1 -- ` → megjelennek **más orvosok** jegyzetei is.
- **Gyökérok:** `index.php` 17–18. sor: `"... AND beteg LIKE '%" . $q . "%' ..."` – a `'` lezárja a szöveget, az `OR 1=1` kikapcsolja az orvos-szűrést, a `--` kikommenteli a maradékot. OWASP A03:2021, CWE-89.
- **Javítás:** [d456088](https://github.com/cofarialex/WebLabor/commit/d4560889ccfb419cd1ee52b6cf82f39b8133257d)
  - Előtte: `$pdo->query("SELECT * ... WHERE orvos_id = " . $_SESSION['orvos_id'] . " AND beteg LIKE '%" . $q . "%' ...")`
  - Utána: `prepare('SELECT * FROM jegyzetek WHERE orvos_id = ? AND beteg LIKE ? ORDER BY id DESC')` + `execute([$orvos, '%' . $q . '%'])`; tömb típusú `q[]` esetén üres keresés.
- **Hogyan teszteltem:** T5: ugyanaz a payload orvos #2-ként → 200-as válasz, orvos #1 jegyzete nem látszik. F3: a normál keresés működik.
- **Tanulság / alapelv:** Soha ne bízz a bemenetben – a `%` jeleket a PHP-ban fűzd hozzá, ne az SQL-ben.

## 6. hiba – tükrözött (reflected) XSS a keresőmezőben
- **Hiba leírása és reprodukálása:** `index.php?q=%22%3E%3Cscript%3Ealert(1)%3C/script%3E` (`?q="><script>alert(1)</script>`) → felugró ablak; egy ilyen linket bárki elküldhet az orvosnak.
- **Gyökérok:** `index.php` 28. sor: `value="<?= $q ?>"` – a `"` lezárja az attribútumot, a `<script>` lefut. OWASP A03:2021, CWE-79.
- **Javítás:** [f5fb64e](https://github.com/cofarialex/WebLabor/commit/f5fb64e4693faecd53ce4c8ee9ceade52967f9e1)
  - Előtte: `value="<?= $q ?>"`
  - Utána: `value="<?= e($q) ?>"`, ahol `e()` = `htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` (db.php)
- **Hogyan teszteltem:** T6: ugyanaz a payload → a válaszban `&quot;&gt;&lt;script&gt;` szerepel, nyers `<script>` nincs.
- **Tanulság / alapelv:** Escape-elj a kimeneten – attribútumban is (ENT_QUOTES).

## 7. hiba – tárolt (stored) XSS a jegyzet szövegében
- **Hiba leírása és reprodukálása:** Új jegyzet szövege: `<img src=x onerror=alert(1)>` → a lista minden megnyitásakor lefut a szkript (szétesik az oldal / sütilopás lehetséges).
- **Gyökérok:** `index.php` 33. sor: `<?= $s['szoveg'] ?>` escape nélkül, miközben a beteg neve `htmlspecialchars`-szal megy ki – következetlen kimeneti kezelés. OWASP A03:2021, CWE-79.
- **Javítás:** [6bb5937](https://github.com/cofarialex/WebLabor/commit/6bb59370d1c2b772b11ec5c4a9218865a4c13909)
  - Előtte: `<b><?= htmlspecialchars($s['beteg']) ?></b>: <?= $s['szoveg'] ?>`
  - Utána: `<b><?= e($s['beteg']) ?></b>: <?= nl2br(e($s['szoveg'])) ?>` – minden kimenet a közös `e()`-n megy át (a CSRF-token is), előbb `e()`, utána `nl2br()`.
- **Hogyan teszteltem:** T7: a jegyzet `&lt;img src=x onerror=alert(1)&gt;` formában, szövegként jelenik meg.
- **Tanulság / alapelv:** Escape-elj a kimeneten, mindenhol ugyanazzal a függvénnyel.

## Tesztmátrix (`teszt.sh` minden commit után)

| Commit | F1–F3 | T1 | T2 | T3 | T4 | T5 | T6 | T7 |
|---|---|---|---|---|---|---|---|---|
| 08a9866 eredeti + tesztek | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| 7b8d85d SQLi törlés | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| 20b05eb IDOR | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| 05b0ee8 exit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| 412e7b5 CSRF | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| d456088 SQLi keresés | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| f5fb64e tükrözött XSS | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| 6bb5937 tárolt XSS | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

## Összegzés
- **Mit tanultam:** Az SQL-be és a HTML-be kerülő adatot mindig a célnak megfelelően kell kezelni (SQL → prepared statement, HTML → `e()`); adatot módosító kérés csak POST + CSRF-token lehet; a jogosultságot minden kérésnél a szerveren kell ellenőrizni; `header('Location')` után `exit`.
- **Melyik alapelv sérült a leggyakrabban:** „Soha ne bízz a bemenetben” (2× SQLi, 2× XSS), utána a „Legkisebb jogosultság” (IDOR).
- **Ismert korlát (nem a 7 hiba része):** a demó-belépés (`?orvos=2`) bárkit bármelyik orvossá tesz – éles rendszerben valódi bejelentkezés kell (`password_hash`/`password_verify`, `session_regenerate_id`). A `schema.sql` legkisebb jogosultságú adatbázis-felhasználót hoz létre (csak SELECT, INSERT, DELETE).
