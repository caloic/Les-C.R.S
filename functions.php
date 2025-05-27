<?php

// Inclure la configuration
require_once 'config.php';
require_once 'ml_prediction_service.php';

/**
 * Fonction utilitaire pour valider et nettoyer les données numériques
 */
function validateNumeric($value, $default = 0, $min = null, $max = null) {
    // Convertir en float
    $num = is_numeric($value) ? floatval($value) : $default;

    // Vérifier si c'est un nombre valide
    if (!is_finite($num) || is_nan($num)) {
        return $default;
    }

    // Appliquer les limites min/max si spécifiées
    if ($min !== null && $num < $min) $num = $min;
    if ($max !== null && $num > $max) $num = $max;

    return $num;
}

/**
 * Nettoie et valide les données météo reçues de l'API
 */
function cleanWeatherData($data) {
    if (!$data || !is_array($data)) {
        return null;
    }

    // Nettoyer les données actuelles
    if (isset($data['current'])) {
        $data['current']['temp_c'] = validateNumeric($data['current']['temp_c'] ?? null, 20, -50, 60);
        $data['current']['humidity'] = validateNumeric($data['current']['humidity'] ?? null, 60, 0, 100);
        $data['current']['wind_kph'] = validateNumeric($data['current']['wind_kph'] ?? null, 10, 0, 200);
        $data['current']['pressure_mb'] = validateNumeric($data['current']['pressure_mb'] ?? null, 1013, 800, 1200);
        $data['current']['vis_km'] = validateNumeric($data['current']['vis_km'] ?? null, 10, 0, 50);
        $data['current']['uv'] = validateNumeric($data['current']['uv'] ?? null, 3, 0, 12);

        // Vérifier que la condition existe
        if (!isset($data['current']['condition']['text']) || empty($data['current']['condition']['text'])) {
            $data['current']['condition']['text'] = 'Temps variable';
        }
    }

    // Nettoyer les données de localisation
    if (isset($data['location'])) {
        $data['location']['lat'] = validateNumeric($data['location']['lat'] ?? null, 46.603354, -90, 90);
        $data['location']['lon'] = validateNumeric($data['location']['lon'] ?? null, 1.888334, -180, 180);

        if (!isset($data['location']['name']) || empty($data['location']['name'])) {
            $data['location']['name'] = 'Ville inconnue';
        }
    }

    // Nettoyer les prévisions
    if (isset($data['forecast']['forecastday']) && is_array($data['forecast']['forecastday'])) {
        foreach ($data['forecast']['forecastday'] as $index => &$day) {
            if (isset($day['day'])) {
                $day['day']['maxtemp_c'] = validateNumeric($day['day']['maxtemp_c'] ?? null, 25, -50, 60);
                $day['day']['mintemp_c'] = validateNumeric($day['day']['mintemp_c'] ?? null, 15, -50, 60);
                $day['day']['avgtemp_c'] = validateNumeric($day['day']['avgtemp_c'] ?? null, 20, -50, 60);
                $day['day']['maxwind_kph'] = validateNumeric($day['day']['maxwind_kph'] ?? null, 15, 0, 200);
                $day['day']['avghumidity'] = validateNumeric($day['day']['avghumidity'] ?? null, 60, 0, 100);

                // Vérifier la cohérence des températures
                if ($day['day']['mintemp_c'] > $day['day']['maxtemp_c']) {
                    $temp = $day['day']['mintemp_c'];
                    $day['day']['mintemp_c'] = $day['day']['maxtemp_c'];
                    $day['day']['maxtemp_c'] = $temp;
                }

                // S'assurer que la température moyenne est entre min et max
                $day['day']['avgtemp_c'] = max($day['day']['mintemp_c'],
                    min($day['day']['maxtemp_c'], $day['day']['avgtemp_c']));

                // Vérifier la condition météo
                if (!isset($day['day']['condition']['text']) || empty($day['day']['condition']['text'])) {
                    $day['day']['condition']['text'] = 'Temps variable';
                }
            }
        }
    }

    return $data;
}

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

        // Appel à l'API externe avec gestion d'erreur robuste
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

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

        // NOUVEAU : Nettoyer et valider les données reçues
        $data = cleanWeatherData($data);
        if (!$data) {
            error_log("Échec du nettoyage des données météo");
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

        // Valider les prédictions IA aussi
        if ($prediction) {
            $prediction['predicted_temperature'] = validateNumeric($prediction['predicted_temperature'], 20, -50, 60);
            $prediction['predicted_humidity'] = validateNumeric($prediction['predicted_humidity'], 60, 0, 100);

            // Ajouter la prédiction IA aux données de retour
            $data['prediction'] = [
                'temperature' => round($prediction['predicted_temperature'], 1),
                'humidity' => round($prediction['predicted_humidity']),
                'timestamp' => $prediction['prediction_timestamp'] ?? date('Y-m-d H:i:s')
            ];
        }

        // Sauvegarder les données météo dans la base de données pour référence future
        saveWeatherData($data, $locationData['id']);

        return $data;
    } catch (Exception $e) {
        error_log("Exception dans getWeatherData: " . $e->getMessage());
        // En cas d'erreur, utiliser les données locales
        return getLocalWeatherData($locationData ?? ['id' => null, 'name' => $location, 'latitude' => 46.603354, 'longitude' => 1.888334]);
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
            // Données minimales avec validation si rien n'est trouvé
            $weatherData = [
                'temperature' => 20,
                'humidity' => 60,
                'wind_speed' => 10,
                'weather_condition' => 'Partiellement nuageux'
            ];
        } else {
            // Valider les données de la base
            $weatherData['temperature'] = validateNumeric($weatherData['temperature'], 20, -50, 60);
            $weatherData['humidity'] = validateNumeric($weatherData['humidity'], 60, 0, 100);
            $weatherData['wind_speed'] = validateNumeric($weatherData['wind_speed'], 10, 0, 200);
        }

        // Récupérer également la prédiction
        $prediction = getPredictionForLocation($locationData['id']);
        if (!$prediction) {
            // Créer une prédiction validée si aucune n'existe
            $prediction = [
                'predicted_temperature' => validateNumeric($weatherData['temperature'] * 1.05, 22, -50, 60),
                'predicted_humidity' => validateNumeric(min(100, $weatherData['humidity'] * 0.95), 57, 0, 100),
                'prediction_timestamp' => date('Y-m-d H:i:s')
            ];
        } else {
            // Valider les prédictions existantes
            $prediction['predicted_temperature'] = validateNumeric($prediction['predicted_temperature'], 20, -50, 60);
            $prediction['predicted_humidity'] = validateNumeric($prediction['predicted_humidity'], 60, 0, 100);
        }

        // Formater les données pour qu'elles soient compatibles avec le format de l'API
        return [
            'location' => [
                'name' => $locationData['name'] ?? 'Ville inconnue',
                'lat' => validateNumeric($locationData['latitude'], 46.603354, -90, 90),
                'lon' => validateNumeric($locationData['longitude'], 1.888334, -180, 180),
                'region' => '',
                'country' => 'France',
                'localtime' => date('Y-m-d H:i')
            ],
            'current' => [
                'temp_c' => $weatherData['temperature'],
                'humidity' => $weatherData['humidity'],
                'wind_kph' => $weatherData['wind_speed'],
                'condition' => [
                    'text' => $weatherData['weather_condition'] ?? 'Temps variable',
                    'icon' => getWeatherIcon($weatherData['weather_condition'] ?? 'Temps variable')
                ],
                'pressure_mb' => validateNumeric(rand(1000, 1025), 1013, 800, 1200),
                'vis_km' => validateNumeric(rand(8, 20), 10, 0, 50),
                'uv' => validateNumeric(rand(1, 6), 3, 0, 12),
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
 * Génère des prévisions fictives pour les jours suivants avec validation
 */
function generateFakeForecast($baseTemp, $baseHumidity) {
    $forecast = [];
    $conditions = ['Ensoleillé', 'Partiellement nuageux', 'Nuageux', 'Pluie légère', 'Pluie'];

    // Valider les données de base
    $baseTemp = validateNumeric($baseTemp, 20, -50, 60);
    $baseHumidity = validateNumeric($baseHumidity, 60, 0, 100);

    // Date du jour
    $currentDate = date('Y-m-d');

    // Générer des prévisions pour 7 jours
    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime($currentDate . ' +' . $i . ' days'));

        // Varier la température et l'humidité de manière réaliste
        $tempVariation = rand(-5, 5);
        $humidityVariation = rand(-15, 15);

        $dayTemp = validateNumeric($baseTemp + $tempVariation, 20, -50, 60);
        $nightTemp = validateNumeric($dayTemp - rand(3, 8), 15, -50, 60);
        $humidity = validateNumeric($baseHumidity + $humidityVariation, 60, 0, 100);

        // Assurer la cohérence des températures
        if ($nightTemp > $dayTemp) {
            $temp = $nightTemp;
            $nightTemp = $dayTemp;
            $dayTemp = $temp;
        }

        // Condition météo aléatoire
        $condition = $conditions[array_rand($conditions)];

        $forecast[] = [
            'date' => $date,
            'day' => [
                'maxtemp_c' => round($dayTemp, 1),
                'mintemp_c' => round($nightTemp, 1),
                'avgtemp_c' => round(($dayTemp + $nightTemp) / 2, 1),
                'avghumidity' => round($humidity),
                'maxwind_kph' => validateNumeric(rand(5, 30), 15, 0, 200),
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
                    validateNumeric($data['location']['lat'], 46.603354, -90, 90),
                    validateNumeric($data['location']['lon'], 1.888334, -180, 180)
                ]);
            }
        }

        // Enregistrer les données météo actuelles avec validation
        $weatherId = generateUUID();
        $stmt = $pdo->prepare("INSERT INTO weather_data (id, location_id, temperature, humidity, wind_speed, weather_condition) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $weatherId,
            $locationId,
            validateNumeric($data['current']['temp_c'], 20, -50, 60),
            validateNumeric($data['current']['humidity'], 60, 0, 100),
            validateNumeric($data['current']['wind_kph'], 10, 0, 200),
            $data['current']['condition']['text'] ?? 'Temps variable'
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
        $result = $stmt->fetch();

        if ($result) {
            // Valider les données récupérées
            $result['temperature'] = validateNumeric($result['temperature'], 20, -50, 60);
            $result['humidity'] = validateNumeric($result['humidity'], 60, 0, 100);
            $result['wind_speed'] = validateNumeric($result['wind_speed'], 10, 0, 200);
            $result['latitude'] = validateNumeric($result['latitude'], 46.603354, -90, 90);
            $result['longitude'] = validateNumeric($result['longitude'], 1.888334, -180, 180);
        }

        return $result;
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
        $locations = $stmt->fetchAll();

        // Valider les coordonnées de chaque localisation
        foreach ($locations as &$location) {
            $location['latitude'] = validateNumeric($location['latitude'], 46.603354, -90, 90);
            $location['longitude'] = validateNumeric($location['longitude'], 1.888334, -180, 180);
        }

        return $locations;
    } catch (PDOException $e) {
        error_log('Erreur de récupération des localisations: ' . $e->getMessage());
        return [];
    }
}

/**
 * Crée une prédiction météo simple avec validation
 */
function makePrediction($locationId, $currentTemp, $currentHumidity) {
    global $pdo;

    try {
        // Essayer d'utiliser le ML si disponible
        $mlService = new MLPredictionService('http://localhost:5000');

        if ($mlService->isAvailable()) {
            // Préparer les données pour le ML
            $weatherData = [
                'temperature' => $currentTemp,
                'humidity' => $currentHumidity,
                'wind_speed' => 10,  // Valeur par défaut
                'precipitation' => 0
            ];

            // Obtenir la prédiction ML
            $mlPrediction = $mlService->getPrediction($weatherData);

            if ($mlPrediction) {
                // Sauvegarder en base de données
                $predictionId = generateUUID();
                $stmt = $pdo->prepare("
                    INSERT INTO weather_predictions
                    (id, location_id, predicted_temperature, predicted_humidity, prediction_timestamp)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $predictionId,
                    $locationId,
                    $mlPrediction['predicted_temperature'],
                    $mlPrediction['predicted_humidity']
                ]);

                error_log("✅ Prédiction ML utilisée pour location $locationId");

                return [
                    'predicted_temperature' => $mlPrediction['predicted_temperature'],
                    'predicted_humidity' => $mlPrediction['predicted_humidity'],
                    'prediction_timestamp' => date('Y-m-d H:i:s'),
                    'source' => 'Machine Learning'
                ];
            }
        }

        // FALLBACK: Si ML non disponible, utiliser l'ancienne méthode
        error_log("⚠️ ML non disponible, utilisation du fallback");

        // Simuler une prédiction (dans un vrai projet, ce serait un modèle ML)
        $tempVariation = (rand(-5, 5) / 100); // ±5%
        $humidityVariation = (rand(-10, 10) / 100); // ±10%

        $predictedTemp = validateNumeric($currentTemp * (1 + $tempVariation), 20, -50, 60);
        $predictedHumidity = validateNumeric($currentHumidity * (1 + $humidityVariation), 60, 0, 100);

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
            'prediction_timestamp' => date('Y-m-d H:i:s'),
            'source' => 'Simulation'
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
        $result = $stmt->fetch();

        if ($result) {
            // Valider les prédictions récupérées
            $result['predicted_temperature'] = validateNumeric($result['predicted_temperature'], 20, -50, 60);
            $result['predicted_humidity'] = validateNumeric($result['predicted_humidity'], 60, 0, 100);
        }

        return $result;
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