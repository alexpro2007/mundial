<?php
require_once 'config.php';

function testApiFootball($endpoint) {
    $url = API_FOOTBALL_BASE_URL . $endpoint;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-rapidapi-host: v3.football.api-sports.io',
        'x-apisports-key: ' . API_FOOTBALL_KEY
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    
    if(curl_errno($ch)){
        echo 'Curl error: ' . curl_error($ch) . "\n";
    }

    curl_close($ch);
    return json_decode($response, true);
}

// Probar conexión básica
echo "Probando conexión con API-Football...\n";
$status = testApiFootball('status');

if (isset($status['errors']) && !empty($status['errors'])) {
    echo "Errores de API:\n";
    print_r($status['errors']);
} else {
    echo "Estado de la cuenta:\n";
    print_r($status['response']);
}

echo "\nProbando ligas (La Liga = 140):\n";
$leagues = testApiFootball('leagues?id=140');
print_r($leagues['response']);

?>
