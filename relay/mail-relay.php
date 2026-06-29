<?php
/**
 * Stratton Prime — przekaźnik poczty (mail relay) dla home.pl.
 *
 * DLACZEGO: Railway (backend CRM) ma ZABLOKOWANE wychodzące porty SMTP (465/587),
 * więc nie może wysłać poczty bezpośrednio. Ten skrypt stoi na hostingu home.pl
 * (tym samym, na którym są skrzynki) i wysyła maile LOKALNIE przez SMTP home.pl —
 * lokalnie porty nie są blokowane. Backend woła ten skrypt po HTTPS.
 *
 * INSTALACJA:
 *   1. Wgraj ten plik na hosting home.pl pod adresem HTTPS,
 *      np. https://stratton-prime.pl/mail-relay.php  (albo w podkatalogu).
 *   2. NIE zmieniaj $SECRET poniżej — jest już ustawiony i ten sam wpiszę na Railway.
 *   3. Podaj mi finalny URL — ustawię MAIL_RELAY_URL na Railway i przetestuję.
 *
 * Bezpieczeństwo: dostęp tylko z poprawnym sekretem (HMAC-safe porównanie),
 * połączenie po HTTPS. Skrypt nie loguje haseł.
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// Sekret WYŁĄCZNIE ze środowiska (getenv MAIL_RELAY_SECRET). Bez literału w repo.
// Ten plik to nieaktywny fallback (relay działa na Vercel) — gdyby go wgrać na home.pl,
// ustaw MAIL_RELAY_SECRET w środowisku PHP. Fail-closed gdy pusty.
$SECRET = (string) (getenv('MAIL_RELAY_SECRET') ?: '');
if ($SECRET === '') {
    relay_out(500, ['ok' => false, 'error' => 'relay not configured']);
}

function relay_out(int $code, array $arr): void {
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    relay_out(405, ['ok' => false, 'error' => 'method not allowed']);
}

$raw = file_get_contents('php://input');
$in = json_decode((string) $raw, true);
if (!is_array($in)) {
    relay_out(400, ['ok' => false, 'error' => 'bad json']);
}
if (!hash_equals($SECRET, (string) ($in['secret'] ?? ''))) {
    relay_out(403, ['ok' => false, 'error' => 'forbidden']);
}

$host     = (string) ($in['host'] ?? 'serwer2690202.home.pl');
$port     = (int) ($in['port'] ?? 465);
$secure   = array_key_exists('secure', $in) ? (bool) $in['secure'] : ($port === 465);
$user     = (string) ($in['user'] ?? '');
$pass     = (string) ($in['pass'] ?? '');
$mailFrom = (string) ($in['mail_from'] ?? $user);
$rcpt     = $in['rcpt'] ?? [];
if (is_string($rcpt)) {
    $rcpt = array_filter(array_map('trim', explode(',', $rcpt)));
}
$rawMsg = base64_decode((string) ($in['raw_base64'] ?? ''), true);

if ($user === '' || $pass === '' || empty($rcpt) || $rawMsg === false || $rawMsg === '') {
    relay_out(422, ['ok' => false, 'error' => 'missing fields']);
}

function smtp_read($fp): string {
    $data = '';
    while (($line = fgets($fp, 600)) !== false) {
        $data .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }
    return $data;
}

function smtp_cmd($fp, ?string $cmd, $expect): string {
    if ($cmd !== null) {
        fwrite($fp, $cmd . "\r\n");
    }
    $resp = smtp_read($fp);
    $code = (int) substr($resp, 0, 3);
    $ok = is_array($expect) ? in_array($code, $expect, true) : $code === $expect;
    if (!$ok) {
        throw new RuntimeException('SMTP ' . trim($resp));
    }
    return $resp;
}

$remote = ($secure ? 'ssl://' : 'tcp://') . $host . ':' . $port;
$ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
$fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
if (!$fp) {
    relay_out(502, ['ok' => false, 'error' => "connect failed: $errstr ($errno)"]);
}
stream_set_timeout($fp, 25);

try {
    smtp_cmd($fp, null, 220);
    smtp_cmd($fp, 'EHLO stratton-relay', 250);
    if (!$secure) {
        // 587 — STARTTLS
        smtp_cmd($fp, 'STARTTLS', 220);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('STARTTLS failed');
        }
        smtp_cmd($fp, 'EHLO stratton-relay', 250);
    }
    smtp_cmd($fp, 'AUTH LOGIN', 334);
    smtp_cmd($fp, base64_encode($user), 334);
    smtp_cmd($fp, base64_encode($pass), 235);
    smtp_cmd($fp, 'MAIL FROM:<' . $mailFrom . '>', 250);
    foreach ($rcpt as $r) {
        smtp_cmd($fp, 'RCPT TO:<' . $r . '>', [250, 251]);
    }
    smtp_cmd($fp, 'DATA', 354);
    // dot-stuffing (linie zaczynające się od kropki)
    $dataMsg = preg_replace('/^\./m', '..', $rawMsg);
    fwrite($fp, $dataMsg);
    if (substr($dataMsg, -2) !== "\r\n") {
        fwrite($fp, "\r\n");
    }
    smtp_cmd($fp, '.', 250);
    @smtp_cmd($fp, 'QUIT', [221]);
    fclose($fp);
    relay_out(200, ['ok' => true]);
} catch (\Throwable $e) {
    @fclose($fp);
    relay_out(502, ['ok' => false, 'error' => $e->getMessage()]);
}
