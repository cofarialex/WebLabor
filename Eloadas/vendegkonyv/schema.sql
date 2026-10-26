-- schema.sql – a Vendégkönyv adatbázisának szerkezete (SQLite)
--
-- Nem kell kézzel futtatnod: a db.php minden kapcsolódáskor lefuttatja.
-- Az "IF NOT EXISTS" miatt ez biztonságos: ha a tábla már létezik, nem történik semmi.
-- Ha mégis kézzel szeretnéd:  sqlite3 vendegkonyv.sqlite < schema.sql

CREATE TABLE IF NOT EXISTS bejegyzes (
  -- sorszám: az adatbázis adja (1, 2, 3, ...), nekünk nem kell
  id         INTEGER PRIMARY KEY AUTOINCREMENT,

  -- A VARCHAR(50) hosszkorlátot az SQLite NEM tartatja be (a MySQL igen).
  -- Ezért tettünk mellé CHECK feltételt: így az adatbázis is őrködik,
  -- nem csak a PHP. Ez a "rétegelt védelem" (defense in depth).
  nev        VARCHAR(50)  NOT NULL CHECK (length(nev) BETWEEN 1 AND 50),
  uzenet     VARCHAR(500) NOT NULL CHECK (length(uzenet) BETWEEN 1 AND 500),

  -- a beírás ideje: az adatbázis tölti ki, világidőben (UTC)
  letrehozva TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- "Tartalomjegyzék" a dátum oszlophoz: gyors marad a legújabbak szerinti rendezés
CREATE INDEX IF NOT EXISTS idx_bejegyzes_ido ON bejegyzes (letrehozva);
