import pandas as pd
import numpy as np
from sklearn.model_selection import train_test_split, cross_val_score
from sklearn.ensemble import RandomForestRegressor
from sklearn.preprocessing import StandardScaler
from sklearn.metrics import mean_absolute_error, r2_score
import joblib
import json
from datetime import datetime
import os
import sys


def load_and_prepare_data(csv_file):
    """
    Charge et prépare les données météorologiques pour l'entraînement
    """
    print(f"Chargement du fichier: {csv_file}")

    try:
        # Charger le CSV avec le bon séparateur
        df = pd.read_csv(csv_file, sep=';')
        print(f"Données chargées: {len(df)} lignes, {len(df.columns)} colonnes")

        # Colonnes essentielles pour les features
        feature_columns = [
            '2 metre temperature',
            '2 metre relative humidity',
            '10m wind speed',
            'Total precipitation'
        ]

        # Vérifier que toutes les colonnes existent
        missing_cols = [col for col in feature_columns if col not in df.columns]
        if missing_cols:
            print(f"Colonnes manquantes: {missing_cols}")
            # Utiliser les colonnes disponibles
            feature_columns = [col for col in feature_columns if col in df.columns]

        # Nettoyer les données
        df_clean = df[feature_columns].dropna()
        print(f"Données après nettoyage: {len(df_clean)} lignes")

        # Créer des features temporelles à partir de l'index
        df_clean['hour'] = np.arange(len(df_clean)) % 24
        df_clean['day_of_week'] = (np.arange(len(df_clean)) // 24) % 7

        # Créer des features de décalage (valeurs passées)
        for col in feature_columns:
            if col in df_clean.columns:
                df_clean[f'{col}_lag_1h'] = df_clean[col].shift(1)
                df_clean[f'{col}_lag_3h'] = df_clean[col].shift(3)
                df_clean[f'{col}_lag_6h'] = df_clean[col].shift(6)

                # Moyennes mobiles
                df_clean[f'{col}_ma_6h'] = df_clean[col].rolling(window=6, min_periods=1).mean()
                df_clean[f'{col}_ma_24h'] = df_clean[col].rolling(window=24, min_periods=1).mean()

        # Supprimer les lignes avec des NaN créés par les décalages
        df_clean = df_clean.dropna()

        # Créer les targets (prédiction à 3 heures)
        df_clean['temperature_future'] = df_clean['2 metre temperature'].shift(-3)
        df_clean['humidity_future'] = df_clean['2 metre relative humidity'].shift(-3)

        # Supprimer les dernières lignes sans target
        df_clean = df_clean.dropna()

        print(f"Données finales: {len(df_clean)} lignes")
        print(f"Features créées: {list(df_clean.columns)}")

        return df_clean

    except Exception as e:
        print(f"Erreur lors du chargement des données: {str(e)}")
        return None


def train_models(df):
    """
    Entraîne deux modèles: un pour la température, un pour l'humidité
    """
    # Séparer features et targets
    feature_cols = [col for col in df.columns if col not in ['temperature_future', 'humidity_future']]
    X = df[feature_cols]
    y_temp = df['temperature_future']
    y_humid = df['humidity_future']

    # Diviser en train/test
    X_train, X_test, y_temp_train, y_temp_test, y_humid_train, y_humid_test = train_test_split(
        X, y_temp, y_humid, test_size=0.2, random_state=42
    )

    # Normaliser les features
    scaler = StandardScaler()
    X_train_scaled = scaler.fit_transform(X_train)
    X_test_scaled = scaler.transform(X_test)

    print("\n=== Entraînement du modèle de température ===")
    # Modèle pour la température
    temp_model = RandomForestRegressor(
        n_estimators=100,
        max_depth=10,
        random_state=42,
        n_jobs=-1
    )
    temp_model.fit(X_train_scaled, y_temp_train)

    # Évaluation température
    y_temp_pred = temp_model.predict(X_test_scaled)
    temp_mae = mean_absolute_error(y_temp_test, y_temp_pred)
    temp_r2 = r2_score(y_temp_test, y_temp_pred)

    print(f"MAE Température: {temp_mae:.2f}°C")
    print(f"R² Température: {temp_r2:.3f}")

    # Cross-validation
    cv_scores = cross_val_score(temp_model, X_train_scaled, y_temp_train, cv=5, scoring='neg_mean_absolute_error')
    print(f"Cross-validation MAE: {-cv_scores.mean():.2f} (+/- {cv_scores.std() * 2:.2f})")

    print("\n=== Entraînement du modèle d'humidité ===")
    # Modèle pour l'humidité
    humid_model = RandomForestRegressor(
        n_estimators=100,
        max_depth=10,
        random_state=42,
        n_jobs=-1
    )
    humid_model.fit(X_train_scaled, y_humid_train)

    # Évaluation humidité
    y_humid_pred = humid_model.predict(X_test_scaled)
    humid_mae = mean_absolute_error(y_humid_test, y_humid_pred)
    humid_r2 = r2_score(y_humid_test, y_humid_pred)

    print(f"MAE Humidité: {humid_mae:.2f}%")
    print(f"R² Humidité: {humid_r2:.3f}")

    # Feature importance
    print("\n=== Importance des features (Top 10) ===")
    feature_importance = pd.DataFrame({
        'feature': feature_cols,
        'importance_temp': temp_model.feature_importances_,
        'importance_humid': humid_model.feature_importances_
    }).sort_values('importance_temp', ascending=False).head(10)

    print(feature_importance.to_string())

    return {
        'temp_model': temp_model,
        'humid_model': humid_model,
        'scaler': scaler,
        'feature_cols': feature_cols,
        'metrics': {
            'temperature': {
                'mae': float(temp_mae),
                'r2': float(temp_r2)
            },
            'humidity': {
                'mae': float(humid_mae),
                'r2': float(humid_r2)
            }
        }
    }


def save_models(models_dict, output_dir='ml_models'):
    """
    Sauvegarde les modèles entraînés et les métadonnées
    """
    # Créer le répertoire s'il n'existe pas
    os.makedirs(output_dir, exist_ok=True)

    # Sauvegarder les modèles
    joblib.dump(models_dict['temp_model'], os.path.join(output_dir, 'temperature_model.pkl'))
    joblib.dump(models_dict['humid_model'], os.path.join(output_dir, 'humidity_model.pkl'))
    joblib.dump(models_dict['scaler'], os.path.join(output_dir, 'scaler.pkl'))

    # Sauvegarder les métadonnées
    metadata = {
        'feature_columns': models_dict['feature_cols'],
        'metrics': models_dict['metrics'],
        'training_date': datetime.now().isoformat(),
        'model_type': 'RandomForestRegressor',
        'prediction_horizon': '3 hours'
    }

    with open(os.path.join(output_dir, 'model_metadata.json'), 'w') as f:
        json.dump(metadata, f, indent=2)

    print(f"\nModèles sauvegardés dans le répertoire '{output_dir}'")
    print("Fichiers créés:")
    print("- temperature_model.pkl")
    print("- humidity_model.pkl")
    print("- scaler.pkl")
    print("- model_metadata.json")


def main():
    """Fonction principale"""
    # Fichier CSV à utiliser
    csv_file = 'meteo-0025_clean.csv'

    if len(sys.argv) > 1:
        csv_file = sys.argv[1]

    if not os.path.exists(csv_file):
        print(f"Erreur: Le fichier '{csv_file}' n'existe pas")
        print("Usage: python train_weather_model.py [fichier_csv]")
        sys.exit(1)

    # Charger et préparer les données
    df = load_and_prepare_data(csv_file)

    if df is None or df.empty:
        print("Erreur: Impossible de charger ou préparer les données")
        sys.exit(1)

    # Entraîner les modèles
    models = train_models(df)

    # Sauvegarder les modèles
    save_models(models)

    print("\n✅ Entraînement terminé avec succès!")
    print(f"Les modèles peuvent maintenant être utilisés pour faire des prédictions.")


if __name__ == "__main__":
    main()