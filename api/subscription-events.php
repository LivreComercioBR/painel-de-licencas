<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function apiResponse(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiHeader(string $name): string
{
    $key = 'HTTP_'.strtoupper(str_replace('-', '_', $name));
    return trim((string) ($_SERVER[$key] ?? ''));
}

function apiDate(?string $value, bool $required = false): ?string
{
    if ($value === null || trim($value) === '') {
        if ($required) throw new InvalidArgumentException('Data obrigatória ausente.');
        return null;
    }
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    } catch (Throwable $exception) {
        throw new InvalidArgumentException('Data inválida.', 0, $exception);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiResponse(405, ['error' => 'method_not_allowed']);
$length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length > 65536) apiResponse(413, ['error' => 'payload_too_large']);

$rawBody = file_get_contents('php://input', false, null, 0, 65537);
if (!is_string($rawBody) || $rawBody === '') apiResponse(400, ['error' => 'empty_body']);
if (strlen($rawBody) > 65536) apiResponse(413, ['error' => 'payload_too_large']);
$payload = json_decode($rawBody, true);
if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) apiResponse(400, ['error' => 'invalid_json']);

$productCode = apiHeader('X-RMC-Product-Code');
$timestamp = apiHeader('X-RMC-Timestamp');
$eventId = apiHeader('X-RMC-Event-Id');
$signature = apiHeader('X-RMC-Signature');
$secrets = $config['subscription_event_secrets'] ?? [];
$secret = is_array($secrets) ? (string) ($secrets[$productCode] ?? '') : '';

if (!preg_match('/^[a-z0-9][a-z0-9-]{1,63}$/', $productCode)
    || !preg_match('/^[0-9]{10}$/', $timestamp)
    || !preg_match('/^[A-Za-z0-9._:-]{8,120}$/', $eventId)
    || !preg_match('/^[a-f0-9]{64}$/i', $signature)
    || strlen($secret) < 32) {
    apiResponse(401, ['error' => 'unauthorized']);
}
if (abs(time() - (int) $timestamp) > 300) apiResponse(401, ['error' => 'stale_request']);

$canonical = $productCode."\n".$timestamp."\n".$eventId."\n".hash('sha256', $rawBody);
$expected = hash_hmac('sha256', $canonical, $secret);
if (!hash_equals($expected, strtolower($signature))) apiResponse(401, ['error' => 'invalid_signature']);

if (($payload['event_type'] ?? '') !== 'subscription.snapshot') apiResponse(422, ['error' => 'unsupported_event']);
$subscription = $payload['subscription'] ?? null;
if (!is_array($subscription)) apiResponse(422, ['error' => 'invalid_subscription']);

try {
    $externalReference = trim((string) ($subscription['external_reference'] ?? ''));
    $customerName = trim((string) ($subscription['customer_name'] ?? ''));
    $customerEmail = strtolower(trim((string) ($subscription['customer_email'] ?? '')));
    $customerDocument = trim((string) ($subscription['customer_document'] ?? ''));
    $planCode = trim((string) ($subscription['plan_code'] ?? ''));
    $status = trim((string) ($subscription['status'] ?? ''));
    $provider = trim((string) ($subscription['provider'] ?? ''));
    $paymentStatus = trim((string) ($subscription['payment_status'] ?? ''));
    $paymentReference = trim((string) ($subscription['last_payment_reference'] ?? ''));
    $paymentAmount = filter_var($subscription['last_payment_amount'] ?? null, FILTER_VALIDATE_FLOAT);
    $currency = strtoupper(trim((string) ($subscription['currency'] ?? 'BRL')));
    $initialAmount = filter_var($subscription['initial_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    $renewalAmount = filter_var($subscription['renewal_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    $occurredAt = apiDate((string) ($payload['occurred_at'] ?? ''), true);
    $startsAt = apiDate(isset($subscription['starts_at']) ? (string) $subscription['starts_at'] : null);
    $expiresAt = apiDate(isset($subscription['expires_at']) ? (string) $subscription['expires_at'] : null);
    $graceUntil = apiDate(isset($subscription['grace_until']) ? (string) $subscription['grace_until'] : null);
    $paidAt = apiDate(isset($subscription['paid_at']) ? (string) $subscription['paid_at'] : null);

    $allowedStatuses = ['pending', 'trial', 'active', 'past_due', 'expired', 'canceled'];
    if ($externalReference === '' || strlen($externalReference) > 160
        || $customerName === '' || strlen($customerName) > 160
        || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL) || strlen($customerEmail) > 190
        || strlen($customerDocument) > 32
        || !preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/', $planCode)
        || !in_array($status, $allowedStatuses, true)
        || !preg_match('/^[a-z0-9_-]{1,40}$/i', $provider)
        || !preg_match('/^[A-Z]{3}$/', $currency)
        || $initialAmount === false || $renewalAmount === false || $initialAmount < 0 || $renewalAmount < 0
        || ($paymentReference !== '' && ($paymentAmount === false || $paymentAmount < 0))) {
        apiResponse(422, ['error' => 'invalid_subscription_fields']);
    }
    if (in_array($status, ['active', 'past_due', 'expired'], true) && !$expiresAt) {
        apiResponse(422, ['error' => 'expiry_required']);
    }
    if ($startsAt && $expiresAt && $expiresAt < $startsAt) apiResponse(422, ['error' => 'invalid_term']);
    if ($expiresAt && $graceUntil && $graceUntil < $expiresAt) apiResponse(422, ['error' => 'invalid_grace_period']);

    $providerCustomerReference = trim((string) ($subscription['provider_customer_reference'] ?? ''));
    $providerSubscriptionReference = trim((string) ($subscription['provider_subscription_reference'] ?? ''));
    if (strlen($providerCustomerReference) > 160 || strlen($providerSubscriptionReference) > 160 || strlen($paymentReference) > 160 || strlen($paymentStatus) > 32) {
        apiResponse(422, ['error' => 'field_too_long']);
    }

    $pdo = db();
    $pdo->beginTransaction();
    $productQuery = $pdo->prepare("SELECT code FROM products WHERE code=? AND license_model='subscription' AND status='active' FOR UPDATE");
    $productQuery->execute([$productCode]);
    if (!$productQuery->fetchColumn()) {
        $pdo->rollBack();
        apiResponse(404, ['error' => 'subscription_product_not_found']);
    }
    $planQuery = $pdo->prepare("SELECT code FROM subscription_plans WHERE product_code=? AND code=? AND status='active'");
    $planQuery->execute([$productCode, $planCode]);
    if (!$planQuery->fetchColumn()) {
        $pdo->rollBack();
        apiResponse(422, ['error' => 'plan_not_found']);
    }

    $duplicate = $pdo->prepare('SELECT id FROM subscription_events WHERE product_code=? AND provider=? AND source_event_id=?');
    $duplicate->execute([$productCode, $provider, $eventId]);
    if ($duplicate->fetchColumn()) {
        $pdo->commit();
        apiResponse(200, ['received' => true, 'duplicate' => true]);
    }
    $find = $pdo->prepare('SELECT * FROM subscriptions WHERE product_code=? AND external_reference=? FOR UPDATE');
    $find->execute([$productCode, $externalReference]);
    $current = $find->fetch(PDO::FETCH_ASSOC);
    $isNewer = !$current || !$current['source_updated_at'] || $occurredAt >= $current['source_updated_at'];
    if ($isNewer) {
        $values = [
            $customerName, $customerEmail, $customerDocument !== '' ? $customerDocument : null, $planCode,
            $provider, $providerCustomerReference !== '' ? $providerCustomerReference : null,
            $providerSubscriptionReference !== '' ? $providerSubscriptionReference : null,
            $status, $startsAt, $expiresAt, $graceUntil, number_format((float) $initialAmount, 2, '.', ''),
            number_format((float) $renewalAmount, 2, '.', ''), $currency, $paymentStatus !== '' ? $paymentStatus : null,
            $paymentReference !== '' ? $paymentReference : null, $paidAt, $occurredAt,
        ];
        if ($current) {
            $values[] = (int) $current['id'];
            $update = $pdo->prepare('UPDATE subscriptions SET customer_name=?, customer_email=?, customer_document=?, plan_code=?, provider=?, provider_customer_reference=?, provider_subscription_reference=?, status=?, starts_at=?, expires_at=?, grace_until=?, initial_amount=?, renewal_amount=?, currency=?, payment_status=?, last_payment_reference=?, paid_at=?, source_updated_at=? WHERE id=?');
            $update->execute($values);
            $subscriptionId = (int) $current['id'];
        } else {
            array_unshift($values, $productCode, $externalReference);
            $insert = $pdo->prepare('INSERT INTO subscriptions (product_code, external_reference, customer_name, customer_email, customer_document, plan_code, provider, provider_customer_reference, provider_subscription_reference, status, starts_at, expires_at, grace_until, initial_amount, renewal_amount, currency, payment_status, last_payment_reference, paid_at, source_updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $insert->execute($values);
            $subscriptionId = (int) $pdo->lastInsertId();
        }
    } else {
        $subscriptionId = (int) $current['id'];
    }

    $eventStatus = $paymentStatus !== '' ? $paymentStatus : $status;
    $eventInsert = $pdo->prepare('INSERT INTO subscription_events (product_code, subscription_id, provider, source_event_id, provider_payment_reference, event_type, event_status, amount, currency, occurred_at, payload_sha256) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $eventInsert->execute([
        $productCode, $subscriptionId, $provider, $eventId, $paymentReference !== '' ? $paymentReference : null,
        'subscription.snapshot', $eventStatus, $paymentReference !== '' ? number_format((float) $paymentAmount, 2, '.', '') : null,
        $currency, $occurredAt, hash('sha256', $rawBody),
    ]);
    $pdo->commit();
    apiResponse(200, ['received' => true, 'updated' => (bool) $isNewer]);
} catch (InvalidArgumentException $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    apiResponse(422, ['error' => 'invalid_date']);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Subscription sync failed: '.$exception->getMessage());
    apiResponse(500, ['error' => 'temporary_failure']);
}
