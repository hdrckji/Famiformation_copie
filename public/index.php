<?php
require_once 'config.php';
verifierConnexion($db);
require_once 'includes/quizz_status.php';

$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'etudiant';
if ($role === 'agence_interim') {
    header('Location: interim_horaires.php');
    exit();
}
if ($role === 'evaluateur') {
    header('Location: evaluation.php');
    exit();
}

$user_id = $_SESSION['user_id'];
// --- LOGIQUE DE NOTIFICATION NEW ---
$nouvelles_formations = 0;
try {
    $stmt = $db->prepare("SELECT derniere_visite, nom, prenom, photo_profil FROM utilisateurs WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
    $derniere_visite = ($user_data && $user_data['derniere_visite']) ? $user_data['derniere_visite'] : '2000-01-01 00:00:00';
    // Sync session avec la DB pour garantir fraicheur
    if ($user_data) {
        $_SESSION['nom'] = $user_data['nom'];
        $_SESSION['prenom'] = $user_data['prenom'];
        $_SESSION['photo_profil'] = $user_data['photo_profil'];
    }

    $stmtCount = $db->prepare("SELECT COUNT(*) FROM formations_sessions WHERE created_at > ?");
    $stmtCount->execute([$derniere_visite]);
    $nouvelles_formations = $stmtCount->fetchColumn();

    $stmtUpdate = $db->prepare("UPDATE utilisateurs SET derniere_visite = NOW() WHERE id = ?");
    $stmtUpdate->execute([$user_id]);
} catch (Exception $e) {
    $nouvelles_formations = 0;
}

$caisse_valid = false;
$onboarding_unlocked = false;
$onboarding_completed = false;
if ($role === 'etudiant') {
    $caisse_valid = hasCompletedCaisseQuizzes($user_id);
    $onboarding_unlocked = hasUnlockedOnboarding($user_id);
    $onboarding_completed = hasCompletedOnboarding($user_id);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - FamiFormation</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; background: url('background.jpg') no-repeat center center fixed; background-size: cover; margin: 0; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
        
        /* AJUSTEMENT DE LA POSITION DU BOUTON DÉCONNEXION */
        .top-nav { 
            width: 100%; 
            display: flex; 
            justify-content: space-between;
            align-items: center;
            padding: 8px 16px 0 16px; 
            box-sizing: border-box; 
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.9);
            padding: 8px 16px;
            border-radius: 30px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            text-decoration: none;
            color: #333;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .user-info:hover { background: #fff; transform: scale(1.03); box-shadow: 0 6px 15px rgba(0,0,0,0.15); }
        .user-avatar {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #2d5a37;
        }
        .user-avatar-placeholder {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: #e8f5e9;
            border: 3px solid #2d5a37;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .btn-logout { 
            background: rgba(255, 255, 255, 0.9); 
            color: #d93025; 
            text-decoration: none; 
            padding: 12px 25px; 
            border-radius: 30px; 
            font-weight: bold; 
            font-size: 0.9rem; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .btn-logout:hover {
            background: #fff;
            transform: scale(1.05);
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }

        .header { text-align: center; padding: 0px 20px 2px; } 
        .logo-main { max-width: 250px; filter: drop-shadow(0 5px 15px rgba(0,0,0,0.2)); }
        .tiles-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; width: 90%; max-width: 1200px; margin-top: 0; padding-bottom: 0; }
        .tile { background: rgba(255, 255, 255, 0.95); border-radius: 20px; padding: 30px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 10px 25px rgba(0,0,0,0.1); transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; position: relative; }
        .tile:hover { transform: translateY(-10px); box-shadow: 0 15px 35px rgba(0,0,0,0.2); }
        .tile-icon { font-size: 3.5rem; margin-bottom: 15px; }
        .tile-title { font-size: 1.4rem; font-weight: 700; color: #2d5a37; margin-bottom: 10px; }
        .tile-desc { font-size: 0.95rem; color: #666; line-height: 1.4; }
        .tile-title-stack { display: flex; flex-direction: column; align-items: center; gap: 10px; }
        .tile-badges-row { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; }
        .tile-badge { display: inline-flex; align-items: center; justify-content: center; padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; line-height: 1.2; }
        .tile-badge.required { background:#e74c3c; color:#fff; }
        .tile-badge.valid { background:#27ae60; color:#fff; }

        .tile-admin { border: 2px solid #2d5a37; }
        
        .tile-media-beco { height: 3.5rem; margin-bottom: 15px; display: flex; justify-content: center; align-items: center; }
        .logo-beco-tile { max-width: 120px; max-height: 100%; display: block; }

        .badge-new { position: absolute; top: -10px; right: -10px; background: #d93025; color: white; font-size: 0.75rem; font-weight: bold; padding: 5px 12px; border-radius: 20px; animation: pulse 2s infinite; box-shadow: 0 4px 8px rgba(0,0,0,0.2); z-index: 10; }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
    </style>
</head>
<body>

    <div class="top-nav">
        <?php
            $userNom = trim(($_SESSION['prenom'] ?? '') . ' ' . ($_SESSION['nom'] ?? ''));
            $userPhoto = $_SESSION['photo_profil'] ?? null;
        ?>
        <a href="profil.php" class="user-info">
            <?php if ($userPhoto && is_file(__DIR__ . '/' . $userPhoto)): ?>
                <img src="<?= htmlspecialchars($userPhoto) ?>" alt="Photo de profil" class="user-avatar">
            <?php else: ?>
                <span class="user-avatar-placeholder">👤</span>
            <?php endif; ?>
            <span><?= htmlspecialchars($userNom ?: ($_SESSION['username'] ?? '')) ?></span>
        </a>
        <a href="logout.php" class="btn-logout">Déconnexion</a>
    </div>

    <div class="header">
        <img src="logo.png" alt="Famiflora" class="logo-main">
    </div>

    <div class="tiles-container">
        <?php if ($role !== 'etudiant' || $onboarding_unlocked): ?>
        <a href="onboarding.php" class="tile">
            <div class="tile-media"><span class="tile-icon">🚀</span></div>
            <div class="tile-title">Onboarding
            </div>
            <div class="tile-desc">Bienvenue chez Famiflora ! Découvrez notre univers.</div>
        </a>
        <?php endif; ?>

        <!-- planning des formations : désormais visible pour tous les rôles -->
        <a href="formation.php" class="tile">
            <?php if ($nouvelles_formations > 0): ?>
                <span class="badge-new">NOUVEAU</span>
            <?php endif; ?>
            <div class="tile-media"><span class="tile-icon">📅</span></div>
            <div class="tile-title">Planning Formations</div>
            <div class="tile-desc">Inscrivez-vous aux sessions en présentiel.</div>
        </a>

        <?php if ($role === 'admin' || $role === 'teamcoach' || $role === 'mentor' || $role === 'employe_magasin'): ?>
        <a href="magasin.php" class="tile">
            <div class="tile-media"><span class="tile-icon">🛒</span></div>
            <div class="tile-title">Magasin</div>
            <div class="tile-desc">Procédures de vente et caisses.</div>
        </a>
        <?php if ($role !== 'employe_magasin'): ?>
        <a href="management.php" class="tile">
            <div class="tile-media"><span class="tile-icon">🧑‍💼</span></div>
            <div class="tile-title">Management</div>
            <div class="tile-desc">Outils et formations pour managers et mentors.</div>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($role !== 'etudiant'): ?>
        <a href="formation_becosoft.php" class="tile">
            <div class="tile-media-beco"><img src="beco.png" alt="Becosoft" class="logo-beco-tile"></div>
            <div class="tile-title">Becosoft</div>
            <div class="tile-desc">Logiciel de gestion de stock.</div>
        </a>
        <?php endif; ?>

        <!-- tuile caisse réservée aux étudiants -->
        <?php if ($role === 'etudiant'): ?>
        <a href="formation-caisse.php" class="tile">
            <div class="tile-media"><span class="tile-icon">💳</span></div>
            <div class="tile-title tile-title-stack">
                <span>Formation Caisse</span>
                <span class="tile-badges-row">
                    <span class="tile-badge required">Obligatoire</span>
                    <?php if ($caisse_valid): ?>
                        <span class="tile-badge valid">Quiz validés</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="tile-desc">Parcours rapide sur l’utilisation de la caisse.</div>
        </a>

        <?php if ($onboarding_completed): ?>
        <a href="student_disponibilites.php" class="tile">
            <div class="tile-media"><span class="tile-icon">🗓️</span></div>
            <div class="tile-title">Mes disponibilités</div>
            <div class="tile-desc">Indique tes jours de disponibilité sur les 30 prochains jours.</div>
        </a>
        <?php endif; ?>

        <a href="mon_horaire.php" class="tile">
            <div class="tile-media"><span class="tile-icon">🕒</span></div>
            <div class="tile-title">Mes horaires attribués</div>
            <div class="tile-desc">Consulte tes créneaux passés, du jour et futurs en lecture seule.</div>
        </a>
        <?php endif; ?>

        <?php if ($role === 'admin' || $role === 'employe_logistique' || $role === 'teamcoach' || $role === 'mentor'): ?>
        <a href="logistique.php" class="tile">
            <div class="tile-media"><span class="tile-icon">📦</span></div>
            <div class="tile-title">Logistique</div>
            <div class="tile-desc">Gestion des flux et des stocks.</div>
        </a>
        <?php endif; ?>
      
        <?php if ($role !== 'etudiant'): ?>
        <a href="classement.php" class="tile">
            <div class="tile-media"><span class="tile-icon">🏆</span></div>
            <div class="tile-title">Classement</div>
            <div class="tile-desc">Tableau des scores et points.</div>
        </a>
        <a href="securite_travail.php" class="tile">
            <div class="tile-media"><span class="tile-icon">🦺</span></div>
            <div class="tile-title">Sécurité au travail</div>
            <div class="tile-desc">Chaussure de sécurité & secourisme</div>
        </a>
        <?php endif; ?>

        <?php if ($role === 'admin' || $role === 'teamcoach'): ?>
        <?php if ($role === 'admin'): ?>
        <a href="interim_horaires_demandes.php" class="tile tile-admin">
            <div class="tile-media"><span class="tile-icon">📝</span></div>
            <div class="tile-title">Demandes Horaires Intérim</div>
            <div class="tile-desc">Créer, modifier ou supprimer les demandes d'horaires pour les agences intérim.</div>
        </a>
        <a href="interim_horaires.php" class="tile tile-admin">
            <div class="tile-media"><span class="tile-icon">🤝</span></div>
            <div class="tile-title">Matching Intérim</div>
            <div class="tile-desc">Assigner les étudiants aux créneaux intérim, matching manuel ou automatique.</div>
        </a>
        <a href="validation_demandes_horaires.php" class="tile tile-admin">
            <div class="tile-media"><span class="tile-icon">✅</span></div>
            <div class="tile-title">Validation demandes horaires</div>
            <div class="tile-desc">Valider ou refuser les demandes d'horaires avant publication dans le matching.</div>
        </a>
        <a href="admin.php" class="tile tile-admin">
            <div class="tile-media"><span class="tile-icon">👥</span></div>
            <div class="tile-title">RH</div>
            <div class="tile-desc">Gestion des comptes et scores.</div>
        </a>
        <a href="admin_disponibilites_etudiants.php" class="tile tile-admin">
            <div class="tile-media"><span class="tile-icon">🗓️</span></div>
            <div class="tile-title">Dispos Etudiants</div>
            <div class="tile-desc">Vue par semaine et par secteur des disponibilités étudiantes.</div>
        </a>
        <?php endif; ?>
        <a href="admin_questions.php" class="tile tile-admin">
            <div class="tile-media"><span class="tile-icon">⚙️</span></div>
            <div class="tile-title">Gestion Questions</div>
            <div class="tile-desc">Ajouter/Modifier les quiz.</div>
        </a>
        <?php endif; ?>
    </div>

</body>
</html>