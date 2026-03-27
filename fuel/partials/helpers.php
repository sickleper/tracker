<?php

function renderFuelVehicleOptions(array $response, bool $withAllOption = false, string $allLabel = 'All Vehicles'): void
{
    if ($withAllOption) {
        echo '<option value="" class="text-gray-900">' . htmlspecialchars($allLabel) . '</option>';
    }

    if (!($response['success'] ?? false) || empty($response['vehicles'])) {
        return;
    }

    foreach ($response['vehicles'] as $vehicle) {
        echo "<option value='" . htmlspecialchars($vehicle['vehicle_id']) . "' class='text-gray-900'>" . htmlspecialchars($vehicle['license_plate']) . '</option>';
    }
}

function renderFuelDriverOptions(array $response, bool $includePlaceholder = true, string $placeholder = 'Select Driver'): void
{
    if ($includePlaceholder) {
        echo '<option value="">' . htmlspecialchars($placeholder) . '</option>';
    }

    if (!($response['success'] ?? false) || empty($response['users'])) {
        return;
    }

    foreach ($response['users'] as $user) {
        echo "<option value='" . htmlspecialchars($user['id']) . "'>" . htmlspecialchars($user['name']) . '</option>';
    }
}
