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
 * @since         3.0.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Cake\Queue\Client;

use InvalidArgumentException;

/**
 * Pure PHP replacement for Symfony Config normalization.
 */
final class EnqueueClientConfig
{
    /**
     * Router processor service name.
     *
     * @var string
     */
    public const ROUTER_PROCESSOR = 'enqueue.client.router_processor';

    /**
     * Normalize user config into transport, client and extensions sections.
     *
     * @param string|array<string, mixed>|null $config DSN string or ['transport'=>..., 'client'=>..., 'extensions'=>...]
     * @return array{transport: array<string, mixed>, client: array<string, mixed>, extensions: array<string, mixed>} Normalized config.
     * @throws \InvalidArgumentException When the config has an unexpected shape or unknown keys.
     */
    public static function normalize(string|array|null $config): array
    {
        if (in_array($config, [null, '', []], true)) {
            $config = ['transport' => ['dsn' => 'null:']];
        }

        if (is_string($config)) {
            $config = [
                'transport' => $config,
                'client' => true,
            ];
        }

        $unknown = array_diff(array_keys($config), ['transport', 'client', 'extensions']);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('Unknown queue config key(s): `%s`. Allowed keys: transport, client, extensions.', implode('`, `', $unknown)));
        }

        return [
            'transport' => self::normalizeTransport($config['transport'] ?? null),
            'client' => self::normalizeClient($config['client'] ?? true),
            'extensions' => self::normalizeExtensions($config['extensions'] ?? []),
        ];
    }

    /**
     * Normalize the transport section.
     *
     * Accepts an empty value, a DSN string or a full array. Extra keys are
     * passed through to the connection factory.
     *
     * @param string|array<string, mixed>|null $transport DSN string or transport array.
     * @return array<string, mixed> Normalized transport config.
     * @throws \InvalidArgumentException When the transport section has an unexpected shape.
     */
    public static function normalizeTransport(string|array|null $transport): array
    {
        if (in_array($transport, [null, '', []], true)) {
            return ['dsn' => 'null:'];
        }

        if (is_string($transport)) {
            return ['dsn' => $transport];
        }

        if (!isset($transport['dsn']) || !is_string($transport['dsn']) || $transport['dsn'] === '') {
            throw new InvalidArgumentException('Transport config must contain a non-empty `dsn` key.');
        }

        return $transport;
    }

    /**
     * Normalize the client section.
     *
     * Merges user values over the default prefix, separator, app name,
     * router and queue names.
     *
     * @param bool|array<string, mixed> $client Client config or true for defaults.
     * @return array<string, mixed> Normalized client config.
     * @throws \InvalidArgumentException When the client section has an unexpected shape.
     */
    public static function normalizeClient(bool|array $client = true): array
    {
        $defaults = [
            'prefix' => 'enqueue',
            'separator' => '.',
            'app_name' => 'app',
            'router_topic' => 'default',
            'router_queue' => 'default',
            'default_queue' => 'default',
            'router_processor' => null,
            'redelivered_delay_time' => 0,
            'driver_options' => [],
        ];

        if ($client === true) {
            return $defaults;
        }

        if ($client === false) {
            throw new InvalidArgumentException('Client config `false` is not supported, use `true` for defaults or an array with client options.');
        }

        $unknown = array_diff(array_keys($client), array_keys($defaults));
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('Unknown client config key(s): `%s`.', implode('`, `', $unknown)));
        }

        $merged = array_merge($defaults, $client);

        foreach (['prefix', 'separator', 'app_name'] as $key) {
            if (!is_string($merged[$key])) {
                throw new InvalidArgumentException(sprintf('Client config `%s` must be a string.', $key));
            }
        }

        foreach (['router_topic', 'router_queue', 'default_queue'] as $key) {
            if (!is_string($merged[$key]) || trim($merged[$key]) === '') {
                throw new InvalidArgumentException(sprintf('Client config `%s` must be a non-empty string.', $key));
            }
        }

        if (!is_int($merged['redelivered_delay_time']) || $merged['redelivered_delay_time'] < 0) {
            throw new InvalidArgumentException('Client config `redelivered_delay_time` must be an integer >= 0.');
        }

        if (!is_array($merged['driver_options'])) {
            throw new InvalidArgumentException('Client config `driver_options` must be an array.');
        }

        return $merged;
    }

    /**
     * Normalize the extensions section.
     *
     * @param array<string, mixed> $extensions User extensions config.
     * @return array{signal_extension: bool, reply_extension: bool} Normalized extensions config.
     * @throws \InvalidArgumentException When the extensions section has an unexpected shape.
     */
    public static function normalizeExtensions(array $extensions = []): array
    {
        $unknown = array_diff(array_keys($extensions), ['signal_extension', 'reply_extension']);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('Unknown extensions config key(s): `%s`.', implode('`, `', $unknown)));
        }

        return [
            'signal_extension' => self::normalizeBoolean($extensions['signal_extension'] ?? function_exists('pcntl_signal_dispatch'), 'signal_extension'),
            'reply_extension' => self::normalizeBoolean($extensions['reply_extension'] ?? true, 'reply_extension'),
        ];
    }

    /**
     * Normalize a boolean config value.
     *
     * Accepts booleans as well as integer and string representations
     * (`1`, `0`, `'true'`, `'false'`, `'yes'`, `'no'`, `'on'`, `'off'`).
     *
     * @param mixed $value Raw config value.
     * @param string $key Config key used in the exception message.
     * @return bool Normalized value.
     * @throws \InvalidArgumentException When the value is not recognizable as boolean.
     */
    protected static function normalizeBoolean(mixed $value, string $key): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_string($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (is_bool($normalized)) {
                return $normalized;
            }
        }

        throw new InvalidArgumentException(sprintf('Extensions config `%s` must be a boolean.', $key));
    }
}
