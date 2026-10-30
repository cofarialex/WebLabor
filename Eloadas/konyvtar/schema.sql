-- schema.sql – a kölcsönzés-nyilvántartó adatbázisa (SQLite)
--
-- Négy fő tábla:  konyv 1 ── N peldany 1 ── N kolcsonzes N ── 1 olvaso
-- A könyv és a példány külön tábla: egy könyvnek (pl. Netter-atlasz) több
-- példánya lehet, mindegyik saját leltári számmal – kölcsönözni példányt lehet.
--
-- Az idegen kulcsokat az SQLite csak akkor ellenőrzi, ha a kapcsolat elején
-- lefut a PRAGMA foreign_keys = ON (ezt a src/Db.php megteszi).

CREATE TABLE IF NOT EXISTS konyv (
  id      INTEGER PRIMARY KEY,
  cim     TEXT NOT NULL CHECK (length(cim) > 0),
  szerzo  TEXT NOT NULL,
  isbn    TEXT UNIQUE
);

CREATE TABLE IF NOT EXISTS peldany (
  id            INTEGER PRIMARY KEY,
  konyv_id      INTEGER NOT NULL REFERENCES konyv(id),
  leltari_szam  TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS olvaso (
  id          INTEGER PRIMARY KEY,
  nev         TEXT NOT NULL,
  olvasojegy  TEXT NOT NULL UNIQUE,
  email       TEXT
);

-- A dátumok ISO formátumú szövegek (ÉÉÉÉ-HH-NN), így szövegként is
-- helyesen hasonlíthatók össze és rendezhetők.
CREATE TABLE IF NOT EXISTS kolcsonzes (
  id           INTEGER PRIMARY KEY,
  peldany_id   INTEGER NOT NULL REFERENCES peldany(id),
  olvaso_id    INTEGER NOT NULL REFERENCES olvaso(id),
  kiadva       TEXT NOT NULL,
  hatarido     TEXT NOT NULL,
  visszahozva  TEXT,            -- NULL = még nincs visszahozva (nyitott kölcsönzés)
  CHECK (hatarido >= kiadva),
  CHECK (visszahozva IS NULL OR visszahozva >= kiadva)
);

-- Üzleti szabály az adatbázis szintjén: egy példánynak egyszerre legfeljebb
-- EGY nyitott kölcsönzése lehet. Ez egy részleges egyedi index: csak azokra a
-- sorokra vonatkozik, ahol visszahozva IS NULL. Ha két kérés egyszerre próbálná
-- kiadni ugyanazt a példányt, a második INSERT itt hibára fut.
CREATE UNIQUE INDEX IF NOT EXISTS egy_nyitott_kolcsonzes
  ON kolcsonzes (peldany_id) WHERE visszahozva IS NULL;

CREATE INDEX IF NOT EXISTS idx_peldany_konyv      ON peldany (konyv_id);
CREATE INDEX IF NOT EXISTS idx_kolcsonzes_olvaso  ON kolcsonzes (olvaso_id);
CREATE INDEX IF NOT EXISTS idx_kolcsonzes_hatarido ON kolcsonzes (hatarido);

-- A könyvtárosok belépéséhez. A jelszó helyett csak a hash-e kerül ide
-- (password_hash), így egy adatbázis-szivárgásból sem derül ki a jelszó.
CREATE TABLE IF NOT EXISTS konyvtaros (
  id              INTEGER PRIMARY KEY,
  felhasznalonev  TEXT NOT NULL UNIQUE,
  jelszo_hash     TEXT NOT NULL,
  nev             TEXT NOT NULL
);
