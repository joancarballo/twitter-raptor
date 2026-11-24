<?php
/**
 * Bot diario para X (Twitter). Sin dependencias: PHP 7.4+ con cURL.
 *
 * Uso:
 *   php bot.php                     -> modo cron: publica solo si es la hora configurada
 *   php bot.php --cron              -> muestra la línea de cron que debes poner en SiteGround
 *   php bot.php --dry-run           -> muestra el mensaje de hoy sin publicar
 *   php bot.php --dry-run --date="2026-12-31 23:59"   -> simula otra fecha
 *   php bot.php --force             -> publica ahora mismo (para probar)
 *
 * Tiempos de ejecución (para que el tuit no salga ya en el día siguiente):
 *   - El cron del servidor suele arrancar en los primeros segundos del minuto programado
 *     (en hosting compartido puede retrasarse algo si el servidor va cargado).
 *   - Arrancar PHP y generar el mensaje: < 0,1 s.
 *   - Llamada a la API de X: normalmente 0,5-2 s.
 *   => Lo normal es que el tuit esté publicado unos 2-7 s después de la hora del cron.
 *      Con 23:59 sobran ~50 s de margen.
 *   - Si X falla se reintenta (hasta 3 intentos, 15 s de timeout cada uno y 5 s de pausa),
 *     pero los reintentos se cortan solos antes de las 00:00: nunca se publica
 *     un mensaje de ayer pasada la medianoche. Si quieres margen para reintentos,
 *     programa a las 23:58 o antes.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Falta config.php\n");
    exit(1);
}
$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'Europe/Madrid');

// Claves de la API: archivo .env (o variables de entorno del sistema)
$env = loadEnv(__DIR__ . '/.env');
foreach ([
    'api_key'             => 'X_API_KEY',
    'api_secret'          => 'X_API_SECRET',
    'access_token'        => 'X_ACCESS_TOKEN',
    'access_token_secret' => 'X_ACCESS_TOKEN_SECRET',
] as $key => $var) {
    $config[$key] = $env[$var] ?? (getenv($var) ?: '');
}

$opts  = getopt('', ['dry-run', 'force', 'cron', 'date:']);
$dry   = isset($opts['dry-run']);
$force = isset($opts['force']);
$now   = new DateTimeImmutable($opts['date'] ?? 'now');

// Hora de publicación "HH:MM" en la zona horaria configurada
if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', (string) ($config['hora_publicacion'] ?? ''), $hm)) {
    fwrite(STDERR, "hora_publicacion debe tener formato HH:MM (p. ej. '23:59')\n");
    exit(1);
}
$targetMin = (int) $hm[1] * 60 + (int) $hm[2];

if (isset($opts['cron'])) {
    // El servidor puede estar en otra zona horaria (SiteGround suele usar UTC) y hay
    // cambio de hora verano/invierno: por eso el cron se lanza CADA HORA en el minuto
    // indicado y el propio script decide si es la hora local correcta.
    echo "{$hm[2]} * * * * php " . __FILE__ . "\n";
    exit(0);
}

$stateFile = __DIR__ . '/last_posted.txt';
$today     = $now->format('Y-m-d');
$midnight  = $now->setTime(0, 0)->modify('+1 day')->getTimestamp();

if (!$dry && !$force) {
    $nowMin = (int) $now->format('G') * 60 + (int) $now->format('i');
    // Se admite hasta 5 min de retraso del cron, siempre dentro del mismo día
    if ($nowMin < $targetMin || $nowMin >= $targetMin + 5) {
        exit(0); // no es la hora: el cron se lanza cada hora y solo actuamos una vez
    }
    if (is_file($stateFile) && trim((string) file_get_contents($stateFile)) === $today) {
        logMsg('Ya se publicó hoy, no se repite.');
        exit(0);
    }
}

$message = buildMessage($now, $config);
$length  = tweetLength($message);

if ($dry) {
    echo $message, "\n\n[{$length}/280 caracteres]\n";
    exit($length > 280 ? 1 : 0);
}
if ($length > 280) {
    logMsg("ERROR: mensaje demasiado largo ({$length}): {$message}");
    exit(1);
}
foreach (['api_key', 'api_secret', 'access_token', 'access_token_secret'] as $key) {
    if ($config[$key] === '') {
        logMsg('ERROR: faltan claves de la API en .env (ver .env.example).');
        exit(1);
    }
}

// Hasta 3 intentos por si la API falla puntualmente
for ($i = 1; $i <= 3; $i++) {
    $timeout = 15;
    if (!$force) {
        // No dejar que una petición termine después de medianoche (2 s de margen)
        $timeout = min($timeout, $midnight - time() - 2);
        if ($timeout < 3) {
            logMsg('Sin tiempo antes de medianoche: no se publica para no cambiar de día.');
            exit(1);
        }
    }
    [$code, $body] = postTweet($message, $config, $timeout);
    if ($code === 201) {
        $id = json_decode($body, true)['data']['id'] ?? '?';
        file_put_contents($stateFile, $today);
        $secs = round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 1);
        logMsg("OK en {$secs} s (id {$id}): {$message}");
        exit(0);
    }
    logMsg("Intento {$i} fallido (HTTP {$code}): {$body}");
    if ($code >= 400 && $code < 500 && $code !== 429) {
        break; // error de credenciales/permisos/contenido: no tiene sentido reintentar
    }
    sleep(5);
}
exit(1);

// ---------------------------------------------------------------------------

function buildMessage(DateTimeImmutable $d, array $c): string
{
    // Prioridad: fecha fija (MM-DD) > festivo móvil (días respecto al Domingo de Pascua) > plantillas
    $md     = $d->format('m-d');
    $offset = (int) easterSunday((int) $d->format('Y'))->diff($d->setTime(0, 0))->format('%r%a');
    $pascua = $c['especiales_pascua'] ?? [];
    $key    = ($offset > 0 ? '+' : '') . $offset; // '-2', '+1', '0'...

    if (!empty($c['especiales'][$md])) {
        $choice = $c['especiales'][$md];
    } elseif (!empty($pascua[$key]) || !empty($pascua[(string) $offset])) {
        $choice = $pascua[$key] ?? $pascua[(string) $offset];
    } else {
        $choice = $c['plantillas'];
    }
    // Un especial puede ser un texto o una lista de textos (se elige uno al azar)
    $template = is_array($choice) ? $choice[array_rand($choice)] : $choice;
    return renderTemplate($template, $d, $c);
}

/** Domingo de Pascua (calendario gregoriano, algoritmo de Meeus). Sin depender de ext-calendar. */
function easterSunday(int $y): DateTimeImmutable
{
    $a = $y % 19;
    $b = intdiv($y, 100);
    $c = $y % 100;
    $d = intdiv($b, 4);
    $e = $b % 4;
    $f = intdiv($b + 8, 25);
    $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $month = intdiv($h + $l - 7 * $m + 114, 31);
    $day   = (($h + $l - 7 * $m + 114) % 31) + 1;
    return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $month, $day));
}

function renderTemplate(string $tpl, DateTimeImmutable $d, array $c): string
{
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
              'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $dias  = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    $mes       = $meses[(int) $d->format('n') - 1];
    $diaSemana = $dias[(int) $d->format('w')];
    $finAnio   = new DateTimeImmutable($d->format('Y-12-31'));
    $hoy       = $d->setTime(0, 0);

    // El contador se reinicia cada año en la fecha 'inicio_anual' (MM-DD).
    // Si ese día aún no ha llegado este año, se cuenta desde el del año anterior.
    $inicio = new DateTimeImmutable($d->format('Y') . '-' . ($c['inicio_anual'] ?? '01-01'));
    if ($inicio > $hoy) {
        $inicio = $inicio->modify('-1 year');
    }

    $vars = [
        'dia'            => $d->format('j'),
        'mes'            => $mes,
        'Mes'            => ucfirstUtf8($mes),
        'anio'           => $d->format('Y'),
        'dia_semana'     => $diaSemana,
        'Dia_semana'     => ucfirstUtf8($diaSemana),
        'fecha'          => $d->format('j') . ' de ' . $mes,
        'fecha_corta'    => $d->format('d/m/Y'),
        'hora'           => $d->format('H:i'),
        'contador'       => (string) ($inicio->diff($hoy)->days + 1),
        'dia_del_anio'   => (string) ((int) $d->format('z') + 1),
        'dias_restantes' => (string) $hoy->diff($finAnio)->days,
        'semana'         => (string) (int) $d->format('W'),
        'luna'           => moonPhase($d),
        'estacion'       => season($d),
    ];

    // {nombre} o {funcion:argumento}
    return preg_replace_callback('/\{(\w+)(?::([^}]*))?\}/u', function ($m) use ($vars, $hoy) {
        $name = $m[1];
        $arg  = $m[2] ?? null;
        if ($name === 'azar' && $arg !== null) {          // {azar:a|b|c}
            $opts = explode('|', $arg);
            return $opts[array_rand($opts)];
        }
        if ($name === 'cuenta_atras' && $arg !== null) {  // {cuenta_atras:2026-12-25}
            $target = new DateTimeImmutable($arg);
            $diff = $hoy->diff($target);
            return (string) ($diff->invert ? 0 : $diff->days);
        }
        return $vars[$name] ?? $m[0]; // variable desconocida: se deja tal cual
    }, $tpl);
}

function ucfirstUtf8(string $s): string
{
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
}

function moonPhase(DateTimeImmutable $d): string
{
    $synodic = 29.530588853;
    $knownNewMoon = 947182440; // 2000-01-06 18:14 UTC
    $age = fmod(($d->getTimestamp() - $knownNewMoon) / 86400, $synodic);
    if ($age < 0) {
        $age += $synodic;
    }
    $phases = ['🌑', '🌒', '🌓', '🌔', '🌕', '🌖', '🌗', '🌘'];
    return $phases[(int) floor(($age / $synodic) * 8 + 0.5) % 8];
}

function season(DateTimeImmutable $d): string
{
    $md = (int) $d->format('md');
    if ($md >= 321 && $md < 621)  return 'primavera';
    if ($md >= 621 && $md < 923)  return 'verano';
    if ($md >= 923 && $md < 1221) return 'otoño';
    return 'invierno';
}

/** Aproximación del contador de X: los emojis y caracteres CJK cuentan doble. */
function tweetLength(string $text): int
{
    $len = 0;
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        $len += (mb_ord($ch) > 0x10FF) ? 2 : 1;
    }
    return $len;
}

function postTweet(string $text, array $c, int $timeout = 15): array
{
    $url = 'https://api.x.com/2/tweets';
    $oauth = [
        'oauth_consumer_key'     => $c['api_key'],
        'oauth_nonce'            => bin2hex(random_bytes(16)),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp'        => (string) time(),
        'oauth_token'            => $c['access_token'],
        'oauth_version'          => '1.0',
    ];
    // Con cuerpo JSON, la firma OAuth 1.0a solo incluye los parámetros oauth_*
    $params = $oauth;
    ksort($params);
    $pairs = [];
    foreach ($params as $k => $v) {
        $pairs[] = rawurlencode($k) . '=' . rawurlencode($v);
    }
    $base = 'POST&' . rawurlencode($url) . '&' . rawurlencode(implode('&', $pairs));
    $key  = rawurlencode($c['api_secret']) . '&' . rawurlencode($c['access_token_secret']);
    $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $base, $key, true));

    $header = [];
    foreach ($oauth as $k => $v) {
        $header[] = rawurlencode($k) . '="' . rawurlencode($v) . '"';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['text' => $text], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Authorization: OAuth ' . implode(', ', $header),
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) {
        $body = 'cURL: ' . curl_error($ch);
    }
    curl_close($ch);
    return [$code, (string) $body];
}

/** Lee un archivo .env sencillo: líneas CLAVE=valor, comentarios con #, comillas opcionales. */
function loadEnv(string $file): array
{
    $vars = [];
    if (!is_file($file)) {
        return $vars;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        }
        $vars[$name] = $value;
    }
    return $vars;
}

function logMsg(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . "] {$msg}\n";
    file_put_contents(__DIR__ . '/bot.log', $line, FILE_APPEND);
    echo $line;
}
