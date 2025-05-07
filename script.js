// Attendre que le DOM soit chargé
document.addEventListener('DOMContentLoaded', function() {
    // Éléments du DOM
    const locationInput = document.getElementById('locationInput');
    const searchBtn = document.getElementById('searchBtn');
    const autocompleteContainer = document.getElementById('autocompleteContainer');
    const currentLocation = document.getElementById('currentLocation');
    const currentDate = document.getElementById('currentDate');
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
    const heroSection = document.querySelector('.hero-section');
    const weatherIconLarge = document.querySelector('.weather-icon-large');

    // Éléments pour les animations
    let weatherAnimationContainer = document.getElementById('weatherAnimationContainer');

    // Créer le conteneur d'animations s'il n'existe pas
    if (!weatherAnimationContainer) {
        weatherAnimationContainer = document.createElement('div');
        weatherAnimationContainer.id = 'weatherAnimationContainer';
        weatherAnimationContainer.classList.add('weather-animation-container');
        heroSection.appendChild(weatherAnimationContainer);
    }

    // Événements
    searchBtn.addEventListener('click', searchWeather);
    locationInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchWeather();
            e.preventDefault(); // Empêcher le rechargement de la page
        }
    });

    // Gestion de l'autocomplétion
    locationInput.addEventListener('input', debounce(handleAutocomplete, 300));

    // Fermer l'autocomplétion quand on clique ailleurs
    document.addEventListener('click', function(e) {
        if (!locationInput.contains(e.target) && !autocompleteContainer.contains(e.target)) {
            autocompleteContainer.classList.add('d-none');
        }
    });

    // Animation des cartes de prévision
    const forecastCards = document.querySelectorAll('.forecast-card');
    forecastCards.forEach(card => {
        card.addEventListener('click', function() {
            forecastCards.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Initialisation de la carte
    let map;
    let currentMarker;
    let markers = [];

    initMap();

    // Initialiser avec une animation par défaut
    createWeatherAnimation('sunny');

    /**
     * Initialise la carte Leaflet
     */
    function initMap() {
        // Vérifier si l'élément map existe
        const mapElement = document.getElementById('map');
        if (!mapElement) return;

        // Initialiser la carte
        map = L.map('map').setView([46.603354, 1.888334], 5); // Vue centrée sur la France

        // Utiliser un style de carte moderne
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(map);

        // Ajouter quelques villes principales pour l'exemple
        addSampleCitiesToMap();
    }

    /**
     * Ajoute des villes d'exemple à la carte
     */
    function addSampleCitiesToMap() {
        if (!map) return;

        const cities = [
            { name: 'Paris', lat: 48.8566, lon: 2.3522, temp: 24, condition: 'Ensoleillé' },
            { name: 'Lyon', lat: 45.75, lon: 4.85, temp: 22, condition: 'Partiellement nuageux' },
            { name: 'Marseille', lat: 43.2965, lon: 5.3698, temp: 26, condition: 'Ensoleillé' },
            { name: 'Bordeaux', lat: 44.8378, lon: -0.5792, temp: 21, condition: 'Nuageux' },
            { name: 'Lille', lat: 50.6292, lon: 3.0573, temp: 19, condition: 'Pluie légère' }
        ];

        cities.forEach(city => {
            addCityToMap(city.name, city.lat, city.lon, city.temp, city.condition);
        });
    }

    /**
     * Ajoute une ville à la carte avec un marqueur personnalisé
     */
    function addCityToMap(name, lat, lon, temp, condition) {
        if (!map) return;

        // Déterminer la couleur et l'icône en fonction de la condition
        let markerColor, iconClass;

        if (condition.toLowerCase().includes('soleil') || condition.toLowerCase().includes('ensoleillé')) {
            markerColor = '#ffaa33';
            iconClass = 'fa-sun';
        } else if (condition.toLowerCase().includes('nuage') || condition.toLowerCase().includes('nuageux')) {
            markerColor = '#90caf9';
            iconClass = 'fa-cloud';
        } else if (condition.toLowerCase().includes('pluie')) {
            markerColor = '#64b5f6';
            iconClass = 'fa-cloud-rain';
        } else {
            markerColor = '#90caf9';
            iconClass = 'fa-cloud';
        }

        // Créer un élément HTML pour le marqueur personnalisé
        const markerHtml = `
            <div class="custom-marker" style="background-color: ${markerColor};">
                <div class="marker-temp">${temp}°</div>
                <i class="fas ${iconClass}"></i>
            </div>
        `;

        const customIcon = L.divIcon({
            html: markerHtml,
            className: 'custom-div-icon',
            iconSize: [40, 40],
            iconAnchor: [20, 20]
        });

        // Créer et ajouter le marqueur
        const marker = L.marker([lat, lon], { icon: customIcon }).addTo(map);
        marker.bindPopup(`<b>${name}</b><br>${temp}°C, ${condition}`);

        // Stocker le marqueur
        markers.push(marker);
    }

    /**
     * Crée une animation météo basée sur la condition
     */
    function createWeatherAnimation(condition) {
        // Nettoyer les animations précédentes
        weatherAnimationContainer.innerHTML = '';

        const conditionLower = condition.toLowerCase();

        // Créer l'animation appropriée selon la condition météo
        if (conditionLower.includes('soleil') || conditionLower.includes('ensoleillé') || conditionLower.includes('dégagé') || conditionLower.includes('clair')) {
            createSunAnimation();
        } else if (conditionLower.includes('pluie') || conditionLower.includes('bruine')) {
            createRainAnimation();
        } else if (conditionLower.includes('neige') || conditionLower.includes('neigeux')) {
            createSnowAnimation();
        } else if (conditionLower.includes('nuage') || conditionLower.includes('nuageux') || conditionLower.includes('couvert')) {
            createCloudAnimation();
        } else if (conditionLower.includes('orage') || conditionLower.includes('tonnerre')) {
            createLightningAnimation();
        } else if (conditionLower.includes('brouillard') || conditionLower.includes('brumeux')) {
            createFogAnimation();
        } else {
            // Par défaut, créer une animation nuageuse légère
            createCloudAnimation();
        }
    }

    /**
     * Crée une animation de soleil améliorée avec rayonnement
     */
    function createSunAnimation() {
        // Créer le conteneur principal
        const sunContainer = document.createElement('div');
        sunContainer.className = 'sun-animation-container';

        // Ajouter le halo extérieur
        const sunGlow = document.createElement('div');
        sunGlow.className = 'sun-glow';
        sunContainer.appendChild(sunGlow);

        // Ajouter le noyau du soleil (cercle central)
        const sunCore = document.createElement('div');
        sunCore.className = 'sun-core';
        sunContainer.appendChild(sunCore);

        // Ajouter le conteneur de rayons externes qui va tourner
        const sunRaysOuter = document.createElement('div');
        sunRaysOuter.className = 'sun-rays-outer';

        // Ajouter les 12 rayons principaux
        for (let i = 0; i < 12; i++) {
            const ray = document.createElement('div');
            ray.className = 'sun-ray';
            sunRaysOuter.appendChild(ray);
        }

        // Ajouter les 12 rayons dynamiques (plus courts)
        for (let i = 0; i < 12; i++) {
            const ray = document.createElement('div');
            ray.className = 'sun-ray-dynamic';
            sunRaysOuter.appendChild(ray);
        }

        sunContainer.appendChild(sunRaysOuter);

        // Ajouter des points lumineux aléatoires (étincelles)
        for (let i = 0; i < 15; i++) {
            const sparkle = document.createElement('div');
            sparkle.className = 'sun-sparkle';

            // Position aléatoire autour du soleil
            const angle = Math.random() * Math.PI * 2; // Angle aléatoire
            const distance = 40 + Math.random() * 80; // Distance aléatoire du centre

            const x = Math.cos(angle) * distance + 150; // 150 = centre x
            const y = Math.sin(angle) * distance + 150; // 150 = centre y

            sparkle.style.left = `${x}px`;
            sparkle.style.top = `${y}px`;

            // Délai d'animation aléatoire
            sparkle.style.animationDelay = `${Math.random() * 3}s`;

            sunContainer.appendChild(sparkle);
        }

        weatherAnimationContainer.appendChild(sunContainer);
    }

    /**
     * Crée une animation de pluie
     */
    function createRainAnimation() {
        const rainContainer = document.createElement('div');
        rainContainer.className = 'rain-container';

        // Créer des gouttes de pluie avec différentes positions et durées d'animation
        for (let i = 0; i < 100; i++) {
            const drop = document.createElement('div');
            drop.className = 'rain-drop';

            // Position horizontale aléatoire
            drop.style.left = `${Math.random() * 100}%`;

            // Durée d'animation aléatoire pour un effet plus naturel
            const duration = 0.5 + Math.random() * 0.5;
            drop.style.animationDuration = `${duration}s`;

            // Délai d'animation aléatoire
            drop.style.animationDelay = `${Math.random() * 2}s`;

            rainContainer.appendChild(drop);
        }

        weatherAnimationContainer.appendChild(rainContainer);
    }

    /**
     * Crée une animation de neige
     */
    function createSnowAnimation() {
        const snowContainer = document.createElement('div');
        snowContainer.className = 'snow-container';

        // Créer des flocons de neige avec différentes positions et durées d'animation
        for (let i = 0; i < 50; i++) {
            const snowflake = document.createElement('div');
            snowflake.className = 'snowflake';

            // Position horizontale aléatoire
            snowflake.style.left = `${Math.random() * 100}%`;

            // Taille aléatoire
            const size = 2 + Math.random() * 4;
            snowflake.style.width = `${size}px`;
            snowflake.style.height = `${size}px`;

            // Durée d'animation aléatoire pour un effet plus naturel
            const duration = 7 + Math.random() * 10;
            snowflake.style.animationDuration = `${duration}s`;

            // Délai d'animation aléatoire
            snowflake.style.animationDelay = `${Math.random() * 5}s`;

            snowContainer.appendChild(snowflake);
        }

        weatherAnimationContainer.appendChild(snowContainer);
    }

    /**
     * Crée une animation de nuages
     */
    function createCloudAnimation() {
        const cloudContainer = document.createElement('div');
        cloudContainer.className = 'cloud-container';

        // Créer plusieurs nuages avec différentes positions et durées d'animation
        for (let i = 0; i < 5; i++) {
            const cloud = document.createElement('div');
            cloud.className = 'cloud';

            // Taille aléatoire
            const width = 100 + Math.random() * 150;
            cloud.style.width = `${width}px`;

            // Position verticale aléatoire
            cloud.style.top = `${10 + Math.random() * 40}%`;

            // Durée d'animation aléatoire
            const duration = 80 + Math.random() * 40;
            cloud.style.animationDuration = `${duration}s`;

            // Délai d'animation aléatoire
            cloud.style.animationDelay = `${Math.random() * -40}s`;

            cloudContainer.appendChild(cloud);
        }

        weatherAnimationContainer.appendChild(cloudContainer);
    }

    /**
     * Crée une animation d'orage
     */
    function createLightningAnimation() {
        // D'abord créer l'animation de pluie
        createRainAnimation();

        // Puis ajouter l'animation d'éclair
        const lightningContainer = document.createElement('div');
        lightningContainer.className = 'lightning-container';

        // Ajouter plusieurs éclairs à des positions différentes
        for (let i = 0; i < 3; i++) {
            const lightning = document.createElement('div');
            lightning.className = 'lightning';

            // Position aléatoire
            lightning.style.left = `${10 + Math.random() * 80}%`;
            lightning.style.top = `${10 + Math.random() * 30}%`;

            // Délai d'animation aléatoire
            lightning.style.animationDelay = `${Math.random() * 8}s`;

            lightningContainer.appendChild(lightning);

            // Ajouter un éclair zigzag pour chaque flash
            const bolt = document.createElement('div');
            bolt.className = 'lightning-bolt';
            bolt.style.left = `${parseInt(lightning.style.left) + 125}px`;
            bolt.style.top = `${parseInt(lightning.style.top) + 150}px`;
            bolt.style.animationDelay = lightning.style.animationDelay;

            lightningContainer.appendChild(bolt);
        }

        weatherAnimationContainer.appendChild(lightningContainer);
    }

    /**
     * Crée une animation de brouillard
     */
    function createFogAnimation() {
        const fogContainer = document.createElement('div');
        fogContainer.className = 'fog-container';

        // Créer plusieurs couches de brouillard
        for (let i = 0; i < 3; i++) {
            const fogLayer = document.createElement('div');
            fogLayer.className = 'fog-layer';

            // Position et durée différentes pour chaque couche
            fogLayer.style.top = `${i * 33}%`;
            fogLayer.style.opacity = `${0.4 - i * 0.1}`;

            // Durée d'animation différente
            const duration = 50 + i * 20;
            fogLayer.style.animationDuration = `${duration}s`;

            // Délai d'animation
            fogLayer.style.animationDelay = `${i * -10}s`;

            fogContainer.appendChild(fogLayer);
        }

        weatherAnimationContainer.appendChild(fogContainer);
    }

    /**
     * Fonction pour obtenir l'URL de l'icône météo en fonction de la condition
     */
    function getWeatherIconUrl(condition) {
        const conditionLower = condition.toLowerCase();

        if (conditionLower.includes('soleil') || conditionLower.includes('ensoleillé') || conditionLower.includes('dégagé') || conditionLower.includes('clair')) {
            return 'assets/img/icons/sunny.svg';
        } else if (conditionLower.includes('partiellement nuageux') || conditionLower.includes('éclaircies')) {
            return 'assets/img/icons/partly-cloudy.svg';
        } else if (conditionLower.includes('nuage') || conditionLower.includes('nuageux') || conditionLower.includes('couvert')) {
            return 'assets/img/icons/cloudy.svg';
        } else if (conditionLower.includes('bruine') || conditionLower.includes('pluie légère')) {
            return 'assets/img/icons/drizzle.svg';
        } else if (conditionLower.includes('pluie')) {
            return 'assets/img/icons/rainy.svg';
        } else if (conditionLower.includes('orage') || conditionLower.includes('tonnerre')) {
            return 'assets/img/icons/thunderstorm.svg';
        } else if (conditionLower.includes('neige') || conditionLower.includes('neigeux')) {
            return 'assets/img/icons/snowy.svg';
        } else if (conditionLower.includes('brouillard') || conditionLower.includes('brumeux')) {
            return 'assets/img/icons/foggy.svg';
        }

        // Icône par défaut
        return 'assets/img/icons/partly-cloudy.svg';
    }

    /**
     * Met à jour le fond de la page en fonction de la condition météo
     */
    function updateWeatherBackground(condition) {
        const conditionLower = condition.toLowerCase();

        // Supprimer toutes les classes de condition précédentes
        heroSection.classList.remove(
            'weather-sunny',
            'weather-cloudy',
            'weather-rainy',
            'weather-thunderstorm',
            'weather-snowy',
            'weather-foggy'
        );

        // Ajouter la classe appropriée en fonction de la condition
        if (conditionLower.includes('soleil') || conditionLower.includes('ensoleillé') || conditionLower.includes('dégagé') || conditionLower.includes('clair')) {
            heroSection.classList.add('weather-sunny');
        } else if (conditionLower.includes('nuage') || conditionLower.includes('nuageux') || conditionLower.includes('couvert') || conditionLower.includes('éclaircies')) {
            heroSection.classList.add('weather-cloudy');
        } else if (conditionLower.includes('pluie') || conditionLower.includes('bruine')) {
            heroSection.classList.add('weather-rainy');
        } else if (conditionLower.includes('orage') || conditionLower.includes('tonnerre')) {
            heroSection.classList.add('weather-thunderstorm');
        } else if (conditionLower.includes('neige') || conditionLower.includes('neigeux')) {
            heroSection.classList.add('weather-snowy');
        } else if (conditionLower.includes('brouillard') || conditionLower.includes('brumeux')) {
            heroSection.classList.add('weather-foggy');
        } else {
            // Par défaut, fond partiellement nuageux
            heroSection.classList.add('weather-cloudy');
        }

        // Créer l'animation correspondante
        createWeatherAnimation(condition);
    }

    /**
     * Fonction de recherche météo
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

                // Fermer l'autocomplétion
                autocompleteContainer.classList.add('d-none');
            })
            .catch(error => {
                console.error('Erreur:', error);
                showAlert('Erreur de connexion au serveur');
                hideLoading();
            });
    }

    /**
     * Gère l'autocomplétion des villes
     */
    function handleAutocomplete() {
        const query = locationInput.value.trim();

        // Ne pas afficher l'autocomplétion si la requête est trop courte
        if (query.length < 2) {
            autocompleteContainer.classList.add('d-none');
            return;
        }

        // Simuler des résultats d'autocomplétion (à remplacer par un appel API réel)
        const cities = [
            'Paris', 'Marseille', 'Lyon', 'Toulouse', 'Nice',
            'Nantes', 'Strasbourg', 'Montpellier', 'Bordeaux', 'Lille'
        ];

        const filteredCities = cities.filter(city =>
            city.toLowerCase().includes(query.toLowerCase())
        );

        if (filteredCities.length > 0) {
            renderAutocompleteResults(filteredCities);
        } else {
            autocompleteContainer.classList.add('d-none');
        }
    }

    /**
     * Affiche les résultats d'autocomplétion
     */
    function renderAutocompleteResults(cities) {
        autocompleteContainer.innerHTML = '';

        cities.forEach(city => {
            const item = document.createElement('div');
            item.className = 'autocomplete-item';
            item.textContent = city;

            item.addEventListener('click', () => {
                locationInput.value = city;
                autocompleteContainer.classList.add('d-none');
                searchWeather();
            });

            autocompleteContainer.appendChild(item);
        });

        autocompleteContainer.classList.remove('d-none');
    }

    /**
     * Met à jour l'interface avec les données météo de l'API
     */
    function updateWeatherUI(data) {
        // Mettre à jour les informations de localisation
        currentLocation.textContent = data.location.name;

        // Format de la date actuelle
        const now = new Date();
        const hours = now.getHours().toString().padStart(2, '0');
        const minutes = now.getMinutes().toString().padStart(2, '0');
        currentDate.textContent = `Update As Of ${hours}:${minutes}`;

        // Mettre à jour les données météo actuelles
        temperature.textContent = Math.round(data.current.temp_c);

        const conditionText = data.current.condition.text;
        weatherCondition.textContent = conditionText;

        // Mettre à jour le fond en fonction de la condition météo
        updateWeatherBackground(conditionText);

        // Mettre à jour l'icône météo
        if (weatherIconLarge) {
            const iconUrl = getWeatherIconUrl(conditionText);
            weatherIconLarge.innerHTML = `<img src="${iconUrl}" alt="${conditionText}" width="120">`;
        }

        humidity.textContent = `${data.current.humidity}%`;
        windSpeed.textContent = `${Math.round(data.current.wind_kph)} km/h`;
        pressure.textContent = data.current.pressure_mb;
        visibility.textContent = `${data.current.vis_km} km`;

        // Mettre à jour la carte
        if (map) {
            updateMap(data.location.lat, data.location.lon, data.location.name, data.current.temp_c, data.current.condition.text);
        }

        // Mettre à jour les prévisions
        if (data.forecast && data.forecast.forecastday) {
            updateForecast(data.forecast.forecastday, conditionText);
        }

        // Mettre à jour les prédictions IA
        if (data.prediction) {
            aiTemperature.textContent = `${data.prediction.temperature}°C`;
            aiHumidity.textContent = `${data.prediction.humidity}%`;
        } else {
            // Simuler une prédiction si non disponible
            updatePredictions(data.current.temp_c, data.current.humidity);
        }
    }

    /**
     * Met à jour la carte avec les informations météo
     */
    function updateMap(lat, lon, name, temp, condition) {
        if (!map) return;

        // Centrer la carte sur la nouvelle position
        map.setView([lat, lon], 10);

        // Supprimer le marqueur actuel s'il existe
        if (currentMarker) {
            map.removeLayer(currentMarker);
        }

        // Déterminer la couleur et l'icône en fonction de la condition
        let markerColor, iconClass;
        const conditionLower = condition.toLowerCase();

        if (conditionLower.includes('soleil') || conditionLower.includes('ensoleillé') || conditionLower.includes('dégagé')) {
            markerColor = '#ffaa33';
            iconClass = 'fa-sun';
        } else if (conditionLower.includes('nuage') || conditionLower.includes('nuageux') || conditionLower.includes('couvert')) {
            markerColor = '#90caf9';
            iconClass = 'fa-cloud';
        } else if (conditionLower.includes('pluie') || conditionLower.includes('bruine')) {
            markerColor = '#64b5f6';
            iconClass = 'fa-cloud-rain';
        } else if (conditionLower.includes('orage') || conditionLower.includes('tonnerre')) {
            markerColor = '#5c6bc0';
            iconClass = 'fa-bolt';
        } else if (conditionLower.includes('neige') || conditionLower.includes('neigeux')) {
            markerColor = '#b3e5fc';
            iconClass = 'fa-snowflake';
        } else {
            markerColor = '#90caf9';
            iconClass = 'fa-cloud';
        }

        // Créer un élément HTML pour le marqueur personnalisé
        const markerHtml = `
            <div class="custom-marker highlight-marker" style="background-color: ${markerColor};">
                <div class="marker-temp">${Math.round(temp)}°</div>
                <i class="fas ${iconClass}"></i>
            </div>
        `;

        const customIcon = L.divIcon({
            html: markerHtml,
            className: 'custom-div-icon',
            iconSize: [50, 50],
            iconAnchor: [25, 25]
        });

        // Ajouter le nouveau marqueur
        currentMarker = L.marker([lat, lon], { icon: customIcon }).addTo(map);
        currentMarker.bindPopup(`<b>${name}</b><br>${Math.round(temp)}°C, ${condition}`).openPopup();
    }

    /**
     * Met à jour les prévisions météo
     */
    function updateForecast(forecastDays, currentCondition) {
        // Vérifier si le conteneur existe
        if (!forecastContainer) return;

        // Vider le conteneur
        forecastContainer.innerHTML = '';

        // Créer une première carte active
        const firstDay = forecastDays[0];
        const date = new Date();
        const joursSemaine = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
        const jourSemaine = joursSemaine[date.getDay()];

        const activeCard = document.createElement('div');
        activeCard.className = 'forecast-card active';

        // Obtenir l'icône correcte pour la condition météo actuelle
        const currentIconUrl = getWeatherIconUrl(currentCondition);

        activeCard.innerHTML = `
            <div class="forecast-time">
                <span class="forecast-day">${jourSemaine}</span>
                <span class="forecast-hour">4:00PM</span>
            </div>
            <div class="forecast-icon">
                <img src="${currentIconUrl}" alt="${currentCondition}" width="64">
            </div>
            <div class="forecast-temp">${Math.round(firstDay.day.avgtemp_c)}°</div>
            <div class="forecast-high-low">${Math.round(firstDay.day.maxtemp_c)}°</div>
            <div class="forecast-detail">
                <span><i class="fas fa-wind"></i>${Math.round(firstDay.day.maxwind_kph)} km/h</span>
                <span><i class="fas fa-tint"></i>${Math.round(firstDay.day.avghumidity)}%</span>
            </div>
        `;
        forecastContainer.appendChild(activeCard);

        // Ajouter des cartes pour les heures suivantes
        const hours = ["5:00PM", "6:00PM", "7:00PM", "8:00PM"];

        // N'afficher que quelques prévisions
        forecastDays.slice(0, 4).forEach((day, index) => {
            const date = new Date();
            date.setDate(date.getDate() + (index > 0 ? 1 : 0));
            const jourSemaine = joursSemaine[date.getDay()];
            const hour = hours[index % hours.length];

            // Obtenir l'icône correcte pour cette prévision
            const forecastIconUrl = getWeatherIconUrl(day.day.condition.text);

            const forecastCard = document.createElement('div');
            forecastCard.className = 'forecast-card';
            forecastCard.innerHTML = `
                <div class="forecast-time">
                    <span class="forecast-day">${jourSemaine}</span>
                    <span class="forecast-hour">${hour}</span>
                </div>
                <div class="forecast-icon">
                    <img src="${forecastIconUrl}" alt="${day.day.condition.text}" width="64">
                </div>
                <div class="forecast-temp">${Math.round(day.day.avgtemp_c)}°</div>
                <div class="forecast-high-low">${Math.round(day.day.maxtemp_c)}°</div>
                <div class="forecast-detail">
                    <span><i class="fas fa-wind"></i>${Math.round(day.day.maxwind_kph)} km/h</span>
                    <span><i class="fas fa-tint"></i>${Math.round(day.day.avghumidity)}%</span>
                </div>
            `;

            // Ajouter l'événement de clic
            forecastCard.addEventListener('click', function() {
                document.querySelectorAll('.forecast-card').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
            });

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

        if (aiTemperature) aiTemperature.textContent = `${predictedTemp}°C`;
        if (aiHumidity) aiHumidity.textContent = `${predictedHumidity}%`;
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

    /**
     * Fonction debounce pour limiter les appels fréquents
     */
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

    // Ajouter du CSS personnalisé pour les marqueurs de carte
    addCustomMapStyles();

    /**
     * Ajoute des styles CSS personnalisés pour les marqueurs de carte
     */
    function addCustomMapStyles() {
        const style = document.createElement('style');
        style.innerHTML = `
            .custom-marker {
                border-radius: 50%;
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
                width: 100%;
                height: 100%;
                font-weight: bold;
            }
            
            .highlight-marker {
                transform: scale(1.2);
                z-index: 1000;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
            }
            
            .custom-div-icon {
                background: none;
                border: none;
            }
            
            .weather-animation-container {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 1;
                pointer-events: none;
                overflow: hidden;
            }
        `;
        document.head.appendChild(style);
    }
});