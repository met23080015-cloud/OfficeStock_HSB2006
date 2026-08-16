<?php
declare(strict_types=1);

final class Validator
{
    public static function requiredString(array $data, string $key, int $max = 255): string
    {
        $value = trim((string)($data[$key] ?? ''));
        if ($value === '') throw new DomainException("$key is required.");
        if (mb_strlen($value) > $max) throw new DomainException("$key is too long.");
        return $value;
    }

    public static function optionalString(array $data, string $key, int $max = 255): ?string
    {
        $value = trim((string)($data[$key] ?? ''));
        if ($value === '') return null;
        if (mb_strlen($value) > $max) throw new DomainException("$key is too long.");
        return $value;
    }

    public static function email(array $data, string $key, bool $required = true): ?string
    {
        $value = strtolower(trim((string)($data[$key] ?? '')));
        if ($value === '' && !$required) return null;
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) throw new DomainException("$key is invalid.");
        return $value;
    }

    public static function positiveInt(mixed $value, string $name): int
    {
        $v = filter_var($value, FILTER_VALIDATE_INT);
        if ($v === false || $v <= 0) throw new DomainException("$name must be greater than 0.");
        return (int)$v;
    }

    public static function nonNegativeInt(mixed $value, string $name): int
    {
        $v = filter_var($value, FILTER_VALIDATE_INT);
        if ($v === false || $v < 0) throw new DomainException("$name must be 0 or greater.");
        return (int)$v;
    }

    public static function nonNegativeFloat(mixed $value, string $name): float
    {
        if (!is_numeric($value) || (float)$value < 0) throw new DomainException("$name must be 0 or greater.");
        return round((float)$value, 2);
    }

    public static function enum(mixed $value, array $allowed, string $name): string
    {
        $v = strtoupper(trim((string)$value));
        if (!in_array($v, $allowed, true)) throw new DomainException("$name is invalid.");
        return $v;
    }

    public static function items(mixed $raw): array
    {
        if (!is_array($raw) || $raw === []) throw new DomainException('At least one item is required.');
        $out = [];
        $seen = [];
        foreach ($raw as $i => $row) {
            if (!is_array($row)) throw new DomainException("Item #".($i+1)." is invalid.");
            $pid = self::positiveInt($row['product_id'] ?? null, "item.product_id");
            $qty = self::positiveInt($row['quantity'] ?? null, "item.quantity");
            if (isset($seen[$pid])) throw new DomainException('Duplicate products are not allowed in one request.');
            $seen[$pid] = true;
            $out[] = ['product_id' => $pid, 'quantity' => $qty];
        }
        return $out;
    }
}
