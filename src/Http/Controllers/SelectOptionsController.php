<?php

namespace Laravilt\Panel\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravilt\Panel\Contracts\HasTenants;
use Laravilt\Panel\Facades\Laravilt;
use Laravilt\Panel\Facades\Panel;

class SelectOptionsController extends Controller
{
    /**
     * Check if the current panel has tenancy enabled.
     */
    protected function isTenancyEnabled(): bool
    {
        $panel = Panel::getCurrent();

        return $panel && $panel->hasTenancy() && Laravilt::hasTenant();
    }

    /**
     * Get the current tenant ID.
     */
    protected function getTenantId(): mixed
    {
        return Laravilt::getTenantId();
    }

    /**
     * Get the tenant model class.
     */
    protected function getTenantModel(): ?string
    {
        return Laravilt::getTenantModel();
    }

    /**
     * Get the current user's accessible tenants (teams).
     * This is used for team_id/team_ids fields.
     */
    protected function getUserTenants(): array
    {
        $user = auth()->user();

        if (! $user instanceof HasTenants) {
            return [];
        }

        $panel = Panel::getCurrent();
        if (! $panel) {
            return [];
        }

        return $user->getTenants($panel)->pluck('name', 'id')->toArray();
    }

    /**
     * Check if a field name represents a tenant/team selection field.
     */
    protected function isTenantField(string $fieldName): bool
    {
        return in_array($fieldName, ['team_id', 'team_ids', 'tenant_id', 'tenant_ids']);
    }

    /**
     * Apply tenant scoping to a query based on the model's tenant relationship.
     */
    protected function applyTenantScoping(Builder $query, string $modelClass): Builder
    {
        if (! $this->isTenancyEnabled()) {
            return $query;
        }

        $tenantId = $this->getTenantId();
        $tenantModel = $this->getTenantModel();

        if (! $tenantId || ! $tenantModel) {
            return $query;
        }

        $model = new $modelClass;
        $table = $model->getTable();

        // Check if model is the tenant model itself (e.g., Team) - apply user's teams filter
        if ($modelClass === $tenantModel || is_subclass_of($modelClass, $tenantModel)) {
            return $this->applyUserTeamsFilter($query);
        }

        // Check for direct tenant column (e.g., team_id)
        $tenantColumn = Laravilt::getTenantOwnershipColumn();
        if ($tenantColumn && \Schema::hasColumn($table, $tenantColumn)) {
            return $query->where($table.'.'.$tenantColumn, $tenantId);
        }

        // Check for many-to-many relationship with tenant model (e.g., teams)
        if ($this->modelHasTenantRelationship($model)) {
            $tenantTable = (new $tenantModel)->getTable();

            return $query->whereHas('teams', function ($q) use ($tenantId, $tenantTable) {
                $q->where($tenantTable.'.id', $tenantId);
            });
        }

        return $query;
    }

    /**
     * Apply filter to only include user's accessible teams.
     */
    protected function applyUserTeamsFilter(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user instanceof HasTenants) {
            return $query->whereRaw('1 = 0'); // Return no results if user has no tenant access
        }

        $panel = Panel::getCurrent();
        if (! $panel) {
            return $query->whereRaw('1 = 0');
        }

        $userTenantIds = $user->getTenants($panel)->pluck('id')->toArray();

        if (empty($userTenantIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $userTenantIds);
    }

    /**
     * Check if a model has a many-to-many relationship with the tenant model.
     */
    protected function modelHasTenantRelationship(object $model): bool
    {
        // Check for common tenant relationship names
        $relationshipNames = ['teams', 'tenants'];

        foreach ($relationshipNames as $name) {
            if (method_exists($model, $name)) {
                try {
                    $relation = $model->{$name}();
                    if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                        return true;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return false;
    }

    /**
     * Search options for a Select field (both relationship and closure-based).
     *
     * For relationships:
     * - model: The parent model class (e.g., App\Models\Order)
     * - relationship: The relationship method name (e.g., customer)
     * - titleAttribute: The attribute to use for option labels (e.g., email)
     *
     * For closure-based options:
     * - resource: The resource slug (e.g., orders)
     * - field: The field name (e.g., product_id)
     *
     * Common parameters:
     * - search: The search query
     * - limit: Max number of results (default: 50)
     * - offset: Number of results to skip (default: 0)
     */
    public function search(Request $request): JsonResponse
    {
        // Check if this is a closure-based field request (field is required, resource is optional)
        if ($request->has('field')) {
            return $this->searchClosureOptions($request);
        }

        // Otherwise handle relationship search
        $modelClass = $request->input('model');
        $relationship = $request->input('relationship');
        $titleAttribute = $request->input('titleAttribute', 'name');
        $search = $request->input('search', '');
        $limit = min((int) $request->input('limit', 50), 100);

        // Validate required parameters
        if (! $modelClass || ! $relationship) {
            return response()->json([
                'error' => 'Missing required parameters: model and relationship',
            ], 400);
        }

        // Validate model class exists
        if (! class_exists($modelClass)) {
            return response()->json([
                'error' => 'Model class not found',
            ], 400);
        }

        // Create model instance and get relationship
        $modelInstance = new $modelClass;

        if (! method_exists($modelInstance, $relationship)) {
            return response()->json([
                'error' => 'Relationship method not found',
            ], 400);
        }

        $relation = $modelInstance->{$relationship}();
        $relatedModel = $relation->getRelated();
        $relatedTable = $relatedModel->getTable();
        $relatedModelClass = get_class($relatedModel);

        // Build query
        $query = $relatedModel::query();

        // Apply tenant scoping
        $query = $this->applyTenantScoping($query, $relatedModelClass);

        // Apply search if provided
        if ($search) {
            // Search in title attribute and common fields
            $searchableFields = [$titleAttribute];

            // Add common searchable fields if they exist
            $commonFields = ['name', 'email', 'title', 'first_name', 'last_name'];
            foreach ($commonFields as $field) {
                if ($field !== $titleAttribute && \Schema::hasColumn($relatedTable, $field)) {
                    $searchableFields[] = $field;
                }
            }

            $query->where(function ($q) use ($searchableFields, $search) {
                foreach ($searchableFields as $field) {
                    $q->orWhere($field, 'like', '%'.$search.'%');
                }
            });
        }

        // Get results with limit
        $records = $query->limit($limit)->get();

        // Build options array
        $options = [];
        foreach ($records as $record) {
            $label = $record->{$titleAttribute};

            // If title attribute is email and we have name fields, make a nicer label
            if ($titleAttribute === 'email') {
                if ($record->first_name || $record->last_name) {
                    $label = trim($record->first_name.' '.$record->last_name).' ('.$record->email.')';
                } elseif ($record->name) {
                    $label = $record->name.' ('.$record->email.')';
                }
            }

            $options[] = [
                'value' => $record->getKey(),
                'label' => $label,
            ];
        }

        return response()->json([
            'options' => $options,
            'hasMore' => $records->count() >= $limit,
        ]);
    }

    /**
     * Get initial options for a Select field (for preloading or showing selected value).
     */
    public function getOptions(Request $request): JsonResponse
    {
        $modelClass = $request->input('model');
        $relationship = $request->input('relationship');
        $titleAttribute = $request->input('titleAttribute', 'name');
        $ids = $request->input('ids', []);
        $limit = min((int) $request->input('limit', 50), 100);

        // Validate required parameters
        if (! $modelClass || ! $relationship) {
            return response()->json([
                'error' => 'Missing required parameters: model and relationship',
            ], 400);
        }

        // Validate model class exists
        if (! class_exists($modelClass)) {
            return response()->json([
                'error' => 'Model class not found',
            ], 400);
        }

        // Create model instance and get relationship
        $modelInstance = new $modelClass;

        if (! method_exists($modelInstance, $relationship)) {
            return response()->json([
                'error' => 'Relationship method not found',
            ], 400);
        }

        $relation = $modelInstance->{$relationship}();
        $relatedModel = $relation->getRelated();
        $relatedModelClass = get_class($relatedModel);

        // Build query
        $query = $relatedModel::query();

        // Apply tenant scoping
        $query = $this->applyTenantScoping($query, $relatedModelClass);

        // If specific IDs are requested, get those
        if (! empty($ids)) {
            $query->whereIn($relatedModel->getKeyName(), (array) $ids);
        } else {
            // Otherwise get initial set (limited)
            $query->limit($limit);
        }

        $records = $query->get();

        // Build options array
        $options = [];
        foreach ($records as $record) {
            $label = $record->{$titleAttribute};

            // If title attribute is email and we have name fields, make a nicer label
            if ($titleAttribute === 'email') {
                if ($record->first_name || $record->last_name) {
                    $label = trim($record->first_name.' '.$record->last_name).' ('.$record->email.')';
                } elseif ($record->name) {
                    $label = $record->name.' ('.$record->email.')';
                }
            }

            $options[] = [
                'value' => $record->getKey(),
                'label' => $label,
            ];
        }

        return response()->json([
            'options' => $options,
        ]);
    }

    /**
     * Search options for a closure-based Select field.
     */
    protected function searchClosureOptions(Request $request): JsonResponse
    {
        $resourceSlug = $request->input('resource');
        $fieldName = $request->input('field');
        $search = $request->input('search', '');
        $limit = min((int) $request->input('limit', 50), 100);
        $offset = (int) $request->input('offset', 0);
        $ids = $request->input('ids', []); // Specific IDs to fetch
        $relationManager = $request->input('relationManager'); // For relation manager forms

        // If fetching by IDs and no resource context, use field name convention to look up directly
        if (! empty($ids) && ! $resourceSlug && $fieldName) {
            return $this->fetchOptionsByFieldName($fieldName, (array) $ids);
        }

        // Find the resource
        $panel = Panel::getCurrent();
        if (! $panel) {
            return response()->json(['error' => 'Panel not found'], 400);
        }

        $resourceClass = null;
        if ($resourceSlug) {
            foreach ($panel->getResources() as $resource) {
                if ($resource::getSlug() === $resourceSlug) {
                    $resourceClass = $resource;
                    break;
                }
            }
        }

        // If no resource found, fall back to field name convention lookup
        if (! $resourceClass) {
            // If we have specific IDs, fetch those
            if (! empty($ids) && $fieldName) {
                return $this->fetchOptionsByFieldName($fieldName, (array) $ids);
            }

            // Otherwise, try to fetch all options using field name convention
            if ($fieldName) {
                return $this->fetchAllOptionsByFieldName($fieldName, $search, $limit, $offset);
            }

            return response()->json(['error' => 'Resource not found'], 400);
        }

        // Get the form schema - either from relation manager or resource
        $schema = new \Laravilt\Schemas\Schema;

        if ($relationManager && class_exists($relationManager)) {
            // Get form from relation manager
            try {
                // Create a dummy model instance for the relation manager
                $modelClass = $resourceClass::getModel();
                $dummyModel = new $modelClass;
                $manager = new $relationManager($dummyModel);
                $schema = $manager->form($schema);
            } catch (\Exception $e) {
                // Fallback to resource form
                $schema = $resourceClass::form($schema);
            }
        } else {
            $schema = $resourceClass::form($schema);
        }

        // Find the field (handle nested fields in Sections, Repeaters, etc.)
        $field = $this->findFieldInSchema($schema->getSchema(), $fieldName);

        \Log::info('[SelectOptionsController] Searching for field', [
            'fieldName' => $fieldName,
            'foundField' => $field ? get_class($field) : null,
            'schemaCount' => count($schema->getSchema()),
        ]);

        if (! $field) {
            return response()->json(['error' => 'Field not found'], 400);
        }

        // Get all options from the closure
        $allOptions = [];
        if (method_exists($field, 'getOptions')) {
            $allOptions = $field->getOptions();
            \Log::info('[SelectOptionsController] Got options', [
                'fieldName' => $fieldName,
                'optionsCount' => is_array($allOptions) ? count($allOptions) : 'not array',
                'authUserId' => auth()->id(),
            ]);
        }

        // Convert to array if needed
        if ($allOptions instanceof \Illuminate\Support\Collection) {
            $allOptions = $allOptions->all();
        }

        if (! is_array($allOptions)) {
            $allOptions = [];
        }

        // If specific IDs are requested, return only those options
        if (! empty($ids)) {
            $ids = (array) $ids;
            $options = [];
            foreach ($ids as $id) {
                $id = (string) $id;
                $label = null;

                // First try to find in closure options
                if (isset($allOptions[$id])) {
                    $label = (string) $allOptions[$id];
                } elseif (isset($allOptions[(int) $id])) {
                    $label = (string) $allOptions[(int) $id];
                }

                // If not found in closure options, try to get label from database directly
                // This handles cases where the selected item doesn't match the closure filter
                if ($label === null && $field) {
                    $label = $this->getLabelFromDatabase($field, $id);
                }

                if ($label !== null) {
                    $options[] = [
                        'value' => $id,
                        'label' => $label,
                    ];
                } else {
                    // Fallback: use ID as label
                    $options[] = [
                        'value' => $id,
                        'label' => 'ID: '.$id,
                    ];
                }
            }

            return response()->json([
                'options' => $options,
            ]);
        }

        // Apply search filter
        if ($search) {
            $searchLower = strtolower($search);
            $allOptions = array_filter($allOptions, function ($label, $key) use ($searchLower) {
                return str_contains(strtolower((string) $label), $searchLower) ||
                       str_contains(strtolower((string) $key), $searchLower);
            }, ARRAY_FILTER_USE_BOTH);
        }

        // Get total count before pagination
        $totalCount = count($allOptions);

        // Apply pagination
        $paginatedOptions = array_slice($allOptions, $offset, $limit, true);

        // Transform to frontend format
        $options = [];
        foreach ($paginatedOptions as $value => $label) {
            $options[] = [
                'value' => (string) $value,
                'label' => (string) $label,
            ];
        }

        return response()->json([
            'options' => $options,
            'hasMore' => ($offset + $limit) < $totalCount,
            'total' => $totalCount,
        ]);
    }

    /**
     * Fetch options by field name convention (e.g., product_id -> Product model).
     * This is a fallback when resource context is not available.
     */
    protected function fetchOptionsByFieldName(string $fieldName, array $ids): JsonResponse
    {
        $options = [];

        // Special handling for tenant/team fields - return only user's accessible tenants
        if ($this->isTenantField($fieldName)) {
            $userTenants = $this->getUserTenants();
            foreach ($ids as $id) {
                $id = (string) $id;
                if (isset($userTenants[$id]) || isset($userTenants[(int) $id])) {
                    $options[] = [
                        'value' => $id,
                        'label' => (string) ($userTenants[$id] ?? $userTenants[(int) $id]),
                    ];
                }
            }

            return response()->json([
                'options' => $options,
            ]);
        }

        // Remove _id or _ids suffix to get potential model name
        $modelName = $fieldName;
        if (str_ends_with($fieldName, '_ids')) {
            $modelName = str_replace('_ids', '', $fieldName);
        } elseif (str_ends_with($fieldName, '_id')) {
            $modelName = str_replace('_id', '', $fieldName);
        }

        $modelName = str($modelName)->studly()->toString();

        // Try common model namespaces
        $namespaces = [
            'App\\Models\\',
            'App\\',
        ];

        foreach ($namespaces as $namespace) {
            $fullClassName = $namespace.$modelName;
            if (class_exists($fullClassName)) {
                // Build query with tenant scoping
                $query = $fullClassName::whereIn('id', $ids);
                $query = $this->applyTenantScoping($query, $fullClassName);

                $records = $query->get();

                foreach ($records as $record) {
                    // Try common label attributes
                    $label = $record->name
                        ?? $record->title
                        ?? $record->label
                        ?? $record->email
                        ?? (string) $record->getKey();

                    $options[] = [
                        'value' => (string) $record->getKey(),
                        'label' => (string) $label,
                    ];
                }
                break;
            }
        }

        // Add fallback for any IDs not found
        $foundIds = collect($options)->pluck('value')->toArray();
        foreach ($ids as $id) {
            if (! in_array((string) $id, $foundIds)) {
                $options[] = [
                    'value' => (string) $id,
                    'label' => 'ID: '.$id,
                ];
            }
        }

        return response()->json([
            'options' => $options,
        ]);
    }

    /**
     * Fetch all options by field name convention (e.g., product_id -> Product model).
     * This is a fallback when resource context is not available.
     */
    protected function fetchAllOptionsByFieldName(string $fieldName, string $search, int $limit, int $offset): JsonResponse
    {
        $options = [];
        $totalCount = 0;

        // Special handling for tenant/team fields - return only user's accessible tenants
        if ($this->isTenantField($fieldName)) {
            $userTenants = $this->getUserTenants();

            // Apply search filter
            if ($search) {
                $searchLower = strtolower($search);
                $userTenants = array_filter($userTenants, function ($label) use ($searchLower) {
                    return str_contains(strtolower((string) $label), $searchLower);
                });
            }

            $totalCount = count($userTenants);

            // Apply pagination
            $paginatedTenants = array_slice($userTenants, $offset, $limit, true);

            foreach ($paginatedTenants as $id => $label) {
                $options[] = [
                    'value' => (string) $id,
                    'label' => (string) $label,
                ];
            }

            return response()->json([
                'options' => $options,
                'hasMore' => ($offset + $limit) < $totalCount,
                'total' => $totalCount,
            ]);
        }

        // Remove _id or _ids suffix to get potential model name
        $modelName = $fieldName;
        if (str_ends_with($fieldName, '_ids')) {
            $modelName = str_replace('_ids', '', $fieldName);
        } elseif (str_ends_with($fieldName, '_id')) {
            $modelName = str_replace('_id', '', $fieldName);
        }

        $modelName = str($modelName)->studly()->toString();

        // Try common model namespaces
        $namespaces = [
            'App\\Models\\',
            'App\\',
        ];

        foreach ($namespaces as $namespace) {
            $fullClassName = $namespace.$modelName;
            if (class_exists($fullClassName)) {
                // Build query
                $query = $fullClassName::query();

                // Apply tenant scoping
                $query = $this->applyTenantScoping($query, $fullClassName);

                // Apply search if provided
                if ($search) {
                    $model = new $fullClassName;
                    $table = $model->getTable();

                    // Try common searchable fields
                    $searchableFields = ['name', 'title', 'label', 'email'];
                    $existingFields = [];

                    foreach ($searchableFields as $field) {
                        if (\Schema::hasColumn($table, $field)) {
                            $existingFields[] = $field;
                        }
                    }

                    if (! empty($existingFields)) {
                        $query->where(function ($q) use ($existingFields, $search) {
                            foreach ($existingFields as $field) {
                                $q->orWhere($field, 'like', '%'.$search.'%');
                            }
                        });
                    }
                }

                // Get total count before pagination
                $totalCount = $query->count();

                // Apply pagination
                $records = $query->offset($offset)->limit($limit)->get();

                foreach ($records as $record) {
                    // Try common label attributes
                    $label = $record->name
                        ?? $record->title
                        ?? $record->label
                        ?? $record->email
                        ?? (string) $record->getKey();

                    $options[] = [
                        'value' => (string) $record->getKey(),
                        'label' => (string) $label,
                    ];
                }
                break;
            }
        }

        return response()->json([
            'options' => $options,
            'hasMore' => ($offset + $limit) < $totalCount,
            'total' => $totalCount,
        ]);
    }

    /**
     * Try to get a label from the database for a given field and ID.
     * This analyzes the field's options closure to determine the model and label attribute.
     */
    protected function getLabelFromDatabase(object $field, string $id): ?string
    {
        try {
            // Try to get model info from field name convention (e.g., product_id -> Product model)
            $fieldName = $field->getName();

            // Remove _id suffix to get potential model name
            if (str_ends_with($fieldName, '_id')) {
                $modelName = str_replace('_id', '', $fieldName);
                $modelName = str($modelName)->studly()->toString();

                // Try common model namespaces
                $namespaces = [
                    'App\\Models\\',
                    'App\\',
                ];

                foreach ($namespaces as $namespace) {
                    $fullClassName = $namespace.$modelName;
                    if (class_exists($fullClassName)) {
                        $model = $fullClassName::find($id);
                        if ($model) {
                            // Try common label attributes
                            $labelAttributes = ['name', 'title', 'label', 'email'];
                            foreach ($labelAttributes as $attr) {
                                if (isset($model->{$attr})) {
                                    return (string) $model->{$attr};
                                }
                            }

                            // Fallback to first string attribute
                            return (string) ($model->name ?? $model->title ?? $model->getKey());
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to get label from database: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Recursively find a field in the schema by name.
     */
    protected function findFieldInSchema(array $components, string $fieldName): ?object
    {
        foreach ($components as $component) {
            // Check if this component is the field we're looking for
            if (method_exists($component, 'getName') && $component->getName() === $fieldName) {
                return $component;
            }

            // Check nested schemas (Sections, Grids, etc.)
            if (method_exists($component, 'getSchema')) {
                $nestedSchema = $component->getSchema();
                if (is_array($nestedSchema)) {
                    $found = $this->findFieldInSchema($nestedSchema, $fieldName);
                    if ($found) {
                        return $found;
                    }
                }
            }

            // Check tabs
            if (method_exists($component, 'getTabs')) {
                $tabs = $component->getTabs();
                if (is_array($tabs)) {
                    foreach ($tabs as $tab) {
                        if (method_exists($tab, 'getSchema')) {
                            $tabSchema = $tab->getSchema();
                            if (is_array($tabSchema)) {
                                $found = $this->findFieldInSchema($tabSchema, $fieldName);
                                if ($found) {
                                    return $found;
                                }
                            }
                        }
                    }
                }
            }

            // Check repeater components
            if (method_exists($component, 'getComponents')) {
                $repeaterComponents = $component->getComponents();
                if (is_array($repeaterComponents)) {
                    $found = $this->findFieldInSchema($repeaterComponents, $fieldName);
                    if ($found) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }
}
