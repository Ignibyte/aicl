<?php

namespace Aicl\Rlm;

class EntitySignature
{
    /**
     * @param  array<string, string>  $fields  Field definitions: ['name' => 'string', 'status' => 'enum:TaskStatus']
     * @param  array<int, string>  $states  State names: ['draft', 'active', 'completed']
     * @param  array<int, string>  $relationships  Relationship defs: ['belongsTo:User:owner', 'hasMany:Comment']
     * @param  array<int, string>  $features  Enabled features: ['media', 'pdf', 'notifications', 'widgets', 'views', 'ai_context']
     */
    public function __construct(
        public readonly string $entityName,
        public readonly array $fields = [],
        public readonly array $states = [],
        public readonly array $relationships = [],
        public readonly array $features = [],
    ) {}

    /**
     * List of expected file targets based on the entity name and features.
     *
     * @return array<int, string>
     */
    public function expectedFiles(): array
    {
        $files = [
            'model',
            'migration',
            'factory',
            'policy',
            'observer',
            'filament',
            'form',
            'infolist',
            'test',
        ];

        if (in_array('views', $this->features, true)) {
            $files[] = 'blade_view';
            $files[] = 'view_controller';
        }

        return $files;
    }

    /**
     * Estimate the expected pattern count based on features.
     */
    public function expectedPatternCount(): int
    {
        $patterns = PatternRegistry::all($this->entityName);

        return count($patterns);
    }

    /**
     * Build entity context array for recall services.
     *
     * @return array<string, mixed>
     */
    public function toContext(): array
    {
        return [
            'has_states' => $this->states !== [],
            'has_media' => in_array('media', $this->features, true),
            'has_enum' => $this->hasEnumFields(),
            'has_pdf' => in_array('pdf', $this->features, true),
            'has_notifications' => in_array('notifications', $this->features, true),
            'has_tagging' => false,
            'has_search' => true,
            'has_audit' => true,
            'has_api' => true,
            'has_widgets' => in_array('widgets', $this->features, true),
            'has_views' => in_array('views', $this->features, true),
        ];
    }

    /**
     * Generate a deterministic SHA-256 hash of the signature.
     */
    public function hash(): string
    {
        $normalized = [
            'entity_name' => $this->entityName,
            'fields' => $this->fields,
            'states' => $this->states,
            'relationships' => $this->relationships,
            'features' => $this->features,
        ];

        ksort($normalized);
        ksort($normalized['fields']);
        sort($normalized['states']);
        sort($normalized['relationships']);
        sort($normalized['features']);

        return hash('sha256', json_encode($normalized));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity_name' => $this->entityName,
            'fields' => $this->fields,
            'states' => $this->states,
            'relationships' => $this->relationships,
            'features' => $this->features,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            entityName: $data['entity_name'] ?? '',
            fields: $data['fields'] ?? [],
            states: $data['states'] ?? [],
            relationships: $data['relationships'] ?? [],
            features: $data['features'] ?? [],
        );
    }

    private function hasEnumFields(): bool
    {
        foreach ($this->fields as $type) {
            if (str_starts_with($type, 'enum:') || $type === 'enum') {
                return true;
            }
        }

        return false;
    }
}
