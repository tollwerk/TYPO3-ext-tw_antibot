# Ablauf

## Standard-TYPO3-Formular (Extbase)

1. Der Besucher ruft eine Seite mit Formular auf
2. Wenn das Formular gerade übermittelt wurde
	* Wenn kein Antibot-Token übermittelt wurde
		* **ZUGRIFF VERWEIGERN** und ggf. **BANNEN**
	* Wenn der BotSmasher aktiviert ist
		* Wenn die an BotSmasher übermittelte IP-Adresse und / oder konfigurierte und übermittelte E-Mail-Adresse negativ ist
			* **ZUGRIFF VERWEIGERN** und ggf. **BANNEN**
	* Extrahieren des Antibot-Tokens
	* Wenn Honeypots aktiviert sind und eines der konfigurierten Honeypot-Felder ausgefüllt wurde
		* **ZUGRIFF VERWEIGERN** und ggf. **BANNEN**
	* Wenn Session-Tokens aktiviert sind und kein passendes Session-Token übermittelt wurde
		* **ZUGRIFF VERWEIGERN** und ggf. **BANNEN**
	* Wenn Übermittlungszeiten aktiviert sind und das Formular nicht innerhalb der Rahmenbedingungen übermittelt wurde
		* **ZUGRIFF VERWEIGERN** und ggf. **BANNEN**
	* Wenn Zugriffsreihenfolgen aktiviert sind und die Übermittlungsreihenfolge nicht stimmt
		* **ZUGRIFF VERWEIGERN** und ggf. **BANNEN**
3. Ansonsten wenn der BotSmasher aktiviert ist
	* Wenn die an BotSmasher übermittelte IP-Adresse negativ ist
		* **ZUGRIFF VERWEIGERN** und ggf. **BANNEN**
3. Wenn IP-Banning aktiv ist und die IP-Adresse des aktuellen Benutzers gelistet ist
	* **ZUGRIFF VERWEIGERN**
4. Wenn E-Mail-Banning aktiv ist und eines der konfigurierten E-Mail-Felder eine E-Mail-Adresse enthält, die gelistet ist
	* **ZUGRIFF VERWEIGERN**

5. Wenn der Zugriff nicht verweigert wurde
	* Darstellen des Formulars
		* 
	* Einbetten von Antibot-Code nach dem Submit-Button durch ViewHelper-Aufruf
		* Antibot-Token
		* Ggf. Honeypot: `<input type="text" style="display:hidden"/>`
6. Ansonsten: Ausgabe einer Hinweismeldung "Aus Spamschutzgründen dürfen sie nicht ..."


## Formhandler

* Preprocessor analog Authcode-Validierung (https://forge.typo3.org/projects/extension-formhandler/repository/entry/branches/typo37-compat/Classes/PreProcessor/ValidateAuthCode.php), der den Zugriff auf das Formular prüft
	* Im Fehlerfall: Umleitung?
* Individueller Marker per UserFunc (Antibot-Token + Honeypot)

## Antibot-Token

Das Antibot-Token muss folgende Parameter transportieren (sofern aktiviert)

* Session Token
* Rendering-Zeitpunkt des Formulars
* HTTP-Methode bzw. die Methodenkette

Für das Antibot-Token gibt es zwei möglichkeiten:

1. Entweder, die notwendigen Informationen werden reversibel verschlüsselt, so dass sie vom Server gezielt extrahiert und interpretiert werden können. Dann braucht es eine sichere 2-Wege Verschlüsselung.
2. Die Daten werden über eine 1-Wege-Verschlüsselung kodiert (`\TYPO3\CMS\Core\Utility\GeneralUtility::hmac()`). Die einzige Variable, die dem Server übermittelt wird, ist der Rendering-Zeitpunkt des Formulars (der innerhalb der Grenzwerte liegen muss), die beiden anderen Werte (Session-Token und HTTP-Methode bzw. Methodenkette) sind dabei konstant, der Server weiß hier, was er erwartet. Er müsste also nur die möglichen Rendering-Zeitpunkte durchgehen, beginnend mit dem unteren Grenzwert und sekundenweise iterierend, den jeweiligen Hash ermitteln und mit dem übermittelten Wert vergleichen, bis er eine zutreffende Kombination findet oder den oberen Grenzwert überschreitet (und damit kein gültiges Token findet). 

## HTTP-Methodenkette

Über die "Submission order" lässt sich die Reihenfolge von HTTP-Methoden angeben, die das Formular durchlaufen haben muss. Angenommen man definiert hier GET-POST (das Formular muss per GET aufgerufen worden sein, und dann per POST versendet worden sein), und es treten beim Versand anderweitige Valdidierungsfehler auf (Felder, die noch korrigiert werden, bevor das Formular erneut abgeschickt wird), dann hat man schnell Reihenfolgen wie GET-POST-POST. Idee: Die zweite Angabe ist immer mehrfach zu akzeptieren. Solange sich die Methode nach dem ersten Versand nicht ändert, ist alles gut (also quasi GET-POST+).
