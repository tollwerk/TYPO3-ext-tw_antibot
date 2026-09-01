# tollwerk/tw-antibot

> Death to spambots! CAPTCHA-less spam protection for TYPO3 forms

This extension decides **on the server** whether a form submission came from a person or a
machine. It never asks the visitor to prove anything: no puzzle, no image grid, no "click
all the traffic lights", no proof-of-work in the background.

That is not a convenience — it is the point. Every challenge-response scheme moves the cost
of the operator's spam problem onto the people the form is meant to serve, and none of them
is fully accessible. Whatever the heuristics let through is a debt for the site operator to
carry, not the visitor.

It is a TYPO3 integration of [jkphl/antibot](https://packagist.org/packages/jkphl/antibot),
which does the actual judging and knows nothing about TYPO3.

> **Development happens at
> [code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot](https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot).**
> The GitHub repository is a mirror kept in step automatically — it is the public face and
> the source Packagist reads, but pull requests and issues opened against it may be
> answered from the other side.

## How it works

Three things happen around a form of the TYPO3 Form Framework, and none of them is visible
to the person filling it in.

**The form is armored while it is being built.** Hidden fields are added whose names nobody
can guess: they are derived from the visitor's session, the form, an installation secret and
the exact set of checks in force. An armor scraped from one form is worthless in another
session, in another form, or after the configuration has changed. One of the fields carries
the second the form was served, inside a signed token.

**A submission is judged before the finishers run.** That moment matters more than it looks:
a submission that completes a form never renders it again — it goes straight to the
finishers that send the mail. Refusing at rendering time would therefore refuse everything
except the submission a machine makes.

**A refused submission is answered with a notice in place of the form.** The client is
banned, the whole submission is recorded, and every later call gets the notice until an
administrator deletes the record.

## Installation

```bash
composer require tollwerk/tw-antibot
```

Then, in this order:

1. **Activate the extension.** In a Composer installation it is active as soon as it is
   installed; a classic installation needs it switched on in the extension manager.
2. **Include the static TypoScript** `EXT:tw_antibot/Configuration/TypoScript/Static` in the
   root template.
3. **Create a storage folder** for the ban records and note its page UID.
4. **Set the constants:**

   ```
   plugin.tx_twantibot.persistence.storagePid = 42
   plugin.tx_twantibot.settings.contact.email = info@example.com
   plugin.tx_twantibot.settings.classAttribute =
   ```

   Without `storagePid` the records land on the root page. Without `contact.email` the
   notice simply leaves out the paragraph offering an address. `classAttribute` decides what
   the notice is wrapped in — empty means it keeps the classes the form itself would have
   carried, which is right until a grid layout puts a form in a narrower column than a block
   of text.

The two database tables are created by the usual schema update.

## Protecting a form

Nothing is protected until a form asks for it, and every check is switched on by itself: a
heuristic that nobody chose is a heuristic nobody can answer for.

A form built in YAML asks through a rendering option:

```yaml
renderingOptions:
  antibot:
    honeypots:
      email: email
      homepage: url
    plausibility:
      message:
        minWords: 5
        maxLength: 5000
    links:
      parameters: [message]
      maximum: 1
    equality:
      parameters: [name, subject, message]
    duplicates:
      parameters: [subject, message]
```

A form built in PHP asks the same way:

```php
$form->setRenderingOption('antibot', [
    'honeypots' => ['email' => 'email', 'homepage' => 'url'],
    'equality'  => ['parameters' => ['name', 'subject', 'message']],
]);
```

| Key | What it does |
|---|---|
| `honeypots` | Fields no browser fills in, given as name → type. A honeypot passes only when it comes back exactly as handed out |
| `plausibility` | Word and character counts per field: `minWords`, `maxWords`, `minLength`, `maxLength` |
| `links` | Caps hyperlinks in the named fields. Leave it out where people legitimately link — a comment about an article, for instance |
| `equality` | Refuses submissions in which several unrelated fields carry one and the same value |
| `parameterSet` | Compares the submitted field names against the announced ones |
| `duplicates` | Refuses content that has been submitted before. Remembered for `content.duplicateLifetime`, and only for submissions that were accepted |
| `methods` | The order of request methods a form is served and submitted under, e.g. `GET-POST` |
| `times` | `minimum`, `maximum` and `followup` seconds. Defaults come from the constants |
| `ban` | Whether a refused submission earns a ban. Default: yes |

Whatever a form does not mention comes from the installation-wide settings, and whatever
those do not mention comes from the defaults in the code.

## The notice

The refusal is worded once, for everybody, and says neither what was detected nor who is to
blame — a person who ran into a false positive reads the same sentences as a spambot's
operator, and both must be able to act on them. It reads sensibly right after a bounced
submission and days later.

Override `Resources/Private/Templates/AntibotBlocked.html` through
`plugin.tx_twantibot.view.templateRootPath` to word it differently. The address from
`settings.contact.email` reaches the template as a rendering option.

## Ban records

A ban is a record in the storage folder, and it holds the complete submission — all form
fields, the antibot fields including the one that triggered, and the form state. That makes
the case judgeable: whoever opens the record sees at a glance whether this was spam or
somebody the protection should not have caught. The record is shown as a table of fields,
not as the JSON it is stored as, and it cannot be typed over.

Bans expire after `settings.banning.period` seconds (one month by default). An expired
record is **deleted**, not merely hidden — a note about somebody's submission has no reason
to outlive the ban it justified. `period = 0` means "until somebody lifts it". Deleting the
record in the backend lifts the ban immediately.

## Caching

A page carrying an armored form is not cached — neither by TYPO3 nor by browsers, proxies or
service workers. It cannot be: the armor belongs to one session and carries the second it
was served, so a cached page would hand the first visitor's armor to everyone after them and
keep handing it out until it had aged past the submission window. All other pages keep their
caching untouched.

## Requirements

PHP 7.4 or later, TYPO3 10.4, EXT:form.

## License

Copyright © 2026 [Joschi Kuphal](https://jkphl.is) / joschi@tollwerk.de. Licensed under the
terms of the [GPL v2 or later](LICENSE).
