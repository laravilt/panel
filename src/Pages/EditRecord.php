<?php

declare(strict_types=1);

namespace Laravilt\Panel\Pages;

use Illuminate\Database\Eloquent\Model;
use Laravilt\Schemas\Schema;

abstract class EditRecord extends Page
{
    protected Model $record;

    /**
     * Mount the page with the record.
     * This method is for compatibility with Filament-style pages.
     *
     * @param  int|string|null  $record  The record ID
     */
    public function mount($record = null): void
    {
        if ($record !== null) {
            $this->record = $this->getRecord($record);
        }
    }

    /**
     * Get the record by ID.
     *
     * @param  int|string  $record  The record ID
     */
    public function getRecord($record): Model
    {
        $resource = static::getResource();
        $modelClass = $resource::getModel();

        return $modelClass::findOrFail($record);
    }

    /**
     * Fill the form with record data (for compatibility).
     */
    public function fillForm(): void
    {
        // This is a no-op in Laravilt since we use Inertia
        // The form is filled via getSchema() which uses the record data
    }

    /**
     * Get the page title using the resource's label with "Edit" prefix.
     */
    public static function getTitle(): string
    {
        $resource = static::getResource();

        if ($resource) {
            return __('laravilt-panel::panel.pages.edit_record.title', [
                'label' => $resource::getLabel(),
            ]);
        }

        return parent::getTitle();
    }

    /**
     * Get the page heading using the resource's label with "Edit" prefix.
     */
    public function getHeading(): string
    {
        return static::getTitle();
    }

    /**
     * Display the page (GET request handler).
     * Receives the record ID from route parameter and resolves the model.
     */
    public function create(\Illuminate\Http\Request $request, ...$parameters)
    {
        // Extract the record ID from the named route parameter
        // This handles both regular routes and subdomain routes where {tenant} is also a parameter
        $recordId = $request->route('record');

        // Fallback to first parameter if route parameter not available
        if (! $recordId && ! empty($parameters)) {
            // Skip tenant parameter if present (for subdomain routes)
            $recordId = count($parameters) > 1 ? end($parameters) : ($parameters[0] ?? null);
        }

        if (! $recordId) {
            throw new \InvalidArgumentException('Record ID parameter is required for EditRecord pages');
        }

        // Get the model class from the resource
        $resource = static::getResource();

        if (! $resource) {
            throw new \InvalidArgumentException('Resource is not set for '.static::class);
        }

        $modelClass = $resource::getModel();

        if (! $modelClass) {
            throw new \InvalidArgumentException('Model class is not set for resource '.$resource);
        }

        // Resolve the model instance from the ID
        $this->record = $modelClass::findOrFail($recordId);

        // Authorize access after record is resolved
        $this->authorizeAccess();

        return $this->render();
    }

    /**
     * Authorize access to this page.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function authorizeAccess(): void
    {
        $resource = static::getResource();

        if ($resource && ! $resource::canUpdate($this->record)) {
            abort(403);
        }
    }

    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    public function form(Schema $schema): Schema
    {
        $resource = static::getResource();

        return $resource::form($schema);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mutateFormDataBeforeFill(array $data): array
    {
        // Load many-to-many relationship IDs for the form
        $data = $this->loadRelationshipData($data);

        // Load media library data for the form (e.g., avatar URL)
        $data = $this->loadMediaLibraryData($data);

        return $data;
    }

    /**
     * Load media library data into form data for FileUpload fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function loadMediaLibraryData(array $data): array
    {
        // Check if model uses media library
        if (! method_exists($this->record, 'getRegisteredMediaCollections')) {
            return $data;
        }

        // Get registered media collections from the model
        try {
            $collections = $this->record->getRegisteredMediaCollections();
        } catch (\Throwable $e) {
            return $data;
        }

        foreach ($collections as $collection) {
            $collectionName = $collection->name;

            // Get the media URL(s) for this collection
            $media = $this->record->getMedia($collectionName);

            if ($media->isNotEmpty()) {
                // For single file collection, return the URL
                if ($media->count() === 1) {
                    $data[$collectionName] = $media->first()->getUrl();
                } else {
                    // For multiple files, return array of URLs
                    $data[$collectionName] = $media->map->getUrl()->toArray();
                }
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function afterSave(): void
    {
        //
    }

    protected function getRedirectUrl(): ?string
    {
        $resource = static::getResource();

        return $resource::getUrl('list');
    }

    public function save(array $data): Model
    {
        // Authorize save action
        $resource = static::getResource();
        if ($resource && ! $resource::canUpdate($this->record)) {
            abort(403);
        }

        $data = $this->mutateFormDataBeforeSave($data);

        // Extract relationship data for many-to-many relationships
        $relationships = $this->extractRelationshipData($data);

        // Extract media library field data before update
        $mediaLibraryData = $this->extractMediaLibraryData($data);

        $this->record->update($data);

        // Sync many-to-many relationships
        $this->syncRelationships($this->record, $relationships);

        // Process media library fields after model is saved
        $this->processMediaLibraryFields($this->record, $mediaLibraryData);

        $this->afterSave();

        return $this->record;
    }

    /**
     * Extract media library field data from form data.
     * These fields need to be processed separately after the model is saved.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractMediaLibraryData(array &$data): array
    {
        $mediaData = [];

        // Check if model uses media library
        if (! method_exists($this->record, 'getRegisteredMediaCollections')) {
            return $mediaData;
        }

        // Get registered media collections from the model
        $collections = $this->record->getRegisteredMediaCollections();

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

            // Skip if no value - clear the collection (user removed the file)
            if (empty($value)) {
                if (method_exists($record, 'clearMediaCollection')) {
                    $record->clearMediaCollection($collection);
                }

                continue;
            }

            // Handle single file (string path or URL)
            if (is_string($value)) {
                // If value is an existing URL, the media already exists - don't do anything
                if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                    continue;
                }

                // Otherwise it's a new file path, add it
                $this->addMediaFromPath($record, $value, $collection);
            }
            // Handle multiple files (array of paths/URLs)
            elseif (is_array($value)) {
                // Separate existing URLs from new file paths
                $existingUrls = [];
                $newPaths = [];

                foreach ($value as $path) {
                    if (is_string($path) && ! empty($path)) {
                        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                            $existingUrls[] = $path;
                        } else {
                            $newPaths[] = $path;
                        }
                    }
                }

                // If there are new paths, we need to handle them
                if (! empty($newPaths)) {
                    // Clear existing media for this collection before adding new ones
                    if (method_exists($record, 'clearMediaCollection')) {
                        $record->clearMediaCollection($collection);
                    }

                    foreach ($newPaths as $path) {
                        $this->addMediaFromPath($record, $path, $collection);
                    }
                }
                // If only existing URLs and no new paths, media is already in place
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
            $exists = \Illuminate\Support\Facades\Storage::disk($checkDisk)->exists($path);

            if ($exists) {
                try {
                    // For single file collection, clear existing first
                    if (method_exists($record, 'clearMediaCollection')) {
                        $record->clearMediaCollection($collection);
                    }

                    // Add media from disk
                    $record->addMediaFromDisk($path, $checkDisk)
                        ->toMediaCollection($collection);

                    // Delete the temporary upload after adding to media library
                    \Illuminate\Support\Facades\Storage::disk($checkDisk)->delete($path);

                    return;
                } catch (\Throwable $e) {
                    // Failed to add media, continue to next disk
                }
            }
        }
    }

    /**
     * Load relationship data into form data.
     * Handles BelongsToMany (IDs array) and HasMany (full records array for Repeaters).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function loadRelationshipData(array $data): array
    {
        $reflectionClass = new \ReflectionClass($this->record);
        $modelClass = $this->record::class;

        // List of methods to skip (Eloquent methods that should not be called)
        $skipMethods = [
            'delete', 'forceDelete', 'restore', 'save', 'update', 'fresh', 'refresh',
            'push', 'touch', 'replicate', 'toArray', 'toJson', 'jsonSerialize',
            'getKey', 'getTable', 'getConnection', 'newQuery', 'newQueryWithoutScopes',
            // SoftDeletes trait methods
            'forceDeleteQuietly', 'deleteQuietly', 'restoreQuietly',
            // Spatie Media Library methods (InteractsWithMedia trait)
            'media', 'registerMediaCollections', 'getRegisteredMediaCollections',
            'registerMediaConversions', 'registerAllMediaConversions',
            'clearMediaCollection', 'clearMediaCollectionExcept',
            'deletePreservingMedia', 'shouldDeletePreservingMedia',
            'getMedia', 'getFirstMedia', 'getFirstMediaUrl', 'getFirstMediaPath',
            'hasMedia', 'loadMedia', 'addMedia', 'addMediaFromUrl', 'addMediaFromDisk',
            'addMediaFromBase64', 'addMediaFromStream', 'addMediaFromRequest',
            'copyMedia', 'moveMedia', 'updateMedia', 'syncMedia', 'syncMediaWithIds',
            'deleteAllMedia', 'deleteMedia',
            // HasAvatar trait methods
            'deleteAvatar',
            // Other destructive/side-effect methods
            'deleteOtherSessions',
        ];

        // Known relationship methods from traits (e.g., Spatie's HasRoles)
        $knownRelationshipMethods = ['permissions', 'roles'];

        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();
            $declaringClass = $method->getDeclaringClass()->getName();

            // Skip magic methods, methods with parameters, and dangerous Eloquent methods
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
                $relation = $this->record->{$methodName}();

                // Check if it's a BelongsToMany relationship - load IDs
                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                    $data[$methodName] = $this->record->{$methodName}()->pluck($relation->getRelated()->getTable().'.id')->toArray();
                }
                // Check if it's a HasMany relationship - load full records (for Repeaters)
                elseif ($relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany) {
                    $data[$methodName] = $this->record->{$methodName}()->get()->toArray();
                }
            } catch (\Throwable $e) {
                // Not a relationship method, skip
                continue;
            }
        }

        return $data;
    }

    /**
     * Extract many-to-many relationship data from form data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractRelationshipData(array &$data): array
    {
        $relationships = [];

        $reflectionClass = new \ReflectionClass($this->record);
        $modelClass = $this->record::class;

        // List of methods to skip (Eloquent methods that should not be called)
        $skipMethods = [
            'delete', 'forceDelete', 'restore', 'save', 'update', 'fresh', 'refresh',
            'push', 'touch', 'replicate', 'toArray', 'toJson', 'jsonSerialize',
            'getKey', 'getTable', 'getConnection', 'newQuery', 'newQueryWithoutScopes',
            // SoftDeletes trait methods
            'forceDeleteQuietly', 'deleteQuietly', 'restoreQuietly',
            // Spatie Media Library methods (InteractsWithMedia trait)
            'media', 'registerMediaCollections', 'getRegisteredMediaCollections',
            'registerMediaConversions', 'registerAllMediaConversions',
            'clearMediaCollection', 'clearMediaCollectionExcept',
            'deletePreservingMedia', 'shouldDeletePreservingMedia',
            'getMedia', 'getFirstMedia', 'getFirstMediaUrl', 'getFirstMediaPath',
            'hasMedia', 'loadMedia', 'addMedia', 'addMediaFromUrl', 'addMediaFromDisk',
            'addMediaFromBase64', 'addMediaFromStream', 'addMediaFromRequest',
            'copyMedia', 'moveMedia', 'updateMedia', 'syncMedia', 'syncMediaWithIds',
            'deleteAllMedia', 'deleteMedia',
            // HasAvatar trait methods
            'deleteAvatar',
            // Other destructive/side-effect methods
            'deleteOtherSessions',
        ];

        // Known relationship methods from traits (e.g., Spatie's HasRoles)
        $knownRelationshipMethods = ['permissions', 'roles'];

        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();
            $declaringClass = $method->getDeclaringClass()->getName();

            // Skip magic methods, methods with parameters, and dangerous Eloquent methods
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

            // Check if this key exists in the data
            if (! array_key_exists($methodName, $data)) {
                continue;
            }

            try {
                $relation = $this->record->{$methodName}();

                // Check if it's a BelongsToMany relationship
                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                    $relationships[$methodName] = $data[$methodName];
                    unset($data[$methodName]);
                }
            } catch (\Throwable $e) {
                // Not a relationship method, skip
                continue;
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
     * @return array<mixed>
     */
    public function getHeaderActions(): array
    {
        return [];
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
        $form = $this->form((new \Laravilt\Schemas\Schema)->model($modelClass)->resourceSlug($resource::getSlug())->operation('edit'));

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

        $form = $this->form((new \Laravilt\Schemas\Schema)->model($modelClass)->resourceSlug($resource::getSlug())->operation('edit'));

        // Fill with record data if available
        if (isset($this->record)) {
            $formData = $this->mutateFormDataBeforeFill($this->record->toArray());
            $form->fill($formData);
        }

        // Get the form schema
        $schema = $form->getSchema();

        // Add actions to the bottom of the form
        $actions = [
            \Laravilt\Actions\Action::make('save')
                ->label(__('laravilt-panel::panel.common.save'))
                ->color('primary')
                ->submit()
                ->preserveState(false)
                ->action(function (mixed $record, array $data) {
                    // Validate form data
                    $validated = $this->validateFormData($data);

                    $this->save($validated);
                    $redirectUrl = $this->getRedirectUrl();

                    \Laravilt\Notifications\Notification::success()
                        ->title(__('notifications::notifications.success'))
                        ->body(__('notifications::notifications.record_updated'))
                        ->send();

                    return redirect($redirectUrl);
                }),

            \Laravilt\Actions\Action::make('cancel')
                ->label(__('laravilt-panel::panel.common.cancel'))
                ->color('secondary')
                ->outlined()
                ->method('GET')
                ->url($resource::getUrl('list')),
        ];

        // Append actions to schema
        foreach ($actions as $action) {
            $schema[] = $action;
        }

        $form->schema($schema);

        return [$form];
    }

    /**
     * Get the relation managers for this record.
     *
     * @return array<array<string, mixed>>
     */
    public function getRelationManagers(): array
    {
        $resource = static::getResource();

        if (! $resource || ! isset($this->record)) {
            return [];
        }

        $relationManagers = $resource::getRelations();
        $resourceSlug = $resource::getSlug();

        return collect($relationManagers)
            ->map(function ($relationManagerClass) use ($resourceSlug) {
                /** @var \Laravilt\Panel\Resources\RelationManagers\RelationManager $manager */
                $manager = $relationManagerClass::make($this->record);

                if ($resourceSlug) {
                    $manager->resourceSlug($resourceSlug);
                }

                return $manager->toArray();
            })
            ->values()
            ->all();
    }

    /**
     * Get extra props for Inertia response.
     */
    protected function getInertiaProps(): array
    {
        $resource = static::getResource();

        return [
            'record' => $this->record ?? null,
            'relationManagers' => $this->getRelationManagers(),
            'resourceSlug' => $resource ? $resource::getSlug() : null,
        ];
    }
}
