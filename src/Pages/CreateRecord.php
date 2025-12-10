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
        $model = $resource::getModel();

        $data = $this->mutateFormDataBeforeCreate($data);

        // Extract relationship data for many-to-many relationships
        $relationships = $this->extractRelationshipData($data);

        $record = $model::create($data);

        // Sync many-to-many relationships
        $this->syncRelationships($record, $relationships);

        $this->afterCreate();

        return $record;
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
        $form = $this->form(new \Laravilt\Schemas\Schema);

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
        $form = $this->form(new \Laravilt\Schemas\Schema);

        $resource = static::getResource();

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
