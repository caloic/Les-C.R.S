<?php
/**
 * Script de test pour vérifier que le ML fonctionne avec votre site
 * Placez ce fichier dans votre dossier de projet et accédez-y via http://localhost:8888/Les-C.R.S/test_ml_integration.php
 */

// Inclure les dépendances
require_once 'config.php';
require_once 'ml_prediction_service.php';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test ML Integration - MétéoCRS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f7fa;
            padding: 20px;
        }
        .test-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
        }
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">🧪 Test d'intégration Machine Learning</h1>

        <?php
        // Test 1: Vérifier la connexion à l'API ML
        echo '<div class="test-card">';
        echo '<h3>1. Test de connexion à l\'API ML</h3>';

        $mlService = new MLPredictionService('http://localhost:5000');
        $isAvailable = $mlService->isAvailable();

        if ($isAvailable) {
            echo '<span class="status-badge status-success">✅ API ML disponible</span>';
            echo '<p class="mt-2">L\'API Python est accessible sur le port 5000</p>';
        } else {
            echo '<span class="status-badge status-error">❌ API ML non disponible</span>';
            echo '<p class="mt-2">Assurez-vous que l\'API Python est lancée : <code>python weather_prediction_api.py</code></p>';
        }
        echo '</div>';

        // Test 2: Obtenir les informations du modèle
        if ($isAvailable) {
            echo '<div class="test-card">';
            echo '<h3>2. Informations du modèle ML</h3>';

            $modelInfo = $mlService->getModelInfo();
            if ($modelInfo) {
                echo '<span class="status-badge status-success">✅ Modèle chargé</span>';
                echo '<pre>' . json_encode($modelInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
            } else {
                echo '<span class="status-badge status-error">❌ Impossible de récupérer les infos du modèle</span>';
            }
            echo '</div>';
        }

        // Test 3: Faire une prédiction test
        if ($isAvailable) {
            echo '<div class="test-card">';
            echo '<h3>3. Test de prédiction</h3>';

            $testWeatherData = [
                'temperature' => 20,
                'humidity' => 65,
                'wind_speed' => 15,
                'precipitation' => 0
            ];

            echo '<h5>Données d\'entrée :</h5>';
            echo '<pre>' . json_encode($testWeatherData, JSON_PRETTY_PRINT) . '</pre>';

            $prediction = $mlService->getPrediction($testWeatherData);

            if ($prediction) {
                echo '<span class="status-badge status-success">✅ Prédiction réussie</span>';
                echo '<h5 class="mt-3">Résultat :</h5>';
                echo '<pre>' . json_encode($prediction, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';

                echo '<div class="alert alert-info mt-3">';
                echo '<strong>Prédiction à 3 heures :</strong><br>';
                echo '🌡️ Température : ' . round($prediction['predicted_temperature'], 1) . '°C<br>';
                echo '💧 Humidité : ' . round($prediction['predicted_humidity']) . '%';
                echo '</div>';
            } else {
                echo '<span class="status-badge status-error">❌ Échec de la prédiction</span>';
            }
            echo '</div>';
        }

        // Test 4: Vérifier l'intégration avec la base de données
        echo '<div class="test-card">';
        echo '<h3>4. Test d\'intégration avec la base de données</h3>';

        try {
            // Récupérer une ville de test
            $stmt = $pdo->query("SELECT id, name FROM locations LIMIT 1");
            $location = $stmt->fetch();

            if ($location) {
                echo '<p>Test avec la ville : <strong>' . htmlspecialchars($location['name']) . '</strong></p>';

                // Utiliser la fonction makePrediction
                require_once 'functions.php';
                $prediction = makePrediction($location['id'], 22, 70);

                if ($prediction) {
                    echo '<span class="status-badge status-success">✅ Intégration réussie</span>';
                    echo '<pre>' . json_encode($prediction, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';

                    if (isset($prediction['source'])) {
                        if ($prediction['source'] === 'Machine Learning') {
                            echo '<div class="alert alert-success">🤖 Prédiction générée par Machine Learning</div>';
                        } else {
                            echo '<div class="alert alert-warning">📊 Prédiction générée par simulation (fallback)</div>';
                        }
                    }
                } else {
                    echo '<span class="status-badge status-error">❌ Échec de l\'intégration</span>';
                }
            } else {
                echo '<span class="status-badge status-warning">⚠️ Aucune ville dans la base de données</span>';
                echo '<p>Importez d\'abord des données avec csv-import.php</p>';
            }
        } catch (Exception $e) {
            echo '<span class="status-badge status-error">❌ Erreur : ' . $e->getMessage() . '</span>';
        }
        echo '</div>';

        // Résumé
        echo '<div class="test-card">';
        echo '<h3>📊 Résumé</h3>';

        if ($isAvailable) {
            echo '<div class="alert alert-success">';
            echo '<h5>✅ Le Machine Learning est opérationnel !</h5>';
            echo '<p>Votre site utilise maintenant l\'intelligence artificielle pour les prédictions météo.</p>';
            echo '<hr>';
            echo '<p class="mb-0">Prochaines étapes :</p>';
            echo '<ul class="mb-0">';
            echo '<li>Visitez votre site : <a href="index.php">index.php</a></li>';
            echo '<li>Les prédictions ML seront automatiquement utilisées</li>';
            echo '<li>En cas d\'erreur, le système utilisera le fallback automatiquement</li>';
            echo '</ul>';
            echo '</div>';
        } else {
            echo '<div class="alert alert-danger">';
            echo '<h5>❌ Le Machine Learning n\'est pas actif</h5>';
            echo '<p>Pour activer le ML :</p>';
            echo '<ol>';
            echo '<li>Ouvrez un terminal dans le dossier du projet</li>';
            echo '<li>Activez l\'environnement virtuel : <code>source .venv/bin/activate</code></li>';
            echo '<li>Lancez l\'API : <code>python weather_prediction_api.py</code></li>';
            echo '<li>Rafraîchissez cette page</li>';
            echo '</ol>';
            echo '</div>';
        }
        echo '</div>';
        ?>

        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-primary">Retour au site</a>
        </div>
    </div>
</body>
</html>