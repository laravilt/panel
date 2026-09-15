<?php

namespace Laravilt\Panel\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravilt\Panel\Resources\RelationManagers\RelationManager;
use Laravilt\Tables\Columns\Contracts\EditableColumn;
use Laravilt\Tables\Columns\ToggleColumn;
use Laravilt\Tables\Table;

/**
 * Persists a single inline-edited column value (ToggleColumn, CheckboxColumn, SelectColumn, TextInputColumn)
 * for resource tables and relation manager tables.
 *
 * Only a column the developer declared as editable (and not disabled) in the relevant table can be written,
 * only to its own attribute, validated with its rules, on a tenant-scoped record the user may update.
 *
 * laravilt/tables releases without the EditableColumn contract are still supported: in that case only
 * ToggleColumn is editable and the value must be a boolean.
 */
class ColumnStateController
{
    /**
     * PATCH {slug}/{id}/column
     *
     * @param  class-string  $resourceClass
     */
    public function updateResourceColumn(Request $request, string $resourceClass): mixed
    {
        $column = $this->resolveColumn($resourceClass::table(new Table), $request);

        $record = $this->resolveOwnerRecord($resourceClass, (string) $request->route('id'));

        abort_unless($this->canUpdateResourceRecord($resourceClass, $record), 403);

        $name = $column->getName();
        $value = $this->validateState($column, $request);

        $this->persist($column, $record, $name, $value);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'column' => $name,
                'state' => $record->getAttribute($name),
            ]);
        }

        return back();
    }

    /**
     * PATCH {slug}/{id}/relations/{relationship}/{relationId}/column
     *
     * @param  class-string  $resourceClass
     */
    public function updateRelationColumn(Request $request, string $resourceClass): mixed
    {
        $relationship = (string) $request->route('relationship');

        $relationManagerClass = null;

        foreach ($resourceClass::getRelations() as $rmClass) {
            if ($rmClass::getRelationship() === $relationship) {
                $relationManagerClass = $rmClass;
                break;
            }
        }

        if (! $relationManagerClass) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Relation manager not found'], 404);
            }

            return back()->withErrors(['error' => 'Relation manager not found']);
        }

        // The owner record is loaded through the resource query, so tenant scoping applies
        $ownerRecord = $this->resolveOwnerRecord($resourceClass, (string) $request->route('id'));

        /** @var RelationManager $relationManager */
        $relationManager = $relationManagerClass::make($ownerRecord);

        $column = $this->resolveColumn($relationManager->table(new Table), $request);

        // The related record must belong to the (scoped) owner record
        $relatedRecord = $relationManager->getRelationshipQuery()->findOrFail($request->route('relationId'));

        abort_unless($this->canUpdateRelatedRecord($resourceClass, $relationManager, $ownerRecord, $relatedRecord), 403);

        $name = $column->getName();
        $value = $this->validateState($column, $request);

        $this->persist($column, $relatedRecord, $name, $value);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'data' => $relatedRecord,
            ]);
        }

        return back();
    }

    /**
     * Whether the installed laravilt/tables ships the EditableColumn contract (tables > 1.0.x).
     */
    protected function supportsEditableColumns(): bool
    {
        return interface_exists(EditableColumn::class);
    }

    /**
     * Find the requested column in the table and make sure it may be written inline.
     */
    protected function resolveColumn(Table $table, Request $request): object
    {
        $name = $request->input('column');

        abort_if(! is_string($name) || $name === '', 422, 'The column field is required.');

        foreach ($table->getColumns() as $column) {
            if ($column->getName() !== $name) {
                continue;
            }

            // Plain display columns (TextColumn, ...) can never be written through this endpoint
            abort_unless($this->isEditableColumn($column), 403, 'This column is not editable.');
            // Relationship paths ("author.name") are display-only: only the record's own attribute is updated
            abort_if(str_contains($name, '.'), 403, 'Relationship columns cannot be edited inline.');
            abort_if(method_exists($column, 'isDisabled') && $column->isDisabled(), 403, 'This column is disabled.');

            return $column;
        }

        abort(404, 'Column not found.');
    }

    protected function isEditableColumn(object $column): bool
    {
        if ($this->supportsEditableColumns()) {
            return $column instanceof EditableColumn;
        }

        // Legacy tables: ToggleColumn is the only inline-editable column
        return $column instanceof ToggleColumn;
    }

    /**
     * @param  class-string  $resourceClass
     */
    protected function resolveOwnerRecord(string $resourceClass, string $key): Model
    {
        // getEloquentQuery() applies the resource's tenant scoping
        $query = method_exists($resourceClass, 'getEloquentQuery')
            ? $resourceClass::getEloquentQuery()
            : $resourceClass::getModel()::query();

        return $query->whereKey($key)->firstOrFail();
    }

    /**
     * @param  class-string  $resourceClass
     */
    protected function canUpdateResourceRecord(string $resourceClass, Model $record): bool
    {
        // Resource authorization honours $usePolicies (policy "update") or the panel's permission system
        if (method_exists($resourceClass, 'canUpdate')) {
            return (bool) $resourceClass::canUpdate($record);
        }

        if (Gate::getPolicyFor($record)) {
            return Gate::allows('update', $record);
        }

        return auth()->check();
    }

    /**
     * Editing a related record inline requires the relation manager to allow editing, the user to be
     * allowed to update the owner record, and (when the related model has a policy) to update the related record.
     *
     * @param  class-string  $resourceClass
     */
    protected function canUpdateRelatedRecord(string $resourceClass, RelationManager $relationManager, Model $ownerRecord, Model $relatedRecord): bool
    {
        if (method_exists($relationManager, 'canEdit') && ! $relationManager->canEdit()) {
            return false;
        }

        if (! $this->canUpdateResourceRecord($resourceClass, $ownerRecord)) {
            return false;
        }

        if (Gate::getPolicyFor($relatedRecord)) {
            return Gate::allows('update', $relatedRecord);
        }

        return true;
    }

    /**
     * Validate the incoming value with the column's rules and return it converted for storage.
     */
    protected function validateState(object $column, Request $request): mixed
    {
        $name = $column->getName();
        $label = method_exists($column, 'getLabel') && $column->getLabel() ? $column->getLabel() : $name;

        if ($this->supportsEditableColumns() && $column instanceof EditableColumn) {
            $rules = $column->getStateValidationRules();
        } else {
            $rules = ['required', 'boolean'];
        }

        // Errors are keyed by the column name, which is what the Vue columns read on failure
        $validated = Validator::make(
            [$name => $request->input('value')],
            [$name => $rules],
            [],
            [$name => $label],
        )->validate();

        $value = $validated[$name] ?? null;

        if ($this->supportsEditableColumns() && $column instanceof EditableColumn) {
            return $column->dehydrateState($value);
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Write only this column's attribute, running the column's state callbacks around the save.
     */
    protected function persist(object $column, Model $record, string $name, mixed $value): void
    {
        if (method_exists($column, 'getBeforeStateUpdated') && ($before = $column->getBeforeStateUpdated())) {
            $before($record, $name, $value);
        }

        $record->setAttribute($name, $value);
        $record->save();

        if (method_exists($column, 'getAfterStateUpdated') && ($after = $column->getAfterStateUpdated())) {
            $after($record, $name, $value);
        }
    }
}
