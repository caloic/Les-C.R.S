from flask import Flask, request, jsonify
from flask_cors import CORS
import joblib
import pandas as pd
import numpy as np
import json
import os
from datetime import datetime
import logging

# Configuration du logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Initialiser Flask
app = Flask(__name__)
CORS(app)  # Permettre les requêtes cross-origin

# Variables globales pour les modèles
models = {}


def load_models():
    """
    Charge les modèles entraînés et les métadonnées
    """
    global models

    model_dir = 'ml_models'

    try:
        # Charger les modèles
        models['temp_model'] = joblib.load(os.path.join(model_dir, 'temperature_model.pkl'))
        models['humid_model'] = joblib.load(os.path.join(model_dir, 'humidity_model.pkl'))
        models['scaler'] = joblib.load(os.path.join(model_dir, 'scaler.pkl'))

        # Charger les métadonnées
        with open(os.path.join(model_dir, 'model_metadata.json'), 'r') as f:
            models['metadata'] = json.load(f)

        logger.info("Modèles chargés avec succès")
        return True
    except Exception as e:
        logger.error(f"Erreur lors du chargement des modèles: {str(e)}")
        return False


def prepare_features(weather_data, historical_data=None):
    """
    Prépare les features pour la prédiction à partir des données météo actuelles
    """
    try:
        # Créer un DataFrame avec les données actuelles
        current_data = {
            '2 metre temperature': float(weather_data.get('temperature', 20)),
            '2 metre relative humidity': float(weather_data.get('humidity', 60)),
            '10m wind speed': float(weather_data.get('wind_speed', 10)),
            'Total precipitation': float(weather_data.get('precipitation', 0))
        }

        # Si on a des données historiques, les utiliser pour les features de décalage
        if historical_data and len(historical_data) >= 24:
            # Convertir en DataFrame
            hist_df = pd.DataFrame(historical_data)

            # Ajouter les données actuelles
            current_df = pd.DataFrame([current_data])
            df = pd.concat([hist_df, current_df], ignore_index=True)

            # Créer les features de décalage pour la dernière ligne
            for col in current_data.keys():
                if col in df.columns:
                    current_data[f'{col}_lag_1h'] = df[col].iloc[-2] if len(df) > 1 else current_data[col]
                    current_data[f'{col}_lag_3h'] = df[col].iloc[-4] if len(df) > 3 else current_data[col]
                    current_data[f'{col}_lag_6h'] = df[col].iloc[-7] if len(df) > 6 else current_data[col]
                    current_data[f'{col}_ma_6h'] = df[col].tail(6).mean()
                    current_data[f'{col}_ma_24h'] = df[col].tail(24).mean()
        else:
            # Pas de données historiques, utiliser les valeurs actuelles
            for col in list(current_data.keys()):
                current_data[f'{col}_lag_1h'] = current_data[col]
                current_data[f'{col}_lag_3h'] = current_data[col]
                current_data[f'{col}_lag_6h'] = current_data[col]
                current_data[f'{col}_ma_6h'] = current_data[col]
                current_data[f'{col}_ma_24h'] = current_data[col]

        # Ajouter les features temporelles
        now = datetime.now()
        current_data['hour'] = now.hour
        current_data['day_of_week'] = now.weekday()

        # Créer un DataFrame avec toutes les features dans le bon ordre
        feature_cols = models['metadata']['feature_columns']
        features_df = pd.DataFrame([current_data])

        # S'assurer que toutes les colonnes sont présentes
        for col in feature_cols:
            if col not in features_df.columns:
                features_df[col] = 0  # Valeur par défaut

        # Réordonner les colonnes
        features_df = features_df[feature_cols]

        return features_df

    except Exception as e:
        logger.error(f"Erreur lors de la préparation des features: {str(e)}")
        return None


@app.route('/health', methods=['GET'])
def health_check():
    """Endpoint de vérification de santé"""
    return jsonify({
        'status': 'healthy',
        'models_loaded': bool(models),
        'timestamp': datetime.now().isoformat()
    })


@app.route('/predict', methods=['POST'])
def predict():
    """
    Endpoint principal pour obtenir des prédictions météo

    Format de requête attendu:
    {
        "current_weather": {
            "temperature": 20,
            "humidity": 65,
            "wind_speed": 15,
            "precipitation": 0
        },
        "historical_data": [...]  // Optionnel
    }
    """
    try:
        # Vérifier que les modèles sont chargés
        if not models:
            return jsonify({
                'success': False,
                'error': 'Modèles non chargés'
            }), 500

        # Récupérer les données de la requête
        data = request.get_json()

        if not data or 'current_weather' not in data:
            return jsonify({
                'success': False,
                'error': 'Données météo actuelles requises'
            }), 400

        # Préparer les features
        features_df = prepare_features(
            data['current_weather'],
            data.get('historical_data', None)
        )

        if features_df is None:
            return jsonify({
                'success': False,
                'error': 'Erreur lors de la préparation des données'
            }), 500

        # Normaliser les features
        features_scaled = models['scaler'].transform(features_df)

        # Faire les prédictions
        temp_pred = models['temp_model'].predict(features_scaled)[0]
        humid_pred = models['humid_model'].predict(features_scaled)[0]

        # S'assurer que les prédictions sont dans des plages valides
        temp_pred = max(-50, min(60, temp_pred))
        humid_pred = max(0, min(100, humid_pred))

        # Calculer l'intervalle de confiance (approximatif)
        temp_std = 2.5  # Écart-type approximatif basé sur les performances du modèle
        humid_std = 5.0

        response = {
            'success': True,
            'predictions': {
                'temperature': {
                    'value': round(float(temp_pred), 1),
                    'unit': '°C',
                    'confidence_interval': {
                        'lower': round(float(temp_pred - temp_std), 1),
                        'upper': round(float(temp_pred + temp_std), 1)
                    }
                },
                'humidity': {
                    'value': round(float(humid_pred)),
                    'unit': '%',
                    'confidence_interval': {
                        'lower': round(max(0, float(humid_pred - humid_std))),
                        'upper': round(min(100, float(humid_pred + humid_std)))
                    }
                },
                'horizon': '3 hours',
                'timestamp': datetime.now().isoformat()
            },
            'model_info': {
                'type': models['metadata']['model_type'],
                'training_date': models['metadata']['training_date'],
                'metrics': models['metadata']['metrics']
            }
        }

        return jsonify(response)

    except Exception as e:
        logger.error(f"Erreur lors de la prédiction: {str(e)}")
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500


@app.route('/batch_predict', methods=['POST'])
def batch_predict():
    """
    Endpoint pour prédire plusieurs emplacements à la fois
    """
    try:
        data = request.get_json()

        if not data or 'locations' not in data:
            return jsonify({
                'success': False,
                'error': 'Liste de localisations requise'
            }), 400

        predictions = []

        for location in data['locations']:
            # Préparer les features pour chaque localisation
            features_df = prepare_features(
                location.get('current_weather', {}),
                location.get('historical_data', None)
            )

            if features_df is not None:
                # Normaliser et prédire
                features_scaled = models['scaler'].transform(features_df)
                temp_pred = models['temp_model'].predict(features_scaled)[0]
                humid_pred = models['humid_model'].predict(features_scaled)[0]

                predictions.append({
                    'location_id': location.get('id', 'unknown'),
                    'location_name': location.get('name', 'Unknown'),
                    'temperature': round(float(temp_pred), 1),
                    'humidity': round(float(humid_pred))
                })

        return jsonify({
            'success': True,
            'predictions': predictions,
            'timestamp': datetime.now().isoformat()
        })

    except Exception as e:
        logger.error(f"Erreur lors de la prédiction batch: {str(e)}")
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500


@app.route('/model_info', methods=['GET'])
def model_info():
    """
    Retourne des informations sur les modèles chargés
    """
    if not models:
        return jsonify({
            'success': False,
            'error': 'Modèles non chargés'
        }), 500

    return jsonify({
        'success': True,
        'metadata': models['metadata'],
        'status': 'operational',
        'timestamp': datetime.now().isoformat()
    })


if __name__ == '__main__':
    # Charger les modèles au démarrage
    if load_models():
        print("✅ API de prédiction météo démarrée!")
        print("📍 Endpoints disponibles:")
        print("   - POST /predict : Prédiction pour une localisation")
        print("   - POST /batch_predict : Prédictions pour plusieurs localisations")
        print("   - GET /model_info : Informations sur les modèles")
        print("   - GET /health : Vérification de santé")

        # Démarrer le serveur
        app.run(host='0.0.0.0', port=5000, debug=True)
    else:
        print("❌ Erreur: Impossible de charger les modèles")
        print("Assurez-vous d'avoir exécuté train_weather_model.py d'abord")