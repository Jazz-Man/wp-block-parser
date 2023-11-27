<?php

namespace JazzMan\WpBlockParser;

use JetBrains\PhpStorm\Pure;

class BlockParser {

    /**
     * Input document being parsed.
     *
     * @example "Pre-text\n<!-- wp:paragraph -->This is inside a block!<!-- /wp:paragraph -->"
     *
     * @since 5.0.0
     */
    public string $document;

    /**
     * Tracks parsing progress through document.
     *
     * @since 5.0.0
     */
    public int $offset;

    /**
     * List of parsed blocks.
     *
     * @since 5.0.0
     *
     * @var BlockParserBlock[]
     */
    public array $output;

    /**
     * Stack of partially-parsed structures in memory during parse.
     *
     * @since 5.0.0
     *
     * @var BlockParserFrame[]
     */
    public array $stack;

    /**
     * Empty associative array, here due to PHP quirks.
     *
     * @since 4.4.0
     *
     * @var array empty associative array
     */
    public array $empty_attrs;

    /**
     * Parses a document and returns a list of block structures.
     *
     * When encountering an invalid parse will return a best-effort
     * parse. In contrast to the specification parser this does not
     * return an error on invalid inputs.
     *
     * @since 5.0.0
     *
     * @param string $document input document being parsed
     *
     * @return array[]
     */
    public function parse( string $document ): array {
        $this->document = $document;
        $this->offset = 0;
        $this->output = [];
        $this->stack = [];
        $this->empty_attrs = json_decode( '{}', true );

        while ( $this->proceed() ) {
            continue;
        }

        return $this->output;
    }

    /**
     * Processes the next token from the input document
     * and returns whether to proceed eating more tokens.
     *
     * This is the "next step" function that essentially
     * takes a token as its input and decides what to do
     * with that token before descending deeper into a
     * nested block tree or continuing along the document
     * or breaking out of a level of nesting.
     *
     * @internal
     *
     * @since 5.0.0
     */
    public function proceed(): bool {
        $next_token = $this->next_token();
        [ $token_type, $block_name, $attrs, $start_offset, $token_length ] = $next_token;
        $stack_depth = \count( $this->stack );

        // we may have some HTML soup before the next block.
        $leading_html_start = $start_offset > $this->offset ? $this->offset : null;

        switch ( $token_type ) {
            case 'no-more-tokens':
                // if not in a block then flush output.
                if ( 0 === $stack_depth ) {
                    $this->add_freeform();

                    return false;
                }

                /*
                 * Otherwise we have a problem
                 * This is an error
                 *
                 * we have options
                 * - treat it all as freeform text
                 * - assume an implicit closer (easiest when not nesting)
                 */

                // for the easy case we'll assume an implicit closer.
                if ( 1 === $stack_depth ) {
                    $this->add_block_from_stack();

                    return false;
                }

                /*
                 * for the nested case where it's more difficult we'll
                 * have to assume that multiple closers are missing
                 * and so we'll collapse the whole stack piecewise
                 */
                while ( 0 < \count( $this->stack ) ) {
                    $this->add_block_from_stack();
                }

                return false;

            case 'void-block':
                /*
                 * easy case is if we stumbled upon a void block
                 * in the top-level of the document
                 */
                if ( 0 === $stack_depth ) {
                    if ( isset( $leading_html_start ) ) {
                        $this->output[] = (array) $this->freeform(
                            substr(
                                $this->document,
                                $leading_html_start,
                                $start_offset - $leading_html_start
                            )
                        );
                    }

                    $this->output[] = (array) new BlockParserBlock( $block_name, $attrs, [], '', [] );
                    $this->offset = $start_offset + $token_length;

                    return true;
                }

                // otherwise we found an inner block.
                $this->add_inner_block(
                    new BlockParserBlock( $block_name, $attrs, [], '', [] ),
                    $start_offset,
                    $token_length
                );
                $this->offset = $start_offset + $token_length;

                return true;

            case 'block-opener':
                // track all newly-opened blocks on the stack.
                $this->stack[] = new BlockParserFrame(
                    new BlockParserBlock( $block_name, $attrs, [], '', [] ),
                    $start_offset,
                    $token_length,
                    $start_offset + $token_length,
                    $leading_html_start
                );
                $this->offset = $start_offset + $token_length;

                return true;

            case 'block-closer':
                /*
                 * if we're missing an opener we're in trouble
                 * This is an error
                 */
                if ( 0 === $stack_depth ) {
                    /*
                     * we have options
                     * - assume an implicit opener
                     * - assume _this_ is the opener
                     * - give up and close out the document
                     */
                    $this->add_freeform();

                    return false;
                }

                // if we're not nesting then this is easy - close the block.
                if ( 1 === $stack_depth ) {
                    $this->add_block_from_stack( $start_offset );
                    $this->offset = $start_offset + $token_length;

                    return true;
                }

                /*
                 * otherwise we're nested and we have to close out the current
                 * block and add it as a new innerBlock to the parent
                 */
                $stack_top = array_pop( $this->stack );
                $html = substr( $this->document, $stack_top->prev_offset, $start_offset - $stack_top->prev_offset );
                $stack_top->block->innerHTML .= $html;
                $stack_top->block->innerContent[] = $html;
                $stack_top->prev_offset = $start_offset + $token_length;

                $this->add_inner_block(
                    $stack_top->block,
                    $stack_top->token_start,
                    $stack_top->token_length,
                    $start_offset + $token_length
                );
                $this->offset = $start_offset + $token_length;

                return true;

            default:
                // This is an error.
                $this->add_freeform();

                return false;
        }
    }

    /**
     * Scans the document from where we last left off
     * and finds the next valid token to parse if it exists.
     *
     * Returns the type of the find: kind of find, block information, attributes
     *
     * @internal
     *
     * @since 5.0.0
     * @since 4.6.1 fixed a bug in attribute parsing which caused catastrophic backtracking on invalid block comments
     */
    public function next_token(): array {
        $matches = null;

        /*
         * aye the magic
         * we're using a single RegExp to tokenize the block comment delimiters
         * we're also using a trick here because the only difference between a
         * block opener and a block closer is the leading `/` before `wp:` (and
         * a closer has no attributes). we can trap them both and process the
         * match back in PHP to see which one it was.
         */
        $has_match = preg_match(
            '/<!--\s+(?P<closer>\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\/)?(?P<name>[a-z][a-z0-9_-]*)\s+(?P<attrs>{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?}\s+)?(?P<void>\/)?-->/s',
            $this->document,
            $matches,
            PREG_OFFSET_CAPTURE,
            $this->offset
        );

        // if we get here we probably have catastrophic backtracking or out-of-memory in the PCRE.
        if ( false === $has_match ) {
            return [ 'no-more-tokens', null, null, null, null ];
        }

        // we have no more tokens.
        if ( 0 === $has_match ) {
            return [ 'no-more-tokens', null, null, null, null ];
        }

        [ $match, $started_at ] = $matches[0];

        $length = \strlen( $match );
        $is_closer = isset( $matches['closer'] ) && -1 !== $matches['closer'][1];
        $is_void = isset( $matches['void'] ) && -1 !== $matches['void'][1];
        $namespace = $matches['namespace'];
        $namespace = ( isset( $namespace ) && -1 !== $namespace[1] ) ? $namespace[0] : 'core/';
        $name = $namespace.$matches['name'][0];
        $has_attrs = isset( $matches['attrs'] ) && -1 !== $matches['attrs'][1];

        /*
         * Fun fact! It's not trivial in PHP to create "an empty associative array" since all arrays
         * are associative arrays. If we use `array()` we get a JSON `[]`
         */

        try {

            if ( $has_attrs ) {

                $attr_json_string = self::unicode_to_utf8( $matches['attrs'][0] );

                $attrs = app_json_decode( $attr_json_string, true );

            } else {
                $attrs = $this->empty_attrs;
            }

        } catch ( Exception $exception ) {
            app_error_log( $exception, 'parse_block_attr' );
            $attrs = $this->empty_attrs;
        }

        /*
         * This state isn't allowed
         * This is an error
         */
        if ( $is_closer && ( $is_void || $has_attrs ) ) {
            // we can ignore them since they don't hurt anything.
        }

        if ( $is_void ) {
            return [ 'void-block', $name, $attrs, $started_at, $length ];
        }

        if ( $is_closer ) {
            return [ 'block-closer', $name, null, $started_at, $length ];
        }

        return [ 'block-opener', $name, $attrs, $started_at, $length ];
    }

    /**
     * Returns a new block object for freeform HTML.
     *
     * @internal
     *
     * @since 3.9.0
     *
     * @param string $innerHTML HTML content of block
     *
     * @return BlockParserBlock freeform block object
     */
    #[Pure]
    public function freeform( string $innerHTML ): BlockParserBlock {
        return new BlockParserBlock( null, $this->empty_attrs, [], $innerHTML, [ $innerHTML ] );
    }

    /**
     * Pushes a length of text from the input document
     * to the output list as a freeform block.
     *
     * @param int|null $length how many bytes of document text to output
     *
     * @since 5.0.0
     *
     * @internal
     */
    public function add_freeform( ?int $length = null ): void {
        $length = $length ?: \strlen( $this->document ) - $this->offset;

        if ( 0 === $length ) {
            return;
        }

        $this->output[] = (array) $this->freeform( substr( $this->document, $this->offset, $length ) );
    }

    /**
     * Given a block structure from memory pushes
     * a new block to the output list.
     *
     * @internal
     *
     * @since 5.0.0
     *
     * @param BlockParserBlock $block        the block to add to the output
     * @param int              $token_start  byte offset into the document where the first token for the block starts
     * @param int              $token_length byte length of entire block from start of opening token to end of closing token
     * @param int|null         $last_offset  last byte offset into document if continuing form earlier output
     */
    public function add_inner_block( BlockParserBlock $block, int $token_start, int $token_length, ?int $last_offset = null ): void {
        $parent = $this->stack[ \count( $this->stack ) - 1 ];
        $parent->block->innerBlocks[] = (array) $block;
        $html = substr( $this->document, $parent->prev_offset, $token_start - $parent->prev_offset );

        if ( ! empty( $html ) ) {
            $parent->block->innerHTML .= $html;
            $parent->block->innerContent[] = $html;
        }

        $parent->block->innerContent[] = null;
        $parent->prev_offset = $last_offset ?: $token_start + $token_length;
    }

    /**
     * Pushes the top block from the parsing stack to the output list.
     *
     * @internal
     *
     * @since 5.0.0
     *
     * @param int|null $end_offset byte offset into document for where we should stop sending text output as HTML
     */
    public function add_block_from_stack( ?int $end_offset = null ): void {
        $stack_top = array_pop( $this->stack );
        $prev_offset = $stack_top->prev_offset;

        $html = isset( $end_offset )
            ? substr( $this->document, $prev_offset, $end_offset - $prev_offset )
            : substr( $this->document, $prev_offset );

        if ( ! empty( $html ) ) {
            $stack_top->block->innerHTML .= $html;
            $stack_top->block->innerContent[] = $html;
        }

        if ( isset( $stack_top->leading_html_start ) ) {
            $this->output[] = (array) $this->freeform(
                substr(
                    $this->document,
                    $stack_top->leading_html_start,
                    $stack_top->token_start - $stack_top->leading_html_start
                )
            );
        }

        $this->output[] = (array) $stack_top->block;
    }

    private static function unicode_to_utf8( string $string ): string {
        return preg_replace_callback( '/u([0-9a-fA-F]{4})/', static fn ( array $match ) => mb_convert_encoding( pack( 'H*', $match[1] ), 'UTF-8', 'UCS-2BE' ), $string );
    }
}
