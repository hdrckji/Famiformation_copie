<?php
require_once 'config.php';
verifierConnexion($db);
require_once 'includes/modules.php';

// Réservé à l'admin
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit();
}

ensureModulesTable($db);

$redirectTo = 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $isContainer = !empty($_POST['is_container']) ? 1 : 0;
        $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int) $_POST['parent_id'] : null;

        if ($nom === '') {
            $_SESSION['module_flash'] = "❌ Le nom du module est obligatoire.";
        } else {
            $stmt = $db->prepare(
                "INSERT INTO modules (nom, description, is_container, parent_id) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([
                mb_substr($nom, 0, 150),
                mb_substr($description, 0, 500),
                $isContainer,
                $parentId,
            ]);
            $_SESSION['module_flash'] = "✅ Module « " . $nom . " » créé.";
        }

        if ($parentId) {
            $redirectTo = 'module.php?id=' . $parentId;
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $module = getModuleById($db, $id);
            // Supprime aussi les éventuels sous-modules
            $db->prepare("DELETE FROM modules WHERE id = ? OR parent_id = ?")->execute([$id, $id]);
            $_SESSION['module_flash'] = "✅ Module supprimé.";
            if ($module && !empty($module['parent_id'])) {
                $redirectTo = 'module.php?id=' . (int) $module['parent_id'];
            }
        }
    }
}

header('Location: ' . $redirectTo);
exit();
