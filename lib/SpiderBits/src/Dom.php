<?php

namespace SpiderBits;

/**
 * Searching in a DOM should not be complicated
 *
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Dom
{
    /** @var \DOMDocument */
    private $dom;

    /** @var string */
    private $xpath_query;

    /** @var \DOMNodeList */
    private $nodes_selected;

    /**
     * Return a new Dom object from text.
     *
     * @param string $html_as_string
     *
     * @return \SpiderBits\Dom
     */
    public static function fromText($html_as_string)
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html_as_string, 'HTML-ENTITIES', 'UTF-8'));
        return new self($dom);
    }

    /**
     * @param \DOMDocument $dom
     * @param string $xpath_query default is null
     *
     * @throw \DomainException if the xpath is invalid or matches no nodes
     */
    public function __construct($dom, $xpath_query = null)
    {
        $this->dom = $dom;

        $this->xpath_query = $xpath_query;
        if ($xpath_query) {
            $this->nodes_selected = self::xpathQuery($this->dom, $this->xpath_query);
        }
    }

    /**
     * Select nodes from the dom based on a XPath query.
     *
     * If the query matches no nodes, the method returns null.
     * Note the XPath query is relative to the current selection (i.e. it's
     * appended).
     *
     * @param string $xpath_query
     *
     * @return \SpiderBits\Dom|null
     */
    public function select($xpath_query)
    {
        if ($this->xpath_query) {
            $xpath_query = $this->xpath_query . $xpath_query;
        }

        try {
            $clone_dom = $this->dom->cloneNode(true);
            return new self($clone_dom, $xpath_query);
        } catch (\DomainException $e) {
            return null;
        }
    }

    /**
     * Remove in the current Dom the nodes corresponding to the xpath query
     *
     * Note the XPath query is relative to the current selection (i.e. it's
     * appended).
     *
     * @param string $xpath_query
     */
    public function remove($xpath_query)
    {
        if ($this->xpath_query) {
            $xpath_query = $this->xpath_query . $xpath_query;
        }

        try {
            $nodes_selected = self::xpathQuery($this->dom, $xpath_query);
            foreach ($nodes_selected as $node) {
                $node->parentNode->removeChild($node);
            }
        } catch (\DomainException $e) {
        }
    }

    /**
     * Return the content of the current selected node(s) as a string
     *
     * @return string
     */
    public function text()
    {
        if ($this->nodes_selected) {
            $texts = [];
            foreach ($this->nodes_selected as $node) {
                $texts[] = trim($node->textContent);
            }
            return implode("\n", $texts);
        } else {
            return trim($this->dom->textContent);
        }
    }

    public function nodes()
    {
        if ($this->nodes_selected) {
            return $this->nodes_selected;
        } else {
            return $this->dom;
        }
    }

    /**
     * Return a list of nodes corresponding to a xpath query
     *
     * @param \DOMDocument $dom
     * @param string $xpath_query
     *
     * @throw \DomainException if the xpath is invalid or matches no nodes
     *
     * @return \DOMNodeList
     */
    private static function xpathQuery($dom, $xpath_query)
    {
        $dom_xpath = new \DomXPath($dom);
        $nodes_selected = @$dom_xpath->query($xpath_query);

        if ($nodes_selected === false) {
            throw new \DomainException('XPath query is invalid');
        }

        if ($nodes_selected->length === 0) {
            throw new \DomainException('XPath query matches no nodes');
        }

        return $nodes_selected;
    }
}
