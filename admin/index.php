<?php
declare(strict_types=1);

require __DIR__ . '/../api/lib.php';

$cfg = konfig();
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: same-origin');

session_name('aa_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/admin/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

const ARTEN = ['Workshop', 'Meditation', 'Yoga', 'Pilates', 'Faszientraining', 'Atemarbeit', 'Retreat', 'Event', 'Sonstiges'];
const TAGE = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
const MONATE = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

function datum_lang(string $iso): string
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
    if (!$d) {
        return $iso;
    }
    return TAGE[(int)$d->format('w')] . ', ' . $d->format('j') . '. ' . MONATE[(int)$d->format('n') - 1] . ' ' . $d->format('Y');
}

function meldung_setzen(string $art, string $text): void
{
    $_SESSION['meldung'] = ['art' => $art, 'text' => $text];
}

function umleiten(): never
{
    header('Location: ./', true, 303);
    exit;
}

/* ---------- Anmeldung: Sperre bei zu vielen Fehlversuchen ---------- */

function login_gesperrt(): int
{
    $d = json_lesen(LOGIN_DATEI, []);
    $bis = (int)($d['gesperrt_bis'] ?? 0);
    return $bis > time() ? $bis - time() : 0;
}

function login_fehlschlag(): void
{
    $d = json_lesen(LOGIN_DATEI, []);
    $jetzt = time();
    if (($d['seit'] ?? 0) < $jetzt - 900) {
        $d = ['seit' => $jetzt, 'fehler' => 0];
    }
    $d['fehler'] = ((int)($d['fehler'] ?? 0)) + 1;
    if ($d['fehler'] >= 5) {
        $d['gesperrt_bis'] = $jetzt + 900;
        $d['fehler'] = 0;
        $d['seit'] = $jetzt;
    }
    json_schreiben(LOGIN_DATEI, $d);
}

function login_erfolg(): void
{
    if (is_file(LOGIN_DATEI)) {
        @unlink(LOGIN_DATEI);
    }
}

$hash = (string)($cfg['admin_passwort_hash'] ?? '');
$eingerichtet = $hash !== '';
$angemeldet = !empty($_SESSION['ok']);

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string)$_SESSION['csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aktion = (string)($_POST['aktion'] ?? '');

    if ($aktion === 'login' && $eingerichtet && !$angemeldet) {
        $rest = login_gesperrt();
        if ($rest > 0) {
            meldung_setzen('fehler', 'Zu viele Versuche. Bitte warte ' . (int)ceil($rest / 60) . ' Minuten und versuche es dann erneut.');
            umleiten();
        }
        usleep(400000);
        if (hash_equals($csrf, (string)($_POST['csrf'] ?? '')) && password_verify((string)($_POST['passwort'] ?? ''), $hash)) {
            session_regenerate_id(true);
            $_SESSION['ok'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            login_erfolg();
        } else {
            login_fehlschlag();
            meldung_setzen('fehler', 'Das Passwort stimmt nicht.');
        }
        umleiten();
    }

    if ($angemeldet) {
        if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
            meldung_setzen('fehler', 'Die Sitzung ist abgelaufen. Bitte versuche es noch einmal.');
            umleiten();
        }

        if ($aktion === 'logout') {
            $_SESSION = [];
            session_destroy();
            umleiten();
        }

        if ($aktion === 'hinzufuegen') {
            $datum = (string)($_POST['datum'] ?? '');
            $titel = einzeilig((string)($_POST['titel'] ?? ''), 100);
            $art   = (string)($_POST['art'] ?? '');
            $zeit  = einzeilig((string)($_POST['uhrzeit'] ?? ''), 30);
            $ort   = einzeilig((string)($_POST['ort'] ?? ''), 80);

            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $datum);
            if (!$d || $d->format('Y-m-d') !== $datum) {
                meldung_setzen('fehler', 'Bitte wähle ein gültiges Datum.');
            } elseif ($titel === '') {
                meldung_setzen('fehler', 'Bitte gib dem Termin einen Titel.');
            } elseif (!in_array($art, ARTEN, true)) {
                meldung_setzen('fehler', 'Bitte wähle eine Art aus.');
            } else {
                $liste = termine_laden();
                $liste[] = [
                    'id'    => bin2hex(random_bytes(6)),
                    'date'  => $datum,
                    'type'  => $art,
                    'title' => $titel,
                    'time'  => $zeit,
                    'place' => $ort,
                ];
                if (termine_speichern($liste)) {
                    meldung_setzen('ok', 'Der Termin „' . $titel . '“ ist eingetragen und steht jetzt auf der Website.');
                } else {
                    meldung_setzen('fehler', 'Das Speichern hat leider nicht geklappt. Bitte melde dich bei deiner Webdesignerin.');
                }
            }
            umleiten();
        }

        if ($aktion === 'loeschen') {
            $id = (string)($_POST['id'] ?? '');
            $liste = termine_laden();
            $neu = array_values(array_filter($liste, static fn(array $t): bool => (string)$t['id'] !== $id));
            if (count($neu) === count($liste)) {
                meldung_setzen('fehler', 'Dieser Termin wurde nicht gefunden (vielleicht ist er schon gelöscht).');
            } elseif (termine_speichern($neu)) {
                meldung_setzen('ok', 'Der Termin wurde gelöscht.');
            } else {
                meldung_setzen('fehler', 'Das Löschen hat leider nicht geklappt.');
            }
            umleiten();
        }

        if ($aktion === 'vergangene_loeschen') {
            $heute = date('Y-m-d');
            $liste = termine_laden();
            $neu = array_values(array_filter($liste, static fn(array $t): bool => (string)$t['date'] >= $heute));
            if (termine_speichern($neu)) {
                $n = count($liste) - count($neu);
                meldung_setzen('ok', $n === 1 ? '1 vergangener Termin wurde gelöscht.' : $n . ' vergangene Termine wurden gelöscht.');
            } else {
                meldung_setzen('fehler', 'Das Aufräumen hat leider nicht geklappt.');
            }
            umleiten();
        }
    }
    umleiten();
}

$meldung = $_SESSION['meldung'] ?? null;
unset($_SESSION['meldung']);

$heute = date('Y-m-d');
$alle = $angemeldet ? termine_laden() : [];
$kommende = array_values(array_filter($alle, static fn(array $t): bool => (string)$t['date'] >= $heute));
$vergangene = array_values(array_filter($alle, static fn(array $t): bool => (string)$t['date'] < $heute));
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Termine verwalten</title>
<style>
  :root { --bg:#F5F1E8; --text:#4F4A52; --muted:#7B7480; --primary:#A996B8; --secondary:#AEBDA3; --accent:#BFA06A; --border:#DDD4C8; --card:#FFFFFF; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Georgia, 'Times New Roman', serif; background: var(--bg); color: var(--text); font-size: 17px; line-height: 1.6; }
  .wrap { max-width: 720px; margin: 0 auto; padding: 1.5rem 1.2rem 4rem; }
  header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
  h1 { font-size: 1.7rem; font-weight: normal; }
  h2 { font-size: 1.2rem; font-weight: normal; margin-bottom: 1rem; }
  .card { background: var(--card); border: 1px solid var(--border); border-radius: 3px; padding: 1.4rem; margin-bottom: 1.5rem; }
  label { display: block; font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 0.8rem; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted); margin: 1rem 0 0.35rem; }
  label:first-of-type { margin-top: 0; }
  input, select { width: 100%; padding: 12px 14px; font-size: 1rem; font-family: inherit; color: var(--text); background: #fff; border: 1px solid var(--border); border-radius: 3px; }
  input:focus, select:focus { outline: 2px solid var(--primary); outline-offset: 1px; }
  .row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  @media (max-width: 560px) { .row { grid-template-columns: 1fr; } }
  button, .btn { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 0.9rem; letter-spacing: 0.06em; text-transform: uppercase; background: var(--primary); color: #fff; border: none; padding: 14px 22px; border-radius: 3px; cursor: pointer; text-decoration: none; display: inline-block; }
  button:hover, .btn:hover { background: #967fa8; }
  button.full { width: 100%; margin-top: 1.4rem; }
  button.leise { background: transparent; color: var(--muted); border: 1px solid var(--border); padding: 10px 16px; }
  button.leise:hover { border-color: var(--primary); color: var(--primary); background: transparent; }
  button.loeschen { background: transparent; color: #a24d4d; border: 1px solid #e3c4c4; padding: 8px 14px; font-size: 0.78rem; }
  button.loeschen:hover { background: #a24d4d; color: #fff; border-color: #a24d4d; }
  .meldung { padding: 1rem 1.2rem; border-radius: 3px; margin-bottom: 1.5rem; font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 0.95rem; }
  .meldung.ok { background: rgba(174,189,163,0.25); border: 1px solid rgba(174,189,163,0.6); color: #44603a; }
  .meldung.fehler { background: #f8e8e8; border: 1px solid #e3c4c4; color: #8a3a3a; }
  .termin { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid var(--border); }
  .termin:last-child { border-bottom: none; }
  .termin .art { display: inline-block; font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 0.7rem; letter-spacing: 0.08em; text-transform: uppercase; background: rgba(191,160,106,0.15); color: #9a7840; padding: 2px 8px; border-radius: 2px; margin-bottom: 0.2rem; }
  .termin .datum { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 0.85rem; color: var(--muted); }
  .termin .titel { font-size: 1.05rem; }
  .leer { font-family: 'Helvetica Neue', Arial, sans-serif; color: var(--muted); }
  .hinweis { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 0.85rem; color: var(--muted); margin-top: 0.6rem; }
  details summary { cursor: pointer; font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 0.85rem; color: var(--muted); padding: 0.3rem 0; }
  .zeile-links { display: flex; gap: 0.8rem; align-items: center; flex-wrap: wrap; }
  a { color: var(--primary); }
</style>
</head>
<body>
<div class="wrap">

<?php if (!$eingerichtet): ?>
  <h1>Terminverwaltung</h1>
  <div class="card" style="margin-top:1.5rem">
    <p>Die Terminverwaltung ist noch nicht eingerichtet. Bitte melde dich bei deiner Webdesignerin.</p>
  </div>

<?php elseif (!$angemeldet): ?>
  <h1>Termine verwalten</h1>
  <?php if ($meldung): ?><div class="meldung <?= h($meldung['art']) ?>" style="margin-top:1.2rem"><?= h($meldung['text']) ?></div><?php endif; ?>
  <form method="post" class="card" style="margin-top:1.5rem" autocomplete="off">
    <input type="hidden" name="aktion" value="login">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <label for="passwort">Passwort</label>
    <input type="password" id="passwort" name="passwort" required autofocus autocomplete="current-password">
    <button type="submit" class="full">Anmelden</button>
  </form>

<?php else: ?>
  <header>
    <h1>Termine verwalten</h1>
    <div class="zeile-links">
      <a href="/#termine" target="_blank" rel="noopener">Website ansehen</a>
      <form method="post">
        <input type="hidden" name="aktion" value="logout">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="leise">Abmelden</button>
      </form>
    </div>
  </header>

  <?php if ($meldung): ?><div class="meldung <?= h($meldung['art']) ?>"><?= h($meldung['text']) ?></div><?php endif; ?>

  <form method="post" class="card">
    <h2>Neuen Termin eintragen</h2>
    <input type="hidden" name="aktion" value="hinzufuegen">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

    <label for="datum">Datum</label>
    <input type="date" id="datum" name="datum" required min="<?= h($heute) ?>">

    <label for="titel">Titel</label>
    <input type="text" id="titel" name="titel" maxlength="100" required placeholder="z. B. Abendmeditation – Stille finden">

    <div class="row">
      <div>
        <label for="art">Art</label>
        <select id="art" name="art" required>
          <?php foreach (ARTEN as $a): ?><option><?= h($a) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="uhrzeit">Uhrzeit</label>
        <input type="text" id="uhrzeit" name="uhrzeit" maxlength="30" placeholder="z. B. 19:00 oder ganztags">
      </div>
    </div>

    <label for="ort">Ort</label>
    <input type="text" id="ort" name="ort" maxlength="80" placeholder="z. B. Leipzig, Praxisraum">

    <button type="submit" class="full">Termin eintragen</button>
    <p class="hinweis">Der Termin erscheint sofort auf der Website. Falls ein Eintrag falsch ist: löschen und neu eintragen.</p>
  </form>

  <div class="card">
    <h2>Kommende Termine (<?= count($kommende) ?>)</h2>
    <?php if (!$kommende): ?>
      <p class="leer">Noch keine Termine eingetragen. Auf der Website steht dann „Neue Termine folgen bald."</p>
    <?php endif; ?>
    <?php foreach ($kommende as $t): ?>
      <div class="termin">
        <div>
          <span class="art"><?= h((string)($t['type'] ?? '')) ?></span>
          <div class="titel"><?= h((string)$t['title']) ?></div>
          <div class="datum"><?= h(datum_lang((string)$t['date'])) ?><?= !empty($t['time']) ? ' · ' . h((string)$t['time']) : '' ?><?= !empty($t['place']) ? ' · ' . h((string)$t['place']) : '' ?></div>
        </div>
        <form method="post" onsubmit="return confirm('Diesen Termin wirklich löschen?');">
          <input type="hidden" name="aktion" value="loeschen">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="id" value="<?= h((string)$t['id']) ?>">
          <button type="submit" class="loeschen">Löschen</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($vergangene): ?>
  <div class="card">
    <details>
      <summary>Vergangene Termine (<?= count($vergangene) ?>)</summary>
      <p class="hinweis" style="margin-bottom:0.8rem">Vergangene Termine sind auf der Website nicht mehr sichtbar. Du kannst sie hier aufräumen.</p>
      <?php foreach (array_reverse($vergangene) as $t): ?>
        <div class="termin">
          <div>
            <div class="titel"><?= h((string)$t['title']) ?></div>
            <div class="datum"><?= h(datum_lang((string)$t['date'])) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
      <form method="post" onsubmit="return confirm('Alle vergangenen Termine löschen?');" style="margin-top:1rem">
        <input type="hidden" name="aktion" value="vergangene_loeschen">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="leise">Alle vergangenen Termine löschen</button>
      </form>
    </details>
  </div>
  <?php endif; ?>
<?php endif; ?>

</div>
</body>
</html>
