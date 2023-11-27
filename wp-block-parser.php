<?php

/**
 * Plugin Name:         wp-block-parser
 * Plugin URI:          https://github.com/Jazz-Man/wp-block-parser
 * Description:         Better WordPress block attribute parser
 * Author:              Vasyl Sokolyk
 * Author URI:          https://www.linkedin.com/in/sokolyk-vasyl
 * Requires at least:   6.2
 * Requires PHP:        8.2
 * License:             MIT
 * Update URI:          https://github.com/Jazz-Man/wp-block-parser.
 */

use JazzMan\WpBlockParser\BlockParser;

add_filter( 'block_parser_class', static fn ( string $class ) => BlockParser::class );
