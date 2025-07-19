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
        
        // Créer une classe adaptateur pour simuler mysqli
        class PDOAdapter {
            private $pdo;
            
            public function __construct($pdo) {
                $this->pdo = $pdo;
            }
            
            public function prepare($sql) {
                return new PDOStatementAdapter($this->pdo->prepare($sql));
            }
            
            public function connect_error() {
                return false; // PDO ne gère pas cette propriété
            }
        }
        
        class PDOStatementAdapter {
            private $stmt;
            private $params = [];
            
            public function __construct($stmt) {
                $this->stmt = $stmt;
            }
            
            public function bind_param($types, ...$params) {
                $this->params = $params;
                return true;
            }
            
            public function execute() {
                return $this->stmt->execute($this->params);
            }
            
            public function get_result() {
                $this->stmt->execute($this->params);
                return new PDOResultAdapter($this->stmt);
            }
            
            public function close() {
                // PDO n'a pas besoin de fermer explicitement
            }
        }
        
        class PDOResultAdapter {
            private $stmt;
            
            public function __construct($stmt) {
                $this->stmt = $stmt;
            }
            
            public function fetch_assoc() {
                return $this->stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            public function num_rows() {
                // PDO n'a pas cette méthode, on simule
                $count = 0;
                while ($this->stmt->fetch()) {
                    $count++;
                }
                $this->stmt->execute(); // Réexécuter pour réutiliser
                return $count;
            }
        }
        
        // Créer l'adaptateur
        $conn = new PDOAdapter($pdo);
        
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

// ... (gardez le reste des fonctions comme avant)

/**
 * Fonction pour échapper les sorties HTML afin de prévenir les attaques XSS.
 * @param string $data Les données à échapper.
 * @return string Les données échappées.
 */
function h($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirige vers une autre page.
 * @param string $url L'URL vers laquelle rediriger.
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Vérifie si l'utilisateur est connecté.
 * @return bool Vrai si l'utilisateur est connecté, faux sinon.
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Récupère les messages échangés entre deux utilisateurs.
 *
 * @param int $user1_id L'ID du premier utilisateur.
 * @param int $user2_id L'ID du second utilisateur.
 * @param PDO $conn L'objet de connexion à la base de données.
 * @return array Un tableau de messages, chacun étant un tableau associatif.
 */
function getMessagesBetweenUsers($user1_id, $user2_id, $conn) {
    $messages = [];
    $sql = "SELECT m.id, m.sender_id, m.receiver_id, m.content, m.created_at, m.is_read,
                   s.username AS sender_username, s.profile_picture AS sender_profile_picture,
                   r.username AS receiver_username, r.profile_picture AS receiver_profile_picture
            FROM messages m
            JOIN users s ON m.sender_id = s.id
            JOIN users r ON m.receiver_id = r.id
            WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$user1_id, $user2_id, $user2_id, $user1_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $messages;
}

/**
 * Ajoute une notification dans la base de données.
 *
 * @param int $user_id L'ID de l'utilisateur qui reçoit la notification.
 * @param int|null $sender_id L'ID de l'utilisateur qui a déclenché la notification (peut être null).
 * @param string $type Le type de notification (ex: 'follow', 'like', 'comment').
 * @param string $content Le contenu du message de la notification.
 * @param string|null $link Le lien associé à la notification (ex: vers un profil ou un post).
 * @param PDO $conn L'objet de connexion à la base de données.
 * @return bool Vrai si la notification a été ajoutée, faux sinon.
 */
function addNotification($user_id, $sender_id, $type, $content, $link = null, $conn) {
    $sql = "INSERT INTO notifications (user_id, sender_id, type, content, link) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$user_id, $sender_id, $type, $content, $link]);
}

/**
 * Récupère les notifications pour un utilisateur donné.
 *
 * @param int $user_id L'ID de l'utilisateur.
 * @param PDO $conn L'objet de connexion à la base de données.
 * @param bool $unread_only Si vrai, ne récupère que les notifications non lues.
 * @param int $limit Le nombre maximum de notifications à récupérer.
 * @return array Un tableau de notifications.
 */
function getNotifications($user_id, $conn, $unread_only = false, $limit = 10) {
    $sql = "SELECT n.id, n.sender_id, n.type, n.content, n.is_read, n.created_at, n.link, u.username AS sender_username, u.profile_picture AS sender_profile_picture
            FROM notifications n
            LEFT JOIN users u ON n.sender_id = u.id
            WHERE n.user_id = ?";
    if ($unread_only) {
        $sql .= " AND n.is_read = FALSE";
    }
    $sql .= " ORDER BY n.created_at DESC LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Marque des notifications comme lues.
 *
 * @param array $notification_ids Un tableau d'IDs de notifications à marquer comme lues.
 * @param PDO $conn L'objet de connexion à la base de données.
 * @return bool Vrai si les notifications ont été mises à jour, faux sinon.
 */
function markNotificationsAsRead($notification_ids, $conn) {
    if (empty($notification_ids)) {
        return true; // Rien à faire
    }
    $placeholders = implode(',', array_fill(0, count($notification_ids), '?'));
    $sql = "UPDATE notifications SET is_read = TRUE WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    return $stmt->execute($notification_ids);
}

/**
 * Convertit une date/heure en chaîne de temps écoulé.
 * @param string $datetime La date/heure à convertir.
 * @param bool $full Si vrai, renvoie la chaîne complète (ex: 1 jour, 2 heures), sinon la plus grande unité (ex: 1 jour).
 * @return string La chaîne de temps écoulé.
 */
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'an',
        'm' => 'mois',
        'w' => 'semaine',
        'd' => 'jour',
        'h' => 'heure',
        'i' => 'minute',
        's' => 'seconde',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'maintenant';
}
?>
