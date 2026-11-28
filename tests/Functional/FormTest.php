<?php

/**
 * A form of EXT:form that asks for protection carries the protective fields and closes for a refused submission
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Tests\Functional;

use Doctrine\DBAL\Exception as DBALException;
use Dom\Element;
use Dom\HTMLDocument;
use InvalidArgumentException;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tollwerk\TwAntibot\Store\BanStore;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * A form of EXT:form that asks for protection carries the protective fields and closes for a refused submission
 *
 * Each request is a second later than the one before, plus the seconds a visitor waits before sending a form.
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class FormTest extends FunctionalTestCase
{
    /**
     * Seconds a visitor waits before sending a form
     *
     * @var integer
     */
    private const int WAIT = 5;

    /**
     * Extensions under test
     *
     * @var array<non-empty-string>
     */
    #[Override]
    protected array $testExtensionsToLoad = [
        'tollwerk/tw-base',
        'tollwerk/tw-antibot',
        __DIR__ . '/Fixtures/Extensions/tw_antibot_test',
    ];

    /**
     * Extensions of the core
     *
     * @var array<non-empty-string>
     */
    #[Override]
    protected array $coreExtensionsToLoad = ['filemetadata', 'form'];

    /**
     * A page cache that stores pages, which the testing framework leaves out
     *
     * @var array<string, mixed>
     */
    #[Override]
    protected array $configurationToUseInTestInstance = [
        'SYS' => ['caching' => ['cacheConfigurations' => ['pages' => ['backend' => Typo3DatabaseBackend::class]]]],
    ];

    /**
     * Time of the next request
     *
     * @var integer
     */
    private int $now = 1795000000;

    /**
     * Address of the client of the next request
     *
     * @var string
     */
    private string $client = '127.0.0.1';

    /**
     * Put a form on each page of a site that keeps its bans in folder 4
     *
     * @return void
     *
     * @throws RuntimeException If the folder of the site configuration cannot be created
     * @throws LogicException If the fixture cannot be imported
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Forms.csv');

        $sites = $this->instancePath . '/typo3conf/sites/main';
        GeneralUtility::mkdir_deep($sites);
        file_put_contents($sites . '/config.yaml', <<<'YAML'
            rootPageId: 1
            base: 'http://localhost/'
            websiteTitle: 'Example Website'
            dependencies:
              - typo3/form
              - tollwerk/tw-antibot
            settings:
              twAntibot:
                storage: 4
                contact: 'antibot@example.org'
            languages:
              - title: English
                languageId: 0
                base: /
                locale: en_US.UTF-8
            YAML);
        file_put_contents($sites . '/setup.typoscript', <<<'TYPOSCRIPT'
            page = PAGE
            page.10 = EXTBASEPLUGIN
            page.10.extensionName = Form
            page.10.pluginName = Formframework
            page.10.settings.persistenceIdentifier = EXT:tw_antibot_test/Resources/Private/Forms/Contact.form.yaml
            [traverse(page, "uid") == 2]
              page.10.settings.persistenceIdentifier = EXT:tw_antibot_test/Resources/Private/Forms/Steps.form.yaml
            [traverse(page, "uid") == 3]
              page.10.settings.persistenceIdentifier = EXT:tw_antibot_test/Resources/Private/Forms/Plain.form.yaml
            [end]
            TYPOSCRIPT);
    }

    /**
     * The form carries a hidden field, a timestamp and two honeypots out of reach, and its page stays out of caches
     *
     * @return void
     *
     * @throws InvalidArgumentException If the request is invalid
     */
    #[Test]
    public function theFormCarriesTheProtectiveFields(): void
    {
        $response = $this->visit('/');
        $page = $this->page($response);

        self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('Thu, 01 Jan 1970 00:00:00 GMT', $response->getHeaderLine('Expires'));
        $traps = $page->querySelectorAll('form .AntibotFields[hidden] input');
        self::assertCount(2, $traps);
        foreach ($traps as $trap) {
            self::assertInstanceOf(Element::class, $trap);
            self::assertMatchesRegularExpression(
                '/\[contact-\d+_[0-9a-f]{40}\]\[(email|homepage)\]$/',
                (string) $trap->getAttribute('name'),
            );
            self::assertSame('-1', $trap->getAttribute('tabindex'));
        }

        $hidden = 'form input[type="hidden"][name$="][hmac]"], form input[type="hidden"][name$="][ts]"]';
        self::assertCount(2, $page->querySelectorAll($hidden));
        self::assertCount(1, $page->querySelectorAll('.AntibotFields'), 'The fields appear once');
    }

    /**
     * A form that does not ask for protection carries no fields and stays cacheable
     *
     * @return void
     *
     * @throws InvalidArgumentException If the request is invalid
     */
    #[Test]
    public function aPlainFormCarriesNoFields(): void
    {
        $response = $this->visit('/plain');
        $page = $this->page($response);

        self::assertNull($page->querySelector('.AntibotFields'));
        self::assertFalse($response->hasHeader('Expires'));
        $answer = $this->page($this->submit($page, ['message' => 'Hi']));
        self::assertStringContainsString('Thank you anyway', $this->text($answer));
    }

    /**
     * A person who waits and leaves the traps empty gets the confirmation
     *
     * @return void
     *
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If the request is invalid
     */
    #[Test]
    public function aPersonGetsTheConfirmation(): void
    {
        $page = $this->page($this->visit('/'));
        $answer = $this->page($this->submit($page, ['name' => 'Ada', 'message' => 'I would like to come.']));

        self::assertStringContainsString('Thank you for your message', $this->text($answer));
        self::assertSame([], $this->bans());
    }

    /**
     * A machine that fills a trap gets the notice with the contact address instead of the form, and is banned
     *
     * @return void
     *
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If the request is invalid
     */
    #[Test]
    public function aMachineGetsTheNoticeAndIsBanned(): void
    {
        $page = $this->page($this->visit('/'));
        $trap = $page->querySelector('.AntibotFields input[type="url"]');
        self::assertInstanceOf(Element::class, $trap);
        $answer = $this->page($this->submit($page, [
            'name' => 'Bot',
            'message' => 'Cheap pills',
            'code' => 'secret-password',
            (string) $trap->getAttribute('name') => 'https://spam.example.org/',
        ]));

        self::assertNotNull($answer->querySelector('.AntibotNotice[role="alert"]'));
        self::assertNull($answer->querySelector('form'));
        $link = $answer->querySelector('.AntibotNotice a');
        self::assertSame('mailto:antibot@example.org', $link?->getAttribute('href'));
        self::assertStringNotContainsString('Thank you', $this->text($answer));

        $bans = $this->bans();
        self::assertCount(1, $bans);
        self::assertSame('127.0.0.1', $bans[0]['value'] ?? null);
        self::assertSame(4, $bans[0]['pid'] ?? null);
        $data = json_decode(\is_string($bans[0]['data'] ?? null) ? $bans[0]['data'] : '', true);
        self::assertIsArray($data, 'The record holds the values of the form, without the namespace of the plugin');
        $values = $data;
        self::assertSame('Cheap pills', $values['message'] ?? null);
        self::assertArrayNotHasKey('code', $values, 'A ban leaves out the password');
        self::assertStringNotContainsString('secret-password', (string) json_encode($data));

        $again = $this->page($this->visit('/'));
        self::assertNotNull($again->querySelector('.AntibotNotice'), 'A banned client gets the notice on a visit');
        self::assertNull($again->querySelector('form'));
        self::assertNotNull($this->page($this->visit('/steps'))->querySelector('.AntibotNotice'));
        $plain = $this->page($this->visit('/plain'));
        self::assertNotNull($plain->querySelector('form'), 'Forms without protection stay open');
    }

    /**
     * The notice for a banned client stays out of the page cache: the next visitor gets the form
     *
     * @return void
     *
     * @throws InvalidArgumentException If the request is invalid
     */
    #[Test]
    public function theNoticeStaysOutOfThePageCache(): void
    {
        $page = $this->page($this->visit('/'));
        $trap = $page->querySelector('.AntibotFields input[type="url"]');
        self::assertInstanceOf(Element::class, $trap);
        $this->submit($page, ['name' => 'Bot', (string) $trap->getAttribute('name') => 'https://spam.example.org/']);
        self::assertNotNull($this->page($this->visit('/'))->querySelector('.AntibotNotice'));

        $this->client = '198.51.100.9';
        $next = $this->page($this->visit('/'));
        self::assertNull($next->querySelector('.AntibotNotice'), 'Another client gets the form, not the notice');
        self::assertNotNull($next->querySelector('form .AntibotFields'));
    }

    /**
     * A submission without the protective fields gets the notice, but bans nobody
     *
     * Another site can make the browser of any visitor send a form without the fields.
     *
     * @return void
     *
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If the request is invalid
     */
    #[Test]
    public function aSubmissionWithoutTheFieldsGetsTheNotice(): void
    {
        $page = $this->page($this->visit('/'));
        foreach ($page->querySelectorAll('[name*="_"][name$="][hmac]"], [name$="][ts]"], .AntibotFields') as $field) {
            $field->remove();
        }

        $answer = $this->page($this->submit($page, ['name' => 'Bot', 'message' => 'Cheap pills']));

        self::assertNotNull($answer->querySelector('.AntibotNotice'));
        self::assertSame([], $this->bans());
    }

    /**
     * A submission from a page of another site gets the notice, even with the fields, but bans nobody
     *
     * @return void
     *
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If the request is invalid
     */
    #[Test]
    public function aSubmissionFromAnotherSiteIsRefused(): void
    {
        $page = $this->page($this->visit('/'));
        $answer = $this->page($this->submit($page, ['name' => 'Ada', 'message' => 'Hello'], [
            'Origin' => 'https://evil.example',
            'Sec-Fetch-Site' => 'cross-site',
        ]));

        self::assertNotNull($answer->querySelector('.AntibotNotice'));
        self::assertStringNotContainsString('Thank you', $this->text($answer));
        self::assertSame([], $this->bans());

        $same = $this->page($this->visit('/'));
        $sent = $this->page($this->submit($same, ['name' => 'Ada', 'message' => 'Hello again'], [
            'Origin' => 'http://localhost',
            'Sec-Fetch-Site' => 'same-origin',
        ]));
        self::assertStringContainsString('Thank you for your message', $this->text($sent));
    }

    /**
     * A POST to the cached view of the form, which the form does not process, is not judged and bans nobody
     *
     * @return void
     *
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If the request is invalid
     */
    #[Test]
    public function aPostTheFormDoesNotProcessIsNotJudged(): void
    {
        $field = $this->page($this->visit('/'))->querySelector('form textarea');
        self::assertInstanceOf(Element::class, $field);
        $name = (string) $field->getAttribute('name');
        self::assertSame(1, preg_match('/^tx_form_formframework\[([^\]]+)\]/', $name, $form));
        $body = ['tx_form_formframework' => [$form[1] => ['x' => '1']]];
        $answer = $this->page($this->execute(new InternalRequest('http://localhost/')
            ->withMethod('POST')
            ->withBody(new StreamFactory()->createStream(http_build_query($body)))
            ->withParsedBody($body)));

        self::assertNull($answer->querySelector('.AntibotNotice'));
        self::assertSame([], $this->bans());
        $this->client = '198.51.100.9';
        self::assertNotNull($this->page($this->visit('/'))->querySelector('form .AntibotFields'));
    }

    /**
     * A message that failed the validation of the form counts as not sent; a message sent before is refused
     *
     * @return void
     *
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If the request is invalid
     */
    #[Test]
    public function aRepeatedMessageIsRefused(): void
    {
        $form = $this->page($this->visit('/'));
        $invalid = $this->page($this->submit($form, ['name' => '', 'message' => 'Hello there']));
        self::assertNotNull($invalid->querySelector('form .AntibotFields'), 'The form comes back with new fields');
        self::assertNull($invalid->querySelector('.AntibotNotice'));

        $sent = $this->page($this->submit($invalid, ['name' => 'Ada', 'message' => 'Hello there']));
        self::assertStringContainsString('Thank you for your message', $this->text($sent));

        $again = $this->page($this->visit('/'));
        $repeated = $this->page($this->submit($again, ['name' => 'Ada', 'message' => 'Hello there']));
        self::assertNotNull($repeated->querySelector('.AntibotNotice'));
        self::assertSame([], $this->bans(), 'A repeated message earns no ban');
    }

    /**
     * The protective fields come with the first step, which is judged; the later steps carry none
     *
     * @return void
     *
     * @throws InvalidArgumentException If the request is invalid
     */
    #[Test]
    public function theFirstStepIsJudged(): void
    {
        $first = $this->page($this->visit('/steps'));
        self::assertNotNull($first->querySelector('form .AntibotFields'));

        $second = $this->page($this->submit($first, ['name' => 'Ada']));
        self::assertNull($second->querySelector('.AntibotFields'));
        self::assertNotNull($second->querySelector('textarea'));

        $done = $this->page($this->submit($second, ['message' => 'Hello']));
        self::assertStringContainsString('Thank you for both steps', $this->text($done));

        $without = $this->page($this->visit('/steps'));
        foreach ($without->querySelectorAll('[name$="][hmac]"], [name$="][ts]"], .AntibotFields') as $field) {
            $field->remove();
        }

        self::assertNotNull($this->page($this->submit($without, ['name' => 'Bot']))->querySelector('.AntibotNotice'));
    }

    /**
     * Visit a page
     *
     * @param string $path Path
     *
     * @return ResponseInterface
     *
     * @throws InvalidArgumentException If the request is invalid
     */
    private function visit(string $path): ResponseInterface
    {
        return $this->execute(new InternalRequest('http://localhost' . $path));
    }

    /**
     * Send the form of a page after a while, with its fields and the given values
     *
     * @param HTMLDocument          $page    Page
     * @param array<string, string> $values  Values by the identifier of the element or the full name of a field
     * @param array<string, string> $headers Headers of the request, as a browser would send them
     *
     * @return ResponseInterface
     *
     * @throws InvalidArgumentException If the request is invalid
     */
    private function submit(HTMLDocument $page, array $values, array $headers = []): ResponseInterface
    {
        $form = $page->querySelector('form');
        self::assertInstanceOf(Element::class, $form);
        $body = [];
        foreach ($form->querySelectorAll('input[name], textarea[name]') as $input) {
            $name = (string) $input->getAttribute('name');
            $body[$name] = 'textarea' === strtolower($input->tagName)
                ? ($input->textContent ?? '')
                : (string) $input->getAttribute('value');
        }

        // The button to the next step carries the step, as a click on it would
        $next = null;
        foreach ($form->querySelectorAll('button[type="submit"][name]') as $button) {
            $step = (int) $button->getAttribute('value');
            if ($next instanceof Element && $step <= (int) $next->getAttribute('value')) {
                continue;
            }

            $next = $button;
        }

        if ($next instanceof Element) {
            $body[(string) $next->getAttribute('name')] = (string) $next->getAttribute('value');
        }

        foreach ($values as $key => $value) {
            $matches = array_filter(
                array_keys($body),
                static fn(string $name): bool => str_ends_with($name, '[' . $key . ']'),
            );
            $body[array_key_exists($key, $body) || [] === $matches ? $key : reset($matches)] = $value;
        }

        $fields = http_build_query($body);
        parse_str($fields, $parsed);
        $this->now += self::WAIT;
        $request = new InternalRequest('http://localhost' . html_entity_decode((string) $form->getAttribute('action')))
            ->withMethod('POST')
            ->withBody(new StreamFactory()->createStream($fields))
            ->withParsedBody($parsed);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->execute($request);
    }

    /**
     * Send a request a second after the last one
     *
     * @param InternalRequest $request Request
     *
     * @return ResponseInterface
     */
    private function execute(InternalRequest $request): ResponseInterface
    {
        ++$this->now;
        $GLOBALS['EXEC_TIME'] = $GLOBALS['SIM_EXEC_TIME'] = $GLOBALS['ACCESS_TIME'] = $this->now;

        // jkphl/antibot reads the method from the server, which the testing framework leaves out
        return $this->executeFrontendSubRequest($request->withServerParams([
            'SCRIPT_NAME' => '/index.php',
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
            'HTTPS' => 'off',
            'REMOTE_ADDR' => $this->client,
            'REQUEST_METHOD' => $request->getMethod(),
        ]));
    }

    /**
     * Page of a successful response
     *
     * @param ResponseInterface $response Response
     *
     * @return HTMLDocument
     */
    private function page(ResponseInterface $response): HTMLDocument
    {
        self::assertSame(200, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));

        return HTMLDocument::createFromString((string) $response->getBody(), LIBXML_NOERROR);
    }

    /**
     * Text of a page with whitespace collapsed
     *
     * @param HTMLDocument $page Page
     *
     * @return string
     */
    private function text(HTMLDocument $page): string
    {
        return (string) preg_replace('/\s+/', ' ', $page->body->textContent ?? '');
    }

    /**
     * Records of bans
     *
     * @return list<array<string, mixed>>
     *
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If a class cannot be instantiated
     */
    private function bans(): array
    {
        $query = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(BanStore::TABLE);

        return array_values($query->select('pid', 'value', 'data')
            ->from(BanStore::TABLE)
            ->executeQuery()
            ->fetchAllAssociative());
    }
}
