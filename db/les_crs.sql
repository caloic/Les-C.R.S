-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:8889
-- Généré le : mer. 16 avr. 2025 à 10:34
-- Version du serveur : 8.0.40
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `les_crs`
--

-- --------------------------------------------------------

--
-- Structure de la table `api_requests`
--

CREATE TABLE `api_requests` (
  `id` char(36) COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_general_ci NOT NULL,
  `endpoint` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `response_status` int NOT NULL,
  `request_timestamp` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `locations`
--

CREATE TABLE `locations` (
  `id` char(36) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `insee_code` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `city_code` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `zip_code` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `department_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `department_number` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `region_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `region_geojson_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` char(36) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `weather_data`
--

CREATE TABLE `weather_data` (
  `id` char(36) COLLATE utf8mb4_general_ci NOT NULL,
  `location_id` char(36) COLLATE utf8mb4_general_ci NOT NULL,
  `temperature` float NOT NULL,
  `humidity` float NOT NULL,
  `wind_speed` float NOT NULL,
  `weather_condition` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `weather_predictions`
--

CREATE TABLE `weather_predictions` (
  `id` char(36) COLLATE utf8mb4_general_ci NOT NULL,
  `location_id` char(36) COLLATE utf8mb4_general_ci NOT NULL,
  `predicted_temperature` float NOT NULL,
  `predicted_humidity` float NOT NULL,
  `prediction_timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `api_requests`
--
ALTER TABLE `api_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `insee_code` (`insee_code`),
  ADD KEY `zip_code` (`zip_code`),
  ADD KEY `department_number` (`department_number`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `weather_data`
--
ALTER TABLE `weather_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `timestamp` (`timestamp`);

--
-- Index pour la table `weather_predictions`
--
ALTER TABLE `weather_predictions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `prediction_timestamp` (`prediction_timestamp`);

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `api_requests`
--
ALTER TABLE `api_requests`
  ADD CONSTRAINT `api_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `weather_data`
--
ALTER TABLE `weather_data`
  ADD CONSTRAINT `weather_data_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`);

--
-- Contraintes pour la table `weather_predictions`
--
ALTER TABLE `weather_predictions`
  ADD CONSTRAINT `weather_predictions_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
