# Changelog

Alle nennenswerten Änderungen an der Extension. Das Format folgt [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), und das Projekt nutzt [semantische Versionen](https://semver.org/lang/de/); die Hauptversion folgt der von TYPO3.

## Unreleased

### Hinzugefügt

- Komponenten unter `twc:antibot.*` mit der Priorität 30: die Schutzfelder und der Hinweis bei einer Sperre.
- `GuardFactory` und `Guard`: Schutzfelder für jedes Formular und das Urteil über eine Einsendung, aus jedem Controller aufrufbar.
- Das Site-Set `tollwerk/tw-antibot` mit den Einstellungen für Ordner, Kontaktadresse, Sperren, Methoden und Zeiten.
- Ein Adapter für EXT:form über die Events `AfterCurrentPageIsResolvedEvent` und `BeforeRenderableIsRenderedEvent`.
- Seiten mit Schutzfeldern tragen `Cache-Control: private, no-store`.
- Die Optionen `times: false`, die keine Zeiten prüft, und `session: false`, die die Session aus den Namen der Felder lässt.
- `Guard::isSubmitted()` sagt, ob der Request das Formular sendet; erst dann darf eigener Code `judge()` aufrufen.
- `Guard::keepPagePrivate()` hält eine Seite aus den Caches, die statt des Formulars nur den Hinweis zeigt.
- Die Option `secrets` nennt Felder, deren Werte eine Sperre nicht speichert; der Adapter für EXT:form nennt Passwörter selbst.
- Der Upgrade-Wizard `twAntibot_forgetBannedSubmissions` leert die Einsendungen bestehender Sperren, die Passwörter enthalten können.

### Geändert

- Für TYPO3 14 und PHP 8.5 neu aufgebaut, nach den tollwerk guidelines.
- Die Einstellungen kommen aus den Einstellungen der Site statt aus TypoScript; die Kontaktadresse fällt auf `twBase.mail.contact` zurück.
- Der Hinweis übernimmt die Klassen des Formulars, dessen Platz er einnimmt; die Einstellung `classAttribute` entfällt.
- Eine Seite mit dem Hinweis kommt wie eine Seite mit Schutzfeldern aus keinem Cache, auch wenn eine Middleware den Wächter erzeugt hat; sonst bekamen alle den Hinweis eines gesperrten Clients.
- Eine Einsendung ohne Schutzfelder und eine von einer fremden Seite werden abgelehnt, aber nicht gesperrt: Eine fremde Seite konnte Besucher sperren lassen.
- Der Adapter für EXT:form beurteilt nur Einsendungen, die EXT:form verarbeitet.
- Eine Sperre speichert nur, was das Formular unter seinem Scope gesendet hat, ohne Passwörter.
- Logs nennen weder die Adresse eines Clients noch die Session; die Meldungen der Bibliothek zum Debuggen entfallen.

### Entfernt

- Die Whitelist, die keine Prüfung mehr nutzte.
- TYPO3 10 und PHP 7.4; dafür bleiben die bisherigen Versionen.
