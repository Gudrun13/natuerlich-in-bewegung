<?php
declare(strict_types=1);

const TERMINE_DATEI = __DIR__ . '/../data/termine.json';
const RATE_DATEI    = __DIR__ . '/../private/rate.json';
const LOGIN_DATEI   = __DIR__ . '/../private/login.json';

function konfig(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $datei = __DIR__ . '/../private/config.php';
        $cfg = is_file($datei) ? (require $datei) : [];
        if (!is_array($cfg)) {
            $cfg = [];
        }
    }
    return $cfg;
}

/** Entfernt Steuerzeichen, kürzt auf $max Zeichen. Zeilenumbrüche bleiben erhalten. */
function bereinigen(string $s, int $max): string
{
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s) ?? '';
    return mb_substr(trim($s), 0, $max);
}

/** Wie bereinigen(), aber ohne Zeilenumbrüche (für Namen, Betreffzeilen, Kopfzeilen). */
function einzeilig(string $s, int $max): string
{
    $s = preg_replace('/\s+/u', ' ', $s) ?? '';
    return bereinigen($s, $max);
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Liest eine JSON-Datei mit gemeinsamem Lock. */
function json_lesen(string $datei, $standard)
{
    if (!is_file($datei)) {
        return $standard;
    }
    $fh = fopen($datei, 'rb');
    if (!$fh) {
        return $standard;
    }
    flock($fh, LOCK_SH);
    $inhalt = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    $daten = json_decode((string)$inhalt, true);
    return is_array($daten) ? $daten : $standard;
}

/** Schreibt atomar: erst in eine Temp-Datei, dann umbenennen. */
function json_schreiben(string $datei, array $daten): bool
{
    $ordner = dirname($datei);
    if (!is_dir($ordner) && !mkdir($ordner, 0755, true)) {
        return false;
    }
    $tmp = $datei . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $json = json_encode($daten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        return false;
    }
    if (!rename($tmp, $datei)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

function termine_laden(): array
{
    $liste = json_lesen(TERMINE_DATEI, []);
    $ok = [];
    foreach ($liste as $t) {
        if (is_array($t) && isset($t['id'], $t['date'], $t['title'])) {
            $ok[] = $t;
        }
    }
    usort($ok, static fn(array $a, array $b): int => strcmp((string)$a['date'], (string)$b['date']));
    return $ok;
}

function termine_speichern(array $liste): bool
{
    $liste = array_values($liste);
    usort($liste, static fn(array $a, array $b): int => strcmp((string)$a['date'], (string)$b['date']));
    return json_schreiben(TERMINE_DATEI, $liste);
}

/**
 * Zählt Formular-Mails pro Stunde (ohne personenbezogene Daten, nur eine Zahl).
 * Gibt false zurück, wenn die Obergrenze erreicht ist.
 */
function mail_limit_pruefen(int $max): bool
{
    $stunde = (int)floor(time() / 3600);
    $fh = @fopen(RATE_DATEI, 'c+');
    if (!$fh) {
        return true; // Wenn der Zähler nicht schreibbar ist, lieber Mails zulassen als Anfragen verlieren
    }
    flock($fh, LOCK_EX);
    $daten = json_decode((string)stream_get_contents($fh), true);
    if (!is_array($daten) || ($daten['stunde'] ?? 0) !== $stunde) {
        $daten = ['stunde' => $stunde, 'anzahl' => 0];
    }
    $erlaubt = $daten['anzahl'] < $max;
    if ($erlaubt) {
        $daten['anzahl']++;
    }
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($daten));
    flock($fh, LOCK_UN);
    fclose($fh);
    return $erlaubt;
}
