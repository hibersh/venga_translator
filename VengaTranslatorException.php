<?php

namespace Drupal\venga_translator;

/**
 * Venga translator generic exception class.
 */
class VengaTranslatorException extends \Exception {

  /**
   * Constructs a class instance.
   *
   * @param string $message
   *   Message text.
   * @param array $data
   *   Associative array of dynamic data that will be inserted into $message.
   * @param int $code
   *   Error code.
   */
  function __construct($message = "", $data = array(), $code = 0) {
    parent::__construct(strtr($message, $data), $code);
  }
}
