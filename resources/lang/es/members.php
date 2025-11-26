<?php

return [
    'page_title' => 'Miembros',

    'status' => [
        'active'    => 'Activo',
        'pending'   => 'Pendiente',
        'cancelled' => 'Cancelado',
        'paused'    => 'Pausado',
        'expired'   => 'Vencido',
        'inactive'  => 'Inactivo',
        'overdue'   => 'En mora',
    ],

    'filters' => [
        'all_status' => 'Todos los estados',
        'active'     => 'Activo',
        'inactive'   => 'Inactivo',
        'paused'     => 'Pausado',
        'pending'    => 'Pendiente',
        'overdue'    => 'En mora',
    ],

    'table' => [
        'recent_members_title' => 'Miembros recientes',
        'headers' => [
            'name'          => 'Nombre',
            'member_number' => 'Número de miembro',
            'membership'    => 'Membresía',
            'status'        => 'Estado',
            'last_visit'    => 'Última visita',
            'contract_end'  => 'Fin de contrato',
            'actions'       => 'Acciones',
        ],
        'empty'               => 'No hay miembros registrados.',
        'never_checked_in'    => 'Nunca',
        'no_members_title'    => 'No se encontraron miembros',
        'no_members_filtered' => 'Ningún miembro coincide con los filtros actuales.',
        'no_members_default'  => 'Comienza agregando tu primer miembro.',
    ],

    'search' => [
        'member_placeholder' => 'Buscar miembro...',
    ],

    'actions' => [
        'new_member'   => 'Nuevo miembro',
        'new_contract' => 'Nuevo contrato',
        'show'         => 'Ver',
        'edit'         => 'Editar',
        'delete'       => 'Eliminar',
    ],

    'delete_tooltip' => [
        'title' => 'No es posible eliminar',
        'hint'  => 'Consejo: el miembro debe estar inactivo primero',
    ],

    'delete_modal' => [
        'title'                => 'Eliminar miembro',
        'status_inactive'      => 'Estado: Inactivo',
        'no_active_memberships'=> 'Sin membresías activas',
        'no_open_payments'     => 'Sin pagos pendientes',
        'warning_text'         => 'Esta acción no se puede deshacer. Todos los datos del miembro se eliminarán de forma permanente.',
        'confirm_button'       => 'Eliminar definitivamente',
        'deleting'             => 'Eliminando...',
        'cancel_button'        => 'Cancelar',
    ],

    'pagination' => [
        'item_label' => 'miembros',
    ],
];
