#!/usr/bin/env python3
"""
Script d'analyse et de visualisation des performances du modèle ML
Génère des graphiques et des statistiques pour la présentation
"""

import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
import seaborn as sns
import joblib
import json
from datetime import datetime
import os

# Configuration du style des graphiques
plt.style.use('seaborn-v0_8-darkgrid')
sns.set_palette("husl")


def load_models_and_data():
    """Charge les modèles et prépare les données pour l'analyse"""
    print("Chargement des modèles et métadonnées...")

    # Charger les modèles
    temp_model = joblib.load('ml_models/temperature_model.pkl')
    humid_model = joblib.load('ml_models/humidity_model.pkl')
    scaler = joblib.load('ml_models/scaler.pkl')

    # Charger les métadonnées
    with open('ml_models/model_metadata.json', 'r') as f:
        metadata = json.load(f)

    return temp_model, humid_model, scaler, metadata


def create_performance_report(metadata):
    """Crée un rapport de performance en format texte et graphique"""

    # Créer le dossier pour les graphiques
    os.makedirs('ml_analysis', exist_ok=True)

    # 1. Graphique des métriques de performance
    fig, (ax1, ax2) = plt.subplots(1, 2, figsize=(12, 5))

    # Métriques de température
    metrics_temp = metadata['metrics']['temperature']
    ax1.bar(['MAE', 'R²'],
            [metrics_temp['mae'], metrics_temp['r2'] * 100],
            color=['#FF6B6B', '#4ECDC4'])
    ax1.set_title('Performance - Prédiction Température', fontsize=14, fontweight='bold')
    ax1.set_ylabel('Valeur')
    ax1.set_ylim(0, 100)

    # Ajouter les valeurs sur les barres
    for i, (metric, value) in enumerate([('MAE', metrics_temp['mae']), ('R²', metrics_temp['r2'] * 100)]):
        ax1.text(i, value + 1, f'{value:.2f}{"°C" if metric == "MAE" else "%"}',
                 ha='center', fontweight='bold')

    # Métriques d'humidité
    metrics_humid = metadata['metrics']['humidity']
    ax2.bar(['MAE', 'R²'],
            [metrics_humid['mae'], metrics_humid['r2'] * 100],
            color=['#FF6B6B', '#4ECDC4'])
    ax2.set_title('Performance - Prédiction Humidité', fontsize=14, fontweight='bold')
    ax2.set_ylabel('Valeur')
    ax2.set_ylim(0, 100)

    # Ajouter les valeurs sur les barres
    for i, (metric, value) in enumerate([('MAE', metrics_humid['mae']), ('R²', metrics_humid['r2'] * 100)]):
        ax2.text(i, value + 1, f'{value:.2f}{"%" if metric == "MAE" else "%"}',
                 ha='center', fontweight='bold')

    plt.tight_layout()
    plt.savefig('ml_analysis/performance_metrics.png', dpi=300, bbox_inches='tight')
    plt.close()

    print("✅ Graphique des métriques sauvegardé: ml_analysis/performance_metrics.png")


def analyze_feature_importance(temp_model, humid_model, feature_names):
    """Analyse et visualise l'importance des features"""

    # Obtenir l'importance des features
    temp_importance = pd.DataFrame({
        'feature': feature_names,
        'importance': temp_model.feature_importances_
    }).sort_values('importance', ascending=False).head(10)

    humid_importance = pd.DataFrame({
        'feature': feature_names,
        'importance': humid_model.feature_importances_
    }).sort_values('importance', ascending=False).head(10)

    # Créer le graphique
    fig, (ax1, ax2) = plt.subplots(1, 2, figsize=(14, 6))

    # Importance pour la température
    ax1.barh(temp_importance['feature'], temp_importance['importance'])
    ax1.set_xlabel('Importance')
    ax1.set_title('Top 10 Features - Température', fontsize=14, fontweight='bold')
    ax1.invert_yaxis()

    # Importance pour l'humidité
    ax2.barh(humid_importance['feature'], humid_importance['importance'])
    ax2.set_xlabel('Importance')
    ax2.set_title('Top 10 Features - Humidité', fontsize=14, fontweight='bold')
    ax2.invert_yaxis()

    plt.tight_layout()
    plt.savefig('ml_analysis/feature_importance.png', dpi=300, bbox_inches='tight')
    plt.close()

    print("✅ Graphique d'importance des features sauvegardé: ml_analysis/feature_importance.png")

    return temp_importance, humid_importance


def create_prediction_example():
    """Crée un exemple visuel de prédiction"""

    # Données d'exemple
    hours = np.arange(0, 24)
    actual_temp = 15 + 10 * np.sin((hours - 6) * np.pi / 12) + np.random.normal(0, 1, 24)
    predicted_temp = actual_temp[3:] + np.random.normal(0, 2, 21)

    # Créer le graphique
    plt.figure(figsize=(12, 6))

    # Données réelles
    plt.plot(hours, actual_temp, 'b-', linewidth=2, label='Température réelle')

    # Prédictions (décalées de 3 heures)
    plt.plot(hours[3:], predicted_temp, 'r--', linewidth=2, label='Prédiction (3h avant)')

    # Zone de prédiction
    plt.axvspan(20, 23, alpha=0.2, color='red', label='Fenêtre de prédiction')

    # Annotations
    plt.xlabel('Heure de la journée', fontsize=12)
    plt.ylabel('Température (°C)', fontsize=12)
    plt.title('Exemple de Prédiction Météorologique - Horizon 3 heures', fontsize=14, fontweight='bold')
    plt.legend(loc='upper right')
    plt.grid(True, alpha=0.3)

    # Ajouter une annotation
    plt.annotate('Le modèle prédit\nla température\n3 heures à l\'avance',
                 xy=(20, predicted_temp[17]), xytext=(16, 28),
                 arrowprops=dict(arrowstyle='->', color='red', lw=2),
                 fontsize=11, ha='center',
                 bbox=dict(boxstyle="round,pad=0.3", facecolor='yellow', alpha=0.5))

    plt.tight_layout()
    plt.savefig('ml_analysis/prediction_example.png', dpi=300, bbox_inches='tight')
    plt.close()

    print("✅ Exemple de prédiction sauvegardé: ml_analysis/prediction_example.png")


def create_model_architecture_diagram():
    """Crée un diagramme simple de l'architecture du modèle"""

    fig, ax = plt.subplots(figsize=(10, 8))
    ax.axis('off')

    # Titre
    ax.text(0.5, 0.95, 'Architecture du Système de Prédiction Météo ML',
            ha='center', fontsize=16, fontweight='bold')

    # Définir les boîtes
    boxes = [
        {'xy': (0.2, 0.8), 'text': 'Données Météo\nActuelles', 'color': '#3498db'},
        {'xy': (0.5, 0.8), 'text': 'Données\nHistoriques', 'color': '#3498db'},
        {'xy': (0.8, 0.8), 'text': 'Features\nTemporelles', 'color': '#3498db'},
        {'xy': (0.5, 0.6), 'text': 'Préparation des Features\n(Lag, Moyennes mobiles)', 'color': '#e74c3c'},
        {'xy': (0.5, 0.4), 'text': 'Normalisation\n(StandardScaler)', 'color': '#f39c12'},
        {'xy': (0.3, 0.2), 'text': 'Random Forest\nTempérature', 'color': '#27ae60'},
        {'xy': (0.7, 0.2), 'text': 'Random Forest\nHumidité', 'color': '#27ae60'},
        {'xy': (0.5, 0.05), 'text': 'Prédictions\n(+3 heures)', 'color': '#9b59b6'}
    ]

    # Dessiner les boîtes
    for box in boxes:
        rect = plt.Rectangle((box['xy'][0] - 0.08, box['xy'][1] - 0.03),
                             0.16, 0.06,
                             facecolor=box['color'],
                             alpha=0.3,
                             edgecolor=box['color'],
                             linewidth=2)
        ax.add_patch(rect)
        ax.text(box['xy'][0], box['xy'][1], box['text'],
                ha='center', va='center', fontsize=10, fontweight='bold')

    # Dessiner les flèches
    arrows = [
        ((0.2, 0.77), (0.45, 0.63)),
        ((0.5, 0.77), (0.5, 0.63)),
        ((0.8, 0.77), (0.55, 0.63)),
        ((0.5, 0.57), (0.5, 0.43)),
        ((0.5, 0.37), (0.35, 0.23)),
        ((0.5, 0.37), (0.65, 0.23)),
        ((0.3, 0.17), (0.45, 0.08)),
        ((0.7, 0.17), (0.55, 0.08))
    ]

    for start, end in arrows:
        ax.annotate('', xy=end, xytext=start,
                    arrowprops=dict(arrowstyle='->', lw=2, color='black'))

    ax.set_xlim(0, 1)
    ax.set_ylim(0, 1)

    plt.tight_layout()
    plt.savefig('ml_analysis/model_architecture.png', dpi=300, bbox_inches='tight')
    plt.close()

    print("✅ Diagramme d'architecture sauvegardé: ml_analysis/model_architecture.png")


def generate_performance_report(metadata):
    """Génère un rapport texte des performances"""

    report = f"""
# Rapport de Performance du Modèle ML - MétéoCRS
Date de génération: {datetime.now().strftime('%Y-%m-%d %H:%M')}

## Informations du Modèle
- Type: {metadata['model_type']}
- Date d'entraînement: {metadata['training_date']}
- Horizon de prédiction: {metadata['prediction_horizon']}

## Métriques de Performance

### Prédiction de Température
- MAE (Mean Absolute Error): {metadata['metrics']['temperature']['mae']:.2f}°C
- R² Score: {metadata['metrics']['temperature']['r2']:.3f}
- Interprétation: Le modèle prédit la température avec une erreur moyenne de {metadata['metrics']['temperature']['mae']:.1f}°C

### Prédiction d'Humidité
- MAE (Mean Absolute Error): {metadata['metrics']['humidity']['mae']:.2f}%
- R² Score: {metadata['metrics']['humidity']['r2']:.3f}
- Interprétation: Le modèle prédit l'humidité avec une erreur moyenne de {metadata['metrics']['humidity']['mae']:.1f}%

## Features Utilisées
Le modèle utilise {len(metadata['feature_columns'])} features incluant:
- Données météo actuelles (température, humidité, vitesse du vent)
- Valeurs historiques (1h, 3h, 6h)
- Moyennes mobiles (6h, 24h)
- Features temporelles (heure, jour de la semaine)

## Recommandations
- Réentraîner le modèle mensuellement avec les nouvelles données
- Monitorer les performances en production
- Considérer l'ajout de features supplémentaires (pression, direction du vent)
"""

    with open('ml_analysis/performance_report.txt', 'w', encoding='utf-8') as f:
        f.write(report)

    print("✅ Rapport de performance sauvegardé: ml_analysis/performance_report.txt")

    return report


def main():
    """Fonction principale"""
    print("🔍 Analyse des performances du modèle ML...")

    try:
        # Charger les modèles et métadonnées
        temp_model, humid_model, scaler, metadata = load_models_and_data()

        # Générer les analyses
        print("\n📊 Génération des graphiques...")

        # 1. Métriques de performance
        create_performance_report(metadata)

        # 2. Importance des features
        feature_names = metadata['feature_columns']
        analyze_feature_importance(temp_model, humid_model, feature_names)

        # 3. Exemple de prédiction
        create_prediction_example()

        # 4. Architecture du modèle
        create_model_architecture_diagram()

        # 5. Rapport texte
        report = generate_performance_report(metadata)

        print("\n" + "=" * 50)
        print("✅ Analyse terminée avec succès!")
        print("=" * 50)
        print("\nFichiers générés dans le dossier 'ml_analysis/':")
        print("- performance_metrics.png : Graphique des métriques MAE et R²")
        print("- feature_importance.png : Importance des features pour les prédictions")
        print("- prediction_example.png : Exemple visuel de prédiction")
        print("- model_architecture.png : Diagramme de l'architecture")
        print("- performance_report.txt : Rapport détaillé des performances")
        print("\n💡 Utilisez ces éléments pour votre présentation!")

    except Exception as e:
        print(f"❌ Erreur: {str(e)}")
        print("Assurez-vous d'avoir d'abord entraîné le modèle avec train_weather_model.py")


if __name__ == "__main__":
    main()