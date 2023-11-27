<?php

declare( strict_types=1 );

use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\Config\RectorConfig;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;

return static function ( RectorConfig $config ): void {
    $config->sets( [
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        SetList::NAMING,
        SetList::PRIVATIZATION,
        LevelSetList::UP_TO_PHP_82,
    ] );

    $config->fileExtensions( ['php'] );
    $config->importNames();
    $config->removeUnusedImports();
    $config->importShortClasses( false );
    $config->parallel();
        $config->cacheDirectory(__DIR__.'/cache/rector');
//    $config->phpstanConfig( __DIR__.'/phpstan-rector.neon' );

    $config->paths( [
        __DIR__.'/src',
        __DIR__.'/wp-block-parser.php',
    ] );

    $config->skip( [
        __DIR__.'/vendor',
//        __DIR__.'/cache',
        CallableThisArrayToAnonymousFunctionRector::class,
        //        ClassConstantToSelfClassRector::class,
        RemoveExtraParametersRector::class,
        EncapsedStringsToSprintfRector::class,
        DisallowedEmptyRuleFixerRector::class,
        SimplifyEmptyCheckOnEmptyArrayRector::class,
        //	    ArrayShapeFromConstantArrayReturnRector::class,
    ] );
};
