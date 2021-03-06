<?php
/**
 * Carbon JSON to HTML converter
 *
 * @author  Adam McCann (@AssembledAdam)
 * @license MIT (see LICENSE file)
 */
namespace Candybanana\CarbonJsonToHtml;

use DOMDocument;
use DOMElement;

/**
 * Converter
 */
class Converter
{
    /**
     * A representation of the HTML document we are building
     *
     * @var \DomDocument
     */
    protected $dom;

    /**
     * An object representing the JSON to convert
     *
     * @var string
     */
    protected $json;

    /**
     * Array of default components and their configurations, representing Carbon components
     *
     * @var array
     */
    protected $defaultComponents = [
        'Section',
        'Layout',
        'Paragraph',
        'Figure',
        'ListComponent',
        'EmbeddedComponent',
        'HTMLComponent',
    ];

    /**
     * Array of instantiated components
     *
     * @var array
     */
    protected $components = [];

    /**
     * Array of custom HTML elements to insert at paragraph points (specified by key) - starts at 1
     * Note this gets applied to every section.
     *
     * @var array
     */
    protected $customInserts = [];

    /**
     * Conter for parent paragraphs
     *
     * @var int
     */
    protected $parentParagraphCount = 1;

    /**
     * Constructor
     *
     * @return string
     */
    public function __construct()
    {
        $this->dom = new DOMDocument('1.0', 'utf-8');

        // add default components
        foreach ($this->defaultComponents as $componentName => $config) {

            // do we have a config?
            if (! is_array($config)) {
                $componentName = $config;
                $config = [];
            }

            $component = '\\Candybanana\\CarbonJsonToHtml\\Components\\' . ucfirst($componentName);
            $component = new $component($config);

            $this->addComponent($component->getName(), $component);
        }
    }

    /**
     * Adds a component parser
     *
     * @param  string
     * @param  \Candybanana\CarbonJsonToHtml\Components\ComponentInterface
     * @return \Candybanana\CarbonJsonToHtml\Converter
     */
    public function addComponent($componentName, Components\ComponentInterface $component)
    {
        $this->components[$componentName] = $component;

        return $this;
    }

    /**
     * Perform the conversion
     *
     * @param  string  Input json to convert
     * @param  array   Custom HTML to insert - array [{paragraph_num} => {Closure} (,{paragraph_num} => {Closure})]
     * @return string  HTML
     */
    public function convert($json, $customInserts = null)
    {
        $this->customInserts = $customInserts;

        if (($this->json = json_decode($json)) === null) {

            throw new Exceptions\NotTraversableException(
                'The JSON provided is not valid'
            );
        }

        // sections is *always* our first node
        if (! isset($this->json->sections)) {

            throw new Exceptions\InvalidStructureException(
                'The JSON provided is not in a Carbon Editor format.'
            );
        }

        $this->convertRecursive($this->json->sections);

        return trim($this->dom->saveHTML($this->dom->documentElement));
    }

    /**
     * Recursively walk the object and build the HTML
     *
     * @param  array
     */
    protected function convertRecursive(array $json, DOMElement $parentElement = null)
    {
        foreach ($json as $key => $jsonNode) {

            $component = ucfirst($jsonNode->component);

            // insert custom code in between valid paragraphs
            if ($component == 'Paragraph' && $jsonNode->paragraphType == 'p') {

                if (isset($this->customInserts[$this->parentParagraphCount])) {

                    // add up all previous paragraphs
                    $totalPrev = 0;
                    for ($i = 0; $i <= $key; $i++) {
                        if (! empty($json[$key - $i]->text)) {
                            $totalPrev += strlen($json[$key - $i]->text);
                        }
                    }

                    reset($this->customInserts);
                    $firstKey = key($this->customInserts);

                    // if current paragraph is long enough,
                    // or if this is the first custom insert and there's been sufficient text previously
                    if (strlen($jsonNode->text) > 120 || $this->parentParagraphCount == $firstKey && $totalPrev > 120) {
                        $parentElement = $this->customInserts[$this->parentParagraphCount]($this->dom, $parentElement);
                    }
                }

                $this->parentParagraphCount++;
            }

            if (empty($this->components[$component])) {

                throw new Exceptions\InvalidStructureException(
                    "The JSON contains the component '$component', but that isn't loaded."
                );
            }

            $element = $this->components[$component]->parse($jsonNode, $this->dom, $parentElement);

            if (isset($jsonNode->components)) {
                $this->convertRecursive($jsonNode->components, $element);
            }
        }
    }

    protected function insertCustomCode()
    {

    }
}
