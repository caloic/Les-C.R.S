# Guide d'installation du système Machine Learning pour MétéoCRS

## 📋 Vue d'ensemble

Ce système de Machine Learning prédit la température et l'humidité à 3 heures en utilisant un modèle Random Forest entraîné sur vos données météorologiques historiques.

## 🚀 Installation rapide

### 1. Prérequis
- Python 3.8 ou supérieur
- pip (gestionnaire de paquets Python)
- Votre fichier CSV de données météo (`meteo-0025_clean.csv`)

### 2. Installation des dépendances

```bash
# Créer un environnement virtuel (recommandé)
python -m venv venv

# Activer l'environnement virtuel
# Sur Windows:
venv\Scripts\activate
# Sur Mac/Linux:
source venv/bin/activate

# Installer les dépendances
pip install -r requirements.txt
```

### 3. Entraîner le modèle

```bash
# Assurez-vous que votre fichier CSV est présent
python train_weather_model.py meteo-0025_clean.csv
```

Cela va :
- Charger et nettoyer vos données
- Créer des features temporelles et de décalage
- Entraîner deux modèles (température et humidité)
- Sauvegarder les modèles dans le dossier `ml_models/`
- Afficher les métriques de performance

### 4. Lancer l'API de prédiction

```bash
python weather_prediction_api.py
```

L'API sera accessible sur `http://localhost:5000`

### 5. Intégrer avec PHP

Ajoutez ceci dans votre `functions.php` après la ligne `require_once 'config.php';` :

```php
require_once 'ml_prediction_service.php';
```

Puis modifiez la fonction `makePrediction` dans `functions.php` :

```php
function makePrediction($locationId, $currentTemp, $currentHumidity) {
    global $pdo;
    
    try {
        // D'abord essayer d'obtenir une prédiction ML
        $mlPrediction = getMLPrediction($locationId, [
            'temperature' => $currentTemp,
            'humidity' => $currentHumidity,
            'wind_speed' => 10, // Valeur par défaut ou récupérée de la DB
            'precipitation' => 0
        ]);
        
        if ($mlPrediction) {
            // Utiliser la prédiction ML
            return $mlPrediction;
        }
        
        // Fallback sur l'ancienne méthode si ML non disponible
        // ... (garder votre code existant comme fallback)
    } catch (Exception $e) {
        error_log('Erreur ML: ' . $e->getMessage());
        // Fallback sur l'ancienne méthode
    }
}
```

## 📊 Comprendre les résultats

### Métriques affichées lors de l'entraînement :

- **MAE (Mean Absolute Error)** : Erreur moyenne en °C ou %
  - Température : ~2°C est excellent
  - Humidité : ~5% est très bon

- **R² Score** : Qualité de la prédiction (0 à 1)
  - 0.7+ = Bon
  - 0.8+ = Très bon
  - 0.9+ = Excellent

### Features importantes :
Le modèle utilise :
- Température/humidité actuelles
- Valeurs des 1h, 3h, 6h précédentes
- Moyennes mobiles 6h et 24h
- Heure du jour et jour de la semaine

## 🔧 Configuration avancée

### Modifier les paramètres du modèle

Dans `train_weather_model.py`, vous pouvez ajuster :

```python
# Nombre d'arbres dans la forêt
n_estimators=100  # Augmenter pour plus de précision

# Profondeur maximale des arbres
max_depth=10  # Augmenter pour capturer plus de complexité

# Horizon de prédiction (ligne 86)
df_clean['temperature_future'] = df_clean['2 metre temperature'].shift(-3)  # -3 = 3 heures
```

### Utiliser l'API directement

```bash
# Test de santé
curl http://localhost:5000/health

# Prédiction simple
curl -X POST http://localhost:5000/predict \
  -H "Content-Type: application/json" \
  -d '{
    "current_weather": {
      "temperature": 20,
      "humidity": 65,
      "wind_speed": 15,
      "precipitation": 0
    }
  }'
```

## 📈 Améliorer les performances

1. **Plus de données** : Plus vous avez de données historiques, meilleures seront les prédictions

2. **Features supplémentaires** : Ajouter pression, direction du vent, etc.

3. **Réentraîner régulièrement** : Tous les mois avec les nouvelles données

4. **Tuning hyperparamètres** : Utiliser GridSearchCV pour optimiser

## 🐛 Dépannage

### "ModuleNotFoundError"
→ Vérifiez que l'environnement virtuel est activé et les dépendances installées

### "Erreur API ML non disponible"
→ Vérifiez que l'API Python est lancée sur le port 5000

### Prédictions peu précises
→ Vérifiez la qualité des données d'entrée et réentraînez avec plus de données

### Erreur de mémoire
→ Réduisez `n_estimators` ou utilisez un échantillon des données

## 🎯 Prochaines étapes

1. **Monitoring** : Ajouter des logs et métriques de performance
2. **Déploiement** : Utiliser Gunicorn pour la production
3. **Amélioration** : Tester d'autres algorithmes (XGBoost, LSTM)
4. **Visualisation** : Créer des graphiques de performance

## 📝 Commandes utiles

```bash
# Réentraîner le modèle
python train_weather_model.py

# Lancer l'API en arrière-plan
nohup python weather_prediction_api.py > api.log 2>&1 &

# Voir les logs de l'API
tail -f api.log

# Tester que tout fonctionne
python -c "import requests; print(requests.get('http://localhost:5000/health').json())"
```

## ✅ Checklist pour la soutenance

- [ ] Modèle entraîné avec de bonnes métriques
- [ ] API Python fonctionnelle
- [ ] Intégration PHP opérationnelle
- [ ] Prédictions affichées sur le site
- [ ] Documentation des performances (MAE, R²)
- [ ] Explication de l'architecture ML