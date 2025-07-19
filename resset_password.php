<?php
// reset_password.php
require_once 'config.php';

// Dans un vrai scénario, un jeton serait passé dans l'URL (ex: reset_password.php?token=xyz)
// et vous le valideriez par rapport à un jeton stocké en BDD avec une date d'expiration.
// Pour cet exemple, nous allons ignorer le jeton pour simplifier, mais c'est crucial.
// $token = $_GET['token'] ?? '';

$password_err = $confirm_password_err = "";
$user_id_to_reset = null; // Cet ID viendrait de la validation du jeton

// SIMULATION : Pour que la page s'affiche et que vous puissiez tester le formulaire,
// nous allons forcer un user_id si vous venez de la page forgot_password.php.
// CELA EST TRÈS INSÉCURE EN PRODUCTION. Ne faites JAMAIS cela en production.
// C'est juste pour que vous puissiez voir le formulaire.
if (isset($_SESSION['user_id_for_reset'])) {
    $user_id_to_reset = $_SESSION['user_id_for_reset'];
    // Une fois utilisé, retirez-le de la session
    unset($_SESSION['user_id_for_reset']);
} else {
    // En production, vous redirigeriez si le jeton n'est pas valide ou manquant.
    // echo "Jeton de réinitialisation invalide ou manquant.";
    // exit();
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Re-vérifier l'ID de l'utilisateur à réinitialiser (qui viendrait du jeton en prod)
    // Ici, nous simulons en le prenant du POST, ce qui est très dangereux sans jeton.
    $user_id_to_reset = $_POST['user_id_to_reset'] ?? null;


    // Valider le nouveau mot de passe
    if (empty(trim($_POST["password"]))) {
        $password_err = "Veuillez entrer un nouveau mot de passe.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Le mot de passe doit contenir au moins 6 caractères.";
    }

    // Valider la confirmation du mot de passe
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Veuillez confirmer le mot de passe.";
    } elseif (trim($_POST["password"]) != trim($_POST["confirm_password"])) {
        $confirm_password_err = "Les mots de passe ne correspondent pas.";
    }

    // Si pas d'erreurs, mettre à jour le mot de passe
    if (empty($password_err) && empty($confirm_password_err) && $user_id_to_reset) {
        $new_password_hash = password_hash(trim($_POST["password"]), PASSWORD_DEFAULT);

        $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("si", $new_password_hash, $user_id_to_reset);
            if ($stmt->execute()) {
                // Mot de passe mis à jour avec succès
                // Ici, vous supprimeriez le jeton de réinitialisation de la BDD
                redirect('login.php?reset=success'); // Rediriger vers la connexion
            } else {
                echo "Une erreur est survenue lors de la mise à jour du mot de passe.";
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialiser le mot de passe - Mon Réseau Social</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,.1); width: 400px; text-align: center; }
        h2 { color: #1c1e21; margin-bottom: 20px; }
        p { color: #606770; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; text-align: left; }
        label { display: block; margin-bottom: 5px; color: #606770; }
        input[type="password"] { width: calc(100% - 22px); padding: 10px; border: 1px solid #dddfe2; border-radius: 6px; font-size: 16px; }
        .btn { width: 100%; padding: 12px; background-color: #1877f2; color: white; border: none; border-radius: 6px; font-size: 18px; cursor: pointer; transition: background-color .2s; }
        .btn:hover { background-color: #166fe5; }
        .error { color: #fa3e3e; font-size: 14px; margin-top: 5px; }
        .success { color: #4CAF50; font-size: 14px; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Réinitialiser votre mot de passe</h2>
        <?php if ($user_id_to_reset): ?>
            <p>Veuillez entrer votre nouveau mot de passe.</p>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="user_id_to_reset" value="<?php echo htmlspecialchars($user_id_to_reset); ?>">

                <div class="form-group">
                    <label for="password">Nouveau mot de passe</label>
                    <input type="password" id="password" name="password">
                    <span class="error"><?php echo $password_err; ?></span>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe</label>
                    <input type="password" id="confirm_password" name="confirm_password">
                    <span class="error"><?php echo $confirm_password_err; ?></span>
                </div>
                <div class="form-group">
                    <input type="submit" class="btn" value="Réinitialiser le mot de passe">
                </div>
            </form>
        <?php else: ?>
            <p class="error">Le lien de réinitialisation est invalide ou a expiré. Veuillez refaire une demande de réinitialisation de mot de passe.</p>
            <a href="forgot_password.php" class="btn">Retourner à la page de mot de passe oublié</a>
        <?php endif; ?>
    </div>
</body>
</html>