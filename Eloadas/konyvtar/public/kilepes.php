<?php
// kilepes.php – kilépés. Csak POST + CSRF: egy idegen oldal ne tudjon kiléptetni.
require __DIR__ . '/../src/bootstrap.php';

csak_post();
$_SESSION = [];
session_regenerate_id(true);
flash('ok', 'Kiléptél.');
atiranyit('index.php');
