<?php
// === LIETOTAJA SASKARNE: visas lapas (?page=login/register/logout/dashboard) 
// === avoti: chartjs.org un nedaudz chatgpt :)
require __DIR__ . '/logic.php';
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

$page = $_GET['page'] ?? 'dashboard';
$errors = [];
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post) csrf_check();
$errs = fn() => $errors ? '<p><mark>' . implode('<br>', array_map('h', $errors)) . '</mark></p>' : '';

if ($page === 'logout') { $_SESSION = []; session_destroy(); header('Location: index.php?page=login'); exit; }
if ($page === 'register' && $post) { $errors = register($_POST); if (!$errors) { header('Location: index.php?page=login'); exit; } }
if ($page === 'login' && $post)    { $errors = login($_POST);    if (!$errors) { header('Location: index.php'); exit; } }

$guest = in_array($page, ['login', 'register'], true);
if (!$guest && empty($_SESSION['uid'])) { header('Location: index.php?page=login'); exit; }

if (!$guest) {
    $uid = $_SESSION['uid'];
    if ($post) {
        match ($_POST['action'] ?? '') {
            'add_transaction' => $errors = add_transaction($uid, $_POST),
            'add_category' => $errors = add_category($uid, $_POST),
            'delete' => tx_delete((int) ($_POST['id'] ?? 0), $uid),
            default => null,
        };
        if (!$errors) { header('Location: index.php?month=' . urlencode(v_month($_GET['month'] ?? ''))); exit; }
    }
    $month = v_month($_GET['month'] ?? '');
    $s = summary($uid, $month);
    $cats = cat_all($uid);
    $chart = array_values(array_filter($s['cats'], fn($c) => $c['type'] === 'expense' && $c['total'] > 0));
    $eur = fn($n) => number_format((float) $n, 2, ',', ' ') . ' €';
}
?><!doctype html>
<html lang="lv"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Budzeta planotajs</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2.0.6/css/pico.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script><!-- Chart.js diagramma -->
</head><body><main class="container">

<?php if ($guest): ?>
  <article><h2><?= $page === 'login' ? 'Pieteiksanas' : 'Registracija' ?></h2><?= $errs() ?>
  <form method="post"><?= csrf_field() ?>
    <label>E-pasts <input type="email" name="email" required maxlength="254"></label>
    <label>Parole <input type="password" name="password" required <?= $page === 'register' ? 'minlength="8" maxlength="128"' : '' ?>></label>
    <?php if ($page === 'register'): ?><label>Atkartota parole <input type="password" name="password2" required minlength="8" maxlength="128"></label><?php endif; ?>
    <button><?= $page === 'login' ? 'Pieteikties' : 'Registreties' ?></button></form>
  <a href="?page=<?= $page === 'login' ? 'register' : 'login' ?>"><?= $page === 'login' ? 'Izveidot kontu' : 'Jau ir konts?' ?></a></article>

<?php else: ?>
  <nav><ul><li><strong>Budzeta planotajs</strong></li></ul><ul><li><?= h($_SESSION['email']) ?></li><li><a href="?page=logout">Iziet</a></li></ul></nav>
  <form method="get"><fieldset role="group"><input type="month" name="month" value="<?= h($month) ?>"><button>Radit</button></fieldset></form>
  <?= $errs() ?>
  <div class="grid">
    <article>Ienakumi<h3><?= $eur($s['income']) ?></h3></article>
    <article>Izdevumi<h3><?= $eur($s['expense']) ?></h3></article>
    <article>Bilance<h3><?= $eur($s['income'] - $s['expense']) ?></h3></article>
  </div>

  <div class="grid">
    <article><h4>Jauns ieraksts</h4><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add_transaction">
      <select name="category_id" required><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>"><?= h($c['name']) ?> (<?= $c['type'] === 'income' ? 'ienākumi' : 'izdevumi' ?>)</option><?php endforeach; ?></select>
      <input name="amount" placeholder="Summa €" required inputmode="decimal" pattern="\d{1,8}([.,]\d{1,2})?">
      <input type="date" name="trans_date" value="<?= date('Y-m-d') ?>" required>
      <input name="description" placeholder="Apraksts" maxlength="255">
      <button>Pievienot</button></form></article>
    <article><h4>Jauna kategorija</h4><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add_category">
      <input name="name" placeholder="Nosaukums" required minlength="2" maxlength="50">
      <select name="type"><option value="expense">Izdevumi</option><option value="income">Ienākumi</option></select>
      <input name="budget_limit" placeholder="Menesa limits € (neobligats)" inputmode="decimal">
      <button>Pievienot</button></form></article>
  </div>

  <div class="grid">
    <article><h4>Kategorijas</h4><table><tr><th>Kategorija</th><th>Kopa</th><th>Limits</th></tr>
      <?php foreach ($s['cats'] as $c): ?><tr><td><?= h($c['name']) ?></td><td><?= $eur($c['total']) ?></td>
      <td><?= $c['budget_limit'] !== null ? $eur($c['budget_limit']) : '–' ?><?= $c['over'] ? ' <mark>⚠ parsniegts</mark>' : '' ?></td></tr><?php endforeach; ?></table></article>
    <article><h4>Izdevumi pa kategorijam</h4><canvas id="chart"></canvas></article>
  </div>

  <article><h4>Ieraksti</h4><table><tr><th>Datums</th><th>Kategorija</th><th>Apraksts</th><th>Summa</th><th></th></tr>
    <?php foreach ($s['tx'] as $t): ?><tr><td><?= h($t['trans_date']) ?></td><td><?= h($t['category']) ?></td><td><?= h($t['description']) ?></td>
    <td><?= $t['type'] === 'income' ? '+' : '−' ?><?= $eur($t['amount']) ?></td>
    <td><form method="post" onsubmit="return confirm('Dzest?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button class="secondary outline">Dzēst</button></form></td></tr><?php endforeach; ?></table></article>

  <script>
  // Chart.js "Doughnut" piemers no dokumentacijas (https://www.chartjs.org/docs/latest/charts/doughnut.html), dati no PHP
  const d = <?= json_encode($chart) ?>;
  if (d.length) new Chart(document.getElementById('chart'), {type: 'doughnut',
    data: {labels: d.map(c => c.name), datasets: [{data: d.map(c => c.total)}]}});
  else document.getElementById('chart').replaceWith('Saja menesi izdevumu nav.');
  </script>
<?php endif; ?>
</main></body></html>
