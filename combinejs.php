<?php
/**
 * combinejs.php Combines all of the JS files listed in scripts.json in scripts.js.
 * To be used with FileWatchers in PhpStorm.
 *
 * Incorporate code from the excellent "PHP Source Maps" by @bspot on GitHub
 * @see https://github.com/bspot/phpsourcemaps
 *
 * @author Mike Schinkel <mike@newclarity.net>
 * @version 1.0.3
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

$save_cwd = getcwd();

if ( empty( $argv[1] ) ) {
  $dir = getcwd();
} else if ( '/' ==  $argv[1][0] && is_dir( $argv[1] ) ) {
    $dir = $argv[1];
} else if ( ! is_dir( $dir = getcwd() . "/{$argv[1]}" ) ) {
  echo "\nERROR: {$argv[1]} is not a valid directory.\n\n";
  die(1);
}

chdir( $dir );

$script_files = new Script_Files();
$script_files->local_sourcemap = false !== strpos( implode( '|', $argv ), '--local' );
if ( ! is_file( $script_files->scripts_json_filepath ) ) {
  echo "\nERROR. No JSON file: {$script_files->scripts_json_filepath}.\n\n";
  die(2);
}

$script_files->generate();

$map_type = $script_files->local_sourcemap ? "LOCAL" : "REMOTE";
echo "\nSUCCESS! CombineJS combined these Javascript files:\n\n";
echo implode( "\n", array_map( 'prefix_with_tab', $script_files->get_script_filepaths( 'filepath' ) ) );
echo "\n\nInto a {$map_type} sourcemap:\n\n\t{$script_files->output_filepath}.\n\n";

chdir( $save_cwd );
exit;

/**
 * Class Script_Files
 *
 * @author mikeschinkel
 */
class Script_Files {

  /**
   * @var string $output_filepath - Full path of output file.
   * Defaults to getcwd() . /scripts.js
   */
  var $output_filepath;

  /**
   * @var string $sourcemap_filepath - Full path of output file.
   * Defaults to getcwd() . /scripts.js.map
   */
  var $sourcemap_filepath;

  /**
   * @var string $files_json_filepath - Full path to .json file containing list of files to combine.
   * Defaults to getcwd() . /src/scripts.js.map
   */
  var $scripts_json_filepath;

  /**
   * @var bool $local_sourcemap - Generate a web map by default, or a local one of true.
   */
  var $local_sourcemap = false;

  /**
   * @var array $script_files - Array of
   */
  private $_script_files;

  /**
   *
   */
  function __construct() {
    $this->output_filepath =  getcwd() . '/scripts.js';
    $this->sourcemap_filepath = $this->output_filepath . '.map';
    $this->scripts_json_filepath = getcwd() . '/src/scripts.json';
  }

  /**
   * @param bool|string $property_name
   * @return array
   */
  function get_script_filepaths( $property_name = false ) {

    if ( ! $property_name )
      $property_name =  $this->local_sourcemap ? 'filepath' : 'relative_filepath';

    return $this->pluck( $this->_script_files, $property_name );
  }

  /**
   *
   */
  function load_script_files() {
    $this->_script_files = $this->_load_json( $this->scripts_json_filepath );
  }
  /**
   *
   */
  function generate() {
    if ( ! isset( $this->_script_files ) )
      $this->load_script_files();
    $this->combine_script_files();
    $this->generate_sourcemap();
  }
  /**
   *
   */
  function combine_script_files() {
    $output_content = implode( "\n", $this->pluck( $this->_script_files, 'contents' ) );
    $sourcemap_basename = basename( $this->sourcemap_filepath );
    $tail = "\n//@ sourceMappingURL={$sourcemap_basename}\n";
    file_put_contents( $this->output_filepath, "{$output_content}{$tail}" );
  }
  /**
   *
   */
  function generate_sourcemap() {
    Base64VLQ::initialize();
    $script_filepaths = $this->get_script_filepaths();

    $output_filepath = $this->local_sourcemap ? $this->output_filepath : basename( $this->output_filepath );

    $map = new SourceMap( $output_filepath, $script_filepaths );
    $offset = 0;
    foreach( $this->_script_files as $index => $script_file ) {
      for( $i=0; $i<$script_file->line_count; ++$i ) {
        $map->mappings[] = array(
          'src_index' => $index,
          'src_line' => $i,
          'dest_line' => $offset++,
          'src_col' => 0,
          'dest_col' => 0,
        );
      }
    }
    file_put_contents( $this->sourcemap_filepath, $sourcemap = $map->generateJSON() );
  }
  /**
   * @param string $json_filepath
   *
   * @return array
   */
  private function _load_json( $json_filepath ) {
    $cwd = getcwd() . '/';

    $script_files = json_decode( file_get_contents( $json_filepath ) );
    if ( ! $script_files ) {
      trigger_error( "\nERROR: {$json_filepath} is not a valid JSON file.\n\n", E_USER_NOTICE );
      die(3);
    }

    foreach( $script_files as $index => $script ) {
      if( is_file( $script_fullpath = ( $cwd . ( $script_file = "src/{$script}" ) ) ) ) {
        $script_files[$index] = new Script_File( $script_file );
      } else {
        trigger_error( "\nERROR: {$script_fullpath} is not a valid file.\n\n", E_USER_NOTICE );
        die(4);
      }
    }

    if( is_file( $cwd . ( $prefix_file = 'src/prefix.combinejs' ) ) )
      array_unshift( $script_files, new Script_File( $prefix_file ) );

    if( is_file( $cwd . ( $postfix_file = 'src/postfix.combinejs' ) ) )
      $script_files[] = new Script_File( $postfix_file );

    return $script_files;
  }
  /**
   * @param array|object $collection
   * @param string $name
   *
   * @return array
   */
  function pluck( $collection, $name ) {
    $result = array();
    foreach( $collection as $item ) {
      if ( is_array( $item ) && isset( $item[$name] ) ) {
        $result[] =  $item[$name];
      } else if ( is_object( $item ) && property_exists( $item, $name ) ) {
        $result[] =  $item->$name;
      }
    }
    return $result;
  }
}

/**
 * Class Script_File
 *
 * @author mikeschinkel
 */
class Script_File {
  var $filepath;
  var $contents;
  var $line_count;
  var $relative_filepath;

  /**
   * @param string $filepath
   */
  function __construct( $filepath ){
    $this->relative_filepath = ltrim( $filepath, '/' );
    $this->filepath = getcwd() . "/{$this->relative_filepath}";
    $this->contents = rtrim( file_get_contents( $filepath ) );
    $this->line_count = count( explode( "\n", $this->contents ) );
  }
}

/**
 * Generate source maps
 *
 * @author bspot
 */
class SourceMap {

  public function __construct($out_file, $source_files) {
    $this->out_file = $out_file;
    $this->source_files = $source_files;

    $this->mappings = array();
  }

  public function generateJSON() {

    return json_encode(array(
      "version" => 3,
      "file" => $this->out_file,
      "sourceRoot" => "",
      "sources" => $this->source_files,
      "names" => array(),
      "mappings" => $this->generateMappings()
    ));
  }

  public function generateMappings() {

    // Group mappings by dest line number.
    $grouped_map = array();
    foreach ($this->mappings as $m) {
      $grouped_map[$m['dest_line']][] = $m;
    }

    ksort($grouped_map);

    $grouped_map_enc = array();

    $last_dest_line = 0;
    $last_src_index = 0;
    $last_src_line = 0;
    $last_src_col = 0;
    foreach ($grouped_map as $dest_line => $line_map) {
      while (++$last_dest_line < $dest_line) {
        $grouped_map_enc[] = ";";
      }

      $line_map_enc = array();
      $last_dest_col = 0;

      foreach ($line_map as $m) {
        $m_enc = Base64VLQ::encode($m['dest_col'] - $last_dest_col);
        $last_dest_col = $m['dest_col'];
        if (isset($m['src_index'])) {
          $m_enc .= Base64VLQ::encode($m['src_index'] - $last_src_index);
          $last_src_index = $m['src_index'];

          $m_enc .= Base64VLQ::encode($m['src_line'] - $last_src_line);
          $last_src_line = $m['src_line'];

          $m_enc .= Base64VLQ::encode($m['src_col'] - $last_src_col);
          $last_src_col = $m['src_col'];
        }
        $line_map_enc[] = $m_enc;
      }

      $grouped_map_enc[] = implode(",", $line_map_enc) . ";";
    }

    $grouped_map_enc = implode($grouped_map_enc);

    return $grouped_map_enc;
  }
};

/**
 * Encode / Decode Base64 VLQ.
 *
 * @author bspot
 */
class Base64VLQ {

  public static $SHIFT = 5;
  public static $MASK = 0x1F; // == (1 << SHIFT) == 0b00011111
  public static $CONTINUATION_BIT = 0x20; // == (MASK - 1 ) == 0b00100000

  public static $CHAR_TO_INT = array();
  public static $INT_TO_CHAR = array();

  /**
   * Convert from a two-complement value to a value where the sign bit is
   * is placed in the least significant bit.  For example, as decimals:
   *   1 becomes 2 (10 binary), -1 becomes 3 (11 binary)
   *   2 becomes 4 (100 binary), -2 becomes 5 (101 binary)
   * We generate the value for 32 bit machines, hence
   *   -2147483648 becomes 1, not 4294967297,
   * even on a 64 bit machine.
  */
  public static function toVLQSigned($aValue) {
    return 0xffffffff & ($aValue < 0 ? ((-$aValue) << 1) + 1 : ($aValue << 1) + 0);
  }

  /**
   * Convert to a two-complement value from a value where the sign bit is
   * is placed in the least significant bit. For example, as decimals:
   *   2 (10 binary) becomes 1, 3 (11 binary) becomes -1
   *   4 (100 binary) becomes 2, 5 (101 binary) becomes -2
   * We assume that the value was generated with a 32 bit machine in mind.
   * Hence
   *   1 becomes -2147483648
   * even on a 64 bit machine.
   */
  public static function fromVLQSigned($aValue) {
    return $aValue & 1 ? self::zeroFill(~$aValue+2, 1) | (-1 - 0x7fffffff) : self::zeroFill($aValue, 1);
  }

  /**
   * Return the base 64 VLQ encoded value.
   */
  public static function encode($aValue) {
    $encoded = "";

    $vlq = self::toVLQSigned($aValue);

    do {
      $digit = $vlq & self::$MASK;
      $vlq = self::zeroFill($vlq, self::$SHIFT);
      if ($vlq > 0) {
        $digit |= self::$CONTINUATION_BIT;
      }
      $encoded .= self::base64Encode($digit);
    } while ($vlq > 0);

    return $encoded;
  }

  /**
   * Return the value decoded from base 64 VLQ.
   */
  public static function decode($encoded) {
    $vlq = 0;

    $i = 0;
    do {
      $digit = self::base64Decode($encoded[$i]);
      $vlq |= ($digit & self::$MASK) << ($i*self::$SHIFT);
      $i++;
    } while ($digit & self::$CONTINUATION_BIT);

    return self::fromVLQSigned($vlq);
  }

  /**
   * Right shift with zero fill.
   *
   * @param number $a number to shift
   * @param number $b number of bits to shift
   * @return number
   */
  public static function zeroFill($a, $b) {
    return ($a >= 0) ? ($a >> $b) : ($a >> $b) & (PHP_INT_MAX >> ($b-1));
  }

  /**
   * Encode single 6-bit digit as base64.
   *
   * @param number $number
   * @return string
   * @throws Exception
   */
  public static function base64Encode($number) {
    if ($number < 0 || $number > 63) {
      throw new Exception("Must be between 0 and 63: " . $number);
    }
    if ( ! isset( self::$INT_TO_CHAR[$number] ) ) {
      echo '';
    }
    return self::$INT_TO_CHAR[$number];
  }

  /**
   * Decode single 6-bit digit from base64
   *
   * @param string $char
   * @return number
   * @throws Exception
   */
  public static function base64Decode($char) {
    if (!array_key_exists($char, self::$CHAR_TO_INT)) {
      throw new Exception("Not a valid base 64 digit: " . $char);
    }
    return self::$CHAR_TO_INT[$char];
  }

  public static function initialize() {
    // Initialize char conversion table.
    Base64VLQ::$CHAR_TO_INT = array();
    Base64VLQ::$INT_TO_CHAR = array();

    foreach (str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/') as $i => $char) {
      Base64VLQ::$CHAR_TO_INT[$char] = $i;
      Base64VLQ::$INT_TO_CHAR[$i] = $char;
    }
  }
}

function prefix_with_tab( $string ) {
  return "\t{$string}";
}

