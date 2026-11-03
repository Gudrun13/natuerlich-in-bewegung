# Anima Absoluta – Website von Anett Szabó

Statische Website (HTML/CSS/JS) mit zwei kleinen PHP-Teilen:

- `api/anfrage.php` nimmt Kontakt-, Termin- und Wartelisten-Formulare entgegen und schickt sie per E-Mail an Anett.
- `admin/` ist die Verwaltung. Anett trägt dort ihre Termine und die Referenzen selbst ein. Sie werden in `data/termine.json` und `data/referenzen.json` gespeichert und von den Seiten geladen.

## Einrichtung auf dem Server (einmalig)

1. **Alle Dateien hochladen** (ohne `.git`). Die Ordner `private/` und `data/` müssen mit hoch.
2. **Absender-Postfach anlegen** im KAS unter E-Mail, zum Beispiel `formular@animaabsoluta.de`. Mails von einer Adresse der eigenen Domain landen deutlich seltener im Spam.
3. **Konfiguration anlegen:** `private/config.sample.php` kopieren nach `private/config.php` und ausfüllen:
   - `empfaenger`: Adresse, an die die Anfragen gehen
   - `absender`: die Adresse aus Schritt 2
   - `admin_passwort_hash`: Passwort-Hash für die Terminverwaltung, erzeugen mit
     `php -r "echo password_hash('DAS-PASSWORT', PASSWORD_DEFAULT), PHP_EOL;"`
     (das Passwort selbst steht nirgends im Klartext)
4. **HTTPS aktivieren** für animaabsoluta.de (im KAS unter Tools/SSL, Let's Encrypt) und auf PHP 8.1 oder neuer stellen.
5. **Schutz prüfen.** Diese Adressen müssen „403 Forbidden" oder „404" liefern:
   - `https://www.animaabsoluta.de/private/config.php`
   - `https://www.animaabsoluta.de/private/config.sample.php`
   - `https://www.animaabsoluta.de/data/termine.sample.json`
   - `https://www.animaabsoluta.de/data/referenzen.sample.json`
6. **Testen:** Kontaktformular absenden (kommt die Mail an?), unter `https://www.animaabsoluta.de/admin/` anmelden, einen Termin eintragen und auf der Startseite prüfen.

## Spätere Updates

Beim erneuten Hochladen **nicht überschreiben bzw. löschen**: `private/config.php`, `data/termine.json` und `data/referenzen.json`. Diese Dateien liegen nur auf dem Server (sie stehen in `.gitignore`). Sonst gehen Passwort und Termine verloren.

## Für Anett: Termine eintragen

1. `https://www.animaabsoluta.de/admin/` öffnen und mit dem Passwort anmelden.
2. Datum, Titel, Art, Uhrzeit und Ort eintragen, auf „Termin eintragen" klicken.
3. Der Termin steht sofort auf der Website. Ein falscher Eintrag lässt sich löschen und neu eintragen.

Vergangene Termine verschwinden automatisch von der Website und lassen sich im Verwaltungsbereich aufräumen.

## Referenzen pflegen

Die Seite `referenzen.html` liest ihre Einträge aus `data/referenzen.json`. Gepflegt werden sie im Verwaltungsbereich unter `https://www.animaabsoluta.de/admin/` im Reiter „Referenzen“: eintragen (Text, Name, optional „Wobei“), bearbeiten und löschen. Die neueste Referenz steht oben. Solange nichts eingetragen ist, zeigt die Seite einen freundlichen Hinweis. Bitte nur mit Einverständnis der Personen veröffentlichen.

`data/referenzen.json` liegt wie `data/termine.json` nur auf dem Server (steht in `.gitignore`) und darf beim Update nicht überschrieben werden.

## Datenschutz

Die Datenschutzerklärung (`datenschutz.html`) beschreibt die Formularverarbeitung. Wer Dienste ergänzt (Analyse, Karten, Videos, Newsletter), muss sie anpassen.
