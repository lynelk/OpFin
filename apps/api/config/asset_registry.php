<?php

return [
    // Keyed HMAC secret for asset identifiers, so an IMEI or VIN cannot be recovered from its stored
    // hash by enumeration. Deliberately separate from APP_KEY: rotating it requires re-hashing every
    // identifier. The registry refuses requests (HTTP 503) until this is configured.
    'identifier_key' => env('OPFIN_ASSET_IDENTIFIER_KEY'),

    // Shared passport lifecycle with class-specific identity evidence (CAP-002, DEV-001, AUTO-001, AST-001).
    // A passport needs at least one identifier from required_any.
    'classes' => [
        'phone' => ['family' => 'device', 'required_any' => ['imei']],
        'tablet' => ['family' => 'device', 'required_any' => ['imei', 'serial']],
        'laptop' => ['family' => 'device', 'required_any' => ['serial']],
        'car' => ['family' => 'vehicle', 'required_any' => ['vin', 'chassis_number']],
        'motorcycle' => ['family' => 'vehicle', 'required_any' => ['vin', 'chassis_number']],
        'electric_vehicle' => ['family' => 'vehicle', 'required_any' => ['vin', 'chassis_number']],
        'commercial_vehicle' => ['family' => 'vehicle', 'required_any' => ['vin', 'chassis_number']],
        'solar_system' => ['family' => 'productive', 'required_any' => ['serial']],
        'machinery' => ['family' => 'productive', 'required_any' => ['serial']],
        'agricultural_equipment' => ['family' => 'productive', 'required_any' => ['serial']],
    ],

    'identifier_types' => ['imei', 'serial', 'vin', 'chassis_number', 'engine_number', 'registration_plate'],

    'verification_methods' => ['physical_inspection', 'dealer_invoice', 'oem_record', 'registry_extract'],
];
