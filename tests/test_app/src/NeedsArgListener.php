<?php
declare(strict_types=1);

namespace TestApp;

use Cake\Event\EventListenerInterface;

/**
 * Listener with a required constructor dependency.
 *
 * Deliberately unresolvable without the container: used to verify that the
 * worker fails loudly instead of fataling with an engine error.
 */
class NeedsArgListener implements EventListenerInterface
{
    public function __construct(
        public readonly string $name,
    ) {
    }

    /**
     * @return array{}
     */
    public function implementedEvents(): array
    {
        return [];
    }
}
