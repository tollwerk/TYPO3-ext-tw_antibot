<?php

/**
 * Shows the submission of a ban as tables of fields instead of the JSON it is stored as
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Backend;

use Override;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;

/**
 * Shows the submission of a ban as tables of fields instead of the JSON it is stored as
 *
 * Whoever opens a ban wants to see what arrived. The first table has what somebody typed, in full; the second what
 * the form carried along, such as its state and the protective fields, shortened. The protective fields are named
 * "antibot" instead of their hashed names. The submission cannot be edited.
 *
 * @phpstan-type Row array{path: list<string>, value: mixed}
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class SubmissionElement extends AbstractFormElement
{
    /**
     * Translations of the backend
     *
     * @var string
     */
    private const string LABELS = 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:';

    /**
     * Fields a form carries along, besides the protective fields
     *
     * @var list<string>
     */
    private const array CARRIED = ['__state', '__trustedProperties', '__currentPage', '__referrer', '__session'];

    /**
     * Name of protective fields: a prefix and a hash of 40 characters
     *
     * @var string
     */
    private const string PROTECTIVE = '/^.+_[0-9a-f]{40}$/';

    /**
     * Characters of a value that is carried along, before it is shortened
     *
     * @var integer
     */
    private const int LENGTH = 120;

    /**
     * Render the submission
     *
     * @return array<array-key, mixed>
     */
    #[Override]
    public function render(): array
    {
        $result = $this->initializeResultArray();
        $parameters = $this->data['parameterArray'] ?? [];
        $raw = \is_array($parameters) && \is_string($parameters['itemFormElValue'] ?? null)
            ? $parameters['itemFormElValue']
            : '';
        $data = json_decode($raw, true);
        if (!\is_array($data)) {
            $result['html'] = $this->verbatim($raw);

            return $result;
        }

        $submitted = [];
        $carried = [];
        foreach (self::flatten($data) as $row) {
            if ($this->isCarried($row['path'])) {
                $carried[] = $row;

                continue;
            }

            $submitted[] = $row;
        }

        $result['html'] = [] === $submitted && [] === $carried
            ? $this->verbatim('')
            : $this->table($submitted, 'submission.submitted', false)
                . $this->table($carried, 'submission.carried', true);

        return $result;
    }

    /**
     * Rows of nested values
     *
     * @param array<array-key, mixed> $data Values
     * @param list<string>            $path Path to the values
     *
     * @return list<Row>
     */
    private static function flatten(array $data, array $path = []): array
    {
        $rows = [];
        foreach ($data as $key => $value) {
            $keyPath = [...$path, (string) $key];
            if (\is_array($value) && [] !== $value) {
                $rows = [...$rows, ...self::flatten($value, $keyPath)];

                continue;
            }

            $rows[] = ['path' => $keyPath, 'value' => $value];
        }

        return $rows;
    }

    /**
     * Whether a value was carried along by the form
     *
     * @param list<string> $path Path of the value
     *
     * @return boolean
     */
    private function isCarried(array $path): bool
    {
        return array_any(
            $path,
            static fn(string $segment): bool => \in_array($segment, self::CARRIED, true)
                || 1 === preg_match(self::PROTECTIVE, $segment),
        );
    }

    /**
     * Table of values, with the paths shortened by their common beginning
     *
     * @param list<Row> $rows    Rows
     * @param string    $caption Translation of the caption
     * @param boolean   $shorten Whether long values are shortened
     *
     * @return string
     */
    private function table(array $rows, string $caption, bool $shorten): string
    {
        if ([] === $rows) {
            return '';
        }

        $common = $this->common($rows);
        $cells = '';
        foreach ($rows as $row) {
            $path = \array_slice($row['path'], $common);
            $cells .= '<tr><th scope="row">' . $this->path([] === $path ? \array_slice($row['path'], -1) : $path)
                . '</th><td>' . $this->value($row['value'], $shorten) . '</td></tr>';
        }

        return '<h4>' . htmlspecialchars($this->translate($caption)) . '</h4>'
            . '<table class="table table-striped table-hover"><tbody>' . $cells . '</tbody></table>';
    }

    /**
     * Number of segments all paths begin with, leaving each row at least one
     *
     * @param non-empty-list<Row> $rows Rows
     *
     * @return integer
     */
    private function common(array $rows): int
    {
        $common = $rows[0]['path'];
        $shortest = PHP_INT_MAX;
        foreach ($rows as $row) {
            $shared = 0;
            while (isset($common[$shared], $row['path'][$shared]) && $common[$shared] === $row['path'][$shared]) {
                ++$shared;
            }

            $common = \array_slice($common, 0, $shared);
            $shortest = min($shortest, \count($row['path']));
        }

        return min(\count($common), $shortest - 1);
    }

    /**
     * Path of a value, with the protective fields named "antibot"
     *
     * @param list<string> $path Path
     *
     * @return string
     */
    private function path(array $path): string
    {
        $segments = array_map(
            static fn(string $segment): string => htmlspecialchars(
                (string) preg_replace(self::PROTECTIVE, 'antibot', $segment),
            ),
            $path,
        );

        return implode(' <span class="text-muted">›</span> ', $segments);
    }

    /**
     * Value; what somebody typed is never shortened
     *
     * @param mixed   $value   Value
     * @param boolean $shorten Whether a long value is shortened
     *
     * @return string
     */
    private function value(mixed $value, bool $shorten): string
    {
        if (\is_array($value) || null === $value) {
            return '<span class="text-muted">' . htmlspecialchars($this->translate('submission.empty')) . '</span>';
        }

        $text = \is_bool($value) ? ($value ? '1' : '0') : (\is_scalar($value) ? (string) $value : '');
        if ('' === trim($text)) {
            return '<span class="text-muted">' . htmlspecialchars($this->translate('submission.blank')) . '</span>';
        }

        if ($shorten && mb_strlen($text) > self::LENGTH) {
            $text = mb_substr($text, 0, self::LENGTH) . ' …';
        }

        return nl2br(htmlspecialchars($text));
    }

    /**
     * A submission that cannot be read as fields, as it was stored
     *
     * @param string $raw Stored submission
     *
     * @return string
     */
    private function verbatim(string $raw): string
    {
        if ('' === trim($raw)) {
            return '<p class="text-muted">' . htmlspecialchars($this->translate('submission.none')) . '</p>';
        }

        return '<p class="text-muted">' . htmlspecialchars($this->translate('submission.unreadable')) . '</p>'
            . '<pre>' . htmlspecialchars($raw) . '</pre>';
    }

    /**
     * Text of a translation
     *
     * @param string $key Key
     *
     * @return string
     */
    private function translate(string $key): string
    {
        return $this->getLanguageService()->sL(self::LABELS . $key);
    }
}
