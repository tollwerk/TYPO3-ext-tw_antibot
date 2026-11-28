<?php

/**
 * Judges the submission of a protected form of EXT:form before its finishers run
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

use Doctrine\DBAL\Exception as DBALException;
use Jkphl\Antibot\Domain\Exceptions\RuntimeException;
use Jkphl\Antibot\Ports\Exceptions\InvalidArgumentException;
use Tollwerk\TwAntibot\Form\FormGuards;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Form\Event\AfterCurrentPageIsResolvedEvent;
use TYPO3\CMS\Form\Exception as FormException;

/**
 * Judges the submission of a protected form of EXT:form before its finishers run
 *
 * Only a submission the form goes on to process is judged: the cached action of a plugin shows the form without
 * looking at the request, and a judgement there would refuse, and ban, a request the form never handles.
 *
 * A submission that completes a form is not rendered again: it goes straight to the finishers, which send the mail.
 * A refused submission therefore goes back to the step it came from, which keeps the finishers from running; the
 * rendering then shows the notice. A banned client gets the same, submission or not.
 *
 * A form that refuses repeated content remembers the content of a submission that goes on to the finishers. The form
 * validates its fields only after this event; if a field is invalid, the form is shown again and RenderProtection
 * forgets the content, so that the corrected submission is not refused as a repetition.
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
#[AsEventListener('tollwerk/tw-antibot/judge-submission')]
final readonly class JudgeSubmission
{
    /**
     * Constructor
     *
     * @param FormGuards $guards Guards of the forms
     */
    public function __construct(private FormGuards $guards) {}

    /**
     * Judge the submission
     *
     * @param AfterCurrentPageIsResolvedEvent $event Event
     *
     * @return void
     *
     * @throws FormException If the form has no first step
     * @throws DBALException If a ban cannot be recorded
     * @throws InvalidArgumentException If a field of the form has an invalid name
     * @throws RuntimeException If the checks are set up twice
     */
    public function __invoke(AfterCurrentPageIsResolvedEvent $event): void
    {
        $form = $event->formRuntime->getFormDefinition();
        $guard = $this->guards->guard($form, $event->request);
        if (!$guard instanceof \Tollwerk\TwAntibot\Protection\Guard) {
            return;
        }

        $closed = $guard->isBanned()
            || (
                $event->formRuntime->canProcessFormSubmission()
                && $this->guards->isFirstStep($form, $event->request)
                && $guard->judge()->isClosed()
            );
        if ($closed) {
            $event->currentPage = $event->lastDisplayedPage ?? $form->getPageByIndex(0);

            return;
        }

        if ($event->currentPage instanceof \TYPO3\CMS\Form\Domain\Model\FormElements\Page) {
            return;
        }

        $guard->remember();
    }
}
