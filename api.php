<?php

// Inclure les fonctions
require_once 'functions.php';

// Configuration des en-têtes CORS et JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// Traiter la requête
$action = isset($_GET['action']) ? $_GET['action'] : '';
$response = ['success' => false, 'message' => 'Action non reconnue'];

switch ($action) {
    // Récupérer les données météo pour une localisation (recherche locale)
    case 'getWeather':
        $location = isset($_GET['location']) ? $_GET['location'] : '';

        if (empty($location)) {
            $response = ['success' => false, 'message' => 'Localisation non spécifiée'];
        } else {
            // Utiliser la fonction qui ne recherche que dans la base de données
            $weatherData = getLocalWeatherData($location);

            if (isset($weatherData['error'])) {
                $response = ['success' => false, 'message' => $weatherData['error']];
            } else {
                $response = ['success' => true, 'data' => $weatherData];
            }
        }
        break;

    // Récupérer toutes les localisations
    case 'getLocations':
        $locations = getAllLocations();
        $response = ['success' => true, 'data' => $locations];
        break;

    // Récupérer les données météo pour un ID de localisation
    case 'getWeatherById':
        $locationId = isset($_GET['id']) ? $_GET['id'] : '';

        if (empty($locationId)) {
            $response = ['success' => false, 'message' => 'ID de localisation non spécifié'];
        } else {
            $weatherData = getWeatherForLocation($locationId);

            if (!$weatherData) {
                $response = ['success' => false, 'message' => 'Données météo non trouvées'];
            } else {
                // Récupérer également la prédiction
                $prediction = getPredictionForLocation($locationId);

                $response = [
                    'success' => true,
                    'data' => [
                        'weather' => $weatherData,
                        'prediction' => $prediction
                    ]
                ];
            }
        }
        break;

    // Récupérer les prévisions complètes pour une localisation
    case 'getForecast':
        $location = isset($_GET['location']) ? $_GET['location'] : '';

        if (empty($location)) {
            $response = ['success' => false, 'message' => 'Localisation non spécifiée'];
        } else {
            // Utiliser la fonction qui ne recherche que dans la base de données
            $weatherData = getLocalWeatherData($location);

            if (isset($weatherData['error'])) {
                $response = ['success' => false, 'message' => $weatherData['error']];
            } else {
                $response = [
                    'success' => true,
                    'data' => [
                        'location' => $weatherData['location'],
                        'current' => $weatherData['current'],
                        'forecast' => $weatherData['forecast']
                    ]
                ];
            }
        }
        break;

    // Action par défaut: retourner une erreur
    default:
        $response = [
            'success' => false,
            'message' => 'Action non valide',
            'valid_actions' => [
                'getWeather' => 'Récupérer les données météo pour une localisation (paramètre: location)',
                'getLocations' => 'Récupérer toutes les localisations enregistrées',
                'getWeatherById' => 'Récupérer les données météo pour un ID de localisation (paramètre: id)',
                'getForecast' => 'Récupérer les prévisions complètes pour une localisation (paramètre: location)'
            ]
        ];
        break;
}

// Envoyer la réponse JSON
echo json_encode($response);