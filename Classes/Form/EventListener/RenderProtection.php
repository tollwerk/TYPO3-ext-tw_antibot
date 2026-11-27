<?php

/**
 * Renders a protected form of EXT:form with its protective fields, or the notice in its place
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Form\EventListener;

use Jkphl\Antibot\Domain\Exceptions\RuntimeException;
use Jkphl\Antibot\Ports\Exceptions\InvalidArgumentException;
use Tollwerk\TwAntibot\Form\FormGuards;
use Tollwerk\TwAntibot\Protection\Guard;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Form\Domain\Exception\TypeDefinitionNotFoundException;
use TYPO3\CMS\Form\Domain\Exception\TypeDefinitionNotValidException;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Event\BeforeRenderableIsRenderedEvent;

/**
 * Renders a protected form of EXT:form with its protective fields, or the notice in its place
 *
 * Every way a form reaches the frontend, the content element, the view helper "formvh:render" or a plugin of its own,
 * ends in the renderer, which dispatches this event for the form before it resolves the template. Exchanging the
 * template here replaces the whole form with the notice, which takes the classes of the form and so its place in the
 * layout. The protective fields join the first step as an element of their own.
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
#[AsEventListener('tollwerk/tw-antibot/render-protection')]
final readonly class RenderProtection
{
    /**
     * Identifier of the element with the protective fields
     *
     * @var string
     */
    public const string ELEMENT = 'antibot-fields';

    /**
     * Template of the notice
     *
     * @var string
     */
    public const string NOTICE = 'AntibotBlocked';

    /**
     * Constructor
     *
     * @param FormGuards $guards Guards of the forms
     */
    public function __construct(private FormGuards $guards) {}

    /**
     * Add the protective fields, or show the notice
     *
     * @param BeforeRenderableIsRenderedEvent $event Event
     *
     * @return void
     *
     * @throws TypeDefinitionNotFoundException If the configuration of EXT:form lacks the element
     * @throws TypeDefinitionNotValidException If the element is configured wrongly
     * @throws InvalidArgumentException If a field of the form has an invalid name
     * @throws RuntimeException If the checks are set up twice
     */
    public function __invoke(BeforeRenderableIsRenderedEvent $event): void
    {
        $form = $event->renderable;
        if (!$form instanceof FormDefinition) {
            return;
        }

        $guard = $this->guards->guard($form, $event->formRuntime->getRequest());
        if (!$guard instanceof Guard) {
            return;
        }

        if ($guard->isBanned() || true === $guard->verdict()?->isClosed()) {
            $this->close($form, $guard);

            return;
        }

        // The submission was accepted but a field is invalid: the corrected one is no repetition
        $guard->forget();
        $this->arm($event->formRuntime, $form, $guard);
    }

    /**
     * Show the notice in place of the form
     *
     * @param FormDefinition $form  Form
     * @param Guard          $guard Guard
     *
     * @return void
     */
    private function close(FormDefinition $form, Guard $guard): void
    {
        $class = $form->getRenderingOptions()['elementClassAttribute'] ?? '';
        $form->setRenderingOption('antibotContact', $guard->contact());
        $form->setRenderingOption('antibotClass', \is_string($class) ? $class : '');
        $form->setRenderingOption('templateName', self::NOTICE);
    }

    /**
     * Add the protective fields to the first step
     *
     * @param FormRuntime    $runtime Runtime of the form
     * @param FormDefinition $form    Form
     * @param Guard          $guard   Guard
     *
     * @return void
     *
     * @throws TypeDefinitionNotFoundException If the configuration of EXT:form lacks the element
     * @throws TypeDefinitionNotValidException If the element is configured wrongly
     */
    private function arm(FormRuntime $runtime, FormDefinition $form, Guard $guard): void
    {
        $page = $runtime->getCurrentPage();
        if (!$page instanceof \TYPO3\CMS\Form\Domain\Model\FormElements\Page || 0 !== $page->getIndex()) {
            return;
        }

        $element = $form->getElementByIdentifier(self::ELEMENT) ?? $page->createElement(self::ELEMENT, 'AntibotFields');
        $element->setRenderingOption(FormGuards::OPTION, $guard->fields());
    }
}
