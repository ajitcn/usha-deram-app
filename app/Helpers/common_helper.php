<?php

if (!function_exists('display_session_msg')) {
    function display_session_msg()
    {
        if (session()->has('status')) {
            if (session()->get('status') === true) {
                return '<div class="alert alert-success text-center">' . session()->get('msg') . '</div>';
            } else {
                return '<div class="alert alert-danger text-center" style="max-height:150px; overflow:auto;">' . session()->get('msg') . '</div>';
            }
        } else {
            return false;
        }
    }
}

if (!function_exists('getDayName')) {
    function getDayName(int $day): string
    {
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

        return $days[$day] ?? 'Unknown';
    }
}

if (!function_exists('getYMD')) {
    function getYMD($dateStr): string
    {
        return usha_derma_parse_date($dateStr) ?? '';
    }
}

if (!function_exists('_dateOnly')) {
    function _dateOnly($dateStr): string
    {
        return usha_derma_format_date($dateStr);
    }
}

if (!function_exists('usha_derma_parse_date')) {
    /** Normalize an exact user/API date to the database ISO format. */
    function usha_derma_parse_date(mixed $value, bool $allowIso = true): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $formats = $allowIso ? ['!d-m-Y', '!Y-m-d'] : ['!d-m-Y'];

        foreach ($formats as $format) {
            if (($format === '!d-m-Y' && ! preg_match('/^\d{2}-\d{2}-\d{4}$/', $value))
                || ($format === '!Y-m-d' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value))) {
                continue;
            }

            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && $date->format($format === '!d-m-Y' ? 'd-m-Y' : 'Y-m-d') === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}

if (!function_exists('usha_derma_format_date')) {
    /** Format a stored/API date as the user-facing dd-mm-yyyy value. */
    function usha_derma_format_date(mixed $value): string
    {
        $iso = usha_derma_parse_date($value);

        if ($iso === null) {
            return '';
        }

        return substr($iso, 8, 2) . '-' . substr($iso, 5, 2) . '-' . substr($iso, 0, 4);
    }
}

if (!function_exists('usha_derma_format_time')) {
    /** Format a stored/API time as a user-facing 12-hour value. */
    function usha_derma_format_time(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $format = preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $value) ? '!H:i:s' : '!H:i';
        $time = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($time === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return $value;
        }

        return $time->format('h:i A');
    }
}

if (!function_exists('usha_derma_patient_age')) {
    /** Prefer an explicitly recorded age, then derive whole years from DOB. */
    function usha_derma_patient_age($age, $dateOfBirth, ?\DateTimeImmutable $today = null): ?int
    {
        if ($age !== null && trim((string) $age) !== '' && filter_var($age, FILTER_VALIDATE_INT) !== false) {
            $explicitAge = (int) $age;
            if ($explicitAge >= 0 && $explicitAge <= 130) {
                return $explicitAge;
            }
        }

        $dob = trim((string) $dateOfBirth);
        if ($dob === '') {
            return null;
        }

        try {
            $birthDate = new \DateTimeImmutable($dob);
            $referenceDate = $today ?? new \DateTimeImmutable('today');
            return $birthDate > $referenceDate ? null : $birthDate->diff($referenceDate)->y;
        } catch (\Throwable) {
            return null;
        }
    }
}

if (!function_exists('patient_source_label')) {
    function patient_source_label(mixed $source): string
    {
        return match ((string) $source) {
            'self' => 'Self',
            'core_api' => 'Core API',
            'lead' => 'Lead',
            default => 'Unknown',
        };
    }
}

if (!function_exists('patient_source_badge_class')) {
    function patient_source_badge_class(mixed $source): string
    {
        return match ((string) $source) {
            'self' => 'badge-secondary',
            'core_api' => 'badge-info',
            'lead' => 'badge-primary',
            default => 'badge-light',
        };
    }
}

if (!function_exists('display_role_label')) {
    /**
     * Translate legacy stored role labels into the current public terminology.
     * Role codes, permission keys, and authorization checks remain unchanged.
     */
    function display_role_label(mixed $label, mixed $roleCode = null): string
    {
        $text = trim((string) $label);

        if (strtoupper(trim((string) $roleCode)) === 'PHARMACY_ASSISTANT') {
            return 'Pharmacy Assistant';
        }

        return $text;
    }
}

if (!function_exists('display_permission_label')) {
    function display_permission_label(mixed $label): string
    {
        $text = trim((string) $label);

        return $text;
    }
}

if (!function_exists('pharmacy_status_meta')) {
    /** @return array{value:string,label:string,tone:string} */
    function pharmacy_status_meta(string $domain, mixed $status): array
    {
        $value = strtolower(trim((string) $status));
        $value = str_replace([' ', '-'], '_', $value);
        $maps = [
            'order' => [
                'pending' => ['Pending', 'pending'], 'approved' => ['Approved', 'approved'], 'confirmed' => ['Confirmed', 'approved'],
                'processing' => ['Processing', 'processing'], 'ready' => ['Ready', 'success'], 'fulfilled' => ['Completed', 'success'],
                'delivered' => ['Delivered', 'success'], 'cancelled' => ['Cancelled', 'danger'], 'rejected' => ['Rejected', 'danger'], 'failed' => ['Failed', 'danger'],
            ],
            'payment' => [
                'pending' => ['Unpaid', 'pending'], 'unpaid' => ['Unpaid', 'pending'], 'authorized' => ['Authorized', 'approved'],
                'partially_paid' => ['Partially Paid', 'partial'], 'paid' => ['Paid', 'success'], 'succeeded' => ['Paid', 'success'], 'failed' => ['Failed', 'danger'],
                'cancelled' => ['Cancelled', 'danger'], 'refunded' => ['Refunded', 'refunded'],
            ],
            'delivery' => [
                'pending' => ['Pending', 'pending'], 'packed' => ['Packed', 'processing'], 'dispatched' => ['Dispatched', 'processing'],
                'out_for_delivery' => ['Out for Delivery', 'processing'], 'delivered' => ['Delivered', 'success'],
                'failed' => ['Failed', 'danger'], 'returned' => ['Returned', 'danger'],
            ],
        ];
        if ($value === '' || $value === 'not_recorded' || $value === 'unknown') return ['value' => '', 'label' => 'Not recorded', 'tone' => 'neutral'];
        $entry = $maps[$domain][$value] ?? null;
        if ($entry === null) return ['value' => $value, 'label' => 'Unknown status', 'tone' => 'neutral'];
        return ['value' => $value, 'label' => $entry[0], 'tone' => $entry[1]];
    }
}
