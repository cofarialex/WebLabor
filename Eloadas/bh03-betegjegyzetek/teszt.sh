#!/usr/bin/env bash
# teszt.sh – a BH03 (Betegjegyzetek) biztonsági és működési tesztjei, curl-lel.
#
# Minden támadást két orvos nevében, két külön munkamenettel (sütitárolóval)
# próbálunk ki: orvos #1 a „károsult”, orvos #2 a „kíváncsi kolléga”.
#
# Használat:
#   bash teszt.sh                         → saját PHP szervert indít egy ideiglenes
#                                           SQLite adatbázissal (nem kell hozzá MySQL)
#   bash teszt.sh http://localhost:8000   → egy már futó példány ellen fut (pl. MySQL-lel)
#
# Kilépési kód: a bukott tesztek száma (0 = minden rendben).
# Etika: csak a saját, helyben futó példányodon futtasd!

set -u
cd "$(dirname "$0")"

TMP=$(mktemp -d "${TMPDIR:-/tmp}/bh03-teszt.XXXXXX")
SZERVER_PID=""
takaritas() {
  if [ -n "$SZERVER_PID" ]; then kill "$SZERVER_PID" 2>/dev/null; fi
  rm -rf "$TMP"
}
trap takaritas EXIT

if [ $# -ge 1 ]; then
  BASE="${1%/}"
else
  PORT="${PORT:-8099}"
  BASE="http://127.0.0.1:$PORT"
  DB="$TMP/teszt.sqlite"
  # A schema.sql SQLite-os megfelelője (ott AUTO_INCREMENT, itt AUTOINCREMENT).
  php -r '$p = new PDO("sqlite:" . $argv[1]);
          $p->exec("CREATE TABLE jegyzetek (id INTEGER PRIMARY KEY AUTOINCREMENT,
                    orvos_id INTEGER NOT NULL, beteg VARCHAR(100) NOT NULL,
                    szoveg TEXT NOT NULL, letrehozva TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");' "$DB"
  DB_DSN="sqlite:$DB" php -d session.save_path="$TMP" -d display_errors=stderr \
    -S "127.0.0.1:$PORT" -t . >"$TMP/szerver.log" 2>&1 &
  SZERVER_PID=$!
  for _ in $(seq 1 50); do
    curl -s -o /dev/null "$BASE/index.php" && break
    sleep 0.1
  done
fi
echo "Cél: $BASE"

# ---------- segédfüggvények ----------
O1="$TMP/orvos1.sutik"   # orvos #1 munkamenete (sütitároló)
O2="$TMP/orvos2.sutik"   # orvos #2 munkamenete
R="$$$RANDOM"            # egyedi jel, hogy a saját tesztjegyzeteinket felismerjük

# oldal SÜTIK [--data-urlencode "q=..."] → az oldal HTML-je (GET)
oldal() { local s=$1; shift; curl -s -b "$s" -c "$s" -G "$@" "$BASE/index.php"; }

# kerj SÜTIK [curl opciók] → kiírja a HTTP státuszkódot, a válasz törzse a $TMP/valasz fájlba kerül
kerj() { local s=$1; shift; curl -s -o "$TMP/valasz" -w '%{http_code}' -b "$s" -c "$s" "$@" "$BASE/index.php"; }

# token SÜTIK → az oldalba ágyazott CSRF-token (üres, ha az oldalnak még nincs ilyen)
token() { oldal "$1" | grep -o 'name="csrf" value="[^"]*"' | head -n 1 | sed 's/.*value="//; s/"$//'; }

# uj_jegyzet SÜTIK BETEG SZÖVEG → új jegyzet mentése az űrlappal (POST)
uj_jegyzet() {
  local t; t=$(token "$1")
  curl -s -o /dev/null -b "$1" -c "$1" --data-urlencode "beteg=$2" \
    --data-urlencode "szoveg=$3" --data-urlencode "csrf=$t" "$BASE/index.php"
}

# van_jegyzet SÜTIK JEL → igaz, ha az orvos listájában szerepel a JEL
van_jegyzet() { oldal "$1" | grep -qF -- "$2"; }

# jegyzet_id SÜTIK JEL → a JEL-et tartalmazó jegyzet azonosítója (a törlés linkjéből vagy űrlapjából)
jegyzet_id() {
  oldal "$1" | php -r '
    foreach (preg_split("/<li/", stream_get_contents(STDIN)) as $darab) {
      if (str_contains($darab, $argv[1]) &&
          preg_match("/torol=(\d+)|name=\"torol\" value=\"(\d+)\"/", $darab, $m)) {
        echo $m[1] !== "" ? $m[1] : $m[2];
        break;
      }
    }' "$2"
}

# torles_kiserlet SÜTIK ID → megpróbálja törölni a jegyzetet MINDKÉT módon,
# ahogy az alkalmazás valaha támogatta: GET ?torol=ID és POST torol=ID (+ saját CSRF-token)
torles_kiserlet() {
  local t; t=$(token "$1")
  curl -s -o /dev/null -b "$1" -c "$1" -G --data-urlencode "torol=$2" "$BASE/index.php"
  curl -s -o /dev/null -b "$1" -c "$1" --data-urlencode "torol=$2" --data-urlencode "csrf=$t" "$BASE/index.php"
}

# ---------- a tesztek ----------
# Mindegyik 0-val tér vissza, ha az alkalmazás jól viselkedik (PASS), különben 1-gyel.

f1() { # Működés: új jegyzet mentése – orvos #1 látja, orvos #2 nem
  uj_jegyzet "$O1" "Teszt Elek $R-f1" "Kontroll két hét múlva."
  van_jegyzet "$O1" "$R-f1" && ! van_jegyzet "$O2" "$R-f1"
}

f2() { # Működés: a saját jegyzet törlése továbbra is működik
  uj_jegyzet "$O1" "Törlendő $R-f2" "x"
  local id; id=$(jegyzet_id "$O1" "$R-f2"); [ -n "$id" ] || return 1
  torles_kiserlet "$O1" "$id"
  ! van_jegyzet "$O1" "$R-f2"
}

f3() { # Működés: a keresés megtalálja a saját jegyzetet
  uj_jegyzet "$O1" "Kovács Anna $R-f3" "x"
  oldal "$O1" --data-urlencode "q=Anna $R-f3" | grep -qF -- "$R-f3"
}

t1() { # 1. hiba – SQL injection a törlésben: ?torol=0 OR 1=1
  uj_jegyzet "$O1" "Bent kell maradnia $R-t1" "x"
  torles_kiserlet "$O1" "0 OR 1=1"
  van_jegyzet "$O1" "$R-t1"
}

t2() { # 2. hiba – IDOR: orvos #2 nem törölheti orvos #1 jegyzetét az azonosító alapján
  uj_jegyzet "$O1" "Orvos1 betege $R-t2" "x"
  local id; id=$(jegyzet_id "$O1" "$R-t2"); [ -n "$id" ] || return 1
  torles_kiserlet "$O2" "$id"
  van_jegyzet "$O1" "$R-t2"
}

t3() { # 3. hiba – átirányítás után a szkript nem fut tovább (exit; a válasz törzse üres)
  uj_jegyzet "$O1" "Átirányítás $R-t3" "x"
  local id; id=$(jegyzet_id "$O1" "$R-t3"); [ -n "$id" ] || return 1
  local t kod lattunk=0 szivargott=0
  t=$(token "$O1")
  kod=$(kerj "$O1" -G --data-urlencode "torol=$id")
  case $kod in 3*) lattunk=1; grep -q '<h1' "$TMP/valasz" && szivargott=1 ;; esac
  kod=$(kerj "$O1" --data-urlencode "torol=$id" --data-urlencode "csrf=$t")
  case $kod in 3*) lattunk=1; grep -q '<h1' "$TMP/valasz" && szivargott=1 ;; esac
  [ "$lattunk" -eq 1 ] && [ "$szivargott" -eq 0 ]
}

t4() { # 4. hiba – CSRF: idegen oldalról indított kérés nem törölhet
  uj_jegyzet "$O1" "CSRF célpont $R-t4" "x"
  local id; id=$(jegyzet_id "$O1" "$R-t4"); [ -n "$id" ] || return 1
  # a) e-mailbe rejtett <img src=".../index.php?torol=ID"> → a böngésző GET-et küld, a sütivel együtt
  curl -s -o /dev/null -b "$O1" -G --data-urlencode "torol=$id" "$BASE/index.php"
  # b) idegen oldal automatikusan beküldött űrlapja → POST, de CSRF-token nélkül
  curl -s -o /dev/null -b "$O1" --data-urlencode "torol=$id" "$BASE/index.php"
  # c) ugyanez kitalált tokennel
  curl -s -o /dev/null -b "$O1" --data-urlencode "torol=$id" --data-urlencode "csrf=hamis" "$BASE/index.php"
  van_jegyzet "$O1" "$R-t4"
}

t5() { # 5. hiba – SQL injection a keresésben: ' OR 1=1 --
  uj_jegyzet "$O1" "Titkos Beteg $R-t5" "x"
  local kod; kod=$(kerj "$O2" -G --data-urlencode "q=' OR 1=1 -- ")
  [ "$kod" = "200" ] && ! grep -qF -- "$R-t5" "$TMP/valasz"
}

t6() { # 6. hiba – tükrözött XSS a keresőmezőben: ?q="><script>alert(1)</script>
  kerj "$O1" -G --data-urlencode 'q="><script>alert(1)</script>' >/dev/null
  ! grep -qF '<script>alert(1)</script>' "$TMP/valasz" &&
    grep -qF '&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;' "$TMP/valasz"
}

t7() { # 7. hiba – tárolt XSS: HTML a jegyzet szövegében
  uj_jegyzet "$O1" "XSS teszt $R-t7" '<img src=x onerror=alert(1)>'
  oldal "$O1" >"$TMP/valasz"
  ! grep -qF '<img src=x onerror=alert(1)>' "$TMP/valasz" &&
    grep -qF '&lt;img src=x onerror=alert(1)&gt;' "$TMP/valasz"
}

# ---------- futtatás ----------
curl -s -o /dev/null -c "$O1" "$BASE/index.php?orvos=1"   # „belépés” orvos #1-ként
curl -s -o /dev/null -c "$O2" "$BASE/index.php?orvos=2"   # „belépés” orvos #2-ként

SIKER=0; BUKAS=0
futtat() {
  if "$1"; then echo "  PASS  $2"; SIKER=$((SIKER + 1))
  else          echo "  FAIL  $2"; BUKAS=$((BUKAS + 1)); fi
}
futtat f1 "F1  Új jegyzet: a szerzője látja, a másik orvos nem"
futtat f2 "F2  A saját jegyzet törlése működik"
futtat f3 "F3  A keresés megtalálja a saját jegyzetet"
futtat t1 "T1  SQL injection a törlésben (?torol=0 OR 1=1)"
futtat t2 "T2  IDOR: más orvos jegyzete nem törölhető"
futtat t3 "T3  Átirányítás után nincs kimenet (exit)"
futtat t4 "T4  CSRF: idegen oldalról indított törlés hatástalan"
futtat t5 "T5  SQL injection a keresésben (' OR 1=1 --)"
futtat t6 "T6  Tükrözött XSS a keresőmezőben"
futtat t7 "T7  Tárolt XSS a jegyzet szövegében"
echo "Eredmény: $SIKER sikeres, $BUKAS bukott"
exit "$BUKAS"
