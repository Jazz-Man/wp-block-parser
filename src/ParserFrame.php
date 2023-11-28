<?php

namespace JazzMan\WpBlockParser;

final class ParserFrame {

    public int $prev_offset;

    /**
     * Constructor.
     *
     * Will populate object properties from the provided arguments.
     *
     * @param Block    $block              full or partial block
     * @param int      $token_start        byte offset into document for start of parse token
     * @param int      $token_length       byte length of entire parse token string
     * @param int|null $prev_offset        byte offset into document for after parse token ends
     * @param int|null $leading_html_start byte offset into document where leading HTML before token starts
     *
     * @since 5.0.0
     */
    public function __construct(
        public Block $block,
        public int $token_start,
        public int $token_length,
        ?int $prev_offset = null,
        public ?int $leading_html_start = null
    ) {
        $this->prev_offset = $prev_offset ?? $token_start + $token_length;
    }
}
