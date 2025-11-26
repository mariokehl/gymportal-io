<?php

return [
    'title'    => 'Dashboard',
    'subtitle' => 'Overview of your gyms and members',

    'cards' => [
        'active_members'       => 'Active members',
        'new_members'          => 'New members (last 30 days)',
        'revenue_month'        => 'Revenue this month',
        'expiring_contracts'   => 'Contracts expiring soon',
        'change_vs_last_month' => ':change compared to last month',
    ],

    'sections' => [
        'recent_members' => 'Recently added members',
        'notifications'  => 'Notifications',
    ],

    'actions' => [
        'new_contract'           => 'New contract',
        'show'                   => 'Show',
        'edit'                   => 'Edit',
        'view_all_notifications' => 'View all',
    ],

    'search' => [
        'member_placeholder' => 'Search member...',
    ],

    'table' => [
        'headers' => [
            'name'        => 'Name',
            'membership'  => 'Membership',
            'status'      => 'Status',
            'last_visit'  => 'Last visit',
            'actions'     => 'Actions',
        ],
        'never_visited' => 'Never',
        'pagination'    => 'Showing 1-:count of :total members',
    ],

    'notifications' => [
        'empty' => 'No notifications available.',
    ],
];

