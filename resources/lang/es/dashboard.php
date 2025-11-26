<?php

return [
    'title'    => 'Panel',
    'subtitle' => 'Visión general de tus gimnasios y miembros',

    'cards' => [
        'active_members'       => 'Miembros activos',
        'new_members'          => 'Nuevos miembros (últimos 30 días)',
        'revenue_month'        => 'Ingresos este mes',
        'expiring_contracts'   => 'Contratos por vencer',
        'change_vs_last_month' => ':change respecto al mes anterior',
    ],

    'sections' => [
        'recent_members' => 'Miembros recientes',
        'notifications'  => 'Notificaciones',
    ],

    'actions' => [
        'new_contract'           => 'Nuevo contrato',
        'show'                   => 'Ver',
        'edit'                   => 'Editar',
        'view_all_notifications' => 'Ver todas',
    ],

    'search' => [
        'member_placeholder' => 'Buscar miembro...',
    ],

    'table' => [
        'headers' => [
            'name'        => 'Nombre',
            'membership'  => 'Membresía',
            'status'      => 'Estado',
            'last_visit'  => 'Última visita',
            'actions'     => 'Acciones',
        ],
        'never_visited' => 'Nunca',
        'pagination'    => 'Mostrando 1-:count de :total miembros',
    ],

    'notifications' => [
        'empty' => 'No hay notificaciones.',
    ],
];

