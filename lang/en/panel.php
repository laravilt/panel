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
        'optional' => 'optional',
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

    /*
    |--------------------------------------------------------------------------
    | Tenancy
    |--------------------------------------------------------------------------
    */
    'tenancy' => [
        'select_tenant' => 'Select Team',
        'tenants' => 'Teams',
        'tenants_count' => ':count teams',
        'tenant_settings' => 'Team Settings',
        'create_tenant' => 'Create Team',
        'create_tenant_description' => 'Create a new team to get started with your workspace.',
        'switch_tenant' => 'Switch Team',
        'no_tenants' => 'No teams available',
        'register_team' => 'Register a Team',
        'team_profile' => 'Team Profile',
        'team_name' => 'Team Name',
        'team_name_placeholder' => 'Enter team name',
        'team_slug' => 'Team URL (optional)',
        'team_slug_placeholder' => 'my-team',
        'team_slug_help' => 'Leave empty to auto-generate from the team name.',
        'tenant_created' => 'Team created successfully!',
        'team_created' => 'Team created successfully!',

        // Multi-database subdomain tenancy
        'subdomain' => 'Subdomain',
        'subdomain_placeholder' => 'your-team',
        'subdomain_helper' => 'Your team will be accessible at subdomain.:domain',
        'subdomain_invalid' => 'Subdomain must contain only lowercase letters, numbers, and hyphens.',
        'subdomain_reserved' => 'This subdomain is reserved and cannot be used.',
        'subdomain_taken' => 'This subdomain is already in use.',
        'team_description' => 'Description',
        'team_description_placeholder' => 'Describe your team or organization...',
        'what_happens_next' => 'What happens next?',
        'provision_database' => 'A dedicated database will be created for your team',
        'provision_subdomain' => 'Your team will be accessible at your chosen subdomain',
        'provision_redirect' => 'You will be redirected to your new workspace',

        // Team Settings Page
        'settings' => [
            'description' => 'Manage your team settings and members.',
            'team_profile_section' => 'Team Profile',
            'team_profile_description' => 'Update your team\'s profile information and settings.',
            'team_name_section' => 'Team Name',
            'team_name_description' => 'The team\'s name and owner information.',
            'team_members_section' => 'Team Members',
            'team_members_description' => 'All of the people that are part of this team.',
            'invite_member' => 'Invite Member',
            'invite_member_description' => 'Invite a new member to your team by entering their email address.',
            'add_member' => 'Add Team Member',
            'add_member_description' => 'Add a new team member to your team, allowing them to collaborate with you.',
            'email' => 'Email',
            'email_placeholder' => 'Enter email address',
            'role' => 'Role',
            'owner' => 'Owner',
            'remove' => 'Remove',
            'remove_member_title' => 'Remove Team Member',
            'remove_member_description' => 'Are you sure you want to remove :name from the team?',
            'leave_team' => 'Leave Team',
            'leave_team_description' => 'Are you sure you want to leave this team?',
            'danger_zone' => 'Danger Zone',
            'danger_zone_description' => 'Once you delete a team, there is no going back. Please be certain.',
            'delete_team' => 'Delete Team',
            'delete_team_title' => 'Delete Team',
            'delete_team_description' => 'Permanently delete this team and all of its resources.',
            'only_owner_can_edit' => 'Only the team owner can edit this.',
            'team_updated' => 'Team name updated successfully.',
            'user_not_found' => 'No user found with that email address.',
            'already_member' => 'This user is already a member of the team.',
            'member_added' => 'Team member added successfully.',
            'role_updated' => 'Team member role updated successfully.',
            'cannot_remove_others' => 'You do not have permission to remove other members.',
            'cannot_remove_owner' => 'The team owner cannot be removed.',
            'member_removed' => 'Team member removed successfully.',
            'team_deleted' => 'Team deleted successfully.',
            'no_members' => 'No team members yet.',
            'member_invited' => 'Team member invited successfully.',
            'send_email_notification' => 'Send email notification',
            'send_notification_center' => 'Send to notification center',
            'team_avatar' => 'Team Avatar',
            'upload_avatar' => 'Upload',
            'remove_avatar' => 'Remove',
            'avatar_hint' => 'JPG, PNG or GIF. Max 2MB.',
            'team_description' => 'Description',
            'team_description_placeholder' => 'Describe your team...',
            'team_settings' => 'Team Settings',
            'team_settings_description' => 'Configure team-specific settings and preferences.',
            'show_unassigned_records' => 'Show Unassigned Records',
            'show_unassigned_records_description' => 'When enabled, team members can view records that haven\'t been assigned to any team. This allows you to claim and assign orphaned records to your team.',
        ],

        // Team Invitation Notifications
        'notifications' => [
            'invitation_subject' => 'You\'ve been invited to join :team',
            'invitation_greeting' => 'Hello :name!',
            'invitation_line1' => ':inviter has invited you to join the :team team.',
            'invitation_role' => 'You have been assigned the role of :role.',
            'invitation_action' => 'View Team',
            'invitation_footer' => 'If you did not expect this invitation, you can ignore this email.',
            'salutation' => 'Best regards',
            'invitation_title' => 'Team Invitation',
            'invitation_body' => ':inviter invited you to join :team as :role.',
            'view_team' => 'View Team',
            'accept_invitation' => 'Accept Invitation',
            'decline_invitation' => 'Decline',
            'invitation_accepted' => 'Invitation accepted! Welcome to the team.',
            'invitation_declined' => 'Invitation declined.',
            'already_member' => 'You are already a member of this team.',
            'invalid_invitation_link' => 'This invitation link is invalid or has expired.',
            'login_required' => 'Please log in to accept or decline this invitation.',
            'team_not_found' => 'The team you are looking for does not exist.',
        ],

        // Team Roles
        'roles' => [
            'owner' => 'Owner',
            'owner_description' => 'Full control of the team.',
            'admin' => 'Admin',
            'admin_description' => 'Can manage team settings and members.',
            'editor' => 'Editor',
            'editor_description' => 'Can create and edit content.',
            'member' => 'Member',
            'member_description' => 'Can view content and collaborate.',
        ],
    ],
];
