<?php
/**
 * @package dompdf
 * @link    http://dompdf.github.com/
 * @author  Benj Carson <benjcarson@digitaljunkies.ca>
 * @author  Fabien Ménager <fabien.menager@gmail.com>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
 
/**
 * DOMPDF autoload function
 *
 * If you have an existing autoload function, add a call to this function
 * from your existing __autoload() implementation.
 *
 * @param string $class
 */
function DOMPDF_autoload($class) {
  $filename = DOMPDF_INC_DIR . "/" . mb_strtolower($class) . ".cls.php";
  
  if ( is_file($filename) ) {
    include_once $filename;
  }
}

// PHP 8 removed __autoload(); register the DOMPDF loader with SPL instead.
if (!in_array('DOMPDF_autoload', spl_autoload_functions() ?: [], true)) {
  spl_autoload_register('DOMPDF_autoload', true, defined('DOMPDF_AUTOLOAD_PREPEND') && DOMPDF_AUTOLOAD_PREPEND);
}
