<?php

declare(strict_types=1);

namespace App\Forms\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use DomainException;
use InvalidArgumentException;

final class ChurchForm extends WorkflowRecord
{
    public readonly string $publicToken;

    public function __construct(public readonly string $title, public readonly array $fields, public readonly string $description = '', public readonly bool $published = false, public readonly ?string $eventId = null, public readonly ?string $occurrenceId = null, string $publicToken = '', string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0)
    {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::text($title, 200);
        if (mb_strlen($description) > 2000) {
            throw new InvalidArgumentException('Description is too long.');
        }
        if ((null === $eventId) !== (null === $occurrenceId)) {
            throw new InvalidArgumentException('Choose both an event and occurrence.');
        }
        if (null !== $eventId) {
            Input::id($eventId);
            Input::id($occurrenceId);
        }
        if (!array_is_list($fields) || count($fields) < 1 || count($fields) > 20) {
            throw new InvalidArgumentException('A form needs between 1 and 20 fields.');
        }
        $keys = [];
        foreach ($fields as $field) {
            if (!is_array($field) || array_diff(array_keys($field), ['key', 'label', 'type', 'required', 'options']) || !is_string($field['key'] ?? null) || !preg_match('/^[a-z][a-z0-9_]{0,39}$/D', $field['key']) || in_array($field['key'], $keys, true)) {
                throw new InvalidArgumentException('Field keys must be unique lowercase names.');
            }
            $keys[] = $field['key'];
            if (!is_string($field['label'] ?? null)) {
                throw new InvalidArgumentException('Field label is required.');
            }
            Input::text($field['label'], 200);
            if (!in_array($field['type'] ?? null, ['text', 'textarea', 'email', 'select', 'checkbox'], true) || !is_bool($field['required'] ?? null)) {
                throw new InvalidArgumentException('Choose a supported field type and required flag.');
            }
            $options = $field['options'] ?? [];
            if (!is_array($options) || !array_is_list($options) || count($options) > 20 || ('select' === $field['type'] && count($options) < 1)) {
                throw new InvalidArgumentException('Choices need between 1 and 20 options.');
            }
            foreach ($options as $option) {
                if (!is_string($option)) {
                    throw new InvalidArgumentException('Choices must be text.');
                } Input::text($option, 100);
            }
            if (count(array_unique($options)) !== count($options) || ('select' !== $field['type'] && [] !== $options)) {
                throw new InvalidArgumentException('Only choice fields may have distinct options.');
            }
        }
        $this->publicToken = '' === $publicToken ? bin2hex(random_bytes(32)) : $publicToken;
        if (!preg_match('/^[a-f0-9]{64}$/D', $this->publicToken)) {
            throw new InvalidArgumentException('Invalid public token.');
        }
    }

    public function edit(string $title, array $fields, string $description, ?string $eventId, ?string $occurrenceId): self
    {
        if ($this->published) {
            throw new DomainException('Unpublish the form before editing it.');
        }

        return $this->changed(compact('title', 'fields', 'description', 'eventId', 'occurrenceId'));
    }

    public function publish(bool $published): self
    {
        return $this->changed(compact('published'));
    }

    public function answers(array $answers): array
    {
        if (array_diff(array_keys($answers), array_column($this->fields, 'key'))) {
            throw new InvalidArgumentException('The response contains unknown fields.');
        }
        $clean = [];
        foreach ($this->fields as $field) {
            $value = $answers[$field['key']] ?? ('checkbox' === $field['type'] ? false : '');
            if ('checkbox' === $field['type']) {
                if (!is_bool($value) || ($field['required'] && !$value)) {
                    throw new InvalidArgumentException($field['label'].': please confirm this checkbox.');
                }
            } else {
                if (!is_string($value)) {
                    throw new InvalidArgumentException($field['label'].': enter text.');
                }
                $value = trim($value);
                if (($field['required'] && '' === $value) || mb_strlen($value) > ('textarea' === $field['type'] ? 4000 : 1000)) {
                    throw new InvalidArgumentException($field['label'].': a valid response is required.');
                }
                if ('' !== $value && 'email' === $field['type'] && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException($field['label'].': enter a valid email address.');
                }
                if ('' !== $value && 'select' === $field['type'] && !in_array($value, $field['options'], true)) {
                    throw new InvalidArgumentException($field['label'].': choose an available option.');
                }
            }
            $clean[$field['key']] = $value;
        }

        return $clean;
    }
}
