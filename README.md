# tw_antibot

Spamschutz ohne CAPTCHA für TYPO3 14. Die Extension entscheidet **auf dem Server**, ob eine Einsendung von einem Menschen oder einer Maschine kommt. Sie verlangt von niemandem einen Beweis: kein Rätsel, keine Bilder mit Ampeln, keine Rechenarbeit im Hintergrund. Jede Abfrage dieser Art lädt das Spamproblem des Betreibers bei den Menschen ab, für die das Formular da ist, und keine ist vollständig barrierefrei. Was die Prüfungen durchlassen, trägt der Betreiber, nicht die Person vor dem Formular.

Die Prüfungen macht [jkphl/antibot](https://packagist.org/packages/jkphl/antibot), das TYPO3 nicht kennt. `tw_antibot` verbindet es mit TYPO3: Schutzfelder als Komponente, Sperren als Datensätze, Stopwords im Backend und ein Adapter für EXT:form.

> Entwickelt wird auf [code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot](https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot). Das Repository auf GitHub ist ein Spiegel, aus dem Packagist liest.

## Installation

```sh
composer require tollwerk/tw-antibot
```

`tw_antibot` setzt `tw_base` voraus, für die Komponenten. Danach:

1. **Das Site-Set** `tollwerk/tw-antibot` als Abhängigkeit der Site eintragen.
2. **Einen Ordner** für die Sperren anlegen und in der Einstellung `twAntibot.storage` wählen.
3. **Die Tabellen** mit dem Schema-Update anlegen.

## Ablauf

Um ein geschütztes Formular passiert dreierlei, und nichts davon sieht die Person, die es ausfüllt.

- **Das Formular bekommt Schutzfelder.** Ein verborgenes Feld trägt eine Signatur, ein zweites die Sekunde, in der das Formular ausgeliefert wurde; dazu kommen Honeypots, die kein Browser ausfüllt. Die Namen der Felder hängen von der Session der Person ab, vom Formular, vom Schlüssel der Installation und von den Prüfungen des Formulars. Felder aus einem Formular sind in einer anderen Session, einem anderen Formular und nach einer Änderung der Prüfungen wertlos.
- **Eine Einsendung wird beurteilt, bevor das Formular sie verarbeitet.** Eine Einsendung ohne Schutzfelder gilt als abgelehnt: Es gab nichts zu prüfen, und sie durchzulassen ließe jede Maschine durch, die die Felder weglässt.
- **Eine abgelehnte Einsendung bekommt einen Hinweis an Stelle des Formulars.** Will das Formular es so, wird die Adresse des Clients gesperrt, die Einsendung aufgezeichnet, und jeder weitere Aufruf zeigt den Hinweis, bis die Sperre endet oder jemand den Datensatz löscht.

Es zählt nur eine Session, die die Person mitbringt. Für einen anonymen Besuch beginnt TYPO3 in jedem Request eine neue; ohne Session hängen die Namen vom Rest ab, und die Prüfungen von Zeit, Honeypots und Signatur gelten weiter.

## Einstellungen der Site

| Einstellung | Vorgabe | Bedeutung |
| --- | --- | --- |
| `twAntibot.storage` | `0` | Ordner für die Sperren |
| `twAntibot.contact` | leer | Mailadresse im Hinweis; leer für `twBase.mail.contact`, ohne beide fehlt der Absatz dazu |
| `twAntibot.ban` | `true` | ob eine Ablehnung den Client sperrt |
| `twAntibot.banPeriod` | `2592000` | Sekunden, die eine Sperre dauert; `0` bis jemand sie aufhebt |
| `twAntibot.methods` | `GET-POST` | Methoden, mit denen ein Formular geladen und gesendet wird; leer für jede |
| `twAntibot.duplicateLifetime` | `86400` | Sekunden, die der Inhalt einer angenommenen Einsendung im Gedächtnis bleibt |
| `twAntibot.time.minimum` | `3` | Sekunden, die ein Formular offen sein muss, bevor es gesendet werden darf |
| `twAntibot.time.maximum` | `3600` | Sekunden, die ein Formular gültig bleibt |
| `twAntibot.time.followup` | `1` | Sekunden bis zum nächsten Senden nach einem Fehler |

## Ein Formular von EXT:form schützen

Kein Formular ist geschützt, bis es danach fragt, und jede Prüfung des Inhalts wird einzeln eingeschaltet: Eine Heuristik, die niemand gewählt hat, kann niemand verantworten. Ein Formular fragt mit der Rendering-Option `antibot`; `true` gibt ihm die Honeypots und die Einstellungen der Site.

```yaml
renderingOptions:
  antibot:
    plausibility:
      message: { minWords: 5, maxLength: 5000 }
    links:
      parameters: [message]
      maximum: 1
    equality:
      parameters: [name, subject, message]
    duplicates:
      parameters: [subject, message]
    stopwords:
      parameters: [name, subject, message]
```

| Schlüssel | Wirkung |
| --- | --- |
| `honeypots` | Felder, die kein Browser ausfüllt, als Name und Typ; Vorgabe `email: email` und `homepage: url`, `false` für keine |
| `plausibility` | Zahl der Wörter und Zeichen je Feld: `minWords`, `maxWords`, `minLength`, `maxLength` |
| `links` | höchstens `maximum` Links in den Feldern `parameters`; nicht dort, wo Menschen zu Recht verlinken |
| `equality` | lehnt ab, wenn mehr als `tolerance` der Felder `parameters` denselben Wert tragen; Vorgabe 1 |
| `parameterSet` | vergleicht die gesendeten Felder mit `expected`, mit `tolerateUnexpected` und `requireComplete` |
| `duplicates` | lehnt Inhalt der Felder `parameters` ab, der schon einmal angenommen wurde, für `lifetime` Sekunden |
| `stopwords` | lehnt ab, wenn die Felder `parameters` ein Stopword enthalten |
| `methods`, `times`, `ban` | wie die Einstellungen der Site, für dieses Formular; `times: false` prüft keine Zeiten |
| `session` | `false` lässt die Session aus den Namen der Felder, für ein Formular, das beurteilt wird, bevor TYPO3 die Session kennt, etwa eine Anmeldung in einer Middleware |

Der Adapter hört auf zwei Events von EXT:form. Beim Rendern der ersten Seite fügt er das Element `AntibotFields` mit den Schutzfeldern hinzu; bei einer Sperre oder einer Ablehnung rendert er statt des Formulars die Vorlage `AntibotBlocked`. Bevor EXT:form die erste Seite eines Formulars verarbeitet, beurteilt er die Einsendung und schickt eine abgelehnte auf ihre Seite zurück, ohne dass ein Finisher läuft. Die späteren Seiten eines Formulars mit mehreren Schritten tragen keine Schutzfelder. Wer den Hinweis anders formulieren will, legt eine eigene `AntibotBlocked.fluid.html` in einen Pfad mit höherem Index.

## Aus eigenem Code

Ein Controller holt sich mit `GuardFactory::create()` den Wächter eines Formulars, für den ganzen Request derselbe:

```php
$guard = $this->guards->create($request, 'registration', ['ban' => false], ['tx_twaccounts_registration']);
$closed = $guard->isBanned() || ('POST' === $request->getMethod() && $guard->judge()->isClosed());

$this->view->assignMultiple(['closed' => $closed, 'antibot' => $guard->view()]);
```

```html
<f:if condition="{closed}">
    <f:then><twc:antibot.notice contact="{antibot.contact}" /></f:then>
    <f:else>
        <f:form action="register">
            <twc:antibot.fields fields="{antibot.fields}" />
            …
        </f:form>
    </f:else>
</f:if>
```

- **`create()`** nimmt den Request, den Namen des Formulars, die Optionen wie bei EXT:form und den Scope, unter dem das Formular seine Felder sendet.
- **`judge()`** beurteilt die Einsendung einmal und liefert `Verdict::Accepted`, `Verdict::Refused` oder `Verdict::Banned`; `isClosed()` ist wahr für die beiden letzten. `isBanned()` sagt auch ohne Einsendung, ob der Client gesperrt ist.
- **`remember()`** merkt sich den Inhalt einer Einsendung, die das Formular am Ende angenommen hat, für `duplicates`; `forget()` vergisst ihn wieder, etwa wenn die Einsendung danach an einer anderen Prüfung scheitert.
- **`view()`** liefert die Argumente der Komponenten: die Schutzfelder und die Kontaktadresse.

Inhalt, der schon einmal kam, wird abgelehnt, aber nicht gesperrt: Er kann von einem Menschen stammen, der zweimal auf Senden gedrückt hat.

## Komponenten

`tw_antibot` meldet seine Komponenten unter `Tollwerk\Components` mit der **Priorität 30** an. Zu jeder gehören Beispiele, die Functional-Tests rendern und prüfen.

| Komponente | Zweck |
| --- | --- |
| `twc:antibot.fields` | die verborgenen Felder und die Honeypots in einem Container mit `hidden`, außerhalb der Fokusreihenfolge und ohne Autofill |
| `twc:antibot.notice` | der Hinweis mit `role="alert"` an Stelle des Formulars, mit der Kontaktadresse als Link |

Der Hinweis ist für alle gleich formuliert und sagt weder, was angeschlagen hat, noch wer schuld ist. Wer fälschlich hängen bleibt, liest dieselben Sätze wie der Betreiber eines Bots, und beide müssen danach handeln können. `class` gibt ihm weitere Klassen, im Adapter die des Formulars, dessen Platz er einnimmt.

## Stopwords

Manche Kampagnen verraten sich durch ein Wort. Ein Stopword ist ein eigener Datensatz, an beliebiger Stelle angelegt, und gilt in der ganzen Installation für jedes Formular, das mit `stopwords` Felder dafür nennt. Es trifft nur ganze Wörter, ohne Rücksicht auf Groß- und Kleinschreibung; `*` steht für weitere Buchstaben, `casino*` trifft also auch „Casinos“. Ein einzelner Treffer genügt. Ein ausgeschaltetes Stopword prüft nicht mit; so lässt sich ausprobieren, ob ein Begriff Ärger macht, ohne ihn zu verlieren.

## Sperren

Eine Sperre ist ein Datensatz im Ordner der Site und enthält die ganze Einsendung. Wer ihn öffnet, sieht in zwei Tabellen, was eingegeben wurde und was das Formular mitgeschickt hat, und kann beurteilen, ob es Spam war oder ein Mensch, den der Schutz nicht hätte treffen dürfen. Der Datensatz lässt sich nicht überschreiben.

Eine Sperre endet nach `twAntibot.banPeriod`. Ihr Datensatz wird dann **gelöscht**, nicht verborgen: Eine Notiz über die Einsendung eines Menschen hat keinen Grund, ihre Sperre zu überdauern. Wer den Datensatz im Backend löscht, hebt die Sperre sofort auf. Die Adresse des Clients ist die, die TYPO3 hinter den Proxys auflöst, denen es vertraut; sonst träfe eine Sperre alle hinter demselben Proxy.

## Caching

Eine Seite mit Schutzfeldern kommt aus keinem Cache, weder aus dem von TYPO3 noch aus dem eines Browsers oder Proxys: Sie trägt `Cache-Control: private, no-store` und ein abgelaufenes `Expires`. Die Felder gehören einer Person und tragen ihre Zeit; aus einem Cache bekäme jeder die Felder des ersten Besuchs. Alle anderen Seiten behalten ihr Caching.

## Tests

- **PHPUnit:** Optionen und Einstellungen, die Middleware, die Sperren und Stopwords auf SQLite, der Wächter mit echten Requests, der Adapter mit Formularen von EXT:form im Frontend und jedes Beispiel jeder Komponente.
- **Browser:** `npm run test:browser` rendert mit `BrowserPagesTest` ein Formular mit Schutzfeldern und den Hinweis nach `var/browser` und prüft beides mit Playwright in Chromium und Firefox, mit und ohne JavaScript, und mit axe: Die Honeypots sind unsichtbar und nicht erreichbar, die Schutzfelder reisen mit dem Formular, der Hinweis wird angekündigt.

## Lizenz

GPL-2.0-or-later, siehe [LICENSE.md](LICENSE.md).
