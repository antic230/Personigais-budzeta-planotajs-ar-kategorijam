<?php
// === LOGIKAS SLANIS: validacija, autentifikacija, budzeta aprekini ===
require_once __DIR__ . '/data.php';

function h(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); } // XSS aizsardziba

/* https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html */
function csrf_field(): string { $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">'; }
function csrf_check(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(403); exit('CSRF kluda'); } }

// ---------- Validacija (standarti: skat. README) ----------
function v_email(string $v, array &$e): string {            
    $v = strtolower(trim($v));
    if (strlen($v) > 254 || !filter_var($v, FILTER_VALIDATE_EMAIL)) $e[] = 'Nederiga e-pasta adrese.';
    return $v;
}
function v_password(string $v, array &$e): void {           
    $n = mb_strlen($v);
    if ($n < 8 || $n > 128) $e[] = 'Parolei jabut 8–128 rakstzimes garai.';
}
function v_amount(string $v, array &$e): string {           
    $v = str_replace(',', '.', trim($v));
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $v) || (float) $v <= 0) { $e[] = 'Nederiga summa (pozitivs skaitlis, lidz 2 zimem aiz komata).'; return '0'; }
    return $v;
}
function v_date(string $v, array &$e): string {            
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) $e[] = 'Datumam jabut formata GGGG-MM-DD.';
    return $v;
}
function v_text(string $v, string $label, int $min, int $max, array &$e): string {
    $v = trim($v); $n = mb_strlen($v);
    if ($n < $min || $n > $max) $e[] = "$label: garums $min–$max rakstzimes.";
    return $v;
}
function v_month(string $v): string { return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $v) ? $v : date('Y-m'); }

// ---------- Autentifikacija ----------
function register(array $in): array {
    $e = [];
    $email = v_email($in['email'] ?? '', $e);
    $pass = $in['password'] ?? '';
    v_password($pass, $e);
    if ($pass !== ($in['password2'] ?? null)) $e[] = 'Paroles nesakrit.';
    if (!$e && user_by_email($email)) $e[] = 'Sads lietotajs jau eksiste.';
    if ($e) return $e;
    // PHP manual password_hash piemers: https://www.php.net/manual/en/function.password-hash.php
    $uid = user_create($email, password_hash($pass, PASSWORD_DEFAULT));
    foreach ([['Alga', 'income'], ['Partika', 'expense'], ['Transports', 'expense'], ['Izklaide', 'expense']] as [$n, $t]) cat_create($uid, $n, $t, null);
    return [];
}
function login(array $in): array {
    $u = user_by_email(strtolower(trim($in['email'] ?? '')));
    if (!$u || !password_verify($in['password'] ?? '', $u['password_hash'])) return ['Nepareizs e-pasts vai parole.'];
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id']; $_SESSION['email'] = $u['email'];
    return [];
}

// ---------- Budzeta logika ----------
function add_transaction(int $uid, array $in): array {
    $e = [];
    $cat = (int) ($in['category_id'] ?? 0);
    $amount = v_amount($in['amount'] ?? '', $e);
    $date = v_date($in['trans_date'] ?? '', $e);
    $desc = v_text($in['description'] ?? '', 'Apraksts', 0, 255, $e);
    if (!cat_owned($cat, $uid)) $e[] = 'Nederiga kategorija.';
    if (!$e) tx_create($uid, $cat, $amount, $desc, $date);
    return $e;
}
function add_category(int $uid, array $in): array {
    $e = [];
    $name = v_text($in['name'] ?? '', 'Nosaukums', 2, 50, $e);
    $type = $in['type'] ?? '';
    if (!in_array($type, ['income', 'expense'], true)) $e[] = 'Nederigs veids.';
    $limit = trim($in['budget_limit'] ?? '') !== '' ? v_amount($in['budget_limit'], $e) : null;
    if (!$e) { try { cat_create($uid, $name, $type, $limit); } catch (PDOException) { $e[] = 'Sada kategorija jau eksiste.'; } }
    return $e;
}
function summary(int $uid, string $month): array {
    $from = "$month-01"; $to = date('Y-m-t', strtotime($from));
    $cats = cat_totals($uid, $from, $to);
    $inc = $exp = 0.0;
    foreach ($cats as &$c) {
        $c['total'] = (float) $c['total'];
        $c['over'] = $c['type'] === 'expense' && $c['budget_limit'] !== null && $c['total'] > (float) $c['budget_limit'];
        $c['type'] === 'income' ? $inc += $c['total'] : $exp += $c['total'];
    }
    unset($c);
    return ['cats' => $cats, 'income' => $inc, 'expense' => $exp, 'tx' => tx_between($uid, $from, $to)];
}
