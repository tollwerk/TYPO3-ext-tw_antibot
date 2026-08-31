<?php

/**
 * tollwerk
 *
 * @category   Tollwerk
 * @package    Tollwerk\TwAntibot
 * @subpackage Tollwerk\TwAntibot\Hooks
 * @author     Joschi Kuphal <joschi@tollwerk.de>
 * @copyright  Copyright © 2026 Joschi Kuphal <joschi@tollwerk.de>
 * @license    http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Hooks;

use Jkphl\Antibot\Domain\Contract\ValidationResultInterface;
use Jkphl\Antibot\Ports\Antibot;
use Jkphl\Antibot\Ports\Validators\BanValidator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Tollwerk\TwAntibot\Store\BlacklistStore;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\Renderable\RootRenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Form Rendering Hook
 *
 * Wraps the rendering of a protected form. Every way a form can reach the frontend —
 * the form content element, a `form:render` view helper, a form built by a factory in
 * an extension of its own — ends in FluidFormRenderer::render(), and that is where this
 * hook is called. It is therefore the one place that covers all of them.
 *
 * The renderer resolves the template name *after* the hook has run, so exchanging it
 * here replaces the entire output of the form. The block template lives in a path of
 * this extension that carries nothing else, so the templates of the surrounding project
 * keep precedence for everything but that one name.
 */
class FormHook implements SingletonInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Template rendered instead of a form the client may not use
     *
     * @var string
     */
    const TEMPLATE_BLOCKED = 'AntibotBlocked';
    /**
     * Validation results, indexed by form identifier
     *
     * @var ValidationResultInterface[]
     */
    protected $results = [];

    /**
     * Constructor
     *
     * TYPO3 replaces the logger when it instantiates this hook. The fallback stands for
     * the case where someone constructs it directly.
     */
    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * Wrap the rendering of a form
     *
     * @param FormRuntime $formRuntime            Form runtime
     * @param RootRenderableInterface $renderable  Renderable
     */
    public function beforeRendering(FormRuntime $formRuntime, RootRenderableInterface $renderable): void
    {
        // The hook is called twice: once per form by the renderer, and once per
        // renderable by the view helper. Only the first carries the form definition,
        // and only it may exchange the template.
        if (!$renderable instanceof FormDefinition) {
            return;
        }

        $result = $this->validate($renderable);
        if ($result === null) {
            return;
        }

        // A client that is banned or has just given itself away sees the notice
        // instead of the form
        if ($result->isBlacklisted() || $result->isFailed()) {
            $renderable->setRenderingOption('templateName', self::TEMPLATE_BLOCKED);
        }
    }

    /**
     * Validate the current request against a form
     *
     * @param FormDefinition $formDefinition Form definition
     *
     * @return ValidationResultInterface|null Validation result (NULL if unprotected)
     */
    protected function validate(FormDefinition $formDefinition): ?ValidationResultInterface
    {
        $identifier = $formDefinition->getIdentifier();
        if (array_key_exists($identifier, $this->results)) {
            return $this->results[$identifier];
        }

        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request === null) {
            return $this->results[$identifier] = null;
        }

        $antibot = $this->createAntibot($identifier);
        $result  = $antibot->validate($request);

        if ($result->isBlacklisted()) {
            $this->logger->warning(
                'Antibot refused a banned client',
                ['form' => $identifier, 'reasons' => $result->getBlacklists()]
            );
        }

        return $this->results[$identifier] = $result;
    }

    /**
     * Create the Antibot instance guarding a form
     *
     * @param string $identifier Form identifier
     *
     * @return Antibot Antibot instance
     */
    protected function createAntibot(string $identifier): Antibot
    {
        $antibot = new Antibot($this->getSessionIdentifier(), $identifier);
        $antibot->setSecret(strval($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? ''));
        $antibot->setLogger($this->logger);
        $antibot->addValidator(new BanValidator(GeneralUtility::makeInstance(BlacklistStore::class)));

        return $antibot;
    }

    /**
     * Return the session-persistent identifier of the current visitor
     *
     * @return string Session identifier
     */
    protected function getSessionIdentifier(): string
    {
        $frontendUser = $GLOBALS['TSFE']->fe_user ?? null;

        return ($frontendUser === null) ? '' : $frontendUser->getSessionId();
    }
}
