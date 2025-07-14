<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "reseau_social";

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérifier la connexion
if ($conn->connect_error) {
    die("Échec de la connexion à la base de données : " . $conn->connect_error);
}

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
 * @param mysqli $conn L'objet de connexion à la base de données.
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

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iiii", $user1_id, $user2_id, $user2_id, $user1_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        $stmt->close();
    }
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
 * @param mysqli $conn L'objet de connexion à la base de données.
 * @return bool Vrai si la notification a été ajoutée, faux sinon.
 */
function addNotification($user_id, $sender_id, $type, $content, $link = null, $conn) {
    $sql = "INSERT INTO notifications (user_id, sender_id, type, content, link) VALUES (?, ?, ?, ?, ?)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iisss", $user_id, $sender_id, $type, $content, $link);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    return false;
}

/**
 * Récupère les notifications pour un utilisateur donné.
 *
 * @param int $user_id L'ID de l'utilisateur.
 * @param mysqli $conn L'objet de connexion à la base de données.
 * @param bool $unread_only Si vrai, ne récupère que les notifications non lues.
 * @param int $limit Le nombre maximum de notifications à récupérer.
 * @return array Un tableau de notifications.
 */
function getNotifications($user_id, $conn, $unread_only = false, $limit = 10) {
    $notifications = [];
    $sql = "SELECT n.id, n.sender_id, n.type, n.content, n.is_read, n.created_at, n.link, u.username AS sender_username, u.profile_picture AS sender_profile_picture
            FROM notifications n
            LEFT JOIN users u ON n.sender_id = u.id
            WHERE n.user_id = ?";
    if ($unread_only) {
        $sql .= " AND n.is_read = FALSE";
    }
    $sql .= " ORDER BY n.created_at DESC LIMIT ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
    }
    return $notifications;
}

/**
 * Marque des notifications comme lues.
 *
 * @param array $notification_ids Un tableau d'IDs de notifications à marquer comme lues.
 * @param mysqli $conn L'objet de connexion à la base de données.
 * @return bool Vrai si les notifications ont été mises à jour, faux sinon.
 */
function markNotificationsAsRead($notification_ids, $conn) {
    if (empty($notification_ids)) {
        return true; // Rien à faire
    }
    $placeholders = implode(',', array_fill(0, count($notification_ids), '?'));
    $types = str_repeat('i', count($notification_ids)); // 'i' pour chaque ID entier

    $sql = "UPDATE notifications SET is_read = TRUE WHERE id IN ($placeholders)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$notification_ids);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    return false;
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