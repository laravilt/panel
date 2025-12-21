<?php

declare(strict_types=1);

namespace Laravilt\Panel\Pages;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravilt\Actions\Action;
use Laravilt\Actions\DeleteBulkAction;
use Laravilt\Infolists\Infolist;
use Laravilt\Schemas\Schema;
use Laravilt\Tables\Table;

/**
 * ManageRecords page for simple resources.
 * Handles all CRUD operations (list, create, edit, view, delete) on a single page using modals.
 * Similar to FilamentPHP v4's simple resource pattern.
 */
abstract class ManageRecords extends ListRecords
{
    /**
     * Whether to show the view action in modal.
     */
    protected bool $canView = true;

    /**
     * Whether to show the create action.
     */
    protected bool $canCreate = true;

    /**
     * Whether to show the edit action in modal.
     */
    protected bool $canEdit = true;

    /**
     * Whether to show the delete action.
     */
    protected bool $canDelete = true;

    /**
     * Configure the form schema for create/edit modals.
     */
    public function form(Schema $schema): Schema
    {
        $resource = static::getResource();

        return $resource::form($schema);
    }

    /**
     * Configure the infolist schema for view modals.
     */
    public function infolist(Infolist $infolist): Infolist
    {
        $resource = static::getResource();

        return $resource::infolist($infolist);
    }

    /**
     * Get the resource label (singular).
     */
    public function getResourceLabel(): string
    {
        $resource = static::getResource();

        return $resource::getLabel();
    }

    /**
     * Get the resource plural label.
     */
    public function getResourcePluralLabel(): string
    {
        $resource = static::getResource();

        return $resource::getPluralLabel();
    }

    /**
     * Check if records can be viewed.
     */
    public function canView(): bool
    {
        return $this->canView;
    }

    /**
     * Check if records can be created.
     */
    public function canCreate(): bool
    {
        return $this->canCreate;
    }

    /**
     * Check if records can be edited.
     */
    public function canEdit(): bool
    {
        return $this->canEdit;
    }

    /**
     * Check if records can be deleted.
     */
    public function canDelete(): bool
    {
        return $this->canDelete;
    }

    /**
     * Define header actions for this page.
     * Override this method in your page class to add actions.
     *
     * Example in ManageUser:
     *   protected function headerActions(): array
     *   {
     *       return [
     *           $this->getCreateAction(),
     *       ];
     *   }
     *
     * @return array<Action>
     */
    protected function headerActions(): array
    {
        return [];
    }

    /**
     * Get all header actions for this page.
     */
    public function getHeaderActions(): array
    {
        return $this->headerActions();
    }

    /**
     * Get the create action configured for this resource.
     * Use CreateAction::make() in your headerActions() and customize as needed.
     */
    protected function getCreateAction(): \Laravilt\Actions\CreateAction
    {
        $resource = static::getResource();
        $modelClass = $resource::getModel();
        $formSchema = $this->form(Schema::make()->model($modelClass))->getSchema();
        $slug = $resource::getSlug();
        $page = $this;

        return \Laravilt\Actions\CreateAction::make()
            ->stableId("{$slug}_create")
            ->label(__('actions::actions.buttons.create').' '.$this->getResourceLabel())
            ->modalHeading(__('actions::actions.buttons.create').' '.$this->getResourceLabel())
            ->model($modelClass)
            ->formSchema($formSchema)
            ->component(static::class) // For reactive fields in modal forms
            ->action(function (array $data) use ($modelClass, $page, $resource) {
                // Apply mutation hook
                $data = $page->mutateFormDataBeforeCreate($data);

                // Extract many-to-many relationship data before filling
                $relationships = $page->extractRelationshipData($data, $modelClass);

                // Create the record
                $record = new $modelClass;
                $record->fill($data);

                // Associate record with current tenant if applicable
                $resource::associateRecordWithTenant($record);

                $record->save();

                // Sync many-to-many relationships from form data
                $page->syncRelationships($record, $relationships);

                // Associate record with tenant via many-to-many if applicable
                $resource::associateRecordWithTenantManyToMany($record);

                \Laravilt\Notifications\Notification::success()
                    ->title(__('notifications::notifications.success'))
                    ->body(__('notifications::notifications.record_created'))
                    ->send();

                return $record;
            });
    }

    /**
     * Mutate form data before creating a record.
     * Override this method to add custom data transformations.
     */
    public function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }

    /**
     * Mutate form data before saving/updating a record.
     * Override this method to add custom data transformations.
     */
    public function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    /**
     * Extract many-to-many relationship data from form data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractRelationshipData(array &$data, string $modelClass): array
    {
        $modelInstance = new $modelClass;
        $relationships = [];

        foreach ($data as $key => $value) {
            // Check if this key corresponds to a relationship method
            if (method_exists($modelInstance, $key)) {
                try {
                    $relation = $modelInstance->{$key}();

                    // Check if it's a BelongsToMany relationship
                    if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                        $relationships[$key] = $value;
                        unset($data[$key]);
                    }
                } catch (\Throwable $e) {
                    // Not a relationship method, skip
                    continue;
                }
            }
        }

        return $relationships;
    }

    /**
     * Sync many-to-many relationships.
     *
     * @param  array<string, mixed>  $relationships
     */
    protected function syncRelationships(\Illuminate\Database\Eloquent\Model $record, array $relationships): void
    {
        foreach ($relationships as $relationName => $relationData) {
            if ($relationData !== null) {
                $record->{$relationName}()->sync($relationData);
            }
        }
    }

    /**
     * Override the table configuration to add modal-based actions.
     */
    protected function configureTableForModalCrud(Table $table): Table
    {
        $resource = static::getResource();
        $panelId = $this->getPanel()->getId();
        $slug = $resource::getSlug();

        // Get form and infolist schemas for modals
        $modelClass = $resource::getModel();
        $formSchema = $this->form(Schema::make()->model($modelClass))->getSchema();
        $infolistSchema = $this->infolist(Infolist::make())->toInertiaProps();

        // Build modal-based record actions
        $recordActions = [];

        if ($this->canView()) {
            $recordActions[] = Action::make('view')
                ->stableId("{$slug}_view")
                ->label(__('actions::actions.buttons.view'))
                ->icon('Eye')
                ->color('secondary')
                ->tooltip(__('actions::actions.tooltips.view'))
                ->modal(true)
                ->modalHeading(__('actions::actions.buttons.view').' '.$this->getResourceLabel())
                ->modalInfolistSchema($infolistSchema['schema'] ?? [])
                ->modalSubmitActionLabel(null)
                ->modalCancelActionLabel(__('actions::actions.buttons.close'))
                ->modalWidth('lg')
                ->isViewOnly(true)
                ->fillForm(function ($record) {
                    // Fill infolist with record data for viewing
                    if (is_object($record) && method_exists($record, 'toArray')) {
                        return $record->toArray();
                    }
                    if (is_array($record)) {
                        return $record;
                    }

                    return [];
                });
        }

        if ($this->canEdit()) {
            $page = $this;
            $recordActions[] = Action::make('edit')
                ->stableId("{$slug}_edit")
                ->label(__('actions::actions.buttons.edit'))
                ->icon('Pencil')
                ->color('warning')
                ->tooltip(__('actions::actions.tooltips.edit'))
                ->modal(true)
                ->modalHeading(__('actions::actions.buttons.edit').' '.$this->getResourceLabel())
                ->modalFormSchema($formSchema)
                ->modalSubmitActionLabel(__('actions::actions.buttons.save'))
                ->modalCancelActionLabel(__('actions::actions.buttons.cancel'))
                ->modalWidth('lg')
                ->component(static::class) // For reactive fields in modal forms
                ->fillForm(function ($record) {
                    // Fill form with record data for editing
                    if (is_object($record) && method_exists($record, 'toArray')) {
                        return $record->toArray();
                    }
                    if (is_array($record)) {
                        return $record;
                    }

                    return [];
                })
                ->action(function ($record, array $data) use ($modelClass, $page) {
                    // Get record ID - handle both object and array formats
                    $recordId = null;
                    if (is_object($record) && isset($record->id)) {
                        $recordId = $record->id;
                    } elseif (is_array($record) && isset($record['id'])) {
                        $recordId = $record['id'];
                    }

                    if (! $recordId) {
                        throw new \Exception(__('actions::actions.errors.no_record_id'));
                    }

                    // Apply mutation hook
                    $data = $page->mutateFormDataBeforeSave($data);

                    // Extract many-to-many relationship data before filling
                    $relationships = $page->extractRelationshipData($data, $modelClass);

                    $existingRecord = $modelClass::findOrFail($recordId);
                    $existingRecord->fill($data);
                    $existingRecord->save();

                    // Sync many-to-many relationships from form data
                    $page->syncRelationships($existingRecord, $relationships);

                    \Laravilt\Notifications\Notification::success()
                        ->title(__('notifications::notifications.success'))
                        ->body(__('actions::actions.messages.saved'))
                        ->send();

                    return $existingRecord;
                });
        }

        if ($this->canDelete()) {
            $recordActions[] = Action::make('delete')
                ->stableId("{$slug}_delete")
                ->label(__('actions::actions.buttons.delete'))
                ->icon('Trash2')
                ->color('destructive')
                ->tooltip(__('actions::actions.tooltips.delete'))
                ->requiresConfirmation(true)
                ->modalHeading(__('actions::actions.buttons.delete').' '.$this->getResourceLabel())
                ->modalDescription(__('actions::actions.confirm_delete_description'))
                ->modalSubmitActionLabel(__('actions::actions.buttons.confirm'))
                ->modalCancelActionLabel(__('actions::actions.buttons.cancel'))
                ->action(function ($record) use ($modelClass) {
                    // Get record ID - handle both object and array formats
                    $recordId = null;
                    if (is_object($record) && isset($record->id)) {
                        $recordId = $record->id;
                    } elseif (is_array($record) && isset($record['id'])) {
                        $recordId = $record['id'];
                    }

                    if (! $recordId) {
                        throw new \Exception(__('actions::actions.errors.no_record_id'));
                    }

                    $existingRecord = $modelClass::findOrFail($recordId);
                    $existingRecord->delete();

                    \Laravilt\Notifications\Notification::success()
                        ->title(__('notifications::notifications.success'))
                        ->body(__('notifications::notifications.record_deleted'))
                        ->send();
                });
        }

        // Build bulk actions
        $bulkActions = [];
        if ($this->canDelete()) {
            $bulkActions[] = DeleteBulkAction::make();
        }

        // Apply to table
        $table->recordActions($recordActions);
        $table->bulkActions($bulkActions);

        // Set modal CRUD context
        $table->setOption('isManageRecords', true);
        $table->setOption('modalFormSchema', $formSchema);
        $table->setOption('modalInfolistSchema', $infolistSchema['schema'] ?? []);
        $table->setOption('canView', $this->canView());
        $table->setOption('canCreate', $this->canCreate());
        $table->setOption('canEdit', $this->canEdit());
        $table->setOption('canDelete', $this->canDelete());
        $table->setOption('label', $this->getResourceLabel());
        $table->setOption('pluralLabel', $this->getResourcePluralLabel());

        return $table;
    }

    /**
     * Get the schema (table) for this page.
     */
    public function getSchema(): array
    {
        $resource = static::getResource();

        // Configure the table
        $table = new Table;
        $table = $resource::table($table);
        $table->query(fn () => $this->getTableQuery());
        $table->model($resource::getModel());
        $table->resourceSlug($resource::getSlug());

        // Add modal CRUD configuration
        $table = $this->configureTableForModalCrud($table);

        // Return the Table object - Page::render() will call toInertiaProps()
        return [$table];
    }

    /**
     * Get the form schema for reactive field updates.
     * This is called by ReactiveFieldController for modal form live updates.
     *
     * @param  array  $formData  Current form data
     */
    public function getFormSchema(array $formData = []): \Laravilt\Schemas\Schema
    {
        $resource = static::getResource();
        $modelClass = $resource::getModel();

        return $this->form(Schema::make()->model($modelClass));
    }

    /**
     * Get additional Inertia props for ManageRecords page.
     * Merges with parent props to maintain standard page behavior.
     */
    protected function getInertiaProps(): array
    {
        // Get parent props (view toggle, grid option, etc.)
        return parent::getInertiaProps();
    }

    /**
     * Get paginated records for AJAX request.
     */
    public function index(Request $request): JsonResponse
    {
        $resource = static::getResource();

        // Configure the table with modal CRUD actions
        $table = new Table;
        $table = $resource::table($table);
        $table->query(fn () => $this->getTableQuery());
        $table->model($resource::getModel());
        $table->resourceSlug($resource::getSlug());
        $table = $this->configureTableForModalCrud($table);

        // Get the records using the table's toInertiaProps which handles pagination
        $tableProps = $table->toInertiaProps();

        return response()->json([
            'data' => $tableProps['records'] ?? [],
            'pagination' => $tableProps['pagination'] ?? [
                'total' => 0,
                'per_page' => 12,
                'current_page' => 1,
                'last_page' => 1,
                'from' => 0,
                'to' => 0,
            ],
        ]);
    }

    /**
     * Store a new record (create via modal).
     */
    public function store(Request $request): JsonResponse
    {
        $resource = static::getResource();
        $modelClass = $resource::getModel();

        try {
            $data = $request->all();
            $record = new $modelClass;
            $record->fill($data);
            $record->save();

            return response()->json([
                'success' => true,
                'message' => __('actions::actions.messages.created'),
                'record' => $record,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Show a single record (for view/edit modal via AJAX).
     */
    public function show(Request $request, $id): JsonResponse
    {
        $resource = static::getResource();
        $modelClass = $resource::getModel();

        try {
            $record = $modelClass::findOrFail($id);

            // Load relationship data for the form
            $recordData = $record->toArray();
            $recordData = $this->loadRelationshipDataForRecord($record, $recordData);

            return response()->json([
                'success' => true,
                'record' => $recordData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Load relationship data (IDs) for BelongsToMany relationships.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function loadRelationshipDataForRecord(\Illuminate\Database\Eloquent\Model $record, array $data): array
    {
        $reflectionClass = new \ReflectionClass($record);
        $modelClass = $record::class;

        // List of methods to skip (Eloquent methods that should not be called)
        $skipMethods = [
            'delete', 'forceDelete', 'restore', 'save', 'update', 'fresh', 'refresh',
            'push', 'touch', 'replicate', 'toArray', 'toJson', 'jsonSerialize',
            'getKey', 'getTable', 'getConnection', 'newQuery', 'newQueryWithoutScopes',
            'forceDeleteQuietly', 'deleteQuietly', 'restoreQuietly',
        ];

        // Known relationship methods from traits (e.g., Spatie's HasRoles)
        $knownRelationshipMethods = ['permissions', 'roles'];

        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();
            $declaringClass = $method->getDeclaringClass()->getName();

            if (str_starts_with($methodName, '__')
                || $method->getNumberOfParameters() > 0
                || in_array($methodName, $skipMethods)) {
                continue;
            }

            // Allow methods declared on model OR known relationship methods from traits
            $isModelMethod = $declaringClass === $modelClass;
            $isKnownRelationship = in_array($methodName, $knownRelationshipMethods);

            if (! $isModelMethod && ! $isKnownRelationship) {
                continue;
            }

            try {
                $relation = $record->{$methodName}();

                // Check if it's a BelongsToMany relationship - load IDs
                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                    $data[$methodName] = $record->{$methodName}()->pluck($relation->getRelated()->getTable().'.id')->toArray();
                }
                // Check if it's a HasMany relationship - load full records (for Repeaters)
                elseif ($relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany) {
                    $data[$methodName] = $record->{$methodName}()->get()->toArray();
                }
            } catch (\Throwable $e) {
                // Not a relationship method, skip
                continue;
            }
        }

        return $data;
    }

    /**
     * Show a single record via Inertia (direct URL access).
     * This renders the page with the record pre-loaded for viewing/editing.
     */
    public function showRecord(Request $request, $id)
    {
        $resource = static::getResource();
        $modelClass = $resource::getModel();
        $slug = $resource::getSlug();

        try {
            $record = $modelClass::findOrFail($id);

            // Boot and mount the page
            $this->boot();
            $this->mount();

            // Get standard page props
            $props = $this->getPageProps();

            // Load relationship data for the form
            $recordData = $record->toArray();
            $recordData = $this->loadRelationshipDataForRecord($record, $recordData);

            // Add the selected record to props for auto-opening modal
            $props['selectedRecord'] = $recordData;
            $props['selectedRecordId'] = $id;
            $props['autoOpenModal'] = 'view'; // or 'edit' based on user preference

            return \Inertia\Inertia::render($this->getView(), $props);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Record not found - redirect to index with error notification
            \Laravilt\Notifications\Notification::danger()
                ->title(__('notifications::notifications.error'))
                ->body(__('notifications::notifications.record_not_found'))
                ->send();

            // Redirect back to the list page
            return redirect()->back()->withErrors(['record' => 'Record not found']);
        } catch (\Exception $e) {
            // Other errors - redirect back with error
            \Laravilt\Notifications\Notification::danger()
                ->title(__('notifications::notifications.error'))
                ->body($e->getMessage())
                ->send();

            return redirect()->back();
        }
    }

    /**
     * Update a record (edit via modal).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $resource = static::getResource();
        $modelClass = $resource::getModel();

        try {
            $record = $modelClass::findOrFail($id);
            $data = $request->all();
            $record->fill($data);
            $record->save();

            return response()->json([
                'success' => true,
                'message' => __('actions::actions.messages.saved'),
                'record' => $record,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a record.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $resource = static::getResource();
        $modelClass = $resource::getModel();

        try {
            $record = $modelClass::findOrFail($id);
            $record->delete();

            return response()->json([
                'success' => true,
                'message' => __('actions::actions.messages.deleted'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Bulk delete records.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $resource = static::getResource();
        $modelClass = $resource::getModel();

        try {
            $ids = $request->input('ids', []);

            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => __('actions::actions.messages.no_records_selected'),
                ], 422);
            }

            $modelClass::whereIn('id', $ids)->delete();

            return response()->json([
                'success' => true,
                'message' => __('actions::actions.messages.bulk_deleted', ['count' => count($ids)]),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
