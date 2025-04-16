<?php
header('Content-Type: text/html; charset=utf-8');

// Tester l'API WeatherAPI directement
$apiKey = '4d7bd0c96dbe413199690446250503'; // Votre clé actuelle
$location = 'Paris';
$url = "https://api.weatherapi.com/v1/forecast.json?key={$apiKey}&q={$location}&days=7&aqi=no&alerts=no&lang=fr";

echo "<h2>Test d'appel direct à WeatherAPI.com</h2>";
echo "URL: " . htmlspecialchars($url) . "<br>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Parfois nécessaire en environnement local
$response = curl_exec($ch);
$info = curl_getinfo($ch);
$error = curl_error($ch);
curl_close($ch);

echo "<h3>Informations sur la requête</h3>";
echo "Code HTTP: " . $info['http_code'] . "<br>";
echo "Temps d'exécution: " . $info['total_time'] . " secondes<br>";
echo "Erreur curl: " . ($error ?: "Aucune") . "<br>";

echo "<h3>Réponse de l'API</h3>";
echo "<pre>";
if ($info['http_code'] == 200) {
    $data = json_decode($response, true);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo htmlspecialchars($response);
}
echo "</pre>";
?>