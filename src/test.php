<?php

require_once __DIR__ . '/config/conexion.php';

echo "<h1>Conexión OK</h1>";

echo "<pre>";
echo "MYSQLHOST: " . getenv('MYSQLHOST') . PHP_EOL;
echo "MYSQLPORT: " . getenv('MYSQLPORT') . PHP_EOL;
echo "MYSQLDATABASE: " . getenv('MYSQLDATABASE') . PHP_EOL;
echo "MYSQLUSER: " . getenv('MYSQLUSER') . PHP_EOL;
echo "</pre>";