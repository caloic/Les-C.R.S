<?php
/**
 * Fichier de fonctions utilitaires pour le site météo
 */

// Inclure la configuration
require_once 'config.php';

/**
 * Récupère les données météo actuelles pour une localisation
 *
 * @param string $location Nom de la ville ou coordonnées
 * @return array Données météo
 */
function getWeatherData($location) {
    global $pdo;
    $apiKey = WEATHER_API_KEY;
    $url = "https://api.weatherapi.com/v1/forecast.json?key={$apiKey}&q={$location}&days=7&aqi=no&alerts=no&lang=fr";

    try {
        // Appel à l'API externe
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status != 200) {
            return ['error' => 'Impossible de récupérer les données météo'];
        }

        $data = json_decode($response, true);

        // Sauvegarder les données dans la base de données
        saveWeatherData($data);

        return $data;
    } catch (Exception $e) {
        return ['error' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Sauvegarde les données météo dans la base de données
 *
 * @param array $data Données météo à sauvegarder
 * @return bool Succès ou échec
 */
function saveWeatherData($data) {
    global $pdo;

    try {
        // Vérifier si la localisation existe déjà
        $stmt = $pdo->prepare("SELECT id FROM locations WHERE name = ?");
        $stmt->execute([$data['location']['name']]);
        $location = $stmt->fetch();

        if ($location) {
            $locationId = $location['id'];
        } else {
            // Créer une nouvelle localisation
            $locationId = generateUUID();
            $stmt = $pdo->prepare("INSERT INTO locations (id, name, latitude, longitude) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $locationId,
                $data['location']['name'],
                $data['location']['lat'],
                $data['location']['lon']
            ]);
        }

        // Enregistrer les données météo actuelles
        $weatherId = generateUUID();
        $stmt = $pdo->prepare("INSERT INTO weather_data (id, location_id, temperature, humidity, wind_speed, weather_condition) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $weatherId,
            $locationId,
            $data['current']['temp_c'],
            $data['current']['humidity'],
            $data['current']['wind_kph'],
            $data['current']['condition']['text']
        ]);

        // Créer une prédiction météo simple
        makePrediction($locationId, $data['current']['temp_c'], $data['current']['humidity']);

        return true;
    } catch (PDOException $e) {
        error_log('Erreur de sauvegarde des données: ' . $e->getMessage());
        return false;
    }
}

/**
 * Génère un UUID v4
 *
 * @return string UUID généré
 */
function generateUUID() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Récupère toutes les localisations enregistrées
 *
 * @return array Liste des localisations
 */
function getAllLocations() {
    global $pdo;

    try {
        $stmt = $pdo->query("SELECT * FROM locations ORDER BY name");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Erreur de récupération des localisations: ' . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les dernières données météo pour une localisation
 *
 * @param string $locationId ID de la localisation
 * @return array Données météo
 */
function getWeatherForLocation($locationId) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT l.name, l.latitude, l.longitude, w.temperature, w.humidity, 
                   w.wind_speed, w.weather_condition
            FROM weather_data w
            JOIN locations l ON w.location_id = l.id
            WHERE l.id = ?
            ORDER BY w.id DESC
            LIMIT 1
        ");
        $stmt->execute([$locationId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Erreur de récupération des données météo: ' . $e->getMessage());
        return null;
    }
}

/**
 * Crée une prédiction météo simple
 *
 * @param string $locationId ID de la localisation
 * @param float $currentTemp Température actuelle
 * @param float $currentHumidity Humidité actuelle
 * @return bool Succès ou échec
 */
function makePrediction($locationId, $currentTemp, $currentHumidity) {
    global $pdo;

    try {
        // Simuler une prédiction (dans un vrai projet, ce serait un modèle ML)
        $predictedTemp = $currentTemp * (1 + (rand(-5, 5) / 100));
        $predictedHumidity = min(100, max(0, $currentHumidity * (1 + (rand(-10, 10) / 100))));

        $predictionId = generateUUID();
        $stmt = $pdo->prepare("
            INSERT INTO weather_predictions 
            (id, location_id, predicted_temperature, predicted_humidity, prediction_timestamp)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $predictionId,
            $locationId,
            $predictedTemp,
            $predictedHumidity
        ]);

        return true;
    } catch (PDOException $e) {
        error_log('Erreur de création de prédiction: ' . $e->getMessage());
        return false;
    }
}

/**
 * Récupère la dernière prédiction pour une localisation
 *
 * @param string $locationId ID de la localisation
 * @return array Données de prédiction
 */
function getPredictionForLocation($locationId) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT predicted_temperature, predicted_humidity, prediction_timestamp
            FROM weather_predictions
            WHERE location_id = ?
            ORDER BY prediction_timestamp DESC
            LIMIT 1
        ");
        $stmt->execute([$locationId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Erreur de récupération des prédictions: ' . $e->getMessage());
        return null;
    }
}

/**
 * Formatte une date en français
 *
 * @param string $date Date au format SQL
 * @return string Date formatée en français
 */
function formatDateFr($date) {
    $timestamp = strtotime($date);
    $jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $mois = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

    $jour_semaine = $jours[date('w', $timestamp)];
    $jour = date('j', $timestamp);
    $mois_nom = $mois[date('n', $timestamp) - 1];
    $annee = date('Y', $timestamp);

    return "$jour_semaine $jour $mois_nom $annee";
}

/**
 * Sécurise une chaîne pour l'affichage HTML
 *
 * @param string $string Chaîne à sécuriser
 * @return string Chaîne sécurisée
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}