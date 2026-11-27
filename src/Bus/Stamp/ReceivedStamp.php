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
namespace Cake\Queue\Bus\Stamp;

use Cake\Queue\Bus\NonSendableStampInterface;
use InvalidArgumentException;

/**
 * Marker that the envelope was received from a transport.
 *
 * Lets phase-aware middlewares tell dispatch apart from consume, and guards
 * re-send loops: anything stamped this way flows toward handling, never back
 * into the transport, unless a middleware deliberately strips the stamp.
 *
 * Never leaves the process: writers skip it and the transport-body parser
 * drops forged rows, so the stamp a consumer sees was always attached
 * locally by `BusProcessor`, never read off the wire.
 */
final class ReceivedStamp implements NonSendableStampInterface
{
    /**
     * @param string $transport Transport the envelope was received from.
     */
    public function __construct(
        private readonly string $transport,
    ) {
        if ($transport === '') {
            throw new InvalidArgumentException('ReceivedStamp requires a non-empty string `transport`.');
        }
    }

    /**
     * Get the transport the envelope was received from.
     *
     * @return string
     */
    public function getTransport(): string
    {
        return $this->transport;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'type' => 'ReceivedStamp',
            'v' => 1,
            'transport' => $this->transport,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        if (
            !isset($data['transport']) || !is_string($data['transport']) ||
            $data['transport'] === ''
        ) {
            throw new InvalidArgumentException('ReceivedStamp requires a non-empty string `transport`.');
        }

        return new self($data['transport']);
    }
}
