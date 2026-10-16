<?php
// Diese Datei nach "config.php" kopieren (im selben Ordner) und ausfüllen.
// config.php wird NICHT ins Repository übernommen und gehört nur auf den Server.
return [
    // An diese Adresse gehen alle Anfragen aus den Formularen.
    'empfaenger' => 'a.szabo@hotmail.de',

    // Absenderadresse der Formular-Mails. Muss eine Adresse auf der eigenen Domain sein,
    // am besten ein echtes Postfach (z. B. formular@animaabsoluta.de), sonst landen Mails im Spam.
    'absender' => 'formular@animaabsoluta.de',

    // Passwort-Hash für die Terminverwaltung. Erzeugen mit:
    //   php -r "echo password_hash('DEIN-PASSWORT', PASSWORD_DEFAULT), PHP_EOL;"
    'admin_passwort_hash' => '',

    // Obergrenze an Formular-Mails pro Stunde (Schutz vor Spam-Flut).
    'max_mails_pro_stunde' => 20,
];
