<?php
/**
 * Carbon JSON to HTML converter
 *
 * @author  Adam McCann (@AssembledAdam)
 * @license MIT (see LICENSE file)
 */
namespace Candybanana\CarbonJsonToHtml\Components;

use stdClass;
use DOMDocument;
use DOMElement;
use voku\helper\UTF8;
use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Formats
 */
class Formats
{
    /**
     * Text to format
     *
     * @var string
     */
    protected $text;

    /**
     * Array of formatting options
     *
     * @var array
     */
    protected $formats;

    /**
     * DOM Document
     *
     * @var \DOMDocument
     */
    protected $dom;

    /**
     * DOM Paragraph node
     *
     * @var \DOMElement
     */
    protected $paragraph;

    /**
     * Array of custom attributes to apply to formatting tags within the text
     *
     * @var array
     */
    protected $customAttrs;

    /**
     * Set up the class. Expects the text to format, an array of carbon formatting key/values,
     * and optional custom attributes. Custom attributes are an array of closures, with
     * a key corrisponding to the type/tag it applies to. For example:
     *
     * $customAttrs = [
     *     'a' => function ($attrs, $text) {
     *         return [
     *             'rel' => 'nofollow'
     *         ];
     *     }
     * ];
     *
     * @param  \stdClass
     * @param  \DOMDocument
     * @param  \DOMElement
     * @param  array
     */
    public function __construct(stdClass $json, DOMDocument $dom, DOMElement $paragraph, array $customAttrs = null)
    {
        $this->text = $json->text;

        if (empty($json->formats)) {
            return $this;
        }

        $this->formats     = $json->formats;
        $this->dom         = $dom;
        $this->paragraph   = $paragraph;
        $this->customAttrs = $customAttrs; // sort this out in a constructor
    }

    /**
     * Render formatting options onto text
     */
    public function render()
    {
        $offset = 0;

        foreach ($this->formats as $format) {

            $attrs = $this->attributes($format->type, (! empty($format->attrs) ? $format->attrs : null));

            $opening = '<' . $format->type . "$attrs>";
            $closing = '</' . $format->type . '>';

            $this->text = UTF8::substr_replace($this->text, $opening, $format->from + $offset, 0);

            $offset += strlen($opening);

            $this->text = UTF8::substr_replace($this->text, $closing, $format->to + $offset, 0);

            $offset += strlen($closing);
        }

        // create a temporary document and load the plain html
        $tmpDoc = new DOMDocument;

        // purify HTML to convert HTML chars in text nodes etc.
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Trusted', true);
        $this->text = (new HTMLPurifier($config))->purify($this->text);

        $tmpDoc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $this->text . '</body></html>');
        $tmpDoc->encoding = 'UTF-8';

        // import and attach the created nodes to the paragraph
        foreach ($tmpDoc->getElementsByTagName('body')->item(0)->childNodes as $node) {

            $node = $this->dom->importNode($node, true);
            $this->paragraph->appendChild($node);
        }

        return $this->paragraph;
    }

    /**
     * Create a string of attributes to append to an html tag
     *
     * @param  string
     * @param  \stdClass
     * @return stirng
     */
    public function attributes($type, stdClass $attrs = null)
    {
        $attrs = $attrs ? get_object_vars($attrs) : [];

        // do we have any custom attributes?
        if (! empty($this->customAttrs[$type])) {

            $customAttrs = $this->customAttrs[$type];

            $attrs = array_merge($attrs, $customAttrs($attrs, $this->text));
        }

        $string = '';

        foreach ($attrs as $attr => $value) {

            $string .= ' ' . $attr . '="' . htmlentities($value) . '"';
        }

        return $string;
    }
}
