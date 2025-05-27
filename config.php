<?php
// Configuration de base
define('SITE_NAME', 'MétéoCRS');
define('WEATHER_API_KEY', '4d7bd0c96dbe413199690446250503'); // Remplacer par votre clé WeatherAPI.com

// Configuration de la base de données
$db_config = [
    'host' => 'localhost',
    'dbname' => 'les_crs',
    'user' => 'root',
    'password' => 'root'
];

// Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4",
        $db_config['user'],
        $db_config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Fuseau horaire
date_default_timezone_set('Europe/Paris');