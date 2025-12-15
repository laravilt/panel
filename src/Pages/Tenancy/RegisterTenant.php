<?php

namespace Laravilt\Panel\Pages\Tenancy;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravilt\Actions\Action;
use Laravilt\Forms\Components\FileUpload;
use Laravilt\Forms\Components\Textarea;
use Laravilt\Forms\Components\TextInput;
use Laravilt\Panel\Enums\PageLayout;
use Laravilt\Panel\Facades\Laravilt;
use Laravilt\Panel\Facades\Panel;
use Laravilt\Panel\Pages\Page;
use Laravilt\Schemas\Schema;
use Laravilt\Support\Utilities\Get;
use Laravilt\Support\Utilities\Set;

class RegisterTenant extends Page
{
    protected static ?string $title = null;

    protected static ?string $slug = 'tenant/register';

    protected static ?string $navigationIcon = 'building-2';

    protected static bool $shouldRegisterNavigation = false;

    public static function getTitle(): string
    {
        return __('panel::panel.tenancy.create_tenant');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel::panel.tenancy.create_tenant');
    }

    public function getHeading(): string
    {
        return __('panel::panel.tenancy.create_tenant');
    }

    public function getSubheading(): ?string
    {
        return __('panel::panel.tenancy.create_tenant_description');
    }

    public function getLayout(): string
    {
        return PageLayout::Card->value;
    }

    public static function canAccess(): bool
    {
        $panel = Panel::getCurrent();

        return $panel?->hasTenantRegistration() ?? false;
    }

    /**
     * Get the form components for tenant registration.
     * Override this method to customize the registration form components.
     */
    protected function getFormComponents(): array
    {
        return [
            FileUpload::make('avatar')
                ->label(__('panel::panel.tenancy.settings.team_avatar'))
                ->helperText(__('panel::panel.tenancy.settings.avatar_hint'))
                ->image()
                ->imagePreviewHeight('100')
                ->maxSize(2048),

            TextInput::make('name')
                ->label(__('panel::panel.tenancy.team_name'))
                ->placeholder(__('panel::panel.tenancy.team_name_placeholder'))
                ->live(debounce: 500)
                ->afterStateUpdated(fn (Get $get, Set $set) => $set('slug', Str::slug($get('name') ?? '')))
                ->required()
                ->maxLength(255)
                ->autofocus(),

            TextInput::make('slug')
                ->label(__('panel::panel.tenancy.team_slug'))
                ->placeholder(__('panel::panel.tenancy.team_slug_placeholder'))
                ->helperText(__('panel::panel.tenancy.team_slug_help'))
                ->maxLength(255)
                ->alphaDash(),

            Textarea::make('description')
                ->label(__('panel::panel.tenancy.settings.team_description'))
                ->placeholder(__('panel::panel.tenancy.settings.team_description_placeholder'))
                ->rows(3)
                ->maxLength(1000),
        ];
    }

    /**
     * Configure the form schema.
     * Override this method in custom registration pages.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->schema($this->getFormComponents());
    }

    /**
     * Get the form schema for tenant registration.
     * This wraps form components in a Schema object for reactive fields support.
     *
     * @param  array|null  $formData  Form data passed by ReactiveFieldController
     */
    public function getSchema(?array $formData = null): array
    {
        $form = $this->form(new Schema);

        // Return the form schema - actions are rendered separately via getHeaderActions()
        return [$form];
    }

    /**
     * Get the actions for tenant registration form.
     * Override this method to customize the registration actions.
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('register')
                ->label(__('panel::panel.tenancy.create_tenant'))
                ->color('primary')
                ->submit()
                ->action(function (array $data) {
                    return $this->createTenant($data, request());
                }),
        ];
    }

    /**
     * Get header actions for the page.
     * These are rendered as ActionButtons in the form.
     */
    public function getHeaderActions(): array
    {
        return $this->getFormActions();
    }

    /**
     * Create a new tenant.
     * Override this method to customize tenant creation logic.
     */
    public function createTenant(array $data, ?Request $request = null): mixed
    {
        $panel = Panel::getCurrent();
        $user = request()->user();
        $request = $request ?? request();

        $tenantModel = $panel->getTenantModel();
        $slugAttribute = $panel->getTenantSlugAttribute();

        // Generate slug if not provided
        $slug = ! empty($data['slug']) ? $data['slug'] : Str::slug($data['name']);

        // Ensure slug is unique
        $baseSlug = $slug;
        $counter = 1;
        while ($tenantModel::where($slugAttribute, $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        // Create the tenant
        $tenant = new $tenantModel;
        $tenant->name = $data['name'];
        $tenant->{$slugAttribute} = $slug;

        // Set description if provided and model supports it
        if (isset($data['description']) && $this->tenantHasAttribute($tenant, 'description')) {
            $tenant->description = $data['description'];
        }

        // Set owner if the model has owner_id
        if (property_exists($tenant, 'owner_id') || method_exists($tenant, 'owner')) {
            $tenant->owner_id = $user->id;
        }

        $tenant->save();

        // Handle avatar upload after save (need tenant ID for path)
        if ($request->hasFile('avatar') && $this->tenantHasAttribute($tenant, 'avatar')) {
            $avatarPath = $this->uploadAvatar($request->file('avatar'), $tenant);
            if ($avatarPath) {
                $tenant->avatar = $avatarPath;
                $tenant->save();
            }
        }

        // Attach user to tenant
        $ownershipRelationship = $panel->getTenantOwnershipRelationship();
        $pluralRelationship = Str::plural($ownershipRelationship);

        if (method_exists($user, $pluralRelationship)) {
            $user->{$pluralRelationship}()->attach($tenant->id, ['role' => 'owner']);
        }

        // Set as current team
        if (property_exists($user, 'current_team_id') || isset($user->current_team_id)) {
            $user->current_team_id = $tenant->id;
            $user->save();
        }

        // Set tenant in session
        session()->put('laravilt.tenant_id', $tenant->getKey());
        Laravilt::setTenant($tenant);

        notify(__('panel::panel.tenancy.tenant_created'));

        return redirect('/'.$panel->getPath());
    }

    /**
     * Upload avatar file.
     */
    protected function uploadAvatar($file, $tenant): ?string
    {
        if (! $file) {
            return null;
        }

        $filename = 'teams/'.$tenant->getKey().'/avatar-'.time().'.'.$file->getClientOriginalExtension();

        return $file->storeAs('', $filename, 'public');
    }

    /**
     * Handle POST request to create tenant.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash'],
            'description' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        return $this->createTenant($validated, $request);
    }

    /**
     * Check if tenant model has a specific attribute.
     */
    protected function tenantHasAttribute($tenant, string $attribute): bool
    {
        return in_array($attribute, $tenant->getFillable()) ||
               array_key_exists($attribute, $tenant->getAttributes()) ||
               $tenant->getConnection()->getSchemaBuilder()->hasColumn($tenant->getTable(), $attribute);
    }

    protected function getInertiaProps(): array
    {
        $panel = Panel::getCurrent();

        return [
            'panel' => [
                'id' => $panel->getId(),
                'path' => $panel->getPath(),
                'brandName' => $panel->getBrandName(),
                'brandLogo' => $panel->getBrandLogo(),
            ],
        ];
    }
}
