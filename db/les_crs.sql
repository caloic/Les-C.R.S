-- Script SQL complet pour créer la base de données MétéoCRS
-- Version améliorée avec support pour les données historiques

-- Création de la base de données
CREATE DATABASE IF NOT EXISTS les_crs;
USE les_crs;

-- Configuration du jeu de caractères
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Structure de la table `users`
CREATE TABLE `users` (
                         `id` char(36) NOT NULL,
                         `username` varchar(50) NOT NULL,
                         `email` varchar(100) NOT NULL,
                         `password_hash` varchar(255) NOT NULL,
                         `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                         PRIMARY KEY (`id`),
                         UNIQUE KEY `username` (`username`),
                         UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Structure de la table `locations`
CREATE TABLE `locations` (
                             `id` char(36) NOT NULL,
                             `name` varchar(100) NOT NULL,
                             `region` varchar(100) DEFAULT NULL,
                             `country` varchar(100) DEFAULT 'France',
                             `latitude` float NOT NULL,
                             `longitude` float NOT NULL,
                             `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                             PRIMARY KEY (`id`),
                             KEY `idx_location_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Structure de la table `weather_data`
CREATE TABLE `weather_data` (
                                `id` char(36) NOT NULL,
                                `location_id` char(36) NOT NULL,
                                `temperature` float NOT NULL,
                                `humidity` float NOT NULL,
                                `wind_speed` float NOT NULL,
                                `weather_condition` varchar(100) NOT NULL,
                                `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                                PRIMARY KEY (`id`),
                                KEY `location_id` (`location_id`),
                                KEY `idx_weather_data_timestamp` (`timestamp`),
                                CONSTRAINT `weather_data_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Structure de la table `weather_predictions`
CREATE TABLE `weather_predictions` (
                                       `id` char(36) NOT NULL,
                                       `location_id` char(36) NOT NULL,
                                       `predicted_temperature` float NOT NULL,
                                       `predicted_humidity` float NOT NULL,
                                       `prediction_timestamp` datetime NOT NULL,
                                       PRIMARY KEY (`id`),
                                       KEY `location_id` (`location_id`),
                                       CONSTRAINT `weather_predictions_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Structure de la table `api_requests`
CREATE TABLE `api_requests` (
                                `id` char(36) NOT NULL,
                                `user_id` char(36) NOT NULL,
                                `endpoint` varchar(255) NOT NULL,
                                `response_status` int(11) NOT NULL,
                                `request_timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                                PRIMARY KEY (`id`),
                                KEY `user_id` (`user_id`),
                                CONSTRAINT `api_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Nouvelle table pour l'historique des données météo (CSV)
CREATE TABLE `weather_history` (
                                   `id` char(36) NOT NULL,
                                   `location_id` char(36) NOT NULL,
                                   `date` date NOT NULL,
                                   `min_temp` float DEFAULT NULL,
                                   `max_temp` float DEFAULT NULL,
                                   `rainfall` float DEFAULT NULL,
                                   `evaporation` float DEFAULT NULL,
                                   `sunshine` float DEFAULT NULL,
                                   `wind_gust_dir` varchar(10) DEFAULT NULL,
                                   `wind_gust_speed` float DEFAULT NULL,
                                   `wind_dir_9am` varchar(10) DEFAULT NULL,
                                   `wind_dir_3pm` varchar(10) DEFAULT NULL,
                                   `wind_speed_9am` float DEFAULT NULL,
                                   `wind_speed_3pm` float DEFAULT NULL,
                                   `humidity_9am` float DEFAULT NULL,
                                   `humidity_3pm` float DEFAULT NULL,
                                   `pressure_9am` float DEFAULT NULL,
                                   `pressure_3pm` float DEFAULT NULL,
                                   `cloud_9am` float DEFAULT NULL,
                                   `cloud_3pm` float DEFAULT NULL,
                                   `temp_9am` float DEFAULT NULL,
                                   `temp_3pm` float DEFAULT NULL,
                                   `rain_today` varchar(3) DEFAULT NULL,
                                   `rain_tomorrow` varchar(3) DEFAULT NULL,
                                   `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                                   PRIMARY KEY (`id`),
                                   KEY `idx_weather_history_location_date` (`location_id`, `date`),
                                   CONSTRAINT `weather_history_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pour les prévisions météo quotidiennes
CREATE TABLE `daily_forecasts` (
                                   `id` char(36) NOT NULL,
                                   `location_id` char(36) NOT NULL,
                                   `forecast_date` date NOT NULL,
                                   `min_temp` float NOT NULL,
                                   `max_temp` float NOT NULL,
                                   `avg_humidity` float NOT NULL,
                                   `max_wind_speed` float NOT NULL,
                                   `weather_condition` varchar(100) NOT NULL,
                                   `icon_url` varchar(255) NOT NULL,
                                   `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                                   PRIMARY KEY (`id`),
                                   KEY `idx_forecast_location_date` (`location_id`, `forecast_date`),
                                   CONSTRAINT `daily_forecasts_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insertion des données de test (utilisateurs)
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`) VALUES
                                                                     ('3b22948f-bd56-11ef-b3dd-0a0027000014', 'user1', 'user1@mail.com', 'user1'),
                                                                     ('3b22c356-bd56-11ef-b3dd-0a0027000014', 'user2', 'user2@mail.com', 'user2'),
                                                                     ('3b22e746-bd56-11ef-b3dd-0a0027000014', 'user3', 'user3@mail.com', 'user3'),
                                                                     ('3b230beb-bd56-11ef-b3dd-0a0027000014', 'user4', 'user4@mail.com', 'user4');

-- Insertion des données de test (localisations)
INSERT INTO `locations` (`id`, `name`, `latitude`, `longitude`) VALUES
                                                                    ('b911397a-bd56-11ef-b3dd-0a0027000014', 'Paris', 48.8566, 2.3522),
                                                                    ('b9116701-bd56-11ef-b3dd-0a0027000014', 'Lyon', 45.75, 4.85),
                                                                    ('b9118b74-bd56-11ef-b3dd-0a0027000014', 'Marseille', 43.2965, 5.3698),
                                                                    ('b911af58-bd56-11ef-b3dd-0a0027000014', 'Toulouse', 43.6045, 1.4442);

-- Insertion des données de test (données météo)
INSERT INTO `weather_data` (`id`, `location_id`, `temperature`, `humidity`, `wind_speed`, `weather_condition`) VALUES
                                                                                                                   ('ec6f8a9c-bd56-11ef-b3dd-0a0027000014', 'b9116701-bd56-11ef-b3dd-0a0027000014', 15.5, 65, 12, 'Partiellement nuageux'),
                                                                                                                   ('ec6fbf45-bd56-11ef-b3dd-0a0027000014', 'b911397a-bd56-11ef-b3dd-0a0027000014', 14.2, 70, 8, 'Ensoleillé'),
                                                                                                                   ('ec6fefb9-bd56-11ef-b3dd-0a0027000014', 'b9118b74-bd56-11ef-b3dd-0a0027000014', 18.7, 55, 15, 'Dégagé'),
                                                                                                                   ('ec701fff-bd56-11ef-b3dd-0a0027000014', 'b911af58-bd56-11ef-b3dd-0a0027000014', 16.3, 60, 10, 'Nuageux');

-- Insertion des données de test (prédictions)
INSERT INTO `weather_predictions` (`id`, `location_id`, `predicted_temperature`, `predicted_humidity`, `prediction_timestamp`) VALUES
                                                                                                                                   ('157f6402-bd57-11ef-b3dd-0a0027000014', 'b9116701-bd56-11ef-b3dd-0a0027000014', 16.2, 68, '2025-03-26 12:00:00'),
                                                                                                                                   ('157f8188-bd57-11ef-b3dd-0a0027000014', 'b911397a-bd56-11ef-b3dd-0a0027000014', 15.1, 72, '2025-03-26 12:00:00'),
                                                                                                                                   ('157f9e9b-bd57-11ef-b3dd-0a0027000014', 'b9118b74-bd56-11ef-b3dd-0a0027000014', 19.5, 58, '2025-03-26 12:00:00'),
                                                                                                                                   ('157fbe6e-bd57-11ef-b3dd-0a0027000014', 'b911af58-bd56-11ef-b3dd-0a0027000014', 17.0, 62, '2025-03-26 12:00:00');

-- Insertion des données de test (requêtes API)
INSERT INTO `api_requests` (`id`, `user_id`, `endpoint`, `response_status`) VALUES
                                                                                ('8a43e2e2-bd56-11ef-b3dd-0a0027000014', '3b22948f-bd56-11ef-b3dd-0a0027000014', 'getWeather', 200),
                                                                                ('8a44217a-bd56-11ef-b3dd-0a0027000014', '3b230beb-bd56-11ef-b3dd-0a0027000014', 'getLocations', 200),
                                                                                ('8a445bfd-bd56-11ef-b3dd-0a0027000014', '3b22e746-bd56-11ef-b3dd-0a0027000014', 'getWeatherById', 200),
                                                                                ('8a448c0f-bd56-11ef-b3dd-0a0027000014', '3b22c356-bd56-11ef-b3dd-0a0027000014', 'getForecast', 200);

-- Réactiver les contraintes de clé étrangère
SET FOREIGN_KEY_CHECKS = 1;