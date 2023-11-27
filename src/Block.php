<?php

namespace JazzMan\WpBlockParser;

use JetBrains\PhpStorm\ArrayShape;

final class Block {

    /**
         * Constructor.
         *
         * Will populate object properties from the provided arguments.
         *
         * @param string|null                   $blockName    name of block
         * @param array<string, mixed>|null     $attrs        optional set of attributes from block comment delimiters
         * @param array<array-key, mixed>       $innerBlocks  list of inner blocks (of this same class)
         * @param string                        $innerHTML    resultant HTML from inside block comment delimiters after removing inner blocks
         * @param array<array-key, string|null>|null $innerContent list of string fragments and null markers where inner blocks were found
         */
    public function __construct(
        public ?string $blockName,
        public ?array $attrs,
        public array $innerBlocks = [],
        public string $innerHTML = '',
        public ?array $innerContent = []
    ) {}

    /**
     * @return array{
     *     attrs: array<string, mixed>|null,
     *     blockName: null|string,
     *     innerBlocks: array<array-key, mixed>,
     *     innerContent: array<array-key, string|null>|null,
     *     innerHTML: string
     * }
     */
    #[ArrayShape( [
        'blockName' => 'null|string',
        'attrs' => 'array<string, mixed>|null',
        'innerBlocks' => 'array<array-key, mixed>',
        'innerHTML' => 'string',
        'innerContent' => 'array<array-key, string|null>|null',
    ] )]
    public function to_array(): array {
        return [
            'blockName' => $this->blockName,
            'attrs' => $this->attrs,
            'innerBlocks' => $this->innerBlocks,
            'innerHTML' => $this->innerHTML,
            'innerContent' => $this->innerContent,
        ];

    }
}
