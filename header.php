<?php
if (!isset($_SESSION)) session_start();
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$profile_picture = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'uploads/profiles/default.jpg';
?>
<style>
.header-main {
    background: #1a3a3a;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 32px;
    height: 64px;
    box-shadow: 0 2px 4px rgba(0,0,0,.08);
    position: sticky;
    top: 0;
    z-index: 1000;
}
.header-main .logo {
    font-size: 2rem;
    font-weight: bold;
    color: #fff;
    text-decoration: none;
    letter-spacing: 2px;
}
.header-main .nav {
    display: flex;
    gap: 32px;
}
.header-main .nav a {
    color: #fff;
    text-decoration: none;
    font-size: 1.1rem;
    font-weight: 500;
    transition: color 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.header-main .nav a:hover {
    color: #4dcab1;
}
.header-main .profile {
    display: flex;
    align-items: center;
    gap: 16px;
}
.header-main .profile-img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #4dcab1;
    background: #fff;
}
.header-main .logout-btn {
    background: none;
    border: none;
    color: #fff;
    font-size: 1.5rem;
    cursor: pointer;
    margin-left: 8px;
    transition: color 0.2s;
}
.header-main .logout-btn:hover {
    color: #e74c3c;
}
</style>
<div class="header-main">
    <a href="/index.php" class="logo">Nex</a>
    <nav class="nav">
        <a href="/friends_list.php"><i class="fas fa-user-friends"></i> Amis</a>
        <a href="/saved.php"><i class="fas fa-bookmark"></i> Enregistré</a>
        <a href="/notif.php"><i class="fas fa-bell"></i> Notifications</a>
        <a href="/theme.php"><i class="fas fa-palette"></i> Thème</a>
        <a href="/settings.php"><i class="fas fa-cog"></i> Paramètres</a>
        <a href="/help.php"><i class="fas fa-question-circle"></i> Aide</a>
    </nav>
    <div class="profile">
        <a href="/profile.php?username=<?php echo urlencode($username); ?>">
            <img src="/<?php echo htmlspecialchars($profile_picture); ?>" alt="Photo de profil" class="profile-img">
        </a>
        <form action="/logout.php" method="post" style="display:inline;">
            <button type="submit" class="logout-btn" title="Déconnexion"><i class="fas fa-sign-out-alt"></i></button>
        </form>
    </div>
</div> 