<?php
define('DB_SERVER', getenv('DB_SERVER'));
define('DB_USERNAME', getenv('DB_USERNAME'));
define('DB_PASSWORD', getenv('DB_PASSWORD'));
define('DB_NAME', getenv('DB_NAME'));

function dbConnection() {
    $connection = null;
    try {
        $connection = new PDO('sqlsrv:server=' . DB_SERVER . ';Database=' . DB_NAME . ';Encrypt=no', DB_USERNAME, DB_PASSWORD);
        http_response_code(200);
        echo json_encode(['ok' => true, 'message' => 'Conexión establecida'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    catch (PDOException $error) {
        http_response_code(500);
        echo json_encode(['error' => true, 'message' => 'Error de conexión: ' . $error->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return $connection;
}
dbConnection();
?>
