-- seed.sql – mintaadatok a kipróbáláshoz (20 könyv, 45 példány, 15 olvasó, kölcsönzések)
--
-- Demó adatok: az olvasók kitalált személyek, az ISBN-számok kitalált
-- (978-0-00-…) azonosítók. A kölcsönzések dátumai a betöltés napjához
-- viszonyítva készülnek (date('now', '-20 days')), így mindig lesz lejárt
-- és határidőn belüli kölcsönzés is.
--
-- Kézi betöltés:  sqlite3 konyvtar.db < schema.sql && sqlite3 konyvtar.db < seed.sql
-- (Nem kötelező: ha a konyvtar.db még nem létezik, a src/Db.php magától létrehozza.)

BEGIN;

INSERT INTO konyv (id, cim, szerzo, isbn) VALUES
  (1, 'Az ember anatómiájának atlasza', 'Frank H. Netter', '978-0-00-000001-1'),
  (2, 'Sobotta anatómiai atlasz', 'Friedrich Paulsen, Jens Waschke', '978-0-00-000002-2'),
  (3, 'Funkcionális anatómia', 'Szentágothai János, Réthelyi Miklós', '978-0-00-000003-3'),
  (4, 'Orvosi élettan', 'Fonyó Attila', '978-0-00-000004-4'),
  (5, 'Guyton and Hall Textbook of Medical Physiology', 'John E. Hall', '978-0-00-000005-5'),
  (6, 'Robbins Basic Pathology', 'Vinay Kumar', '978-0-00-000006-6'),
  (7, 'Harrison''s Principles of Internal Medicine', 'J. Larry Jameson', '978-0-00-000007-7'),
  (8, 'Lippincott Illustrated Reviews: Biochemistry', 'Denise Ferrier', '978-0-00-000008-8'),
  (9, 'Basic and Clinical Pharmacology', 'Bertram G. Katzung', '978-0-00-000009-9'),
  (10, 'Rang & Dale''s Pharmacology', 'James M. Ritter', '978-0-00-000010-0'),
  (11, 'Bates'' Guide to Physical Examination', 'Lynn S. Bickley', '978-0-00-000011-1'),
  (12, 'Junqueira''s Basic Histology', 'Anthony L. Mescher', '978-0-00-000012-2'),
  (13, 'Molecular Biology of the Cell', 'Bruce Alberts', '978-0-00-000013-3'),
  (14, 'Clinically Oriented Anatomy', 'Keith L. Moore', '978-0-00-000014-4'),
  (15, 'Medical Microbiology', 'Patrick R. Murray', '978-0-00-000015-5'),
  (16, 'Oxford Handbook of Clinical Medicine', 'Ian B. Wilkinson', '978-0-00-000016-6'),
  (17, 'Davidson''s Principles and Practice of Medicine', 'Stuart H. Ralston', '978-0-00-000017-7'),
  (18, 'Gray''s Anatomy for Students', 'Richard L. Drake', '978-0-00-000018-8'),
  (19, 'Kaplan & Sadock''s Synopsis of Psychiatry', 'Robert J. Boland', '978-0-00-000019-9'),
  (20, 'Langman''s Medical Embryology', 'T. W. Sadler', '978-0-00-000020-0');

-- Példányok: leltári szám K-0001-től
INSERT INTO peldany (id, konyv_id, leltari_szam) VALUES
  (1, 1, 'K-0001'),
  (2, 1, 'K-0002'),
  (3, 1, 'K-0003'),
  (4, 1, 'K-0004'),
  (5, 1, 'K-0005'),
  (6, 2, 'K-0006'),
  (7, 2, 'K-0007'),
  (8, 2, 'K-0008'),
  (9, 3, 'K-0009'),
  (10, 3, 'K-0010'),
  (11, 3, 'K-0011'),
  (12, 4, 'K-0012'),
  (13, 4, 'K-0013'),
  (14, 4, 'K-0014'),
  (15, 5, 'K-0015'),
  (16, 5, 'K-0016'),
  (17, 5, 'K-0017'),
  (18, 6, 'K-0018'),
  (19, 6, 'K-0019'),
  (20, 6, 'K-0020'),
  (21, 7, 'K-0021'),
  (22, 7, 'K-0022'),
  (23, 8, 'K-0023'),
  (24, 8, 'K-0024'),
  (25, 9, 'K-0025'),
  (26, 9, 'K-0026'),
  (27, 10, 'K-0027'),
  (28, 10, 'K-0028'),
  (29, 11, 'K-0029'),
  (30, 11, 'K-0030'),
  (31, 12, 'K-0031'),
  (32, 12, 'K-0032'),
  (33, 13, 'K-0033'),
  (34, 13, 'K-0034'),
  (35, 14, 'K-0035'),
  (36, 14, 'K-0036'),
  (37, 15, 'K-0037'),
  (38, 15, 'K-0038'),
  (39, 16, 'K-0039'),
  (40, 16, 'K-0040'),
  (41, 17, 'K-0041'),
  (42, 18, 'K-0042'),
  (43, 18, 'K-0043'),
  (44, 19, 'K-0044'),
  (45, 20, 'K-0045');

INSERT INTO olvaso (id, nev, olvasojegy, email) VALUES
  (1, 'Kovács Anna', 'OJ-2026-001', 'anna.kovacs@hallgato.example'),
  (2, 'Szabó Bence', 'OJ-2026-002', 'bence.szabo@hallgato.example'),
  (3, 'Nagy Réka', 'OJ-2026-003', 'reka.nagy@hallgato.example'),
  (4, 'Molnár Csaba', 'OJ-2026-004', 'csaba.molnar@hallgato.example'),
  (5, 'Farkas Emőke', 'OJ-2026-005', 'emoke.farkas@hallgato.example'),
  (6, 'Balogh Levente', 'OJ-2026-006', 'levente.balogh@hallgato.example'),
  (7, 'Kiss Tímea', 'OJ-2026-007', 'timea.kiss@hallgato.example'),
  (8, 'Fodor Szilárd', 'OJ-2026-008', 'szilard.fodor@hallgato.example'),
  (9, 'Lukács Boglárka', 'OJ-2026-009', 'boglarka.lukacs@hallgato.example'),
  (10, 'Szilágyi Ákos', 'OJ-2026-010', 'akos.szilagyi@hallgato.example'),
  (11, 'Pál Orsolya', 'OJ-2026-011', 'orsolya.pal@hallgato.example'),
  (12, 'Vass Hunor', 'OJ-2026-012', 'hunor.vass@hallgato.example'),
  (13, 'Gál Kinga', 'OJ-2026-013', 'kinga.gal@hallgato.example'),
  (14, 'Sándor Noémi', 'OJ-2026-014', 'noemi.sandor@hallgato.example'),
  (15, 'Antal Zsolt', 'OJ-2026-015', 'zsolt.antal@hallgato.example');

-- Kölcsönzések: 8 visszahozott (előzmény), 5 lejárt, 7 határidőn belüli
INSERT INTO kolcsonzes (peldany_id, olvaso_id, kiadva, hatarido, visszahozva) VALUES
  (1, 1, date('now', '-60 days'), date('now', '-46 days'), date('now', '-50 days')),
  (6, 1, date('now', '-40 days'), date('now', '-26 days'), date('now', '-20 days')),
  (12, 2, date('now', '-45 days'), date('now', '-31 days'), date('now', '-31 days')),
  (2, 3, date('now', '-35 days'), date('now', '-21 days'), date('now', '-22 days')),
  (21, 4, date('now', '-50 days'), date('now', '-36 days'), date('now', '-30 days')),
  (30, 5, date('now', '-28 days'), date('now', '-14 days'), date('now', '-15 days')),
  (9, 6, date('now', '-33 days'), date('now', '-19 days'), date('now', '-10 days')),
  (15, 7, date('now', '-25 days'), date('now', '-11 days'), date('now', '-12 days')),
  (3, 1, date('now', '-30 days'), date('now', '-16 days'), NULL),
  (7, 1, date('now', '-24 days'), date('now', '-10 days'), NULL),
  (13, 4, date('now', '-20 days'), date('now', '-6 days'), NULL),
  (22, 4, date('now', '-18 days'), date('now', '-4 days'), NULL),
  (10, 9, date('now', '-16 days'), date('now', '-2 days'), NULL),
  (1, 2, date('now', '-5 days'), date('now', '+9 days'), NULL),
  (4, 3, date('now', '-3 days'), date('now', '+11 days'), NULL),
  (16, 5, date('now', '-1 days'), date('now', '+13 days'), NULL),
  (25, 6, date('now', '-7 days'), date('now', '+7 days'), NULL),
  (31, 8, date('now', '-2 days'), date('now', '+12 days'), NULL),
  (40, 10, date('now'), date('now', '+14 days'), NULL),
  (19, 12, date('now', '-10 days'), date('now', '+4 days'), NULL);

-- Könyvtáros a belépéshez:  felhasználónév: konyvtaros   jelszó: Konyvtar2026!
INSERT INTO konyvtaros (felhasznalonev, jelszo_hash, nev) VALUES
  ('konyvtaros', '$2y$10$zdoosAFmTGS9yKrWptl.b.waHbQf5dA8HvNHceVr3/694T8WK3dgy', 'Demó Könyvtáros');

COMMIT;
