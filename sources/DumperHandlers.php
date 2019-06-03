<?php
namespace Dallgoot\Yaml;

// use \SplDoublyLinkedList as DLL;

/**
 *  Convert PHP datatypes to a YAML string syntax
 *
 * @author  Stéphane Rebai <stephane.rebai@gmail.com>
 * @license Apache 2.0
 * @link    https://github.com/dallgoot/yaml
 */
class DumperHandlers
{
    private const INDENT = 2;
    private const OPTIONS = 00000;
    private const DATE_FORMAT = 'Y-m-d';

    private $options;
    private $multipleDocs = false;
    //options
    public const EXPAND_SHORT = 00001;
    public const SERIALIZE_CUSTOM_OBJECTS = 00010;
    public $floatPrecision = 4;

    public function __construct(int $options = null)
    {
        if (is_int($options)) $this->options = $options;
    }



    public function dump($dataType, int $indent):string
    {
        if(is_null($dataType)) {
            return '';
        } elseif(is_resource($dataType)) {
            return get_resource_type($dataType);
        } elseif (is_scalar($dataType)) {
            return $this->dumpScalar($dataType);
        } else {
            return $this->dumpCompound($dataType, $indent);
        }
    }

    public function dumpScalar($dataType):string
    {
        if ($dataType === \INF) return '.inf';
        if ($dataType === -\INF) return '-.inf';
        $precision = "%.".$this->floatPrecision."F";
        switch (gettype($dataType)) {
            case 'boolean': return $dataType ? 'true' : 'false';
            case 'float': //fall through
            case 'double': return is_nan((double) $dataType) ? '.nan' : sprintf($precision, $dataType);
        }
        return $this->dumpString($dataType);
    }


    private function dumpCompound($compound, int $indent):string
    {
        $iterator = null;
        $mask = '%s:';
        if (is_callable($compound)) {
            throw new \Exception("Dumping Callable|Closure is not currently supported", 1);
        } elseif ($compound instanceof YamlObject) {
            return $this->dumpYamlObject($compound);
        } elseif ($compound instanceof Compact) {
             return $this->dumpCompact($compound, $indent);
        } elseif (is_array($compound)) {
            $iterator = new \ArrayIterator($compound);
            $mask = '-';
            $refKeys = range(0, count($compound)-1);
            if (array_keys($compound) !== $refKeys) {
                $mask = '%s:';
            }
        } elseif (is_iterable($compound)) {
            $iterator = $compound;
        } elseif (is_object($compound)) {
            if ($compound instanceof Tagged)     return $this->dumpTagged($compound, $indent);
            //TODO:  consider dumping datetime as date strings according to a format provided by user
            if ($compound instanceof \DateTime)  return $compound->format(self::DATE_FORMAT);
            $iterator = new \ArrayIterator(get_object_vars($compound));
        }
        return $this->iteratorToString($iterator, $mask, $indent);
    }


    private function dumpYamlObject(YamlObject $obj):string
    {
        if ($this->multipleDocs || $obj->hasDocStart() || $obj->isTagged()) {
           $this->multipleDocs = true;
          // && $this->$result instanceof DLL) $this->$result->push("---");
        }
        if (count($obj) > 0) {
            return $this->iteratorToString($obj, '-', 0);
        }
        return $this->iteratorToString(new \ArrayIterator(get_object_vars($obj)), '%s:', 0);
        // $this->insertComments($obj->getComment());
        //TODO: $references = $obj->getAllReferences();
    }


    private function iteratorToString(\Iterator $iterable, string $keyMask, int $indent):string
    {
        $pairs = [];
        foreach ($iterable as $key => $value) {
            $separator = "\n";
            $valueIndent = $indent + self::INDENT;
            if (is_scalar($value) || $value instanceof Compact || $value instanceof \DateTime ) {
                $separator   = ' ';
                $valueIndent = 0;
            }
            $pairs[] = str_repeat(' ', $indent).sprintf($keyMask, $key).$separator.$this->dump($value, $valueIndent);
            //var_dump(str_repeat(' ', $indent)."key($keyMask):$key");
        }
        //var_dump($pairs);
        return implode("\n", $pairs);
    }

    /**
     * Dumps a Compact|mixed (representing an array or object) as the single-line format representation.
     * All values inside are assumed single-line as well.
     * Note: can NOT use JSON_encode because of possible reference calls or definitions as : '&abc 123', '*fre'
     * which would be quoted by json_encode
     *
     * @param mixed   $subject The subject
     * @param integer $indent  The indent
     *
     * @return string the string representation (JSON like) of the value
     */
    public function dumpCompact($subject, int $indent):string
    {
        $pairs = [];
        if (is_array($subject) || $subject instanceof \ArrayIterator) {
            $max = count($subject);
            $objectAsArray = is_array($subject) ? $subject : $subject->getArrayCopy();
            if(array_keys($objectAsArray) !== range(0, $max-1)) {
                $pairs = $objectAsArray;
            } else {
                $valuesList = [];
                foreach ($objectAsArray as $value) {
                    $valuesList[] = is_scalar($value) ? $this->dump($value, $indent) : $this->dumpCompact($value, $indent);
                }
                return '['.implode(', ', $valuesList).']';
            }
        } else {
            $pairs = get_object_vars($subject);
        }
        $content = [];
        foreach ($pairs as $key => $value) {
            $content[] = "$key: ".(is_scalar($value) ? $this->dump($value, $indent) : $this->dumpCompact($value, $indent));
        }
        return '{'.implode(', ', $content).'}';
    }

    /**
     * Dumps a string. Protects it if needed
     *
     * @param      string  $str    The string
     *
     * @return     string  ( description_of_the_return_value )
     * @todo   implements checking and protection function
     */
    public function dumpString(string $str):string
    {
        return ltrim($str);
    }

    public function dumpTagged(Tagged $obj, int $indent):string
    {
        $separator   = ' ';
        $valueIndent = 0;
        if (!is_scalar($obj->value)) {
            $separator = "\n";
            $valueIndent = $indent + self::INDENT;
        }
        return $obj->tagName.$separator.$this->dump($obj->value, $valueIndent);
    }
}
