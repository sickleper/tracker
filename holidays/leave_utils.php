<?php
if (!function_exists('isHolidayLeaveType')) {
    function isHolidayLeaveType(array $row): bool
    {
        $isHoliday = $row['is_statutory'] ?? null;
        if ($isHoliday !== null && filter_var($isHoliday, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $leaveType = $row['leave_type'] ?? [];
        if (is_array($leaveType)) {
            $isHolidayLeaveType = $leaveType['is_statutory'] ?? null;
            if ($isHolidayLeaveType !== null && filter_var($isHolidayLeaveType, FILTER_VALIDATE_BOOLEAN)) {
                return true;
            }
        }

        if (isset($row['is_holiday']) && filter_var($row['is_holiday'], FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $typeName = trim((string)($row['type_name'] ?? ''));
        $nestedTypeName = trim((string)($row['leave_type']['type_name'] ?? ''));
        $typeSlug = trim((string)($row['type_slug'] ?? ''));
        $leaveTypeSlug = trim((string)($row['leave_type']['slug'] ?? ''));

        foreach ([$typeName, $nestedTypeName] as $name) {
            if ($name !== '' && stripos($name, 'holiday') !== false) {
                return true;
            }
        }

        foreach ([$typeSlug, $leaveTypeSlug] as $slug) {
            if ($slug !== '' && stripos($slug, 'holiday') !== false) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('resolveYearFilter')) {
    function resolveYearFilter($rawYear): ?int
    {
        if (is_string($rawYear) && ctype_digit($rawYear)) {
            return (int)$rawYear;
        }

        if (is_int($rawYear)) {
            return $rawYear;
        }

        return null;
    }
}
