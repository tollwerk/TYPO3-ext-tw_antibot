<?php

/**
 * Each example of a component of tw_antibot renders: traps out of reach, the notice readable
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

use Dom\Element;
use Dom\HTMLDocument;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Container\ContainerExceptionInterface;
use Tollwerk\TwBase\Component\Example\ExampleRenderer;
use Tollwerk\TwBase\Component\Example\Examples;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use UnexpectedValueException;

/**
 * Each example of a component of tw_antibot renders: traps out of reach, the notice readable
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class ComponentExamplesTest extends FunctionalTestCase
{
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
     * Every example of every component
     *
     * @return iterable<string, array{string, string}>
     *
     * @throws UnexpectedValueException If a file with examples is invalid
     */
    public static function examples(): iterable
    {
        foreach (Examples::find(\dirname(__DIR__, 2) . '/Resources/Private/Components') as $component => $file) {
            foreach (array_keys(Examples::fromFile($file)->examples) as $example) {
                yield $component . '#' . $example => [$component, $example];
            }
        }
    }

    /**
     * Visible inputs are traps inside a hidden container, out of the order of focus and without autofill; the notice
     * is an alert with text, and its links have text
     *
     * @param string $component Component
     * @param string $example   Example
     *
     * @return void
     *
     * @throws ContainerExceptionInterface If a service is missing
     * @throws UnexpectedValueException If an example is missing
     */
    #[Test]
    #[DataProvider('examples')]
    public function exampleRenders(string $component, string $example): void
    {
        $html = $this->get(ExampleRenderer::class)->render($component, $example);
        $document = HTMLDocument::createFromString('<!DOCTYPE html><html lang="en"><body>' . $html, LIBXML_NOERROR);
        self::assertNotSame('', trim($html));

        foreach ($document->querySelectorAll('input:not([type="hidden"])') as $trap) {
            self::assertInstanceOf(Element::class, $trap);
            self::assertInstanceOf(Element::class, $trap->closest('[hidden]'), 'A trap outside a hidden container');
            self::assertSame('-1', $trap->getAttribute('tabindex'));
            self::assertSame('off', $trap->getAttribute('autocomplete'));
            self::assertSame('', $trap->getAttribute('value'));
        }

        foreach ($document->querySelectorAll('[role="alert"], a') as $element) {
            self::assertNotSame('', trim($element->textContent ?? ''));
        }
    }

    /**
     * The notice offers the contact address as a link, and leaves the paragraph out without one
     *
     * @return void
     *
     * @throws ContainerExceptionInterface If a service is missing
     * @throws UnexpectedValueException If an example is missing
     */
    #[Test]
    public function theNoticeOffersTheContactAddress(): void
    {
        $renderer = $this->get(ExampleRenderer::class);
        $contact = HTMLDocument::createFromString(
            '<!DOCTYPE html><html lang="en"><body>' . $renderer->render('twc:antibot.notice', 'contact'),
            LIBXML_NOERROR,
        );
        $plain = HTMLDocument::createFromString(
            '<!DOCTYPE html><html lang="en"><body>' . $renderer->render('twc:antibot.notice', 'plain'),
            LIBXML_NOERROR,
        );

        self::assertSame('mailto:hello@example.org', $contact->querySelector('a')?->getAttribute('href'));
        self::assertNull($plain->querySelector('a'));
        self::assertCount(2, $plain->querySelectorAll('p'));
    }
}
