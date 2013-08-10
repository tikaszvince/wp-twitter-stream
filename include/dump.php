<?php
/**
 * WP Twitter Stream.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 * @license GPL-2.0+
 * @link http://vince.tikasz.hu
 * @copyright 2013 Vince Tikász
 */

/**
 * WP Twitter Stream Dump.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 * @license GPL-2.0+
 * @link http://vince.tikasz.hu
 * @copyright 2013 Vince Tikász
 */
class WP_Twitter_Stream_Dump {

  /**
   * Dumps information about a variable
   *
   * @link http://docs.php.net/var_dump
   */
  static public function dump() {
    foreach (func_get_args() as $arg) {
      $d = new self($arg);
      echo $d->output();
    }
  }

  /** @var mixed */
  protected $var;

  /**
   * Constructor
   * @param $var
   *   The variable we want to dump.
   */
  public function __construct($var) {
    $this->var = $var;
  }

  /**
   * Get rendered output.
   * @return string
   */
  public function output() {
    return $this->do_dump($this->var);
  }

  /**
   * Generates output recursively.
   *
   * @param $val
   * @param int $depth
   * @param string $prefix
   * @param string $type_prefix
   *
   * @return string
   */
  protected function do_dump($val, $depth = 0, $prefix = '', $type_prefix = '') {
    $indent = str_repeat('  ', $depth);
    if ($depth > 5) {
      return $indent . " …\n";
    }
    $return = $indent . $prefix;
    $_indent = $depth ? '    ' : '';

    if (is_array($val)) {
      $return .= "\n" . $_indent . $type_prefix . '<b>array</b> <em>(size=' . count($val) . ")</em>\n";
      if ($depth > 4) {
        return $return . $_indent . " ...\n";
      }
      $return .= $this->show_array($val, $depth);
    }
    elseif (is_object($val)) {
      $return .= "\n" . $_indent . $type_prefix . '<b>object</b><em>(' . get_class($val) . ")</em>\n";
      $return .= $this->show_object($val, $depth);
    }
    else {
      $return .= ' ' . $this->show_scalar($val);
    }

    return $return;
  }

  /**
   * Display a scalar value.
   * @param bool|int|float|string|NULL $val
   * @return string
   */
  protected function show_scalar($val) {

    if (is_float($val)) {
      $type = 'float';
      $color = '#f57900';
    }
    elseif (is_int($val)) {
      $type = 'int';
      $color = '#4e9a06';
    }
    elseif (is_bool($val)) {
      $type = 'boolean';
      $color = '#75507b';
      $value = $val ? 'true' : 'false';
    }
    elseif (!isset($val)) {
      $type = '';
      $color = '#3465a4';
      $value = 'null';
    }
    elseif (is_string($val)) {
      $type = 'string';
      $color = '#cc0000';
      $value = "'" . htmlspecialchars($val, ENT_NOQUOTES & ENT_HTML401, 'UTF-8') ."'";
    }

    if (isset($type)) {
      if (!isset($value)) {
        $value = $val;
      }
      $cover_prefix = '<span style="color: ' . $color . '">';
      $cover_suffix = '</span>';
      if (!isset($color)) {
        $cover_prefix = '';
        $cover_suffix = '';
      }
      if ($type) {
        $type .= ' ';
      }
      return "{$type}{$cover_prefix}{$value}{$cover_suffix}\n";
    }
    return "UNKNOWN\n";
  }

  /**
   * Display an array.
   * @param array $array
   * @param int $depth
   * @return string
   */
  protected function show_array($array, $depth) {
    $return = '';
    foreach ($array as $key => $value) {
      $pref = $depth ? '  ' : '';
      $pref .= (is_numeric($key) ? $key : "'{$key}'") . ' =&gt;';
      if (is_object($value)) {
        $display = trim($this->do_dump($value, $depth + 1)) . "\n";
        $display = preg_replace('%^%m', str_repeat('  ', $depth + 2), $display);
        $return .=
          str_repeat('  ', $depth + 1) .
          $pref . "\n" .
          $display;
      }
      else {
        $return .= $this->do_dump($value, $depth + 1, $pref);
      }
    }
    return $return;
  }

  /**
   * Display an object.
   * @param $object
   * @param $depth
   * @return string
   */
  protected function show_object($object, $depth) {
    $type = get_class($object);
    $protected_str = "\0" . '*' . "\0";
    $private_str = "\0" . $type . "\0";
    $return = '';
    foreach ((array) $object as $name => $property) {
      $pref = $depth ? '  ' : '';
      if (strpos($name, '' . $private_str) === 0) {
        $pref .= "<em>private</em> '" . str_replace($private_str, '', $name);
      }
      elseif (strpos($name, '' . $protected_str) === 0) {
        $pref .= "<em>protected</em> '" . str_replace($protected_str, '', $name);
      }
      else {
        $pref .= "<em>public</em> '" . $name;
      }
      $pref .= "' =&gt;";

      $display = $this->do_dump($property);
      if (is_array($property) || is_object($property)) {
        $display = preg_replace('%^%m', str_repeat('  ', $depth + 1), $display);
      }
      $return .= $pref . $display;
    }
    return $return;
  }
}
