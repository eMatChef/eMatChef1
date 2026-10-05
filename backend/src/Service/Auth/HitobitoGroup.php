<?php

declare(strict_types=1);

namespace App\Service\Auth;

final readonly class HitobitoGroup
{
    public function __construct(
        public string $id,
        public ?string $parentId,
        public string $type,
        public string $name,
        public ?string $layerGroupId = null,
    ) {}

    /**
     * @param array<string, mixed> $resource
     */
    public static function fromJsonApi(array $resource): self
    {
        if (array_is_list($resource)) {
            throw new HitobitoGroupParseException('invalid_resource', 'Hitobito group resource is not an object');
        }
        $attributes = $resource['attributes'] ?? null;
        if (!is_array($attributes)) {
            throw new HitobitoGroupParseException('invalid_attributes', 'Hitobito group attributes are malformed');
        }
        $id = self::normalizeId($resource['id'] ?? null);
        $parentAttributeId = self::normalizeId($attributes['parent_id'] ?? null);
        $parentRelationshipId = self::relationshipId($resource, 'parent');
        $parentId = $parentAttributeId ?? $parentRelationshipId;
        $type = $attributes['group_type'] ?? $attributes['type'] ?? null;
        $name = $attributes['name'] ?? null;
        $layerGroupAttributeId = self::normalizeId($attributes['layer_group_id'] ?? null);
        $layerGroupRelationshipId = self::relationshipId($resource, 'layer_group');
        $layerGroupId = $layerGroupAttributeId ?? $layerGroupRelationshipId;

        if (
            ($resource['type'] ?? null) !== 'groups'
        ) {
            throw new HitobitoGroupParseException('invalid_resource_type', 'Hitobito group resource type is invalid');
        }
        if ($id === null) {
            throw new HitobitoGroupParseException('invalid_resource_id', 'Hitobito group resource id is invalid');
        }
        if (!is_string($type) || $type === '') {
            throw new HitobitoGroupParseException('invalid_group_type', 'Hitobito group type is missing or invalid');
        }
        if (!is_string($name)) {
            throw new HitobitoGroupParseException('invalid_name', 'Hitobito group name is missing or invalid');
        }

        if (
            array_key_exists('parent_id', $attributes)
            && $attributes['parent_id'] !== null
            && $parentAttributeId === null
        ) {
            throw new HitobitoGroupParseException('invalid_parent_attribute', 'Hitobito parent id attribute is invalid');
        }
        if (
            array_key_exists('layer_group_id', $attributes)
            && $attributes['layer_group_id'] !== null
            && $layerGroupAttributeId === null
        ) {
            throw new HitobitoGroupParseException('invalid_layer_group_attribute', 'Hitobito layer group id attribute is invalid');
        }

        if (
            $parentAttributeId !== null
            && $parentRelationshipId !== null
            && $parentAttributeId !== $parentRelationshipId
        ) {
            throw new HitobitoGroupParseException('parent_id_conflict', 'Hitobito parent ids conflict');
        }
        if (
            $layerGroupAttributeId !== null
            && $layerGroupRelationshipId !== null
            && $layerGroupAttributeId !== $layerGroupRelationshipId
        ) {
            throw new HitobitoGroupParseException('layer_group_id_conflict', 'Hitobito layer group ids conflict');
        }

        return new self($id, $parentId, $type, $name, $layerGroupId);
    }

    private static function normalizeId(mixed $value): ?string
    {
        if (is_int($value) && $value > 0) {
            return (string) $value;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $resource
     */
    private static function relationshipId(array $resource, string $name): ?string
    {
        $relationships = $resource['relationships'] ?? null;
        if (!array_key_exists('relationships', $resource)) {
            return null;
        }
        if (!is_array($relationships)) {
            throw new HitobitoGroupParseException(
                $name === 'parent' ? 'invalid_parent_relationship' : 'invalid_layer_group_relationship',
                'Hitobito group relationships are malformed',
            );
        }
        if (!array_key_exists($name, $relationships)) {
            return null;
        }
        $relationship = $relationships[$name];
        if (!is_array($relationship)) {
            throw new HitobitoGroupParseException(
                $name === 'parent' ? 'invalid_parent_relationship' : 'invalid_layer_group_relationship',
                'Hitobito group relationship is malformed',
            );
        }
        if (!array_key_exists('data', $relationship)) {
            return null;
        }
        if ($relationship['data'] === null) {
            return null;
        }
        if (!is_array($relationship['data'])) {
            throw new HitobitoGroupParseException(
                $name === 'parent' ? 'invalid_parent_relationship' : 'invalid_layer_group_relationship',
                'Hitobito group relationship is malformed',
            );
        }
        $id = self::normalizeId($relationship['data']['id'] ?? null);
        if ($id === null) {
            throw new HitobitoGroupParseException(
                $name === 'parent' ? 'invalid_parent_relationship' : 'invalid_layer_group_relationship',
                'Hitobito group relationship id is malformed',
            );
        }

        return $id;
    }
}
