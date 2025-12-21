<?php

declare(strict_types=1);

namespace Laravilt\Panel\Pages;

use Illuminate\Database\Eloquent\Model;
use Laravilt\Schemas\Schema;

abstract class ViewRecord extends Page
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
     * Get the page title using the resource's label with "View" prefix.
     */
    public static function getTitle(): string
    {
        $resource = static::getResource();

        if ($resource) {
            return __('laravilt-panel::panel.pages.view_record.title', [
                'label' => $resource::getLabel(),
            ]);
        }

        return parent::getTitle();
    }

    /**
     * Get the page heading using the resource's label with "View" prefix.
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
            throw new \InvalidArgumentException('Record ID parameter is required for ViewRecord pages');
        }

        // Get the model class from the resource
        $resource = static::getResource();
        $modelClass = $resource::getModel();

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

        if ($resource && ! $resource::canView($this->record)) {
            abort(403);
        }
    }

    public function infolist(Schema $schema): Schema
    {
        $resource = static::getResource();

        return $resource::infolist($schema);
    }

    /**
     * @return array<mixed>
     */
    public function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Get the schema (infolist) for this page.
     */
    public function getSchema(): array
    {
        // Configure infolist
        $infolist = $this->infolist(new \Laravilt\Schemas\Schema);

        // Fill with record data if available
        if (isset($this->record)) {
            // Set the record on schema so entries can access it for closures
            $infolist->record($this->record);
            $infolist->fill($this->record->toArray());
        }

        return [$infolist];
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

        return collect($relationManagers)
            ->map(function ($relationManagerClass) {
                /** @var \Laravilt\Panel\Resources\RelationManagers\RelationManager $manager */
                $manager = $relationManagerClass::make($this->record);

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
