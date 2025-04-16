<?php
/**
 * Script d'importation du fichier meteo-0025(no filtrer).csv
 * Version adaptée pour le nouveau format de données météorologiques
 */

// Inclure la configuration
require_once 'config.php';

// Configuration
$csvFile = 'meteo-0025_clean.csv';
$batchSize = 5000; // Nombre de lignes à traiter par lot
$maxRows = 0;  // 0 = importer toutes les lignes
$startTime = microtime(true);

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
 * Fonction pour afficher un message formaté
 */
function displayMessage($message, $type = 'info') {
    $bgColor = ($type == 'error') ? '#f8d7da' : (($type == 'warning') ? '#fff3cd' : '#d4edda');
    $textColor = ($type == 'error') ? '#721c24' : (($type == 'warning') ? '#856404' : '#155724');

    echo "<div style='background-color: {$bgColor}; color: {$textColor}; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
    echo $message;
    echo "</div>";
    ob_flush();
    flush();
}

/**
 * Vérifie si le fichier CSV existe et peut être lu
 */
function checkCSVFile($filepath) {
    if (!file_exists($filepath)) {
        displayMessage("Le fichier {$filepath} n'existe pas.", "error");
        return false;
    }

    if (!is_readable($filepath)) {
        displayMessage("Le fichier {$filepath} n'est pas lisible.", "error");
        return false;
    }

    return true;
}

/**
 * Tronque les tables pour un nouveau import
 */
function truncateTables() {
    global $pdo;

    try {
        // Désactiver les contraintes de clé étrangère
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Tronquer les tables
        $pdo->exec("TRUNCATE TABLE weather_predictions");
        $pdo->exec("TRUNCATE TABLE weather_data");
        $pdo->exec("TRUNCATE TABLE locations");

        // Réactiver les contraintes de clé étrangère
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        displayMessage("Les tables ont été vidées avec succès.");
        return true;
    } catch (PDOException $e) {
        displayMessage("Erreur lors de la suppression des données : " . $e->getMessage(), "error");
        return false;
    }
}

/**
 * Obtient le département et la région en fonction des coordonnées GPS
 * Dans un cas réel, cela pourrait être implémenté avec une API géographique
 */
function getLocationInfoFromCoordinates($latitude, $longitude) {
    // Valeurs par défaut - dans une version réelle, vous utiliseriez une API
    // comme l'API Gouvernementale française ou OpenStreetMap pour obtenir ces informations
    $regions = ['Île-de-France', 'Bretagne', 'Normandie', 'Occitanie', 'Auvergne-Rhône-Alpes', 'Grand Est'];
    $departments = [
        'Paris' => '75',
        'Ille-et-Vilaine' => '35',
        'Finistère' => '29',
        'Seine-Maritime' => '76',
        'Haute-Garonne' => '31',
        'Rhône' => '69',
        'Bas-Rhin' => '67'
    ];

    // Logique simplifiée basée sur les coordonnées
    // En réalité, vous feriez une recherche géographique précise
    $departmentIndex = abs(intval($latitude * 10 + $longitude)) % count(array_keys($departments));
    $departmentName = array_keys($departments)[$departmentIndex];
    $departmentNumber = $departments[$departmentName];

    $regionIndex = abs(intval($latitude * 5 + $longitude)) % count($regions);
    $regionName = $regions[$regionIndex];

    return [
        'department_name' => $departmentName,
        'department_number' => $departmentNumber,
        'region_name' => $regionName,
        'region_geojson_name' => $regionName
    ];
}

/**
 * Importe les données du fichier CSV dans la base de données
 */
function importCSV($filepath, $batchSize, $maxRows) {
    global $pdo;

    // Augmenter la durée maximale d'exécution pour les gros fichiers
    set_time_limit(3600); // 1 heure

    // Définir une taille de mémoire plus importante
    ini_set('memory_limit', '512M');

    // Vérifier le fichier
    if (!checkCSVFile($filepath)) {
        return false;
    }

    // Calculer le nombre total de lignes (pour l'affichage de progression)
    $totalLines = 0;
    $lineCounter = fopen($filepath, "r");
    while(!feof($lineCounter)) {
        fgets($lineCounter);
        $totalLines++;
    }
    fclose($lineCounter);
    $totalLines--; // Enlever l'en-tête

    // Afficher des informations sur l'import
    displayMessage("Début de l'importation du fichier {$filepath}");
    displayMessage("Nombre total de lignes à importer : environ {$totalLines}");
    displayMessage("Taille de lot : {$batchSize} lignes");

    // Ouvrir le fichier
    $handle = fopen($filepath, "r");
    if (!$handle) {
        displayMessage("Impossible d'ouvrir le fichier.", "error");
        return false;
    }

    // Lire l'en-tête
    $header = fgetcsv($handle, 0, ";"); // Utilisation du point-virgule comme séparateur
    if (!$header) {
        displayMessage("Impossible de lire l'en-tête du fichier CSV.", "error");
        fclose($handle);
        return false;
    }

    // Statistiques
    $stats = [
        'total' => 0,
        'inserted' => 0,
        'skipped' => 0,
        'errors' => 0
    ];

    // Préparer les requêtes
    $insertLocation = $pdo->prepare("
        INSERT INTO locations (
            id, name, latitude, longitude, insee_code, city_code, zip_code,
            department_name, department_number, region_name, region_geojson_name
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $insertWeather = $pdo->prepare("
        INSERT INTO weather_data (
            id, location_id, temperature, humidity, wind_speed, weather_condition
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");

    $insertPrediction = $pdo->prepare("
        INSERT INTO weather_predictions (
            id, location_id, predicted_temperature, predicted_humidity
        ) VALUES (?, ?, ?, ?)
    ");

    // Garder une trace des emplacements déjà traités pour éviter les doublons
    $processedLocations = [];

    // Commencer une transaction
    $pdo->beginTransaction();

    try {
        $rowCount = 0;
        $batchCount = 0;
        $lastProgressUpdate = 0;

        // Lire les données ligne par ligne
        while (($data = fgetcsv($handle, 0, ";")) !== FALSE) { // Utilisation du point-virgule comme séparateur
            // Vérifier si on a atteint le nombre maximum de lignes
            if ($maxRows > 0 && $rowCount >= $maxRows) {
                break;
            }

            // Compteur de lignes
            $rowCount++;
            $stats['total']++;

            // Associer les valeurs aux noms des colonnes
            $row = array_combine($header, $data);

            try {
                // Extraire les coordonnées (format "latitude, longitude")
                $position = explode(',', $row['Position']);
                $latitude = isset($position[0]) ? trim($position[0]) : 0;
                $longitude = isset($position[1]) ? trim($position[1]) : 0;

                // Vérifier si les coordonnées sont valides
                if (!is_numeric($latitude) || !is_numeric($longitude)) {
                    $stats['skipped']++;
                    continue;
                }

                // Créer une clé unique pour cette position
                $locationKey = "{$latitude}_{$longitude}";

                // Si nous avons déjà traité cet emplacement, passer à la suite
                if (isset($processedLocations[$locationKey])) {
                    // Utiliser l'ID d'emplacement existant pour les données météo
                    $locationId = $processedLocations[$locationKey];
                } else {
                    // Extraire ou générer les informations nécessaires pour l'emplacement
                    $commune = !empty($row['Commune']) ? $row['Commune'] : "Ville {$latitude}, {$longitude}";
                    $code_commune = !empty($row['code_commune']) ? $row['code_commune'] : "CODE" . substr(md5($locationKey), 0, 6);

                    // Obtenir des informations géographiques supplémentaires
                    $geoInfo = getLocationInfoFromCoordinates($latitude, $longitude);

                    // Générer un UUID pour l'ID
                    $locationId = generateUUID();

                    // Insérer la localisation
                    $insertLocation->execute([
                        $locationId,
                        $commune,
                        $latitude,
                        $longitude,
                        $code_commune,     // insee_code (simulé)
                        $code_commune,     // city_code (simulé)
                        substr($code_commune, 0, 5), // zip_code (simulé)
                        $geoInfo['department_name'],
                        $geoInfo['department_number'],
                        $geoInfo['region_name'],
                        $geoInfo['region_geojson_name']
                    ]);

                    // Mémoriser cet emplacement
                    $processedLocations[$locationKey] = $locationId;
                }

                // Extraire les données météo
                $temperature = isset($row['2 metre temperature']) && is_numeric($row['2 metre temperature'])
                    ? floatval($row['2 metre temperature'])
                    : null;

                $humidity = isset($row['2 metre relative humidity']) && is_numeric($row['2 metre relative humidity'])
                    ? floatval($row['2 metre relative humidity'])
                    : null;

                $windSpeed = isset($row['10m wind speed']) && is_numeric($row['10m wind speed'])
                    ? floatval($row['10m wind speed'])
                    : null;

                // Vérifier si nous avons au moins la température
                if ($temperature !== null) {
                    // Déterminer une condition météo approximative basée sur les données
                    $condition = 'Inconnu';
                    if (isset($row['Total precipitation']) && floatval($row['Total precipitation']) > 5) {
                        $condition = 'Pluie';
                    } elseif (isset($row['Total precipitation']) && floatval($row['Total precipitation']) > 0) {
                        $condition = 'Pluie légère';
                    } elseif ($temperature > 25) {
                        $condition = 'Ensoleillé';
                    } elseif ($temperature > 15) {
                        $condition = 'Partiellement nuageux';
                    } elseif ($temperature > 5) {
                        $condition = 'Nuageux';
                    } elseif ($temperature <= 0) {
                        $condition = 'Neige légère';
                    } else {
                        $condition = 'Couvert';
                    }

                    // Générer un ID pour les données météo
                    $weatherId = generateUUID();

                    // Insérer les données météo
                    $insertWeather->execute([
                        $weatherId,
                        $locationId,
                        $temperature,
                        $humidity !== null ? $humidity : rand(30, 95),
                        $windSpeed !== null ? $windSpeed * 3.6 : rand(0, 50), // Conversion en km/h
                        $condition
                    ]);

                    // Si nous avons une prévision météo (temperature max)
                    if (isset($row['Maximum temperature at 2 metres']) && is_numeric($row['Maximum temperature at 2 metres'])) {
                        $predictionId = generateUUID();
                        $predictedTemp = floatval($row['Maximum temperature at 2 metres']);
                        $predictedHumidity = $humidity !== null ? min(100, $humidity * 0.95) : rand(30, 95);

                        $insertPrediction->execute([
                            $predictionId,
                            $locationId,
                            $predictedTemp,
                            $predictedHumidity
                        ]);
                    }

                    $stats['inserted']++;
                } else {
                    $stats['skipped']++;
                }

                // Incrémenter le compteur de lot
                $batchCount++;

                // Valider la transaction et en commencer une nouvelle tous les X enregistrements
                if ($batchCount >= $batchSize) {
                    $pdo->commit();
                    $pdo->beginTransaction();
                    $batchCount = 0;

                    // Afficher la progression tous les 1000 enregistrements
                    if ($stats['inserted'] - $lastProgressUpdate >= 1000) {
                        $percentage = round(($stats['inserted'] / $totalLines) * 100, 1);
                        $currentTime = microtime(true);
                        $elapsedTime = round($currentTime - $GLOBALS['startTime'], 1);
                        $estimatedTotal = ($totalLines / $stats['inserted']) * $elapsedTime;
                        $remainingTime = round($estimatedTotal - $elapsedTime, 1);

                        displayMessage("Progression : {$stats['inserted']} / {$totalLines} ({$percentage}%) - Temps écoulé : {$elapsedTime}s - Temps restant estimé : {$remainingTime}s");
                        $lastProgressUpdate = $stats['inserted'];
                    }
                }

            } catch (PDOException $e) {
                $stats['errors']++;

                // Si l'erreur est une violation de contrainte d'unicité, c'est normal
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $stats['skipped']++;
                } else {
                    displayMessage("Erreur à la ligne {$rowCount} : " . $e->getMessage(), "error");
                }
            }
        }

        // Valider la dernière transaction
        if ($batchCount > 0) {
            $pdo->commit();
        }

        fclose($handle);

        // Afficher les statistiques finales
        $endTime = microtime(true);
        $duration = round($endTime - $GLOBALS['startTime'], 2);

        displayMessage("
            <h4>Importation terminée en {$duration} secondes</h4>
            <ul>
                <li>Lignes traitées : {$stats['total']}</li>
                <li>Données météo importées : {$stats['inserted']}</li>
                <li>Lignes ignorées : {$stats['skipped']}</li>
                <li>Erreurs : {$stats['errors']}</li>
            </ul>
        ");

        return true;

    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $pdo->rollBack();
        displayMessage("Erreur globale : " . $e->getMessage(), "error");
        fclose($handle);
        return false;
    }
}

// Page HTML
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import des données - MétéoCRS</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- CSS personnalisé -->
    <link rel="stylesheet" href="style.css">

    <style>
        body {
            background-color: #1d2c3d;
            color: white;
            padding-bottom: 50px;
        }
        .import-card {
            background-color: #3D5974;
            border-radius: 8px;
            color: white;
            margin-top: 20px;
            padding: 20px;
        }
        .btn-primary {
            background-color: #4A90E2;
            border-color: #4A90E2;
        }
        .navbar {
            background-color: #1d2c3d !important;
        }
        .logo-img {
            height: 80px;
        }
    </style>
</head>
<body>
<!-- Header existant -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="assets/img/logo.png" alt="Météo C.R.S" class="logo-img">
        </a>

        <div class="d-flex order-lg-2">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

        <div class="collapse navbar-collapse order-lg-1" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="#">Import des données</a>
                </li>
            </ul>
        </div>
    </div>
</nav> 

<div class="container">
    <div class="import-card">
        <h2><i class="fas fa-database me-2"></i>Importation du fichier CSV</h2>
        <p>Ce script va importer les données du fichier <strong>meteo-0025.csv</strong> dans la base de données.</p>

        <form method="post" action="">
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="truncate" name="truncate" value="1" checked>
                <label class="form-check-label" for="truncate">Vider les tables avant l'importation</label>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>Attention : L'importation complète peut prendre plusieurs minutes. Ne fermez pas cette page pendant le processus.
            </div>

            <button type="submit" name="import" class="btn btn-primary">
                <i class="fas fa-file-import me-2"></i>Lancer l'importation
            </button>
        </form>

        <div class="mt-4">
            <?php
            // Traitement de l'importation
            if (isset($_POST['import'])) {
                // Vider les tables si demandé
                $truncate = isset($_POST['truncate']) ? (bool)$_POST['truncate'] : false;

                if ($truncate) {
                    if (!truncateTables()) {
                        exit;
                    }
                }

                // Importer les données (0 = toutes les lignes)
                importCSV($csvFile, $batchSize, 0);
            }
            ?>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>