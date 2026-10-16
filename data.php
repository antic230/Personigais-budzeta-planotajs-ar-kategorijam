<?php
// === DATU SLANIS: savienojums ar MySQL un visi SQL vaicajumi ===
const DB_DSN = 'mysql:host=localhost;dbname=budget_planner';
const DB_USER = 'root';   // XAMPP noklusejums
const DB_PASS = '';

/* Avots: https://www.php.net/manual/en/pdo.connections.php  */
function db(): PDO {
    static $pdo;
    return $pdo ??= new PDO(DB_DSN, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function user_by_email(string $email): ?array { return q('SELECT * FROM users WHERE email=?', [$email])->fetch() ?: null; }
function user_create(string $email, string $hash): int { q('INSERT INTO users (email,password_hash) VALUES (?,?)', [$email, $hash]); return (int) db()->lastInsertId(); }

function cat_all(int $uid): array { return q('SELECT * FROM categories WHERE user_id=? ORDER BY type,name', [$uid])->fetchAll(); }
function cat_create(int $uid, string $name, string $type, ?string $limit): void { q('INSERT INTO categories (user_id,name,type,budget_limit) VALUES (?,?,?,?)', [$uid, $name, $type, $limit]); }
function cat_owned(int $id, int $uid): bool { return (bool) q('SELECT 1 FROM categories WHERE id=? AND user_id=?', [$id, $uid])->fetchColumn(); }
function cat_totals(int $uid, string $from, string $to): array {
    return q('SELECT c.id,c.name,c.type,c.budget_limit,COALESCE(SUM(t.amount),0) AS total
              FROM categories c LEFT JOIN transactions t ON t.category_id=c.id AND t.trans_date BETWEEN ? AND ?
              WHERE c.user_id=? GROUP BY c.id ORDER BY c.type,c.name', [$from, $to, $uid])->fetchAll();
}

function tx_create(int $uid, int $cat, string $amount, string $desc, string $date): void {
    q('INSERT INTO transactions (user_id,category_id,amount,description,trans_date) VALUES (?,?,?,?,?)', [$uid, $cat, $amount, $desc, $date]);
}
function tx_delete(int $id, int $uid): void { q('DELETE FROM transactions WHERE id=? AND user_id=?', [$id, $uid]); }
function tx_between(int $uid, string $from, string $to): array {
    return q('SELECT t.*,c.name AS category,c.type FROM transactions t JOIN categories c ON c.id=t.category_id
              WHERE t.user_id=? AND t.trans_date BETWEEN ? AND ? ORDER BY t.trans_date DESC,t.id DESC', [$uid, $from, $to])->fetchAll();
}
