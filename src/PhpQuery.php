<?php

namespace P0n0marev\PhpQuery;

use Closure;
use DOMDocument;
use DOMElement;
use DOMXPath;

class PhpQuery implements PhpQueryInterface
{
    private readonly DOMXPath $xpath;

    public function __construct(
        public string $data,
    )
    {
        $this->buildXpath();

        return $this;
    }

    public function length(): int
    {
        return $this->xpath->query('/root/*')->count();
    }

    public function count(): int
    {
        return $this->length();
    }

    public function attr(string $attributeName, string $newValue = null): string|self
    {
        $nodeList = $this->xpath->query('/root/*');

        if ($newValue === null) {
            $result = '';
            /** @var DOMElement $node */
            foreach ($nodeList as $node) {
                $result .= $node->getAttribute($attributeName);
            }
        } else {
            $data = '';
            /** @var DOMElement $node */
            foreach ($nodeList as $node) {
                $node->setAttribute($attributeName, $newValue);
                $data .= $node->C14N();
            }
            $result = new self($data);
        }

        return $result;
    }

    public function html(): string
    {
        $result = '';

        $nodeList = $this->xpath->query('/root/*');
        /** @var DOMElement $node */
        foreach ($nodeList as $node) {
            $result .= $node->C14N();
        }

        return $result;
    }

    public function text(string $newText = null): string|self
    {
        $nodeList = $this->xpath->query('/root/*');

        if ($newText === null) {
            $result = '';
            /** @var DOMElement $node */
            foreach ($nodeList as $node) {
                $result .= $node->textContent;
            }
        } else {
            $data = '';
            /** @var DOMElement $node */
            foreach ($nodeList as $node) {
                $node->textContent = $newText;
                $data .= $node->C14N();
            }
            $result = new self($data);
        }

        return $result;
    }

    public function first(): self
    {
        $nodeList = $this->xpath->query('/root/*[1]');
        $data = $nodeList->item(0)->C14N();

        return new self($data);
    }

    public function last(): self
    {
        $nodeList = $this->xpath->query('/root/*[last()]');
        $data = $nodeList->item(0)->C14N();

        return new self($data);
    }

    public function find(string $selector): self
    {
        $query = $this->getXPath($selector);
        $nodeList = $this->xpath->query($query);
        $data = '';
        /** @var DOMElement $node */
        foreach ($nodeList as $node) {
            $data .= $node->C14N();
        }

        return new self($data);
    }

    public function each(Closure $callback): self
    {
        $data = '';

        $nodeList = $this->xpath->query('/root/*');
        /** @var DOMElement $node */
        foreach ($nodeList as $index => $node) {
            $pq = $callback(new self($node->C14N()), $index);
            if($pq instanceof self) {
                $data .= $pq->html();
            } else {
                $data .= $node->C14N();
            }
        }

        return new self($data);
    }

    private function getXPath(string $query): string
    {
        $steps = [];

        $selectors = explode(' ', $query);
        foreach ($selectors as $selector) {
            $selector = trim($selector);

            $matches = [];
            preg_match_all('/#\w+/', $selector, $matches);
            $ids = reset($matches);
            if (!empty($ids)) {
                $steps[] = [
                    'type'  => 'id',
                    'value' => reset($ids),
                ];
                continue;
            }

            $matches = [];
            preg_match_all('/(?<=\.)[\w-]+/', $selector, $matches);
            $classes = reset($matches);
            if (!empty($classes)) {
                $steps[] = [
                    'type'  => 'class',
                    'value' => reset($classes),
                ];
                continue;
            }

            $matches = [];
            preg_match_all('/(?<=\[)\w+=?[\"\']?\w+[\"\']?(?=\])/', $selector, $matches);
            $attributes = reset($matches);
            if (!empty($attributes)) {
                $steps[] = [
                    'type'  => 'attribute',
                    'value' => reset($attributes),
                ];
                continue;
            }

            $steps[] = [
                'type'  => 'tag',
                'value' => $selector,
            ];
        }

        $xpath = [];
        foreach ($steps as $selector) {
            if ($selector['type'] == 'id') {
                $xpath[] = sprintf('*[@id="%s"]', substr($selector['value'], 1));
            } elseif ($selector['type'] == 'class') {
                $xpath[] = sprintf('*[@class="%s"]', $selector['value']);
            } elseif ($selector['type'] == 'attribute') {
                $xpath[] = sprintf('*[@%s]', $selector['value']);
            } else {
                $xpath[] = $selector['value'];
            }
        }
        $xpath = array_filter($xpath);

        return '//' . implode('//', $xpath);
    }

    private function buildXpath(): void
    {
        $doc = new DOMDocument;
        $doc->loadHTML(sprintf('<root>%s</root>', $this->data), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);
        $this->xpath = new DOMXPath($doc);
    }
}
