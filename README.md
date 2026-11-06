# Helfer – Helferplanung für Vereine in Nextcloud

Eine Nextcloud-App nach dem Vorbild von [Voluntira](https://www.voluntira.com/de): Schichten und Stände
für Vereinsfeste und Veranstaltungen planen und Helfer sich über einen geteilten Link eintragen lassen –
ganz ohne Nextcloud-Konto.

## Funktionen

**Verwaltung (in Nextcloud, App „Helfer“)**

- Beliebig viele **Umfragen** (eine pro Veranstaltung), kopierbar für wiederkehrende Feste
- **Schichten** (Beginn/Ende) und **Stände/Aufgaben**; pro Schicht und Stand die Anzahl benötigter Helfer
- **Einteilung** auf einen Blick: wer ist wo, was ist noch frei
- Helfer selbst eintragen, verschieben, austragen und **Rollen** vergeben (z. B. Schichtleitung, Kasse)
- Abfrage von E-Mail, Telefon, Gruppe/Abteilung/Verein und **eigenen Fragen** (z. B. T-Shirt-Größe)
- **Helferliste** nach Einsätzen oder Personen inkl. Stundensumme
- **Spendenlisten** anlegen und Einträge bearbeiten (Tab „Spenden“)
- Alle Schichten als Kalenderdatei (`.ics`) herunterladen
- Öffentlichen Link als **QR-Code** mit Vereinslogo in der Mitte anzeigen und als PNG herunterladen
- Oberfläche komplett mit den Nextcloud-Komponenten ([@nextcloud/vue](https://github.com/nextcloud-libraries/nextcloud-vue)),
  passt sich Design, Dark Mode und Barrierefreiheit von Nextcloud an

**Öffentlicher Link (normale Helferansicht)**

- Kopf mit Titel, **Beginn der Veranstaltung** und Ort, darunter **Information** (Veranstalter,
  Ansprechpartner mit E-Mail) und **Bemerkungen**
- **Spendenlisten** (z. B. „Kuchenliste – es werden 20 Kuchen benötigt“): Spende + Name eintragen,
  Fortschritt „x von 20“, eigene Spenden wieder austragen
- **Filter** nach Stand/Aufgabe und „nur freie Plätze“

- Läuft auf Handy und PC, kein Konto nötig
- Namen werden **direkt in die Schicht eingetragen**: bei jedem freien Platz auf „Eintragen“ klicken und Namen eingeben
- Von einem Gerät aus können **auch andere Personen mit eingetragen** werden (z. B. Familie, Freunde);
  bereits eingegebene Personen lassen sich per „Schnell eintragen“ für weitere Schichten übernehmen
- **E-Mail-Adresse ist freiwillig** – kann pro Umfrage als Pflichtfeld eingestellt werden
- Alles, was von diesem Gerät eingetragen wurde, kann bis zum Enddatum wieder ausgetragen werden –
  auch nach Neuladen oder Browser-Neustart: ein technisch notwendiges Cookie (`helfer_device`, HttpOnly,
  gültig 1 Jahr ab dem letzten Besuch) erkennt das Gerät wieder; zusätzlich merkt sich der Browser die Einträge lokal
- **Kalender-Import**: jede Schicht einzeln oder alle eigenen Schichten auf einmal als `.ics`-Datei
  (funktioniert mit Nextcloud Kalender, Google, Apple, Outlook, Thunderbird), inkl. Erinnerung 1 Stunde vorher
- Namen der Helfer optional sichtbar

**Intern teilen**

- Umfragen lassen sich im Tab „Teilen“ mit anderen Nextcloud-**Benutzern** und **Gruppen** teilen
  (die Suche beachtet die Freigabe-Einstellungen des Servers)
- Geteilte Personen können **alles bearbeiten** (Schichten, Stände, Helfer, Spenden, Einstellungen, Link)
  **außer dem Export-Pfad** – der liegt in den Dateien des Besitzers. Sie können aber jederzeit in
  ihre eigenen Dateien exportieren oder herunterladen
- Löschen der Umfrage und Teilen bleiben dem Besitzer vorbehalten
- Neue Freigaben erscheinen als Nextcloud-Benachrichtigung

**Termine**

- **Beginn** und **Enddatum der Veranstaltung**
- **Anmeldeschluss** (optional): bis dahin können sich Helfer eintragen und austragen. Ohne
  Anmeldeschluss gilt das Enddatum. Danach sind Änderungen nur noch in der Verwaltung möglich.
- **Schließungsdatum:** danach wird der öffentliche Link **geschlossen** (Seite zeigt „geschlossen“) oder
  **gelöscht** (Link nicht mehr erreichbar). Die Umfrage bleibt intern vollständig erhalten.

**Export**

- Excel-Datei (`.xlsx`) mit mehreren Tabs in **einer** Datei:
  - **Information** – Veranstaltung, Beginn, Enddatum, Schließungsdatum, Ort, Veranstalter,
    Ansprechpartner, Bemerkungen, Link sowie Summen (Plätze, Helfer, Stunden, Spenden „x von y“)
  - **Helfer** – wer geholfen hat (Name, Kontakt, Gruppe, Anzahl Einsätze, Stunden gesamt)
  - **Einsätze** – Name mit Datum, Uhrzeit von/bis, Stunden, Schicht, Stand, Rolle, Antworten
  - **Spenden** – Liste, Spende, Name, E-Mail, Kommentar
- Manuell jederzeit in einen frei wählbaren Nextcloud-Ordner oder als Download
- **Automatisch**, sobald ein Ziel-Ordner angegeben ist (Hintergrundjob):
  - **bei Änderungen** wird die Datei regelmäßig aktualisiert – nur wenn sich seit dem letzten Export etwas
    geändert hat (Eintragungen, Spenden, Bearbeitungen) und höchstens alle 15 Minuten (abschaltbar)
  - **nach dem Enddatum** wird einmal die endgültige Liste exportiert
  - die Datei wird dabei überschrieben; ältere Stände bleiben über die Nextcloud-Versionen erhalten

## Installation

Voraussetzungen: Nextcloud 31–35, PHP ≥ 8.1. Für das automatische Exportieren/Schließen müssen die
Nextcloud-Hintergrundjobs laufen (am besten per Cron).

```bash
cd /pfad/zu/nextcloud/apps        # oder custom_apps
git clone https://github.com/EJM0/Helfer-Verein.git helfer
sudo -u www-data php ../occ app:enable helfer
```

Die kompilierten JavaScript-Dateien liegen im Repository, ein Build ist für die Installation nicht nötig.
Alternativ das Archiv `helfer.tar.gz` aus einem Release nach `apps/` entpacken.

## Entwicklung

```bash
npm ci
npm run build          # oder: npm run watch
composer install       # PHP-Werkzeuge (Psalm, CS-Fixer, PHPUnit)
composer run psalm
composer run cs:check
composer run test:unit
npm run lint
make appstore          # erzeugt build/helfer.tar.gz
```

Aufbau:

- `lib/Db` – Entities und Mapper (Umfragen, Schichten, Stände, Plätze, Eintragungen)
- `lib/Service` – Logik (Datumsregeln, Kapazität, Export)
- `lib/Export/XlsxWriter.php` – schlanker XLSX-Writer ohne externe Abhängigkeiten
- `lib/BackgroundJob/LifecycleJob.php` – automatischer Export nach Enddatum, Schließen nach Schließungsdatum
- `lib/Controller` – Verwaltungs-API (`/apps/helfer/api/...`) und öffentliche Seite (`/apps/helfer/s/{token}`)
- `src/` – Vue-3-Oberfläche (`App.vue` Verwaltung, `public/PublicApp.vue` Helferansicht)

## Lizenz

AGPL-3.0-or-later
