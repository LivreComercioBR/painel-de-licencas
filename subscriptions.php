<?php
declare(strict_types=1);

require __DIR__.'/bootstrap.php';
if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }
requireLogin();

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'create_product') {
            $code = strtolower(trim((string) ($_POST['code'] ?? '')));
            $name = trim((string) ($_POST['name'] ?? ''));
            $claim = trim((string) ($_POST['product_claim'] ?? ''));
            $featuresRaw = trim((string) ($_POST['features_json'] ?? ''));
            $features = $featuresRaw === '' ? null : json_decode($featuresRaw, true);
            $model = (string) ($_POST['license_model'] ?? '');
            if (!preg_match('/^[a-z0-9][a-z0-9-]{1,63}$/', $code) || $name === '' || strlen($name) > 160
                || $claim === '' || strlen($claim) > 160 || ($featuresRaw !== '' && !is_array($features))
                || !in_array($model, ['perpetual', 'subscription'], true)) {
                throw new InvalidArgumentException('Confira o código, o nome e o modelo do produto.');
            }
            db()->prepare('INSERT INTO products (code, name, product_claim, features_json, license_model, status) VALUES (?, ?, ?, ?, ?, \'active\')')
                ->execute([$code, $name, $claim, $features === null ? null : json_encode($features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $model]);
            $success = 'Produto cadastrado.';
        } elseif ($action === 'toggle_product') {
            $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) throw new InvalidArgumentException('Produto inválido.');
            db()->prepare("UPDATE products SET status=IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
            $success = 'Situação do produto atualizada.';
        } elseif ($action === 'create_plan') {
            $productCode = trim((string) ($_POST['product_code'] ?? ''));
            $code = strtolower(trim((string) ($_POST['plan_code'] ?? '')));
            $name = trim((string) ($_POST['plan_name'] ?? ''));
            $unit = (string) ($_POST['interval_unit'] ?? '');
            $count = filter_var($_POST['interval_count'] ?? null, FILTER_VALIDATE_INT);
            $initial = filter_var($_POST['initial_amount'] ?? null, FILTER_VALIDATE_FLOAT);
            $renewal = filter_var($_POST['renewal_amount'] ?? null, FILTER_VALIDATE_FLOAT);
            $grace = filter_var($_POST['grace_days'] ?? null, FILTER_VALIDATE_INT);
            $allowedUnits = ['day', 'week', 'month', 'year'];
            if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/', $code) || $name === '' || strlen($name) > 120
                || !in_array($unit, $allowedUnits, true) || $count === false || $count < 1 || $count > 120
                || $initial === false || $initial < 0 || $renewal === false || $renewal < 0
                || $grace === false || $grace < 0 || $grace > 365) {
                throw new InvalidArgumentException('Confira os dados do plano, os valores e o período.');
            }
            $product = db()->prepare("SELECT code FROM products WHERE code=? AND license_model='subscription' AND status='active'");
            $product->execute([$productCode]);
            if (!$product->fetchColumn()) throw new InvalidArgumentException('Selecione um produto ativo com modelo de assinatura.');
            db()->prepare('INSERT INTO subscription_plans (product_code, code, name, interval_unit, interval_count, initial_amount, renewal_amount, currency, grace_days, status) VALUES (?, ?, ?, ?, ?, ?, ?, \'BRL\', ?, \'active\')')
                ->execute([$productCode, $code, $name, $unit, $count, number_format((float) $initial, 2, '.', ''), number_format((float) $renewal, 2, '.', ''), $grace]);
            $success = 'Plano cadastrado.';
        } elseif ($action === 'toggle_plan') {
            $id = filter_var($_POST['plan_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) throw new InvalidArgumentException('Plano inválido.');
            db()->prepare("UPDATE subscription_plans SET status=IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
            $success = 'Situação do plano atualizada.';
        } else {
            throw new InvalidArgumentException('Ação inválida.');
        }
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('Central product administration failed: '.$exception->getMessage());
        $error = 'Não foi possível salvar. Verifique se o código já está cadastrado e se a migração v0.3.0 foi aplicada.';
    }
}

$products = db()->query('SELECT * FROM products ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$subscriptionProducts = db()->query("SELECT code, name FROM products WHERE license_model='subscription' AND status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$plans = db()->query('SELECT p.*, pr.name AS product_name FROM subscription_plans p JOIN products pr ON pr.code=p.product_code ORDER BY pr.name, p.name')->fetchAll(PDO::FETCH_ASSOC);
$subscriptions = db()->query('SELECT s.*, p.name AS product_name, pl.name AS plan_name FROM subscriptions s JOIN products p ON p.code=s.product_code LEFT JOIN subscription_plans pl ON pl.product_code=s.product_code AND pl.code=s.plan_code ORDER BY s.updated_at DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrfToken = htmlspecialchars(csrf(), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-BR">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Produtos e assinaturas — RMC Sites</title>
<link rel="stylesheet" href="assets/app.css">
<body>
<header><strong>Central RMC Sites</strong><nav><a href="index.php">Licenças permanentes</a> &nbsp; <a href="?logout=1">Sair</a></nav></header>
<main>
<h1>Produtos e assinaturas</h1>
<p>Cadastre os produtos e planos. As assinaturas recebidas dos sistemas integrados aparecem no histórico abaixo.</p>
<?php if ($success): ?><p class="success"><?= $e($success) ?></p><?php endif; ?>
<?php if ($error): ?><p class="error"><?= $e($error) ?></p><?php endif; ?>

<section class="card">
<h2>Novo produto</h2>
<form method="post" class="grid">
<input type="hidden" name="csrf" value="<?= $csrfToken ?>"><input type="hidden" name="action" value="create_product">
<label>Código único<input name="code" required maxlength="64" placeholder="meu-produto"></label>
<label>Nome exibido<input name="name" required maxlength="160"></label>
<label>Nome declarado na licença<input name="product_claim" required maxlength="160"></label>
<label>Recursos da licença (JSON)<textarea name="features_json" rows="2" placeholder="{&quot;offline&quot;:true}"></textarea></label>
<label>Modelo de licença<select name="license_model" required><option value="perpetual">Permanente</option><option value="subscription">Assinatura</option></select></label>
<button>Cadastrar produto</button>
</form>
</section>

<section class="card">
<h2>Produtos</h2>
<div class="table-wrap"><table><thead><tr><th>Produto</th><th>Código</th><th>Produto na licença</th><th>Modelo</th><th>Situação</th><th></th></tr></thead><tbody>
<?php foreach ($products as $product): ?><tr>
<td><?= $e($product['name']) ?></td><td><?= $e($product['code']) ?></td><td><?= $e($product['product_claim']) ?></td>
<td><?= $product['license_model'] === 'subscription' ? 'Assinatura' : 'Permanente' ?></td><td><?= $e($product['status']) ?></td>
<td><form method="post"><input type="hidden" name="csrf" value="<?= $csrfToken ?>"><input type="hidden" name="action" value="toggle_product"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><button class="secondary"><?= $product['status'] === 'active' ? 'Desativar' : 'Ativar' ?></button></form></td>
</tr><?php endforeach; ?>
</tbody></table></div>
</section>

<section class="card">
<h2>Novo plano de assinatura</h2>
<?php if (!$subscriptionProducts): ?><p>Cadastre primeiro um produto com modelo de assinatura.</p><?php else: ?>
<form method="post" class="grid">
<input type="hidden" name="csrf" value="<?= $csrfToken ?>"><input type="hidden" name="action" value="create_plan">
<label>Produto<select name="product_code" required><?php foreach ($subscriptionProducts as $product): ?><option value="<?= $e($product['code']) ?>"><?= $e($product['name']) ?></option><?php endforeach; ?></select></label>
<label>Código do plano<input name="plan_code" required maxlength="64" placeholder="anual"></label>
<label>Nome do plano<input name="plan_name" required maxlength="120"></label>
<label>Intervalo<select name="interval_unit"><option value="year">Ano(s)</option><option value="month">Mês(es)</option><option value="week">Semana(s)</option><option value="day">Dia(s)</option></select></label>
<label>Quantidade de intervalos<input type="number" name="interval_count" min="1" max="120" value="1" required></label>
<label>Valor inicial (R$)<input type="number" name="initial_amount" min="0" step="0.01" value="0.00" required></label>
<label>Valor de renovação (R$)<input type="number" name="renewal_amount" min="0" step="0.01" value="0.00" required></label>
<label>Dias de tolerância<input type="number" name="grace_days" min="0" max="365" value="0" required></label>
<button>Cadastrar plano</button>
</form><?php endif; ?>
</section>

<section class="card">
<h2>Planos</h2>
<div class="table-wrap"><table><thead><tr><th>Produto</th><th>Plano</th><th>Periodicidade</th><th>Inicial</th><th>Renovação</th><th>Tolerância</th><th>Situação</th><th></th></tr></thead><tbody>
<?php foreach ($plans as $plan): ?><tr><td><?= $e($plan['product_name']) ?></td><td><?= $e($plan['name']) ?></td>
<td><?= (int) $plan['interval_count'] ?> <?= $e($plan['interval_unit']) ?></td><td>R$ <?= number_format((float) $plan['initial_amount'], 2, ',', '.') ?></td>
<td>R$ <?= number_format((float) $plan['renewal_amount'], 2, ',', '.') ?></td><td><?= (int) $plan['grace_days'] ?> dias</td><td><?= $e($plan['status']) ?></td>
<td><form method="post"><input type="hidden" name="csrf" value="<?= $csrfToken ?>"><input type="hidden" name="action" value="toggle_plan"><input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>"><button class="secondary"><?= $plan['status'] === 'active' ? 'Desativar' : 'Ativar' ?></button></form></td></tr><?php endforeach; ?>
</tbody></table></div>
</section>

<section class="card">
<h2>Assinaturas sincronizadas</h2>
<p>Exibindo até 200 registros mais recentes. Os dados de cobrança são confirmados pelo produto de origem antes da sincronização.</p>
<div class="table-wrap"><table><thead><tr><th>Produto</th><th>Cliente</th><th>Plano</th><th>Status</th><th>Vigência</th><th>Tolerância</th><th>Pagamento</th></tr></thead><tbody>
<?php foreach ($subscriptions as $item): ?><tr><td><?= $e($item['product_name']) ?></td><td><?= $e($item['customer_name']) ?><br><small><?= $e($item['customer_email']) ?></small></td><td><?= $e($item['plan_name'] ?? $item['plan_code']) ?></td><td><?= $e($item['status']) ?></td><td><?= $item['starts_at'] ? $e(date('d/m/Y', strtotime($item['starts_at']))) : '—' ?> a <?= $item['expires_at'] ? $e(date('d/m/Y', strtotime($item['expires_at']))) : '—' ?></td><td><?= $item['grace_until'] ? $e(date('d/m/Y', strtotime($item['grace_until']))) : '—' ?></td><td><?= $e($item['payment_status'] ?? '—') ?></td></tr><?php endforeach; ?>
<?php if (!$subscriptions): ?><tr><td colspan="7">Nenhuma assinatura sincronizada ainda.</td></tr><?php endif; ?>
</tbody></table></div>
</section>
</main>
</body></html>
