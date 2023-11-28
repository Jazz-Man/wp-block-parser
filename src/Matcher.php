<?php

namespace JazzMan\WpBlockParser;

use Exception;

class Matcher {

    private string $match;
    private int $started_at;
    private bool $_closer;
    private bool $_void;
    private string $name;

    /**
     * @var array<array-key, mixed>
     */
    private array $attrs = [];

    /**
     * @param array{
     *     0: array{0: string,1:int},
     *     closer: array{0: string,1:int}|null,
     *     1: array{0: string,1:int}|null,
     *     namespace: array{0: string,1:int}|null,
     *     2: array{0: string,1:int}|null,
     *     name: array{0: string,1:int},
     *     3: array{0: string,1:int}|null,
     *     attrs: array{0: string,1:int}|null,
     *     4: array{0: string,1:int}|null,
     *     void: array{0: string,1:int}|null,
     * } $matches
     */
    public function __construct( array $matches ) {
        [$match, $started_at] = $matches[0];

        $this->match = $match;
        $this->started_at = $started_at;

        $this->_closer = isset( $matches['closer'] ) && -1 !== $matches['closer'][1];
        $this->_void = isset( $matches['void'] ) && -1 !== $matches['void'][1];

        $namespace = ( isset( $matches['namespace'] ) && -1 !== $matches['namespace'][1] ) ? $matches['namespace'][0] : 'core/';

        $has_attrs = isset( $matches['attrs'] ) && -1 !== $matches['attrs'][1];

        $this->name = $namespace.$matches['name'][0];

        try {

            if ( $has_attrs && isset( $matches['attrs'][0] ) ) {

                $attr_json_string = $this->unicode_to_utf8( $matches['attrs'][0] );

                $this->attrs = (array) app_json_decode( $attr_json_string, true );

            } else {
                $this->attrs = [];
            }

        } catch ( Exception $exception ) {
            app_error_log( $exception, 'parse_block_attr' );
            $this->attrs = [];
        }
    }

    public function get_match(): string {
        return $this->match;
    }

    public function get_started_at(): int {
        return $this->started_at;
    }

    public function is_closer(): bool {
        return $this->_closer;
    }

    public function is_void(): bool {
        return $this->_void;
    }

    public function get_name(): string {
        return $this->name;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function get_attrs(): array {
        return $this->attrs;
    }

    public function has_attrs(): bool {
        return ! empty( $this->attrs );
    }

    private function unicode_to_utf8( string $string ): string {
        return (string) preg_replace_callback( '/u([0-9a-fA-F]{4})/', static fn ( array $match ): string => (string) mb_convert_encoding( pack( 'H*', $match[1] ), 'UTF-8', 'UCS-2BE' ), $string );
    }
}
