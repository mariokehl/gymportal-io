<?php

return [
    'page_title' => 'Members',

    'status' => [
        'active'    => 'Active',
        'pending'   => 'Pending',
        'cancelled' => 'Cancelled',
        'paused'    => 'Paused',
        'expired'   => 'Expired',
        'inactive'  => 'Inactive',
        'overdue'   => 'Overdue',
    ],

    'filters' => [
        'all_status' => 'All statuses',
        'active'     => 'Active',
        'inactive'   => 'Inactive',
        'paused'     => 'Paused',
        'pending'    => 'Pending',
        'overdue'    => 'Overdue',
    ],

    'table' => [
        'recent_members_title' => 'Recent members',
        'headers' => [
            'name'          => 'Name',
            'member_number' => 'Member number',
            'membership'    => 'Membership',
            'status'        => 'Status',
            'last_visit'    => 'Last visit',
            'contract_end'  => 'Contract end',
            'actions'       => 'Actions',
        ],
        'empty'               => 'No members registered.',
        'never_checked_in'    => 'Never',
        'no_members_title'    => 'No members found',
        'no_members_filtered' => 'No members match the current filters.',
        'no_members_default'  => 'Start by adding your first member.',
    ],

    'search' => [
        'member_placeholder' => 'Search members...',
    ],

    'actions' => [
        'new_member'   => 'New member',
        'new_contract' => 'New contract',
        'show'         => 'View',
        'edit'         => 'Edit',
        'delete'       => 'Delete',
    ],

    'delete_tooltip' => [
        'title' => 'Cannot delete',
        'hint'  => 'Tip: member must be inactive first',
    ],

    'delete_modal' => [
        'title'                => 'Delete member',
        'status_inactive'      => 'Status: Inactive',
        'no_active_memberships'=> 'No active memberships',
        'no_open_payments'     => 'No open payments',
        'warning_text'         => 'This action cannot be undone. All member data will be permanently deleted.',
        'confirm_button'       => 'Delete permanently',
        'deleting'             => 'Deleting...',
        'cancel_button'        => 'Cancel',
    ],

    'pagination' => [
        'item_label' => 'members',
    ],
];
