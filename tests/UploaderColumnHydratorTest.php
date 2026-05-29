<?php

namespace Backpack\Settings\Test;

use Backpack\Settings\app\Library\UploaderColumnHydrator;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class UploaderColumnHydratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Fresh container per test so the bound `UploadersRepository`
        // doesn't leak between cases.
        Container::setInstance(new Container());
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        FakeSettingsUploader::$calls = [];
        FakeSettingsAlternateUploader::$calls = [];

        parent::tearDown();
    }

    public function test_it_is_a_noop_for_non_upload_column_types()
    {
        $entry = new FakeSettingEntry(['value' => 'raw']);
        $column = ['name' => 'value', 'type' => 'text'];

        $result = UploaderColumnHydrator::hydrate($entry, $column);

        $this->assertSame('raw', $result->value);
        $this->assertArrayNotHasKey('disk', $column);
    }

    public function test_it_is_a_noop_when_no_uploader_macro_is_present()
    {
        $entry = new FakeSettingEntry(['value' => 'foo/bar.jpg']);
        $column = ['name' => 'value', 'type' => 'upload', 'disk' => 'public'];

        $result = UploaderColumnHydrator::hydrate($entry, $column);

        $this->assertSame('foo/bar.jpg', $result->value);
        $this->assertSame('public', $column['disk']);
    }

    public function test_it_is_a_noop_when_uploaders_repository_is_not_bound()
    {
        // Container is fresh + empty; no UploadersRepository registered.
        $entry = new FakeSettingEntry(['value' => 'foo/bar.jpg']);
        $column = ['name' => 'value', 'type' => 'upload', 'withFiles' => ['disk' => 'public']];

        $result = UploaderColumnHydrator::hydrate($entry, $column);

        $this->assertSame('foo/bar.jpg', $result->value);
    }

    public function test_it_is_a_noop_when_no_uploader_is_registered_for_the_column_type_and_macro()
    {
        Container::getInstance()->instance('UploadersRepository', new FakeUploadersRepository([]));

        $entry = new FakeSettingEntry(['value' => 'foo/bar.jpg']);
        $column = ['name' => 'value', 'type' => 'upload', 'withFiles' => ['disk' => 'public']];

        $result = UploaderColumnHydrator::hydrate($entry, $column);

        $this->assertSame('foo/bar.jpg', $result->value);
    }

    public function test_it_runs_the_default_uploader_for_with_files_and_hydrates_the_value()
    {
        Container::getInstance()->instance('UploadersRepository', new FakeUploadersRepository([
            'upload|withFiles' => FakeSettingsUploader::class,
        ]));

        $entry = new FakeSettingEntry(['value' => 'logo.png']);
        $column = [
            'name'      => 'value',
            'type'      => 'upload',
            'withFiles' => ['disk' => 'public', 'path' => 'settings'],
        ];

        $result = UploaderColumnHydrator::hydrate($entry, $column);

        $this->assertSame('hydrated:logo.png', $result->value);
        $this->assertCount(1, FakeSettingsUploader::$calls);
        $this->assertSame(
            ['disk' => 'public', 'path' => 'settings'],
            FakeSettingsUploader::$calls[0]['uploadDefinition']
        );
    }

    /**
     * Regression: the upload column blade view reads `$column['disk']` and
     * `$column['prefix']` directly. If the hydrator only mutates $entry and
     * leaves the column untouched, the view crashes with
     * "Undefined array key 'disk'". RegisterUploadEvents normally sets
     * these via setupUploadConfigsInCrudObject() — the hydrator must too.
     */
    public function test_it_populates_disk_and_prefix_on_the_column_from_the_uploader()
    {
        Container::getInstance()->instance('UploadersRepository', new FakeUploadersRepository([
            'upload|withFiles' => FakeSettingsUploader::class,
        ]));

        $entry = new FakeSettingEntry(['value' => 'logo.png']);
        $column = [
            'name'      => 'value',
            'type'      => 'upload',
            'withFiles' => ['disk' => 'public', 'path' => 'settings'],
        ];

        UploaderColumnHydrator::hydrate($entry, $column);

        $this->assertSame('public', $column['disk']);
        $this->assertSame('settings', $column['prefix']);
    }

    public function test_it_populates_temporary_and_expiration_when_the_uploader_uses_temporary_urls()
    {
        Container::getInstance()->instance('UploadersRepository', new FakeUploadersRepository([
            'upload|withFiles' => FakeTemporaryUrlUploader::class,
        ]));

        $entry = new FakeSettingEntry(['value' => 'logo.png']);
        $column = [
            'name'      => 'value',
            'type'      => 'upload',
            'withFiles' => ['disk' => 'private', 'temporaryUrl' => true],
        ];

        UploaderColumnHydrator::hydrate($entry, $column);

        $this->assertTrue($column['temporary']);
        $this->assertSame(15, $column['expiration']);
    }

    public function test_it_runs_the_default_uploader_for_with_media()
    {
        Container::getInstance()->instance('UploadersRepository', new FakeUploadersRepository([
            'image|withMedia' => FakeSettingsUploader::class,
        ]));

        $entry = new FakeSettingEntry(['value' => 'avatar.jpg']);
        $column = [
            'name'      => 'value',
            'type'      => 'image',
            'withMedia' => ['collection' => 'settings'],
        ];

        $result = UploaderColumnHydrator::hydrate($entry, $column);

        $this->assertSame('hydrated:avatar.jpg', $result->value);
    }

    public function test_it_honors_a_custom_uploader_class_from_the_definition()
    {
        // Even with NO default registered, an explicit `uploader` key wins.
        Container::getInstance()->instance('UploadersRepository', new FakeUploadersRepository([]));

        $entry = new FakeSettingEntry(['value' => 'doc.pdf']);
        $column = [
            'name'      => 'value',
            'type'      => 'upload',
            'withFiles' => [
                'uploader' => FakeSettingsUploader::class,
                'disk'     => 'private',
            ],
        ];

        $result = UploaderColumnHydrator::hydrate($entry, $column);

        $this->assertSame('hydrated:doc.pdf', $result->value);
    }

    public function test_with_files_wins_when_both_macros_are_present()
    {
        Container::getInstance()->instance('UploadersRepository', new FakeUploadersRepository([
            'upload|withFiles' => FakeSettingsUploader::class,
            'upload|withMedia' => FakeSettingsAlternateUploader::class,
        ]));

        $entry = new FakeSettingEntry(['value' => 'x.png']);
        $column = [
            'name'      => 'value',
            'type'      => 'upload',
            'withFiles' => ['disk' => 'a'],
            'withMedia' => ['disk' => 'b'],
        ];

        UploaderColumnHydrator::hydrate($entry, $column);

        $this->assertCount(1, FakeSettingsUploader::$calls);
        $this->assertCount(0, FakeSettingsAlternateUploader::$calls);
    }
}

/**
 * Minimal Eloquent stand-in. We only need property access on `value` —
 * the hydrator never touches relations / casts / persistence.
 */
class FakeSettingEntry extends Model
{
    protected $guarded = [];
}

class FakeUploadersRepository
{
    public function __construct(private array $map = [])
    {
    }

    public function hasUploadFor($type, $macro): bool
    {
        return isset($this->map[$type.'|'.$macro]);
    }

    public function getUploadFor($type, $macro): string
    {
        return $this->map[$type.'|'.$macro];
    }
}

class FakeSettingsUploader
{
    public static array $calls = [];

    public function __construct(public array $crudObject, public array $uploadDefinition)
    {
    }

    public static function for(array $crudObject, array $uploadDefinition): self
    {
        self::$calls[] = compact('crudObject', 'uploadDefinition');

        return new static($crudObject, $uploadDefinition);
    }

    public function getDisk(): string
    {
        return $this->uploadDefinition['disk'] ?? 'public';
    }

    public function getPath(): string
    {
        return $this->uploadDefinition['path'] ?? '';
    }

    public function useTemporaryUrl(): bool
    {
        return false;
    }

    public function getExpirationTimeInMinutes(): int
    {
        return 1;
    }

    public function retrieveUploadedFiles(Model $entry): Model
    {
        $entry->value = 'hydrated:'.$entry->value;

        return $entry;
    }
}

class FakeSettingsAlternateUploader extends FakeSettingsUploader
{
    public static array $calls = [];

    public static function for(array $crudObject, array $uploadDefinition): self
    {
        self::$calls[] = compact('crudObject', 'uploadDefinition');

        return new self($crudObject, $uploadDefinition);
    }

    public function retrieveUploadedFiles(Model $entry): Model
    {
        $entry->value = 'alt:'.$entry->value;

        return $entry;
    }
}

class FakeTemporaryUrlUploader extends FakeSettingsUploader
{
    public function useTemporaryUrl(): bool
    {
        return true;
    }

    public function getExpirationTimeInMinutes(): int
    {
        return 15;
    }

    public function getDisk(): string
    {
        return 'private';
    }
}
