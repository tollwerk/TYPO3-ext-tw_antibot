<?php

/**
 * tollwerk
 *
 * @category   Tollwerk
 * @package    Tollwerk\TwAntibot
 * @subpackage Tollwerk\TwAntibot\Form\Element
 * @author     Joschi Kuphal <joschi@tollwerk.de>
 * @copyright  Copyright © 2026 Joschi Kuphal <joschi@tollwerk.de>
 * @license    http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * Submission Element
 *
 * Shows what somebody sent as a table of fields instead of the JSON it is stored as.
 * Whoever opens a ban record wants to answer one question — was this spam, or did the
 * protection catch someone it should not have — and that answer is in the message, not
 * in the encoding.
 *
 * The field is shown, never edited. What arrived is what arrived; a record of it that
 * can be typed over is worth less than one that cannot.
 */
class SubmissionElement extends AbstractFormElement
{
    /**
     * Language file
     *
     * @var string
     */
    const LLL = 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:';
    /**
     * Fields the form framework carries along on its own
     *
     * @var string[]
     */
    const BOOKKEEPING = ['__state', '__trustedProperties', '__currentPage', '__referrer'];
    /**
     * Characters after which a bookkeeping value is cut short
     *
     * @var int
     */
    const MAXIMUM_LENGTH = 120;

    /**
     * Render the submission
     *
     * @return array Result array
     */
    public function render(): array
    {
        $result = $this->initializeResultArray();
        $raw    = strval($this->data['parameterArray']['itemFormElValue'] ?? '');
        $data   = json_decode($raw, true);

        // A submission that cannot be read back is still evidence and is shown as it is
        if (!is_array($data)) {
            $result['html'] = $this->renderVerbatim($raw);

            return $result;
        }

        $submitted = [];
        $carried   = [];
        foreach ($this->flatten($data) as $row) {
            if ($this->isBookkeeping($row['path'])) {
                $carried[] = $row;
                continue;
            }
            $submitted[] = $row;
        }

        // An array without a single value in it is as empty as an empty field
        if (!count($submitted) && !count($carried)) {
            $result['html'] = $this->renderVerbatim('');

            return $result;
        }

        $result['html'] = $this->renderTable($submitted, 'submission.submitted', false)
                          . $this->renderTable($carried, 'submission.carried', true);

        return $result;
    }

    /**
     * Turn a nested submission into one row per value
     *
     * @param array $data Submission data
     * @param array $path Path of the enclosing keys
     *
     * @return array[] Rows, each with a path and a value
     */
    protected function flatten(array $data, array $path = []): array
    {
        $rows = [];
        foreach ($data as $key => $value) {
            $keyPath = array_merge($path, [strval($key)]);
            if (is_array($value)) {
                // An empty group would otherwise vanish without a trace
                $rows = array_merge(
                    $rows,
                    count($value) ? $this->flatten($value, $keyPath) : [['path' => $keyPath, 'value' => []]]
                );
                continue;
            }

            $rows[] = ['path' => $keyPath, 'value' => $value];
        }

        return $rows;
    }

    /**
     * Test whether a path belongs to the machinery rather than to the submission
     *
     * Two kinds qualify: the fields the form framework adds to every submission, and the
     * armor, which lives under a key made of the form identifier and a signature.
     *
     * @param array $path Path
     *
     * @return bool The path is bookkeeping
     */
    protected function isBookkeeping(array $path): bool
    {
        foreach ($path as $segment) {
            if (in_array($segment, self::BOOKKEEPING, true) || preg_match('/_[0-9a-f]{40}$/', $segment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render one table of rows
     *
     * @param array $rows     Rows
     * @param string $caption Caption key
     * @param bool $shorten   Cut long values short
     *
     * @return string Table HTML (empty if there are no rows)
     */
    protected function renderTable(array $rows, string $caption, bool $shorten): string
    {
        if (!count($rows)) {
            return '';
        }

        $common = $this->countCommonSegments($rows);
        $cells  = '';
        foreach ($rows as $row) {
            $path  = array_slice($row['path'], $common) ?: array_slice($row['path'], -1);
            $cells .= '<tr><th scope="row">' . $this->renderPath($path) . '</th>'
                      . '<td>' . $this->renderValue($row['value'], $shorten) . '</td></tr>';
        }

        return '<h4>' . htmlspecialchars($this->translate($caption)) . '</h4>'
               . '<table class="table table-striped table-hover"><tbody>' . $cells . '</tbody></table>';
    }

    /**
     * Count the leading path segments every row has in common
     *
     * Every field of one form starts with the name of that form, and repeating it in
     * front of each row says nothing. Where all rows share a beginning, it goes.
     *
     * @param array $rows Rows
     *
     * @return int Number of shared leading segments
     */
    protected function countCommonSegments(array $rows): int
    {
        $common = $rows[0]['path'];
        foreach ($rows as $row) {
            $shared = [];
            foreach ($common as $index => $segment) {
                if (($row['path'][$index] ?? null) !== $segment) {
                    break;
                }
                $shared[] = $segment;
            }
            $common = $shared;
        }

        // A row consisting of nothing but the shared beginning must keep something
        return min(count($common), min(array_map(function (array $row): int {
            return count($row['path']);
        }, $rows)) - 1);
    }

    /**
     * Render the path of a value
     *
     * @param array $path Path
     *
     * @return string Path HTML
     */
    protected function renderPath(array $path): string
    {
        $segments = array_map(function (string $segment): string {
            // The armor lives under the form identifier and a signature, and forty
            // hexadecimal characters tell a reader nothing at all
            return htmlspecialchars(preg_replace('/^.+_[0-9a-f]{40}$/', 'antibot', $segment));
        }, $path);

        return implode(' <span class="text-muted">›</span> ', $segments);
    }

    /**
     * Render a single value
     *
     * @param mixed $value  Value
     * @param bool $shorten Cut a long value short
     *
     * @return string Value HTML
     */
    protected function renderValue($value, bool $shorten): string
    {
        if (is_array($value)) {
            return '<span class="text-muted">' . htmlspecialchars($this->translate('submission.empty')) . '</span>';
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        $value = strval($value);
        if (!strlen(trim($value))) {
            return '<span class="text-muted">' . htmlspecialchars($this->translate('submission.blank')) . '</span>';
        }

        // A signed state blob says nothing to anybody and would push the message out of
        // view. What somebody wrote is never cut: that is the part worth reading.
        if ($shorten && (mb_strlen($value) > self::MAXIMUM_LENGTH)) {
            $value = mb_substr($value, 0, self::MAXIMUM_LENGTH) . ' …';
        }

        return nl2br(htmlspecialchars($value));
    }

    /**
     * Render data that is not a readable submission
     *
     * @param string $raw Stored value
     *
     * @return string HTML
     */
    protected function renderVerbatim(string $raw): string
    {
        if (!strlen(trim($raw))) {
            return '<p class="text-muted">' . htmlspecialchars($this->translate('submission.none')) . '</p>';
        }

        return '<p class="text-muted">' . htmlspecialchars($this->translate('submission.unreadable')) . '</p>'
               . '<pre>' . htmlspecialchars($raw) . '</pre>';
    }

    /**
     * Translate a label
     *
     * @param string $key Key
     *
     * @return string Label
     */
    protected function translate(string $key): string
    {
        return strval($this->getLanguageService()->sL(self::LLL . $key));
    }

    /**
     * Return the language service
     *
     * @return LanguageService Language service
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
