<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Common
    |--------------------------------------------------------------------------
    */
    'common' => [
        'save' => 'Save',
        'save_changes' => 'Save Changes',
        'saving' => 'Saving...',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'create' => 'Create',
        'create_and_create_another' => 'Create & Create Another',
        'update' => 'Update',
        'search' => 'Search',
        'filter' => 'Filter',
        'reset' => 'Reset',
        'close' => 'Close',
        'confirm' => 'Confirm',
        'yes' => 'Yes',
        'no' => 'No',
        'loading' => 'Loading...',
        'no_results' => 'No results found',
        'actions' => 'Actions',
        'view' => 'View',
        'back' => 'Back',
        'next' => 'Next',
        'previous' => 'Previous',
        'submit' => 'Submit',
        'logout' => 'Logout',
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'dashboard' => 'Dashboard',
        'home' => 'Home',
        'settings' => 'Settings',
        'users' => 'Users',
        'profile' => 'Profile',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */
    'resources' => [
        'create' => 'Create :label',
        'edit' => 'Edit :label',
        'view' => 'View :label',
        'list' => ':label',
        'delete' => 'Delete :label',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */
    'pages' => [
        'dashboard' => [
            'title' => 'Dashboard',
            'welcome' => 'Welcome back!',
        ],
        'create_record' => [
            'title' => 'Create :label',
        ],
        'edit_record' => [
            'title' => 'Edit :label',
        ],
        'view_record' => [
            'title' => 'View :label',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Breadcrumbs
    |--------------------------------------------------------------------------
    */
    'breadcrumbs' => [
        'home' => 'Home',
        'create' => 'Create',
        'edit' => 'Edit',
        'view' => 'View',
    ],

    /*
    |--------------------------------------------------------------------------
    | User Menu
    |--------------------------------------------------------------------------
    */
    'user_menu' => [
        'profile' => 'Profile',
        'settings' => 'Settings',
        'logout' => 'Logout',
    ],

    /*
    |--------------------------------------------------------------------------
    | Footer
    |--------------------------------------------------------------------------
    */
    'footer' => [
        'powered_by' => 'Powered by Laravilt',
        'all_rights_reserved' => 'All rights reserved',
    ],

    /*
    |--------------------------------------------------------------------------
    | Empty States
    |--------------------------------------------------------------------------
    */
    'empty' => [
        'title' => 'No records found',
        'description' => 'Get started by creating a new record.',
        'action' => 'Create New',
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */
    'search' => [
        'placeholder' => 'Search...',
        'global_placeholder' => 'Search anything...',
        'no_results' => 'No results found',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'title' => 'Dashboard',
        'total_revenue' => 'Total Revenue',
        'customers' => 'Customers',
        'sales' => 'Sales',
        'active_now' => 'Active Now',
        'from_last_month' => ':change from last month',
        'since_last_hour' => ':count since last hour',
        'recent_activity' => 'Recent Activity',
        'new_customer' => 'New customer registered',
        'customer_joined' => ':name joined the platform',
        'new_order' => 'New order placed',
        'order_info' => 'Order #:id - :amount',
        'payment_received' => 'Payment received',
        'payment_info' => 'Payment of :amount confirmed',
        'time_ago' => ':time ago',
        'minutes_ago' => ':count minutes ago',
        'hour_ago' => '1 hour ago',
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo Pages
    |--------------------------------------------------------------------------
    */
    'demo' => [
        // Form Demo
        'form' => [
            'title' => 'Form Demo',
            'description' => 'A comprehensive showcase of all available form components and layouts.',
            'submitted' => 'Form Submitted',
            'submitted_message' => 'Form data has been logged to the console.',
            'reset' => 'Form Reset',
            'reset_message' => 'All fields have been reset to their default values.',
            'text_inputs' => 'Text Inputs',
            'text_inputs_desc' => 'Text, Email, URL, Phone, Textarea',
            'selection' => 'Selection',
            'selection_desc' => 'Select, Radio, Checkbox, Tags',
            'date_time' => 'Date & Time',
            'date_time_desc' => 'Date, DateTime, Time pickers',
            'rich_content' => 'Rich Content',
            'rich_content_desc' => 'Rich Editor, Markdown, Files',
            'current_form_data' => 'Current Form Data (Live Preview)',
            'updates_realtime' => 'Updates in real-time as you type',
            'code_example' => 'Code Example',
        ],

        // Table Demo
        'table' => [
            'title' => 'Table Demo',
            'description' => 'A showcase of the Table component with columns, filters, sorting, and inline actions.',
            'column_types' => 'Multiple Column Types',
            'column_types_desc' => 'Text, Image, Icon, Toggle',
            'sortable' => 'Sortable Columns',
            'sortable_desc' => 'Click headers to sort',
            'filters' => 'Advanced Filters',
            'filters_desc' => 'Select, Multi, Ternary',
            'bulk_actions' => 'Bulk Actions',
            'bulk_actions_desc' => 'Select & act on multiple',
            'toggleable' => 'Toggleable Columns',
            'toggleable_desc' => 'Show/hide columns',
        ],

        // Infolist Demo
        'infolist' => [
            'title' => 'InfoList Demo',
            'description' => 'A showcase of the InfoList component for displaying read-only data in a structured format.',
            'text_entry' => 'Text Entry',
            'text_entry_desc' => 'Display text values',
            'badge_entry' => 'Badge Entry',
            'badge_entry_desc' => 'Status indicators',
            'image_entry' => 'Image Entry',
            'image_entry_desc' => 'Display images',
            'color_entry' => 'Color Entry',
            'color_entry_desc' => 'Color swatches',
            'code_entry' => 'Code Entry',
            'code_entry_desc' => 'Code snippets',
        ],

        // Actions Demo
        'actions' => [
            'title' => 'Actions Demo',
            'description' => 'A comprehensive showcase of all action types, variants, and configurations available in the Laravilt panel.',
            'closure_actions' => 'Closure Actions',
            'closure_actions_desc' => 'Execute backend code',
            'confirmations' => 'Confirmations',
            'confirmations_desc' => 'Modal dialogs',
            'modal_forms' => 'Modal Forms',
            'modal_forms_desc' => 'Inline form inputs',
            'url_actions' => 'URL Actions',
            'url_actions_desc' => 'Navigation links',
            'code_examples' => 'Code Examples',
        ],

        // Notifications Demo
        'notifications' => [
            'title' => 'Notifications Demo',
            'description' => 'A showcase of the notification system with different types, durations, and configurations.',
            'quick_notifications' => 'Quick Notifications (Client-Side)',
            'quick_notifications_desc' => 'Instant client-side notifications without server roundtrip.',
            'success' => 'Success',
            'success_title' => 'Success!',
            'success_body' => 'Your action completed successfully.',
            'error' => 'Error',
            'error_title' => 'Error!',
            'error_body' => 'Something went wrong. Please try again.',
            'warning' => 'Warning',
            'warning_title' => 'Warning!',
            'warning_body' => 'Please review this action before proceeding.',
            'info' => 'Info',
            'info_title' => 'Information',
            'info_body' => 'Here is some useful information for you.',
            'duration_control' => 'Duration Control',
            'duration_control_desc' => 'Notifications with different display durations.',
            'quick_2s' => 'Quick (2s)',
            'quick_2s_body' => 'This disappears quickly!',
            'normal_5s' => 'Normal (5s)',
            'normal_5s_body' => 'This is the default duration.',
            'long_10s' => 'Long (10s)',
            'long_10s_body' => 'This stays visible for 10 seconds.',
            'persistent' => 'Persistent',
            'persistent_body' => 'Click X to dismiss this notification.',
            '2_seconds' => '2 Seconds',
            '5_seconds' => '5 Seconds',
            '10_seconds' => '10 Seconds',
            'code_examples' => 'Code Examples',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sidebar
    |--------------------------------------------------------------------------
    */
    'sidebar' => [
        'toggle' => 'Toggle Sidebar',
        'collapse' => 'Collapse',
        'expand' => 'Expand',
    ],

    /*
    |--------------------------------------------------------------------------
    | Appearance
    |--------------------------------------------------------------------------
    */
    'appearance' => [
        'title' => 'Appearance',
        'light' => 'Light',
        'dark' => 'Dark',
        'system' => 'System',
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'title' => 'Settings',
        'profile' => 'Profile',
        'profile_description' => 'Manage your profile settings',
        'appearance' => 'Appearance',
        'appearance_description' => 'Customize the appearance of the app',
        'appearance_light' => 'Light',
        'appearance_dark' => 'Dark',
        'appearance_system' => 'System',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'title' => 'Notifications',
        'no_notifications' => 'No notifications yet',
        'unread_count' => ':count unread',
        'mark_all_read' => 'Mark all as read',
        'mark_as_read' => 'Mark as read',
        'delete' => 'Delete',
        'delete_all' => 'Delete all',
    ],

    /*
    |--------------------------------------------------------------------------
    | Language
    |--------------------------------------------------------------------------
    */
    'language' => [
        'switch' => 'Switch Language',
        'current' => 'Current Language',
    ],
];
