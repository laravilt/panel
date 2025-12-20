<?php

declare(strict_types=1);

namespace Laravilt\Panel\Pages;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravilt\Tables\Table;

/**
 * ManageRelatedRecords - A page for managing related records of a parent resource.
 *
 * This is used for displaying and managing HasMany/BelongsToMany relationships
 * in a separate page rather than using RelationManagers.
 *
 * Example:
 * - Organization has many Licenses
 * - ListLicenses extends ManageRelatedRecords to manage licenses for an organization
 */
abstract class ManageRelatedRecords extends Page
{
    /**
     * The parent record being viewed.
     */
    protected ?Model $ownerRecord = null;

    /**
     * The relationship name on the parent model.
     */
    protected static string $relationship;

    /**
     * Default view mode: 'table', 'grid', or 'api'
     */
    protected string $defaultView = 'table';

    /**
     * Mount the page with the parent record.
     */
    public function mount($record = null): void
    {
        parent::mount($record);

        // Get the parent record from route parameter
        $resource = static::getResource();
        $parentResource = static::getParentResource();

        if ($parentResource && $record) {
            $this->ownerRecord = $parentResource::resolveRecordRouteBinding($record);
        }
    }

    /**
     * Get the parent resource class.
     * Override this in your page to specify the parent resource.
     */
    public static function getParentResource(): ?string
    {
        return null;
    }

    /**
     * Get the relationship name.
     */
    public static function getRelationship(): string
    {
        return static::$relationship ?? Str::camel(class_basename(static::class));
    }

    /**
     * Get the owner record.
     */
    public function getOwnerRecord(): ?Model
    {
        return $this->ownerRecord;
    }

    /**
     * Get the page title.
     */
    public static function getTitle(): string
    {
        $resource = static::getResource();

        if ($resource) {
            return $resource::getPluralLabel();
        }

        return Str::title(Str::snake(static::getRelationship(), ' '));
    }

    /**
     * Get the page heading.
     */
    public function getHeading(): string
    {
        return static::getTitle();
    }

    /**
     * Get the table query scoped to the relationship.
     */
    public function getTableQuery()
    {
        $ownerRecord = $this->getOwnerRecord();

        if (! $ownerRecord) {
            return null;
        }

        $relationship = static::getRelationship();

        return $ownerRecord->{$relationship}();
    }

    /**
     * Define the table for this page.
     * Override this in your page class to customize the table.
     */
    public function table(Table $table): Table
    {
        $resource = static::getResource();

        if ($resource) {
            return $resource::table($table);
        }

        return $table;
    }

    /**
     * Define header actions for this page.
     */
    protected function headerActions(): array
    {
        return [];
    }

    /**
     * Get all header actions.
     */
    public function getHeaderActions(): array
    {
        return $this->headerActions();
    }

    /**
     * Get extra props for Inertia response.
     */
    protected function getInertiaProps(): array
    {
        return [
            'ownerRecord' => $this->ownerRecord?->toArray(),
            'relationship' => static::getRelationship(),
            'currentView' => $this->defaultView,
        ];
    }

    /**
     * Get the schema (table) for this page.
     */
    public function getSchema(): array
    {
        $resource = static::getResource();
        $table = $this->table(new Table);

        $query = $this->getTableQuery();
        if ($query) {
            $table->query(fn () => $query);
        }

        if ($resource) {
            $table->model($resource::getModel());
            $table->resourceSlug($resource::getSlug());
        }

        return [$table];
    }

    /**
     * Get the URL for this page.
     */
    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null): string
    {
        // For ManageRelatedRecords, we need the parent record ID in the URL
        return parent::getUrl($parameters, $isAbsolute, $panel, $tenant);
    }
}
