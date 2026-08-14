<?php
declare(strict_types=1);

namespace SPFPU\Controllers;

use PDO;
use SPFPU\Core\{Audit, Auth, Config, CsvImport, Database, Http, Validation, View, VolumePolicy};

final class AppController
{
    private PDO $db;
    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function loginForm(): void
    {
        if (Auth::user()) {
            Http::redirect("/");
        }
        View::render("login", ["title" => "Log Masuk"]);
    }

    public function login(): void
    {
        $identity = mb_strtolower(trim((string) ($_POST["identity"] ?? "")));
        $password = (string) ($_POST["password"] ?? "");
        $ip = substr($_SERVER["REMOTE_ADDR"] ?? "unknown", 0, 45);
        $hash = hash("sha256", $identity);
        $check = $this->db->prepare(
            "SELECT COUNT(*) FROM login_attempts WHERE identity_hash=? AND ip_address=? AND succeeded=0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        $check->execute([$hash, $ip]);
        if ((int) $check->fetchColumn() >= 5) {
            Audit::log(
                "auth.throttled",
                "session",
                null,
                null,
                ["identity" => $identity],
                null
            );
            $this->invalid(
                "Terlalu banyak cubaan. Cuba semula dalam 15 minit."
            );
        }
        $stmt = $this->db->prepare(
            "SELECT * FROM users WHERE (username_norm=? OR email_norm=?) AND archived_at IS NULL LIMIT 1"
        );
        $stmt->execute([$identity, $identity]);
        $user = $stmt->fetch();
        $ok =
            $user &&
            $user["status"] === "Active" &&
            password_verify($password, $user["password_hash"]);
        $this->db
            ->prepare(
                "INSERT INTO login_attempts(identity_hash,ip_address,succeeded) VALUES (?,?,?)"
            )
            ->execute([$hash, $ip, $ok ? 1 : 0]);
        if (!$ok) {
            Audit::log(
                "auth.failed",
                "session",
                null,
                null,
                ["identity" => $identity],
                $user["id"] ?? null
            );
            $this->invalid("Username/e-mel atau kata laluan tidak sah.");
        }
        if (password_needs_rehash($user["password_hash"], PASSWORD_ARGON2ID)) {
            $this->db
                ->prepare("UPDATE users SET password_hash=? WHERE id=?")
                ->execute([
                    password_hash($password, PASSWORD_ARGON2ID),
                    $user["id"],
                ]);
        }
        Auth::login($user);
        Audit::log("auth.login", "session", (int) $user["id"]);
        Http::redirect("/");
    }

    private function invalid(string $message): never
    {
        $_SESSION["old"] = $_POST;
        Http::flash("error", $message);
        Http::redirect("/login");
    }

    public function logout(): void
    {
        $user = Auth::requireLogin();
        Audit::log("auth.logout", "session", (int) $user["id"]);
        Auth::logout();
        session_start();
        Http::flash("success", "Anda telah log keluar.");
        Http::redirect("/login");
    }

    public function dashboard(): void
    {
        Auth::requireLogin();
        $categories = $this->db
            ->query(
                "SELECT c.*, COUNT(DISTINCT f.id) folder_count, COUNT(DISTINCT e.id) entry_count
          FROM categories c LEFT JOIN folders f ON f.category_id=c.id AND f.archived_at IS NULL
          LEFT JOIN volumes v ON v.folder_id=f.id AND v.archived_at IS NULL LEFT JOIN entries e ON e.volume_id=v.id AND e.archived_at IS NULL
          WHERE c.archived_at IS NULL GROUP BY c.id ORDER BY c.name"
            )
            ->fetchAll();
        View::render("dashboard", [
            "title" => "Kategori",
            "categories" => $categories,
        ]);
    }

    public function createCategory(): void
    {
        $user = Auth::requireAdmin();
        $name = trim((string) ($_POST["name"] ?? ""));
        if ($name === "" || mb_strlen($name) > 150) {
            $this->back(
                "Nama kategori diperlukan dan tidak boleh melebihi 150 aksara."
            );
        }
        try {
            $this->db
                ->prepare(
                    "INSERT INTO categories(name,name_norm,description,created_by) VALUES (?,?,?,?)"
                )
                ->execute([
                    $name,
                    mb_strtolower($name),
                    $this->nullable("description"),
                    $user["id"],
                ]);
            $id = (int) $this->db->lastInsertId();
            Audit::log("category.created", "category", $id, null, [
                "name" => $name,
            ]);
            Http::flash("success", "Kategori berjaya ditambah.");
        } catch (\PDOException $e) {
            $this->back("Nama kategori telah digunakan.");
        }
        Http::redirect("/");
    }

    public function category(string $id): void
    {
        Auth::requireLogin();
        $stmt = $this->db->prepare(
            "SELECT * FROM categories WHERE id=? AND archived_at IS NULL"
        );
        $stmt->execute([(int) $id]);
        $category = $stmt->fetch();
        if (!$category) {
            Http::abort(404, "Kategori tidak ditemui.");
        }
        $q = trim((string) ($_GET["q"] ?? ""));
        $sql = "SELECT f.*, COUNT(DISTINCT v.id) volume_count, COUNT(DISTINCT e.id) entry_count
          FROM folders f LEFT JOIN volumes v ON v.folder_id=f.id AND v.archived_at IS NULL LEFT JOIN entries e ON e.volume_id=v.id AND e.archived_at IS NULL
          WHERE f.category_id=? AND f.archived_at IS NULL";
        $params = [(int) $id];
        if ($q !== "") {
            $sql .= " AND (f.reference_code LIKE ? OR f.display_name LIKE ?)";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }
        $sql .= " GROUP BY f.id ORDER BY f.reference_code";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        View::render("category", [
            "title" => $category["name"],
            "category" => $category,
            "folders" => $stmt->fetchAll(),
            "q" => $q,
        ]);
    }

    public function editCategory(string $id): void
    {
        Auth::requireAdmin();
        $name = trim((string) ($_POST["name"] ?? ""));
        $description = $this->nullable("description");
        if (
            $name === "" ||
            mb_strlen($name) > 150 ||
            ($description !== null && mb_strlen($description) > 500)
        ) {
            $this->back(
                "Nama kategori diperlukan dan keterangan tidak boleh melebihi 500 aksara."
            );
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM categories WHERE id=? AND archived_at IS NULL"
        );
        $stmt->execute([(int) $id]);
        $category = $stmt->fetch();
        if (!$category) {
            Http::abort(404, "Kategori tidak ditemui.");
        }

        try {
            $this->db
                ->prepare(
                    "UPDATE categories SET name=?,name_norm=?,description=? WHERE id=?"
                )
                ->execute([
                    $name,
                    mb_strtolower($name),
                    $description,
                    (int) $id,
                ]);
        } catch (\PDOException $e) {
            $this->back("Nama kategori telah digunakan.");
        }

        Audit::log("category.updated", "category", (int) $id, [
            "name" => $category["name"],
            "description" => $category["description"],
        ], [
            "name" => $name,
            "description" => $description,
        ]);
        Http::flash("success", "Kategori berjaya dikemas kini.");
        Http::redirect("/kategori/" . (int) $id);
    }

    public function createFolder(string $categoryId): void
    {
        $user = Auth::requireAdmin();
        $ref = trim((string) ($_POST["reference_code"] ?? ""));
        $name = trim((string) ($_POST["display_name"] ?? ""));
        if (
            $ref === "" ||
            $name === "" ||
            mb_strlen($ref) > 100 ||
            mb_strlen($name) > 150
        ) {
            $this->back("Kod rujukan dan nama fail diperlukan.");
        }
        try {
            Database::transaction(function (PDO $db) use (
                $categoryId,
                $user,
                $ref,
                $name
            ) {
                $db->prepare(
                    "INSERT INTO folders(category_id,reference_code,reference_code_norm,display_name,description,is_confidential,created_by) VALUES (?,?,?,?,?,?,?)"
                )->execute([
                    (int) $categoryId,
                    $ref,
                    mb_strtolower($ref),
                    $name,
                    $this->nullable("description"),
                    isset($_POST["is_confidential"]) ? 1 : 0,
                    $user["id"],
                ]);
                $folderId = (int) $db->lastInsertId();
                $db->prepare(
                    'INSERT INTO volumes(folder_id,sequence_no,status,created_by) VALUES (?,1,\'Open\',?)'
                )->execute([$folderId, $user["id"]]);
                Audit::log("folder.created", "folder", $folderId, null, [
                    "reference_code" => $ref,
                    "display_name" => $name,
                    "is_confidential" => isset($_POST["is_confidential"]),
                ]);
            });
        } catch (\PDOException $e) {
            $this->back("Kod rujukan telah digunakan atau kategori tidak sah.");
        }
        Http::flash("success", "Fail dan Jilid 1 berjaya diwujudkan.");
        Http::redirect("/kategori/" . (int) $categoryId);
    }

    public function editFolder(string $id): void
    {
        Auth::requireAdmin();
        $ref = trim((string) ($_POST["reference_code"] ?? ""));
        $name = trim((string) ($_POST["display_name"] ?? ""));
        $description = $this->nullable("description");
        if (
            $ref === "" ||
            $name === "" ||
            mb_strlen($ref) > 100 ||
            mb_strlen($name) > 150 ||
            ($description !== null && mb_strlen($description) > 500)
        ) {
            $this->back(
                "Kod rujukan dan nama fail diperlukan; keterangan tidak boleh melebihi 500 aksara."
            );
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM folders WHERE id=? AND archived_at IS NULL"
        );
        $stmt->execute([(int) $id]);
        $folder = $stmt->fetch();
        if (!$folder) {
            Http::abort(404, "Fail tidak ditemui.");
        }

        try {
            $this->db
                ->prepare(
                    "UPDATE folders SET reference_code=?,reference_code_norm=?,display_name=?,description=? WHERE id=?"
                )
                ->execute([
                    $ref,
                    mb_strtolower($ref),
                    $name,
                    $description,
                    (int) $id,
                ]);
        } catch (\PDOException $e) {
            $this->back("Kod rujukan telah digunakan.");
        }

        Audit::log("folder.updated", "folder", (int) $id, [
            "reference_code" => $folder["reference_code"],
            "display_name" => $folder["display_name"],
            "description" => $folder["description"],
        ], [
            "reference_code" => $ref,
            "display_name" => $name,
            "description" => $description,
        ]);
        Http::flash("success", "Fail berjaya dikemas kini.");
        Http::redirect("/fail/" . (int) $id);
    }

    public function folder(string $id): void
    {
        $user = Auth::requireLogin();
        $stmt = $this->db->prepare(
            "SELECT f.*,c.name category_name FROM folders f JOIN categories c ON c.id=f.category_id WHERE f.id=? AND f.archived_at IS NULL AND c.archived_at IS NULL"
        );
        $stmt->execute([(int) $id]);
        $folder = $stmt->fetch();
        if (!$folder) {
            Http::abort(404, "Fail tidak ditemui.");
        }
        if (!Auth::canAccessFolder((int) $id)) {
            Http::abort(
                403,
                "Fail sulit ini memerlukan kebenaran akses daripada Admin."
            );
        }
        $volumes = $this->db->prepare(
            "SELECT v.*,COUNT(e.id) entry_count,(SELECT COUNT(*) FROM entries e_all WHERE e_all.volume_id=v.id) total_entry_count,COALESCE((SELECT MAX(e_last.entry_no) FROM entries e_last WHERE e_last.volume_id=v.id),0) last_entry_no FROM volumes v LEFT JOIN entries e ON e.volume_id=v.id AND e.archived_at IS NULL WHERE v.folder_id=? AND v.archived_at IS NULL GROUP BY v.id ORDER BY v.sequence_no DESC"
        );
        $volumes->execute([(int) $id]);
        $volumes = $volumes->fetchAll();
        $volumeId = (int) ($_GET["jilid"] ?? ($volumes[0]["id"] ?? 0));
        $selected = null;
        foreach ($volumes as $v) {
            if ((int) $v["id"] === $volumeId) {
                $selected = $v;
            }
        }
        if (!$selected) {
            Http::abort(404, "Jilid tidak ditemui.");
        }
        $q = trim((string) ($_GET["q"] ?? ""));
        $page = max(1, (int) ($_GET["page"] ?? 1));
        $offset = ($page - 1) * 100;
        $entryWhere = ["e.volume_id=?", "e.archived_at IS NULL"];
        $entryParams = [$volumeId];
        if ($q !== "") {
            $entryWhere[] =
                "(e.correspondent LIKE ? OR e.matter LIKE ? OR e.remarks LIKE ?)";
            $searchTerm = "%{$q}%";
            array_push($entryParams, $searchTerm, $searchTerm, $searchTerm);
        }
        $entries = $this->db->prepare(
            "SELECT e.*,u.fullname author_name FROM entries e JOIN users u ON u.id=e.created_by WHERE " .
                implode(" AND ", $entryWhere) .
                " ORDER BY e.entry_no LIMIT 100 OFFSET " .
                $offset
        );
        $entries->execute($entryParams);
        $staff = [];
        $grants = [];
        if ($user["role"] === "Admin" && $folder["is_confidential"]) {
            $staff = $this->db
                ->query(
                    "SELECT id,fullname,email FROM users WHERE role='Staff' AND status='Active' AND archived_at IS NULL ORDER BY fullname"
                )
                ->fetchAll();
            $g = $this->db->prepare(
                "SELECT user_id FROM folder_access WHERE folder_id=?"
            );
            $g->execute([(int) $id]);
            $grants = array_map("intval", $g->fetchAll(PDO::FETCH_COLUMN));
        }
        View::render("folder", [
            "title" => $folder["reference_code"],
            "folder" => $folder,
            "volumes" => $volumes,
            "volume" => $selected,
            "entries" => $entries->fetchAll(),
            "page" => $page,
            "q" => $q,
            "user" => $user,
            "staff" => $staff,
            "grants" => $grants,
        ]);
    }

    public function createEntry(string $volumeId): void
    {
        $user = Auth::requireLogin();
        $data = $this->entryData();
        $volume = $this->volume((int) $volumeId);
        if (!Auth::canAccessFolder((int) $volume["folder_id"])) {
            Http::abort(403, "Akses tidak dibenarkan.");
        }
        if (!VolumePolicy::canCreateEntry($user["role"], $volume["status"])) {
            $this->back(
                "Hanya Admin boleh memasukkan entri baharu ke jilid yang telah ditutup."
            );
        }
        if (
            $data["movement_date"] < $data["letter_date"] &&
            !isset($_POST["confirm_chronology"])
        ) {
            $this->back(
                "Tarikh dimasukkan/dihantar lebih awal daripada tarikh surat. Tandakan pengesahan untuk meneruskan."
            );
        }
        $id = Database::transaction(function (PDO $db) use (
            $volumeId,
            $user,
            $data
        ) {
            $lock = $db->prepare(
                "SELECT id,status FROM volumes WHERE id=? AND archived_at IS NULL FOR UPDATE"
            );
            $lock->execute([(int) $volumeId]);
            $lockedVolume = $lock->fetch();
            if (
                !$lockedVolume ||
                !VolumePolicy::canCreateEntry(
                    $user["role"],
                    $lockedVolume["status"]
                )
            ) {
                throw new \RuntimeException(
                    "Jilid telah ditutup dan hanya Admin boleh menambah entri."
                );
            }
            $n = $db->prepare(
                "SELECT COALESCE(MAX(entry_no),0)+1 FROM entries WHERE volume_id=?"
            );
            $n->execute([(int) $volumeId]);
            $no = (int) $n->fetchColumn();
            $db->prepare(
                "INSERT INTO entries(volume_id,entry_no,type,letter_date,correspondent,movement_date,matter,remarks,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                (int) $volumeId,
                $no,
                $data["type"],
                $data["letter_date"],
                $data["correspondent"],
                $data["movement_date"],
                $data["matter"],
                $data["remarks"],
                $user["id"],
                $user["id"],
            ]);
            $id = (int) $db->lastInsertId();
            Audit::log(
                "entry.created",
                "entry",
                $id,
                null,
                $data + ["entry_no" => $no]
            );
            return $id;
        });
        Http::flash("success", "Entri berjaya direkodkan.");
        Http::redirect(
            "/fail/" . $volume["folder_id"] . "?jilid=" . (int) $volumeId
        );
    }

    public function editEntry(string $id): void
    {
        $user = Auth::requireLogin();
        $entry = $this->entry((int) $id);
        if (!Auth::canAccessFolder((int) $entry["folder_id"])) {
            Http::abort(403, "Akses tidak dibenarkan.");
        }
        if (
            $entry["status"] !== "Open" &&
            ($user["role"] !== "Admin" ||
                ($_POST["correction_mode"] ?? "") !== "1")
        ) {
            Http::abort(
                403,
                "Aktifkan mod pembetulan Admin untuk mengubah entri dalam jilid sejarah."
            );
        }
        $data = $this->entryData();
        if (
            $data["movement_date"] < $data["letter_date"] &&
            !isset($_POST["confirm_chronology"])
        ) {
            $this->back("Sahkan kronologi tarikh untuk meneruskan.");
        }
        $this->db
            ->prepare(
                "UPDATE entries SET type=?,letter_date=?,correspondent=?,movement_date=?,matter=?,remarks=?,updated_by=? WHERE id=? AND archived_at IS NULL"
            )
            ->execute([
                $data["type"],
                $data["letter_date"],
                $data["correspondent"],
                $data["movement_date"],
                $data["matter"],
                $data["remarks"],
                $user["id"],
                (int) $id,
            ]);
        Audit::log("entry.updated", "entry", (int) $id, $entry, $data);
        Http::flash("success", "Entri dikemas kini.");
        Http::redirect(
            "/fail/" . $entry["folder_id"] . "?jilid=" . $entry["volume_id"]
        );
    }

    public function archiveEntry(string $id): void
    {
        $user = Auth::requireLogin();
        $entry = $this->entry((int) $id);
        if (!Auth::canAccessFolder((int) $entry["folder_id"])) {
            Http::abort(403, "Akses tidak dibenarkan.");
        }
        if ($entry["status"] !== "Open") {
            Http::abort(403, "Entri jilid sejarah tidak boleh diarkibkan.");
        }
        $this->db
            ->prepare(
                "UPDATE entries SET archived_at=NOW(),archived_by=?,archive_batch=UUID() WHERE id=?"
            )
            ->execute([$user["id"], (int) $id]);
        Audit::log("entry.archived", "entry", (int) $id, $entry, null);
        Http::flash(
            "success",
            "Entri telah diarkibkan. Nombornya tidak akan digunakan semula."
        );
        Http::redirect(
            "/fail/" . $entry["folder_id"] . "?jilid=" . $entry["volume_id"]
        );
    }

    public function nextVolume(string $folderId): void
    {
        $user = Auth::requireAdmin();
        $new = Database::transaction(function (PDO $db) use ($folderId, $user) {
            $q = $db->prepare(
                "SELECT id,sequence_no FROM volumes WHERE folder_id=? AND archived_at IS NULL ORDER BY sequence_no DESC LIMIT 1 FOR UPDATE"
            );
            $q->execute([(int) $folderId]);
            $v = $q->fetch();
            if (!$v) {
                throw new \RuntimeException("Fail tidak sah.");
            }
            $db->prepare(
                'UPDATE volumes SET status=\'Closed\',closed_at=NOW() WHERE id=?'
            )->execute([$v["id"]]);
            $n = (int) $v["sequence_no"] + 1;
            $db->prepare(
                'INSERT INTO volumes(folder_id,sequence_no,coverage_start,description,status,created_by) VALUES (?,?,?, ?,\'Open\',?)'
            )->execute([
                (int) $folderId,
                $n,
                $this->nullable("coverage_start"),
                $this->nullable("description"),
                $user["id"],
            ]);
            $id = (int) $db->lastInsertId();
            Audit::log("volume.advanced", "folder", (int) $folderId, $v, [
                "volume_id" => $id,
                "sequence_no" => $n,
            ]);
            return $id;
        });
        Http::flash("success", "Jilid semasa ditutup dan jilid baharu dibuka.");
        Http::redirect("/fail/" . (int) $folderId . "?jilid=" . $new);
    }

    public function archiveBranch(string $type, string $id): void
    {
        $user = Auth::requireAdmin();
        if (!in_array($type, ["kategori", "fail"], true)) {
            Http::abort(404, "Jenis rekod tidak sah.");
        }
        $batch = $this->uuid();
        Database::transaction(function (PDO $db) use (
            $type,
            $id,
            $user,
            $batch
        ) {
            if ($type === "kategori") {
                $db->prepare(
                    "UPDATE entries e JOIN volumes v ON v.id=e.volume_id JOIN folders f ON f.id=v.folder_id SET e.archived_at=NOW(),e.archived_by=?,e.archive_batch=? WHERE f.category_id=? AND e.archived_at IS NULL"
                )->execute([$user["id"], $batch, (int) $id]);
                $db->prepare(
                    "UPDATE volumes v JOIN folders f ON f.id=v.folder_id SET v.archived_at=NOW(),v.archived_by=?,v.archive_batch=? WHERE f.category_id=? AND v.archived_at IS NULL"
                )->execute([$user["id"], $batch, (int) $id]);
                $db->prepare(
                    "UPDATE folders SET archived_at=NOW(),archived_by=?,archive_batch=? WHERE category_id=? AND archived_at IS NULL"
                )->execute([$user["id"], $batch, (int) $id]);
                $db->prepare(
                    "UPDATE categories SET archived_at=NOW(),archived_by=?,archive_batch=? WHERE id=?"
                )->execute([$user["id"], $batch, (int) $id]);
            } else {
                $db->prepare(
                    "UPDATE entries e JOIN volumes v ON v.id=e.volume_id SET e.archived_at=NOW(),e.archived_by=?,e.archive_batch=? WHERE v.folder_id=? AND e.archived_at IS NULL"
                )->execute([$user["id"], $batch, (int) $id]);
                $db->prepare(
                    "UPDATE volumes SET archived_at=NOW(),archived_by=?,archive_batch=? WHERE folder_id=? AND archived_at IS NULL"
                )->execute([$user["id"], $batch, (int) $id]);
                $db->prepare(
                    "UPDATE folders SET archived_at=NOW(),archived_by=?,archive_batch=? WHERE id=?"
                )->execute([$user["id"], $batch, (int) $id]);
            }
            Audit::log($type . ".archived", $type, (int) $id, null, [
                "archive_batch" => $batch,
            ]);
        });
        Http::flash("success", "Cabang rekod telah diarkibkan secara atomik.");
        Http::redirect("/");
    }

    public function search(): void
    {
        $this->searchRun(false);
    }
    public function export(): void
    {
        $this->searchRun(true);
    }
    private function searchRun(bool $export): void
    {
        $user = Auth::requireLogin();
        $where = [
            "e.archived_at IS NULL",
            "v.archived_at IS NULL",
            "f.archived_at IS NULL",
            "c.archived_at IS NULL",
        ];
        $params = [];
        if ($user["role"] !== "Admin") {
            $where[] = "(f.is_confidential=0 OR fa.user_id IS NOT NULL)";
        }
        $map = [
            "type" => "e.type",
            "category" => "c.id",
            "folder" => "f.id",
            "volume" => "v.id",
        ];
        foreach ($map as $k => $col) {
            if (($_GET[$k] ?? "") !== "") {
                $where[] = "$col=?";
                $params[] = $_GET[$k];
            }
        }
        foreach (["date_from" => ">=", "date_to" => "<="] as $k => $op) {
            if (!empty($_GET[$k])) {
                $where[] = "e.letter_date $op ?";
                $params[] = $_GET[$k];
            }
        }
        $q = trim((string) ($_GET["q"] ?? ""));
        if ($q !== "") {
            $where[] =
                "(e.correspondent LIKE ? OR e.matter LIKE ? OR e.remarks LIKE ? OR f.reference_code LIKE ? OR f.display_name LIKE ?)";
            for ($i = 0; $i < 5; $i++) {
                $params[] = "%{$q}%";
            }
        }
        $from =
            " FROM entries e JOIN volumes v ON v.id=e.volume_id JOIN folders f ON f.id=v.folder_id JOIN categories c ON c.id=f.category_id LEFT JOIN folder_access fa ON fa.folder_id=f.id AND fa.user_id=" .
            (int) $user["id"] .
            " WHERE " .
            implode(" AND ", $where);
        $select =
            "SELECT e.*,v.sequence_no,f.id folder_id,f.reference_code,f.display_name,c.name category_name" .
            $from .
            " ORDER BY e.letter_date DESC,e.id DESC";
        if ($export) {
            $stmt = $this->db->prepare($select);
            $stmt->execute($params);
            $this->csv($stmt);
        }
        $page = max(1, (int) ($_GET["page"] ?? 1));
        $stmt = $this->db->prepare(
            $select . " LIMIT 50 OFFSET " . ($page - 1) * 50
        );
        $stmt->execute($params);
        $categories = $this->db
            ->query(
                "SELECT id,name FROM categories WHERE archived_at IS NULL ORDER BY name"
            )
            ->fetchAll();
        View::render("search", [
            "title" => "Carian Global",
            "results" => $stmt->fetchAll(),
            "categories" => $categories,
            "page" => $page,
        ]);
    }

    private function csv(\PDOStatement $stmt): never
    {
        header("Content-Type: text/csv; charset=UTF-8");
        header(
            'Content-Disposition: attachment; filename="spfpu-' .
                date("Ymd-His") .
                '.csv"'
        );
        header("Cache-Control: no-store");
        $out = fopen("php://output", "wb");
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            "Kategori",
            "Fail",
            "Nama Fail",
            "Jilid",
            "Bil.",
            "Jenis",
            "Surat Bertarikh",
            "Daripada/Kepada",
            "Dimasukkan/Dihantar",
            "Perkara",
            "Catatan",
        ]);
        while ($r = $stmt->fetch()) {
            fputcsv($out, [
                $r["category_name"],
                $r["reference_code"],
                $r["display_name"],
                "Jilid " . $r["sequence_no"],
                $r["entry_no"],
                $r["type"] === "Incoming"
                    ? "Masuk"
                    : ($r["type"] === "Outgoing"
                        ? "Keluar"
                        : ""),
                $r["letter_date"] ? View::date($r["letter_date"]) : "",
                $r["correspondent"],
                $r["movement_date"] ? View::date($r["movement_date"]) : "",
                $r["matter"],
                $r["remarks"],
            ]);
        }
        fclose($out);
        exit();
    }

    public function importPreview(string $volumeId): void
    {
        $user = Auth::requireAdmin();
        $volume = $this->volume((int) $volumeId);
        $count = $this->db->prepare(
            "SELECT COUNT(*) FROM entries WHERE volume_id=?"
        );
        $count->execute([(int) $volumeId]);
        if (
            !VolumePolicy::canImport(
                $user["role"],
                (int) $count->fetchColumn()
            )
        ) {
            $this->back(
                "Import hanya dibenarkan ke jilid yang belum pernah mempunyai sebarang entri."
            );
        }
        $file = $_FILES["csv"] ?? null;
        $max = (int) Config::get("IMPORT_MAX_BYTES", "5242880");
        if (
            !$file ||
            $file["error"] !== UPLOAD_ERR_OK ||
            $file["size"] > $max
        ) {
            $this->back("Fail CSV tidak sah atau melebihi 5 MB.");
        }
        $handle = fopen($file["tmp_name"], "rb");
        if (!$handle) {
            $this->back("Fail CSV tidak dapat dibaca.");
        }
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            $this->back("CSV tidak mempunyai baris pengepala.");
        }
        $headers = array_map(
            fn($h) => trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF"),
            $headers
        );
        $aliases = [
            "no" => ["No.", "Bil.", "BIL."],
            "type" => [
                "Type",
                "Jenis",
                "JENIS",
                "Masuk/Keluar",
                "MASUK/KELUAR",
            ],
            "letter_date" => ["DOL", "Surat Bertarikh", "SURAT BERTARIKH"],
            "correspondent" => [
                "From/To",
                "Daripada/Kepada",
                "DARIPADA/KEPADA",
            ],
            "movement_date" => [
                "Received/Sent",
                "Dimasukkan/Dihantar",
                "DIMASUKKAN/DIHANTAR",
            ],
            "matter" => ["Matter", "Perkara", "PERKARA"],
            "remarks" => ["Remarks", "Catatan", "CATATAN"],
        ];
        $map = [];
        $errors = [];
        foreach ($aliases as $key => $names) {
            foreach ($names as $name) {
                $idx = array_search($name, $headers, true);
                if ($idx !== false) {
                    $map[$key] = $idx;
                    break;
                }
            }
            if (!isset($map[$key]) && $key !== "remarks") {
                $errors[] =
                    "Pengepala diperlukan tiada: " . implode(" atau ", $names);
            }
        }
        $rows = [];
        $seen = [];
        $line = 1;
        $warnings = [];
        while (($raw = fgetcsv($handle)) !== false) {
            $line++;
            if ($line > 10001) {
                $errors[] = "Import dihadkan kepada 10,000 baris.";
                break;
            }
            if (
                count(
                    array_filter($raw, fn($v) => trim((string) $v) !== "")
                ) === 0
            ) {
                continue;
            }
            $get = fn($k) => trim((string) ($raw[$map[$k] ?? -1] ?? ""));
            $no = filter_var($get("no"), FILTER_VALIDATE_INT, [
                "options" => ["min_range" => 1],
            ]);
            [$type, $typeValid] = CsvImport::type($get("type"));
            [$letter, $letterValid] = CsvImport::date($get("letter_date"));
            [$movement, $movementValid] = CsvImport::date(
                $get("movement_date")
            );
            $matterValue = $get("matter");
            $correspondentValue = $get("correspondent");
            $remarksValue = $get("remarks");
            $matter = $matterValue === "" ? null : $matterValue;
            $correspondent =
                $correspondentValue === "" ? null : $correspondentValue;
            $remarks = $remarksValue === "" ? null : $remarksValue;
            $rowErrors = [];
            if ($no === false) {
                $rowErrors[] = "Bil. mesti nombor positif";
            } elseif (isset($seen[$no])) {
                $rowErrors[] = "Bil. berulang";
            } else {
                $seen[$no] = true;
            }
            if (!$typeValid) {
                $rowErrors[] = "Jenis tidak sah";
            }
            if (!$letterValid) {
                $rowErrors[] = "Surat Bertarikh tidak sah";
            }
            if (!$movementValid) {
                $rowErrors[] = "Dimasukkan/Dihantar tidak sah";
            }
            if ($correspondent !== null && mb_strlen($correspondent) > 150) {
                $rowErrors[] = "Daripada/Kepada melebihi 150";
            }
            if ($matter !== null && mb_strlen($matter) > 500) {
                $rowErrors[] = "Perkara melebihi 500";
            }
            if ($remarks !== null && mb_strlen($remarks) > 500) {
                $rowErrors[] = "Catatan melebihi 500";
            }
            if ($letter && $movement && $movement < $letter) {
                $warnings[] = "Baris {$line}: kronologi tarikh terbalik";
            }
            if ($rowErrors) {
                $errors[] = "Baris {$line}: " . implode(", ", $rowErrors);
            }
            $rows[] = [
                "entry_no" => $no ?: 0,
                "type" => $type,
                "letter_date" => $letter,
                "correspondent" => $correspondent,
                "movement_date" => $movement,
                "matter" => $matter,
                "remarks" => $remarks ?: null,
            ];
        }
        fclose($handle);
        if (!$rows) {
            $errors[] = "CSV tidak mengandungi data.";
        }
        if (!isset($seen[1])) {
            $errors[] = "CSV mesti mengandungi Bil. 1.";
        }
        $token = bin2hex(random_bytes(32));
        $path = Config::root() . "/storage/imports/" . $token . ".json";
        file_put_contents(
            $path,
            json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            LOCK_EX
        );
        $this->db
            ->prepare("DELETE FROM import_previews WHERE expires_at<NOW()")
            ->execute();
        $this->db
            ->prepare(
                "INSERT INTO import_previews(token,user_id,volume_id,temp_path,row_count,warnings,expires_at) VALUES (?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))"
            )
            ->execute([
                $token,
                $user["id"],
                (int) $volumeId,
                $path,
                count($rows),
                json_encode($warnings, JSON_UNESCAPED_UNICODE),
            ]);
        View::render("import", [
            "title" => "Pratonton Import CSV",
            "rows" => array_slice($rows, 0, 20),
            "errors" => $errors,
            "warnings" => $warnings,
            "token" => $token,
            "volume" => $volume,
        ]);
    }

    public function importConfirm(): void
    {
        $user = Auth::requireAdmin();
        $token = (string) ($_POST["token"] ?? "");
        $s = $this->db->prepare(
            "SELECT * FROM import_previews WHERE token=? AND user_id=? AND expires_at>NOW()"
        );
        $s->execute([$token, $user["id"]]);
        $preview = $s->fetch();
        if (!$preview) {
            Http::abort(
                410,
                "Pratonton import telah tamat. Muat naik semula CSV."
            );
        }
        $rows = json_decode(
            (string) file_get_contents($preview["temp_path"]),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $volume = $this->volume((int) $preview["volume_id"]);
        Database::transaction(function (PDO $db) use ($rows, $preview, $user) {
            $lock = $db->prepare(
                "SELECT id FROM volumes WHERE id=? AND archived_at IS NULL FOR UPDATE"
            );
            $lock->execute([$preview["volume_id"]]);
            if (!$lock->fetch()) {
                throw new \RuntimeException("Jilid tidak lagi tersedia.");
            }
            $count = $db->prepare(
                "SELECT COUNT(*) FROM entries WHERE volume_id=?"
            );
            $count->execute([$preview["volume_id"]]);
            if ((int) $count->fetchColumn() > 0) {
                throw new \RuntimeException("Jilid tidak lagi kosong.");
            }
            $insert = $db->prepare(
                "INSERT INTO entries(volume_id,entry_no,type,letter_date,correspondent,movement_date,matter,remarks,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?)"
            );
            foreach ($rows as $r) {
                $insert->execute([
                    $preview["volume_id"],
                    $r["entry_no"],
                    $r["type"],
                    $r["letter_date"],
                    $r["correspondent"],
                    $r["movement_date"],
                    $r["matter"],
                    $r["remarks"],
                    $user["id"],
                    $user["id"],
                ]);
            }
            Audit::log(
                "csv.imported",
                "volume",
                (int) $preview["volume_id"],
                null,
                ["rows" => count($rows)]
            );
            $db->prepare("DELETE FROM import_previews WHERE token=?")->execute([
                $preview["token"],
            ]);
        });
        @unlink($preview["temp_path"]);
        Http::flash(
            "success",
            count($rows) . " entri berjaya diimport dalam satu transaksi."
        );
        Http::redirect(
            "/fail/" . $volume["folder_id"] . "?jilid=" . $volume["id"]
        );
    }

    public function users(): void
    {
        Auth::requireAdmin();
        $users = $this->db
            ->query(
                "SELECT id,fullname,username,email,phone,role,status,reset_warning,created_at FROM users WHERE archived_at IS NULL ORDER BY fullname"
            )
            ->fetchAll();
        View::render("users", [
            "title" => "Pengurusan Pengguna",
            "users" => $users,
        ]);
    }

    public function createUser(): void
    {
        $actor = Auth::requireAdmin();
        $data = $this->userData();
        $error = Validation::password((string) ($_POST["password"] ?? ""));
        if ($error) {
            $this->back($error);
        }
        try {
            $this->db
                ->prepare(
                    'INSERT INTO users(fullname,username,username_norm,email,email_norm,phone,role,status,password_hash) VALUES (?,?,?,?,?,?,?,\'Active\',?)'
                )
                ->execute([
                    $data["fullname"],
                    $data["username"],
                    mb_strtolower($data["username"]),
                    $data["email"],
                    mb_strtolower($data["email"]),
                    $data["phone"],
                    $data["role"],
                    password_hash($_POST["password"], PASSWORD_ARGON2ID),
                ]);
            $id = (int) $this->db->lastInsertId();
            Audit::log("user.created", "user", $id, null, $data, $actor["id"]);
        } catch (\PDOException $e) {
            $this->back("Username atau e-mel telah digunakan.");
        }
        Http::flash("success", "Pengguna berjaya ditambah.");
        Http::redirect("/admin/pengguna");
    }

    public function userAction(string $id): void
    {
        $actor = Auth::requireAdmin();
        $target = $this->user((int) $id);
        $action = (string) ($_POST["action"] ?? "");
        if (
            (int) $id === (int) $actor["id"] &&
            in_array($action, ["deactivate", "demote"], true)
        ) {
            $this->back(
                "Anda tidak boleh menurunkan role atau menyahaktifkan akaun sendiri."
            );
        }
        if ($action === "update") {
            if ($target["role"] === "Admin") {
                Http::abort(
                    403,
                    "Butiran peribadi Admin lain tidak boleh diedit. Setiap Admin mengurus profil sendiri."
                );
            }
            $fullname = trim((string) ($_POST["fullname"] ?? ""));
            $username = trim((string) ($_POST["username"] ?? ""));
            $email = trim((string) ($_POST["email"] ?? ""));
            $phone = $this->nullable("phone");
            if (
                $fullname === "" ||
                !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username) ||
                !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {
                $this->back("Butiran Staff tidak sah.");
            }
            try {
                $this->db
                    ->prepare(
                        "UPDATE users SET fullname=?,username=?,username_norm=?,email=?,email_norm=?,phone=? WHERE id=?"
                    )
                    ->execute([
                        $fullname,
                        $username,
                        mb_strtolower($username),
                        $email,
                        mb_strtolower($email),
                        $phone,
                        (int) $id,
                    ]);
            } catch (\PDOException $e) {
                $this->back("Username atau e-mel telah digunakan.");
            }
            Audit::log("user.updated", "user", (int) $id, $target, [
                "fullname" => $fullname,
                "username" => $username,
                "email" => $email,
                "phone" => $phone,
            ]);
        } elseif ($action === "reset") {
            $this->db
                ->prepare(
                    "UPDATE users SET password_hash=?,reset_warning=1 WHERE id=?"
                )
                ->execute([
                    password_hash("Passw123", PASSWORD_ARGON2ID),
                    (int) $id,
                ]);
            Audit::log("user.password_reset", "user", (int) $id);
        } elseif ($action === "deactivate") {
            $this->assertAdminPreserved($target);
            $this->db
                ->prepare("UPDATE users SET status='Inactive' WHERE id=?")
                ->execute([(int) $id]);
            Audit::log("user.deactivated", "user", (int) $id);
        } elseif ($action === "activate") {
            $this->db
                ->prepare("UPDATE users SET status='Active' WHERE id=?")
                ->execute([(int) $id]);
            Audit::log("user.activated", "user", (int) $id);
        } elseif ($action === "demote") {
            $this->assertAdminPreserved($target);
            $this->db
                ->prepare("UPDATE users SET role='Staff' WHERE id=?")
                ->execute([(int) $id]);
            Audit::log("user.demoted", "user", (int) $id);
        } elseif ($action === "promote") {
            $this->db
                ->prepare("UPDATE users SET role='Admin' WHERE id=?")
                ->execute([(int) $id]);
            Audit::log("user.promoted", "user", (int) $id);
        } else {
            Http::abort(400, "Tindakan tidak sah.");
        }
        Http::flash("success", "Status pengguna telah dikemas kini.");
        Http::redirect("/admin/pengguna");
    }

    public function grant(string $folderId): void
    {
        $actor = Auth::requireAdmin();
        $userId = (int) ($_POST["user_id"] ?? 0);
        $action = $_POST["action"] ?? "grant";
        if ($action === "revoke") {
            $this->db
                ->prepare(
                    "DELETE FROM folder_access WHERE folder_id=? AND user_id=?"
                )
                ->execute([(int) $folderId, $userId]);
            Audit::log(
                "access.revoked",
                "folder",
                (int) $folderId,
                ["user_id" => $userId],
                null
            );
        } else {
            $check = $this->db->prepare(
                "SELECT 1 FROM users WHERE id=? AND role='Staff' AND status='Active'"
            );
            $check->execute([$userId]);
            if (!$check->fetch()) {
                $this->back("Hanya Staff aktif boleh diberi akses.");
            }
            $this->db
                ->prepare(
                    "INSERT IGNORE INTO folder_access(folder_id,user_id,granted_by) VALUES (?,?,?)"
                )
                ->execute([(int) $folderId, $userId, $actor["id"]]);
            Audit::log("access.granted", "folder", (int) $folderId, null, [
                "user_id" => $userId,
            ]);
        }
        Http::flash("success", "Kebenaran akses dikemas kini.");
        Http::redirect("/fail/" . (int) $folderId);
    }

    public function profile(): void
    {
        $user = Auth::requireLogin();
        View::render("profile", ["title" => "Profil Saya", "profile" => $user]);
    }
    public function updateProfile(): void
    {
        $user = Auth::requireLogin();
        $fullname = trim((string) ($_POST["fullname"] ?? ""));
        $email = trim((string) ($_POST["email"] ?? ""));
        $phone = $this->nullable("phone");
        if ($fullname === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->back("Nama penuh dan e-mel yang sah diperlukan.");
        }
        try {
            $this->db
                ->prepare(
                    "UPDATE users SET fullname=?,email=?,email_norm=?,phone=? WHERE id=?"
                )
                ->execute([
                    $fullname,
                    $email,
                    mb_strtolower($email),
                    $phone,
                    $user["id"],
                ]);
            Audit::log("profile.updated", "user", (int) $user["id"], $user, [
                "fullname" => $fullname,
                "email" => $email,
                "phone" => $phone,
            ]);
        } catch (\PDOException $e) {
            $this->back("E-mel telah digunakan.");
        }
        Http::flash("success", "Profil berjaya dikemas kini.");
        Http::redirect("/profil");
    }
    public function changePassword(): void
    {
        $user = Auth::requireLogin();
        $current = (string) ($_POST["current_password"] ?? "");
        $new = (string) ($_POST["new_password"] ?? "");
        $row = $this->user((int) $user["id"], true);
        if (!password_verify($current, $row["password_hash"])) {
            $this->back("Kata laluan semasa tidak tepat.");
        }
        if ($e = Validation::password($new)) {
            $this->back($e);
        }
        $this->db
            ->prepare(
                "UPDATE users SET password_hash=?,reset_warning=0 WHERE id=?"
            )
            ->execute([password_hash($new, PASSWORD_ARGON2ID), $user["id"]]);
        Audit::log("profile.password_changed", "user", (int) $user["id"]);
        session_regenerate_id(true);
        Http::flash("success", "Kata laluan berjaya ditukar.");
        Http::redirect("/profil");
    }

    public function audit(): void
    {
        Auth::requireAdmin();
        $page = max(1, (int) ($_GET["page"] ?? 1));
        $s = $this->db->query(
            "SELECT a.*,u.fullname actor_name FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_id ORDER BY a.created_at DESC LIMIT 50 OFFSET " .
                ($page - 1) * 50
        );
        View::render("audit", [
            "title" => "Sejarah Audit",
            "logs" => $s->fetchAll(),
            "page" => $page,
        ]);
    }

    public function backup(): void
    {
        $user = Auth::requireAdmin();
        $row = $this->user((int) $user["id"], true);
        if (
            !password_verify(
                (string) ($_POST["password"] ?? ""),
                $row["password_hash"]
            )
        ) {
            $this->back("Kata laluan semasa tidak tepat.");
        }
        $binary = Config::get("BACKUP_BINARY", "/usr/bin/mariadb-dump");
        if (
            $binary === null ||
            $binary === "" ||
            ((str_contains($binary, "/") || str_contains($binary, "\\")) &&
                !is_file($binary))
        ) {
            Http::abort(500, "Utiliti sandaran tidak tersedia.");
        }
        $cmd = [
            $binary,
            "--single-transaction",
            "--skip-lock-tables",
            "--host=" . Config::get("DB_HOST", "127.0.0.1"),
            "--port=" . Config::get("DB_PORT", "3306"),
            "--user=" . Config::get("DB_USER", "spfpu"),
            "--password=" . Config::get("DB_PASS", ""),
            Config::get("DB_NAME", "spfpu"),
        ];
        $dump = @proc_open(
            $cmd,
            [1 => ["pipe", "w"], 2 => ["pipe", "w"]],
            $pipes
        );
        if (!is_resource($dump)) {
            Http::abort(500, "Utiliti sandaran tidak tersedia.");
        }
        $gzip = deflate_init(ZLIB_ENCODING_GZIP, ["level" => 9]);
        if ($gzip === false) {
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_terminate($dump);
            proc_close($dump);
            Http::abort(500, "Sandaran tidak dapat dimampatkan.");
        }

        Audit::log("backup.downloaded", "database");
        session_write_close();
        header("Content-Type: application/gzip");
        header(
            'Content-Disposition: attachment; filename="spfpu-backup-' .
                date("Ymd-His") .
                '.sql.gz"'
        );
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Pragma: no-cache");
        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 8192);
            if ($chunk !== false && $chunk !== "") {
                echo deflate_add($gzip, $chunk, ZLIB_NO_FLUSH);
            }
        }
        echo deflate_add($gzip, "", ZLIB_FINISH);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($dump);
        exit();
    }

    private function entryData(): array
    {
        $type = (string) ($_POST["type"] ?? "");
        $letter = (string) ($_POST["letter_date"] ?? "");
        $movement = (string) ($_POST["movement_date"] ?? "");
        $correspondent = trim((string) ($_POST["correspondent"] ?? ""));
        $matter = trim((string) ($_POST["matter"] ?? ""));
        if (
            !in_array($type, ["Incoming", "Outgoing"], true) ||
            !Validation::date($letter) ||
            !Validation::date($movement) ||
            $correspondent === "" ||
            $matter === "" ||
            mb_strlen($matter) > 500
        ) {
            $this->back("Lengkapkan semua medan entri dengan nilai yang sah.");
        }
        return [
            "type" => $type,
            "letter_date" => $letter,
            "correspondent" => $correspondent,
            "movement_date" => $movement,
            "matter" => $matter,
            "remarks" => $this->nullable("remarks"),
        ];
    }
    private function volume(int $id): array
    {
        $s = $this->db->prepare(
            "SELECT v.*,f.category_id FROM volumes v JOIN folders f ON f.id=v.folder_id WHERE v.id=? AND v.archived_at IS NULL AND f.archived_at IS NULL"
        );
        $s->execute([$id]);
        $r = $s->fetch();
        if (!$r) {
            Http::abort(404, "Jilid tidak ditemui.");
        }
        return $r;
    }
    private function entry(int $id): array
    {
        $s = $this->db->prepare(
            "SELECT e.*,v.folder_id,v.status FROM entries e JOIN volumes v ON v.id=e.volume_id WHERE e.id=? AND e.archived_at IS NULL"
        );
        $s->execute([$id]);
        $r = $s->fetch();
        if (!$r) {
            Http::abort(404, "Entri tidak ditemui.");
        }
        return $r;
    }
    private function user(int $id, bool $secret = false): array
    {
        $cols = $secret
            ? "*"
            : "id,fullname,username,email,phone,role,status,reset_warning";
        $s = $this->db->prepare(
            "SELECT $cols FROM users WHERE id=? AND archived_at IS NULL"
        );
        $s->execute([$id]);
        $r = $s->fetch();
        if (!$r) {
            Http::abort(404, "Pengguna tidak ditemui.");
        }
        return $r;
    }
    private function userData(): array
    {
        $fullname = trim((string) ($_POST["fullname"] ?? ""));
        $username = trim((string) ($_POST["username"] ?? ""));
        $email = trim((string) ($_POST["email"] ?? ""));
        $role = (string) ($_POST["role"] ?? "Staff");
        if (
            $fullname === "" ||
            !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username) ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            !in_array($role, ["Admin", "Staff"], true)
        ) {
            $this->back("Maklumat pengguna tidak sah.");
        }
        return compact("fullname", "username", "email", "role") + [
            "phone" => $this->nullable("phone"),
        ];
    }
    private function assertAdminPreserved(array $target): void
    {
        if (
            $target["role"] === "Admin" &&
            $target["status"] === "Active" &&
            (int) $this->db
                ->query(
                    "SELECT COUNT(*) FROM users WHERE role='Admin' AND status='Active' AND archived_at IS NULL"
                )
                ->fetchColumn() <= 1
        ) {
            $this->back(
                "Sekurang-kurangnya seorang Admin aktif mesti dikekalkan."
            );
        }
    }
    private function nullable(string $key): ?string
    {
        $v = trim((string) ($_POST[$key] ?? ""));
        return $v === "" ? null : $v;
    }
    private function back(string $message): never
    {
        $_SESSION["old"] = $_POST;
        Http::flash("error", $message);
        Http::redirect($_SERVER["HTTP_REFERER"] ?? "/");
    }
    private function uuid(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split(bin2hex($d), 4));
    }
}
