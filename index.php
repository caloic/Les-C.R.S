<?php

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

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Notre CSS -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="animations.css">
</head>
<body>
<!-- Header transparent -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="#">
            <span class="text-white fw-bold fs-4">Météo<span style="color: var(--color-accent);">CRS</span></span>
        </a>

        <div class="d-flex order-lg-2">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

        <div class="collapse navbar-collapse order-lg-1" id="navbarNav">
            <form id="searchForm" class="mx-auto position-relative">
                <input id="locationInput" class="form-control" type="search" placeholder="Rechercher une ville..." aria-label="Search">
                <button id="searchBtn" type="button" class="btn"><i class="fas fa-search"></i></button>
                <div id="autocompleteContainer" class="autocomplete-container d-none"></div>
            </form>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="#forecast-section">Prévisions</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#map-section">Carte</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#history-section">Historique</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section avec fond d'image -->
<div class="hero-section">
    <div id="weatherAnimationContainer" class="weather-animation-container"></div>
    <div class="container weather-container">
        <!-- Message d'alerte pour les erreurs -->
        <div id="alertMessage" class="alert alert-danger d-none" role="alert"></div>

        <!-- Informations principales -->
        <h1 id="currentLocation" class="location-title"><?php echo isset($weatherData['location']) ? escape($weatherData['location']['name']) : 'Paris'; ?></h1>
        <div id="currentDate" class="date-info">Update As Of <?php echo date('g:i A'); ?></div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Météo actuelle -->
                <div class="current-weather">
                    <div class="d-flex align-items-center">
                        <div id="temperature" class="temperature-display"><?php echo isset($weatherData['current']) ? $weatherData['current']['temp_c'] : '24'; ?></div>
                    </div>
                    <div id="weatherCondition" class="condition-text"><?php echo isset($weatherData['current']) ? $weatherData['current']['condition']['text'] : 'Ensoleillé'; ?></div>
                </div>

                <!-- Détails météo en cartes -->
                <div class="weather-row">
                    <div class="weather-detail-card">
                        <i class="fas fa-wind"></i>
                        <div class="weather-detail-text">
                            <span class="detail-value" id="windSpeed"><?php echo isset($weatherData['current']) ? $weatherData['current']['wind_kph'] : '12'; ?> km/h</span>
                            <span class="detail-label">Vent</span>
                        </div>
                    </div>

                    <div class="weather-detail-card">
                        <i class="fas fa-tint"></i>
                        <div class="weather-detail-text">
                            <span class="detail-value" id="humidity"><?php echo isset($weatherData['current']) ? $weatherData['current']['humidity'] : '65'; ?>%</span>
                            <span class="detail-label">Humidité</span>
                        </div>
                    </div>

                    <div class="weather-detail-card">
                        <i class="fas fa-compress-alt"></i>
                        <div class="weather-detail-text">
                            <span class="detail-value" id="pressure"><?php echo isset($weatherData['current']) ? $weatherData['current']['pressure_mb'] : '1013'; ?></span>
                            <span class="detail-label">Pression</span>
                        </div>
                    </div>

                    <div class="weather-detail-card">
                        <i class="fas fa-eye"></i>
                        <div class="weather-detail-text">
                            <span class="detail-value" id="visibility"><?php echo isset($weatherData['current']) ? $weatherData['current']['vis_km'] : '10'; ?> km</span>
                            <span class="detail-label">Visibilité</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mt-4 mt-lg-0">
                <!-- Carte de prévision IA -->
                <div class="weather-prediction-card">
                    <div class="weather-icon-large">
                        <?php
                        $condition = isset($weatherData['current']) ? strtolower($weatherData['current']['condition']['text']) : '';
                        if (strpos($condition, 'soleil') !== false || strpos($condition, 'ensoleill') !== false) {
                            echo '<img src="assets/img/icons/sunny.svg" alt="Ensoleillé" width="120">';
                        } elseif (strpos($condition, 'nuage') !== false || strpos($condition, 'nuageux') !== false) {
                            echo '<img src="assets/img/icons/partly-cloudy.svg" alt="Partiellement nuageux" width="120">';
                        } elseif (strpos($condition, 'pluie') !== false) {
                            echo '<img src="assets/img/icons/rainy.svg" alt="Pluie" width="120">';
                        } else {
                            // Icône par défaut
                            echo '<img src="assets/img/icons/partly-cloudy.svg" alt="Météo" width="120">';
                        }
                        ?>
                    </div>
                    <div class="text-center mb-4">
                        <span class="badge bg-light text-dark rounded-pill px-3 py-2">Today</span>
                    </div>

                    <div class="prediction-temp text-center mb-3">
                        <span class="display-4 fw-bold" id="aiTemperature">
                            <?php
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
                            echo $prediction ? round($prediction['predicted_temperature'], 1) : '25';
                            ?>°C
                        </span>
                    </div>

                    <div class="prediction-range text-center mb-4">
                        <span>
                            <?php echo isset($weatherData['current']) ? $weatherData['current']['temp_c'] : '23'; ?>°C -
                            <?php echo isset($weatherData['forecast']['forecastday'][0]['day']['maxtemp_c']) ? $weatherData['forecast']['forecastday'][0]['day']['maxtemp_c'] : '31'; ?>°C
                        </span>
                    </div>

                    <div class="d-flex justify-content-between mt-3">
                        <div class="text-center">
                            <i class="fas fa-wind me-2"></i>
                            <span><?php echo isset($weatherData['current']) ? $weatherData['current']['wind_kph'] : '12'; ?> km/h</span>
                        </div>
                        <div class="text-center">
                            <i class="fas fa-tint me-2"></i>
                            <span id="aiHumidity"><?php echo $prediction ? round($prediction['predicted_humidity']) : '60'; ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Forecast Section -->
<div id="forecast-section" class="section-container">
    <div class="container">
        <h2 class="section-title">Prévisions horaires</h2>

        <div class="forecast-scroll" id="forecastContainer">
            <?php if (isset($weatherData['forecast']) && isset($weatherData['forecast']['forecastday'])) : ?>
                <?php
                // Premier jour actif
                $firstDay = $weatherData['forecast']['forecastday'][0];
                $date = new DateTime($firstDay['date']);
                $joursSemaine = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
                $jourSemaine = $joursSemaine[$date->format('w')];
                ?>
                <div class="forecast-card active">
                    <div class="forecast-time">
                        <span class="forecast-day"><?php echo $jourSemaine; ?></span>
                        <span class="forecast-hour">4:00PM</span>
                    </div>
                    <div class="forecast-icon">
                        <img src="<?php echo $firstDay['day']['condition']['icon']; ?>" alt="<?php echo $firstDay['day']['condition']['text']; ?>" width="64">
                    </div>
                    <div class="forecast-temp"><?php echo round($firstDay['day']['avgtemp_c']); ?>°</div>
                    <div class="forecast-high-low"><?php echo round($firstDay['day']['maxtemp_c']); ?>°</div>
                    <div class="forecast-detail">
                        <span><i class="fas fa-wind"></i><?php echo round($firstDay['day']['maxwind_kph']); ?> km/h</span>
                        <span><i class="fas fa-tint"></i><?php echo round($firstDay['day']['avghumidity']); ?>%</span>
                    </div>
                </div>

                <?php foreach (array_slice($weatherData['forecast']['forecastday'], 0, 4) as $index => $day) :
                    $date = new DateTime($day['date']);
                    $jourSemaine = $joursSemaine[$date->format('w')];
                    $hours = ["5:00PM", "6:00PM", "7:00PM"];
                    $hour = isset($hours[$index]) ? $hours[$index] : "8:00PM";
                    ?>
                    <div class="forecast-card">
                        <div class="forecast-time">
                            <span class="forecast-day"><?php echo $jourSemaine; ?></span>
                            <span class="forecast-hour"><?php echo $hour; ?></span>
                        </div>
                        <div class="forecast-icon">
                            <img src="<?php echo $day['day']['condition']['icon']; ?>" alt="<?php echo $day['day']['condition']['text']; ?>" width="64">
                        </div>
                        <div class="forecast-temp"><?php echo round($day['day']['avgtemp_c']); ?>°</div>
                        <div class="forecast-high-low"><?php echo round($day['day']['maxtemp_c']); ?>°</div>
                        <div class="forecast-detail">
                            <span><i class="fas fa-wind"></i><?php echo round($day['day']['maxwind_kph']); ?> km/h</span>
                            <span><i class="fas fa-tint"></i><?php echo round($day['day']['avghumidity']); ?>%</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="text-center py-4 w-100">
                    <p>Aucune prévision disponible pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Map Section -->
<div id="map-section" class="section-container">
    <div class="container">
        <h2 class="section-title">Carte météorologique</h2>
        <div class="map-container">
            <div id="map"></div>
        </div>
        <!-- Legend -->
        <div class="d-flex mt-3">
            <div class="me-4"><i class="fas fa-sun text-warning me-2"></i> Ensoleillé</div>
            <div class="me-4"><i class="fas fa-cloud text-info me-2"></i> Nuageux</div>
            <div class="me-4"><i class="fas fa-cloud-rain text-primary me-2"></i> Pluie</div>
            <div class="me-4"><i class="fas fa-snowflake text-light bg-info p-1 rounded me-2"></i> Neige</div>
        </div>
    </div>
</div>

<!-- Historical Data Section -->
<div id="history-section" class="section-container">
    <div class="container">
        <h2 class="section-title">Statistiques historiques</h2>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-title">Température moyenne</div>
                <div class="stat-value">21°C</div>
                <div class="stat-info">+2.3°C par rapport à la normale</div>
            </div>

            <div class="stat-card">
                <div class="stat-title">Pluviométrie moyenne</div>
                <div class="stat-value">5.2 mm</div>
                <div class="stat-info">-3.1 mm par rapport à la normale</div>
            </div>

            <div class="stat-card">
                <div class="stat-title">Probabilité de pluie</div>
                <div class="stat-value">35%</div>
                <div class="stat-info">-10% par rapport au mois dernier</div>
            </div>

            <div class="stat-card">
                <div class="stat-title">Jours d'ensoleillement</div>
                <div class="stat-value">22</div>
                <div class="stat-info">+4 jours par rapport à la normale</div>
            </div>
        </div>

        <!-- Historical Chart -->
        <div class="chart-container">
            <h5 class="mb-3">Évolution des températures</h5>
            <div style="height: 350px;">
                <canvas id="historicalChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-md-6 footer-col">
                <div class="mb-3">
                    <span class="text-white fw-bold fs-4">Météo<span style="color: var(--color-accent);">CRS</span></span>
                </div>
                <p class="mb-4">Les prévisions météo les plus précises pour planifier vos journées en toute confiance.</p>
            </div>

            <div class="col-lg-3 col-md-6 footer-col">
                <h4 class="footer-title">Navigation</h4>
                <ul class="footer-links">
                    <li><a href="#">Accueil</a></li>
                    <li><a href="#forecast-section">Prévisions</a></li>
                    <li><a href="#map-section">Carte</a></li>
                    <li><a href="#history-section">Historique</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6 footer-col">
                <h4 class="footer-title">À propos</h4>
                <ul class="footer-links">
                    <li><a href="#">L'équipe</a></li>
                    <li><a href="#">Notre technologie</a></li>
                    <li><a href="#">Nous contacter</a></li>
                </ul>
            </div>

            <div class="col-lg-2 col-md-6 footer-col">
                <h4 class="footer-title">Suivez-nous</h4>
                <div class="footer-social">
                    <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; 2025 MétéoCRS. Tous droits réservés.</p>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Script JavaScript -->
<script src="script.js"></script>

<!-- Initialiser les graphiques -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Données pour le graphique historique
        const historicalCtx = document.getElementById('historicalChart').getContext('2d');
        const historicalData = {
            labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep'],
            datasets: [
                {
                    label: 'Température max (°C)',
                    backgroundColor: 'rgba(91, 93, 206, 0.2)',
                    borderColor: 'rgba(91, 93, 206, 1)',
                    pointBackgroundColor: 'rgba(91, 93, 206, 1)',
                    data: [8, 10, 15, 19, 23, 27, 30, 29, 25]
                },
                {
                    label: 'Température min (°C)',
                    backgroundColor: 'rgba(113, 128, 150, 0.2)',
                    borderColor: 'rgba(113, 128, 150, 1)',
                    pointBackgroundColor: 'rgba(113, 128, 150, 1)',
                    data: [2, 3, 6, 9, 12, 16, 18, 17, 14]
                }
            ]
        };

        new Chart(historicalCtx, {
            type: 'line',
            data: historicalData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 35
                    }
                }
            }
        });
    });
</script>
</body>
</html>