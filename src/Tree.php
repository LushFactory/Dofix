<?php

namespace WorldFactory\Dofix;

use arrayaccess;
use Iterator;
use Countable;
use WorldFactory\Dofix\Exceptions\WriteAccessException;

class Tree implements arrayaccess, Iterator, Countable
{

    private $_arbre = array ();
    private $_meta = array ();

    const WHERE_EQUAL = '=';
    const WHERE_DISTINCT = '!=';
    const WHERE_EQUAL_OR_UPPER = '>=';
    const WHERE_EQUAL_OR_LOWER = '<=';
    const WHERE_UPPER = '>';
    const WHERE_LOWER = '<';
    const WHERE_ARRAY = '[]';
    const WHERE_NOT_ARRAY = '![]';
    const WHERE_IN_ARRAY = '[*]';
    const WHERE_NOT_IN_ARRAY = '![*]';
    const WHERE_CHECK = '(*)';
    const WHERE_UNCHECK = '!(*)';
    const WHERE_LIKE = '%%';
    const WHERE_LIKE_BEGIN = '-%';
    const WHERE_LIKE_END = '%-';

    const DEFAULT_TEST = self::WHERE_EQUAL;

    public function __construct (array $data)
    {
        if (array_key_exists ('.meta', $data))
        {
            $this->_meta = $data['.meta'];
            unset ($data['.meta']);
        }

        $this->_arbre = $data;
    }


    // ###################################################################
    // ###       fonctions publiques
    // ###################################################################

    public function primary ($adresse, $value)
    {
        foreach ($this->_arbre AS $clef => &$val)
        {
            $test_val = &$this->search_data ($adresse, $val);
            if ($this->test_if ($test_val, self::WHERE_EQUAL, $value))
            {
                return $clef;
            }
        }

        return null;
    }


    public function find ($adresse, $value)
    {
        $clef = $this->primary ($adresse, $value);
        if ($clef !== FALSE)
        {
            return $this->getDofixArray ($this->_arbre[$clef]);
        }

        return null;
    }


    public function where ($adresse, $mode = self::DEFAULT_TEST, $value = TRUE)
    {
        $data = array ();

        foreach ($this->_arbre AS $clef => &$val)
        {
            $test_val = &$this->search_data ($adresse, $val);
            $testing = $this->test_if ($test_val, $mode, $value);
            if ($testing)
            {
                $data[$clef] = &$this->_arbre[$clef];
            }
        }

        return $this->getDofixArray ($data);
    }


    public function collect ($liste)
    {
        $data = array ();

        foreach ($liste AS $clef)
        {
            if (isset ($this->_arbre[$clef]))
            {
                $data[$clef] = &$this->_arbre[$clef];
            }
        }

        return $this->getDofixArray ($data);
    }


    public function select ($adresse)
    {
        $data = array ();

        foreach ($this->_arbre AS $clef => &$val)
        {
            $data[$clef] = &$this->search_data ($adresse, $val);
        }

        return $this->getDofixArray ($data);
    }


    public function test ($adresse, $mode = self::DEFAULT_TEST, $value = TRUE)
    {
        $data = $this->search_data ($adresse);
        return $this->test_if ($data, $mode, $value);
    }


    public function get ($adresse)
    {
        $child = &$this->search_data ($adresse);
        return $this->exportValue ($child);
    }


    public function keys ()
    {
        return array_keys ($this->_arbre);
    }


    public function cut ()
    {
        $result = $this->_arbre;
        array_walk ($result, function (&$value) {$value = TRUE;});
        return $result;
    }


    public function toArray ()
    {
        return $this->_arbre;
    }


    public function aff_tree ()
    {
        echo 'Contenu de l\'arbre :<br />'.$this;
    }



    public function __toString ()
    {
        return (string) var_dump($this->_arbre);
    }

    public function isMeta ($tag)
    {
        return array_key_exists ($tag, $this->_meta);
    }
    public function getMeta ($tag)
    {
        return $this->isMeta ($tag) ? $this->_meta[$tag] : FALSE;
    }
    public function getMetas ()
    {
        return $this->_meta;
    }

    // ###################################################################
    // ###       sous-fonctions d'implémentation de Countable
    // ###################################################################

    public function count ()
    {
        return count ($this->_arbre);
    }




    // ###################################################################
    // ###       sous-fonctions d'accès par tableau
    // ###################################################################

    public function offsetSet ($var, $value)
    {
        throw new WriteAccessException('Un arbre de donnée ne peut être modifié.');
    }
    public function offsetExists ($var)
    {
        return ($this->search_data ($var) !== FALSE);
    }
    public function offsetUnset ($var)
    {
        throw new WriteAccessException('Un arbre de donnée ne peut être modifié.');
    }
    public function offsetGet ($var)
    {
        return $this->get ($var);
    }




    // ###################################################################
    // ###       sous-fonctions d'itération
    // ###################################################################

    public function rewind ()
    {
        return reset ($this->_arbre);
    }

    public function current ()
    {
        return $this[key ($this->_arbre)];
    }

    public function key ()
    {
        return key ($this->_arbre);
    }

    public function next ()
    {
        return next ($this->_arbre);
    }

    public function valid ()
    {
        return (key ($this->_arbre) !== NULL);
    }



    // ###################################################################
    // ###       sous-fonctions utilitaires
    // ###################################################################

    protected function exportValue (&$child)
    {
        if (is_array ($child))
        {
            return $this->getDofixArray ($child);
        }
        else
        {
            return $child;
        }
    }

    protected function getDofixArray (&$data)
    {
        $class = '\WorldFactory\Dofix\Tree';
        if (array_key_exists ('.meta', $data) and array_key_exists ('class', $data['.meta']))
        {
            $class = $data['.meta']['class'];
        }
        return new $class ($data);
    }

    protected function test_if ($test_val, $mode, $value)
    {
        $result = FALSE;
        if (is_array ($value) and is_array ($mode))
        {
            $nb = count ($value);
            $current_mode = self::DEFAULT_TEST;
            for ($c = 0; $c < $nb; $c ++)
            {
                $current_mode = isset ($mode[$c]) ? $mode[$c] : $current_mode;
                $result = $this->testValue ($test_val, $current_mode, $value[$c]);
                if ($result) {break;}
            }
        }
        else
        {
            $result = $this->testValue ($test_val, $mode, $value);
        }
        return $result;
    }

    protected function testValue ($test_val, $mode, $value)
    {
        $result = FALSE;

        switch ($mode)
        {
            case self::WHERE_EQUAL:
                if ($test_val == $value) {$result = TRUE;}
                break;

            case self::WHERE_EQUAL_OR_UPPER:
                if ($test_val >= $value) {$result = TRUE;}
                break;

            case self::WHERE_EQUAL_OR_LOWER:
                if ($test_val <= $value) {$result = TRUE;}
                break;

            case self::WHERE_UPPER:
                if ($test_val > $value) {$result = TRUE;}
                break;

            case self::WHERE_LOWER:
                if ($test_val < $value) {$result = TRUE;}
                break;

            case self::WHERE_DISTINCT:
                if ($test_val != $value) {$result = TRUE;}
                break;

            case self::WHERE_ARRAY:
                if (is_array ($test_val)) {$result = TRUE;}
                break;

            case self::WHERE_NOT_ARRAY:
                if (!is_array ($test_val)) {$result = TRUE;}
                break;

            case self::WHERE_IN_ARRAY:
                if (is_array ($value) and in_array ($test_val, $value)) {$result = TRUE;}
                break;

            case self::WHERE_NOT_IN_ARRAY:
                if (is_array ($value) and !in_array ($test_val, $value)) {$result = TRUE;}
                break;

            case self::WHERE_CHECK:
                if (is_array ($test_val) and array_key_exists ($value, $test_val)) {$result = TRUE;}
                break;

            case self::WHERE_UNCHECK:
                if (is_array ($test_val) and !array_key_exists ($value, $test_val)) {$result = TRUE;}
                break;

            case self::WHERE_LIKE:
                $result = FALSE;
                break;

            case self::WHERE_LIKE_BEGIN:
                $result = FALSE;
                break;

            case self::WHERE_LIKE_END:
                $result = FALSE;
                break;

            default:
                $result = FALSE;
                break;
        }

        return $result;
    }

    private function search_data ($key = FALSE, &$data = FALSE)
    {
        if (!$data)
        {
            $data =& $this->_arbre;
        }

        if ($key === FALSE) {return $data;}

        $all_keys = explode ('.', $key);
        $first_key = array_shift ($all_keys);

        if (is_array ($data) and isset ($data[$first_key]))
        {
            $data =& $data[$first_key];
        }
        else
        {
            return FALSE;
        }

        if (!empty ($all_keys))
        {
            $key = implode ('.', $all_keys);
            return $this->search_data ($key, $data);
        }
        else
        {
            return $data;
        }

    }

}
