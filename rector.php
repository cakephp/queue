<?php
declare(strict_types=1);

use Rector\CodeQuality\Rector\If_\SimplifyIfElseToTernaryRector;
use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveNullTagValueNodeRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;
use Rector\TypeDeclarationDocblocks\Rector\ClassMethod\DocblockReturnArrayFromDirectArrayInstanceRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        DisallowedEmptyRuleFixerRector::class,
        SimplifyIfElseToTernaryRector::class,
        MakeInheritedMethodVisibilitySameAsParentRector::class,
        RemoveNullTagValueNodeRector::class,
        RemoveUselessReturnTagRector::class,
        DocblockReturnArrayFromDirectArrayInstanceRector::class => [
            __DIR__ . '/src/Mailer/Transport/QueueTransport.php',
        ],
    ])
    ->withParallel()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarationDocblocks: true,
    );
