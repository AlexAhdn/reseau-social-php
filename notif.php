<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

// Initialiser le compteur de notifications pour éviter les warnings
$notif_count = 0;
$profile_picture = (isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])) ? $_SESSION['profile_picture'] : 'default.jpg';
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$user_id = $_SESSION['user_id'];
// Charger la photo de profil depuis la base de données
$sql = "SELECT profile_picture FROM users WHERE id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($profile_picture);
    $stmt->fetch();
    $stmt->close();
}
if (empty($profile_picture)) $profile_picture = 'default.jpg';
$sql_notif_count = "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0";
if ($stmt_notif_count = $conn->prepare($sql_notif_count)) {
    $stmt_notif_count->bind_param("i", $user_id);
    $stmt_notif_count->execute();
    $stmt_notif_count->bind_result($notif_count);
    $stmt_notif_count->fetch();
    $stmt_notif_count->close();
}

// Récupérer les notifications
$sql = "SELECT n.*, u.username AS sender_username, u.profile_picture AS sender_profile
        FROM notifications n
        LEFT JOIN users u ON n.sender_id = u.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 50";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];
while ($row = $result->fetch_assoc()) $notifications[] = $row;
$stmt->close();

// Marquer comme lues
$conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Notifications</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Ajout du CSS du header -->
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; }
        .notif-container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 30px; }
        h2 { margin-top: 0; color: #008080; }
        ul.notif-list { list-style: none; padding: 0; }
        .notif-item { display: flex; align-items: center; padding: 15px 0; border-bottom: 1px solid #eee; }
        .notif-item:last-child { border-bottom: none; }
        .notif-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 15px; }
        .notif-content { flex: 1; }
        .notif-content b { color: #212121; }
        .notif-time { color: #95a5a6; font-size: 13px; margin-left: 10px; }
        .notif-link { color: #008080; text-decoration: none; }
        .notif-link:hover { text-decoration: underline; }
        .notif-empty { color: #95a5a6; text-align: center; padding: 30px 0; }
        .header {
            background-color: #1a3a3a;
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
            color: #008080;
            text-decoration: none;
            line-height: 1;
        }
        .header-left .search-bar {
            position: relative;
        }
        .header-left .search-bar input {
            background-color: #34495e;
            border: none;
            border-radius: 20px;
            padding: 8px 15px 8px 40px;
            font-size: 15px;
            color: #e3f2fd;
            width: 200px;
            outline: none;
        }
        .header-left .search-bar input::placeholder {
            color: #bdc3c7;
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
            color: #ecf0f1;
            font-size: 22px;
            padding: 8px 15px;
            border-radius: 8px;
            transition: background-color .2s, color .2s;
            cursor: pointer;
        }
        .header-center .nav-icon:hover {
            background-color: #34495e;
            color: #008080;
        }
        .header-center .nav-icon.active {
            color: #008080;
            border-bottom: 3px solid #008080;
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
            border-color: #008080;
        }
        .header-right .icon-button {
            background-color: #34495e;
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
            position: relative;
        }
        .header-right .icon-button:hover {
            background-color: #1a3a3a;
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
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" />
</head>
<body>
    <div class="header">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu" style="display:none;">
            <i class="fas fa-bars"></i>
        </button>
        <div class="header-left" style="justify-content: center; min-width: 300px;">
            <a href="notif.php" class="logo" style="color:#fff;">Nexa</a>
        </div>
        <div class="header-center">
            <?php $current_page = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)); ?>
            <a href="index.php" class="nav-icon<?php if($current_page == 'index.php') echo ' active'; ?>"><i class="fas fa-home"></i></a>
            <a href="friends_list.php" class="nav-icon<?php if($current_page == 'friends_list.php') echo ' active'; ?>"><i class="fas fa-user-friends"></i></a>
            <a href="messages.php" class="nav-icon<?php if($current_page == 'messages.php') echo ' active'; ?>" title="Messagerie"><i class="fab fa-facebook-messenger"></i></a>
            <a href="notif.php" class="nav-icon<?php if($current_page == 'notif.php') echo ' active'; ?>" title="Notifications">
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
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <style>
.sidebar-classic {
    width: 300px;
    float: left;
    padding: 30px 0 0 10px;
    background: #f8f9fa;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    min-height: 90vh;
    display: flex;
    flex-direction: column;
    align-items: center;
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
<div class="sidebar-classic">
    <button class="close-sidebar" id="closeSidebar" style="display:none;position:absolute;top:10px;right:10px;background:none;border:none;font-size:2em;color:#1a3a3a;z-index:1200;">&times;</button>
    <div class="sidebar-profile">
        <img src="uploads/profiles/<?php echo isset($profile_picture) ? h($profile_picture) : 'default.jpg'; ?>" alt="Photo de profil" class="sidebar-profile-img">
        <div class="sidebar-profile-name"><?php echo isset($username) ? htmlspecialchars($username) : 'Utilisateur'; ?></div>
    </div>
    <a href="profile.php?username=<?php echo urlencode($username); ?>"><i class="fas fa-user-circle"></i> Mon profil</a>
    <a href="friends_list.php"><i class="fas fa-user-friends"></i> Amis</a>
    <a href="messages.php"><i class="fab fa-facebook-messenger"></i> Messages</a>
    <a href="notif.php"><i class="fas fa-bell"></i> Notifications</a>
    <a href="settings.php"><i class="fas fa-cog"></i> Paramètres</a>
    <a href="logout.php" style="color:#c0392b;position:absolute;bottom:20px;left:20px;"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
</div>
    <div class="notif-container">
        <h2>Vos notifications</h2>
        <ul class="notif-list">
            <?php if (empty($notifications)): ?>
                <li class="notif-empty">Aucune notification pour le moment.</li>
            <?php else: foreach ($notifications as $notif): ?>
                <li class="notif-item">
                    <img class="notif-avatar" src="uploads/profiles/<?php echo h($notif['sender_profile'] ?? 'default.jpg'); ?>" alt="Profil">
                    <div class="notif-content">
                        <?php if ($notif['type'] == 'like'): ?>
                            <b><a class="notif-link" href="profile.php?username=<?php echo urlencode($notif['sender_username']); ?>"><?php echo h($notif['sender_username']); ?></a></b> a aimé <a class="notif-link" href="<?php echo h(basename($notif['link']) ? $notif['link'] : 'index.php'); ?>">votre publication</a>
                        <?php elseif ($notif['type'] == 'follow'): ?>
                            <b><a class="notif-link" href="profile.php?username=<?php echo urlencode($notif['sender_username']); ?>"><?php echo h($notif['sender_username']); ?></a></b> s'est abonné à votre compte
                        <?php elseif ($notif['type'] == 'comment'): ?>
                            <b><a class="notif-link" href="profile.php?username=<?php echo urlencode($notif['sender_username']); ?>"><?php echo h($notif['sender_username']); ?></a></b> a commenté <a class="notif-link" href="<?php echo h(basename($notif['link']) ? $notif['link'] : 'index.php'); ?>">votre publication</a>
                        <?php else: ?>
                            <?php echo h($notif['content'] ?? 'Nouvelle notification'); ?>
                        <?php endif; ?>
                        <span class="notif-time"><?php echo date("d/m/Y H:i", strtotime($notif['created_at'])); ?></span>
                    </div>
                </li>
            <?php endforeach; endif; ?>
        </ul>
    </div>
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