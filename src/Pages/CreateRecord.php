<?php

declare(strict_types=1);

namespace Laravilt\Panel\Pages;

use Illuminate\Database\Eloquent\Model;
use Laravilt\Schemas\Schema;

abstract class CreateRecord extends Page
{
    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Authorize access to this page.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function authorizeAccess(): void
    {
        $resource = static::getResource();

        if ($resource && ! $resource::canCreate()) {
            abort(403);
        }
    }

    /**
     * Get the page title using the resource's label with "Create" prefix.
     */
    public static function getTitle(): string
    {
        $resource = static::getResource();

        if ($resource) {
            return __('laravilt-panel::panel.pages.create_record.title', [
                'label' => $resource::getLabel(),
            ]);
        }

        return parent::getTitle();
    }

    /**
     * Get the page heading using the resource's label with "Create" prefix.
     */
    public function getHeading(): string
    {
        return static::getTitle();
    }

    public function form(Schema $schema): Schema
    {
        $resource = static::getResource();

        return $resource::form($schema);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }

    protected function afterCreate(): void
    {
        //
    }

    protected function getRedirectUrl(): ?string
    {
        $resource = static::getResource();

        return $resource::getUrl('list');
    }

    public function createRecord(array $data): Model
    {
        $resource = static::getResource();

        // Authorize create action
        if ($resource && ! $resource::canCreate()) {
            abort(403);
        }

        $model = $resource::getModel();

        $data = $this->mutateFormDataBeforeCreate($data);

        // Extract relationship data for many-to-many relationships
        $relationships = $this->extractRelationshipData($data);

        // Create a new model instance to check for media library collections
        $tempRecord = new $model;

        // Extract media library field data before create
        $mediaLibraryData = $this->extractMediaLibraryData($data, $tempRecord);

        // Create the actual record
        $record = new $model($data);

        // Associate record with current tenant if applicable (sets team_id for direct relationships)
        $resource::associateRecordWithTenant($record);

        $record->save();

        // Sync many-to-many relationships
        $this->syncRelationships($record, $relationships);

        // Process media library fields after model is saved
        $this->processMediaLibraryFields($record, $mediaLibraryData);

        // Associate record with tenant via many-to-many if applicable (attaches to teams pivot)
        $resource::associateRecordWithTenantManyToMany($record);

        $this->afterCreate();

        return $record;
    }

    /**
     * Extract media library field data from form data.
     * These fields need to be processed separately after the model is saved.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractMediaLibraryData(array &$data, Model $record): array
    {
        $mediaData = [];

        // Check if model uses media library
        if (! method_exists($record, 'getRegisteredMediaCollections')) {
            return $mediaData;
        }

        // Get registered media collections from the model
        $collections = $record->getRegisteredMediaCollections();

        foreach ($collections as $collection) {
            $collectionName = $collection->name;

            // Check if this collection name exists in the form data
            if (array_key_exists($collectionName, $data)) {
                $mediaData[$collectionName] = [
                    'value' => $data[$collectionName],
                    'collection' => $collectionName,
                ];
                unset($data[$collectionName]);
            }
        }

        return $mediaData;
    }

    /**
     * Process media library fields after the model is saved.
     *
     * @param  array<string, mixed>  $mediaData
     */
    protected function processMediaLibraryFields(Model $record, array $mediaData): void
    {
        // Check if model uses media library
        if (! method_exists($record, 'addMediaFromDisk') && ! method_exists($record, 'addMedia')) {
            return;
        }

        foreach ($mediaData as $fieldName => $fieldConfig) {
            $value = $fieldConfig['value'];
            $collection = $fieldConfig['collection'];

            // Skip if no value
            if (empty($value)) {
                continue;
            }

            // Handle single file (string path)
            if (is_string($value)) {
                $this->addMediaFromPath($record, $value, $collection);
            }
            // Handle multiple files (array of paths)
            elseif (is_array($value)) {
                foreach ($value as $path) {
                    if (is_string($path) && ! empty($path)) {
                        $this->addMediaFromPath($record, $path, $collection);
                    }
                }
            }
        }
    }

    /**
     * Add media to record from a file path.
     */
    protected function addMediaFromPath(Model $record, string $path, string $collection): void
    {
        // Skip if path is empty or is already a URL (media already exists)
        if (empty($path) || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        // The path should be relative to the storage disk
        $disk = config('filesystems.default', 'public');

        // Try public disk first, then default
        $disksToCheck = ['public', $disk];
        $disksToCheck = array_unique($disksToCheck);

        foreach ($disksToCheck as $checkDisk) {
            if (\Illuminate\Support\Facades\Storage::disk($checkDisk)->exists($path)) {
                try {
                    // Add media from disk
                    $record->addMediaFromDisk($path, $checkDisk)
                        ->toMediaCollection($collection);

                    // Delete the temporary upload after adding to media library
                    \Illuminate\Support\Facades\Storage::disk($checkDisk)->delete($path);

                    return;
                } catch (\Throwable $e) {
                    \Log::error('Failed to add media from disk', [
                        'path' => $path,
                        'disk' => $checkDisk,
                        'collection' => $collection,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Extract many-to-many relationship data from form data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractRelationshipData(array &$data): array
    {
        $model = static::getResource()::getModel();
        $modelInstance = new $model;
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
    protected function syncRelationships(Model $record, array $relationships): void
    {
        foreach ($relationships as $relationName => $relationData) {
            if ($relationData !== null) {
                $record->{$relationName}()->sync($relationData);
            }
        }
    }

    /**
     * Validate form data using schema validation rules.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateFormData(array $data): array
    {
        $resource = static::getResource();
        $modelClass = $resource::getModel();
        $form = $this->form((new \Laravilt\Schemas\Schema)->model($modelClass)->resourceSlug($resource::getSlug())->operation('create'));

        $rules = $form->getValidationRules();
        $messages = $form->getValidationMessages();
        $attributes = $form->getValidationAttributes();
        $prefixes = $form->getFieldPrefixes();

        // Only validate if there are rules
        if (empty($rules)) {
            return $data;
        }

        // Prepend prefixes to field values for validation (e.g., https:// for URL fields)
        $dataForValidation = $data;
        foreach ($prefixes as $fieldName => $prefix) {
            if (isset($dataForValidation[$fieldName]) && is_string($dataForValidation[$fieldName]) && $dataForValidation[$fieldName] !== '') {
                // Only prepend if value doesn't already start with the prefix
                if (! str_starts_with($dataForValidation[$fieldName], $prefix)) {
                    $dataForValidation[$fieldName] = $prefix.$dataForValidation[$fieldName];
                }
            }
        }

        // Validate with prefixed data
        validator($dataForValidation, $rules, $messages, $attributes)->validate();

        // Return original data (without prefixes) - the storage should save the user-entered value
        return $data;
    }

    /**
     * Get the schema (form) for this page.
     */
    public function getSchema(): array
    {
        $resource = static::getResource();
        $modelClass = $resource::getModel();

        $form = $this->form((new \Laravilt\Schemas\Schema)->model($modelClass)->resourceSlug($resource::getSlug())->operation('create'));

        // Get the form schema
        $schema = $form->getSchema();

        // Add actions to the bottom of the form (as standalone actions, not component-based)
        $actions = [
            \Laravilt\Actions\Action::make('create')
                ->label(__('laravilt-panel::panel.common.create'))
                ->color('primary')
                ->submit()
                ->preserveState(false)
                ->action(function (mixed $record, array $data) {
                    // Validate form data
                    $validated = $this->validateFormData($data);

                    $newRecord = $this->createRecord($validated);
                    $redirectUrl = $this->getRedirectUrl();

                    \Laravilt\Notifications\Notification::success()
                        ->title(__('notifications::notifications.success'))
                        ->body(__('notifications::notifications.record_created'))
                        ->send();

                    return redirect($redirectUrl);
                }),

            \Laravilt\Actions\Action::make('createAnother')
                ->label(__('laravilt-panel::panel.common.create_and_create_another'))
                ->color('secondary')
                ->submit()
                ->preserveState(false)
                ->action(function (mixed $record, array $data) {
                    // Validate form data
                    $validated = $this->validateFormData($data);

                    $this->createRecord($validated);
                    $resource = static::getResource();
                    $createUrl = $resource::getUrl('create');

                    \Laravilt\Notifications\Notification::success()
                        ->title(__('notifications::notifications.success'))
                        ->body(__('notifications::notifications.record_created'))
                        ->send();

                    return redirect($createUrl);
                }),

            \Laravilt\Actions\Action::make('cancel')
                ->label(__('laravilt-panel::panel.common.cancel'))
                ->color('secondary')
                ->outlined()
                ->method('GET')
                ->url($resource::getUrl('list')),
        ];

        // Append actions to schema (they will use standalone action tokens)
        foreach ($actions as $action) {
            $schema[] = $action;
        }

        $form->schema($schema);

        return [$form];
    }
}
