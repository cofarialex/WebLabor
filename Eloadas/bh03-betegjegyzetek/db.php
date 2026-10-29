<?php
// Séma (egyszer futtasd):
// CREATE TABLE jegyzetek (id INT AUTO_INCREMENT PRIMARY KEY, orvos_id INT NOT NULL,
//   beteg VARCHAR(100) NOT NULL, szoveg TEXT NOT NULL, letrehozva TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
$pdo = new PDO('mysql:host=localhost;dbname=rendelo;charset=utf8mb4', 'rendelo_app', getenv('DB_PASS') ?: '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
session_start();
// Demó: a belépés egyszerűsítve – ?orvos=2 paraméterrel válthatsz orvost
if (isset($_GET['orvos'])) { $_SESSION['orvos_id'] = (int) $_GET['orvos']; }
$_SESSION['orvos_id'] = $_SESSION['orvos_id'] ?? 1;
