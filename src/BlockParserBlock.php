<?php

namespace JazzMan\WpBlockParser;

class BlockParserBlock {

    /**
         * Constructor.
         *
         * Will populate object properties from the provided arguments.
         *
         * @param string|null        $blockName    name of block
         * @param array|null         $attrs        optional set of attributes from block comment delimiters
         * @param BlockParserBlock[] $innerBlocks  list of inner blocks (of this same class)
         * @param string|null        $innerHTML    resultant HTML from inside block comment delimiters after removing inner blocks
         * @param string[]           $innerContent list of string fragments and null markers where inner blocks were found
         *
         * @since 5.0.0
         */
    public function __construct( public ?string $blockName, public ?array $attrs, public ?array $innerBlocks, public ?string $innerHTML, public ?array $innerContent = [] ) {}
}
