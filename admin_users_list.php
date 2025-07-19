<?php
require_once 'config.php'; // adapte le chemin si besoin

$result = $conn->query("SELECT id, username, email, date_of_birth, sex, created_at, role, is_admin FROM users");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Liste des utilisateurs</title>
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <style>
        body {
            background: #f6f8fa;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .main-center {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .header {
            background: #1a3a3a;
            color: #fff;
            padding: 48px 32px 36px 32px;
            border-radius: 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 56px;
            text-align: center;
            max-width: 900px;
        }
        .header h2 {
            font-weight: 700;
            font-size: 2rem;
            letter-spacing: 1px;
            margin-bottom: 6px;
            text-shadow: none;
        }
        .header p {
            font-size: 1rem;
            font-weight: 400;
            color: #e0e0e0;
            margin-bottom: 0;
        }
        .table thead th {
            background: #e8f5e9;
            color: #1a3a3a;
            font-weight: 600;
            padding: 16px 18px;
            text-align: center;
        }
        .table tbody td {
            padding: 14px 18px;
        }
        .table tbody td.date-inscr, .table tbody td.date-naiss {
            padding-left: 32px;
            padding-right: 32px;
            white-space: nowrap;
        }
        .table tbody tr:hover {
            background: #e0f2f1;
        }
        .badge-role {
            background: #43a047;
            color: #fff;
            font-size: 0.95em;
        }
        .badge-admin {
            background: #ffc107;
            color: #333;
            font-size: 0.95em;
        }
        .table-container {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 32px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .table-responsive {
            width: 100%;
            display: flex;
            justify-content: center;
        }
        table.table {
            margin: 0 auto;
        }
        .btn-delete {
            background: #e53935;
            color: #fff;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            font-size: 0.97em;
            transition: background 0.2s;
        }
        .btn-delete:hover {
            background: #b71c1c;
            color: #fff;
        }
        @media (max-width: 1000px) {
            .table-container { max-width: 100%; padding: 12px 2px; }
            .header { max-width: 100%; }
        }
        @media (max-width: 768px) {
            .header { padding: 18px 0 12px 0; }
            .table-responsive { font-size: 0.95em; }
        }
    </style>
</head>
<body>
    <div class="main-center">
        <div class="header">
            <h2>Liste des utilisateurs inscrits</h2>
            <p>Visualisez tous les membres de la plateforme</p>
        </div>
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom d'utilisateur</th>
                        <th>Email</th>
                        <th>Date de naissance</th>
                        <th>Sexe</th>
                        <th>Date d'inscription</th>
                        <th>Rôle</th>
                        <th>Admin</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><strong><?= htmlspecialchars($row['username']) ?></strong></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td class="date-naiss"><?= htmlspecialchars($row['date_of_birth']) ?></td>
                        <td><?= htmlspecialchars($row['sex']) ?></td>
                        <td class="date-inscr"><?= htmlspecialchars($row['created_at']) ?></td>
                        <td><span class="badge badge-role"><?= htmlspecialchars($row['role']) ?></span></td>
                        <td>
                            <?php if ($row['is_admin']): ?>
                                <span class="badge badge-admin">Oui</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Non</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" action="delete_user.php" onsubmit="return confirm('Voulez-vous vraiment supprimer cet utilisateur ?');" style="display:inline;">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($row['id']) ?>">
                                <button type="submit" class="btn-delete">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</body>
</html>