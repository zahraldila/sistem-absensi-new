<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Organization Terminology
    |--------------------------------------------------------------------------
    |
    | Baseline terminology used when an organization does not have custom
    | terminology configured. Category is DATA and terminology is resolved
    | dynamically via OrganizationHelper::term().
    |
    */
    'defaults' => [
        'member'            => 'Anggota',
        'member_id'         => 'ID Anggota',
        'member_management' => 'Data Anggota',
        'division'          => 'Divisi',
        'position'          => 'Jabatan',
        'location'          => 'Lokasi',
    ],
];
