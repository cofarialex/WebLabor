# Tesztesetek – Vendégkönyv

## Kézi tesztek (böngészőben)

Indítsd el a szervert (`php -S localhost:8000`), és nyisd meg a <http://localhost:8000> címet. A DevTools a Chrome-ban/Safariban: jobb klikk → *Vizsgálat* (Inspect).

| # | Lépések | Elvárt eredmény |
|---|---|---|
| 1 | Töltsd ki a nevet és az üzenetet, küldd be. | A bejegyzés a lista tetején jelenik meg, felül zöld „Köszönjük a bejegyzést!” üzenettel. A címsorban `/index.php` áll. |
| 2 | Az 1. után nyomj F5-öt. | Nem jelenik meg dupla bejegyzés, és a böngésző nem kérdezi, hogy újraküldje-e az űrlapot. A „Köszönjük” eltűnik. |
| 3 | DevTools → *Network* fül, majd küldj be egy bejegyzést. | `POST /index.php` → **303**, utána automatikusan `GET /index.php` → **200**. |
| 4 | DevToolsban töröld az üzenet mező `required` attribútumát, és küldd be üresen. | Piros doboz: „Az üzenet 1–500 karakter legyen.” A név mező megtartja a beírt értéket. |
| 5 | DevToolsban töröld a név mező `maxlength` attribútumát, írj be 51 karaktert, küldd be. | Szerveroldali hiba: „A név 1–50 karakter legyen.” |
| 6 | Név: `Ödön Őrsi`, üzenet: tele ékezettel. | Elfogadja (az `mb_strlen` karaktert számol, nem bájtot). |
| 7 | Üzenetnek: `<script>alert(1)</script>` | Nem ugrik fel ablak; a szöveg betű szerint látszik. Az oldal forrásában `&lt;script&gt;` áll. |
| 8 | Névnek: `' OR 1=1 --` | Sima szövegként mentődik, nincs SQL-hiba. |
| 9 | DevToolsban írd át a rejtett `csrf` mező értékét, és küldd be. | **403** – „Érvénytelen kérés (CSRF).” Nem jön létre bejegyzés. |
| 10 | Csak billentyűzettel (Tab, Shift+Tab, Enter) töltsd ki és küldd be az űrlapot. | Minden mező elérhető, a fókusz jól látható keretet kap. |
| 11 | Kapcsold a gépet sötét módra. | Az oldal sötét színekre vált, minden olvasható marad. |

## Automatikus tesztek (curl)

```bash
bash teszt.sh
```

A `teszt.sh` 25 ellenőrzést futtat:

- **GET:** 200 OK, `PHPSESSID` süti `HttpOnly` és `SameSite=Lax` beállítással, 64 karakteres CSRF token
- **PRG:** helyes POST → 303 + `Location: /index.php`; a bejegyzés megjelenik; a flash üzenet csak egyszer látszik
- **Validálás:** üres üzenet, 51 karakteres név, csak szóköz → hiba; 50 ékezetes betű → elfogadva; tömbként küldött mező → nem okoz szerverhibát
- **XSS:** a `<script>` escape-elve jelenik meg
- **SQL injection:** `' OR 1=1 --` sima szövegként mentődik
- **CSRF:** token nélkül, rossz tokennel, és üres munkamenettel is 403
- **Metódus:** PUT → 405

Néhány curl parancs kézzel is (a tokent az oldal forrásából kell kimásolni, a `name="csrf"` mező `value` értéke):

```bash
# GET: 200 és munkamenet-süti (a sütit a j.txt-be menti)
curl -i -c j.txt http://localhost:8000/index.php

# Helyes POST → 303 See Other, Location: /index.php
curl -i -b j.txt -X POST -d "csrf=TOKEN&nev=Anna&uzenet=Szia" http://localhost:8000/index.php

# Token nélkül → 403
curl -i -b j.txt -X POST -d "nev=Bot&uzenet=Spam" http://localhost:8000/index.php
```
