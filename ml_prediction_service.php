<?php
/**
 * Service pour obtenir des prédictions ML depuis l'API Python
 */

class MLPredictionService {
    private $api_url;
    private $timeout;

    public function __construct($api_url = 'http://localhost:5000', $timeout = 5) {
        $this->api_url = $api_url;
        $this->timeout = $timeout;
    }

    /**
     * Vérifie si l'API ML est disponible
     */
    public function isAvailable() {
        $ch = curl_init($this->api_url . '/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    /**
     * Obtient une prédiction pour des données météo actuelles
     */
    public function getPrediction($weatherData, $historicalData = null) {
        try {
            // Préparer les données pour l'API
            $requestData = [
                'current_weather' => [
                    'temperature' => floatval($weatherData['temperature'] ?? 20),
                    'humidity' => floatval($weatherData['humidity'] ?? 60),
                    'wind_speed' => floatval($weatherData['wind_speed'] ?? 10),
                    'precipitation' => floatval($weatherData['precipitation'] ?? 0)
                ]
            ];

            // Ajouter les données historiques si disponibles
            if ($historicalData && is_array($historicalData)) {
                $requestData['historical_data'] = $historicalData;
            }

            // Appel à l'API
            $ch = curl_init($this->api_url . '/predict');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                error_log("Erreur API ML: Code HTTP $httpCode, Erreur: $error");
                return null;
            }

            $data = json_decode($response, true);

            if (!$data || !$data['success']) {
                error_log("Réponse API ML invalide: " . substr($response, 0, 200));
                return null;
            }

            return [
                'predicted_temperature' => $data['predictions']['temperature']['value'],
                'predicted_humidity' => $data['predictions']['humidity']['value'],
                'confidence_temperature' => $data['predictions']['temperature']['confidence_interval'],
                'confidence_humidity' => $data['predictions']['humidity']['confidence_interval'],
                'model_metrics' => $data['model_info']['metrics'] ?? null
            ];

        } catch (Exception $e) {
            error_log("Exception dans getPrediction: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtient des prédictions pour plusieurs localisations
     */
    public function getBatchPredictions($locations) {
        try {
            $requestData = ['locations' => []];

            foreach ($locations as $location) {
                $requestData['locations'][] = [
                    'id' => $location['id'],
                    'name' => $location['name'],
                    'current_weather' => [
                        'temperature' => floatval($location['temperature'] ?? 20),
                        'humidity' => floatval($location['humidity'] ?? 60),
                        'wind_speed' => floatval($location['wind_speed'] ?? 10),
                        'precipitation' => floatval($location['precipitation'] ?? 0)
                    ]
                ];
            }

            $ch = curl_init($this->api_url . '/batch_predict');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout * 2); // Plus de temps pour batch

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                return null;
            }

            $data = json_decode($response, true);

            if (!$data || !$data['success']) {
                return null;
            }

            return $data['predictions'];

        } catch (Exception $e) {
            error_log("Exception dans getBatchPredictions: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtient les informations sur les modèles ML
     */
    public function getModelInfo() {
        try {
            $ch = curl_init($this->api_url . '/model_info');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                return null;
            }

            $data = json_decode($response, true);

            return $data && $data['success'] ? $data['metadata'] : null;

        } catch (Exception $e) {
            error_log("Exception dans getModelInfo: " . $e->getMessage());
            return null;
        }
    }
}

// Fonction helper pour obtenir les données historiques depuis la base de données
function getHistoricalWeatherData($locationId, $hours = 24) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT
                temperature as '2 metre temperature',
                humidity as '2 metre relative humidity',
                wind_speed as '10m wind speed',
                0 as 'Total precipitation'
            FROM weather_data
            WHERE location_id = ?
            ORDER BY timestamp DESC
            LIMIT ?
        ");

        $stmt->execute([$locationId, $hours]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Inverser pour avoir l'ordre chronologique
        return array_reverse($data);

    } catch (PDOException $e) {
        error_log("Erreur récupération données historiques: " . $e->getMessage());
        return null;
    }
}

// Fonction améliorée pour obtenir une prédiction ML
function getMLPrediction($locationId, $currentWeatherData) {
    static $mlService = null;

    if ($mlService === null) {
        $mlService = new MLPredictionService();
    }

    // Vérifier si le service ML est disponible
    if (!$mlService->isAvailable()) {
        error_log("Service ML non disponible");
        return null;
    }

    // Récupérer les données historiques
    $historicalData = getHistoricalWeatherData($locationId);

    // Obtenir la prédiction
    $prediction = $mlService->getPrediction($currentWeatherData, $historicalData);

    if ($prediction) {
        // Ajouter le timestamp
        $prediction['prediction_timestamp'] = date('Y-m-d H:i:s');

        // Sauvegarder en base de données
        savePredictionToDatabase($locationId, $prediction);
    }

    return $prediction;
}

// Fonction pour sauvegarder les prédictions ML en base de données
function savePredictionToDatabase($locationId, $prediction) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO weather_predictions
            (id, location_id, predicted_temperature, predicted_humidity, prediction_timestamp)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            predicted_temperature = VALUES(predicted_temperature),
            predicted_humidity = VALUES(predicted_humidity),
            prediction_timestamp = NOW()
        ");

        $stmt->execute([
            generateUUID(),
            $locationId,
            $prediction['predicted_temperature'],
            $prediction['predicted_humidity']
        ]);

        return true;

    } catch (PDOException $e) {
        error_log("Erreur sauvegarde prédiction: " . $e->getMessage());
        return false;
    }
}