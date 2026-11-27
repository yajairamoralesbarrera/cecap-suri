<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIdentificationNumber implements ValidationRule
{
    public function __construct(
        protected ?string $identificationType = null
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return;
        }

        if (preg_match('/[^0-9A-Za-z\s\-]/', $raw) === 1) {
            $fail('El número de identificación solo puede contener letras, números y guiones.');

            return;
        }

        $clean = strtoupper((string) preg_replace('/[\s\-]+/', '', $raw));
        $digitsOnly = ctype_digit($clean);
        $len = strlen($clean);

        switch ($this->identificationType) {
            case 'cedula_nacional':
                if (! $digitsOnly || $len !== 9 || preg_match('/^[1-9][0-9]{8}$/', $clean) !== 1) {
                    if ($digitsOnly && ($len === 11 || $len === 12)) {
                        $fail(sprintf(
                            'El número ingresado tiene %d dígitos (formato de DIMEX), pero seleccionó Cédula Nacional (CR) que requiere exactamente 9 dígitos (ej. 1-1234-5678). Verifique el tipo de identificación.',
                            $len
                        ));

                        return;
                    }

                    $fail('La Cédula Nacional (CR) debe tener exactamente 9 dígitos numéricos iniciando del 1 al 9 (ej. 1-1234-5678 o 112345678).');
                }
                break;

            case 'dimex':
                if (! $digitsOnly || ($len !== 11 && $len !== 12)) {
                    if ($digitsOnly && $len === 9) {
                        $fail('El número ingresado tiene 9 dígitos (formato de Cédula Nacional), pero seleccionó DIMEX que requiere 11 o 12 dígitos numéricos. Verifique el tipo de identificación.');

                        return;
                    }

                    $fail('El DIMEX (Residencia CR) debe contener únicamente 11 o 12 dígitos numéricos sin letras (ej. 155812345678).');
                }
                break;

            case 'pasaporte':
                if ($len < 5 || $len > 20 || preg_match('/^[A-Z0-9]{5,20}$/', $clean) !== 1 || preg_match('/[0-9]/', $clean) !== 1) {
                    $fail('El Pasaporte debe tener entre 5 y 20 caracteres alfanuméricos (ej. A12345678).');
                }
                break;

            case 'permiso_refugio':
                if ($len < 6 || $len > 20 || preg_match('/^[A-Z0-9]{6,20}$/', $clean) !== 1 || preg_match('/[0-9]/', $clean) !== 1) {
                    $fail('El Carné de Refugio / Permiso Laboral debe tener entre 6 y 20 caracteres alfanuméricos.');
                }
                break;

            case null:
            case '':
                if ($len < 5 || $len > 20 || preg_match('/^[A-Z0-9]{5,20}$/', $clean) !== 1 || preg_match('/[0-9]/', $clean) !== 1) {
                    $fail('Ingrese un número de identificación válido (Cédula CR de 9 dígitos, DIMEX de 11-12 dígitos o Pasaporte de 5-20 caracteres).');
                }
                break;

            default:
                if ($len < 5 || $len > 25 || preg_match('/^[A-Z0-9]{5,25}$/', $clean) !== 1 || preg_match('/[0-9]/', $clean) !== 1) {
                    $fail('El número de identificación no cumple un formato válido para el tipo seleccionado (5 a 25 caracteres alfanuméricos).');
                }
                break;
        }
    }

    public static function placeholderFor(?string $type): string
    {
        return match ($type) {
            'cedula_nacional' => '1-1234-5678',
            'dimex' => '155812345678',
            'pasaporte' => 'A12345678',
            'permiso_refugio' => 'REF-123456',
            default => 'Número de identificación',
        };
    }

    public static function hintFor(?string $type): string
    {
        return match ($type) {
            'cedula_nacional' => 'Cédula Nacional CR: exactamente 9 dígitos (con o sin guiones, ej. 1-1234-5678).',
            'dimex' => 'DIMEX CR: 11 o 12 dígitos numéricos sin letras (ej. 155812345678).',
            'pasaporte' => 'Pasaporte: 5 a 20 caracteres alfanuméricos.',
            'permiso_refugio' => 'Carné de Refugio / Permiso: 6 a 20 caracteres alfanuméricos.',
            default => 'Ingrese el documento según el tipo seleccionado.',
        };
    }
}
