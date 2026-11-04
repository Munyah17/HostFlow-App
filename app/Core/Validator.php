<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Simple rule-based validator.
 * Rules: required, email, numeric, integer, min:n, max:n (string length or numeric value), in:a,b,
 * date, domain, confirmed, unique:table,column[,ignoreId], exists:table,column, regex:/.../, url
 */
final class Validator
{
    public static function make(array $data, array $rules, array $labels = []): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
            $rulesList = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $isEmpty = $value === null || $value === '' || $value === [];
            $numeric = in_array('numeric', $rulesList, true) || in_array('integer', $rulesList, true);
            foreach ($rulesList as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                if ($name !== 'required' && $isEmpty) {
                    continue;
                }
                $error = match ($name) {
                    'required' => $isEmpty ? "{$label} is required." : null,
                    'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "{$label} must be a valid email address.",
                    'numeric' => is_numeric($value) ? null : "{$label} must be a number.",
                    'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "{$label} must be a whole number.",
                    'min' => $numeric
                        ? ((float) $value >= (float) $arg ? null : "{$label} must be at least {$arg}.")
                        : (mb_strlen((string) $value) >= (int) $arg ? null : "{$label} must be at least {$arg} characters."),
                    'max' => $numeric
                        ? ((float) $value <= (float) $arg ? null : "{$label} may not be greater than {$arg}.")
                        : (mb_strlen((string) $value) <= (int) $arg ? null : "{$label} may not exceed {$arg} characters."),
                    'in' => in_array((string) $value, explode(',', (string) $arg), true) ? null : "{$label} has an invalid value.",
                    'date' => self::isDate((string) $value) ? null : "{$label} must be a valid date (YYYY-MM-DD).",
                    'domain' => self::isDomain((string) $value) ? null : "{$label} must be a valid domain name (e.g. example.co.zw).",
                    'url' => filter_var($value, FILTER_VALIDATE_URL) ? null : "{$label} must be a valid URL.",
                    'regex' => preg_match((string) $arg, (string) $value) ? null : "{$label} has an invalid format.",
                    'confirmed' => ($data[$field . '_confirmation'] ?? null) === $value ? null : "{$label} confirmation does not match.",
                    'unique' => self::unique((string) $arg, $value) ? null : "{$label} is already in use.",
                    'exists' => self::exists((string) $arg, $value) ? null : "Selected {$label} does not exist.",
                    default => throw new \InvalidArgumentException("Unknown validation rule {$name}"),
                };
                if ($error) {
                    $errors[$field] = $error;
                    break;
                }
            }
        }
        return $errors;
    }

    public static function isDate(string $value): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value;
    }

    public static function isDomain(string $value): bool
    {
        return (bool) preg_match('/^(?=.{4,253}$)(?!-)([a-z0-9-]{1,63}(?<!-)\.)+[a-z]{2,63}$/i', $value);
    }

    private static function unique(string $arg, mixed $value): bool
    {
        [$table, $column, $ignore] = array_pad(explode(',', $arg), 3, null);
        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
        $params = [$value];
        if ($ignore) {
            $sql .= ' AND id <> ?';
            $params[] = (int) $ignore;
        }
        return (int) db()->fetchColumn($sql, $params) === 0;
    }

    private static function exists(string $arg, mixed $value): bool
    {
        [$table, $column] = array_pad(explode(',', $arg), 2, 'id');
        return (int) db()->fetchColumn("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?", [$value]) > 0;
    }
}
