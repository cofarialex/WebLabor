# A türelmetlen gyógyszerkereső – hibariport (BH08)

Repó: https://github.com/cofarialex/WebLabor/tree/main/Eloadas/bh08-gyogyszerkereso
Készítette: cofarialex, <csoport>
Kiinduló (hibás) kód: [4e7c272](https://github.com/cofarialex/WebLabor/commit/4e7c27260c8a8bfa25850aef133dd277f9a86b19)

**Tesztelés röviden:** kérésnapló a DevTools konzolban – az oldal betöltése után ezt beillesztve minden „szerverkérés” kiíródik:
`const eredeti = fakeFetch; fakeFetch = q => { console.log('[szerver] kérés:', q); return eredeti(q); };`
Ezen felül automatikus böngészős ellenőrzés (Playwright, Chromium) 7 forgatókönyvvel: az eredeti kódon 0/7, a végén 7/7 sikeres.

## 1. hiba – versenyhelyzet: a régi válasz felülírja az újat
- **Hiba leírása és reprodukálása:** Gyorsan beírni: „A”, majd ~0,3 s múlva „Algo” → a végén az „A” 5 találata látszik (Aspirin, Amoxicillin…) a helyes 2 (Algopyrin, Algoflex) helyett.
- **Gyökérok:** 38–46. sor. A rövidebb keresés lassabb (`1200 - q.length*250` ms): „A” ≈ 1000 ms, „Algo” ≈ 250 ms, így az „A” válasza érkezik **utoljára**, és a `.then(megjelenit)` vakon kirajzolja (out-of-order response).
- **Javítás:** [c3baee6](https://github.com/cofarialex/WebLabor/commit/c3baee67a26e175a32493bdbbfa7eaa39cea2d8f) – kérés-sorszám: `const id = ++utolsoKeres;` … `if (id !== utolsoKeres) return;` – az elavult választ eldobjuk.
- **Hogyan teszteltem:** „A” → 350 ms → „Algo” → 2 s várakozás: a lista `["Algopyrin","Algoflex"]`.
- **Tanulság / alapelv:** A legutolsó kérés nyer.

## 2. hiba – nincs debounce: minden leütés külön kérés
- **Hiba leírása és reprodukálása:** „Algo” begépelése a konzolnaplóban 4 kérést mutat: A, Al, Alg, Algo. Üres mezőnél is kérés indul (és mind a 9 gyógyszer megjelenik).
- **Gyökérok:** 38. sor: az `input` eseménykezelő minden leütésre azonnal `fakeFetch`-et hív – felesleges szerver- és kliensterhelés.
- **Javítás:** [7762272](https://github.com/cofarialex/WebLabor/commit/7762272965ec5c3554f79a6594b9de01c0d2c1af) – `debounce(fn, 300)` (`clearTimeout` + `setTimeout`); üres mezőre nincs kérés; a logika külön `keres(q)` függvényben.
- **Hogyan teszteltem:** 4 leütés 60 ms-onként → pontosan 1 kérés („Algo”); a mező kiürítése → 0 kérés, üres lista.
- **Tanulság / alapelv:** Debounce.

## 3. hiba – a szerverhiba nincs kezelve
- **Hiba leírása és reprodukálása:** „x” beírására a felület örökre „Keresés…”, a konzolban `Uncaught (in promise) Error: Szerverhiba (503)`.
- **Gyökérok:** 41–45. sor: a `try/catch` csak a szinkron hibát fogja meg; a Promise később utasul el, a `.then`-láncban, amihez nincs `.catch` és nincs `await` (eseményhurok: mire a hiba jön, a try blokk rég véget ért).
- **Javítás:** [c20f442](https://github.com/cofarialex/WebLabor/commit/c20f44265d5e9ab89dd987961f76544df1aedacc) – `async function keres` + `const nevek = await fakeFetch(q)` a `try`-ban → a `catch` kiírja: „Hiba: Szerverhiba (503)”.
- **Hogyan teszteltem:** „x” → 2 s: az állapot „Hiba: Szerverhiba (503)”, 0 kezeletlen hiba (pageerror).
- **Tanulság / alapelv:** Mindig kezeld a Promise elutasítását.

## 4. hiba – az összeg mindig 0 lej
- **Hiba leírása és reprodukálása:** „Algo” keresés → 2 találat, de „összesen: 0 lej” (helyesen 19 + 18 = 37).
- **Gyökérok:** 62–63. sor: `nevek.forEach(async n => { osszeg += await fakePrice(n); })` – a forEach nem várja meg az async visszahívást, a `textContent = osszeg` azonnal, még 0-val fut le.
- **Javítás:** [0f9222a](https://github.com/cofarialex/WebLabor/commit/0f9222ad617d0c7424523530f6045c581ed4ee06) – `const arak = await Promise.all(nevek.map(fakePrice)); const osszeg = arak.reduce((a, b) => a + b, 0);` + elavult keresés összegét nem írjuk ki.
- **Hogyan teszteltem:** „Algo” → az összeg 37.
- **Tanulság / alapelv:** async + forEach nem működik együtt → Promise.all.

## 5. hiba – halmozódó setInterval, nem kapcsolható ki
- **Hiba leírása és reprodukálása:** A gombot 3× megnyomva 4 s alatt 6 kérés megy (3 időzítő), a lista egyre sűrűbben villog, kikapcsolni nem lehet; „x”-nél a poll kezeletlen hibákat dob.
- **Gyökérok:** 66–70. sor: minden kattintás új `setInterval`-t indít, az azonosítóját senki nem tárolja (nincs `clearInterval`), és a pollban nincs `.catch`.
- **Javítás:** [b4ad14a](https://github.com/cofarialex/WebLabor/commit/b4ad14a132a4219a5adb45211a3934b43219ff2e) – a gomb kapcsoló (`aria-pressed`), egyetlen tárolt időzítő, kikapcsoláskor `clearInterval`; a poll a `keres()`-t hívja (hibakezelés + „legutolsó nyer”), rejtett lapon (`document.hidden`) kihagy.
- **Hogyan teszteltem:** 3 kattintás (be-ki-be) → 4,3 s alatt legfeljebb 2 kérés; újabb kattintás (ki) → 2,5 s alatt 0 kérés; „x” + frissítés → 0 kezeletlen hiba.
- **Tanulság / alapelv:** Minden elindított időzítőt le is kell tudni állítani.

## Összegzés
- **Mit tanultam:** Az aszinkron válaszok sorrendje nem garantált; a `try/catch` csak `await`-tel fogja meg a Promise hibáját; a `forEach` nem vár; a `setInterval` azonosítóját meg kell őrizni.
- **Melyik alapelv sérült a leggyakrabban:** a Promise-ok időbeli sorrendjének és elutasításának kezelése (1., 3., 4., 5. hiba).
