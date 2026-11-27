<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidEmailDomain implements ValidationRule
{
    /**
     * Terminaciones que no son TLD reales y suelen deberse a errores de digitación.
     *
     * @var list<string>
     */
    private const COMMON_INVALID_TYPOS = [
        'con',
        'cmo',
        'cim',
        'ocm',
        'coom',
        'comm',
        'nett',
        'orgg',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = trim((string) $value);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $fail('Ingrese un correo electrónico válido con terminación correcta (ej. nombre@dominio.com, .net, .org, .cr).');

            return;
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            $fail('Ingrese un correo electrónico válido con terminación correcta (ej. nombre@dominio.com, .net, .org, .cr).');

            return;
        }

        $domain = strtolower($parts[1]);
        if (! str_contains($domain, '.')) {
            $fail('El correo electrónico debe incluir una terminación válida (por ejemplo: .com, .net, .org, .cr, .edu).');

            return;
        }

        $labels = explode('.', $domain);
        foreach ($labels as $label) {
            if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $label)) {
                $fail('El dominio del correo electrónico no tiene un formato válido (ej. nombre@dominio.com, .net, .org, .cr).');

                return;
            }
        }

        $tld = end($labels);
        if (! is_string($tld) || ! preg_match('/^[a-z]{2,24}$/i', $tld) || in_array($tld, self::COMMON_INVALID_TYPOS, true)) {
            $fail('La terminación del correo electrónico no es válida. Verifique que termine correctamente (ej. .com, .net, .org, .cr, .co.cr, .edu).');
        }
    }
}
