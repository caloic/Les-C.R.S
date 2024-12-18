-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mer. 18 déc. 2024 à 16:48
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `les crs`
--

-- --------------------------------------------------------

--
-- Structure de la table `api_requests`
--

CREATE TABLE `api_requests` (
  `id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `response_status` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `api_requests`
--

INSERT INTO `api_requests` (`id`, `user_id`, `endpoint`, `response_status`) VALUES
('8a43e2e2-bd56-11ef-b3dd-0a0027000014', '3b22948f-bd56-11ef-b3dd-0a0027000014', 'test10', 10),
('8a44217a-bd56-11ef-b3dd-0a0027000014', '3b230beb-bd56-11ef-b3dd-0a0027000014', 'test20', 20),
('8a445bfd-bd56-11ef-b3dd-0a0027000014', '3b22e746-bd56-11ef-b3dd-0a0027000014', 'test30', 30),
('8a448c0f-bd56-11ef-b3dd-0a0027000014', '3b22c356-bd56-11ef-b3dd-0a0027000014', 'test40', 40);

-- --------------------------------------------------------

--
-- Structure de la table `locations`
--

CREATE TABLE `locations` (
  `id` char(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `latitude` float NOT NULL,
  `longitude` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `locations`
--

INSERT INTO `locations` (`id`, `name`, `latitude`, `longitude`) VALUES
('b911397a-bd56-11ef-b3dd-0a0027000014', 'test100', 100, 100),
('b9116701-bd56-11ef-b3dd-0a0027000014', 'test200', 200, 200),
('b9118b74-bd56-11ef-b3dd-0a0027000014', 'test300', 300, 300),
('b911af58-bd56-11ef-b3dd-0a0027000014', 'test400', 400, 400);

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` char(36) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`) VALUES
('3b22948f-bd56-11ef-b3dd-0a0027000014', 'user1', 'user1@mail.com', 'user1'),
('3b22c356-bd56-11ef-b3dd-0a0027000014', 'user2', 'user2@mail.com', 'user2'),
('3b22e746-bd56-11ef-b3dd-0a0027000014', 'user3', 'user3@mail.com', 'user3'),
('3b230beb-bd56-11ef-b3dd-0a0027000014', 'user4', 'user4@mail.com', 'user4');

-- --------------------------------------------------------

--
-- Structure de la table `weather_data`
--

CREATE TABLE `weather_data` (
  `id` char(36) NOT NULL,
  `location_id` char(36) NOT NULL,
  `temperature` float NOT NULL,
  `humidity` float NOT NULL,
  `wind_speed` float NOT NULL,
  `weather_condition` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `weather_data`
--

INSERT INTO `weather_data` (`id`, `location_id`, `temperature`, `humidity`, `wind_speed`, `weather_condition`) VALUES
('ec6f8a9c-bd56-11ef-b3dd-0a0027000014', 'b9116701-bd56-11ef-b3dd-0a0027000014', 1000, 1000, 1000, 'test1000'),
('ec6fbf45-bd56-11ef-b3dd-0a0027000014', 'b911397a-bd56-11ef-b3dd-0a0027000014', 2000, 2000, 2000, 'test2000'),
('ec6fefb9-bd56-11ef-b3dd-0a0027000014', 'b9118b74-bd56-11ef-b3dd-0a0027000014', 3000, 3000, 3000, 'test3000'),
('ec701fff-bd56-11ef-b3dd-0a0027000014', 'b911af58-bd56-11ef-b3dd-0a0027000014', 4000, 4000, 4000, 'test4000');

-- --------------------------------------------------------

--
-- Structure de la table `weather_predictions`
--

CREATE TABLE `weather_predictions` (
  `id` char(36) NOT NULL,
  `location_id` char(36) NOT NULL,
  `predicted_temperature` float NOT NULL,
  `predicted_humidity` float NOT NULL,
  `prediction_timestamp` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `weather_predictions`
--

INSERT INTO `weather_predictions` (`id`, `location_id`, `predicted_temperature`, `predicted_humidity`, `prediction_timestamp`) VALUES
('157f6402-bd57-11ef-b3dd-0a0027000014', 'b9116701-bd56-11ef-b3dd-0a0027000014', 10000, 10000, '2024-12-18 16:44:12'),
('157f8188-bd57-11ef-b3dd-0a0027000014', 'b911397a-bd56-11ef-b3dd-0a0027000014', 20000, 20000, '2024-12-18 16:44:12'),
('157f9e9b-bd57-11ef-b3dd-0a0027000014', 'b9118b74-bd56-11ef-b3dd-0a0027000014', 30000, 30000, '2024-12-18 16:44:12'),
('157fbe6e-bd57-11ef-b3dd-0a0027000014', 'b911af58-bd56-11ef-b3dd-0a0027000014', 40000, 40000, '2024-12-18 16:44:12');

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
  ADD PRIMARY KEY (`id`);

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
  ADD KEY `location_id` (`location_id`);

--
-- Index pour la table `weather_predictions`
--
ALTER TABLE `weather_predictions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `location_id` (`location_id`);

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
