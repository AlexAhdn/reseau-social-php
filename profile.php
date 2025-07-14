<?php
// profile.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
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
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 0; }
        .header {
            background-color: #fff;
            padding: 10px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { margin: 0; font-size: 24px; color: #1877f2; }

        .header-profile-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header-profile-section .profile-thumbnail {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #1877f2;
        }
        .header-profile-section .logout-link {
            font-size: 14px;
            color: #65676b;
            text-decoration: none;
            transition: color .2s;
        }
        .header-profile-section .logout-link:hover {
            color: #fa3e3e;
        }

        .container { max-width: 800px; margin: 20px auto; padding: 0 20px; }

        /* Styles pour la navigation centrée au-dessus du fil d'actualité */
        .feed-navigation {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-bottom: 20px;
            background-color: #fff;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .feed-navigation a {
            text-decoration: none;
            color: #1877f2;
            font-size: 1.8em;
            transition: color .2s;
        }
        .feed-navigation a:hover {
            color: #166fe5;
        }

        .profile-card { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,.1); text-align: center; margin-bottom: 20px; }
        .profile-picture { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; margin-bottom: 15px; border: 3px solid #1877f2; }
        .profile-username { font-size: 28px; color: #1c1e21; margin-bottom: 5px; }
        .profile-email { font-size: 16px; color: #65676b; margin-bottom: 20px; }
        .profile-dob { font-size: 14px; color: #65676b; margin-bottom: 10px; }
        .profile-stats { display: flex; justify-content: center; gap: 20px; margin-bottom: 20px; }
        .profile-stat span { display: block; font-weight: bold; font-size: 18px; }
        .profile-stat small { color: #65676b; }
        .upload-form { margin-top: 15px; }
        .upload-form input[type="file"] { margin-bottom: 10px; }
        .upload-form button { background-color: #4CAF50; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-size: 14px; transition: background-color .2s; }
        .upload-form button:hover { background-color: #45a049; }
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
    </style>
</head>
<body>
    <div class="header">
        <h1>Mon Réseau Social</h1>
        <div class="header-profile-section">
            <a href="profile.php?username=<?php echo urlencode($current_username); ?>">
                <img src="uploads/profiles/<?php echo h($current_profile_picture); ?>" alt="Photo de profil" class="profile-thumbnail">
            </a>
            <a href="logout.php" class="logout-link">Déconnexion</a>
        </div>
    </div>

    <div class="container">
        <div class="feed-navigation">
            <a href="index.php"><i class="fas fa-home"></i></a>
            <a href="#"> <i class="fas fa-bell"></i></a>
            <a href="#"> <i class="fab fa-facebook-messenger"></i></a>
        </div>

        <div class="profile-card">
            <img src="uploads/profiles/<?php echo h($user_data['profile_picture'] ?? 'default.jpg'); ?>" alt="Photo de profil" class="profile-picture">
            <h2 class="profile-username"><?php echo h($user_data['username']); ?></h2>
            <p class="profile-email"><?php echo h($user_data['email']); ?></p>
            <?php if (!empty($user_data['date_of_birth'])): ?>
                <p class="profile-dob">Né(e) le : <?php echo date("d/m/Y", strtotime($user_data['date_of_birth'])); ?></p>
            <?php endif; ?>
            <div class="profile-stats">
                <div class="profile-stat">
                    <span><?php echo count($user_posts); ?></span>
                    <small>Publications</small>
                </div>
            </div>

            <?php if ($is_my_profile): ?>
                <form class="upload-form" action="<?php echo h($_SERVER["PHP_SELF"]); ?>?username=<?php echo urlencode($user_data['username']); ?>" method="post" enctype="multipart/form-data">
                    <label for="profile_pic">Changer ma photo de profil :</label>
                    <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
                    <button type="submit" name="upload_profile_pic">Télécharger</button>
                    <?php if (!empty($profile_upload_error)): ?>
                        <div class="profile-upload-error"><?php echo h($profile_upload_error); ?></div>
                    <?php endif; ?>
                </form>
            <?php else: ?>
                <?php endif; ?>
        </div>

        

        <h2>Publications de <?php echo h($user_data['username']); ?></h2>
        <?php if (empty($user_posts)): ?>
            <p class="no-posts">Cet utilisateur n'a pas encore de publications.</p>
        <?php else: ?>
            <?php foreach ($user_posts as $post): ?>
                <div class="post">
                    <div class="post-header">
                        <img src="uploads/profiles/<?php echo h($user_data['profile_picture'] ?? 'default.jpg'); ?>" alt="Photo de profil de <?php echo h($user_data['username']); ?>">
                        <span class="username"><?php echo h($user_data['username']); ?></span>
                        <span class="timestamp"><?php echo date("d/m/Y H:i", strtotime($post['created_at'])); ?></span>
                    </div>
                    <div class="post-content">
                        <?php echo nl2br(h($post['content'])); ?>
                    </div>
                    <?php if (!empty($post['image_path'])): ?>
                        <img src="<?php echo h($post['image_path']); ?>" alt="Image de publication" class="post-image">
                    <?php endif; ?>

                    <div class="post-actions">
                        <div class="like-section">
                            <form class="like-form" method="post">
                                <input type="hidden" name="post_id" value="<?php echo h($post['id']); ?>">
                                <?php if ($post['user_liked']): ?>
                                    <button type="submit" name="action" value="unlike" class="liked"><i class="fas fa-thumbs-up"></i></button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="like"><i class="fas fa-thumbs-up"></i></button>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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