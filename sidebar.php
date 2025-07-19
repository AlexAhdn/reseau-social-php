<?php
if (!isset($_SESSION)) session_start();
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$profile_picture = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'uploads/profiles/default.jpg';
$current_page = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
?>
<style>
.left-sidebar {
    position: static !important;
}
.left-sidebar img {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 12px;
    display: inline-block;
    vertical-align: middle;
}
</style>
<div class="left-sidebar">
    <a href="/profile.php?username=<?php echo urlencode($username); ?>" class="sidebar-item<?php if($current_page == 'profile.php') echo ' active'; ?>">
        <img src="/<?php echo htmlspecialchars($profile_picture); ?>" alt="Photo de profil">
        <span><?php echo htmlspecialchars($username); ?></span>
    </a>
    <a href="/friends_list.php" class="sidebar-item<?php if($current_page == 'friends_list.php') echo ' active'; ?>">
        <i class="icon fas fa-user-friends"></i>
        <span>Amis</span>
    </a>
    <a href="/saved.php" class="sidebar-item<?php if($current_page == 'saved.php') echo ' active'; ?>">
        <i class="icon fas fa-bookmark"></i>
        <span>Enregistré</span>
    </a>
    <a href="/notif.php" class="sidebar-item<?php if($current_page == 'notif.php') echo ' active'; ?>">
        <i class="icon fas fa-bell"></i>
        <span>Notifications</span>
    </a>
    <a href="/theme.php" class="sidebar-item<?php if($current_page == 'theme.php') echo ' active'; ?>">
        <i class="icon fas fa-palette"></i>
        <span>Thème</span>
    </a>
    <a href="/settings.php" class="sidebar-item<?php if($current_page == 'settings.php') echo ' active'; ?>">
        <i class="icon fas fa-cog"></i>
        <span>Paramètres</span>
    </a>
    <a href="/help.php" class="sidebar-item<?php if($current_page == 'help.php') echo ' active'; ?>">
        <i class="icon fas fa-question-circle"></i>
        <span>Aide</span>
    </a>
    <a href="#" class="sidebar-item">
        <i class="icon fas fa-chevron-circle-down"></i>
        <span>Voir plus</span>
    </a>
</div> 