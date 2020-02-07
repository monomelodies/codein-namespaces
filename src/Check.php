<?php

namespace Monomelodies\CodeinNamespaces;

use Generator;
use Monomelodies\Codein;

/**
 * Check for namespace use. Avoid dead wood, and group related namespaces
 * (e.g. Some\Vendor\{ Foo, Bar }).
 */
class Check extends Codein\Check
{
    /**
     * Run the check.
     *
     * @param string $file
     * @return Generator
     */
    public function check(string $file) : Generator
    {
        parent::initialize($file);
        if (!preg_match_all("@^use (.*?);$@ms", $this->code, $matches)) {
            return;
        }
        $namespaces = [];
        $nss = [];
        foreach ($matches[1] as $i => $match) {
            if (strpos($match, '{')) {
                $match = substr($match, strpos($match, '{') + 1);
                $nss = array_merge($nss, preg_split("@,\s*@", preg_replace("@^\s*(.*?)\s*}$@ms", '\\1', $match)));
            } else {
                $parts = explode("\\", $match);
                $nss[] = $match;
                if (count($parts) > 1) {
                    if (!isset($namespaces["{$parts[0]}\\{$parts[1]}"])) {
                        $namespaces["{$parts[0]}\\{$parts[1]}"] = 0;
                    }
                    $namespaces["{$parts[0]}\\{$parts[1]}"]++;
                }
            }
            $this->code = str_replace($matches[0][$i], '', $this->code);
        }
        foreach ($namespaces as $name => $count) {
            if ($count > 1) {
                yield "<red>Namespace <darkRed>$name <red>appears $count times in <darkRed>{$this->file}";
            }
        }
        foreach ($nss as $i => $namespace) {
            if (strpos($namespace, ' as ')) {
                $parts = explode(' as ', $namespace);
                $namespace = end($parts);
            }
            if (strpos($namespace, '\\')) {
               $namespace = substr($namespace, strrpos($namespace, '\\') + 1);
            }
            $namespace = preg_replace("@\s*}@ms", '', trim($namespace));
            $nss[$i] = $namespace;
        }
        $nss = array_unique($nss);
        foreach ($nss as $namespace) {
            if ($this->instantiated($namespace)
                || $this->argumentTypeHint($namespace)
                || $this->traitUsed($namespace)
                || $this->returnTypeHint($namespace)
                || $this->classname($namespace)
                || $this->instanceOfCheck($namespace)
                || $this->extendsClass($namespace)
                || $this->implementsInterface($namespace)
                || $this->catchesException($namespace)
            ) {
                continue;
            }
            yield "<red>Unused: <darkRed>$namespace <red>in <darkRed>{$this->file}";
        }
        return;
    }

    /**
     * Check if the namespace is instantiated as a class anywhere.
     *
     * @param string $namespace
     * @return bool
     */
    private function instantiated(string $namespace) : bool
    {
        return (bool)preg_match("@new $namespace@", $this->code);
    }

    /**
     * Check if the namespace is used as an argument type hint anywhere.
     *
     * @param string $namespace
     * @return bool
     */
    private function argumentTypeHint(string $namespace) : bool
    {
        if (preg_match_all('@function \w*\((.*?)\)@ms', $this->code, $matches)) {
            foreach ($matches[1] as $args) {
                $ns = preg_split('@,\s*@', $args);
                foreach ($ns as $one) {
                    if (preg_match("@^$namespace@", $one)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Check if the namespace is used as a trait.
     *
     * @param string $namespace
     * @return bool
     */
    private function traitUsed(string $namespace) : bool
    {
        return (bool)preg_match("@use $namespace@", $this->code);
    }

    /**
     * Check if the namespace appears as a return type hint.
     *
     * @param string $namespace
     * @return bool
     */
    private function returnTypeHint(string $namespace) : bool
    {
        return (bool)preg_match("@:\?? $namespace@", $this->code);
    }

    /**
     * Check if the namespace is used as a static classname.
     *
     * @param string $namespace
     * @return bool
     */
    private function classname(string $namespace) : bool
    {
        return (bool)preg_match("@$namespace(\\\\(\w|\\\\)+)?::@m", $this->code);
    }

    /**
     * Check if the namespace is used in an instanceof check.
     *
     * @param string $namespace
     * @return bool
     */
    private function instanceOfCheck(string $namespace) : bool
    {
        return (bool)preg_match("@instanceof $namespace@", $this->code);
    }

    /**
     * Check if the namespace is used as a parent class.
     *
     * @param string $namespace
     * @return bool
     */
    private function extendsClass(string $namespace) : bool
    {
        return (bool)preg_match("@extends $namespace@", $this->code);
    }

    /**
     * Check if the namespace is an interface that was implemented.
     *
     * @param string $namespace
     * @return bool
     */
    private function implementsInterface(string $namespace) : bool
    {
        if (preg_match("@implements (.*?)$@m", $this->code, $match)) {
            $ns = preg_split('@,\s*@', $match[1]);
            foreach ($ns as $one) {
                if (preg_match("@^$namespace@", $one)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if the namespace is used to catch an exception.
     *
     * @param string $namespace
     * @return bool
     */
    private function catchesException(string $namespace) : bool
    {
        return (bool)preg_match("@} catch \($namespace @", $this->code);
    }
}

