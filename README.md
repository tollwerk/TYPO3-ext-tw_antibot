About
=====

*Antibot* (`tw_antibot`) is a quick proof of concept of how to provide **captcha-less security for forms in TYPO3 projects**. It may be used with **Fluid templates** and / or **[formhandler](http://www.typo3-formhandler.com/) forms**. Apologies for the extension neither being very well tested nor documented at the moment. Feedback and suggestions are highly appreciated!

The extension was heavily inspired by [Karl Groves](http://www.karlgroves.com/)' article ["CAPTCHA-less Security"](http://www.karlgroves.com/2012/04/03/captcha-less-security/) from 2012/2013 and supports his [BotSmasher API](https://www.botsmasher.com/) as external blacklist.


Installation
============

Right now the extension is not yet released to the TYPO3 extension directory (TER), but you may install it from GitHub:

```bash
cd typo3conf/ext
git clone git@github.com:tollwerk/TYPO3-ext-tw_antibot.git tw_antibot
``` 

Simply add *Antibot*'s static TypoScript to your main template then and start configuring the desired options.

*Antibot* supports [ChromePhp](https://github.com/ccampbell/chromephp) for logging to your Chrome console. If you want to use it, please pull in ChromePhp via Composer:

```bash
cd typo3conf/ext/tw_antibot/Resources/Private
composer install
``` 


Configuration
=============

*Antibot* features the following checks. All options can be configured using the constant editor.


Internal banning (blacklist)
----------------------------

Clients may be blacklisted by

* IP addresses (`plugin.tx_twantibot.settings.banning.ip`)
* Email address (`plugin.tx_twantibot.settings.banning.email`)

You may configure whether the client is blacklisted forever or for a certain time only. Use the provided Extbase-CommandController-Task in TYPO3's scheduler to do periodically remove expired blacklist records. 


External banning
----------------

*Antibot* supports [BotSmasher](https://botsmasher.com) to identify known spammers / fraudulent addresses. You will need a [BotSmasher API key](https://www.botsmasher.com/register.php) to use this option (`plugin.tx_twantibot.settings.botsmasher`). 


Honeypot fields
---------------

You may configure one or more honeypot fields (`plugin.tx_twantibot.settings.honeypot`) to be added to your form. Honeypot fields are usually hidden for human users (e.g. via CSS `display: none`) and / or clearly marked as not to be filled in. If a value gets submitted for a honeypot field, then it's most likely a bot submission. Some tips:

* Put the honeypots **after** the submit button(s).
* Name them like typical form fields, e.g. `email` and / or `name` (they will automatically get an `antibot_*[]` prefix).
* Ensure that they are **not `type="hidden"`** as bots usually ignore these.


Session token
-------------

The session token may be taken into account in order to prevent hijacking and external submissions of your form. (`plugin.tx_twantibot.settings.session`)


Submission time
---------------

*Antibot* may consider the time your forms take to be submitted in order to identify bot submissions (`plugin.tx_twantibot.settings.time`). You may specify

* a **minimum submission time for initial submissions**,
* a **minimum time** for all follow-up submissions (e.g. when there are validation errors)
* and a **maximum time**.


Submission method vector
------------------------

You may restrict the submission method vector of your form (`plugin.tx_twantibot.settings.order`):

* GET-GET
* GET-POST
* POST-GET
* POST-POST

The first part specifies the HTTP method that has to be used when your form is initially displayed, while any (re-)submission have to use the second method. 


Extbase / Fluid example
=======================

```html
{namespace ab=Tollwerk\TwAntibot\ViewHelpers}

<section id="comment-form">
	<h1>Write new comment</h1>
	
	<!-- Antibot access check and validation -->
	<f:if condition="{ab:access.granted(argument: 'newComment')}">
		<f:then>
			<f:form action="create" name="newComment" object="{newComment}">
			 	<p>Required fields are followed by <strong><abbr title="required">*</abbr></strong>.</p>
			 	<div class="form-row type-text">
			 		<label for="name">Name <strong><abbr title="required">*</abbr></strong></label>
					<f:form.textfield id="name" property="name" />
					<f:form.validationResults for="newComment.name"><f:if condition="{validationResults.flattenedErrors}"><p><f:for each="{validationResults.errors}" as="error">{error.code}: {error}</f:for></p></f:if></f:form.validationResults>	
			 	</div>
				
				<!-- Additional form fields ... -->

				<div class="form-row type-submit">
					<f:form.submit class="" value="Create new" />
					
					<!-- Antibot armor fields (place after submit button) -->
					<ab:armor/>
					
				</div>
			</f:form>
		</f:then>
		<f:else>
			<p>You are denied access to this form due to spam restrictions. If you think this is an error, please contact <f:link.email email="test@test.com">test@test.com</f:link.email>.</p>
		</f:else>
	</f:if>
</section>
```

Please be aware that it's essential to make the controller actions involved in sending the form **non-cachable**! To achieve this, include them in the non-cachable action list of your plugin registration (`ext_localconf.php`):

```php
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
	'Tollwerk.' . $_EXTKEY,
	'Comments',
	array(
		'Comment' => 'list, show, new, create',
	),
	// non-cacheable actions
	array(
		'Comment' => 'new, create',
	)
);
```  
Formhandler example
===================

TypoScript
----------

```
plugin.Tx_Formhandler.settings.predef.contact {
	name					= Contact
	templateFile 			= FLUIDTEMPLATE
	templateFile {
		file				= fileadmin/.../contact.html
	}
	
	initInterceptors.1 {
		class				= Tollwerk\TwAntibot\Formhandler\Interceptor
		config {
			antibot {
				token		= customToken
				# ...
			}
			templateFile	< plugin.Tx_Formhandler.settings.predef.contact.templateFile 
		}
	}
	
	markers{
		antibotArmor		= USER_INT
		antibotArmor {
			userFunc		= Tollwerk\TwAntibot\Formhandler\Utility->armor
			
			# Custom config should be the same as above
			antibot			< plugin.Tx_Formhandler.settings.predef.contact.initInterceptors.1.config.antibot
		}
	}
}
```

HTML template
-------------

```html

<!-- ###TEMPLATE_FORM1### begin -->
<form method="post" id="###formID###" enctype="multipart/form-data">
	<fieldset><!-- Form fields ... --></fieldset>
	<div>
		###field_langId###
		###field_submit_contact###
		
		<!-- Antibot armor fields (custom marker) -->
		###antibotArmor###
	</div>
</form>
<!-- ###TEMPLATE_FORM1### end -->

<!-- ###TEMPLATE_ANTIBOT### begin -->
<p>You are denied access to this form due to spam restrictions. If you think this is an error, please contact <a href="mailto:info@manuscript-facsimiles.com">info@manuscript-facsimiles.com</a>.</p>
<!-- ###TEMPLATE_ANTIBOT### end -->
```

Legal
=====

Copyright © 2017 [tollwerk GmbH](https://tollwerk.de) / Joschi Kuphal (<joschi@kuphal.net> / [@jkphl](https://twitter.com/jkphl)). *svg-sprite* is licensed under the terms of the [MIT license](LICENSE.txt).
