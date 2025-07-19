<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration pour Render avec PostgreSQL
$database_url = getenv('DATABASE_URL');

if ($database_url) {
    // Parse DATABASE_URL pour Render
    $url = parse_url($database_url);
    
    // Vérifier que l'URL est valide
    if ($url === false || !isset($url['host'])) {
        die("URL de base de données invalide");
    }
    
    $host = $url['host'];
    $port = isset($url['port']) ? $url['port'] : 5432;
    $dbname = substr($url['path'], 1);
    $username = $url['user'];
    $password = $url['pass'];
    
    // Connexion PostgreSQL avec PDO
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$username;password=$password";
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Créer une connexion mysqli compatible pour votre code existant
        $conn = new mysqli($host, $username, $password, $dbname, $port);
        
        if ($conn->connect_error) {
            die("Échec de la connexion à la base de données : " . $conn->connect_error);
        }
    } catch (PDOException $e) {
        die("Erreur de connexion à la base de données : " . $e->getMessage());
    }
} else {
    // Configuration locale (pour le développement)
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "reseau_social";
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        die("Échec de la connexion à la base de données : " . $conn->connect_error);
    }
}
