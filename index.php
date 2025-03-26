<?php
/**
 * Page principale du site météo
 * Version exacte suivant la maquette Figma
 */

// Inclure les fonctions
require_once 'functions.php';

// Ville par défaut pour l'affichage initial
$defaultCity = 'Paris';

// Récupérer les données météo pour la ville par défaut
$weatherData = getWeatherData($defaultCity);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MétéoCRS</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <!-- Notre CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
<!-- Header exactement comme sur la maquette Figma -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="#">
            <img src="assets/img/logo.png" alt="Météo C.R.S" class="logo-img">
        </a>

        <div class="d-flex order-lg-2">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

        <div class="collapse navbar-collapse order-lg-1" id="navbarNav">
            <form id="searchForm" class="mx-auto position-relative">
                <input id="locationInput" class="form-control" type="search" placeholder="Rechercher une ville" aria-label="Search">
                <button id="searchBtn" type="button" class="btn"><i class="fas fa-search"></i></button>
            </form>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="#forecast-section">Prévisions</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#map-section">Carte</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Content - exactement comme la maquette Figma -->
<div class="main-container">
    <!-- Message d'alerte pour les erreurs -->
    <div id="alertMessage" class="alert alert-danger d-none" role="alert"></div>

    <!-- Section météo actuelle exactement comme la maquette Figma -->
    <div class="weather-card">
        <div class="weather-info">
            <h2 id="currentLocation">Météo <?php echo isset($weatherData['location']) ? escape($weatherData['location']['name']) : 'Ville'; ?></h2>
            <div id="currentDate" class="date-info"><?php echo formatDateFr(date('Y-m-d')); ?></div>

            <div id="temperature" class="temperature-display"><?php echo isset($weatherData['current']) ? $weatherData['current']['temp_c'] : 'Temperature'; ?></div>

            <div id="weatherCondition" class="condition-text"><?php echo isset($weatherData['current']) ? $weatherData['current']['condition']['text'] : 'WeatherCondition'; ?></div>

            <div class="weather-detail">
                <span>Humidité: </span>
                <span id="humidity" class="fw-bold"><?php echo isset($weatherData['current']) ? $weatherData['current']['humidity'] . '%' : 'Humidité'; ?></span>
            </div>

            <div class="weather-detail">
                <span>Vent: </span>
                <span id="windSpeed" class="fw-bold"><?php echo isset($weatherData['current']) ? $weatherData['current']['wind_kph'] . ' km/h' : 'Vent'; ?></span>
            </div>

            <div class="weather-detail">
                <span>Pression: </span>
                <span id="pressure" class="fw-bold"><?php echo isset($weatherData['current']) ? $weatherData['current']['pressure_mb'] . ' hPa' : 'Pression'; ?></span>
            </div>

            <div class="weather-detail">
                <span>Visibilité: </span>
                <span id="visibility" class="fw-bold"><?php echo isset($weatherData['current']) ? $weatherData['current']['vis_km'] . ' km' : 'Visibilité'; ?></span>
            </div>
        </div>

        <div class="prediction-section">
            <h5>Prévision IA</h5>
            <?php
            // Récupération de la prédiction
            $prediction = null;
            if (isset($weatherData['location'])) {
                $locations = getAllLocations();
                foreach ($locations as $loc) {
                    if ($loc['name'] == $weatherData['location']['name']) {
                        $prediction = getPredictionForLocation($loc['id']);
                        break;
                    }
                }
            }
            ?>

            <div class="prediction-values">
                <div class="prediction-value">
                    <span>Température prévue: </span>
                    <span id="aiTemperature" class="fw-bold">
                            <?php echo $prediction ? round($prediction['predicted_temperature'], 1) . '°C' : '--°C'; ?>
                        </span>
                </div>

                <div class="prediction-value">
                    <span>Humidité prévue: </span>
                    <span id="aiHumidity" class="fw-bold">
                            <?php echo $prediction ? round($prediction['predicted_humidity'], 1) . '%' : '--%'; ?>
                        </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section des prévisions -->
<div id="forecast-section" class="container mt-4">
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Prévisions pour les prochains jours</h5>
        </div>
        <div class="card-body">
            <div class="row" id="forecastContainer">
                <?php if (isset($weatherData['forecast']) && isset($weatherData['forecast']['forecastday'])) : ?>
                    <?php foreach (array_slice($weatherData['forecast']['forecastday'], 1) as $day) : ?>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card prediction-card h-100">
                                <div class="card-body text-center">
                                    <div class="forecast-day"><?php echo formatDateFr($day['date']); ?></div>
                                    <img src="<?php echo $day['day']['condition']['icon']; ?>" alt="<?php echo $day['day']['condition']['text']; ?>" class="my-2" width="64">
                                    <div class="mb-2"><?php echo $day['day']['condition']['text']; ?></div>
                                    <div class="d-flex justify-content-around">
                                        <div><i class="fas fa-temperature-high"></i> <?php echo $day['day']['maxtemp_c']; ?>°C</div>
                                        <div><i class="fas fa-temperature-low"></i> <?php echo $day['day']['mintemp_c']; ?>°C</div>
                                    </div>
                                    <div class="mt-2">
                                        <i class="fas fa-tint"></i> <?php echo $day['day']['avghumidity']; ?>%
                                        <i class="fas fa-wind ms-2"></i> <?php echo $day['day']['maxwind_kph']; ?> km/h
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="col-12 text-center py-4">
                        <p>Aucune prévision disponible pour le moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Section carte -->
<div id="map-section" class="container">
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-map me-2"></i>Carte météorologique</h5>
        </div>
        <div class="card-body">
            <div id="map" style="height: 400px;"></div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Script JavaScript -->
<script src="script.js"></script>
</body>
</html>