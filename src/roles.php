<?php

declare(strict_types=1);

const ROLE_ADMIN = 'admin';
const ROLE_USUARIO = 'usuario';
const ROLE_PUERTA = 'puerta';
const ROLE_CAJERA = 'cajera';
const ROLE_KIOSKITO = 'kioskito';
const ROLE_KIOSKO_LEGACY = 'kiosko';

function roleExists(string $role): bool
{
    return in_array($role, [
        ROLE_ADMIN,
        ROLE_USUARIO,
        ROLE_PUERTA,
        ROLE_CAJERA,
        ROLE_KIOSKITO,
        ROLE_KIOSKO_LEGACY,
    ], true);
}

function canAccess(string $role, string $module): bool
{
    static $permissions = [

        ROLE_ADMIN => [
            'admin',
            'door',
            'kiosko',
            'vip',
            'stock',
            'guardarropas',
        ],

        ROLE_PUERTA => [
            'door',
        ],

        ROLE_USUARIO => [
            'door',
        ],

        ROLE_CAJERA => [
            'kiosko',
            'vip',
            'guardarropas',
        ],

        ROLE_KIOSKITO => [
            'kiosko',
            'vip',
            'guardarropas',
        ],

        ROLE_KIOSKO_LEGACY => [
            'kiosko',
            'vip',
            'guardarropas',
        ],

    ];

    return in_array(
        $module,
        $permissions[$role] ?? [],
        true
    );
}
