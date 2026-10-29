-- schema.sql – a Jegyzetek adatbázisának szerkezete (SQLite)
--
-- Nem kell kézzel futtatnod: a db.php minden kapcsolódáskor lefuttatja.
-- Az "IF NOT EXISTS" miatt ez biztonságos: ha a tábla már létezik, nem történik semmi.
-- Ha mégis kézzel szeretnéd:  sqlite3 jegyzetek.sqlite < schema.sql
--
-- MySQL-ben ugyanez: id INT AUTO_INCREMENT PRIMARY KEY, a többi oszlop változatlan.

CREATE TABLE IF NOT EXISTS jegyzet (
  -- sorszám: az adatbázis adja (1, 2, 3, ...)
  id         INTEGER PRIMARY KEY AUTOINCREMENT,

  -- Az SQLite NEM tartatja be a VARCHAR(120) hosszkorlátot (a MySQL igen),
  -- ezért CHECK feltétel is őrködik – nem csak a PHP (rétegelt védelem).
  cim        VARCHAR(120) NOT NULL CHECK (length(cim) BETWEEN 1 AND 120),
  tartalom   TEXT         NOT NULL CHECK (length(tartalom) BETWEEN 1 AND 20000),

  -- Mindkettőt az adatbázis tölti ki, világidőben (UTC).
  -- A frissitve oszlopot az UPDATE-ben kézzel is átállítjuk:
  -- az SQLite-ban nincs "ON UPDATE CURRENT_TIMESTAMP" (a MySQL-ben volna).
  letrehozva TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  frissitve  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- "Tartalomjegyzék" a frissitve oszlophoz: gyors marad a "legutóbb módosított elöl" rendezés
CREATE INDEX IF NOT EXISTS idx_jegyzet_frissitve ON jegyzet (frissitve);
