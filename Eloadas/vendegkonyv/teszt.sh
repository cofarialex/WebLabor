#!/usr/bin/env bash
# teszt.sh – automatikus tesztek curl-lel a Vendégkönyvhöz.
#
# Használat:
#   1. Az egyik terminálfülön fusson a szerver:   php -S localhost:8000
#   2. Egy MÁSIK fülön, ugyanebben a mappában:     bash teszt.sh
#
# Figyelem: a tesztek valódi bejegyzéseket írnak az adatbázisba.
# Ha utána tiszta lapot szeretnél:  rm vendegkonyv.sqlite  (a db.php újra létrehozza)

URL="${1:-http://localhost:8000}"
SUTI="$(mktemp)"            # ide menti a curl a munkamenet-sütit (PHPSESSID)
JEL="$$"                    # egyedi szám, hogy a mostani teszt-bejegyzéseket felismerjük
SIKER=0
HIBA=0

# --- kis segédfüggvények ---
ok()  { echo "✅ $1"; SIKER=$((SIKER + 1)); }
nem() { echo "❌ $1"; HIBA=$((HIBA + 1)); }

egyenlo() {        # egyenlo "leírás" "elvárt" "kapott"
  if [ "$2" = "$3" ]; then ok "$1"; else nem "$1 (elvárt: $2, kapott: $3)"; fi
}
benne_van() {      # benne_van "leírás" "keresett szöveg" "ahol keressük"
  if printf '%s' "$3" | grep -qF -- "$2"; then ok "$1"; else nem "$1 (nem található: $2)"; fi
}
nincs_benne() {    # nincs_benne "leírás" "keresett szöveg" "ahol keressük"
  if printf '%s' "$3" | grep -qF -- "$2"; then nem "$1 (mégis megtalálható: $2)"; else ok "$1"; fi
}
statusz() {        # csak a HTTP státuszkódot adja vissza
  curl -s -o /dev/null -w '%{http_code}' "$@"
}
bekuld() {         # bekuld <nev> <uzenet> → POST a sütivel és a tokennel, státuszkódot ad
  statusz -b "$SUTI" -c "$SUTI" \
    --data-urlencode "csrf=$TOKEN" --data-urlencode "nev=$1" --data-urlencode "uzenet=$2" \
    "$URL/index.php"
}
oldal() {          # az oldal HTML-je a sütivel (GET)
  curl -s -b "$SUTI" -c "$SUTI" "$URL/index.php"
}

echo "Tesztelés: $URL"
echo

# ---------------------------------------------------------------- 1. GET
VALASZ=$(curl -s -i -c "$SUTI" "$URL/index.php")
egyenlo     "GET /index.php → 200 OK" "200" "$(printf '%s' "$VALASZ" | head -1 | awk '{print $2}')"
benne_van   "Kapunk munkamenet-sütit (PHPSESSID)" "PHPSESSID=" "$VALASZ"
benne_van   "A süti HttpOnly (JavaScript nem olvashatja)" "HttpOnly" "$VALASZ"
benne_van   "A süti SameSite=Lax" "SameSite=Lax" "$VALASZ"
TOKEN=$(printf '%s' "$VALASZ" | grep -o 'name="csrf" value="[^"]*"' | sed 's/.*value="//; s/"$//')
egyenlo     "Az űrlapban van 64 karakteres CSRF token" "64" "${#TOKEN}"

# ---------------------------------------------------------------- 2. Sikeres beküldés + PRG
FEJLEC=$(curl -s -i -o /dev/null -D - -b "$SUTI" -c "$SUTI" \
  --data-urlencode "csrf=$TOKEN" --data-urlencode "nev=Teszt Anna $JEL" --data-urlencode "uzenet=Szia!" \
  "$URL/index.php" | tr -d '\r')
egyenlo     "Helyes POST → 303 See Other" "303" "$(printf '%s' "$FEJLEC" | head -1 | awk '{print $2}')"
benne_van   "Átirányítás ide: Location: /index.php" "Location: /index.php" "$FEJLEC"
HTML=$(oldal)
benne_van   "Utána a bejegyzés megjelenik a listában" "Teszt Anna $JEL" "$HTML"
benne_van   "A „Köszönjük” üzenet megjelenik" "Köszönjük a bejegyzést!" "$HTML"
HTML=$(oldal)
nincs_benne "Újratöltéskor a „Köszönjük” már nem jelenik meg (flash)" "Köszönjük a bejegyzést!" "$HTML"

# ---------------------------------------------------------------- 3. Validálás
egyenlo     "Üres üzenet → 303 (vissza az űrlapra)" "303" "$(bekuld "Teszt Béla $JEL" "")"
HTML=$(oldal)
benne_van   "Üres üzenetnél hibaüzenet" "Az üzenet 1–500 karakter legyen." "$HTML"
benne_van   "A beírt név megmarad a mezőben" "value=\"Teszt Béla $JEL\"" "$HTML"

NEV51=$(printf 'a%.0s' $(seq 1 51))
bekuld "$NEV51" "hosszú név" > /dev/null
benne_van   "51 karakteres név → szerveroldali hiba" "A név 1–50 karakter legyen." "$(oldal)"

NEV50=$(printf 'ő%.0s' $(seq 1 50))
bekuld "$NEV50" "50 darab ő betű $JEL" > /dev/null
HTML=$(oldal)
benne_van   "50 ékezetes betű elfogadva (mb_strlen karaktert számol)" "50 darab ő betű $JEL" "$HTML"

egyenlo     "Csak szóközből álló név → hiba, nem mentjük" "303" "$(bekuld "    " "szóköz-teszt $JEL")"
# (Az üzenet szövege a hiba után az űrlapban visszatöltve látszik – ez szándékos –,
#  de bejegyzésként, <p>...</p>-ben nem szabad megjelennie.)
nincs_benne "A szóközös bejegyzés nem került a listába" "<p>szóköz-teszt $JEL</p>" "$(oldal)"

KOD=$(statusz -b "$SUTI" -c "$SUTI" --data-urlencode "csrf=$TOKEN" -d "nev[]=x" -d "uzenet=tomb" "$URL/index.php")
egyenlo     "Tömbként küldött név nem okoz szerverhibát (nem 500)" "303" "$KOD"
oldal > /dev/null

# ---------------------------------------------------------------- 4. Biztonság
bekuld "Hekker $JEL" "<script>alert(1)</script>" > /dev/null
HTML=$(oldal)
benne_van   "XSS: a <script> szövegként (escape-elve) jelenik meg" "&lt;script&gt;alert(1)&lt;/script&gt;" "$HTML"
nincs_benne "XSS: nincs futtatható <script>alert(1)" "<script>alert(1)" "$HTML"

bekuld "' OR 1=1 -- $JEL" "SQL injection próba" > /dev/null
benne_van   "SQL injection: a bemenet sima szövegként mentődik" "&#039; OR 1=1 -- $JEL" "$(oldal)"

egyenlo     "CSRF: token nélküli POST → 403" "403" \
  "$(statusz -b "$SUTI" -d "nev=Bot&uzenet=Spam" "$URL/index.php")"
egyenlo     "CSRF: rossz tokennel → 403" "403" \
  "$(statusz -b "$SUTI" -d "csrf=rossz&nev=Bot&uzenet=Spam" "$URL/index.php")"
egyenlo     "CSRF: süti és token nélkül (üres munkamenet) → 403" "403" \
  "$(statusz -d "csrf=&nev=Bot&uzenet=Spam" "$URL/index.php")"
egyenlo     "Nem támogatott metódus (PUT) → 405" "405" \
  "$(statusz -X PUT "$URL/index.php")"

# ---------------------------------------------------------------- Összesítés
rm -f "$SUTI"
echo
echo "Eredmény: $SIKER sikeres, $HIBA sikertelen"
[ "$HIBA" -eq 0 ]
