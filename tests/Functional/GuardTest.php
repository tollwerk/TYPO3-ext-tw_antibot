<?php

/**
 * A guard hands out protective fields, judges a submission and bans the client of a refused one
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

use DateTimeImmutable;
use Doctrine\DBAL\Exception as DBALException;
use InvalidArgumentException;
use Jkphl\Antibot\Domain\Exceptions\RuntimeException;
use Jkphl\Antibot\Ports\Exceptions\InvalidArgumentException as AntibotArgumentException;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Tollwerk\TwAntibot\Protection\Guard;
use Tollwerk\TwAntibot\Protection\GuardFactory;
use Tollwerk\TwAntibot\Protection\ProtectedPage;
use Tollwerk\TwAntibot\Protection\Verdict;
use Tollwerk\TwAntibot\Store\BanStore;
use Tollwerk\TwAntibot\Store\CacheStore;
use Tollwerk\TwAntibot\Store\StopwordList;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * A guard hands out protective fields, judges a submission and bans the client of a refused one
 *
 * Every visit and every submission is a request of its own, with a factory of its own, as in TYPO3.
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class GuardTest extends FunctionalTestCase
{
    /**
     * Address of the client
     *
     * @var string
     */
    private const string CLIENT = '203.0.113.7';

    /**
     * Checks of the form in the tests: no waiting, and the fields "name" and "message"
     *
     * @var array<string, mixed>
     */
    private const array OPTIONS = ['times' => ['minimum' => 0, 'followup' => 0]];

    /**
     * Extensions under test
     *
     * @var array<non-empty-string>
     */
    #[Override]
    protected array $testExtensionsToLoad = ['tollwerk/tw-base', 'tollwerk/tw-antibot'];

    /**
     * Extensions of the core that tw_base needs
     *
     * @var array<non-empty-string>
     */
    #[Override]
    protected array $coreExtensionsToLoad = ['filemetadata'];

    /**
     * Whether the page of the last visit carries protective fields
     *
     * @var ProtectedPage|null
     */
    private ?ProtectedPage $page = null;

    /**
     * Time of the next request
     *
     * @var integer
     */
    private int $now = 1795000000;

    /**
     * The fields are a hidden field and two honeypots under the names of the form, and their page stays out of caches
     *
     * @return void
     *
     * @throws AntibotArgumentException If a field has an invalid name
     * @throws InvalidArgumentException If the request is invalid
     * @throws NoSuchCacheException If the cache is missing
     * @throws RuntimeException If the checks are set up twice
     */
    #[Test]
    public function theFieldsKeepTheirPageOutOfCaches(): void
    {
        $request = $this->request('GET');
        $guard = $this->guard($request, self::OPTIONS);
        $fields = $guard->fields();

        self::assertSame(['email', 'url', 'hidden', 'hidden'], array_column($fields, 'type'));
        foreach ($fields as $field) {
            self::assertMatchesRegularExpression(
                '/^tx_test\[contact_[0-9a-f]{40}\]\[(email|homepage|hmac|ts)\]$/',
                $field['name'],
            );
        }

        self::assertSame($fields, $guard->fields(), 'The fields are handed out once per request');
        self::assertSame(['fields' => $fields, 'contact' => 'antibot@example.org'], $guard->view());
        $instruction = $request->getAttribute('frontend.cache.instruction');
        self::assertInstanceOf(CacheInstruction::class, $instruction);
        self::assertFalse($instruction->isCachingAllowed());
        self::assertTrue($this->page?->isProtected());
        self::assertFalse($guard->isBanned());
    }

    /**
     * A submission with the fields of the form is accepted and bans nobody
     *
     * @return void
     *
     * @throws AntibotArgumentException If a field has an invalid name
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If the request is invalid
     * @throws NoSuchCacheException If the cache is missing
     * @throws RuntimeException If the checks are set up twice
     */
    #[Test]
    public function aSubmissionWithTheFieldsIsAccepted(): void
    {
        $guard = $this->submit(['message' => 'Hello, I would like to come.'], self::OPTIONS);

        self::assertNull($guard->verdict());
        self::assertSame(Verdict::Accepted, $guard->judge());
        self::assertSame(Verdict::Accepted, $guard->verdict());
        self::assertFalse($guard->judge()->isClosed());
        self::assertSame([], $this->bans());
    }

    /**
     * A submission without the fields is refused, and its client is banned with the submission until the ban ends
     *
     * @return void
     *
     * @throws AntibotArgumentException If a field has an invalid name
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If the request is invalid
     * @throws NoSuchCacheException If the cache is missing
     * @throws LogicException If a ban has no submission
     * @throws RuntimeException If the checks are set up twice
     */
    #[Test]
    public function aSubmissionWithoutTheFieldsBansTheClient(): void
    {
        $request = $this->request('POST', ['tx_test' => ['message' => 'Cheap pills', 'from' => 'spam@example.org']]);
        $guard = $this->guard($request, self::OPTIONS);

        self::assertSame(Verdict::Refused, $guard->judge());
        self::assertTrue($guard->judge()->isClosed());
        $bans = $this->bans();
        self::assertCount(1, $bans);
        self::assertSame(self::CLIENT, $bans[0]['value'] ?? null);
        self::assertSame(BanStore::ADDRESS, $bans[0]['property'] ?? null);
        self::assertSame(3, $bans[0]['pid'] ?? null, 'The ban lies in the folder of the site');
        self::assertSame('Sent without the protective fields', $bans[0]['error'] ?? null);
        self::assertSame(
            ['message' => 'Cheap pills', 'from' => 'spam@example.org'],
            json_decode(\is_string($bans[0]['data'] ?? null) ? $bans[0]['data'] : '', true),
        );

        $visit = $this->guard($this->request('GET'), self::OPTIONS);
        self::assertTrue($visit->isBanned());
        self::assertSame(Verdict::Banned, $visit->judge());
        self::assertTrue(Verdict::Banned->isClosed());
    }

    /**
     * A filled honeypot is refused; a form that does not ban leaves the client alone
     *
     * @return void
     *
     * @throws AntibotArgumentException If a field has an invalid name
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If the request is invalid
     * @throws NoSuchCacheException If the cache is missing
     * @throws RuntimeException If the checks are set up twice
     */
    #[Test]
    public function aFilledHoneypotIsRefused(): void
    {
        $options = [...self::OPTIONS, 'ban' => false];
        $guard = $this->submit(['message' => 'Hello'], $options, ['homepage' => 'https://spam.example.org/']);

        self::assertSame(Verdict::Refused, $guard->judge());
        self::assertSame([], $this->bans());
        self::assertFalse($this->guard($this->request('GET'), $options)->isBanned());
    }

    /**
     * An enabled stopword refuses a submission whose checked fields contain it
     *
     * @return void
     *
     * @throws AntibotArgumentException If a field has an invalid name
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If the request is invalid
     * @throws NoSuchCacheException If the cache is missing
     * @throws RuntimeException If the checks are set up twice
     */
    #[Test]
    public function aStopwordRefusesASubmission(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Stopwords.csv');
        $options = [...self::OPTIONS, 'ban' => false, 'stopwords' => ['parameters' => ['message']]];

        $stopwords = new StopwordList(GeneralUtility::makeInstance(ConnectionPool::class));
        self::assertSame(['casino*'], $stopwords->getStopwords());
        self::assertSame(Verdict::Refused, $this->submit(['message' => 'Visit our Casinos'], $options)->judge());
        self::assertSame(Verdict::Accepted, $this->submit(['message' => 'Hello world'], $options)->judge());
        self::assertSame(Verdict::Accepted, $this->submit(['name' => 'Casino', 'message' => 'Hi'], $options)->judge());
    }

    /**
     * Each check of content the form asks for refuses what breaks it, and accepts what keeps to it
     *
     * @return void
     *
     * @throws AntibotArgumentException If a field has an invalid name
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If the request is invalid
     * @throws NoSuchCacheException If the cache is missing
     * @throws RuntimeException If the checks are set up twice
     */
    #[Test]
    public function theChecksOfContentRefuseWhatBreaksThem(): void
    {
        $checks = [
            'plausibility' => [
                ['plausibility' => ['message' => ['minWords' => 3]]],
                ['message' => 'Hi'],
                ['message' => 'Hello, I would like to come.'],
            ],
            'links' => [
                ['links' => ['parameters' => ['message'], 'maximum' => 1]],
                ['message' => 'See https://a.example.org/ and https://b.example.org/'],
                ['message' => 'See https://a.example.org/'],
            ],
            'equality' => [
                ['equality' => ['parameters' => ['name', 'message']]],
                ['name' => 'Cheap pills', 'message' => 'Cheap pills'],
                ['name' => 'Ada', 'message' => 'Cheap pills'],
            ],
            'parameterSet' => [
                ['parameterSet' => ['expected' => ['name', 'message'], 'requireComplete' => true]],
                ['name' => 'Ada'],
                ['name' => 'Ada', 'message' => 'Hello'],
            ],
        ];

        foreach ($checks as $check => [$options, $refused, $accepted]) {
            $options = [...self::OPTIONS, 'ban' => false, ...$options];
            self::assertSame(Verdict::Refused, $this->submit($refused, $options)->judge(), $check);
            self::assertSame(Verdict::Accepted, $this->submit($accepted, $options)->judge(), $check);
        }
    }

    /**
     * Content the form accepted is refused when it comes again, until the form forgets it
     *
     * @return void
     *
     * @throws AntibotArgumentException If a field has an invalid name
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If the request is invalid
     * @throws NoSuchCacheException If the cache is missing
     * @throws RuntimeException If the checks are set up twice
     */
    #[Test]
    public function repeatedContentIsRefusedUntilForgotten(): void
    {
        $options = [...self::OPTIONS, 'duplicates' => ['parameters' => ['message']]];
        $first = $this->submit(['message' => 'Hello, I would like to come.'], $options);
        self::assertSame(Verdict::Accepted, $first->judge());
        self::assertFalse($first->hasRemembered());
        $first->remember();
        self::assertTrue($first->hasRemembered());

        $again = $this->submit(['message' => '  hello, I would like to come. '], $options);
        self::assertSame(Verdict::Refused, $again->judge());
        self::assertFalse($again->isBanned());
        self::assertSame([], $this->bans(), 'Content sent twice earns no ban');

        $first->forget();
        self::assertFalse($first->hasRemembered());
        $forgotten = $this->submit(['message' => 'Hello, I would like to come.'], $options);
        self::assertSame(Verdict::Accepted, $forgotten->judge());

        $plain = $this->submit(['message' => 'Hello'], self::OPTIONS);
        $plain->remember();
        self::assertFalse($plain->hasRemembered(), 'A form that does not refuse repeated content remembers nothing');
    }

    /**
     * Visit the form and send it with its fields
     *
     * @param array<string, string> $values    Values of the form
     * @param array<string, mixed>  $options   Checks of the form
     * @param array<string, string> $honeypots Values of the honeypots
     *
     * @return Guard Guard of the submission
     *
     * @throws AntibotArgumentException If a field has an invalid name
     * @throws InvalidArgumentException If the request is invalid
     * @throws NoSuchCacheException If the cache is missing
     * @throws RuntimeException If the checks are set up twice
     */
    private function submit(array $values, array $options, array $honeypots = []): Guard
    {
        $query = '';
        foreach ($this->guard($this->request('GET'), $options)->fields() as $field) {
            $name = (string) preg_replace('/^.*\[(\w+)\]$/', '$1', $field['name']);
            $query .= '&' . rawurlencode($field['name']) . '=' . rawurlencode($honeypots[$name] ?? $field['value']);
        }

        foreach ($values as $name => $value) {
            $query .= '&' . rawurlencode('tx_test[' . $name . ']') . '=' . rawurlencode($value);
        }

        parse_str($query, $body);
        $this->now += 5;

        return $this->guard($this->request('POST', $body), $options);
    }

    /**
     * Guard of the form "contact" in a request of its own
     *
     * @param ServerRequest        $request Request
     * @param array<string, mixed> $options Checks of the form
     *
     * @return Guard
     *
     * @throws AntibotArgumentException If a field has an invalid name
     * @throws NoSuchCacheException If the cache is missing
     * @throws RuntimeException If the checks are set up twice
     * @throws InvalidArgumentException If a class cannot be instantiated
     */
    private function guard(ServerRequest $request, array $options): Guard
    {
        $this->page = new ProtectedPage();
        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new DateTimeImmutable()->setTimestamp($this->now)));
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $factory = new GuardFactory(
            $connectionPool,
            new CacheStore(GeneralUtility::makeInstance(CacheManager::class)->getCache('tw_antibot')),
            new StopwordList($connectionPool),
            GeneralUtility::makeInstance(Context::class),
            $this->page,
            new NullLogger(),
        );

        return $factory->create($request, 'contact', $options, ['tx_test']);
    }

    /**
     * Request of the client on a site with a folder for bans and a contact address
     *
     * @param string                  $method Method
     * @param array<array-key, mixed> $body   Parsed body
     *
     * @return ServerRequest
     *
     * @throws InvalidArgumentException If the request is invalid
     */
    private function request(string $method, array $body = []): ServerRequest
    {
        $server = ['REMOTE_ADDR' => self::CLIENT, 'HTTP_HOST' => 'localhost', 'REQUEST_METHOD' => $method];
        $settings = SiteSettings::createFromSettingsTree([
            'twAntibot' => ['storage' => 3, 'contact' => 'antibot@example.org'],
        ]);
        $request = new ServerRequest('http://localhost/contact', $method, 'php://input', [], $server);

        return $request->withParsedBody($body)
            ->withAttribute('normalizedParams', NormalizedParams::createFromServerParams($server))
            ->withAttribute('site', new Site('main', 1, ['base' => 'http://localhost/'], $settings))
            ->withAttribute('frontend.cache.instruction', new CacheInstruction());
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

        return array_values($query->select('pid', 'property', 'value', 'error', 'data')
            ->from(BanStore::TABLE)
            ->executeQuery()
            ->fetchAllAssociative());
    }
}
