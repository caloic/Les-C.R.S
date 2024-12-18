CREATE TABLE `users` (
  `id` CHAR(36) PRIMARY KEY,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `email` VARCHAR(100) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL
);

CREATE TABLE `weather_data` (
  `id` CHAR(36) PRIMARY KEY,
  `location_id` CHAR(36) NOT NULL,
  `temperature` FLOAT NOT NULL,
  `humidity` FLOAT NOT NULL,
  `wind_speed` FLOAT NOT NULL,
  `weather_condition` VARCHAR(100) NOT NULL
);

CREATE TABLE `locations` (
  `id` CHAR(36) PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `latitude` FLOAT NOT NULL,
  `longitude` FLOAT NOT NULL
);

CREATE TABLE `weather_predictions` (
  `id` CHAR(36) PRIMARY KEY,
  `location_id` CHAR(36) NOT NULL,
  `predicted_temperature` FLOAT NOT NULL,
  `predicted_humidity` FLOAT NOT NULL,
  `prediction_timestamp` DATETIME NOT NULL
);

CREATE TABLE `api_requests` (
  `id` CHAR(36) PRIMARY KEY,
  `user_id` CHAR(36) NOT NULL,
  `endpoint` VARCHAR(255) NOT NULL,
  `response_status` INT NOT NULL
);

ALTER TABLE `weather_data` ADD FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`);

ALTER TABLE `weather_predictions` ADD FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`);

ALTER TABLE `api_requests` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
