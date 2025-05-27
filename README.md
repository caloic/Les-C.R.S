# 🌦️ MétéoCRS

<p align="center">
  <strong>Site web de prévisions météorologiques augmentée par Machine Learning</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP"/>
  <img src="https://img.shields.io/badge/Python-3.9+-3776AB?style=for-the-badge&logo=python&logoColor=white" alt="Python"/>
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL"/>
</p>

## ✨ Fonctionnalités

### 🤖 Intelligence Artificielle
- **Prédictions ML** en temps réel (température et humidité à +3h)
- **Modèle Random Forest** entraîné sur 2M+ données météorologiques
- **Précision** : ±1.11°C pour la température, ±3.9% pour l'humidité
- **API REST Python** pour servir les prédictions

### 🌍 Interface Utilisateur
- **Design moderne** et responsive (Bootstrap 5)
- **Animations météo** dynamiques (soleil, pluie, neige, orage)
- **Carte interactive** avec données en temps réel (Leaflet)
- **Graphiques historiques** des températures (Chart.js)
- **Recherche intelligente** avec autocomplétion

### 🔧 Architecture Technique
- **Backend PHP** robuste avec PDO
- **Base de données MySQL** optimisée
- **Système de fallback** automatique
- **Architecture microservices** (PHP + Python)

## 🚀 Installation

### Prérequis
- PHP 8.0+
- MySQL 8.0+
- Python 3.8+
- MAMP/WAMP/XAMPP

### 1. Cloner le repository
```bash
git clone https://github.com/caloic/Les-C.R.S.git
cd Les-C.R.S
```

### 2. Configuration de la base de données
```bash
# Importer la structure de la base de données
mysql -u root -p < db/les_crs.sql

# Configurer les identifiants dans config.php
```

### 3. Installation du Machine Learning
```bash
# Créer l'environnement virtuel Python
python -m venv venv

# Activer l'environnement
source venv/bin/activate  # Mac/Linux
venv\Scripts\activate     # Windows

# Installer les dépendances
pip install -r requirements.txt
```

### 4. Import des données météo
```bash
# Nettoyer les données CSV
python clean_meteo_csv.py meteo-0025.csv

# Importer via l'interface web
http://localhost:8888/Les-C.R.S/csv-import.php
```

### 5. Entraîner le modèle ML
```bash
python train_weather_model.py meteo-0025_clean.csv
```

### 6. Lancer l'application
```bash
# Terminal 1 : API Machine Learning
python weather_prediction_api.py

# Terminal 2 : Lancer MAMP et accéder à
http://localhost:8888/Les-C.R.S/
```

## 📊 Performances du Machine Learning

| Métrique | Température | Humidité |
|----------|-------------|----------|
| MAE | 1.11°C | 3.90% |
| R² Score | 0.288 | 0.143 |
| Horizon | 3 heures | 3 heures |

### Features utilisées (26 au total)
- Données météo actuelles (température, humidité, vent, précipitations)
- Données historiques (lag de 1h, 3h, 6h)
- Moyennes mobiles (6h, 24h)
- Features temporelles (heure, jour de la semaine)

## 🏗️ Architecture

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   Frontend      │────▶│   Backend PHP   │────▶│    MySQL DB     │
│  Bootstrap/JS   │     │   Port 8888     │     │  2M+ données    │
└─────────────────┘     └────────┬────────┘     └─────────────────┘
                                 │
                                 │ HTTP/JSON
                                 ▼
                        ┌─────────────────┐     ┌─────────────────┐
                        │   API Python    │────▶│  Random Forest  │
                        │   Flask:5000    │     │     Models      │
                        └─────────────────┘     └─────────────────┘
```

## 📁 Structure du projet

```
meteocrs/
├── 📁 assets/          # Images et icônes
├── 📁 db/              # Scripts SQL
├── 📁 ml_models/       # Modèles ML entraînés
├── 📄 index.php        # Page principale
├── 📄 api.php          # API PHP
├── 📄 functions.php    # Fonctions utilitaires
├── 🐍 train_weather_model.py    # Entraînement ML
├── 🐍 weather_prediction_api.py # API Flask
├── 📊 analyze_model_performance.py # Analyse ML
└── 📝 requirements.txt # Dépendances Python
```

## 🛠️ Technologies utilisées

### Frontend
- HTML5 / CSS3
- JavaScript (Vanilla)
- Bootstrap 5.3.0
- Leaflet.js 1.9.4
- Chart.js
- Font Awesome 6.0

### Backend
- PHP 8.0+
- MySQL 8.0
- PDO

### Machine Learning
- Python 3.9
- Flask 2.3.2
- scikit-learn 1.3.0
- pandas 2.0.3
- numpy 1.24.3

## 📈 Démonstration

### Test de l'intégration ML
```bash
http://localhost:8888/Les-C.R.S/test_ml_integration.php
```

### Génération des graphiques de performance
```bash
python analyze_model_performance.py
```

## 👥 Équipe

- **Benjamin FERRANDEZ** - *Directeur Général*
- **Loïc CANO** - *Développeur*
- **Dylan ARLIN** - *GOAT*
---