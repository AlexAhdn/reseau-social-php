<?php
// index.php
session_start();
require_once 'config.php';

// Créer les tables manquantes automatiquement
function createMissingTables($conn) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            profile_picture VARCHAR(255) DEFAULT 'default.jpg',
            bio TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS posts (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            content TEXT,
            image_path VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS likes (
            id SERIAL PRIMARY KEY,
            post_id INTEGER REFERENCES posts(id) ON DELETE CASCADE,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(post_id, user_id)
        )",
        "CREATE TABLE IF NOT EXISTS comments (
            id SERIAL PRIMARY KEY,
            post_id INTEGER REFERENCES posts(id) ON DELETE CASCADE,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS follows (
            id SERIAL PRIMARY KEY,
            follower_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            followed_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(follower_id, followed_id)
        )",
        "CREATE TABLE IF NOT EXISTS friendships (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            friend_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, friend_id)
        )",
        "CREATE TABLE IF NOT EXISTS messages (
            id SERIAL PRIMARY KEY,
            sender_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            receiver_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            content TEXT NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS notifications (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            sender_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            type VARCHAR(50) NOT NULL,
            content TEXT NOT NULL,
            link VARCHAR(255),
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS saved_posts (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            post_id INTEGER REFERENCES posts(id) ON DELETE CASCADE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, post_id)
        )"
    ];
    
    foreach ($tables as $sql) {
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute();
        } catch (Exception $e) {
            // Ignore les erreurs si les tables existent déjà
        }
    }
    
    // Créer un utilisateur de test s'il n'existe pas
    try {
        $sql = "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?) ON CONFLICT (email) DO NOTHING";
        $stmt = $conn->prepare($sql);
        $username = 'test';
        $email = 'test@test.com';
        $password_hash = password_hash('test123', PASSWORD_DEFAULT);
        $stmt->bind_param("sss", $username, $email, $password_hash);
        $stmt->execute();
    } catch (Exception $e) {
        // Ignore si l'utilisateur existe déjà
    }
}

// Appeler la fonction pour créer les tables
createMissingTables($conn);

if (!isLoggedIn()) {
    redirect('login.php');
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$profile_picture = isset($_SESSION['profile_picture']) && $_SESSION['profile_picture'] ? $_SESSION['profile_picture'] : 'default.jpg';
$profile_picture_path = 'uploads/profiles/' . $profile_picture;
if (!file_exists($profile_picture_path)) {
    $profile_picture = 'default.jpg';
    $profile_picture_path = 'uploads/profiles/default.jpg';
}

$user_id = $_SESSION['user_id'];

$profilePicture = !empty($user['profile_picture']) ? $user['profile_picture'] : 'avatar.jpg';

// Récupérer la photo de profil à jour depuis la base de données
$sql = "SELECT profile_picture FROM users WHERE id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row && !empty($row['profile_picture'])) {
        $_SESSION['profile_picture'] = $row['profile_picture'];
    } else {
        $_SESSION['profile_picture'] = 'default.jpg';
    }
    $stmt->close();
}



// Gérer l'envoi de message via AJAX
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message_ajax'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $content = trim($_POST['content']);

    $response = ['success' => false, 'message' => '', 'sent_message' => null];

    if (empty($content)) {
        $response['message'] = "Le message ne peut pas être vide.";
    } else if ($receiver_id <= 0) {
        $response['message'] = "Destinataire invalide.";
    } else {
        $sql = "INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("iis", $user_id, $receiver_id, $content);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['sent_message'] = [
                    'content' => h($content),
                    'created_at' => date("Y-m-d H:i:s"), // Format à utiliser pour JS
                    'sender_id' => $user_id,
                    'receiver_id' => $receiver_id,
                    'is_read' => false
                ];
            } else {
                $response['message'] = "Erreur lors de l'envoi du message.";
            }
            $stmt->close();
        } else {
            $response['message'] = "Erreur de préparation de la requête d'envoi.";
        }
    }
    echo json_encode($response);
    exit();
}

// Gérer le chargement des messages via AJAX
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['load_messages_ajax'])) {
    $other_user_id = (int)$_POST['other_user_id'];

    $response = ['success' => false, 'messages' => [], 'message' => ''];

    if ($other_user_id <= 0) {
        $response['message'] = "Utilisateur invalide pour charger les messages.";
    } else {
        // Appeler la fonction getMessagesBetweenUsers de config.php
        $messages = getMessagesBetweenUsers($user_id, $other_user_id, $conn);
        $response['success'] = true;
        // Échapper le contenu des messages pour l'affichage HTML
        $escaped_messages = [];
        foreach ($messages as $msg) {
            $msg['content'] = h($msg['content']);
            $escaped_messages[] = $msg;
        }
        $response['messages'] = $escaped_messages;
    }
    echo json_encode($response);
    exit();
}


// Gérer l'ajout de publication (traitement POST pour la publication principale)
$post_error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_post'])) {
    $content = trim($_POST['content']);

    if (empty($content) && (!isset($_FILES['post_image']) || $_FILES['post_image']['error'] == UPLOAD_ERR_NO_FILE)) {
        $post_error = "Veuillez écrire quelque chose ou ajouter une image.";
    } else {
        $image_path = null;
        if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] == UPLOAD_ERR_OK) {
            $target_dir = "uploads/posts/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $image_name = uniqid() . "_" . basename($_FILES["post_image"]["name"]);
            $target_file = $target_dir . $image_name;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            $check = getimagesize($_FILES["post_image"]["tmp_name"]);
            if ($check === false) {
                $post_error = "Le fichier n'est pas une image.";
            } else if ($_FILES["post_image"]["size"] > 5000000) { // Max 5MB
                $post_error = "Désolé, votre fichier est trop grand.";
            } else if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
                $post_error = "Désolé, seuls les formats JPG, JPEG, PNG & GIF sont autorisés.";
            } else {
                if (move_uploaded_file($_FILES["post_image"]["tmp_name"], $target_file)) {
                    $image_path = $target_file;
                } else {
                    $post_error = "Désolé, une erreur s'est produite lors du téléchargement de votre fichier.";
                }
            }
        }

        if (empty($post_error)) {
            $sql = "INSERT INTO posts (user_id, content, image_path) VALUES (?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("iss", $user_id, $content, $image_path);
                if (!$stmt->execute()) {
                    $post_error = "Erreur lors de la publication.";
                }
                $stmt->close();
            }
        }
    }
    redirect('index.php'); // Redirige pour éviter la soumission multiple
}



// Gérer les actions de like/unlike via AJAX
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['post_id']) && !isset($_POST['follow_action'])) {
    $post_id_action = $_POST['post_id'];
    $action = $_POST['action'];

if ($action == 'like') {
    $sql = "INSERT IGNORE INTO likes (post_id, user_id) VALUES (?, ?)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $post_id_action, $user_id);
        $stmt->execute();
        $stmt->close();
    }
} elseif ($action == 'unlike') {
    $sql = "DELETE FROM likes WHERE post_id = ? AND user_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $post_id_action, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}
echo json_encode(['success' => true]);
exit();

}

// Gérer l'ajout de commentaire via AJAX
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_comment_ajax'])) {
    $post_id_comment = $_POST['post_id_comment'];
    $comment_content = trim($_POST['comment_content']);

   $response = ['success' => false, 'message' => ''];

if (empty($comment_content)) {
    $response['message'] = "Le commentaire ne peut pas être vide.";
} else {
    $sql = "INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iis", $post_id_comment, $user_id, $comment_content);
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['comment'] = [
                'content' => h($comment_content),
                'created_at' => date("d/m/Y H:i"),
                'username' => h($username),
                'profile_picture' => h($profile_picture)
            ];
        } else {
            $response['message'] = "Erreur lors de l'ajout du commentaire.";
        }
        $stmt->close();
    } else {
        $response['message'] = "Erreur de préparation de la requête.";
    }
}
echo json_encode($response);
exit();

}

// Gérer les actions de suivi/désabonnement/ami via AJAX
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['follow_action'])) {
    $target_user_id = (int)$_POST['target_user_id'];
    $follow_action = $_POST['follow_action'];

    $response = ['success' => false, 'action_performed' => '', 'message' => ''];

if ($user_id == $target_user_id) {
    $response['message'] = "Vous ne pouvez pas interagir avec votre propre profil de cette manière.";
    echo json_encode($response);
    exit();
}

if ($follow_action === 'follow') {
    $sql_insert = "INSERT IGNORE INTO follows (follower_id, followed_id) VALUES (?, ?)";
    if ($stmt_insert = $conn->prepare($sql_insert)) {
        $stmt_insert->bind_param("ii", $user_id, $target_user_id);
        if ($stmt_insert->execute()) {
            // Vérifier si le suivi est mutuel après l'insertion
            $sql_check_mutual = "SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?";
            if ($stmt_check_mutual = $conn->prepare($sql_check_mutual)) {
                $stmt_check_mutual->bind_param("ii", $target_user_id, $user_id);
                $stmt_check_mutual->execute();
                $stmt_check_mutual->store_result();
                if ($stmt_check_mutual->num_rows > 0) {
                    $response['success'] = true;
                    $response['action_performed'] = 'became_friends'; // Devient amis
                } else {
                    $response['success'] = true;
                    $response['action_performed'] = 'followed'; // Juste suivi
                }
                $stmt_check_mutual->close();
            }
        } else {
            $response['message'] = "Erreur lors du suivi.";
        }
        $stmt_insert->close();
    }
} elseif ($follow_action === 'unfollow') { // Se désabonner d'une personne qu'on suit (pas un ami)
    $sql_delete = "DELETE FROM follows WHERE follower_id = ? AND followed_id = ?";
    if ($stmt_delete = $conn->prepare($sql_delete)) {
        $stmt_delete->bind_param("ii", $user_id, $target_user_id);
        if ($stmt_delete->execute()) {
            $response['success'] = true;
            $response['action_performed'] = 'unfollowed';
        } else {
            $response['message'] = "Erreur lors du désabonnement.";
        }
        $stmt_delete->close();
    }
} elseif ($follow_action === 'remove_friend') { // Retirer un ami (supprimer les deux suivis mutuels)
    // Supprimer le suivi de l'utilisateur actuel vers la cible
    $sql_delete1 = "DELETE FROM follows WHERE follower_id = ? AND followed_id = ?";
    if ($stmt_delete1 = $conn->prepare($sql_delete1)) {
        $stmt_delete1->bind_param("ii", $user_id, $target_user_id);
        $stmt_delete1->execute();
        $stmt_delete1->close();
    }

    // Supprimer le suivi de la cible vers l'utilisateur actuel
    $sql_delete2 = "DELETE FROM follows WHERE follower_id = ? AND followed_id = ?";
    if ($stmt_delete2 = $conn->prepare($sql_delete2)) {
        $stmt_delete2->bind_param("ii", $target_user_id, $user_id);
        $stmt_delete2->execute();
        $stmt_delete2->close();
    }

    $response['success'] = true;
    $response['action_performed'] = 'removed_friend';
}
echo json_encode($response);
exit();

}


// Récupérer les publications avec le nombre de likes et les commentaires
$posts = [];
$sql = "SELECT p.id, p.content, p.image_path, p.created_at, u.username, u.profile_picture,
               (SELECT COUNT(*) FROM likes WHERE post_id = p.id) AS likes_count,
               (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) AS user_liked
        FROM posts p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $post = $row;
        $comments_sql = "SELECT c.content, c.created_at, u.username, u.profile_picture
                         FROM comments c
                         JOIN users u ON c.user_id = u.id
                         WHERE c.post_id = ?
                         ORDER BY c.created_at ASC";
        if ($comments_stmt = $conn->prepare($comments_sql)) {
            $comments_stmt->bind_param("i", $post['id']);
            $comments_stmt->execute();
            $comments_result = $comments_stmt->get_result();
            $post['comments'] = [];
            while ($comment_row = $comments_result->fetch_assoc()) {
                $post['comments'][] = $comment_row;
            }
            $comments_stmt->close();
        }
        $posts[] = $post;
    }
    $stmt->close();
}

// Récupérer les utilisateurs que l'utilisateur suit
$followed_users_raw = [];
$sql_followed_raw = "SELECT u.id, u.username, u.profile_picture FROM users u
                     JOIN follows f ON u.id = f.followed_id
                     WHERE f.follower_id = ?";
if ($stmt_followed_raw = $conn->prepare($sql_followed_raw)) {
    $stmt_followed_raw->bind_param("i", $user_id);
    $stmt_followed_raw->execute();
    $result_followed_raw = $stmt_followed_raw->get_result();
    while ($row = $result_followed_raw->fetch_assoc()) {
        $followed_users_raw[$row['id']] = $row; // Stocker par ID pour faciliter la recherche
    }
    $stmt_followed_raw->close();
}

// Vérifier les relations d'amitié (suivi mutuel)
$friends = [];
$following_only = [];
$all_chat_users = []; // Nouvelle variable pour la liste de chat

foreach ($followed_users_raw as $followed_id => $user_data) {
    $sql_check_mutual = "SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?";
    if ($stmt_check_mutual = $conn->prepare($sql_check_mutual)) {
        $stmt_check_mutual->bind_param("ii", $followed_id, $user_id); // Vérifier si l'autre utilisateur nous suit
        $stmt_check_mutual->execute();
        $stmt_check_mutual->store_result();
        if ($stmt_check_mutual->num_rows > 0) {
            $friends[] = $user_data; // Ami
            $user_data['relation'] = 'friends';
            $all_chat_users[] = $user_data; // Ajouter aux utilisateurs de chat
        } else {
            $following_only[] = $user_data; // Juste abonné(e)
            $user_data['relation'] = 'following';
            $all_chat_users[] = $user_data; // Ajouter aux utilisateurs de chat
        }
        $stmt_check_mutual->close();
    }
}


// Récupérer les utilisateurs suggérés (ceux que l'utilisateur ne suit pas et qui ne sont pas lui-même)
$suggested_users = [];
$sql_suggested = "SELECT id, username, profile_picture FROM users
                  WHERE id != ?
                  AND id NOT IN (SELECT followed_id FROM follows WHERE follower_id = ?)
                  AND id NOT IN (SELECT follower_id FROM follows WHERE followed_id = ? AND follower_id != ?)
                  LIMIT 5"; // Limiter à 5 suggestions pour l'exemple

if ($stmt_suggested = $conn->prepare($sql_suggested)) {
    // Le dernier paramètre est pour exclure les utilisateurs qui nous suivent déjà mais qu'on ne suit pas encore (pour ne pas les resuggérer si on est déjà suivi)
    $stmt_suggested->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt_suggested->execute();
    $result_suggested = $stmt_suggested->get_result();
    while ($row = $result_suggested->fetch_assoc()) {
        $suggested_users[] = $row;
    }
    $stmt_suggested->close();
}



?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - Mon Réseau Social</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
    body { font-family: Arial, sans-serif; background-color: #ecf0f1; margin: 0; padding: 0; }

    /* NOUVEAU STYLE HEADER */
    .header {
        background-color: #1a3a3a; /* Vert-bleu très foncé */
        padding: 5px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        height: 56px;
        box-shadow: 0 2px 4px rgba(0,0,0,.1);
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .header-left .logo {
        font-size: 28px;
        font-weight: bold;
        color: #008080; /* Sarcelle pour le logo */
        text-decoration: none;
        line-height: 1;
    }

    .header-left .search-bar {
        position: relative;
    }
    .header-left .search-bar input {
        background-color: #34495e; /* Conservé pour un bon contraste avec le texte clair */
        border: none;
        border-radius: 20px;
        padding: 8px 15px 8px 40px;
        font-size: 15px;
        color: #e3f2fd; /* Bleu très clair pour le texte */
        width: 200px;
        outline: none;
    }
    .header-left .search-bar input::placeholder {
        color: #bdc3c7; /* Gris argenté clair pour le placeholder */
    }
    .header-left .search-bar .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #bdc3c7;
        font-size: 16px;
    }

    .header-center {
        display: flex;
        gap: 20px;
    }
    .header-center .nav-icon {
        color: #ecf0f1; /* Gris Clair Doux pour les icônes de navigation */
        font-size: 22px;
        padding: 8px 15px;
        border-radius: 8px;
        transition: background-color .2s, color .2s;
        cursor: pointer;
    }
    .header-center .nav-icon:hover {
        background-color: #34495e; /* Gris Saphir Foncé au survol */
        color: #008080; /* Sarcelle au survol */
    }
    .header-center .nav-icon.active {
        color: #008080; /* Sarcelle pour l'icône active */
        border-bottom: 3px solid #008080; /* Bordure sarcelle en dessous */
        background-color: transparent;
        padding-bottom: 5px;
    }
    .header-center .nav-icon.active:hover {
        background-color: transparent;
    }

    .header-right {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .header-right .profile-thumbnail {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        cursor: pointer;
        border: 2px solid transparent;
        vertical-align: middle;
    }
    .header-right .profile-thumbnail:hover {
        border-color: #008080; /* Sarcelle au survol */
    }

    .header-right .icon-button {
        background-color: #34495e; /* Gris Saphir Foncé pour les boutons d'icônes */
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        justify-content: center;
        align-items: center;
        color: #ecf0f1;
        font-size: 18px;
        cursor: pointer;
        transition: background-color .2s;
    }
    .header-right .icon-button:hover {
        background-color: #1a3a3a; /* Vert-bleu très foncé au survol */
    }
    .notif-badge {
        position: absolute;
        top: 2px;
        right: 2px;
        background: #e74c3c;
        color: #fff;
        border-radius: 50%;
        padding: 2px 6px;
        font-size: 12px;
        font-weight: bold;
        z-index: 10;
    }

    /* FIN NOUVEAU STYLE HEADER */


    /* Conteneur principal pour la mise en page à TROIS colonnes */
    .main-content-wrapper {
        display: flex;
        max-width: 1200px;
        margin: 20px auto;
        gap: 20px;
        padding: 0 20px;
    }

    /* Nouvelle Sidebar Gauche */
    .left-sidebar {
        position: fixed !important;
        top: 76px;
        left: 0;
        width: 280px;
        height: calc(100vh - 76px);
        overflow-y: auto;
        z-index: 100;
        margin-left: 0 !important;
        padding-left: 0 !important;
    }

    .sidebar-item {
        display: flex;
        align-items: center;
        padding: 8px 10px;
        margin-bottom: 5px;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.2s;
        color: #212121; /* Noir Charbon pour le texte */
        text-decoration: none;
        font-size: 15px;
        font-weight: 500;
    }

    .sidebar-item:hover {
        background-color: #dcdde1; /* Gris clair légèrement plus foncé au survol */
    }

    .sidebar-item img {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 12px;
    }

    .sidebar-item .icon {
        font-size: 22px;
        width: 36px;
        height: 36px;
        display: flex;
        justify-content: center;
        align-items: center;
        margin-right: 12px;
        color: #95a5a6; /* Gris Argenté pour les icônes */
    }

    .sidebar-divider {
        border-top: 1px solid #bdc3c7; /* Gris argenté clair pour la ligne de séparation */
        margin: 15px 0;
    }

    .sidebar-title {
        font-size: 17px;
        font-weight: 600;
        color: #95a5a6; /* Gris Argenté */
        margin-bottom: 10px;
        padding: 0 10px;
    }


    .main-feed {
        margin-left: 280px;
        flex-grow: 1;
        max-width: 600px;
    }

    .right-sidebar {
        width: 300px;
        background-color: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,.1);
        align-self: flex-start;
        position: sticky;
        top: 76px;
        overflow-y: auto;
        height: calc(100vh - 76px);
        margin-left: 20px;
        flex-shrink: 0;
    }

    .right-sidebar h3 {
        margin-top: 0;
        color: #212121; /* Noir Charbon */
        font-size: 18px;
        border-bottom: 1px solid #dcdde1;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }

    .sidebar-user-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-user-item {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
        gap: 10px;
    }

    .sidebar-user-item img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }

    .sidebar-user-item .username {
        font-weight: bold;
        color: #212121; /* Noir Charbon */
        flex-grow: 1;
        text-decoration: none;
    }
    .sidebar-user-item .username:hover {
        text-decoration: underline;
    }

    /* Styles des boutons de suivi/désabonnement/ami */
    .sidebar-user-item .action-button {
        background-color: #bdc3c7; /* Gris argenté clair par défaut */
        color: #212121; /* Noir Charbon */
        border: none;
        padding: 6px 12px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 13px;
        transition: background-color .2s, color .2s;
        white-space: nowrap;
        font-weight: bold;
    }

    .sidebar-user-item .action-button:hover {
        background-color: #95a5a6; /* Gris Argenté au survol */
    }

    /* Bouton "Suivre" */
    .sidebar-user-item .action-button.follow-button {
        background-color: #008080; /* Sarcelle */
        color: white; /* Texte blanc pour un meilleur contraste */
    }
    .sidebar-user-item .action-button.follow-button:hover {
        background-color: #006666; /* Sarcelle plus foncé au survol */
    }

    /* Bouton "Abonné(e)" */
    .sidebar-user-item .action-button.followed-button {
        background-color: #bdc3c7;
        color: #212121;
    }
    .sidebar-user-item .action-button.followed-button:hover {
        background-color: #95a5a6;
    }

    /* Bouton "Amis" */
    .sidebar-user-item .action-button.friends-button {
        background-color: #bdc3c7;
        color: #212121;
    }
    .sidebar-user-item .action-button.friends-button:hover {
        background-color: #95a5a6;
    }

    /* Bouton "Retirer" (lorsque hover sur Amis) */
    .sidebar-user-item .action-button.friends-button:hover {
        background-color: #e74c3c; /* Rouge vif pour retirer (couleur d'avertissement standard) */
        color: white;
    }
    .sidebar-user-item .action-button.friends-button:hover .button-text {
        display: none;
    }
    .sidebar-user-item .action-button.friends-button:hover .remove-text {
        display: inline;
    }
    .sidebar-user-item .action-button.friends-button .remove-text {
        display: none;
    }


    .no-suggestions {
        color: #95a5a6; /* Gris Argenté */
        font-size: 14px;
        text-align: center;
        padding: 10px 0;
    }


    .create-post { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,.1); margin-bottom: 20px; }
    .create-post textarea { width: calc(100% - 20px); padding: 10px; border: 1px solid #dcdde1; border-radius: 6px; font-size: 16px; min-height: 80px; resize: vertical; margin-bottom: 10px; }
    .create-post input[type="file"] { margin-bottom: 10px; }
    .create-post button { background-color: #008080; /* Sarcelle */ color: white; /* Texte blanc pour un meilleur contraste */ border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 16px; transition: background-color .2s; }
    .create-post button:hover { background-color: #006666; } /* Sarcelle plus foncé au survol */
    .post-error { color: #e74c3c; font-size: 14px; margin-bottom: 10px; } /* Rouge d'erreur standard */

    .post { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,.1); margin-bottom: 20px; }
    .post-header { display: flex; align-items: center; margin-bottom: 15px; }
    .post-header img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-right: 10px; }
    .post-header .username { font-weight: bold; color: #212121; /* Noir Charbon */ }
    .post-header .timestamp { font-size: 13px; color: #95a5a6; /* Gris Argenté */ margin-left: auto; }
    .post-content { font-size: 16px; color: #212121; /* Noir Charbon */ line-height: 1.5; margin-bottom: 15px; }
    .post-image { max-width: 100%; border-radius: 6px; margin-top: 10px; display: block; }
    .no-posts { text-align: center; color: #95a5a6; /* Gris Argenté */ }

    .post-actions {
        display: flex;
        justify-content: space-around;
        padding-top: 15px;
        border-top: 1px solid #dcdde1;
        margin-top: 15px;
        align-items: center;
    }

    .like-container {
        display: flex;
        align-items: center;
        gap: 5px;
        cursor: pointer;
        padding: 5px 10px;
        border-radius: 5px;
        transition: background-color .2s;
    }
    .like-container:hover {
        background-color: #f5f5f5;
    }

    .like-button {
        background: none;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        padding: 0;
        color: #95a5a6; /* Gris Argenté */
        font-size: 15px;
        transition: color .2s;
    }
    .like-button .fa-thumbs-up {
        color: #95a5a6; /* Gris Argenté par défaut */
    }
    .like-button.liked .fa-thumbs-up {
        color: #008080; /* Sarcelle quand 'liké' */
    }
    .like-container:hover .like-button .fa-thumbs-up {
        color: #008080; /* Sarcelle au survol du conteneur */
    }


    .likes-count {
        font-size: 14px;
        color: #95a5a6; /* Gris Argenté */
        margin: 0;
    }


    .post-actions .comment-button {
        background: none;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        padding: 5px 10px;
        border-radius: 5px;
        transition: background-color .2s, color .2s;
        font-size: 15px;
        color: #95a5a6; /* Gris Argenté */
    }
    .post-actions .comment-button:hover {
        background-color: #f5f5f5;
        color: #008080; /* Sarcelle au survol */
    }

    .add-comment-form {
        display: none;
        margin-top: 15px;
        align-items: center;
    }

    .comments-section { margin-top: 20px; border-top: 1px solid #dcdde1; padding-top: 15px; }
    .comment { display: flex; margin-bottom: 10px; }
    .comment img { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; margin-right: 8px; }
    .comment-content-wrapper { background-color: #dcdde1; padding: 8px 12px; border-radius: 18px; max-width: calc(100% - 40px); }
    .comment-username { font-weight: bold; font-size: 14px; color: #212121; /* Noir Charbon */ }
    .comment-text { font-size: 14px; color: #212121; /* Noir Charbon */ }
    .comment-timestamp { font-size: 12px; color: #95a5a6; margin-left: 5px; }

    .add-comment-form input[type="text"] { flex-grow: 1; padding: 8px 12px; border: 1px solid #bdc3c7; border-radius: 18px; margin-right: 10px; font-size: 14px; background-color: #ecf0f1; /* Gris Clair Doux */ }
    .add-comment-form button { background-color: #008080; /* Sarcelle */ color: white; /* Texte blanc pour un meilleur contraste */ border: none; padding: 8px 15px; border-radius: 18px; cursor: pointer; font-size: 14px; transition: background-color .2s; }
    .add-comment-form button:hover { background-color: #006666; } /* Sarcelle plus foncé au survol */
    .comment-error { color: #e74c3c; font-size: 14px; margin-top: 5px; } /* Rouge d'erreur standard */


    /* NOUVEAUX STYLES POUR LA FENÊTRE DE DISCUSSION */
    .chat-window {
        position: fixed;
        bottom: 0;
        right: 20px;
        width: 350px;
        height: 450px;
        background-color: #fff;
        border-radius: 8px 8px 0 0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: none;
        flex-direction: column;
        z-index: 2000;
        overflow: hidden;
    }

    .chat-header {
        background-color: #1a3a3a; /* Vert-bleu très foncé pour l'en-tête du chat */
        color: white;
        padding: 10px 15px;
        border-radius: 8px 8px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: bold;
        font-size: 16px;
    }
    .chat-header .chat-title {
        flex-grow: 1;
        text-align: center;
    }
    .chat-header .chat-buttons {
        display: flex;
        gap: 10px;
    }

    .chat-header .action-icon {
        background: none;
        border: none;
        color: white;
        font-size: 16px;
        cursor: pointer;
        padding: 5px;
        line-height: 1;
        transition: opacity 0.2s;
    }
    .chat-header .action-icon:hover {
        opacity: 0.8;
    }

    .chat-header .close-btn {
        background: none;
        border: none;
        color: white;
        font-size: 20px;
        cursor: pointer;
        padding: 5px;
        line-height: 1;
    }
    .chat-header .close-btn:hover {
        opacity: 0.8;
    }

    /* Sections internes de la fenêtre de chat */
    .chat-list, .chat-conversation {
        flex-grow: 1;
        overflow-y: auto;
        background-color: #ecf0f1; /* Gris Clair Doux */
        padding: 10px;
    }

    .chat-list {
        display: block;
    }
    .chat-conversation {
        display: none;
        flex-direction: column;
        justify-content: flex-end;
    }
    .chat-conversation .chat-messages {
        flex-grow: 1;
        overflow-y: auto;
        padding-right: 5px;
        display: flex;
        flex-direction: column;
    }
    .chat-conversation .chat-input-area {
        border-top: 1px solid #dcdde1;
        padding-top: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Style des éléments de la liste de discussion (chaque utilisateur) */
    .chat-list-item {
        display: flex;
        align-items: center;
        padding: 10px;
        border-radius: 8px;
        margin-bottom: 5px;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .chat-list-item:hover {
        background-color: #dcdde1;
    }
    .chat-list-item img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 10px;
    }
    .chat-list-item .chat-username {
        font-weight: bold;
        color: #212121; /* Noir Charbon */
        flex-grow: 1;
    }
    .chat-list-item .chat-status-dot {
        width: 10px;
        height: 10px;
        background-color: #27ae60; /* Vert émeraude pour "en ligne" */
        border-radius: 50%;
        margin-left: auto;
    }

    /* Styles des messages individuels */
    .message-wrapper {
        display: flex;
        align-items: flex-end;
        margin-bottom: 8px;
        max-width: 100%;
        position: relative;
    }
    .message-wrapper.sent {
        justify-content: flex-end;
        flex-direction: row-reverse;
    }
    .message-wrapper.received {
        justify-content: flex-start;
    }

    .message-bubble {
        padding: 8px 12px;
        border-radius: 18px;
        max-width: 75%;
        word-wrap: break-word;
        font-size: 15px;
        line-height: 1.4;
        display: flex;
        align-items: flex-end;
        gap: 5px;
    }
    .message-bubble.sent {
        background-color: #1a3a3a; /* Vert-bleu très foncé pour les messages envoyés */
        color: white;
        border-bottom-right-radius: 2px;
        margin-left: 5px;
    }
    .message-bubble.received {
        background-color: #dcdde1; /* Gris clair pour les messages reçus */
        color: #212121; /* Noir Charbon */
        border-bottom-left-radius: 2px;
        margin-right: 5px;
    }

    /* Avatar dans les messages */
    .message-wrapper .message-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }
    .message-wrapper.sent .message-avatar {
        margin-left: 8px;
    }
    .message-wrapper.received .message-avatar {
        margin-right: 8px;
    }


    .message-text {
        flex-grow: 1;
    }
    .message-timestamp-tick {
        font-size: 11px;
        color: rgba(255,255,255,0.7); /* Plus clair pour les messages envoyés */
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 3px;
    }
    .message-bubble.received .message-timestamp-tick {
        color: #95a5a6; /* Gris Argenté pour les messages reçus */
    }

    .message-timestamp-tick .fa-check {
        font-size: 9px;
        vertical-align: middle;
    }

    .chat-conversation .chat-input-area input[type="text"] {
        flex-grow: 1;
        padding: 8px 12px;
        border: 1px solid #bdc3c7;
        border-radius: 20px;
        font-size: 15px;
        outline: none;
        background-color: #ecf0f1; /* Gris Clair Doux */
    }

    .chat-conversation .chat-input-area button {
        background: none;
        border: none;
        color: #008080; /* Sarcelle pour le bouton d'envoi */
        font-size: 20px;
        cursor: pointer;
        padding: 5px;
        transition: color .2s;
    }
    .chat-conversation .chat-input-area button:hover {
        color: #006666; /* Sarcelle plus foncé au survol */
    }

    .upload-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #1aaf5d;
        color: #fff;
        border: none;
        border-radius: 20px;
        padding: 8px 18px;
        font-size: 1rem;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.2s;
        margin-bottom: 10px;
    }
    .upload-btn:hover {
        background: #188d4a;
    }
    .upload-btn i {
        font-size: 1.2em;
    }
    input[type="file"] {
        display: none;
    }
</style>
<style>
.sidebar-classic {
    position: fixed;
    top: 76px;
    left: 0;
    width: 280px;
    height: calc(100vh - 76px);
    overflow-y: auto;
    z-index: 100;
}
.sidebar-classic a {
    display: block;
    margin-bottom: 22px;
    text-decoration: none;
    color: #1a3a3a;
    font-weight: 500;
    font-size: 1.08rem;
    padding: 12px 18px;
    border-radius: 8px;
    transition: background 0.2s, color 0.2s;
    text-align: center;
    width: 90%;
}
.sidebar-classic a:hover {
    background: #1a3a3a;
    color: #fff;
}
.sidebar-classic a:last-child {
    margin-bottom: 0;
}
.sidebar-classic .sidebar-profile {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 30px;
}
.sidebar-classic .sidebar-profile-img {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #4dcab1;
    background: #fff;
}
.sidebar-classic .sidebar-profile-name {
    margin-top: 10px;
    font-weight: bold;
    color: #1a3a3a;
    font-size: 1.1rem;
    text-align: center;
}
.main-feed {
    margin-left: 280px;
    flex: 1 1 0%;
    max-width: 600px;
}
.main-content-wrapper {
    display: flex;
    max-width: 1200px;
    margin: 20px auto;
    gap: 20px;
    padding: 0 20px;
}
</style>
<style>
.sidebar-toggle {
  background: none;
  border: none;
  color: #fff;
  font-size: 2em;
  margin-right: 10px;
  cursor: pointer;
  display: none;
  z-index: 1101;
}
@media (max-width: 900px) {
  .sidebar-toggle {
    display: block !important;
  }
  .sidebar-classic {
    display: none;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 80vw !important;
    max-width: 320px !important;
    height: 100vh !important;
    background: #fff !important;
    box-shadow: 2px 0 12px rgba(0,0,0,0.15) !important;
    z-index: 1100 !important;
    overflow-y: auto !important;
    transition: transform 0.3s;
    transform: translateX(-100%);
  }
  .sidebar-classic.open {
    display: block !important;
    transform: translateX(0);
  }
  .sidebar-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.25);
    z-index: 1099;
  }
  .sidebar-overlay.active {
    display: block;
  }
  .close-sidebar {
    display: block !important;
  }
  .header-center,
  .header-right {
    display: none !important;
  }
  .header-left {
    justify-content: flex-start !important;
    width: 100% !important;
  }
  .logo {
    margin-left: 10px !important;
    font-size: 1.3em !important;
  }
  .sidebar-classic,
  .left-sidebar,
  .right-sidebar,
  .sidebar-overlay {
    display: none !important;
  }
  .main-feed {
    margin-left: 0 !important;
    margin-right: 0 !important;
    max-width: 100vw !important;
    width: 100% !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: flex-start !important;
    margin-top: 20px !important;
  }
  .main-content-wrapper {
    padding: 0 !important;
    gap: 0 !important;
    justify-content: center !important;
  }
}
@media (max-width: 900px) {
  .sidebar-toggle {
    display: block !important;
  }
  .header-center,
  .header-right {
    display: none !important;
  }
  .header-left {
    justify-content: center !important;
    width: 100% !important;
    display: flex !important;
    align-items: center !important;
    position: relative !important;
  }
  .sidebar-toggle {
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    margin-left: 10px;
  }
  .logo {
    margin: 0 auto !important;
    font-size: 1.3em !important;
    display: block !important;
  }
}
</style>
</head>
<body>
      <div class="header">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu" style="display:none;">
          <i class="fas fa-bars"></i>
        </button>
        <div class="header-left" style="justify-content: center; min-width: 300px;">
            <a href="index.php" class="logo" style="color:#fff;">Nexa</a>
        </div>
        <div class="header-center">
            <a href="index.php" class="nav-icon<?php if(basename($_SERVER['REQUEST_URI']) == 'index.php') echo ' active'; ?>"><i class="fas fa-home"></i></a>
            <a href="friends_list.php" class="nav-icon<?php if(basename($_SERVER['REQUEST_URI']) == 'friends_list.php') echo ' active'; ?>"><i class="fas fa-user-friends"></i></a>
            <a href="messages.php" class="nav-icon<?php if(basename($_SERVER['REQUEST_URI']) == 'messages.php') echo ' active'; ?>" title="Messagerie"><i class="fab fa-facebook-messenger"></i></a>
            <a href="notif.php" class="nav-icon<?php if(basename($_SERVER['REQUEST_URI']) == 'notif.php') echo ' active'; ?>" title="Notifications"><i class="fas fa-bell"></i></a>
        </div>
        <div class="header-right">
            <a href="profile.php?username=<?php echo urlencode($username); ?>">
                <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" alt="Photo de profil" class="profile-thumbnail">
            </a>
            <a href="logout.php" class="icon-button" title="Déconnexion"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="sidebar-classic">
      <button class="close-sidebar" id="closeSidebar" style="display:none;position:absolute;top:10px;right:10px;background:none;border:none;font-size:2em;color:#1a3a3a;z-index:1200;">&times;</button>
      <div class="sidebar-profile">
          <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" alt="Photo de profil" class="sidebar-profile-img">
          <div class="sidebar-profile-name"><?php echo isset($username) ? htmlspecialchars($username) : 'Utilisateur'; ?></div>
      </div>
      <a href="profile.php?username=<?php echo urlencode($username); ?>"><i class="fas fa-user-circle"></i> Mon profil</a>
      <a href="friends_list.php"><i class="fas fa-user-friends"></i> Amis</a>
      <a href="messages.php"><i class="fab fa-facebook-messenger"></i> Messages</a>
      <a href="notif.php"><i class="fas fa-bell"></i> Notifications</a>
      <a href="settings.php"><i class="fas fa-cog"></i> Paramètres</a>
      <a href="logout.php" style="color:#c0392b;position:absolute;bottom:20px;left:20px;"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
    </div>

    <div class="main-content-wrapper">
        <div class="main-feed">
            <div class="create-post">
                <h3>Créer une publication</h3>
                <form action="<?php echo h($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                    <textarea name="content" placeholder="Quoi de neuf, <?php echo h($username); ?> ?"></textarea>
                    <label for="post_image" class="upload-btn"><i class="fas fa-image"></i> Ajouter une image</label>
                    <input type="file" name="post_image" id="post_image" accept="image/*">
                    <?php if (!empty($post_error)): ?>
                        <div class="post-error"><?php echo h($post_error); ?></div>
                    <?php endif; ?>
                    <button type="submit" name="submit_post">Publier</button>
                </form>
            </div>

            <h2>Fil d'actualité</h2>
            <?php if (empty($posts)): ?>
                <p class="no-posts">Aucune publication pour le moment. Soyez le premier à publier !</p>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="post">
                        <div class="post-header">
                            <img src="uploads/profiles/<?php echo h($post['profile_picture'] ?? 'default.jpg'); ?>" alt="Photo de profil de <?php echo h($post['username']); ?>">
                            <span class="username"><a href="profile.php?username=<?php echo urlencode($post['username']); ?>"><?php echo h($post['username']); ?></a></span>
                            <span class="timestamp"><?php echo date("d/m/Y H:i", strtotime($post['created_at'])); ?></span>
                        </div>
                        <div class="post-content">
                            <?php echo nl2br(h($post['content'])); ?>
                        </div>
                        <?php if (!empty($post['image_path'])): ?>
                            <img src="<?php echo h($post['image_path']); ?>" alt="Image de publication" class="post-image">
                        <?php endif; ?>

                        <div class="post-actions">
                            <div class="like-container">
                                <form class="like-form" method="post">
                                    <input type="hidden" name="post_id" value="<?php echo h($post['id']); ?>">
                                    <?php if ($post['user_liked']): ?>
                                        <button type="submit" name="action" value="unlike" class="like-button liked"><i class="fas fa-thumbs-up"></i></button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="like" class="like-button"><i class="fas fa-thumbs-up"></i></button>
                                    <?php endif; ?>
                                </form>
                                <span class="likes-count" data-post-id="<?php echo h($post['id']); ?>"><?php echo h($post['likes_count']); ?></span>
                            </div>
                            <button type="button" class="comment-button" data-post-id="<?php echo h($post['id']); ?>">Commenter</button>
                        </div>

                        <div class="comments-section comment-form-container" data-post-id="<?php echo h($post['id']); ?>">
                            <h4>Commentaires (<?php echo count($post['comments']); ?>)</h4>
                            <?php if (empty($post['comments'])): ?>
                                <p class="no-posts">Aucun commentaire pour le moment.</p>
                            <?php else: ?>
                                <?php foreach ($post['comments'] as $comment): ?>
                                    <div class="comment">
                                        <img src="uploads/profiles/<?php echo h($comment['profile_picture'] ?? 'default.jpg'); ?>" alt="Photo de profil de <?php echo h($comment['username']); ?>">
                                        <div class="comment-content-wrapper">
                                            <span class="comment-username"><a href="profile.php?username=<?php echo urlencode($comment['username']); ?>"><?php echo h($comment['username']); ?></a></span>
                                            <span class="comment-text"><?php echo nl2br(h($comment['content'])); ?></span>
                                            <span class="comment-timestamp"><?php echo date("d/m/Y H:i", strtotime($comment['created_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <form class="add-comment-form ajax-comment-form" method="post">
                                <input type="hidden" name="post_id_comment" value="<?php echo h($post['id']); ?>">
                                <input type="text" name="comment_content" placeholder="Écrire un commentaire..." required>
                                <button type="submit" name="submit_comment_ajax">Envoyer</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="right-sidebar">
            <h3>Suggestions de profils</h3>
            <ul class="sidebar-user-list" id="suggested-users-list">
                <?php if (empty($suggested_users)): ?>
                    <li class="no-suggestions">Aucune suggestion pour le moment.</li>
                <?php else: ?>
                    <?php foreach ($suggested_users as $s_user): ?>
                        <li class="sidebar-user-item" id="user-item-<?php echo h($s_user['id']); ?>">
                            <a href="profile.php?username=<?php echo urlencode($s_user['username']); ?>">
                                <img src="uploads/profiles/<?php echo h($s_user['profile_picture'] ?? 'default.jpg'); ?>" alt="Photo de profil">
                            </a>
                            <a href="profile.php?username=<?php echo urlencode($s_user['username']); ?>" class="username"><?php echo h($s_user['username']); ?></a>
                            <button class="action-button follow-button" data-user-id="<?php echo h($s_user['id']); ?>" data-action="follow">Suivre</button>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>

            <h3 style="margin-top: 30px;">Amis et abonnements</h3>
            <ul class="sidebar-user-list" id="friends-and-following-list">
                <?php if (empty($friends) && empty($following_only)): ?>
                    <li class="no-suggestions">Commencez à suivre des personnes pour les voir ici.</li>
                <?php else: ?>
                    <?php foreach ($friends as $f_user): ?>
                        <li class="sidebar-user-item" id="user-item-<?php echo h($f_user['id']); ?>">
                            <a href="profile.php?username=<?php echo urlencode($f_user['username']); ?>">
                                <img src="uploads/profiles/<?php echo h($f_user['profile_picture'] ?? 'default.jpg'); ?>" alt="Photo de profil">
                            </a>
                            <a href="profile.php?username=<?php echo urlencode($f_user['username']); ?>" class="username"><?php echo h($f_user['username']); ?></a>
                            <button class="action-button friends-button" data-user-id="<?php echo h($f_user['id']); ?>" data-action="remove_friend">
                                <span class="button-text">Amis</span>
                                <span class="remove-text">Retirer</span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                    <?php foreach ($following_only as $f_user): ?>
                        <li class="sidebar-user-item" id="user-item-<?php echo h($f_user['id']); ?>">
                            <a href="profile.php?username=<?php echo urlencode($f_user['username']); ?>">
                                <img src="uploads/profiles/<?php echo h($f_user['profile_picture'] ?? 'default.jpg'); ?>" alt="Photo de profil">
                            </a>
                            <a href="profile.php?username=<?php echo urlencode($f_user['username']); ?>" class="username"><?php echo h($f_user['username']); ?></a>
                            <button class="action-button followed-button" data-user-id="<?php echo h($f_user['id']); ?>" data-action="unfollow">Abonné(e)</button>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="chat-window" id="chatWindow">
        <div class="chat-header">
            <button class="action-icon" id="backToChatList" style="display:none;"><i class="fas fa-arrow-left"></i></button>
            <span class="chat-title" id="chatHeaderTitle">Discussions</span>
            <div class="chat-buttons">
                <button class="close-btn" id="closeChat">&times;</button>
            </div>
        </div>

        <div class="chat-list" id="chatList">
            <h4>Vos Amis et Abonnements</h4>
            <ul class="chat-users-list">
                <?php if (empty($all_chat_users)): ?>
                    <li class="no-suggestions" style="text-align: left;">Aucun abonné ou ami avec qui discuter pour le moment.</li>
                <?php else: ?>
                    <?php foreach ($all_chat_users as $chat_user): ?>
                        <li class="chat-list-item" data-user-id="<?php echo h($chat_user['id']); ?>" data-username="<?php echo h($chat_user['username']); ?>" data-profile-picture="<?php echo h($chat_user['profile_picture']); ?>">
                            <img src="uploads/profiles/<?php echo h($chat_user['profile_picture'] ?? 'default.jpg'); ?>" alt="Profil">
                            <span class="chat-username"><?php echo h($chat_user['username']); ?></span>
                            <span class="chat-status-dot"></span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

        <div class="chat-conversation" id="chatConversation">
            <div class="chat-messages" id="chatMessages">
                </div>
            <div class="chat-input-area">
                <input type="text" placeholder="Écrire un message..." id="chatMessageInput">
                <button id="sendChatMessage"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Like/Unlike logic (UNCHANGED)
            const likeForms = document.querySelectorAll('.like-form');
            likeForms.forEach(form => {
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    const postId = this.querySelector('input[name="post_id"]').value;
                    let action = this.querySelector('button[name="action"]').value;
                    const likeButton = this.querySelector('button[name="action"]');
                    const likesCountSpan = this.closest('.like-container').querySelector(`.likes-count[data-post-id="${postId}"]`);
                    let currentLikes = parseInt(likesCountSpan.textContent);

                    const formData = new FormData();
                    formData.append('post_id', postId);
                    formData.append('action', action);

                    fetch('index.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (action === 'like') {
                                likeButton.classList.add('liked');
                                likeButton.value = 'unlike';
                                likesCountSpan.textContent = currentLikes + 1;
                            } else {
                                likeButton.classList.remove('liked');
                                likeButton.value = 'like';
                                likesCountSpan.textContent = currentLikes - 1;
                            }
                        } else {
                            console.error('Erreur lors du traitement du like/unlike.');
                            alert('Une erreur est survenue. Veuillez réessayer.');
                        }
                    })
                    .catch(error => {
                        console.error('Erreur réseau ou du serveur:', error);
                        alert('Impossible de communiquer avec le serveur. Vérifiez votre connexion.');
                    });
                });
            });

            // Comment display toggle logic (UNCHANGED)
            const commentButtons = document.querySelectorAll('.comment-button');
            commentButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const postId = this.dataset.postId;
                    const commentFormContainer = document.querySelector(`.comments-section[data-post-id="${postId}"]`);
                    const addCommentForm = commentFormContainer.querySelector('.add-comment-form');
                    if (addCommentForm) {
                        if (addCommentForm.style.display === 'flex') {
                            addCommentForm.style.display = 'none';
                        } else {
                            addCommentForm.style.display = 'flex';
                            addCommentForm.querySelector('input[type="text"]').focus();
                        }
                    }
                });
            });

            // AJAX Comment submission logic (UNCHANGED)
            const ajaxCommentForms = document.querySelectorAll('.ajax-comment-form');
            ajaxCommentForms.forEach(form => {
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    const postId = this.querySelector('input[name="post_id_comment"]').value;
                    const commentContentInput = this.querySelector('input[name="comment_content"]');
                    const commentContent = commentContentInput.value.trim();
                    if (commentContent === '') {
                        alert('Le commentaire ne peut pas être vide.');
                        return;
                    }
                    const formData = new FormData();
                    formData.append('post_id_comment', postId);
                    formData.append('comment_content', commentContent);
                    formData.append('submit_comment_ajax', '1');

                    fetch('index.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            commentContentInput.value = '';
                            const commentsSection = document.querySelector(`.comments-section[data-post-id="${postId}"]`);
                            const noCommentsParagraph = commentsSection.querySelector('.no-posts');
                            if (noCommentsParagraph) {
                                noCommentsParagraph.remove();
                            }
                            const newCommentDiv = document.createElement('div');
                            newCommentDiv.classList.add('comment');
                            newCommentDiv.innerHTML = `
                                <img src="uploads/profiles/${data.comment.profile_picture}" alt="Photo de profil de ${data.comment.username}">
                                <div class="comment-content-wrapper">
                                    <span class="comment-username"><a href="profile.php?username=${encodeURIComponent(data.comment.username)}">${data.comment.username}</a></span>
                                    <span class="comment-text">${data.comment.content.replace(/\n/g, '<br>')}</span>
                                    <span class="comment-timestamp">${data.comment.created_at}</span>
                                </div>
                            `;
                            commentsSection.insertBefore(newCommentDiv, form);
                            const commentsCountHeading = commentsSection.querySelector('h4');
                            if (commentsCountHeading) {
                                let currentCount = parseInt(commentsCountHeading.textContent.match(/\((\d+)\)/)[1]);
                                commentsCountHeading.textContent = `Commentaires (${currentCount + 1})`;
                            }
                        } else {
                            alert('Erreur: ' + data.message);
                        }
                    })
                    .catch(error => {
                                                console.error('Erreur réseau ou du serveur:', error);
                        alert('Impossible d\'envoyer le commentaire. Veuillez réessayer.');
                    });
                });
            });

            // FOLLOW / UNFOLLOW / REMOVE FRIEND Logic (UNCHANGED)
            document.addEventListener('click', function(event) {
                const target = event.target.closest('.action-button');

                if (target) {
                    event.preventDefault();

                    const userIdToTarget = target.dataset.userId;
                    const action = target.dataset.action;
                    const listItem = document.getElementById(`user-item-${userIdToTarget}`);
                    const suggestedUsersList = document.getElementById('suggested-users-list');
                    const friendsAndFollowingList = document.getElementById('friends-and-following-list');

                    const formData = new FormData();
                    formData.append('target_user_id', userIdToTarget);
                    formData.append('follow_action', action);

                    fetch('index.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (data.action_performed === 'followed') {
                                target.textContent = 'Abonné(e)';
                                target.classList.remove('follow-button');
                                target.classList.add('followed-button');
                                target.dataset.action = 'unfollow';

                                if (friendsAndFollowingList && listItem) {
                                    friendsAndFollowingList.appendChild(listItem);
                                }
                            } else if (data.action_performed === 'became_friends') {
                                target.innerHTML = '<span class="button-text">Amis</span><span class="remove-text">Retirer</span>';
                                target.classList.remove('follow-button', 'followed-button');
                                target.classList.add('friends-button');
                                target.dataset.action = 'remove_friend';

                                if (friendsAndFollowingList && listItem) {
                                    friendsAndFollowingList.appendChild(listItem);
                                }
                            } else if (data.action_performed === 'unfollowed') {
                                target.textContent = 'Suivre';
                                target.classList.remove('followed-button');
                                target.classList.add('follow-button');
                                target.dataset.action = 'follow';

                                if (suggestedUsersList && listItem) {
                                    suggestedUsersList.appendChild(listItem);
                                }
                            } else if (data.action_performed === 'removed_friend') {
                                target.textContent = 'Suivre';
                                target.classList.remove('friends-button');
                                target.classList.add('follow-button');
                                target.dataset.action = 'follow';

                                if (suggestedUsersList && listItem) {
                                    suggestedUsersList.appendChild(listItem);
                                }
                            }
                            updateEmptyListMessages();
                        } else {
                            alert('Erreur: ' + (data.message || 'Impossible de traiter votre demande.'));
                        }
                    })
                    .catch(error => {
                        console.error('Erreur réseau ou du serveur:', error);
                        alert('Une erreur est survenue lors du traitement de l\'action de suivi.');
                    });
                }
            });

            function updateEmptyListMessages() {
                const suggestedUsersList = document.getElementById('suggested-users-list');
                const friendsAndFollowingList = document.getElementById('friends-and-following-list');

                if (suggestedUsersList.children.length === 0 || (suggestedUsersList.children.length === 1 && suggestedUsersList.firstElementChild.classList.contains('no-suggestions'))) {
                    if (!suggestedUsersList.querySelector('.no-suggestions')) {
                         const li = document.createElement('li');
                         li.classList.add('no-suggestions');
                         li.textContent = 'Aucune suggestion pour le moment.';
                         suggestedUsersList.appendChild(li);
                    }
                } else {
                    const noSuggestionsMessage = suggestedUsersList.querySelector('.no-suggestions');
                    if (noSuggestionsMessage) {
                        noSuggestionsMessage.remove();
                    }
                }

                if (friendsAndFollowingList.children.length === 0 || (friendsAndFollowingList.children.length === 1 && friendsAndFollowingList.firstElementChild.classList.contains('no-suggestions'))) {
                     if (!friendsAndFollowingList.querySelector('.no-suggestions')) {
                        const li = document.createElement('li');
                        li.classList.add('no-suggestions');
                        li.textContent = 'Commencez à suivre des personnes pour les voir ici.';
                        friendsAndFollowingList.appendChild(li);
                     }
                } else {
                    const noFollowedMessage = friendsAndFollowingList.querySelector('.no-suggestions');
                    if (noFollowedMessage) {
                        noFollowedMessage.remove();
                    }
                }
            }

            // CHAT WINDOW LOGIC (MODIFIED for message sending/receiving)
            const messengerIcon = document.getElementById('messenger-icon');
            const chatWindow = document.getElementById('chatWindow');
            const closeChatBtn = document.getElementById('closeChat');
            const chatList = document.getElementById('chatList');
            const chatConversation = document.getElementById('chatConversation');
            const chatHeaderTitle = document.getElementById('chatHeaderTitle');
            const backToChatListBtn = document.getElementById('backToChatList');
            const chatUsersList = document.querySelector('.chat-users-list');
            const chatMessagesContainer = document.getElementById('chatMessages');
            const chatMessageInput = document.getElementById('chatMessageInput');
            const sendChatMessageBtn = document.getElementById('sendChatMessage');

            let currentChattingWith = null; // Store ID of the user currently chatting with
            let currentChattingUsername = ''; // Store username of the user currently chatting with
            let currentChattingProfilePicture = ''; // Store profile picture of the user currently chatting with

            // Current user's info from PHP
            const currentUserId = <?php echo json_encode($user_id); ?>;
            const currentUserProfilePicture = <?php echo json_encode($profile_picture); ?>;

            messengerIcon.addEventListener('click', function() {
                if (chatWindow.style.display === 'flex') {
                    chatWindow.style.display = 'none';
                    showChatList(); // Reset view to list when closing
                } else {
                    chatWindow.style.display = 'flex';
                    showChatList(); // Show chat list on opening
                }
            });

            closeChatBtn.addEventListener('click', function() {
                chatWindow.style.display = 'none';
                showChatList(); // Ensure list is visible for next open
            });

            backToChatListBtn.addEventListener('click', function() {
                showChatList();
            });

            function showChatList() {
                chatList.style.display = 'block';
                chatConversation.style.display = 'none';
                chatHeaderTitle.textContent = 'Discussions'; // Generic title
                backToChatListBtn.style.display = 'none'; // Hide back button
                currentChattingWith = null;
                currentChattingUsername = '';
                currentChattingProfilePicture = '';
            }

            async function showConversation(userId, username, profilePicture) {
                chatList.style.display = 'none';
                chatConversation.style.display = 'flex';
                chatHeaderTitle.textContent = username;
                backToChatListBtn.style.display = 'inline-block';
                currentChattingWith = userId;
                currentChattingUsername = username;
                currentChattingProfilePicture = profilePicture;

                chatMessagesContainer.innerHTML = '<p style="text-align: center; color: #666; margin-top: 20px;">Chargement des messages...</p>';
                
                const formData = new FormData();
                formData.append('load_messages_ajax', '1');
                formData.append('other_user_id', userId);

                try {
                    const response = await fetch('index.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    chatMessagesContainer.innerHTML = ''; // Clear loading message

                    if (data.success && data.messages.length > 0) {
                        data.messages.forEach(message => {
                            addMessageToChat(message);
                        });
                    } else {
                        chatMessagesContainer.innerHTML = `
                            <p style="text-align: center; color: #666; margin-top: 20px;">
                                Aucune conversation avec ${username} pour le moment.
                            </p>
                            <p style="text-align: center; color: #666;">
                                Envoyez votre premier message !
                            </p>
                        `;
                    }
                    scrollToBottom();

                } catch (error) {
                    console.error('Erreur lors du chargement des messages:', error);
                    chatMessagesContainer.innerHTML = '<p style="text-align: center; color: #fa3e3e; margin-top: 20px;">Erreur lors du chargement des messages.</p>';
                }
            }

            function addMessageToChat(message) {
                const messageWrapper = document.createElement('div');
                messageWrapper.classList.add('message-wrapper');

                // Check if the message is sent by the current user
                const isSentByMe = message.sender_id == currentUserId; // Use == for comparison as IDs might be numbers or strings
                if (isSentByMe) {
                    messageWrapper.classList.add('sent');
                } else {
                    messageWrapper.classList.add('received');
                }

                // Add avatar for received messages
                if (!isSentByMe) {
                    const avatarImg = document.createElement('img');
                    avatarImg.src = `uploads/profiles/${currentChattingProfilePicture || 'default.jpg'}`; // Use interlocutor's picture for received
                    avatarImg.alt = 'Profil';
                    avatarImg.classList.add('message-avatar');
                    messageWrapper.appendChild(avatarImg);
                }


                const messageBubble = document.createElement('div');
                messageBubble.classList.add('message-bubble');
                if (isSentByMe) {
                    messageBubble.classList.add('sent');
                } else {
                    messageBubble.classList.add('received');
                }

                const messageText = document.createElement('span');
                messageText.classList.add('message-text');
                messageText.textContent = message.content;
                messageBubble.appendChild(messageText);

                const timestampTick = document.createElement('span');
                timestampTick.classList.add('message-timestamp-tick');
                // Format the time as HH:MM
                const date = new Date(message.created_at);
                const hours = date.getHours().toString().padStart(2, '0');
                const minutes = date.getMinutes().toString().padStart(2, '0');
                timestampTick.textContent = `${hours}:${minutes}`;

                // Add a checkmark icon for sent messages
                if (isSentByMe) {
                    const checkIcon = document.createElement('i');
                    checkIcon.classList.add('fas', 'fa-check');
                    // You can add a second check for "read" status if `is_read` is true
                    // if (message.is_read) { checkIcon.classList.add('fa-check-double'); }
                    timestampTick.appendChild(checkIcon);
                }
                messageBubble.appendChild(timestampTick);

                messageWrapper.appendChild(messageBubble);

                // Add avatar for sent messages (after the bubble for flex-direction: row-reverse)
                if (isSentByMe) {
                    const avatarImg = document.createElement('img');
                    avatarImg.src = `uploads/profiles/${currentUserProfilePicture || 'default.jpg'}`; // Use current user's picture for sent
                    avatarImg.alt = 'Moi';
                    avatarImg.classList.add('message-avatar');
                    messageWrapper.appendChild(avatarImg);
                }

                chatMessagesContainer.appendChild(messageWrapper);
            }

            function scrollToBottom() {
                chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight;
            }

            // Gérer le clic sur un utilisateur dans la liste de chat
            chatUsersList.addEventListener('click', function(event) {
                const listItem = event.target.closest('.chat-list-item');
                if (listItem) {
                    const userId = listItem.dataset.userId;
                    const username = listItem.dataset.username;
                    const profilePicture = listItem.dataset.profilePicture;
                    showConversation(userId, username, profilePicture);
                }
            });

            sendChatMessageBtn.addEventListener('click', async function() {
                const messageContent = chatMessageInput.value.trim();
                if (messageContent && currentChattingWith) {
                    const formData = new FormData();
                    formData.append('send_message_ajax', '1');
                    formData.append('receiver_id', currentChattingWith);
                    formData.append('content', messageContent);

                    try {
                        const response = await fetch('index.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();

                        if (data.success) {
                            // Add the newly sent message to the chat display
                            const sentMessage = data.sent_message;
                            addMessageToChat(sentMessage);
                            chatMessageInput.value = ''; // Clear input
                            scrollToBottom();
                        } else {
                            alert('Erreur lors de l\'envoi du message: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Erreur réseau ou du serveur lors de l\'envoi:', error);
                        alert('Impossible d\'envoyer le message. Vérifiez votre connexion.');
                    }
                }
            });

            chatMessageInput.addEventListener('keypress', function(event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    sendChatMessageBtn.click();
                }
            });

            updateEmptyListMessages();
        });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      var sidebar = document.querySelector('.sidebar-classic');
      var toggle = document.getElementById('sidebarToggle');
      var overlay = document.getElementById('sidebarOverlay');
      var closeBtn = document.getElementById('closeSidebar');
      if(toggle && sidebar && overlay && closeBtn) {
        toggle.addEventListener('click', function() {
          sidebar.classList.add('open');
          overlay.classList.add('active');
          closeBtn.style.display = 'block';
        });
        closeBtn.addEventListener('click', function() {
          sidebar.classList.remove('open');
          overlay.classList.remove('active');
          closeBtn.style.display = 'none';
        });
        overlay.addEventListener('click', function() {
          sidebar.classList.remove('open');
          overlay.classList.remove('active');
          closeBtn.style.display = 'none';
        });
      }
    });
    </script>
</body>
</html>
