<?php

namespace Modules\Foundation\Support;

/**
 * Central registry: category => key => meta.
 * meta: label, type (string|text|bool|int|decimal|json|secret|select|file), default, rules, perm (manage permission), scope (global|branch), restart (deployment-managed).
 */
class SettingsRegistry
{
    public static function all(): array
    {
        return [
            'general' => [
                'app.name' => ['label' => 'Application name', 'type' => 'string', 'default' => 'Amoleck HMS', 'rules' => 'required|string|max:100', 'perm' => 'foundation.settings.manage_general', 'scope' => 'global'],
                'app.description' => ['label' => 'Application description', 'type' => 'text', 'default' => 'Physiotherapy Hospital Management', 'rules' => 'nullable|string|max:500', 'perm' => 'foundation.settings.manage_general', 'scope' => 'global'],
                'app.default_dashboard' => ['label' => 'Default landing page', 'type' => 'select', 'options' => ['admin.dashboard' => 'Dashboard'], 'default' => 'admin.dashboard', 'rules' => 'required|string', 'perm' => 'foundation.settings.manage_general', 'scope' => 'global'],
                'app.maintenance_notice' => ['label' => 'Maintenance announcement', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:500', 'perm' => 'foundation.settings.manage_general', 'scope' => 'global'],
            ],
            'localization' => [
                'app.locale' => ['label' => 'Default language', 'type' => 'select', 'options' => ['en' => 'English', 'sw' => 'Swahili'], 'default' => 'en', 'rules' => 'required|in:en,sw', 'perm' => 'foundation.settings.manage_localization', 'scope' => 'global'],
                'app.locales' => ['label' => 'Available languages', 'type' => 'json', 'default' => ['en', 'sw'], 'rules' => 'required|array', 'perm' => 'foundation.settings.manage_localization', 'scope' => 'global'],
                'app.number_locale' => ['label' => 'Number formatting locale', 'type' => 'select', 'options' => ['en' => '1,234.56', 'sw' => '1,234.56 (SW)'], 'default' => 'en', 'rules' => 'required|string', 'perm' => 'foundation.settings.manage_localization', 'scope' => 'global'],
                'app.fallback_locale' => ['label' => 'Translation fallback', 'type' => 'select', 'options' => ['en' => 'English', 'sw' => 'Swahili'], 'default' => 'en', 'rules' => 'required|in:en,sw', 'perm' => 'foundation.settings.manage_localization', 'scope' => 'global'],
            ],
            'datetime' => [
                'app.timezone' => ['label' => 'Default timezone', 'type' => 'select', 'options' => ['Africa/Dar_es_Salaam' => 'Africa/Dar_es_Salaam', 'Africa/Nairobi' => 'Africa/Nairobi', 'UTC' => 'UTC'], 'default' => 'Africa/Dar_es_Salaam', 'rules' => 'required|timezone', 'perm' => 'foundation.settings.manage_general', 'scope' => 'branch'],
                'app.date_format' => ['label' => 'Date format', 'type' => 'select', 'options' => ['d/m/Y' => 'DD/MM/YYYY', 'Y-m-d' => 'YYYY-MM-DD', 'm/d/Y' => 'MM/DD/YYYY'], 'default' => 'd/m/Y', 'rules' => 'required|string', 'perm' => 'foundation.settings.manage_general', 'scope' => 'branch'],
                'app.time_format' => ['label' => 'Time format', 'type' => 'select', 'options' => ['H:i' => '24-hour', 'h:i A' => '12-hour'], 'default' => 'H:i', 'rules' => 'required|string', 'perm' => 'foundation.settings.manage_general', 'scope' => 'branch'],
                'app.week_start' => ['label' => 'First day of week', 'type' => 'select', 'options' => ['monday' => 'Monday', 'sunday' => 'Sunday'], 'default' => 'monday', 'rules' => 'required|in:monday,sunday', 'perm' => 'foundation.settings.manage_general', 'scope' => 'branch'],
            ],
            'currency' => [
                'app.currency' => ['label' => 'Default currency', 'type' => 'select', 'options' => ['TZS' => 'TZS', 'USD' => 'USD', 'KES' => 'KES'], 'default' => 'TZS', 'rules' => 'required|string|max:5', 'perm' => 'foundation.settings.manage_general', 'scope' => 'branch'],
                'app.currency_symbol' => ['label' => 'Currency symbol', 'type' => 'string', 'default' => 'TSh', 'rules' => 'required|string|max:10', 'perm' => 'foundation.settings.manage_general', 'scope' => 'branch'],
                'app.currency_position' => ['label' => 'Symbol position', 'type' => 'select', 'options' => ['before' => 'Before amount', 'after' => 'After amount'], 'default' => 'before', 'rules' => 'required|in:before,after', 'perm' => 'foundation.settings.manage_general', 'scope' => 'branch'],
                'app.currency_decimals' => ['label' => 'Decimal places', 'type' => 'int', 'default' => 2, 'rules' => 'required|integer|min:0|max:4', 'perm' => 'foundation.settings.manage_general', 'scope' => 'branch'],
            ],
            'security' => [
                'auth.session_lifetime' => ['label' => 'Session lifetime (minutes)', 'type' => 'int', 'default' => 120, 'rules' => 'required|integer|min:5|max:1440', 'perm' => 'foundation.settings.manage_security', 'scope' => 'global'],
                'auth.password_min_length' => ['label' => 'Password minimum length', 'type' => 'int', 'default' => 8, 'rules' => 'required|integer|min:8|max:64', 'perm' => 'foundation.settings.manage_security', 'scope' => 'global'],
                'auth.max_failed_logins' => ['label' => 'Max failed logins', 'type' => 'int', 'default' => 5, 'rules' => 'required|integer|min:3|max:20', 'perm' => 'foundation.settings.manage_security', 'scope' => 'global'],
                'auth.lockout_minutes' => ['label' => 'Lockout duration (minutes)', 'type' => 'int', 'default' => 15, 'rules' => 'required|integer|min:1|max:120', 'perm' => 'foundation.settings.manage_security', 'scope' => 'global'],
            ],
            'authentication' => [
                'auth.login_identifier' => ['label' => 'Login identifier', 'type' => 'select', 'options' => ['email' => 'Email only', 'both' => 'Email or username'], 'default' => 'both', 'rules' => 'required|in:email,both', 'perm' => 'foundation.settings.manage_authentication', 'scope' => 'global'],
                'auth.password_reset_enabled' => ['label' => 'Password reset enabled', 'type' => 'bool', 'default' => true, 'rules' => 'required|boolean', 'perm' => 'foundation.settings.manage_authentication', 'scope' => 'global'],
                'auth.remember_me' => ['label' => 'Remember me enabled', 'type' => 'bool', 'default' => true, 'rules' => 'required|boolean', 'perm' => 'foundation.settings.manage_authentication', 'scope' => 'global'],
                'auth.mfa_enabled' => ['label' => 'MFA available', 'type' => 'bool', 'default' => true, 'rules' => 'required|boolean', 'perm' => 'foundation.settings.manage_authentication', 'scope' => 'global'],
            ],
            'notifications' => [
                'notify.mail_from_address' => ['label' => 'Email sender address', 'type' => 'string', 'default' => '', 'rules' => 'nullable|email', 'perm' => 'foundation.settings.manage_notifications', 'scope' => 'global'],
                'notify.mail_from_name' => ['label' => 'Email sender name', 'type' => 'string', 'default' => '', 'rules' => 'nullable|string|max:100', 'perm' => 'foundation.settings.manage_notifications', 'scope' => 'global'],
                'notify.sms_api_key' => ['label' => 'SMS API key', 'type' => 'secret', 'default' => null, 'rules' => 'nullable|string|max:255', 'perm' => 'foundation.settings.manage_secrets', 'scope' => 'global'],
                'notify.whatsapp_token' => ['label' => 'WhatsApp token', 'type' => 'secret', 'default' => null, 'rules' => 'nullable|string|max:255', 'perm' => 'foundation.settings.manage_secrets', 'scope' => 'global'],
            ],
            'uploads' => [
                'uploads.max_size_kb' => ['label' => 'Max upload size (KB)', 'type' => 'int', 'default' => 2048, 'rules' => 'required|integer|min:64|max:20480', 'perm' => 'foundation.settings.manage_uploads', 'scope' => 'global'],
                'uploads.allowed_extensions' => ['label' => 'Allowed extensions', 'type' => 'string', 'default' => 'jpg,jpeg,png,pdf,docx,xlsx', 'rules' => 'required|string|max:255', 'perm' => 'foundation.settings.manage_uploads', 'scope' => 'global'],
                'uploads.allowed_mimes' => ['label' => 'Allowed MIME types', 'type' => 'string', 'default' => 'image/jpeg,image/png,application/pdf', 'rules' => 'required|string|max:500', 'perm' => 'foundation.settings.manage_uploads', 'scope' => 'global'],
                'uploads.disk' => ['label' => 'Storage disk', 'type' => 'select', 'options' => ['public' => 'Public', 'local' => 'Local'], 'default' => 'public', 'rules' => 'required|in:public,local', 'perm' => 'foundation.settings.manage_uploads', 'scope' => 'global'],
            ],
            'reports' => [
                'reports.default_format' => ['label' => 'Default report format', 'type' => 'select', 'options' => ['pdf' => 'PDF', 'xlsx' => 'Excel'], 'default' => 'pdf', 'rules' => 'required|in:pdf,xlsx', 'perm' => 'foundation.settings.manage_reports', 'scope' => 'branch'],
                'reports.page_size' => ['label' => 'Page size', 'type' => 'select', 'options' => ['a4' => 'A4', 'a5' => 'A5', 'letter' => 'Letter'], 'default' => 'a4', 'rules' => 'required|in:a4,a5,letter', 'perm' => 'foundation.settings.manage_reports', 'scope' => 'branch'],
                'reports.orientation' => ['label' => 'Orientation', 'type' => 'select', 'options' => ['portrait' => 'Portrait', 'landscape' => 'Landscape'], 'default' => 'portrait', 'rules' => 'required|in:portrait,landscape', 'perm' => 'foundation.settings.manage_reports', 'scope' => 'branch'],
            ],
            'integrations' => [
                'integrations.payment_api_key' => ['label' => 'Payment provider API key', 'type' => 'secret', 'default' => null, 'rules' => 'nullable|string|max:255', 'perm' => 'foundation.settings.manage_secrets', 'scope' => 'global'],
                'integrations.external_api_url' => ['label' => 'External API base URL', 'type' => 'string', 'default' => '', 'rules' => 'nullable|url', 'perm' => 'foundation.settings.manage_integrations', 'scope' => 'global'],
            ],
            'deployment' => [
                'app.env' => ['label' => 'Application environment', 'type' => 'string', 'default' => 'production', 'rules' => 'nullable', 'perm' => 'foundation.settings.manage_security', 'scope' => 'global', 'restart' => true, 'readonly' => true],
            ],
        ];
    }

    public static function flat(): array
    {
        return collect(static::all())->flatMap(fn ($keys) => $keys)->all();
    }

    public static function keys(): array
    {
        return array_keys(static::flat());
    }

    public static function find(string $key): ?array
    {
        return static::flat()[$key] ?? null;
    }
}
