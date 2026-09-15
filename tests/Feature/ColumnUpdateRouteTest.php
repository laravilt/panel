<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravilt\Panel\Http\Controllers\ColumnStateController;
use Laravilt\Panel\Panel;
use Laravilt\Panel\PanelServiceProvider;
use Laravilt\Panel\Resources\RelationManagers\RelationManager;
use Laravilt\Panel\Resources\Resource;
use Laravilt\Tables\Columns\SelectColumn;
use Laravilt\Tables\Columns\TextColumn;
use Laravilt\Tables\Columns\ToggleColumn;
use Laravilt\Tables\Table;

class ColumnTestUser extends AuthUser
{
    protected $table = 'users';

    protected $guarded = [];
}

class ColumnTestPost extends Model
{
    protected $table = 'posts';

    protected $guarded = [];

    public function comments(): HasMany
    {
        return $this->hasMany(ColumnTestComment::class, 'post_id');
    }
}

class ColumnTestComment extends Model
{
    protected $table = 'comments';

    protected $guarded = [];
}

class ColumnTestCommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    public static bool $readOnly = false;

    public function isReadOnly(): bool
    {
        return static::$readOnly;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('body'),
            ToggleColumn::make('is_approved'),
        ]);
    }
}

class ColumnTestPostResource extends Resource
{
    protected static string $model = ColumnTestPost::class;

    protected static ?string $slug = 'posts';

    /** Simulates the resource's update authorization (permission / policy) */
    public static bool $allowUpdate = true;

    /** Simulates tenant scoping: only records of this team are visible */
    public static ?int $teamId = null;

    /** @var array<int, array{0: string, 1: mixed}> */
    public static array $callbacks = [];

    public static function canUpdate(?Model $record = null): bool
    {
        return static::$allowUpdate;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return static::$teamId === null ? $query : $query->where('team_id', static::$teamId);
    }

    public static function getRelations(): array
    {
        return [ColumnTestCommentsRelationManager::class];
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title'),
            ToggleColumn::make('is_published')
                ->beforeStateUpdated(function ($record, $column, $value) {
                    static::$callbacks[] = ['before', $value];
                })
                ->afterStateUpdated(function ($record, $column, $value) {
                    static::$callbacks[] = ['after', $value];
                }),
            ToggleColumn::make('is_locked')->disabled(),
            SelectColumn::make('status')->options(['draft' => 'Draft', 'live' => 'Live']),
        ]);
    }
}

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('password');
        $table->timestamps();
    });

    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->unsignedBigInteger('team_id')->nullable();
        $table->boolean('is_published')->default(false);
        $table->boolean('is_locked')->default(false);
        $table->string('status')->default('draft');
        $table->boolean('is_admin')->default(false);
        $table->timestamps();
    });

    Schema::create('comments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('post_id');
        $table->string('body');
        $table->boolean('is_approved')->default(false);
        $table->boolean('is_admin')->default(false);
        $table->timestamps();
    });

    ColumnTestPostResource::$allowUpdate = true;
    ColumnTestPostResource::$teamId = null;
    ColumnTestPostResource::$callbacks = [];
    ColumnTestCommentsRelationManager::$readOnly = false;

    // Register the real panel route closures (the same code the panel registers for every resource)
    $provider = app()->getProvider(PanelServiceProvider::class);
    $panel = Panel::make('admin');

    Route::middleware('web')->prefix('admin')->group(function () use ($provider, $panel) {
        (function () use ($panel) {
            $this->registerColumnUpdateRoute(ColumnTestPostResource::class, 'posts', ColumnTestPost::class, $panel);
            $this->registerRelationManagerRoutes(ColumnTestPostResource::class, 'posts', ColumnTestPost::class, $panel);
        })->call($provider);
    });

    $this->actingAs(ColumnTestUser::create(['name' => 'User', 'email' => 'user@example.com', 'password' => 'secret']));

    $this->post = ColumnTestPost::create(['title' => 'Hello', 'team_id' => 1]);
    $this->comment = $this->post->comments()->create(['body' => 'Nice']);
});

describe('resource column route', function () {
    it('lets an authorized user toggle an editable column', function () {
        $this->patchJson('/admin/posts/'.$this->post->id.'/column', ['column' => 'is_published', 'value' => 1])
            ->assertOk()
            ->assertJson(['success' => true, 'column' => 'is_published']);

        expect($this->post->fresh()->is_published)->toBe(1)
            ->and(ColumnTestPostResource::$callbacks)->toBe([['before', true], ['after', true]]);
    });

    it('redirects back for Inertia requests', function () {
        $this->from('/admin/posts')
            ->patch('/admin/posts/'.$this->post->id.'/column', ['column' => 'is_published', 'value' => 1], ['X-Inertia' => 'true'])
            ->assertRedirect('/admin/posts');

        expect((bool) $this->post->fresh()->is_published)->toBeTrue();
    });

    it('returns 403 when the user may not update the record', function () {
        ColumnTestPostResource::$allowUpdate = false;

        $this->patchJson('/admin/posts/'.$this->post->id.'/column', ['column' => 'is_published', 'value' => 1])
            ->assertForbidden();

        expect((bool) $this->post->fresh()->is_published)->toBeFalse();
    });

    it('rejects a non-editable text column', function () {
        $this->patchJson('/admin/posts/'.$this->post->id.'/column', ['column' => 'title', 'value' => 'Hacked'])
            ->assertForbidden();

        expect($this->post->fresh()->title)->toBe('Hello');
    });

    it('rejects a column that is not in the table', function () {
        $this->patchJson('/admin/posts/'.$this->post->id.'/column', ['column' => 'is_admin', 'value' => 1])
            ->assertNotFound();

        expect((bool) $this->post->fresh()->is_admin)->toBeFalse();
    });

    it('rejects a disabled column', function () {
        $this->patchJson('/admin/posts/'.$this->post->id.'/column', ['column' => 'is_locked', 'value' => 1])
            ->assertForbidden();
    });

    it('rejects a missing column name', function () {
        $this->patchJson('/admin/posts/'.$this->post->id.'/column', ['value' => 1])
            ->assertStatus(422);
    });

    it('rejects a non-boolean value on a toggle', function () {
        $this->patchJson('/admin/posts/'.$this->post->id.'/column', ['column' => 'is_published', 'value' => 'yes please'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_published');

        expect((bool) $this->post->fresh()->is_published)->toBeFalse();
    });

    it('only accepts the allowed options on a select column', function () {
        $this->patchJson('/admin/posts/'.$this->post->id.'/column', ['column' => 'status', 'value' => 'deleted'])
            ->assertStatus(422);

        $this->patchJson('/admin/posts/'.$this->post->id.'/column', ['column' => 'status', 'value' => 'live'])
            ->assertOk();

        expect($this->post->fresh()->status)->toBe('live');
    });

    it('only writes the column attribute', function () {
        $this->patchJson('/admin/posts/'.$this->post->id.'/column', ['column' => 'is_published', 'value' => 1, 'is_admin' => 1, 'title' => 'x'])
            ->assertOk();

        $post = $this->post->fresh();
        expect((bool) $post->is_admin)->toBeFalse()->and($post->title)->toBe('Hello');
    });

    it('loads the record through the resource query (tenant scoping)', function () {
        ColumnTestPostResource::$teamId = 2;

        $this->patchJson('/admin/posts/'.$this->post->id.'/column', ['column' => 'is_published', 'value' => 1])
            ->assertNotFound();

        expect((bool) $this->post->fresh()->is_published)->toBeFalse();
    });
});

describe('relation manager column route', function () {
    function relationColumnUrl($test): string
    {
        return '/admin/posts/'.$test->post->id.'/relations/comments/'.$test->comment->id.'/column';
    }

    it('lets an authorized user toggle an editable relation column', function () {
        $this->patchJson(relationColumnUrl($this), ['column' => 'is_approved', 'value' => true])
            ->assertOk()
            ->assertJson(['success' => true]);

        expect((bool) $this->comment->fresh()->is_approved)->toBeTrue();
    });

    it('rejects an arbitrary attribute', function () {
        $this->patchJson(relationColumnUrl($this), ['column' => 'is_admin', 'value' => 1])
            ->assertNotFound();

        $this->patchJson(relationColumnUrl($this), ['column' => 'post_id', 'value' => 999])
            ->assertNotFound();

        $this->patchJson(relationColumnUrl($this), ['column' => 'body', 'value' => 'Hacked'])
            ->assertForbidden();

        $comment = $this->comment->fresh();
        expect((bool) $comment->is_admin)->toBeFalse()
            ->and($comment->post_id)->toBe($this->post->id)
            ->and($comment->body)->toBe('Nice');
    });

    it('rejects a non-boolean value', function () {
        $this->patchJson(relationColumnUrl($this), ['column' => 'is_approved', 'value' => 'abc'])
            ->assertStatus(422);
    });

    it('returns 403 when the owner record may not be updated', function () {
        ColumnTestPostResource::$allowUpdate = false;

        $this->patchJson(relationColumnUrl($this), ['column' => 'is_approved', 'value' => 1])
            ->assertForbidden();
    });

    it('returns 403 when the relation manager is read-only', function () {
        ColumnTestCommentsRelationManager::$readOnly = true;

        $this->patchJson(relationColumnUrl($this), ['column' => 'is_approved', 'value' => 1])
            ->assertForbidden();
    });

    it('rejects a related record that does not belong to the owner', function () {
        $other = ColumnTestPost::create(['title' => 'Other']);
        $foreign = $other->comments()->create(['body' => 'Foreign']);

        $this->patchJson('/admin/posts/'.$this->post->id.'/relations/comments/'.$foreign->id.'/column', ['column' => 'is_approved', 'value' => 1])
            ->assertNotFound();

        expect((bool) $foreign->fresh()->is_approved)->toBeFalse();
    });
});

describe('legacy laravilt/tables without the EditableColumn contract', function () {
    beforeEach(function () {
        // Simulates tables 1.0.x, where EditableColumn / getStateValidationRules() do not exist
        app()->bind(ColumnStateController::class, fn () => new class extends ColumnStateController
        {
            protected function supportsEditableColumns(): bool
            {
                return false;
            }
        });
    });

    it('still lets a toggle column be updated with a boolean', function () {
        $this->patchJson('/admin/posts/'.$this->post->id.'/column', ['column' => 'is_published', 'value' => 1])
            ->assertOk();

        expect((bool) $this->post->fresh()->is_published)->toBeTrue()
            ->and(ColumnTestPostResource::$callbacks)->toBe([['before', true], ['after', true]]);
    });

    it('rejects a non-boolean toggle value', function () {
        $this->patchJson('/admin/posts/'.$this->post->id.'/column', ['column' => 'is_published', 'value' => 'abc'])
            ->assertStatus(422);
    });

    it('only allows toggle columns', function () {
        $this->patchJson('/admin/posts/'.$this->post->id.'/column', ['column' => 'status', 'value' => 'live'])
            ->assertForbidden();

        $this->patchJson('/admin/posts/'.$this->post->id.'/column', ['column' => 'title', 'value' => 'x'])
            ->assertForbidden();

        expect($this->post->fresh()->status)->toBe('draft');
    });

    it('still requires update authorization', function () {
        ColumnTestPostResource::$allowUpdate = false;

        $this->patchJson('/admin/posts/'.$this->post->id.'/column', ['column' => 'is_published', 'value' => 1])
            ->assertForbidden();
    });
});
