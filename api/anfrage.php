<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const FEHLER_ALLGEMEIN = 'Das hat leider nicht geklappt. Bitte schreib mir direkt per E-Mail an a.szabo@hotmail.de oder per WhatsApp.';

function antwort(int $status, bool $ok, string $text = ''): never
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $text], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    antwort(405, false, 'Ungültige Anfrage.');
}

$cfg = konfig();
$empfaenger = (string)($cfg['empfaenger'] ?? '');
$absender   = (string)($cfg['absender'] ?? '');
if ($empfaenger === '' || $absender === '') {
    antwort(500, false, FEHLER_ALLGEMEIN);
}

// Spamschutz 1: verstecktes Feld. Bots füllen es aus, Menschen sehen es nicht.
// Bots bekommen eine scheinbar erfolgreiche Antwort, es wird aber nichts gesendet.
if (trim((string)($_POST['website'] ?? '')) !== '') {
    antwort(200, true, 'Danke!');
}

// Spamschutz 2: Formulare, die schneller als in zwei Sekunden abgeschickt werden, stammen nicht von Menschen.
$dauer = (int)($_POST['dauer'] ?? 0);
if ($dauer < 2000) {
    antwort(400, false, 'Bitte nimm dir einen Moment Zeit und sende das Formular noch einmal ab.');
}

$typ = (string)($_POST['typ'] ?? '');
$name = einzeilig((string)($_POST['name'] ?? ''), 80);
$emailRoh = einzeilig((string)($_POST['email'] ?? ''), 120);
$email = filter_var($emailRoh, FILTER_VALIDATE_EMAIL) ? $emailRoh : '';
$nachricht = bereinigen((string)($_POST['nachricht'] ?? ''), 3000);

$betreff = '';
$zeilen = [];
$replyTo = '';

switch ($typ) {
    case 'buchung':
        $kontakt = einzeilig((string)($_POST['kontakt'] ?? ''), 120);
        $angebot = einzeilig((string)($_POST['angebot'] ?? ''), 80);
        $datum   = einzeilig((string)($_POST['datum'] ?? ''), 60);
        if ($name === '' || $kontakt === '' || $angebot === '') {
            antwort(422, false, 'Bitte fülle Name, Kontakt und Angebot aus.');
        }
        $betreff = 'Terminanfrage von ' . $name;
        $zeilen = [
            'Neue Terminanfrage über die Website',
            '',
            'Name:            ' . $name,
            'Kontakt:         ' . $kontakt,
            'Angebot:         ' . $angebot,
            'Wunschdatum:     ' . ($datum !== '' ? $datum : 'offen'),
            '',
            'Nachricht:',
            $nachricht !== '' ? $nachricht : '(keine)',
        ];
        $kontaktEmail = filter_var($kontakt, FILTER_VALIDATE_EMAIL) ? $kontakt : '';
        $replyTo = $kontaktEmail;
        break;

    case 'kontakt':
        if ($name === '' || $email === '') {
            antwort(422, false, 'Bitte gib deinen Namen und eine gültige E-Mail-Adresse an.');
        }
        $betreff = 'Kontaktanfrage von ' . $name;
        $zeilen = [
            'Neue Nachricht über das Kontaktformular',
            '',
            'Name:   ' . $name,
            'E-Mail: ' . $email,
            '',
            'Nachricht:',
            $nachricht !== '' ? $nachricht : '(keine)',
        ];
        $replyTo = $email;
        break;

    case 'warteliste':
        if ($email === '') {
            antwort(422, false, 'Bitte gib eine gültige E-Mail-Adresse an.');
        }
        $betreff = 'Warteliste Online-Angebote';
        $zeilen = [
            'Neue Eintragung auf der Warteliste für die Online-Angebote',
            '',
            'E-Mail: ' . $email,
        ];
        $replyTo = $email;
        break;

    default:
        antwort(400, false, 'Ungültige Anfrage.');
}

if (!mail_limit_pruefen((int)($cfg['max_mails_pro_stunde'] ?? 20))) {
    antwort(429, false, 'Gerade sind sehr viele Anfragen eingegangen. Bitte schreib mir direkt per E-Mail an a.szabo@hotmail.de oder per WhatsApp.');
}

$kopf = [
    'From'         => 'Anima Absoluta Website <' . $absender . '>',
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=UTF-8',
];
if ($replyTo !== '') {
    $kopf['Reply-To'] = $replyTo;
}

$gesendet = mail(
    $empfaenger,
    mb_encode_mimeheader('[Anima Absoluta] ' . $betreff, 'UTF-8', 'B'),
    implode("\n", $zeilen) . "\n",
    $kopf,
    '-f' . $absender
);

if (!$gesendet) {
    antwort(500, false, FEHLER_ALLGEMEIN);
}

antwort(200, true, 'Danke! Deine Nachricht ist bei mir angekommen.');
