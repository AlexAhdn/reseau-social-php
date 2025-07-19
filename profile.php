<?php
// profile.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Initialiser le compteur de notifications pour éviter les warnings
$notif_count = 0;
$profile_picture = (isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])) ? $_SESSION['profile_picture'] : 'default.jpg';
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $sql_notif_count = "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0";
    if ($stmt_notif_count = $conn->prepare($sql_notif_count)) {
        $stmt_notif_count->bind_param("i", $user_id);
        $stmt_notif_count->execute();
        $stmt_notif_count->bind_result($notif_count);
        $stmt_notif_count->fetch();
        $stmt_notif_count->close();
    }
}


$current_user_id = $_SESSION['user_id'];
$current_username = $_SESSION['username'];
$current_profile_picture = $_SESSION['profile_picture'] ?? 'avatar.jpg'; // Récupérer la photo de profil de la session

$profile_username = $_GET['username'] ?? null;

if (!$profile_username) {
    $profile_username = $current_username;
}

$user_data = null;
$user_posts = [];
$is_my_profile = ($profile_username == $current_username);

// Récupérer les informations de l'utilisateur du profil
$sql_user = "SELECT id, username, email, profile_picture, created_at, date_of_birth FROM users WHERE username = ?";
if ($stmt = $conn->prepare($sql_user)) {
    $stmt->bind_param("s", $profile_username);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $user_data = $result->fetch_assoc();
        }
    }
    $stmt->close();
}

if (!$user_data) {
    echo "Profil non trouvé.";
    $conn->close();
    exit();
}

// Gérer l'upload de la photo de profil (traitement POST)
$profile_upload_error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_profile_pic'])) {
    if ($is_my_profile && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/profiles/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $image_name = uniqid() . "_" . basename($_FILES["profile_pic"]["name"]);
        $target_file = $target_dir . $image_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $check = getimagesize($_FILES["profile_pic"]["tmp_name"]);
        if ($check === false) {
            $profile_upload_error = "Le fichier n'est pas une image.";
        } else if ($_FILES["profile_pic"]["size"] > 2000000) { // Max 2MB
            $profile_upload_error = "Désolé, votre fichier est trop grand (max 2MB).";
        } else if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
            $profile_upload_error = "Désolé, seuls les formats JPG, JPEG, PNG & GIF sont autorisés.";
        } else {
            if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
                $sql_update_profile_pic = "UPDATE users SET profile_picture = ? WHERE id = ?";
                if ($stmt_update = $conn->prepare($sql_update_profile_pic)) {
                    $stmt_update->bind_param("si", $image_name, $current_user_id);
                    if ($stmt_update->execute()) {
                        $user_data['profile_picture'] = $image_name;
                        $_SESSION['profile_picture'] = $image_name; // Mettre à jour la session
                    } else {
                        $profile_upload_error = "Erreur lors de la mise à jour de la base de données.";
                    }
                    $stmt_update->close();
                }
            } else {
                $profile_upload_error = "Désolé, une erreur s'est produite lors du téléchargement de votre fichier.";
            }
        }
    } else {
        $profile_upload_error = "Veuillez sélectionner un fichier à télécharger ou une erreur s'est produite.";
    }
}

// Gérer les actions de like/unlike pour les posts du profil via AJAX
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['post_id'])) {
    $post_id_action = $_POST['post_id'];
    $action = $_POST['action'];

    if ($action == 'like') {
        $sql = "INSERT IGNORE INTO likes (post_id, user_id) VALUES (?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ii", $post_id_action, $current_user_id);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($action == 'unlike') {
        $sql = "DELETE FROM likes WHERE post_id = ? AND user_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ii", $post_id_action, $current_user_id);
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
            $stmt->bind_param("iis", $post_id_comment, $current_user_id, $comment_content);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['comment'] = [
                    'content' => h($comment_content),
                    'created_at' => date("d/m/Y H:i"),
                    'username' => h($current_username),
                    'profile_picture' => h($current_profile_picture)
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

// GESTION AJAX : Vérifier si l'utilisateur courant suit ce profil
if (isset($_GET['check_follow']) && isset($_GET['target_id'])) {
    header('Content-Type: application/json');
    $target_id = (int)$_GET['target_id'];
    $status = 'not_following';
    if ($current_user_id !== $target_id) {
        $sql = "SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ii", $current_user_id, $target_id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $status = 'following';
            }
            $stmt->close();
        }
    }
    echo json_encode(['status' => $status]);
    exit();
}

// Récupérer les statistiques de l'utilisateur (abonnés et abonnements)
$followers_count = 0;
$following_count = 0;

// Compter les abonnés (utilisateurs qui suivent cet utilisateur)
$sql_followers = "SELECT COUNT(*) FROM follows WHERE followed_id = ?";
if ($stmt_followers = $conn->prepare($sql_followers)) {
    $stmt_followers->bind_param("i", $user_data['id']);
    $stmt_followers->execute();
    $stmt_followers->bind_result($followers_count);
    $stmt_followers->fetch();
    $stmt_followers->close();
}

// Compter les abonnements (utilisateurs que cet utilisateur suit)
$sql_following = "SELECT COUNT(*) FROM follows WHERE follower_id = ?";
if ($stmt_following = $conn->prepare($sql_following)) {
    $stmt_following->bind_param("i", $user_data['id']);
    $stmt_following->execute();
    $stmt_following->bind_result($following_count);
    $stmt_following->fetch();
    $stmt_following->close();
}

// Récupérer les publications de cet utilisateur avec les likes et commentaires
$sql_posts = "SELECT p.id, p.content, p.image_path, p.created_at,
               (SELECT COUNT(*) FROM likes WHERE post_id = p.id) AS likes_count,
               (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) AS user_liked
              FROM posts p
              WHERE p.user_id = ?
              ORDER BY p.created_at DESC";

if ($stmt = $conn->prepare($sql_posts)) {
    $stmt->bind_param("ii", $current_user_id, $user_data['id']);
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
        $user_posts[] = $post;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil de <?php echo h($user_data['username']); ?> - Mon Réseau Social</title>
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

        .container { max-width: 800px; margin: 20px auto; padding: 0 20px; }

        .profile-container {
            max-width: 935px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .profile-header {
            display: flex;
            align-items: flex-start;
            gap: 80px;
            margin-bottom: 44px;
            padding: 0 20px;
        }
        
        .profile-picture-section {
            flex-shrink: 0;
        }
        
        .profile-picture { 
            width: 150px; 
            height: 150px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 3px solid #008080;
            box-shadow: 0 2px 8px rgba(0, 128, 128, 0.2);
        }
        
        .profile-info-section {
            flex: 1;
            min-width: 0;
        }
        
        .profile-username-row {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .profile-username { 
            font-size: 28px; 
            font-weight: 300;
            color: #008080;
            margin: 0;
        }
        
        .profile-actions {
            display: flex;
            gap: 8px;
            margin-top: 20px;
        }
        
        .btn-edit {
            background-color: #008080;
            border: 1px solid #008080;
            border-radius: 4px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-edit:hover {
            background-color: #006666;
            border-color: #006666;
        }
        
        .btn-follow {
            background-color: #008080;
            border: none;
            border-radius: 4px;
            padding: 5px 9px;
            font-size: 14px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-follow:hover {
            background-color: #006666;
        }
        
        .profile-stats { 
            display: flex; 
            gap: 40px;
            margin-bottom: 20px;
        }
        
        .profile-stat { 
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .profile-stat span { 
            font-weight: 600;
            font-size: 16px;
            color: #008080;
        }
        
        .profile-stat small { 
            font-size: 16px;
            color: #262626;
        }
        
        .profile-bio {
            margin-bottom: 20px;
        }
        
        .profile-bio .username {
            font-weight: 600;
            color: #262626;
            margin-bottom: 5px;
        }
        
        .profile-bio .bio-text {
            color: #262626;
            line-height: 1.4;
            margin-bottom: 5px;
        }
        
        .profile-bio .website {
            color: #008080;
            text-decoration: none;
            font-weight: 600;
        }
        
        .profile-bio .website:hover {
            text-decoration: underline;
        }
        
        .profile-tabs {
            border-top: 1px solid #dbdbdb;
            display: flex;
            justify-content: center;
            margin-top: 44px;
        }
        
        .profile-tab {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 16px 0;
            margin-right: 60px;
            color: #8e8e8e;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-top: 1px solid transparent;
            transition: color 0.2s;
        }
        
        .profile-tab.active {
            color: #008080;
            border-top-color: #008080;
        }
        
        .profile-tab:hover {
            color: #008080;
        }

        .profile-tab i {
            font-size: 12px;
        }
        
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
            margin-top: 20px;
        }
        
        .post-thumbnail {
            aspect-ratio: 1;
            object-fit: cover;
            width: 100%;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        
        .post-thumbnail:hover {
            opacity: 0.8;
        }
        
        .no-posts-message {
            text-align: center;
            color: #8e8e8e;
            font-size: 16px;
            margin-top: 60px;
        }
        
        /* Modal pour changer la photo de profil */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }
        
        .modal {
            background-color: white;
            border-radius: 12px;
            padding: 24px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #dbdbdb;
        }
        
        .modal-title {
            font-size: 16px;
            font-weight: 600;
            color: #262626;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            color: #8e8e8e;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            color: #262626;
        }
        
        .modal-content {
            margin-bottom: 20px;
        }
        
        .modal-form label {
            display: block;
            font-weight: 600;
            color: #262626;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .modal-form input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #dbdbdb;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .modal-form input[type="file"]::file-selector-button {
            background-color: #008080;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-cancel {
            background-color: transparent;
            border: 1px solid #dbdbdb;
            border-radius: 4px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 600;
            color: #262626;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-cancel:hover {
            background-color: #fafafa;
        }
        
        .btn-save {
            background-color: #008080;
            border: 1px solid #008080;
            border-radius: 4px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-save:hover {
            background-color: #006666;
            border-color: #006666;
        }
        
        .profile-picture {
            cursor: pointer;
            transition: opacity 0.2s;
        }
        
        .profile-picture:hover {
            opacity: 0.8;
        }
        .post { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,.1); margin-bottom: 20px; }
        .post-header { display: flex; align-items: center; margin-bottom: 15px; }
        .post-header img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-right: 10px; }
        .post-header .username { font-weight: bold; color: #1c1e21; }
        .post-header .timestamp { font-size: 13px; color: #65676b; margin-left: auto; }
        .post-content { font-size: 16px; color: #1c1e21; line-height: 1.5; margin-bottom: 15px; }
        .post-image { max-width: 100%; border-radius: 6px; margin-top: 10px; display: block; }
        .no-posts { text-align: center; color: #65676b; }
        .profile-upload-error { color: #fa3e3e; font-size: 14px; margin-top: 10px; }

        .post-actions {
            display: flex;
            justify-content: space-around;
            padding-top: 15px;
            border-top: 1px solid #eee;
            margin-top: 15px;
            align-items: center;
        }
        .post-actions .like-section {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .post-actions button {
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 5px;
            transition: background-color .2s, color .2s;
        }
        .post-actions button:hover {
            background-color: #f2f2f2;
        }

        .post-actions button .fa-thumbs-up {
            color: #65676b;
            font-size: 15px;
        }

        .post-actions button:hover .fa-thumbs-up {
            color: #1877f2;
        }

        .post-actions button.liked .fa-thumbs-up {
            color: #1877f2;
        }

        .likes-count {
            font-size: 14px;
            color: #65676b;
            margin: 0;
            padding-left: 5px;
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
            color: #65676b;
        }
        .post-actions .comment-button:hover {
            background-color: #f2f2f2;
            color: #1877f2;
        }

        .add-comment-form {
            display: none;
            margin-top: 15px;
            align-items: center;
        }

        .comments-section { margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; }
        .comment { display: flex; margin-bottom: 10px; }
        .comment img { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; margin-right: 8px; }
        .comment-content-wrapper { background-color: #f2f2f2; padding: 8px 12px; border-radius: 18px; max-width: calc(100% - 40px); }
        .comment-username { font-weight: bold; font-size: 14px; color: #1c1e21; }
        .comment-text { font-size: 14px; color: #1c1e21; }
        .comment-timestamp { font-size: 12px; color: #999; margin-left: 5px; }

        .add-comment-form input[type="text"] { flex-grow: 1; padding: 8px 12px; border: 1px solid #dddfe2; border-radius: 18px; margin-right: 10px; font-size: 14px; }
        .add-comment-form button { background-color: #1877f2; color: white; border: none; padding: 8px 15px; border-radius: 18px; cursor: pointer; font-size: 14px; transition: background-color .2s; }
        .add-comment-form button:hover { background-color: #166fe5; }
        .comment-error { color: #fa3e3e; font-size: 14px; margin-top: 5px; }

        @media (max-width: 900px) {
  .sidebar-toggle {
    display: block !important;
  }
  .header-center,
  .header-right {
    display: none !important;
  }
  .header {
    justify-content: center !important;
    align-items: center !important;
    text-align: center !important;
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
        <div class="header-left" style="justify-content: flex-start;">
            <a href="index.php" class="logo" style="color:#fff;">Nexa</a>
        </div>
        <div class="header-center">
            <a href="index.php" class="nav-icon"><i class="fas fa-home"></i></a>
            <a href="friends_list.php" class="nav-icon"><i class="fas fa-user-friends"></i></a>
            <a href="messages.php" class="nav-icon" title="Messagerie"><i class="fab fa-facebook-messenger"></i></a>
            <a href="notif.php" class="nav-icon" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if (isset($notif_count) && $notif_count > 0): ?>
                    <span class="notif-badge"><?php echo $notif_count; ?></span>
                <?php endif; ?>
            </a>
        </div>
        <div class="header-right">
            <a href="profile.php?username=<?php echo urlencode($username); ?>">
                <img src="uploads/profiles/<?php echo h($profile_picture); ?>" alt="Photo de profil" class="profile-thumbnail">
            </a>
            <a href="logout.php" class="icon-button" title="Déconnexion"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>

    <div class="profile-container">
        <div class="profile-header">
            <div class="profile-picture-section">
            <img src="uploads/profiles/<?php echo h($user_data['profile_picture'] ?? 'default.jpg'); ?>" alt="Photo de profil" class="profile-picture">
            </div>
            
            <div class="profile-info-section">
                <div class="profile-username-row">
            <h2 class="profile-username"><?php echo h($user_data['username']); ?></h2>
                </div>
                
            <div class="profile-stats">
                <div class="profile-stat">
                    <span><?php echo count($user_posts); ?></span>
                        <small>publications</small>
                    </div>
                    <div class="profile-stat">
                        <span><?php echo $followers_count; ?></span>
                        <small>abonnés</small>
                    </div>
                    <div class="profile-stat">
                        <span><?php echo $following_count; ?></span>
                        <small>abonnements</small>
                </div>
            </div>

                <div class="profile-bio">
                    <div class="username"><?php echo h($user_data['username']); ?></div>
                    <div class="bio-text"><?php echo h($user_data['email']); ?></div>
                    <?php if (!empty($user_data['date_of_birth'])): ?>
                        <div class="bio-text">Né(e) le <?php echo date("d/m/Y", strtotime($user_data['date_of_birth'])); ?></div>
                    <?php endif; ?>
                    <div class="bio-text">Membre depuis <?php echo date("d/m/Y", strtotime($user_data['created_at'])); ?></div>
        </div>

                <?php if (!$is_my_profile): ?>
                <div class="profile-actions" style="display: flex; gap: 12px; margin-top: 20px;">
                    <button id="followBtn" class="btn-follow" data-user-id="<?php echo $user_data['id']; ?>">
                        <!-- Texte dynamique JS -->
                    </button>
                    <a href="messages.php?to=<?php echo urlencode($user_data['username']); ?>" class="btn-edit" style="background-color:#fff; color:#008080; border:1px solid #008080;">Message</a>
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const followBtn = document.getElementById('followBtn');
                    const targetUserId = followBtn.dataset.userId;
                    // Initialiser le texte du bouton selon l'état (AJAX)
                    fetch('profile.php?check_follow=1&target_id=' + targetUserId)
                        .then(r => r.json())
                        .then(data => setFollowBtn(data.status));

                    function setFollowBtn(status) {
                        if (status === 'following') {
                            followBtn.textContent = 'Abonné(e)';
                            followBtn.className = 'btn-follow followed';
                        } else {
                            followBtn.textContent = 'Suivre';
                            followBtn.className = 'btn-follow';
                        }
                    }
                    followBtn.onclick = function(e) {
                        e.preventDefault();
                        let action = followBtn.classList.contains('followed') ? 'unfollow' : 'follow';
                        const formData = new FormData();
                        formData.append('target_user_id', targetUserId);
                        formData.append('follow_action', action);
                        fetch('index.php', { method: 'POST', body: formData })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    setFollowBtn(action === 'follow' ? 'following' : 'not_following');
                                }
                            });
                    };
                });
                </script>
                <?php elseif ($is_my_profile): ?>
                <div class="profile-actions" style="margin-top: 20px;"><button class="btn-edit">Modifier le profil</button></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-tabs">
            <a href="#" class="profile-tab active">
                <i class="fas fa-th"></i>
                Publications
            </a>
            <a href="#" class="profile-tab">
                <i class="fas fa-bookmark"></i>
                Enregistré
            </a>
            <a href="#" class="profile-tab">
                <i class="fas fa-user-tag"></i>
                Tagué
            </a>
        </div>

        <?php if (empty($user_posts)): ?>
            <div class="no-posts-message">
                <p>Aucune publication pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="posts-grid">
            <?php foreach ($user_posts as $post): ?>
                    <div class="post-item">
                    <?php if (!empty($post['image_path'])): ?>
                            <img src="<?php echo h($post['image_path']); ?>" alt="Publication" class="post-thumbnail">
                                <?php else: ?>
                            <div class="post-thumbnail" style="background-color: #fafafa; display: flex; align-items: center; justify-content: center; color: #8e8e8e; font-size: 14px;">
                                <?php echo substr(h($post['content']), 0, 50) . (strlen($post['content']) > 50 ? '...' : ''); ?>
                            </div>
                                <?php endif; ?>
                        </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
                    </div>

    <!-- Modal pour changer la photo de profil -->
    <div class="modal-overlay" id="profileModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Changer la photo de profil</h3>
                <button class="modal-close" id="closeModal">&times;</button>
                                    </div>
            <div class="modal-content">
                <form class="modal-form" action="<?php echo h($_SERVER["PHP_SELF"]); ?>?username=<?php echo urlencode($user_data['username']); ?>" method="post" enctype="multipart/form-data">
                    <label for="profile_pic">Sélectionner une nouvelle photo :</label>
                    <input type="file" name="profile_pic" id="profile_pic" accept="image/*" required>
                    <?php if (!empty($profile_upload_error)): ?>
                        <div class="profile-upload-error"><?php echo h($profile_upload_error); ?></div>
                        <?php endif; ?>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" id="cancelUpload">Annuler</button>
                        <button type="submit" name="upload_profile_pic" class="btn-save">Enregistrer</button>
                    </div>
                        </form>
                    </div>
                </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal pour changer la photo de profil
            const profilePicture = document.querySelector('.profile-picture');
            const modal = document.getElementById('profileModal');
            const closeModal = document.getElementById('closeModal');
            const cancelUpload = document.getElementById('cancelUpload');
            
            // Ouvrir la modal en cliquant sur la photo de profil
            if (profilePicture && <?php echo $is_my_profile ? 'true' : 'false'; ?>) {
                profilePicture.addEventListener('click', function() {
                    modal.style.display = 'flex';
                });
            }
            
            // Fermer la modal
            function closeModalFunction() {
                modal.style.display = 'none';
            }
            
            closeModal.addEventListener('click', closeModalFunction);
            cancelUpload.addEventListener('click', closeModalFunction);
            
            // Fermer en cliquant sur l'overlay
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModalFunction();
                }
            });
            
            // Fermer avec la touche Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.style.display === 'flex') {
                    closeModalFunction();
                }
            });

            const likeForms = document.querySelectorAll('.like-form');

            likeForms.forEach(form => {
                form.addEventListener('submit', function(event) {
                    event.preventDefault();

                    const postId = this.querySelector('input[name="post_id"]').value;
                    let action = this.querySelector('button[name="action"]').value;
                    const likeButton = this.querySelector('button[name="action"]');
                    const likesCountSpan = document.querySelector(`.likes-count[data-post-id="${postId}"]`);
                    let currentLikes = parseInt(likesCountSpan.textContent);

                    const formData = new FormData();
                    formData.append('post_id', postId);
                    formData.append('action', action);

                    const currentUrl = window.location.href.split('?')[0];
                    let fetchTarget = currentUrl;
                    if (window.location.search.includes('username=')) {
                        fetchTarget = fetchTarget + window.location.search;
                    }

                    fetch(fetchTarget, {
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

                    const currentUrl = window.location.href.split('?')[0];
                    let fetchTarget = currentUrl;
                    if (window.location.search.includes('username=')) {
                        fetchTarget = fetchTarget + window.location.search;
                    }

                    fetch(fetchTarget, {
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
        });
    </script>
</body>
</html>
