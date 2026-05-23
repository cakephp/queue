<?php
declare(strict_types=1);

namespace Cake\Queue\TestSuite\Transport;

use Interop\Queue\Message;

/**
 * Test Message
 *
 * Minimal message implementation for testing.
 */
class TestMessage implements Message
{
    /**
     * Message body
     */
    protected string $body;

    /**
     * Message properties
     *
     * @var array<string, mixed>
     */
    protected array $properties;

    /**
     * Message headers
     *
     * @var array<string, mixed>
     */
    protected array $headers;

    /**
     * Constructor
     *
     * @param string $body Message body
     * @param array<string, mixed> $properties Properties
     * @param array<string, mixed> $headers Headers
     */
    public function __construct(string $body = '', array $properties = [], array $headers = [])
    {
        $this->body = $body;
        $this->properties = $properties;
        $this->headers = $headers;
    }

    /**
     * Get body
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Set body
     *
     * @param string $body Body
     * @return void
     */
    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    /**
     * Set property
     *
     * @param string $name Property name
     * @param mixed $value Property value
     * @return void
     */
    public function setProperty(string $name, mixed $value): void
    {
        $this->properties[$name] = $value;
    }

    /**
     * Get property
     *
     * @param string $name Property name
     * @param mixed $default Default value
     * @return mixed
     */
    public function getProperty(string $name, mixed $default = null): mixed
    {
        return $this->properties[$name] ?? $default;
    }

    /**
     * Set header
     *
     * @param string $name Header name
     * @param mixed $value Header value
     * @return void
     */
    public function setHeader(string $name, mixed $value): void
    {
        $this->headers[$name] = $value;
    }

    /**
     * Get header
     *
     * @param string $name Header name
     * @param mixed $default Default value
     * @return mixed
     */
    public function getHeader(string $name, mixed $default = null): mixed
    {
        return $this->headers[$name] ?? $default;
    }

    /**
     * Get properties
     *
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Set properties
     *
     * @param array<string, mixed> $properties Properties
     * @return void
     */
    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
    }

    /**
     * Get headers
     *
     * @return array<string, mixed>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set headers
     *
     * @param array<string, mixed> $headers Headers
     * @return void
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    /**
     * Get redelivered flag
     *
     * @return bool
     */
    public function isRedelivered(): bool
    {
        return false;
    }

    /**
     * Set redelivered flag
     *
     * @param bool $redelivered Redelivered
     * @return void
     */
    public function setRedelivered(bool $redelivered): void
    {
    }

    /**
     * Get correlation ID
     *
     * @return string|null
     */
    public function getCorrelationId(): ?string
    {
        return $this->getHeader('correlation_id');
    }

    /**
     * Set correlation ID
     *
     * @param string|null $correlationId Correlation ID
     * @return void
     */
    public function setCorrelationId(?string $correlationId = null): void
    {
        $this->setHeader('correlation_id', $correlationId);
    }

    /**
     * Get message ID
     *
     * @return string|null
     */
    public function getMessageId(): ?string
    {
        return $this->getHeader('message_id');
    }

    /**
     * Set message ID
     *
     * @param string|null $messageId Message ID
     * @return void
     */
    public function setMessageId(?string $messageId = null): void
    {
        $this->setHeader('message_id', $messageId);
    }

    /**
     * Get timestamp
     *
     * @return int|null
     */
    public function getTimestamp(): ?int
    {
        return $this->getHeader('timestamp');
    }

    /**
     * Set timestamp
     *
     * @param int|null $timestamp Timestamp
     * @return void
     */
    public function setTimestamp(?int $timestamp = null): void
    {
        $this->setHeader('timestamp', $timestamp);
    }

    /**
     * Get reply to
     *
     * @return string|null
     */
    public function getReplyTo(): ?string
    {
        return $this->getHeader('reply_to');
    }

    /**
     * Set reply to
     *
     * @param string|null $replyTo Reply to
     * @return void
     */
    public function setReplyTo(?string $replyTo = null): void
    {
        $this->setHeader('reply_to', $replyTo);
    }
}
