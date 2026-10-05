<?php

declare(strict_types=1);

namespace App\Services\AssetRegistry;

use InvalidArgumentException;

/** Normalises and validates asset identifiers. Raw values are hashed for storage and never logged. */
final class AssetIdentifier
{
    public static function normalise(string $type, string $value): string
    {
        $compact = strtoupper((string) preg_replace('/[\s\-.\/]/', '', trim($value)));

        return match ($type) {
            'imei' => self::imei($compact),
            'vin' => preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $compact) === 1
                ? $compact : throw new InvalidArgumentException('A VIN has 17 letters and digits and never contains I, O or Q. It is on the logbook and the chassis plate.'),
            'chassis_number', 'engine_number' => preg_match('/^[A-Z0-9]{5,30}$/', $compact) === 1
                ? $compact : throw new InvalidArgumentException('Chassis and engine numbers have 5 to 30 letters and digits.'),
            'serial' => preg_match('/^[A-Z0-9]{4,40}$/', $compact) === 1
                ? $compact : throw new InvalidArgumentException('A serial number has 4 to 40 letters and digits. It is usually on the label or in the device settings.'),
            'registration_plate' => preg_match('/^[A-Z0-9]{2,12}$/', $compact) === 1
                ? $compact : throw new InvalidArgumentException('Enter the number plate as shown on the vehicle.'),
            default => throw new InvalidArgumentException('This identifier type is not supported.'),
        };
    }

    /** Keyed hash, scoped by type, so the same digits under two types never collide. */
    public static function hmac(string $type, string $normalised, string $key): string
    {
        return hash_hmac('sha256', $type.':'.$normalised, $key);
    }

    public static function mask(string $normalised): string
    {
        return str_repeat('*', max(0, min(8, strlen($normalised) - 4))).substr($normalised, -4);
    }

    private static function imei(string $digits): string
    {
        if (preg_match('/^\d{15}$/', $digits) !== 1) {
            throw new InvalidArgumentException('An IMEI has 15 digits. Dial *#06# on the phone or check the box label.');
        }
        $sum = 0;
        foreach (str_split($digits) as $index => $digit) {
            $value = (int) $digit;
            if ($index % 2 === 1) {
                $value *= 2;
                $value = $value > 9 ? $value - 9 : $value;
            }
            $sum += $value;
        }
        if ($sum % 10 !== 0) {
            throw new InvalidArgumentException('This IMEI fails its check digit. Compare it with *#06# or the box label.');
        }

        return $digits;
    }
}
