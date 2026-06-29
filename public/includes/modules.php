<?php
// ============================================================
// Gestion des modules dynamiques (créés par l'admin depuis le site)
// ============================================================

if (!function_exists('ensureModulesTable')) {
    /**
     * Crée la table `modules` si elle n'existe pas encore.
     */
    function ensureModulesTable(PDO $db)
    {
        try {
            $db->exec(
                "CREATE TABLE IF NOT EXISTS modules (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nom VARCHAR(150) NOT NULL,
                    description VARCHAR(500) NULL,
                    is_container TINYINT(1) NOT NULL DEFAULT 0,
                    parent_id INT NULL,
                    pdf_path VARCHAR(255) NULL,
                    video_path VARCHAR(255) NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_modules_parent (parent_id),
                    INDEX idx_modules_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );
        } catch (Exception $e) {
            // table déjà présente ou base indisponible : on ignore
        }
    }

    /**
     * Récupère les modules d'un niveau donné (NULL = niveau racine).
     */
    function getModules(PDO $db, $parentId = null, $onlyActive = true)
    {
        ensureModulesTable($db);
        $sql = "SELECT * FROM modules WHERE ";
        $sql .= $parentId === null ? "parent_id IS NULL" : "parent_id = :pid";
        if ($onlyActive) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY sort_order ASC, nom ASC";
        $stmt = $db->prepare($sql);
        if ($parentId !== null) {
            $stmt->bindValue(':pid', (int) $parentId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un module par son id.
     */
    function getModuleById(PDO $db, $id)
    {
        ensureModulesTable($db);
        $stmt = $db->prepare("SELECT * FROM modules WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Icône par défaut selon le type de module.
     */
    function moduleIcon(array $module)
    {
        return !empty($module['is_container']) ? '📂' : '📄';
    }
}
