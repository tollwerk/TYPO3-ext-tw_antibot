<?php

/**
 * Renders the pages of the browser tests to var/browser
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

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Psr\Container\ContainerExceptionInterface;
use Tollwerk\TwBase\Component\Example\Examples;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use UnexpectedValueException;

/**
 * Renders the pages of the browser tests to var/browser
 *
 * The protective fields are those of the example of the component; the browser tests check where they are and that
 * nobody reaches them, not what jkphl/antibot makes of them.
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class BrowserPagesTest extends FunctionalTestCase
{
    /**
     * Paths of the extensions as the server of the browser tests serves them
     *
     * @var array<string, string>
     */
    private const array PATHS = [
        'EXT:tw_antibot/' => '/',
        'EXT:tw_base/' => '/vendor/tollwerk/tw-base/',
    ];

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
     * Render the protected form and the notice
     *
     * @return void
     *
     * @throws ContainerExceptionInterface If a service is missing
     * @throws InvalidArgumentException If the address of the request is invalid
     * @throws UnexpectedValueException If the examples are invalid
     */
    #[Test]
    public function pagesRender(): void
    {
        $folder = \dirname(__DIR__, 2) . '/var/browser';
        if (!is_dir($folder)) {
            mkdir($folder, 0o777, true);
        }

        $file = \dirname(__DIR__, 2) . '/Resources/Private/Components/Antibot/Fields/Fields.examples.php';
        $fields = Examples::fromFile($file)->examples['default']->arguments['fields'] ?? [];

        $form = $this->render(['fields' => $fields, 'contact' => '']);
        self::assertStringContainsString('class="AntibotFields" hidden', $form);
        $stylesheet = '<link rel="stylesheet" href="/Resources/Public/Components/Antibot/Fields/Fields.css';
        self::assertStringContainsString($stylesheet, $form);
        file_put_contents($folder . '/form.html', $form);

        $blocked = $this->render(['fields' => [], 'contact' => 'hello@example.org']);
        self::assertStringContainsString('href="mailto:hello@example.org"', $blocked);
        file_put_contents($folder . '/blocked.html', $blocked);
    }

    /**
     * Render the page, with the stylesheets of the components it used
     *
     * @param array<string, mixed> $variables Variables of the page
     *
     * @return string
     *
     * @throws ContainerExceptionInterface If a service is missing
     * @throws InvalidArgumentException If the address of the request is invalid
     */
    private function render(array $variables): string
    {
        $assets = $this->get(AssetCollector::class);
        $empty = array_map(static fn(mixed $value): mixed => \is_array($value) ? [] : $value, $assets->getState());
        $assets->updateState($empty);
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templatePathAndFilename: __DIR__ . '/../Browser/Fixtures/Page.fluid.html',
            request: $this->request(),
        ));
        $view->assignMultiple([...$variables, 'resources' => '<!--resources-->']);
        $html = $view->render();

        $resources = [];
        foreach (array_column($assets->getInlineStyleSheets(), 'source') as $source) {
            $resources[] = '<style>' . (\is_string($source) ? $source : '') . '</style>';
        }

        foreach (array_column($assets->getStyleSheets(), 'source') as $source) {
            $href = strtr(\is_string($source) ? $source : '', self::PATHS);
            $resources[] = '<link rel="stylesheet" href="' . $href . '">';
        }

        return str_replace('<!--resources-->', implode("\n    ", $resources), $html);
    }

    /**
     * Request in English
     *
     * @return ServerRequest
     *
     * @throws InvalidArgumentException If the address is invalid
     */
    private function request(): ServerRequest
    {
        return new ServerRequest('https://example.org/')
            ->withAttribute('language', new SiteLanguage(0, 'en-US', new Uri('https://example.org/'), []))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
    }
}
