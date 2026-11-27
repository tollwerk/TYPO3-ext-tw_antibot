<?php

/**
 * A ban shows its submission as tables of fields, what was typed in full and what the form carried shortened
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
use InvalidArgumentException;
use JsonException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tollwerk\TwAntibot\Backend\SubmissionElement;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * A ban shows its submission as tables of fields, what was typed in full and what the form carried shortened
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class SubmissionElementTest extends FunctionalTestCase
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
     * Labels of the backend in English
     *
     * @return void
     *
     * @throws InvalidArgumentException If a class cannot be instantiated
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('en');
    }

    /**
     * The fields somebody typed come first and in full, below the names of the form; what the form carried comes
     * second, shortened, with the protective fields named "antibot"
     *
     * @return void
     *
     * @throws JsonException If the submission cannot be encoded
     */
    #[Test]
    public function theSubmissionIsShownAsTables(): void
    {
        $message = str_repeat('Cheap pills <b>now</b>. ', 10);
        $page = $this->render(json_encode([
            'contact-1' => [
                'name' => 'Bot',
                'message' => $message . "\nBuy",
                'empty' => '  ',
                'address' => ['city' => 'Nuremberg', 'none' => []],
                '__state' => str_repeat('x', 200),
                'contact-1_' . str_repeat('ab', 20) => ['hmac' => 'f00', 'homepage' => 'https://spam.example.org/'],
            ],
        ], JSON_THROW_ON_ERROR));

        $tables = array_values(iterator_to_array($page->querySelectorAll('table')));
        self::assertCount(2, $tables);
        self::assertSame(['What was submitted', 'Carried along by the form'], $this->headings($page));

        $submitted = $this->rows($tables[0]);
        self::assertSame(['name', 'message', 'empty', 'address › city', 'address › none'], array_keys($submitted));
        self::assertSame('Bot', $submitted['name'] ?? null);
        self::assertSame(trim($message) . ' Buy', preg_replace('/\s+/', ' ', trim($submitted['message'] ?? '')));
        self::assertSame('(left blank)', $submitted['empty'] ?? null);
        self::assertSame('(empty)', $submitted['address › none'] ?? null);
        self::assertNull($page->querySelector('b'), 'The submission is escaped');
        self::assertNotNull($tables[0]->querySelector('br'), 'Line breaks are kept');

        $carried = $this->rows($tables[1]);
        self::assertSame(['__state', 'antibot › hmac', 'antibot › homepage'], array_keys($carried));
        self::assertSame(str_repeat('x', 120) . ' …', $carried['__state'] ?? null);
        self::assertSame('https://spam.example.org/', $carried['antibot › homepage'] ?? null);
    }

    /**
     * A submission that is not JSON is shown as it was stored, and an empty one says so
     *
     * @return void
     */
    #[Test]
    public function anUnreadableSubmissionIsShownVerbatim(): void
    {
        $unreadable = $this->render('not <json>');
        self::assertSame('not <json>', $unreadable->querySelector('pre')?->textContent);
        self::assertStringContainsString('not a readable submission', $unreadable->body->textContent ?? '');

        foreach (['', '[]', '{}'] as $empty) {
            $page = $this->render($empty);
            self::assertNull($page->querySelector('table'), $empty);
            self::assertStringContainsString('Nothing was recorded.', $page->body->textContent ?? '', $empty);
        }
    }

    /**
     * Render the element for a stored submission
     *
     * @param string $value Stored submission
     *
     * @return HTMLDocument
     */
    private function render(string $value): HTMLDocument
    {
        $element = new SubmissionElement();
        $element->setData(['parameterArray' => ['itemFormElValue' => $value]]);
        $html = $element->render()['html'] ?? '';

        return HTMLDocument::createFromString(
            '<!DOCTYPE html><html lang="en"><body>' . (\is_string($html) ? $html : ''),
            LIBXML_NOERROR,
        );
    }

    /**
     * Headings of the tables
     *
     * @param HTMLDocument $page Page
     *
     * @return list<string>
     */
    private function headings(HTMLDocument $page): array
    {
        return array_values(array_map(
            static fn(Element $heading): string => trim($heading->textContent ?? ''),
            iterator_to_array($page->querySelectorAll('h4')),
        ));
    }

    /**
     * Rows of a table by their heading
     *
     * @param Element $table Table
     *
     * @return array<string, string>
     */
    private function rows(Element $table): array
    {
        $rows = [];
        foreach ($table->querySelectorAll('tr') as $row) {
            $heading = $row->querySelector('th');
            $cell = $row->querySelector('td');
            $rows[trim($heading->textContent ?? '')] = $cell->textContent ?? '';
        }

        return $rows;
    }
}
