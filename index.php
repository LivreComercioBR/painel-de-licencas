<?php require __DIR__.'/bootstrap.php';
if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }
if (!isLogged() && $_SERVER['REQUEST_METHOD'] === 'POST') { requireCsrf(); if (password_verify($_POST['password'] ?? '', $config['admin_password_hash'])) { $_SESSION['rmc_license_admin'] = true; header('Location: index.php'); exit; } $error = 'Senha inválida.'; }
if (!isLogged()): ?>
<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Painel de Licenças RMC</title><link rel="stylesheet" href="assets/app.css"><main class="login"><h1>RMC Flow Oficina Pro</h1><p>Painel privado de licenças</p><?php if (!empty($error)) echo '<p class="error">'.$error.'</p>'; ?><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf())?>"><input type="password" name="password" required placeholder="Senha do administrador" autofocus><button>Entrar</button></form></main></html><?php exit; endif;

if ($_SERVER['REQUEST_METHOD'] === 'POST') { requireCsrf();
    if (($_POST['action'] ?? '') === 'create') {
        $statement = db()->prepare('INSERT INTO licenses (license_code, customer_name, customer_email, customer_document, plan, status) VALUES (?, ?, ?, ?, ?, "available")');
        $statement->execute([code(), trim($_POST['customer_name']), trim($_POST['customer_email']), trim($_POST['customer_document']), 'local_perpetual']);
        header('Location: index.php?created=1'); exit;
    }
    if (($_POST['action'] ?? '') === 'activate') {
        $licenseId = (int) $_POST['license_id']; $request = trim($_POST['request_code']);
        $row = db()->prepare('SELECT * FROM licenses WHERE id = ?'); $row->execute([$licenseId]); $license = $row->fetch(PDO::FETCH_ASSOC);
        if (!$license || !$request) throw new RuntimeException('Licença ou código de solicitação inválido.');
        $payload = ['product' => 'RMC Flow Oficina Pro', 'license_code' => $license['license_code'], 'request_code' => $request, 'type' => 'local_perpetual', 'issued_at' => gmdate('c'), 'features' => ['vehicles' => ['car', 'motorcycle'], 'offline' => true]];
        $signed = signLicense($payload);
        db()->prepare('UPDATE licenses SET status="activated", request_code=?, activation_payload=?, activated_at=NOW() WHERE id=?')->execute([$request, $signed, $licenseId]);
        header('Location: index.php?license='.$licenseId); exit;
    }
    if (($_POST['action'] ?? '') === 'issue_recovery') {
        $parts=explode('|',trim($_POST['recovery_request']??''));
        if(count($parts)!==3||$parts[0]!=='RMCREC1'||!$parts[1]||!$parts[2]) throw new RuntimeException('Solicitação de recuperação inválida.');
        // A solicitação identifica a instalação; localize automaticamente a licença correta.
        $row=db()->prepare('SELECT * FROM licenses WHERE request_code=? AND status="activated"'); $row->execute([$parts[1]]); $license=$row->fetch(PDO::FETCH_ASSOC);
        if(!$license) throw new RuntimeException('Nenhuma licença ativada corresponde a esta solicitação.');
        $licenseId=(int)$license['id'];
        $expires=gmdate('c',time()+1200);
        $payload=['product'=>'RMC Flow Oficina Pro','purpose'=>'admin_password_reset','license_code'=>$license['license_code'],'request_code'=>$license['request_code'],'recovery_nonce'=>$parts[2],'issued_at'=>gmdate('c'),'expires_at'=>$expires];
        $signed=signLicense($payload);
        db()->prepare('INSERT INTO license_recovery_events (license_id,request_nonce,expires_at) VALUES (?,?,?)')->execute([$licenseId,$parts[2],gmdate('Y-m-d H:i:s',time()+1200)]);
        $_SESSION['recovery_authorization']=$signed; header('Location: index.php?license='.$licenseId); exit;
    }
}
$licenses = db()->query('SELECT * FROM licenses ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC); $selected = null;
if (!empty($_GET['license'])) { $q = db()->prepare('SELECT * FROM licenses WHERE id=?'); $q->execute([(int)$_GET['license']]); $selected = $q->fetch(PDO::FETCH_ASSOC); }
$recoveryAuthorization=$_SESSION['recovery_authorization']??null; unset($_SESSION['recovery_authorization']);
?>
<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Painel de Licenças RMC</title><link rel="stylesheet" href="assets/app.css"><body><header><strong>RMC Sites — Central de Licenças</strong><nav><a href="subscriptions.php">Produtos e assinaturas</a><a href="?logout=1">Sair</a></nav></header><main><h1>Licenças da Edição Local</h1><?php if (isset($_GET['created'])) echo '<p class="success">Licença criada com sucesso.</p>'; ?>
<section class="card"><h2>Nova venda</h2><form method="post" class="grid"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf())?>"><input type="hidden" name="action" value="create"><label>Cliente<input name="customer_name" required></label><label>E-mail de compra<input name="customer_email" type="email" required></label><label>CPF/CNPJ<input name="customer_document"></label><button>Criar licença permanente</button></form></section>
<?php if ($selected): ?><section class="card"><h2>Ativação: <?=htmlspecialchars($selected['license_code'])?></h2><?php if ($selected['status'] === 'activated'): ?><p class="success">Licença assinada. Copie o bloco abaixo e envie ao cliente.</p><textarea readonly rows="7"><?=htmlspecialchars($selected['activation_payload'])?></textarea><hr><h3>Recuperação de administrador</h3><p>Receba do cliente a solicitação iniciada por <code>RMCREC1|</code>. A autorização emitida abaixo expira em 20 minutos e só pode ser usada naquela solicitação.</p><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf())?>"><input type="hidden" name="action" value="issue_recovery"><input type="hidden" name="license_id" value="<?=$selected['id']?>"><label>Solicitação de recuperação<input name="recovery_request" required placeholder="RMCREC1|RMC-...|..."></label><button>Gerar autorização temporária</button></form><?php if($recoveryAuthorization): ?><p class="success">Autorização gerada. Copie o bloco abaixo e envie ao cliente.</p><textarea readonly rows="7"><?=htmlspecialchars($recoveryAuthorization)?></textarea><?php endif; ?><?php else: ?><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf())?>"><input type="hidden" name="action" value="activate"><input type="hidden" name="license_id" value="<?=$selected['id']?>"><label>Código de solicitação do computador<input name="request_code" required placeholder="RMC-..."></label><button>Gerar licença assinada</button></form><?php endif; ?></section><?php endif; ?>
<section class="card"><h2>Licenças</h2><table><thead><tr><th>Código</th><th>Cliente</th><th>E-mail</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($licenses as $license): ?><tr><td><?=htmlspecialchars($license['license_code'])?></td><td><?=htmlspecialchars($license['customer_name'])?></td><td><?=htmlspecialchars($license['customer_email'])?></td><td><?=htmlspecialchars($license['status'])?></td><td><a href="?license=<?=$license['id']?>">Abrir</a></td></tr><?php endforeach; ?></tbody></table></section></main></body></html>
