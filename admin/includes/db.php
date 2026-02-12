<?php

function admin_data_dir(): string
{
    return dirname(__DIR__) . '/data';
}

function admin_db_path(): string
{
    return admin_data_dir() . '/app.sqlite';
}

function admin_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(admin_data_dir())) {
        mkdir(admin_data_dir(), 0750, true);
    }

    $pdo = new PDO('sqlite:' . admin_db_path());
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    admin_db_init($pdo);
    return $pdo;
}

function admin_db_init(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL,
            last_login_at TEXT
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            form_type TEXT NOT NULL,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT NOT NULL,
            city TEXT,
            message TEXT,
            ip_address TEXT,
            user_agent TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )"
    );
}

function admin_count_admins(): int
{
    $stmt = admin_db()->query("SELECT COUNT(*) FROM admins");
    return (int) $stmt->fetchColumn();
}

function admin_create_user(string $email, string $password): void
{
    $stmt = admin_db()->prepare(
        "INSERT INTO admins (email, password_hash, created_at) VALUES (:email, :hash, :created_at)"
    );
    $stmt->execute([
        ':email' => strtolower(trim($email)),
        ':hash' => password_hash($password, PASSWORD_DEFAULT),
        ':created_at' => gmdate('c'),
    ]);
}

function admin_get_user_by_email(string $email): ?array
{
    $stmt = admin_db()->prepare("SELECT * FROM admins WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => strtolower(trim($email))]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function admin_update_last_login(int $adminId): void
{
    $stmt = admin_db()->prepare("UPDATE admins SET last_login_at = :last_login_at WHERE id = :id");
    $stmt->execute([
        ':id' => $adminId,
        ':last_login_at' => gmdate('c'),
    ]);
}

function admin_insert_submission(array $payload): int
{
    $stmt = admin_db()->prepare(
        "INSERT INTO submissions (
            form_type, name, email, phone, city, message, ip_address, user_agent, created_at, updated_at
        ) VALUES (
            :form_type, :name, :email, :phone, :city, :message, :ip_address, :user_agent, :created_at, :updated_at
        )"
    );

    $now = gmdate('c');
    $stmt->execute([
        ':form_type' => $payload['form_type'],
        ':name' => $payload['name'],
        ':email' => $payload['email'],
        ':phone' => $payload['phone'],
        ':city' => $payload['city'] ?? null,
        ':message' => $payload['message'] ?? null,
        ':ip_address' => $payload['ip_address'] ?? null,
        ':user_agent' => $payload['user_agent'] ?? null,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    return (int) admin_db()->lastInsertId();
}

function admin_submission_counts(): array
{
    $stmt = admin_db()->query(
        "SELECT form_type, COUNT(*) AS total
         FROM submissions
         GROUP BY form_type"
    );
    $counts = ['index' => 0, 'contact' => 0];
    foreach ($stmt->fetchAll() as $row) {
        if (isset($counts[$row['form_type']])) {
            $counts[$row['form_type']] = (int) $row['total'];
        }
    }
    return $counts;
}

function admin_submissions_by_type(string $formType): array
{
    $stmt = admin_db()->prepare(
        "SELECT * FROM submissions WHERE form_type = :form_type ORDER BY id DESC"
    );
    $stmt->execute([':form_type' => $formType]);
    return $stmt->fetchAll();
}

function admin_submission_by_id(int $id): ?array
{
    $stmt = admin_db()->prepare("SELECT * FROM submissions WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function admin_update_submission(int $id, array $payload): void
{
    $stmt = admin_db()->prepare(
        "UPDATE submissions
         SET name = :name,
             email = :email,
             phone = :phone,
             city = :city,
             message = :message,
             updated_at = :updated_at
         WHERE id = :id"
    );

    $stmt->execute([
        ':id' => $id,
        ':name' => $payload['name'],
        ':email' => $payload['email'],
        ':phone' => $payload['phone'],
        ':city' => $payload['city'] ?? null,
        ':message' => $payload['message'] ?? null,
        ':updated_at' => gmdate('c'),
    ]);
}

function admin_delete_submission(int $id): void
{
    $stmt = admin_db()->prepare("DELETE FROM submissions WHERE id = :id");
    $stmt->execute([':id' => $id]);
}

