<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class InstitutionSetting extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'institution_name',
        'short_name',
        'legal_id',
        'country',
        'phone',
        'email',
        'address',
        'website',
        'theme_color',
        'accent_color',
        'logo_path',
        'online_enrollment_provisional_days',
        'online_enrollment_notice',
        'attention_hours',
        'gender_policy_message',
        'require_financial_solvency_for_graduation',
        'enabled_modules',
        'receipt_prefix',
        'diploma_footer_text',
        'director_name',
        'director_title',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'online_enrollment_provisional_days' => 'integer',
            'require_financial_solvency_for_graduation' => 'boolean',
            'enabled_modules' => 'array',
        ];
    }

    public static function current(): self
    {
        return Cache::remember('cecap.institution_settings', 300, function (): self {
            return static::query()->first() ?? new static([
                'institution_name' => 'CeCap — SURÍ Proyecto Educativo',
                'short_name' => 'CeCap SURÍ',
                'legal_id' => '3-002-000000',
                'country' => 'Costa Rica',
                'phone' => '+506 2200-0000',
                'email' => 'info@cecap.or.cr',
                'theme_color' => '#0F4C4C',
                'accent_color' => '#C87D32',
                'online_enrollment_provisional_days' => 7,
                'online_enrollment_notice' => 'Su solicitud en línea queda en estado provisional por 7 días naturales. Debe presentarse en CeCap para cancelar la matrícula y la primera mensualidad y así confirmar su cupo.',
                'attention_hours' => 'Lunes a Viernes de 8:00 a.m. a 4:30 p.m. · Sábados de 8:00 a.m. a 12:00 m.d.',
                'gender_policy_message' => 'Actualmente nuestros programas de formación técnica y capacitación están dirigidos exclusivamente a mujeres, por lo que esta solicitud no cumple con los parámetros de ingreso de la institución.',
                'require_financial_solvency_for_graduation' => true,
                'enabled_modules' => array_fill_keys(array_keys(config('cecap.modules', [])), true),
                'receipt_prefix' => 'REC',
            ]);
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('cecap.institution_settings');
    }

    public function isModuleEnabled(string $module): bool
    {
        $modules = $this->enabled_modules ?? [];

        return (bool) ($modules[$module] ?? true);
    }
}
