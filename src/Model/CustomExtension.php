<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

/**
 * Additional ext:UBLExtension, rendered after sts:DianExtensions (or before it
 * when $beforeDianExtensions is true, as the sector salud examples do). DIAN
 * uses them for sector-specific data:
 *
 *   - Documento equivalente POS: FabricanteSoftware, BeneficiosComprador, PuntoVenta
 *   - Sector salud: CustomTagGeneral / Interoperabilidad
 *
 * Content is a node tree: ['name' => 'X', 'attrs' => [...], 'text' => '...', 'children' => [...]].
 * Values are XML-escaped when rendered.
 */
final class CustomExtension
{
    /** @var list<array<string,mixed>> */
    private array $nodes;

    /** @param list<array<string,mixed>> $nodes */
    public function __construct(array $nodes, private readonly bool $beforeDianExtensions = false)
    {
        $this->nodes = $nodes;
    }

    public function isBeforeDianExtensions(): bool
    {
        return $this->beforeDianExtensions;
    }

    /**
     * The Name/Value layout DIAN uses for POS data:
     *
     *   <Root><Group><Name>k1</Name><Value>v1</Value><Name>k2</Name>…</Group></Root>
     *
     * @param array<string,string|int|float> $pairs
     */
    public static function nameValues(string $root, string $group, array $pairs): self
    {
        $children = [];
        foreach ($pairs as $name => $value) {
            $children[] = ['name' => 'Name', 'text' => (string) $name];
            $children[] = ['name' => 'Value', 'text' => (string) $value];
        }

        return new self([['name' => $root, 'children' => [['name' => $group, 'children' => $children]]]]);
    }

    /** @return list<array<string,mixed>> */
    public function getNodes(): array
    {
        return $this->nodes;
    }
}
