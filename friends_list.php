<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
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

// Récupérer la liste des amis (suivi mutuel)
$friends = [];
$sql = "SELECT u.id, u.username, u.profile_picture FROM users u
        JOIN follows f1 ON u.id = f1.followed_id
        JOIN follows f2 ON u.id = f2.follower_id
        WHERE f1.follower_id = ? AND f2.followed_id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $friends[] = $row;
    }
    $stmt->close();
}
// Récupérer la liste des abonnés (ceux qui me suivent)
$followers = [];
$sql = "SELECT u.id, u.username, u.profile_picture FROM users u
        JOIN follows f ON u.id = f.follower_id
        WHERE f.followed_id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $followers[] = $row;
    }
    $stmt->close();
}
// Récupérer la liste des abonnements (ceux que je suis)
$following = [];
$sql = "SELECT u.id, u.username, u.profile_picture FROM users u
        JOIN follows f ON u.id = f.followed_id
        WHERE f.follower_id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $following[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes amis</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" />
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: linear-gradient(135deg, #e0f7fa 0%, #f4f4f4 100%); margin: 0; }
        .header { background-color: #1a3a3a; padding: 5px 20px; display: flex; justify-content: space-between; align-items: center; height: 56px; box-shadow: 0 2px 4px rgba(0,0,0,.1); position: sticky; top: 0; z-index: 1000; }
        .header-left { display: flex; align-items: center; gap: 10px; }
        .header-left .logo { font-size: 28px; font-weight: bold; color: #008080; text-decoration: none; line-height: 1; }
        .header-left .search-bar { position: relative; }
        .header-left .search-bar input { background-color: #34495e; border: none; border-radius: 20px; padding: 8px 15px 8px 40px; font-size: 15px; color: #e3f2fd; width: 200px; outline: none; }
        .header-left .search-bar input::placeholder { color: #bdc3c7; }
        .header-left .search-bar .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #bdc3c7; font-size: 16px; }
        .header-center { display: flex; gap: 20px; }
        .header-center .nav-icon { color: #ecf0f1; font-size: 22px; padding: 8px 15px; border-radius: 8px; transition: background-color .2s, color .2s; cursor: pointer; }
        .header-center .nav-icon:hover { background-color: #34495e; color: #008080; }
        .header-center .nav-icon.active { color: #008080; border-bottom: 3px solid #008080; background-color: transparent; padding-bottom: 5px; }
        .header-center .nav-icon.active:hover { background-color: transparent; }
        .header-right { display: flex; align-items: center; gap: 10px; }
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
        .header-right .icon-button { background-color: #34495e; border-radius: 50%; width: 36px; height: 36px; display: flex; justify-content: center; align-items: center; color: #ecf0f1; font-size: 18px; cursor: pointer; transition: background-color .2s; position: relative; }
        .header-right .icon-button:hover { background-color: #1a3a3a; }
        .notif-badge { position: absolute; top: 2px; right: 2px; background: #e74c3c; color: #fff; border-radius: 50%; padding: 2px 6px; font-size: 12px; font-weight: bold; z-index: 10; }

        .friends-container {
            max-width: 600px;
            margin: 50px auto 0 auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,128,128,0.10), 0 1.5px 4px rgba(0,0,0,0.04);
            padding: 40px 30px 30px 30px;
            min-height: 400px;
        }
        .tabs-bar {
            display: flex;
            justify-content: center;
            gap: 18px;
            margin-bottom: 32px;
        }
        .tab-btn {
            border: none;
            outline: none;
            background: #f4f4f4;
            color: #008080;
            font-weight: 600;
            font-size: 16px;
            border-radius: 30px;
            padding: 10px 32px;
            cursor: pointer;
            box-shadow: 0 1px 4px rgba(0,128,128,0.04);
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
        }
        .tab-btn.active, .tab-btn:focus {
            background: linear-gradient(90deg, #008080 60%, #1a3a3a 100%);
            color: #fff;
            box-shadow: 0 2px 8px rgba(0,128,128,0.10);
        }
        .tab-btn:hover {
            background: #e0f7fa;
            color: #008080;
        }
        h2 { margin-top: 0; color: #008080; font-size: 2em; text-align: center; letter-spacing: 1px; }
        .friend-list { list-style: none; padding: 0; margin: 0; }
        .friend-item {
            display: flex;
            align-items: center;
            padding: 18px 0;
            border-bottom: 1px solid #e0f7fa;
            transition: background 0.2s, box-shadow 0.2s;
            border-radius: 12px;
        }
        .friend-item:last-child { border-bottom: none; }
        .friend-item:hover {
            background: #e0f7fa;
            box-shadow: 0 2px 8px rgba(0,128,128,0.08);
        }
        .friend-avatar {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 18px;
            box-shadow: 0 2px 8px rgba(0,128,128,0.10);
            border: 2px solid #00808022;
        }
        .friend-username {
            font-weight: bold;
            color: #008080;
            text-decoration: none;
            font-size: 1.1em;
            margin-right: 12px;
            transition: color 0.2s;
        }
        .friend-username:hover { color: #1a3a3a; text-decoration: underline; }
        .friend-actions {
            margin-left: auto;
            display: flex;
            gap: 10px;
        }
        .btn-message {
            background: #008080;
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 7px 18px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-message:hover {
            background: #1a3a3a;
        }
        .no-friends { color: #95a5a6; text-align: center; padding: 30px 0; font-size: 1.1em; }
        @media (max-width: 700px) {
            .friends-container { padding: 18px 4vw; }
            .friend-avatar { width: 40px; height: 40px; }
            .tab-btn { padding: 8px 12px; font-size: 14px; }
        }
        .btn-follow {
            background: #008080;
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 7px 18px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-left: 8px;
        }
        .btn-follow:hover {
            background: #1a3a3a;
        }
        .btn-friend {
            background: #bdc3c7;
            color: #212121;
            border: none;
            border-radius: 20px;
            padding: 7px 18px;
            font-size: 14px;
            font-weight: 600;
            margin-left: 8px;
            cursor: default;
        }
        #toast-notif {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #008080;
            color: #fff;
            padding: 16px 28px;
            border-radius: 30px;
            font-size: 16px;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(0,128,128,0.15);
            opacity: 0;
            pointer-events: none;
            z-index: 9999;
            transition: opacity 0.4s, bottom 0.4s;
        }
        #toast-notif.show {
            opacity: 1;
            bottom: 60px;
            pointer-events: auto;
        }
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
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
        }
        .sidebar-overlay.active {
            display: block;
        }
        .sidebar-classic.open {
            transform: translateX(0);
        }
        .close-sidebar {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            font-size: 2em;
            color: #1a3a3a;
            z-index: 1200;
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
</head>
<body>
    <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
    <div class="header">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu" style="display:none;">
            <i class="fas fa-bars"></i>
        </button>
        <div class="header-left" style="justify-content: center; min-width: 300px;">
            <a href="index.php" class="logo" style="color:#fff;">Nexa</a>
        </div>
        <div class="header-center">
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
                <img src="uploads/profiles/<?php echo htmlspecialchars($profile_picture); ?>" alt="Photo de profil" class="profile-thumbnail">
            </a>
            <a href="logout.php" class="icon-button" title="Déconnexion"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
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
    <div class="main-content-wrapper">
        
        <div class="main-feed">
            <div class="friends-container">
                <div class="tabs-bar">
                    <button class="tab-btn active" id="tab-friends">Amis</button>
                    <button class="tab-btn" id="tab-followers">Abonnés</button>
                    <button class="tab-btn" id="tab-following">Abonnements</button>
                </div>
                <div id="list-friends">
                    <h2>Mes amis</h2>
                    <ul class="friend-list">
                        <?php if (empty($friends)): ?>
                            <li class="no-friends">Vous n'avez pas encore d'amis.</li>
                        <?php else: foreach ($friends as $friend): ?>
                            <li class="friend-item">
                                <img class="friend-avatar" src="uploads/profiles/<?php echo h($friend['profile_picture'] ?? 'default.jpg'); ?>" alt="Profil">
                                <a class="friend-username" href="profile.php?username=<?php echo urlencode($friend['username']); ?>"><?php echo h($friend['username']); ?></a>
                                <?php if ($friend['username'] !== $username): ?>
                                <div class="friend-actions">
                                    <a href="messages.php?to=<?php echo urlencode($friend['username']); ?>" class="btn-message"><i class="fas fa-paper-plane"></i> Message</a>
                                </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>
                <div id="list-followers" style="display:none;">
                    <h2>Mes abonnés</h2>
                    <ul class="friend-list">
                        <?php if (empty($followers)): ?>
                            <li class="no-friends">Aucun abonné pour le moment.</li>
                        <?php else: 
                        // Juste avant le foreach des followers, construire un tableau d'ID des following pour savoir qui est déjà suivi
                        $following_ids = array_map(function($u) { return $u['id']; }, $following);
                        ?>
                        <?php foreach ($followers as $f): ?>
                            <li class="friend-item" id="follower-<?php echo h($f['id']); ?>">
                                <img class="friend-avatar" src="uploads/profiles/<?php echo h($f['profile_picture'] ?? 'default.jpg'); ?>" alt="Profil">
                                <a class="friend-username" href="profile.php?username=<?php echo urlencode($f['username']); ?>"><?php echo h($f['username']); ?></a>
                                <?php if ($f['username'] !== $username): ?>
                                <div class="friend-actions">
                                    <a href="messages.php?to=<?php echo urlencode($f['username']); ?>" class="btn-message"><i class="fas fa-paper-plane"></i> Message</a>
                                    <?php if (!in_array($f['id'], $following_ids)): ?>
                                        <button class="btn-follow" data-user-id="<?php echo h($f['id']); ?>">Suivre</button>
                                    <?php else: ?>
                                        <button class="btn-friend" disabled>Amis</button>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>
                <div id="list-following" style="display:none;">
                    <h2>Mes abonnements</h2>
                    <ul class="friend-list">
                        <?php if (empty($following)): ?>
                            <li class="no-friends">Aucun abonnement pour le moment.</li>
                        <?php else: foreach ($following as $f): ?>
                            <li class="friend-item">
                                <img class="friend-avatar" src="uploads/profiles/<?php echo h($f['profile_picture'] ?? 'default.jpg'); ?>" alt="Profil">
                                <a class="friend-username" href="profile.php?username=<?php echo urlencode($f['username']); ?>"><?php echo h($f['username']); ?></a>
                                <?php if ($f['username'] !== $username): ?>
                                <div class="friend-actions">
                                    <a href="messages.php?to=<?php echo urlencode($f['username']); ?>" class="btn-message"><i class="fas fa-paper-plane"></i> Message</a>
                                </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>
            </div>
            <div id="toast-notif"></div>
        </div>
    </div>
    <script>
// Animation et gestion des tabs
const tabs = document.querySelectorAll('.tab-btn');
const lists = [document.getElementById('list-friends'), document.getElementById('list-followers'), document.getElementById('list-following')];
tabs.forEach((tab, idx) => {
    tab.onclick = function() {
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        lists.forEach((l, i) => {
            l.style.display = (i === idx) ? '' : 'none';
            l.style.opacity = (i === idx) ? '1' : '0';
            l.style.transition = 'opacity 0.3s';
        });
    };
});

// Gestion du bouton Suivre dans la liste des abonnés
function showToastNotif(msg) {
    const toast = document.getElementById('toast-notif');
    toast.textContent = msg;
    toast.classList.add('show');
    setTimeout(() => { toast.classList.remove('show'); }, 3000);
}
document.querySelectorAll('.btn-follow').forEach(btn => {
    btn.addEventListener('click', function() {
        const userId = this.dataset.userId;
        const btnFollow = this;
        btnFollow.disabled = true;
        btnFollow.textContent = '...';
        fetch('index.php', {
            method: 'POST',
            body: new URLSearchParams({
                target_user_id: userId,
                follow_action: 'follow'
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && (data.action_performed === 'became_friends' || data.action_performed === 'followed')) {
                btnFollow.classList.remove('btn-follow');
                btnFollow.classList.add('btn-friend');
                btnFollow.textContent = 'Amis';
                btnFollow.disabled = true;
                showToastNotif('Vous êtes maintenant amis avec ' + btnFollow.closest('.friend-item').querySelector('.friend-username').textContent + ' !');
            } else {
                btnFollow.textContent = 'Suivre';
                btnFollow.disabled = false;
                showToastNotif('Erreur: ' + (data.message || 'Impossible de suivre.'));
            }
        })
        .catch(() => {
            btnFollow.textContent = 'Suivre';
            btnFollow.disabled = false;
            showToastNotif('Erreur réseau.');
        });
    });
});

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