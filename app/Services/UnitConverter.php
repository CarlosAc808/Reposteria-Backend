<?php

namespace App\Services;

use Exception;

class UnitConverter
{
    /**
     * Define compatible unit families and conversion factors relative to the standard base unit.
     */
    protected static $factors = [
        // Mass (base: kg)
        'kg' => ['family' => 'mass', 'to_base' => 1.0],
        'g'  => ['family' => 'mass', 'to_base' => 0.001],

        // Volume (base: L)
        'L'  => ['family' => 'volume', 'to_base' => 1.0],
        'l'  => ['family' => 'volume', 'to_base' => 1.0],
        'ml' => ['family' => 'volume', 'to_base' => 0.001],

        // Count / Discrete
        'pza'     => ['family' => 'discrete', 'to_base' => 1.0],
        'pieza'   => ['family' => 'discrete', 'to_base' => 1.0],
        'piezas'  => ['family' => 'discrete', 'to_base' => 1.0],
        'paquete' => ['family' => 'package', 'to_base' => 1.0],
        'paq'     => ['family' => 'package', 'to_base' => 1.0],
    ];

    /**
     * Check if two units are compatible.
     */
    public static function areCompatible(string $unitFrom, string $unitTo): bool
    {
        $uFrom = strtolower(trim($unitFrom));
        $uTo = strtolower(trim($unitTo));

        if ($uFrom === $uTo) {
            return true;
        }

        if (!isset(self::$factors[$uFrom]) || !isset(self::$factors[$uTo])) {
            return false;
        }

        return self::$factors[$uFrom]['family'] === self::$factors[$uTo]['family'];
    }

    /**
     * Convert an amount from one unit to another compatible unit.
     */
    public static function convert(float $amount, string $unitFrom, string $unitTo): float
    {
        $uFrom = strtolower(trim($unitFrom));
        $uTo = strtolower(trim($unitTo));

        if ($uFrom === $uTo) {
            return $amount;
        }

        if (!self::areCompatible($uFrom, $uTo)) {
            throw new Exception("Conversión incompatible entre '{$unitFrom}' y '{$unitTo}'. No se permite conversión sin equivalencia configurada.");
        }

        // Convert from source to standard base of family, then to target
        $amountInFamilyBase = $amount * self::$factors[$uFrom]['to_base'];
        $amountInTarget = $amountInFamilyBase / self::$factors[$uTo]['to_base'];

        return round($amountInTarget, 4);
    }

    /**
     * Get list of compatible units for a given base unit.
     */
    public static function getCompatibleUnits(string $baseUnit): array
    {
        $u = strtolower(trim($baseUnit));
        if (!isset(self::$factors[$u])) {
            return [$baseUnit];
        }

        $family = self::$factors[$u]['family'];
        $list = [];
        foreach (self::$factors as $key => $data) {
            if ($data['family'] === $family) {
                $list[] = $key;
            }
        }
        return array_values(array_unique($list));
    }
}
