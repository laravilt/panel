<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravilt\Panel\Pages\ManageRecords;
use Laravilt\Panel\Resources\Resource;
use Laravilt\Tables\Columns\TextColumn;
use Laravilt\Tables\Table;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PolicyTestUser extends AuthUser
{
    protected $table = 'users';

    protected $guarded = [];
}

class PolicyDeniedPost extends Model
{
    protected $table = 'policy_posts';

    protected $guarded = [];
}

class PolicyOpenPost extends Model
{
    protected $table = 'policy_posts';

    protected $guarded = [];
}

class PolicyDeniedPostPolicy
{
    public function viewAny($user): bool
    {
        return false;
    }

    public function view($user, $post): bool
    {
        return false;
    }

    public function create($user): bool
    {
        return false;
    }

    public function update($user, $post): bool
    {
        return false;
    }

    public function delete($user, $post): bool
    {
        return false;
    }

    public function deleteAny($user): bool
    {
        return false;
    }
}

class PolicyDeniedPostResource extends Resource
{
    protected static string $model = PolicyDeniedPost::class;

    protected static ?string $slug = 'denied-posts';

    public static function getPages(): array
    {
        return ['index' => ['class' => PolicyDeniedManagePage::class, 'path' => '/']];
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')]);
    }
}

class PolicyDeniedManagePage extends ManageRecords
{
    protected static ?string $resource = PolicyDeniedPostResource::class;
}

class PolicyOpenPostResource extends Resource
{
    protected static string $model = PolicyOpenPost::class;

    protected static ?string $slug = 'open-posts';

    public static function getPages(): array
    {
        return [];
    }
}

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });
    Schema::create('policy_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });

    $this->actingAs(PolicyTestUser::create(['name' => 'u', 'email' => 'u@example.com', 'password' => 'x']));
    Gate::policy(PolicyDeniedPost::class, PolicyDeniedPostPolicy::class);
});

it('denies every ability when the model policy returns false', function () {
    $record = PolicyDeniedPost::create(['title' => 'a']);

    expect(PolicyDeniedPostResource::canViewAny())->toBeFalse()
        ->and(PolicyDeniedPostResource::canCreate())->toBeFalse()
        ->and(PolicyDeniedPostResource::canView($record))->toBeFalse()
        ->and(PolicyDeniedPostResource::canUpdate($record))->toBeFalse()
        ->and(PolicyDeniedPostResource::canDelete($record))->toBeFalse()
        ->and(PolicyDeniedPostResource::canDelete())->toBeFalse();
});

it('does not restrict resources whose model has no policy', function () {
    $record = PolicyOpenPost::create(['title' => 'a']);

    expect(PolicyOpenPostResource::canViewAny())->toBeTrue()
        ->and(PolicyOpenPostResource::canCreate())->toBeTrue()
        ->and(PolicyOpenPostResource::canUpdate($record))->toBeTrue()
        ->and(PolicyOpenPostResource::canDelete($record))->toBeTrue();
});

it('aborts with 403 on manage page endpoints when the policy denies', function () {
    $record = PolicyDeniedPost::create(['title' => 'a']);
    $page = app(PolicyDeniedManagePage::class);

    $calls = [
        fn () => $page->index(Request::create('/')),
        fn () => $page->store(Request::create('/', 'POST', ['title' => 'b'])),
        fn () => $page->show(Request::create('/'), $record->id),
        fn () => $page->update(Request::create('/', 'PUT', ['title' => 'b']), $record->id),
        fn () => $page->destroy(Request::create('/', 'DELETE'), $record->id),
        fn () => $page->bulkDelete(Request::create('/', 'POST', ['ids' => [$record->id]])),
    ];

    foreach ($calls as $call) {
        try {
            $call();
            $status = 200;
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
        }

        expect($status)->toBe(403);
    }

    expect(PolicyDeniedPost::count())->toBe(1)
        ->and($record->fresh()->title)->toBe('a');
});
