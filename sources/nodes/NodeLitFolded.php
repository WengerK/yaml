<?php

namespace Dallgoot\Yaml;

/**
 *
 * @author  Stéphane Rebai <stephane.rebai@gmail.com>
 * @license Apache 2.0
 * @link    TODO : url to specific online doc
 */
class NodeLitFolded extends NodeLiterals
{
    /**
     * @param NodeList $list The children
     *
     * @return string    The litteral.
     * @todo   Example 6.1. Indentation Spaces  spaces must be considered as content,
     *          Whend indent is reduced : do we insert a line break too ?
     */
    public function getFinalString(NodeList $value, int $refIndent = null):string
    {
        $result = '';
        $list = $value->filterComment();
        if ($this->identifier !== '+') {
             self::litteralStripLeading($list);
             self::litteralStripTrailing($list);
        }
        if ($list->count()) {
            $refSeparator = ' ';
            $first = $list->shift();
            $indent = $refIndent ?? $first->indent;
            $result = $this->getChildValue($first, $indent);
            foreach ($list as $child) {
                $separator = ($result && $result[-1] === "\n") ? '' : $refSeparator;
                if($child->indent > $indent || $child instanceof NodeBlank) {
                    $separator = "\n";
                }
                $result .= $separator .$this->getChildValue($child, $indent);
            }
        }
        return $result;
    }
}