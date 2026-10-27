<?php
namespace PonePaste;

use ParsedownExtra;

class Pastedown extends ParsedownExtra {
    public function __construct() {
        parent::__construct();
        unset($this->BlockTypes['>']);
        unset($this->BlockTypes['<']);
        $this->InlineTypes['>'] = ['Greentext'];
        array_unshift($this->InlineTypes['<'], 'Redtext');
        $this->InlineTypes['@'] = ['Purpletext'];
        $this->inlineMarkerList .= '>@';
    }

    protected function buildFootnoteElement()
    {
        $element = [
            'name' => 'div',
            'attributes' => ['class' => 'footnotes'],
            'handler' => 'elements',
            'allowRawHtmlInSafeMode' => true,
            'text' => [
                ['name' => 'hr'],
                [
                    'name' => 'ol',
                    'handler' => 'elements',
                    'allowRawHtmlInSafeMode' => true,
                    'text' => [],
                ],
            ],
        ];

        uasort($this->DefinitionData['Footnote'], 'self::sortFootnotes');

        foreach ($this->DefinitionData['Footnote'] as $definitionId => $definitionData) {
            if (!isset($definitionData['number'])) {
                continue;
            }

            $text = parent::text($definitionData['text']);
            if (strpos($text, '<p>') === 0) {
                $text = '<p>' . $definitionData['number'] . '. ' . substr($text, 3);
            } else {
                $text = $definitionData['number'] . '. ' . $text;
            }

            $backLinksMarkup = '';
            $escapedDefinitionId = self::escape($definitionId);

            foreach (range(1, $definitionData['count']) as $number) {
                $backLinksMarkup .= ' <a href="#fnref' . $number . ':' . $escapedDefinitionId
                    . '" rev="footnote" class="footnote-backref">&#8617;</a>';
            }

            $backLinksMarkup = substr($backLinksMarkup, 1);

            if (substr($text, -4) === '</p>') {
                $text = substr_replace($text, '&#160;' . $backLinksMarkup . '</p>', -4);
            } else {
                $text .= "\n" . '<p>' . $backLinksMarkup . '</p>';
            }

            $element['text'][1]['text'][] = [
                'name' => 'li',
                'attributes' => ['id' => 'fn:' . $definitionId],
                'rawHtml' => "\n" . $text . "\n",
                'allowRawHtmlInSafeMode' => true,
            ];
        }

        return $element;
    }

    protected function inlineFootnoteMarker($Excerpt)
    {
        $marker = parent::inlineFootnoteMarker($Excerpt);

        if (!isset($marker)) {
            return null;
        }

        $element = $marker['element'];
        $element['element'] = $element['text'];
        unset($element['handler'], $element['text']);
        $marker['element'] = $element;

        return $marker;
    }

    protected function inlineGreentext($Line)
    {
        if ($this->isLineStart($Line) && preg_match('/^>[ ]?(.*?)( {2,})?(?=\n|$)/', $Line['text'], $matches)) {
            return [
                'extent' => strlen($matches[0]) - strlen($matches[2] ?? ''),
                'element' => [
                    'name' => 'span',
                    'rawHtml' => '&gt;' . $this->escapeColoredText($matches[1]),
                    'allowRawHtmlInSafeMode' => true, // safe to do because the content is escaped by escapeColoredText()
                    'attributes' => [
                        'class' => 'greentext'
                    ]
                ]
            ];
        }
    }

    protected function inlineRedtext($Line)
    {
        if ($this->isLineStart($Line) && preg_match('/^<[ ]?(.*?)( {2,})?(?=\n|$)/', $Line['text'], $matches)) {
            return [
                'extent' => strlen($matches[0]) - strlen($matches[2] ?? ''),
                'element' => [
                    'name' => 'span',
                    'rawHtml' => '&lt;' . $this->escapeColoredText($matches[1]),
                    'allowRawHtmlInSafeMode' => true, // safe to do because the content is escaped by escapeColoredText()
                    'attributes' => [
                        'class' => 'redtext'
                    ]
                ]
            ];
        }
    }

    protected function inlinePurpletext($Line)
    {
        if ($this->isLineStart($Line) && preg_match('/^@[ ]?(.*?)( {2,})?(?=\n|$)/', $Line['text'], $matches)) {
            return [
                'extent' => strlen($matches[0]) - strlen($matches[2] ?? ''),
                'element' => [
                    'name' => 'span',
                    'rawHtml' => '&#64;' . $this->escapeColoredText($matches[1]),
                    'allowRawHtmlInSafeMode' => true, // safe to do because the content is escaped by escapeColoredText()
                    'attributes' => [
                        'class' => 'purpletext'
                    ]
                ]
            ];
        }
    }

    private function isLineStart($Line): bool
    {
        $position = strlen($Line['context']) - strlen($Line['text']);

        return $position === 0 || $Line['context'][$position - 1] === "\n";
    }

    private function escapeColoredText(string $text): string
    {
        return str_replace('@', '&#64;', self::escape($text));
    }
}