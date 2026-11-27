# Changelog

Alle nennenswerten Änderungen an der Extension. Das Format folgt [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), und das Projekt nutzt [semantische Versionen](https://semver.org/lang/de/); die Hauptversion folgt der von TYPO3.

## Unreleased

### Hinzugefügt

- Komponenten unter `twc:antibot.*` mit der Priorität 30: die Schutzfelder und der Hinweis bei einer Sperre.
- `GuardFactory` und `Guard`: Schutzfelder für jedes Formular und das Urteil über eine Einsendung, aus jedem Controller aufrufbar.
- Das Site-Set `tollwerk/tw-antibot` mit den Einstellungen für Ordner, Kontaktadresse, Sperren, Methoden und Zeiten.
- Ein Adapter für EXT:form über die Events `AfterCurrentPageIsResolvedEvent` und `BeforeRenderableIsRenderedEvent`.
- Seiten mit Schutzfeldern tragen `Cache-Control: private, no-store`.

### Geändert

- Für TYPO3 14 und PHP 8.5 neu aufgebaut, nach den tollwerk guidelines.
- Die Einstellungen kommen aus den Einstellungen der Site statt aus TypoScript; die Kontaktadresse fällt auf `twBase.mail.contact` zurück.
- Der Hinweis übernimmt die Klassen des Formulars, dessen Platz er einnimmt; die Einstellung `classAttribute` entfällt.

### Entfernt

- Die Whitelist, die keine Prüfung mehr nutzte.
- TYPO3 10 und PHP 7.4; dafür bleiben die bisherigen Versionen.
