<?php

/**
 * Guards for the forms of EXT:form that ask for protection
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Form;

use Jkphl\Antibot\Domain\Exceptions\RuntimeException;
use Jkphl\Antibot\Ports\Exceptions\InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Tollwerk\TwAntibot\Protection\Guard;
use Tollwerk\TwAntibot\Protection\GuardFactory;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Service\ExtensionService;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;

/**
 * Guards for the forms of EXT:form that ask for protection
 *
 * A form asks with the rendering option "antibot": true for the defaults, or the options of
 * Tollwerk\TwAntibot\Protection\Options. The decision lies with the form, not with the installation.
 *
 * A form submits under the namespace of the plugin that renders it, such as "tx_form_formframework", and its
 * identifier; the guard looks for the protective fields there. Only the first step of a form carries them and is
 * judged: identifiers of elements are unique in a form, and a later step can only be reached with a form state that
 * TYPO3 signs and hands out only after the first step passed.
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final readonly class FormGuards
{
    /**
     * Rendering option a form asks for protection with
     *
     * @var string
     */
    public const string OPTION = 'antibot';

    /**
     * Constructor
     *
     * @param GuardFactory     $guards           Guards of the request
     * @param ExtensionService $extensionService Namespaces of plugins
     */
    public function __construct(private GuardFactory $guards, private ExtensionService $extensionService) {}

    /**
     * Guard of a form
     *
     * @param FormDefinition   $form    Form
     * @param RequestInterface $request Request of the plugin that renders the form
     *
     * @return Guard|null Null if the form does not ask for protection
     *
     * @throws InvalidArgumentException If a field of the form has an invalid name
     * @throws RuntimeException If the checks are set up twice
     */
    public function guard(FormDefinition $form, RequestInterface $request): ?Guard
    {
        $option = $form->getRenderingOptions()[self::OPTION] ?? null;
        if (!\is_array($option) && true !== $option) {
            return null;
        }

        return $this->guards->create(
            $request,
            $form->getIdentifier(),
            \is_array($option) ? $option : [],
            [$this->namespace($request), $form->getIdentifier()],
        );
    }

    /**
     * Whether the request sends the first step of a form
     *
     * __currentPage names the step shown next. Sending the first step of a form with several steps, or a form with
     * one, sets it to 1; a submission without it counts as the first step, so that a bare POST is judged.
     *
     * @param FormDefinition   $form    Form
     * @param RequestInterface $request Request of the plugin that renders the form
     *
     * @return boolean
     */
    public function isFirstStep(FormDefinition $form, RequestInterface $request): bool
    {
        if ('POST' !== strtoupper($request->getMethod())) {
            return false;
        }

        $body = $request->getParsedBody();
        $plugin = \is_array($body) ? $body[$this->namespace($request)] ?? null : null;
        $values = \is_array($plugin) ? $plugin[$form->getIdentifier()] ?? null : null;
        if (!\is_array($values)) {
            return false;
        }

        $page = $values['__currentPage'] ?? 1;

        return is_numeric($page) && 1 === (int) $page;
    }

    /**
     * Namespace the plugin submits under
     *
     * @param ServerRequestInterface $request Request of the plugin
     *
     * @return string
     */
    private function namespace(ServerRequestInterface $request): string
    {
        if (!$request instanceof RequestInterface) {
            return 'tx_form_formframework';
        }

        return $this->extensionService->getPluginNamespace(
            $request->getControllerExtensionName(),
            $request->getPluginName(),
        );
    }
}
