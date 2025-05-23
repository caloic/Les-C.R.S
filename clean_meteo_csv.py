import pandas as pd
import numpy as np
import os
import sys

def clean_meteo_csv(input_file, output_file=None, missing_threshold=0.0, conversion_threshold=0.1):
    """
    Nettoie un fichier CSV de données météorologiques en supprimant les lignes incomplètes
    et en vérifiant la validité des données numériques.

    Args:
        input_file (str): Chemin vers le fichier CSV d'entrée
        output_file (str, optional): Chemin pour le fichier CSV de sortie. Si non spécifié,
                                      utilisera le nom du fichier d'entrée avec '_clean' ajouté.
        missing_threshold (float, optional): Seuil de valeurs manquantes pour suppression de ligne (par défaut 0%).
        conversion_threshold (float, optional): Seuil pour la proportion de données non numériques tolérées dans les colonnes numériques (par défaut 10%).

    Returns:
        tuple: (DataFrame nettoyé, statistiques de nettoyage)
    """
    # Définir le nom du fichier de sortie si non spécifié
    if output_file is None:
        base_name = os.path.splitext(input_file)[0]
        output_file = f"{base_name}_clean.csv"

    print(f"Lecture du fichier: {input_file}")

    try:
        # Charger le CSV avec séparateur point-virgule
        df = pd.read_csv(input_file, sep=';')

        # Statistiques initiales
        initial_rows = len(df)
        print(f"Nombre de lignes initiales: {initial_rows}")
        print(f"Colonnes: {', '.join(df.columns)}")

        # 1. Identification des colonnes essentielles
        essential_columns = [
            'Forecast timestamp',
            'Position',
            '2 metre temperature'
        ]

        # 2. Supprimer les lignes où les colonnes essentielles sont manquantes
        rows_before = len(df)
        df = df.dropna(subset=essential_columns)
        rows_after = len(df)
        missing_essential = rows_before - rows_after
        print(f"Lignes supprimées pour valeurs essentielles manquantes: {missing_essential}")

        # 3. Vérifier et convertir les colonnes numériques
        numeric_columns = [
            '2 metre temperature',
            'Minimum temperature at 2 metres',
            'Maximum temperature at 2 metres',
            '2 metre relative humidity',
            'Total precipitation',
            '10m wind speed'
        ]

        # Stats pour les erreurs de conversion
        conversion_errors = 0
        conversion_rows = []

        for col in numeric_columns:
            if col in df.columns:
                # Compter les valeurs non-numériques
                non_numeric = pd.to_numeric(df[col], errors='coerce').isna() & ~df[col].isna()
                conversion_errors += non_numeric.sum()

                # Convertir en numérique, remplacer les erreurs par NaN
                df[col] = pd.to_numeric(df[col], errors='coerce')

                # Vérifier la proportion de valeurs invalides dans la colonne
                invalid_percentage = (df[col].isna().sum() / len(df)) * 100
                if invalid_percentage > conversion_threshold * 100:
                    conversion_rows.append(f"{col}: {invalid_percentage:.2f}% de valeurs invalides")
                    df = df.dropna(subset=[col])  # Supprimer les lignes où cette colonne a des valeurs invalides

        print(f"Erreurs de conversion numérique: {conversion_errors}")
        if conversion_rows:
            print("Colonnes avec trop de valeurs invalides:")
            for row in conversion_rows:
                print(row)

        # 4. Vérifier que les coordonnées GPS sont valides
        # Extraire latitude et longitude de la colonne Position
        if 'Position' in df.columns:
            # Créer une fonction pour extraire et valider les coordonnées
            def extract_coordinates(pos_str):
                try:
                    parts = pos_str.split(',')
                    if len(parts) != 2:
                        return np.nan, np.nan

                    lat = float(parts[0].strip())
                    lon = float(parts[1].strip())

                    # Valider les coordonnées (plage valide)
                    if -90 <= lat <= 90 and -180 <= lon <= 180:
                        return lat, lon
                    else:
                        return np.nan, np.nan
                except:
                    return np.nan, np.nan

            # Appliquer la fonction et créer deux nouvelles colonnes
            coordinates = df['Position'].apply(extract_coordinates)
            df['Latitude'] = coordinates.apply(lambda x: x[0])
            df['Longitude'] = coordinates.apply(lambda x: x[1])

            # Supprimer les lignes avec des coordonnées invalides
            invalid_coords = df['Latitude'].isna() | df['Longitude'].isna()
            invalid_coords_count = invalid_coords.sum()

            if invalid_coords_count > 0:
                print(f"Lignes avec coordonnées GPS invalides: {invalid_coords_count}")
                df = df[~invalid_coords]

        # 5. Supprimer les lignes avec **n'importe quelle valeur manquante**
        rows_before = len(df)
        df = df.dropna()  # Supprime toutes les lignes où il y a une seule valeur manquante
        rows_after = len(df)
        too_many_missing = rows_before - rows_after
        print(f"Lignes supprimées pour valeurs manquantes: {too_many_missing}")

        # 6. Enregistrer le DataFrame nettoyé
        df.to_csv(output_file, sep=';', index=False)

        # Statistiques finales
        cleaned_rows = len(df)
        removed_rows = initial_rows - cleaned_rows
        removal_percent = (removed_rows / initial_rows) * 100 if initial_rows > 0 else 0

        stats = {
            'initial_rows': initial_rows,
            'cleaned_rows': cleaned_rows,
            'removed_rows': removed_rows,
            'removal_percent': removal_percent,
            'missing_essential': missing_essential,
            'conversion_errors': conversion_errors,
            'too_many_missing': too_many_missing
        }

        print(f"\nFichier nettoyé enregistré: {output_file}")
        print(f"Lignes finales: {cleaned_rows} (supprimé {removed_rows} lignes, {removal_percent:.2f}%)")

        return df, stats

    except Exception as e:
        print(f"Erreur lors du nettoyage du fichier: {str(e)}")
        return None, {'error': str(e)}

def main():
    """Fonction principale pour exécuter le script depuis la ligne de commande"""
    if len(sys.argv) < 2:
        print("Usage: python clean_meteo_csv.py input_file.csv [output_file.csv] [missing_threshold] [conversion_threshold]")
        sys.exit(1)

    input_file = sys.argv[1]
    output_file = sys.argv[2] if len(sys.argv) > 2 else None
    missing_threshold = float(sys.argv[3]) if len(sys.argv) > 3 else 0.0  # Suppression stricte des lignes avec une valeur manquante
    conversion_threshold = float(sys.argv[4]) if len(sys.argv) > 4 else 0.1  # Seuil plus strict pour les erreurs de conversion

    clean_meteo_csv(input_file, output_file, missing_threshold, conversion_threshold)

if __name__ == "__main__":
    main()
