/**
 * Script pour le site météo version Figma
 * Version complète avec toutes les fonctionnalités
 */

// Attendre que le DOM soit chargé
document.addEventListener('DOMContentLoaded', function() {
    // Éléments du DOM
    const locationInput = document.getElementById('locationInput');
    const searchBtn = document.getElementById('searchBtn');
    const currentLocation = document.getElementById('currentLocation');
    const temperature = document.getElementById('temperature');
    const weatherCondition = document.getElementById('weatherCondition');
    const humidity = document.getElementById('humidity');
    const windSpeed = document.getElementById('windSpeed');
    const pressure = document.getElementById('pressure');
    const visibility = document.getElementById('visibility');
    const aiTemperature = document.getElementById('aiTemperature');
    const aiHumidity = document.getElementById('aiHumidity');
    const alertMessage = document.getElementById('alertMessage');
    const forecastContainer = document.getElementById('forecastContainer');

    // Événements
    searchBtn.addEventListener('click', searchWeather);
    locationInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchWeather();
            e.preventDefault(); // Empêcher le rechargement de la page
        }
    });

    // Initialisation de la carte
    let map;
    let currentMarker;

    initMap();

    /**
     * Initialise la carte Leaflet
     */
    function initMap() {
        // Vérifier si l'élément map existe
        const mapElement = document.getElementById('map');
        if (!mapElement) return;

        // Initialiser la carte
        map = L.map('map').setView([48.8566, 2.3522], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
    }

    /**
     * Recherche les données météo pour une localisation
     */
    function searchWeather() {
        const location = locationInput.value.trim();

        if (location === '') {
            showAlert('Veuillez entrer une localisation');
            return;
        }

        // Réinitialiser l'alerte
        hideAlert();

        // Afficher un état de chargement
        showLoading();

        // Appeler l'API pour récupérer les données
        fetch(`api.php?action=getWeather&location=${encodeURIComponent(location)}`)
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    showAlert(data.message || 'Erreur lors de la récupération des données météo');
                    hideLoading();
                    return;
                }

                // Mettre à jour l'interface avec les données
                updateWeatherUI(data.data);
                hideLoading();
            })
            .catch(error => {
                console.error('Erreur:', error);
                showAlert('Erreur de connexion au serveur');
                hideLoading();
            });
    }

    /**
     * Met à jour l'interface avec les données météo de l'API
     */
    function updateWeatherUI(data) {
        // Mettre à jour les informations de localisation
        currentLocation.textContent = `Météo ${data.location.name}`;

        // Mettre à jour les données météo actuelles
        temperature.textContent = `${data.current.temp_c}`;
        weatherCondition.textContent = `${data.current.condition.text}`;
        humidity.textContent = `${data.current.humidity}%`;
        windSpeed.textContent = `${data.current.wind_kph} km/h`;
        pressure.textContent = `${data.current.pressure_mb} hPa`;
        visibility.textContent = `${data.current.vis_km} km`;

        // Mettre à jour la carte
        if (map) {
            updateMap(data.location.lat, data.location.lon, data.location.name, data.current.temp_c, data.current.condition.text);
        }

        // Mettre à jour les prévisions
        if (data.forecast && data.forecast.forecastday) {
            updateForecast(data.forecast.forecastday);
        }

        // Simuler une mise à jour des prédictions IA
        updatePredictions(data.current.temp_c, data.current.humidity);
    }

    /**
     * Met à jour la carte avec les informations météo
     */
    function updateMap(lat, lon, name, temp, condition) {
        if (!map) return;

        map.setView([lat, lon], 10);

        if (currentMarker) {
            map.removeLayer(currentMarker);
        }

        currentMarker = L.marker([lat, lon]).addTo(map);
        currentMarker.bindPopup(`<b>${name}</b><br>${temp}°C, ${condition}`).openPopup();
    }

    /**
     * Met à jour les prévisions météo
     */
    function updateForecast(forecastDays) {
        // Vérifier si le conteneur existe
        if (!forecastContainer) return;

        // Vider le conteneur
        forecastContainer.innerHTML = '';

        // N'afficher que les jours futurs (à partir de l'index 1)
        forecastDays.slice(1).forEach(day => {
            const date = new Date(day.date);
            const dayNames = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
            const dayName = dayNames[date.getDay()];

            const forecastCard = document.createElement('div');
            forecastCard.className = 'col-md-3 col-sm-6 mb-3';
            forecastCard.innerHTML = `
                <div class="card prediction-card h-100">
                    <div class="card-body text-center">
                        <div class="forecast-day">${dayName}</div>
                        <img src="${day.day.condition.icon}" alt="${day.day.condition.text}" class="my-2" width="64">
                        <div class="mb-2">${day.day.condition.text}</div>
                        <div class="d-flex justify-content-around">
                            <div><i class="fas fa-temperature-high"></i> ${day.day.maxtemp_c}°C</div>
                            <div><i class="fas fa-temperature-low"></i> ${day.day.mintemp_c}°C</div>
                        </div>
                        <div class="mt-2">
                            <i class="fas fa-tint"></i> ${day.day.avghumidity}%
                            <i class="fas fa-wind ms-2"></i> ${day.day.maxwind_kph} km/h
                        </div>
                    </div>
                </div>
            `;

            forecastContainer.appendChild(forecastCard);
        });
    }

    /**
     * Met à jour les prédictions IA
     */
    function updatePredictions(currentTemp, currentHumidity) {
        // Simuler une légère variation basée sur les conditions actuelles
        const predictedTemp = Math.round((currentTemp * (1 + (Math.random() * 0.1 - 0.05))) * 10) / 10;
        const predictedHumidity = Math.min(100, Math.max(0, Math.round(currentHumidity * (1 + (Math.random() * 0.15 - 0.05)))));

        aiTemperature.textContent = `${predictedTemp}°C`;
        aiHumidity.textContent = `${predictedHumidity}%`;
    }

    /**
     * Affiche un message d'alerte
     */
    function showAlert(message) {
        alertMessage.textContent = message;
        alertMessage.classList.remove('d-none');
    }

    /**
     * Cache le message d'alerte
     */
    function hideAlert() {
        alertMessage.classList.add('d-none');
    }

    /**
     * Affiche l'état de chargement
     */
    function showLoading() {
        // Ajouter la classe loading aux éléments principaux
        currentLocation.classList.add('loading');
        temperature.classList.add('loading');
        weatherCondition.classList.add('loading');
    }

    /**
     * Cache l'état de chargement
     */
    function hideLoading() {
        // Supprimer la classe loading des éléments
        currentLocation.classList.remove('loading');
        temperature.classList.remove('loading');
        weatherCondition.classList.remove('loading');
    }
});