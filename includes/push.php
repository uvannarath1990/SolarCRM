<?php
declare(strict_types=1);

// ── Base64url ─────────────────────────────────────────────────────────────────
function _b64u(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function _b64d(string $data): string {
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'));
}

// ── HKDF ──────────────────────────────────────────────────────────────────────
function _hkdf_extract(string $salt, string $ikm): string {
    return hash_hmac('sha256', $ikm, $salt, true);
}
function _hkdf_expand(string $prk, string $info, int $len): string {
    $out = '';
    $t   = '';
    for ($i = 1; strlen($out) < $len; $i++) {
        $t    = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
        $out .= $t;
    }
    return substr($out, 0, $len);
}

// ── DER ECDSA signature → raw IEEE P1363 (r‖s, 32+32 bytes) ──────────────────
function _der_to_p1363(string $der): string {
    $pos  = 2;                           // skip SEQUENCE tag + length
    $pos++;                              // r INTEGER tag
    $rLen = ord($der[$pos++]);
    $r    = substr($der, $pos, $rLen);   $pos += $rLen;
    $pos++;                              // s INTEGER tag
    $sLen = ord($der[$pos++]);
    $s    = substr($der, $pos, $sLen);
    return str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT)
         . str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
}

// ── Raw EC public key (65 bytes) → PEM SubjectPublicKeyInfo ──────────────────
function _ec_pub_to_pem(string $raw): string {
    $der = "\x30\x59"                                   // SEQUENCE (89 bytes)
         . "\x30\x13"                                   // SEQUENCE (19 bytes)
         . "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"      // OID ecPublicKey
         . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"  // OID P-256
         . "\x03\x42\x00"                               // BIT STRING, 0 unused bits
         . $raw;                                        // 04‖x‖y (65 bytes)
    return "-----BEGIN PUBLIC KEY-----\n"
         . chunk_split(base64_encode($der), 64, "\n")
         . "-----END PUBLIC KEY-----\n";
}

// ── VAPID key generation ──────────────────────────────────────────────────────
function generateVapidKeys(): array {
    $key     = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $details = openssl_pkey_get_details($key);
    $pub     = "\x04"
             . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
             . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    openssl_pkey_export($key, $pem);
    return ['publicKey' => _b64u($pub), 'privateKey' => $pem];
}

// ── VAPID JWT (ES256) ─────────────────────────────────────────────────────────
function _vapid_jwt(string $endpoint, string $subject, string $privPem): string {
    $parts   = parse_url($endpoint);
    $aud     = $parts['scheme'] . '://' . $parts['host'];
    $header  = _b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = _b64u(json_encode(['aud' => $aud, 'exp' => time() + 43200, 'sub' => $subject]));
    $input   = $header . '.' . $payload;
    openssl_sign($input, $der, openssl_pkey_get_private($privPem), OPENSSL_ALGO_SHA256);
    return $input . '.' . _b64u(_der_to_p1363($der));
}

// ── RFC 8291 (aes128gcm) content encryption ───────────────────────────────────
function _encrypt(string $plaintext, string $p256dh, string $auth): string
{
    if (!function_exists('openssl_pkey_derive')) {
        return '';   // requires PHP 8.1+
    }

    $clientPub = _b64d($p256dh);   // 65 bytes: 04‖x‖y
    $authSec   = _b64d($auth);     // 16 bytes

    // Ephemeral server EC key
    $svrKey = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $svrDet = openssl_pkey_get_details($svrKey);
    $svrPub = "\x04"
            . str_pad($svrDet['ec']['x'], 32, "\x00", STR_PAD_LEFT)
            . str_pad($svrDet['ec']['y'], 32, "\x00", STR_PAD_LEFT);

    // ECDH
    $shared = openssl_pkey_derive(
        openssl_pkey_get_public(_ec_pub_to_pem($clientPub)),
        $svrKey,
        32
    );
    if ($shared === false) return '';

    // Key derivation (RFC 8291)
    $ikm  = _hkdf_expand(_hkdf_extract($authSec, $shared), "WebPush: info\x00" . $clientPub . $svrPub, 32);
    $salt = random_bytes(16);
    $prk  = _hkdf_extract($salt, $ikm);
    $cek  = _hkdf_expand($prk, "Content-Encoding: aes128gcm\x00", 16);
    $iv   = _hkdf_expand($prk, "Content-Encoding: nonce\x00", 12);

    // AES-128-GCM encrypt (append \x02 record delimiter per spec)
    $tag = '';
    $ct  = openssl_encrypt($plaintext . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($ct === false) return '';

    // Build RFC 8291 §2.1 content: salt(16) + rs(4) + idlen(1) + serverKey(65) + ciphertext+tag
    return $salt . pack('N', 4096) . chr(65) . $svrPub . $ct . $tag;
}

// ── HTTP push delivery ────────────────────────────────────────────────────────
function _push_http(string $endpoint, string $body, string $jwt, string $vapidPub): bool
{
    $headers = [
        'Authorization: vapid t=' . $jwt . ',k=' . $vapidPub,
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'TTL: 86400',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }
    return false;
}

// ── Internal dispatcher ───────────────────────────────────────────────────────
function _push_ready(): bool
{
    return getSetting('vapid_public_key') !== ''
        && getSetting('vapid_private_key') !== ''
        && function_exists('openssl_pkey_derive');
}

function _dispatch_push(array $subs, string $title, string $body, string $url): void
{
    if (!_push_ready() || empty($subs)) return;

    $vapidPub  = getSetting('vapid_public_key');
    $vapidPriv = getSetting('vapid_private_key');
    $subject   = getSetting('vapid_subject', 'mailto:admin@kresolar.in');
    $stale     = [];

    foreach ($subs as $sub) {
        $ct = _encrypt(json_encode(['title' => $title, 'body' => $body, 'url' => $url]),
                       $sub['p256dh'], $sub['auth']);
        if ($ct === '') continue;
        $jwt = _vapid_jwt($sub['endpoint'], $subject, $vapidPriv);
        if (!_push_http($sub['endpoint'], $ct, $jwt, $vapidPub)) {
            $stale[] = $sub['endpoint'];
        }
    }

    if (!empty($stale)) {
        $pl = implode(',', array_fill(0, count($stale), '?'));
        getPdo()->prepare("DELETE FROM `push_subscriptions` WHERE `endpoint` IN ({$pl})")
                ->execute($stale);
    }
}

// ── Public API ────────────────────────────────────────────────────────────────
function sendPushToUser(int $userId, string $title, string $body, string $url = '/employee/today'): void
{
    if (!_push_ready()) return;
    $s = getPdo()->prepare('SELECT `endpoint`,`p256dh`,`auth` FROM `push_subscriptions` WHERE `user_id`=?');
    $s->execute([$userId]);
    _dispatch_push($s->fetchAll(), $title, $body, $url);
}

function sendPushToRole(string $role, string $title, string $body, string $url = '/employee/today'): void
{
    if (!_push_ready()) return;
    $s = getPdo()->prepare(
        'SELECT ps.`endpoint`,ps.`p256dh`,ps.`auth`
         FROM `push_subscriptions` ps
         JOIN `users` u ON u.`id`=ps.`user_id`
         WHERE u.`role`=? AND u.`is_active`=1'
    );
    $s->execute([$role]);
    _dispatch_push($s->fetchAll(), $title, $body, $url);
}

function sendPushToUsers(array $userIds, string $title, string $body, string $url = '/employee/today'): void
{
    if (!_push_ready() || empty($userIds)) return;
    $ids = array_values(array_unique(array_map('intval', array_filter($userIds))));
    if (empty($ids)) return;
    $pl = implode(',', array_fill(0, count($ids), '?'));
    $s  = getPdo()->prepare("SELECT `endpoint`,`p256dh`,`auth` FROM `push_subscriptions` WHERE `user_id` IN ({$pl})");
    $s->execute($ids);
    _dispatch_push($s->fetchAll(), $title, $body, $url);
}
