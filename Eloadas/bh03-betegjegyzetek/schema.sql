-- schema.sql – a Betegjegyzetek adatbázisa (MySQL / MariaDB)
-- Futtatás egyszer, rendszergazdaként:  mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS rendelo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rendelo;

CREATE TABLE IF NOT EXISTS jegyzetek (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  orvos_id   INT NOT NULL,
  beteg      VARCHAR(100) NOT NULL,
  szoveg     TEXT NOT NULL,
  letrehozva TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (orvos_id)
);

-- Legkisebb jogosultság: az alkalmazás felhasználója csak ehhez az egy táblához fér
-- hozzá, és csak azokkal a műveletekkel, amiket a kód tényleg használ.
-- (Nincs DROP, ALTER, UPDATE – egy esetleges SQL injection így kevesebbet árthat.)
CREATE USER IF NOT EXISTS 'rendelo_app'@'localhost' IDENTIFIED BY 'valtoztasd-meg';
GRANT SELECT, INSERT, DELETE ON rendelo.jegyzetek TO 'rendelo_app'@'localhost';
