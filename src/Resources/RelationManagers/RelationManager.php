<?php

declare(strict_types=1);

namespace Laravilt\Panel\Resources\RelationManagers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Laravilt\Actions\Action;
use Laravilt\Infolists\Infolist;
use Laravilt\Schemas\Schema;
use Laravilt\Tables\Table;

abstract class RelationManager
{
    protected static string $relationship;

    protected static ?string $recordTitleAttribute = null;

    protected static ?string $label = null;

    protected static ?string $pluralLabel = null;

    protected static ?string $icon = null;

    protected Model $ownerRecord;

    protected ?string $resourceSlug = null;

    public function __construct(Model $ownerRecord)
    {
        $this->ownerRecord = $ownerRecord;
    }

    public static function make(Model $ownerRecord): static
    {
        return new static($ownerRecord);
    }

    /**
     * Set the parent resource slug (for Select options pagination).
     */
    public function resourceSlug(string $slug): static
    {
        $this->resourceSlug = $slug;

        return $this;
    }

    public static function getRelationship(): string
    {
        return static::$relationship;
    }

    public static function getLabel(): string
    {
        return static::$label ?? str(static::getRelationship())->title()->toString();
    }

    public static function getPluralLabel(): string
    {
        return static::$pluralLabel ?? str(static::getLabel())->plural()->toString();
    }

    public static function getIcon(): ?string
    {
        return static::$icon;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return static::$recordTitleAttribute;
    }

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function canCreate(): bool
    {
        return ! $this->isReadOnly();
    }

    public function canEdit(): bool
    {
        return ! $this->isReadOnly();
    }

    public function canDelete(): bool
    {
        return ! $this->isReadOnly();
    }

    public function getRelationshipQuery(): Relation
    {
        return $this->ownerRecord->{static::getRelationship()}();
    }

    /**
     * Get the header actions for the relation manager table.
     *
     * @return array<Action>
     */
    public function getHeaderActions(): array
    {
        $actions = [];

        if ($this->canCreate() && ! $this->isReadOnly()) {
            // Get the related model class from the relationship
            $relatedModel = $this->getRelationshipQuery()->getRelated();
            $modelClass = get_class($relatedModel);

            // Create schema with model, resourceSlug and relationManagerClass
            $schema = Schema::make()->model($modelClass);
            if ($this->resourceSlug) {
                $schema->resourceSlug($this->resourceSlug);
            }
            $schema->relationManagerClass(static::class);
            $formSchema = $this->form($schema)->getSchema();

            // Build the relation URL for creating records
            // This will be overridden by the frontend with the correct panel/resource/record context
            $createAction = Action::make('create')
                ->label(__('actions::actions.buttons.create').' '.static::getLabel())
                ->icon('Plus')
                ->color('primary')
                ->method('POST')
                ->requiresConfirmation(true)
                ->modalHeading(__('actions::actions.buttons.create').' '.static::getLabel())
                ->modalFormSchema($formSchema)
                ->modalSubmitActionLabel(__('actions::actions.buttons.create'))
                ->modalCancelActionLabel(__('actions::actions.buttons.cancel'))
                ->preserveState(false);

            $actions[] = $createAction;
        }

        return $actions;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $table = $this->table(Table::make());

        // Add header actions to the table
        $headerActions = $this->getHeaderActions();
        foreach ($headerActions as $action) {
            $table->headerActions([$action]);
        }

        // Get the related model class from the relationship
        $relatedModel = $this->getRelationshipQuery()->getRelated();
        $modelClass = get_class($relatedModel);

        \Log::info('[RelationManager] toArray', [
            'relationManagerClass' => static::class,
            'resourceSlug' => $this->resourceSlug,
            'modelClass' => $modelClass,
        ]);

        // Create schema with model, resourceSlug and relationManagerClass
        $formSchema = Schema::make()->model($modelClass);
        if ($this->resourceSlug) {
            $formSchema->resourceSlug($this->resourceSlug);
        }
        $formSchema->relationManagerClass(static::class);

        return [
            'relationship' => static::getRelationship(),
            'label' => static::getLabel(),
            'pluralLabel' => static::getPluralLabel(),
            'icon' => static::getIcon(),
            'recordTitleAttribute' => static::getRecordTitleAttribute(),
            'readOnly' => $this->isReadOnly(),
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
            'canDelete' => $this->canDelete(),
            'form' => $this->form($formSchema)->toInertiaProps(),
            'infolist' => $this->infolist(Infolist::make())->toInertiaProps(),
            'table' => $table->toInertiaProps(),
            'headerActions' => collect($headerActions)->map(fn ($action) => $action->toArray())->values()->all(),
        ];
    }
}
