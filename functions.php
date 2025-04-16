<?php

// Inclure la configuration
require_once 'config.php';

function getWeatherData($location) {
    global $pdo;

    try {
        // Rechercher d'abord si la ville existe dans notre base de données
        $stmt = $pdo->prepare("
            SELECT id, name, latitude, longitude 
            FROM locations 
            WHERE name LIKE :location
            LIMIT 1
        ");
        $stmt->execute([':location' => '%' . $location . '%']);
        $locationData = $stmt->fetch();

        // Si la ville n'est pas trouvée dans notre base de données, retourner une erreur
        if (!$locationData) {
            return ['error' => 'Cette ville n\'est pas disponible dans notre base de données'];
        }

        // La ville existe dans notre base de données, on peut maintenant appeler l'API externe
        $apiKey = WEATHER_API_KEY;

        // Utiliser les coordonnées plutôt que le nom pour éviter les problèmes d'encodage
        $coordinates = $locationData['latitude'] . ',' . $locationData['longitude'];
        $url = "https://api.weatherapi.com/v1/forecast.json?key={$apiKey}&q={$coordinates}&days=7&aqi=no&alerts=no&lang=fr";

        // Déboguer l'URL si nécessaire
        // error_log("URL API: " . $url);

        // Appel à l'API externe avec gestion d'erreur robuste
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // timeout après 10 secondes

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode != 200 || !$response) {
            // Échec de l'appel API, utiliser les données locales
            error_log("Erreur API WeatherAPI: Code $httpCode, Erreur: $curlError");
            return getLocalWeatherData($locationData);
        }

        // Convertir la réponse JSON en tableau PHP
        $data = json_decode($response, true);
        if (!$data || !isset($data['location']) || !isset($data['current'])) {
            // Réponse API invalide, utiliser les données locales
            error_log("Réponse API WeatherAPI invalide: " . substr($response, 0, 200) . "...");
            return getLocalWeatherData($locationData);
        }

        // Remplacer le nom de la ville par celui de notre base de données pour cohérence
        $data['location']['name'] = $locationData['name'];

        // Ajouter notre prédiction IA
        $prediction = getPredictionForLocation($locationData['id']);
        if (!$prediction) {
            // Générer une nouvelle prédiction si aucune n'existe
            $prediction = makePrediction($locationData['id'], $data['current']['temp_c'], $data['current']['humidity']);
        }

        // Ajouter la prédiction IA aux données de retour
        $data['prediction'] = [
            'temperature' => round($prediction['predicted_temperature'], 1),
            'humidity' => round($prediction['predicted_humidity']),
            'timestamp' => $prediction['prediction_timestamp'] ?? date('Y-m-d H:i:s')
        ];

        // Sauvegarder les données météo dans la base de données pour référence future
        saveWeatherData($data, $locationData['id']);

        return $data;
    } catch (Exception $e) {
        error_log("Exception dans getWeatherData: " . $e->getMessage());
        // En cas d'erreur, utiliser les données locales
        return getLocalWeatherData(['id' => $locationData['id'] ?? null, 'name' => $location, 'latitude' => 0, 'longitude' => 0]);
    }
}

/**
 * Récupère les données météo depuis la base de données pour une localisation
 *
 * @param mixed $location Peut être un tableau avec id, name, etc. ou directement le nom de la ville
 * @return array Données météo au format compatible avec l'API
 */
function getLocalWeatherData($location) {
    global $pdo;

    try {
        // Si $location est une chaîne (nom de ville), chercher d'abord l'ID de localisation
        if (is_string($location)) {
            // Rechercher la localisation par nom
            $stmt = $pdo->prepare("
                SELECT id, name, latitude, longitude 
                FROM locations 
                WHERE name LIKE :location
                LIMIT 1
            ");
            $stmt->execute([':location' => '%' . $location . '%']);
            $locationData = $stmt->fetch();

            if (!$locationData) {
                return ['error' => 'Cette ville n\'est pas disponible dans notre base de données'];
            }
        } else {
            // $location est déjà un tableau de données
            $locationData = $location;
        }

        // Vérifier qu'on a bien l'ID de localisation
        if (!isset($locationData['id'])) {
            return ['error' => 'Données de localisation incomplètes'];
        }

        // Récupérer les données météo les plus récentes pour cette localisation
        $weatherData = getWeatherForLocation($locationData['id']);

        if (!$weatherData) {
            // Données minimales si rien n'est trouvé
            $weatherData = [
                'temperature' => 15,
                'humidity' => 60,
                'wind_speed' => 10,
                'weather_condition' => 'Partiellement nuageux'
            ];
        }

        // Récupérer également la prédiction
        $prediction = getPredictionForLocation($locationData['id']);
        if (!$prediction) {
            // Créer une prédiction si aucune n'existe
            $prediction = [
                'predicted_temperature' => $weatherData['temperature'] * 1.05,
                'predicted_humidity' => min(100, $weatherData['humidity'] * 0.95),
                'prediction_timestamp' => date('Y-m-d H:i:s')
            ];
        }

        // Formater les données pour qu'elles soient compatibles avec le format de l'API
        return [
            'location' => [
                'name' => $locationData['name'],
                'lat' => $locationData['latitude'],
                'lon' => $locationData['longitude'],
                'region' => '',
                'country' => 'France',
                'localtime' => date('Y-m-d H:i')
            ],
            'current' => [
                'temp_c' => $weatherData['temperature'],
                'humidity' => $weatherData['humidity'],
                'wind_kph' => $weatherData['wind_speed'],
                'condition' => [
                    'text' => $weatherData['weather_condition'],
                    'icon' => getWeatherIcon($weatherData['weather_condition'])
                ],
                'pressure_mb' => rand(1000, 1025),
                'vis_km' => rand(8, 20),
                'uv' => rand(1, 6),
                'last_updated' => date('Y-m-d H:i')
            ],
            'forecast' => [
                'forecastday' => generateFakeForecast($weatherData['temperature'], $weatherData['humidity'])
            ],
            'prediction' => [
                'temperature' => round($prediction['predicted_temperature'], 1),
                'humidity' => round($prediction['predicted_humidity']),
                'timestamp' => $prediction['prediction_timestamp']
            ]
        ];
    } catch (PDOException $e) {
        error_log('Erreur dans getLocalWeatherData: ' . $e->getMessage());
        return ['error' => 'Erreur de base de données'];
    }
}

/**
 * Génère une icône météo en fonction de la condition
 */
function getWeatherIcon($condition) {
    $condition = strtolower($condition);

    if (strpos($condition, 'soleil') !== false || strpos($condition, 'ensoleill') !== false) {
        return "//cdn.weatherapi.com/weather/64x64/day/113.png";
    } elseif (strpos($condition, 'nuage') !== false || strpos($condition, 'nuageux') !== false) {
        return "//cdn.weatherapi.com/weather/64x64/day/116.png";
    } elseif (strpos($condition, 'pluie') !== false) {
        return "//cdn.weatherapi.com/weather/64x64/day/296.png";
    } elseif (strpos($condition, 'orage') !== false) {
        return "//cdn.weatherapi.com/weather/64x64/day/389.png";
    } elseif (strpos($condition, 'neige') !== false) {
        return "//cdn.weatherapi.com/weather/64x64/day/326.png";
    } else {
        return "//cdn.weatherapi.com/weather/64x64/day/116.png"; // Icône par défaut
    }
}

/**
 * Génère des prévisions fictives pour les jours suivants
 */
function generateFakeForecast($baseTemp, $baseHumidity) {
    $forecast = [];
    $conditions = ['Ensoleillé', 'Partiellement nuageux', 'Nuageux', 'Pluie légère', 'Pluie'];

    // Date du jour
    $currentDate = date('Y-m-d');

    // Générer des prévisions pour 7 jours
    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime($currentDate . ' +' . $i . ' days'));

        // Varier la température et l'humidité de manière réaliste
        $tempVariation = rand(-5, 5);
        $humidityVariation = rand(-15, 15);

        $dayTemp = $baseTemp + $tempVariation;
        $nightTemp = $dayTemp - rand(3, 8);
        $humidity = min(95, max(30, $baseHumidity + $humidityVariation));

        // Condition météo aléatoire
        $condition = $conditions[array_rand($conditions)];

        $forecast[] = [
            'date' => $date,
            'day' => [
                'maxtemp_c' => round($dayTemp, 1),
                'mintemp_c' => round($nightTemp, 1),
                'avghumidity' => round($humidity),
                'maxwind_kph' => rand(5, 30),
                'condition' => [
                    'text' => $condition,
                    'icon' => getWeatherIcon($condition)
                ]
            ]
        ];
    }

    return $forecast;
}

/**
 * Sauvegarde les données météo dans la base de données
 */
function saveWeatherData($data, $locationId = null) {
    global $pdo;

    try {
        // Si aucun ID de localisation fourni, recherche la localisation par nom
        if (!$locationId) {
            $stmt = $pdo->prepare("SELECT id FROM locations WHERE name = ?");
            $stmt->execute([$data['location']['name']]);
            $location = $stmt->fetch();

            if ($location) {
                $locationId = $location['id'];
            } else {
                // Créer une nouvelle localisation si elle n'existe pas
                $locationId = generateUUID();
                $stmt = $pdo->prepare("INSERT INTO locations (id, name, latitude, longitude) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $locationId,
                    $data['location']['name'],
                    $data['location']['lat'],
                    $data['location']['lon']
                ]);
            }
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

        return true;
    } catch (PDOException $e) {
        error_log('Erreur de sauvegarde des données: ' . $e->getMessage());
        return false;
    }
}

/**
 * Génère un UUID v4
 */
function generateUUID() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Récupère les dernières données météo pour une localisation
 */
function getWeatherForLocation($locationId) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT l.name, l.latitude, l.longitude, w.temperature, w.humidity, 
                   w.wind_speed, w.weather_condition, w.timestamp
            FROM weather_data w
            JOIN locations l ON w.location_id = l.id
            WHERE l.id = ?
            ORDER BY w.timestamp DESC
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
 * Récupère toutes les localisations enregistrées
 */
function getAllLocations() {
    global $pdo;

    try {
        $stmt = $pdo->query("SELECT * FROM locations ORDER BY name LIMIT 1000");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Erreur de récupération des localisations: ' . $e->getMessage());
        return [];
    }
}

/**
 * Crée une prédiction météo simple
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

        return [
            'predicted_temperature' => $predictedTemp,
            'predicted_humidity' => $predictedHumidity,
            'prediction_timestamp' => date('Y-m-d H:i:s')
        ];
    } catch (PDOException $e) {
        error_log('Erreur de création de prédiction: ' . $e->getMessage());
        return null;
    }
}

/**
 * Récupère la dernière prédiction pour une localisation
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
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}