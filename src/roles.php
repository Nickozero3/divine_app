<?php

declare(strict_types=1);

const ROLE_ADMIN = 'admin';
const ROLE_USUARIO = 'usuario';
const ROLE_PUERTA = 'puerta';
const ROLE_KIOSKO = 'kiosko';

function roleExists(string $role): bool
{
    return in_array($role, [
        ROLE_ADMIN,
        ROLE_USUARIO,
        ROLE_PUERTA,
        ROLE_KIOSKO,
    ], true);
}

function canAccess(string $role, string $module): bool
{
    static $permissions = [

        ROLE_ADMIN => [
            'admin',
            'door',
            'scanner',
            'kiosko',
            'stock',
            'guardarropas',
        ],

        ROLE_PUERTA => [
            'door',
            'scanner',
        ],

        ROLE_USUARIO => [
            'door',
        ],

        ROLE_KIOSKO => [
            'kiosko',
            'guardarropas',
        ],

    ];

    return in_array(
        $module,
        $permissions[$role] ?? [],
        true
    );
}