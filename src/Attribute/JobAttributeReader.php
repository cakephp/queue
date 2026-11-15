<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         4.0.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Cake\Queue\Attribute;

use ReflectionClass;

/**
 * Resolves additive job defaults declared via attributes.
 *
 * Resolution order everywhere: explicit value > attribute > static property > global default.
 * Child class attributes win over parent class attributes.
 */
class JobAttributeReader
{
    /**
     * Cached attribute instances per job class.
     *
     * @var array<string, array<string, object|null>>
     */
    protected static array $cache = [];

    /**
     * Get attribute instance from class hierarchy, if present.
     *
     * Child class attributes win over parent class attributes.
     *
     * @param class-string|object $target Job class name or instance.
     * @param class-string $attribute Attribute class name.
     */
    public static function get(string|object $target, string $attribute): ?object
    {
        $class = is_object($target) ? $target::class : $target;

        if (array_key_exists($attribute, static::$cache[$class] ?? [])) {
            return static::$cache[$class][$attribute];
        }

        $resolved = null;
        foreach (static::hierarchy($class) as $candidate) {
            $reflection = new ReflectionClass($candidate);
            $attributes = $reflection->getAttributes($attribute);
            if ($attributes !== []) {
                $resolved = $attributes[0]->newInstance();
            }
        }

        static::$cache[$class][$attribute] = $resolved;

        return $resolved;
    }

    /**
     * Get configured queue name for a job, if declared.
     *
     * @param class-string|object $target Job class name or instance.
     */
    public static function getQueue(string|object $target): ?string
    {
        $attribute = static::get($target, Queue::class);

        return $attribute instanceof Queue ? $attribute->queue : null;
    }

    /**
     * Get configured config name for a job, if declared.
     *
     * @param class-string|object $target Job class name or instance.
     */
    public static function getConfig(string|object $target): ?string
    {
        $attribute = static::get($target, Config::class);

        return $attribute instanceof Config ? $attribute->config : null;
    }

    /**
     * Get configured delay for a job, if declared.
     *
     * @param class-string|object $target Job class name or instance.
     */
    public static function getDelay(string|object $target): ?int
    {
        $attribute = static::get($target, Delay::class);

        return $attribute instanceof Delay ? $attribute->delay : null;
    }

    /**
     * Get configured expiry for a job, if declared.
     *
     * @param class-string|object $target Job class name or instance.
     */
    public static function getExpires(string|object $target): ?int
    {
        $attribute = static::get($target, Expires::class);

        return $attribute instanceof Expires ? $attribute->expires : null;
    }

    /**
     * Get configured priority for a job, if declared.
     *
     * @param class-string|object $target Job class name or instance.
     */
    public static function getPriority(string|object $target): ?string
    {
        $attribute = static::get($target, Priority::class);

        return $attribute instanceof Priority ? $attribute->priority : null;
    }

    /**
     * Get configured max attempts for a job.
     *
     * Attribute wins over static property. Both remain supported.
     *
     * @param class-string|object $target Job class name or instance.
     */
    public static function getMaxAttempts(string|object $target): ?int
    {
        $attribute = static::get($target, MaxAttempts::class);
        if ($attribute instanceof MaxAttempts) {
            return $attribute->maxAttempts;
        }

        $class = is_object($target) ? $target::class : $target;
        if (property_exists($class, 'maxAttempts') && isset($class::$maxAttempts)) {
            $value = $class::$maxAttempts;

            return is_int($value) ? $value : null;
        }

        return null;
    }

    /**
     * Check if a job should be treated as unique.
     *
     * Attribute wins over static property. Both remain supported.
     *
     * @param class-string|object $target Job class name or instance.
     */
    public static function isUnique(string|object $target): bool
    {
        $attribute = static::get($target, ShouldBeUnique::class);
        if ($attribute instanceof ShouldBeUnique) {
            return $attribute->shouldBeUnique;
        }

        $class = is_object($target) ? $target::class : $target;

        return property_exists($class, 'shouldBeUnique') && !empty($class::$shouldBeUnique);
    }

    /**
     * Clear the attribute instance cache.
     *
     * Useful for testing or when classes are reloaded.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        static::$cache = [];
    }

    /**
     * Parent-to-child class hierarchy, including the class itself.
     *
     * @param class-string $class Job class name.
     * @return list<class-string>
     */
    protected static function hierarchy(string $class): array
    {
        $hierarchy = array_reverse(array_values(class_parents($class)));
        $hierarchy[] = $class;

        return $hierarchy;
    }
}
