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
        Container::setInstance(new Container);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        FakeUploadersRepository::$calls = [];
        FakeSettingsUploader::$calls = [];

        parent::tearDown();
    }

    public function test_it_is_a_noop_for_non_upload_column_types()
    {
        $entry = new FakeSettingEntry(['value' => 'raw']);

        $result = UploaderColumnHydrator::hydrate($entry, [
            'name' => 'value',
            'type' => 'text',
        ]);

        $this->assertSame('raw', $result->value);
    }

    public function test_it_is_a_noop_when_no_uploader_macro_is_present()
    {
        $entry = new FakeSettingEntry(['value' => 'foo/bar.jpg']);

        $result = UploaderColumnHydrator::hydrate($entry, [
            'name' => 'value',
            'type' => 'upload',
            'disk' => 'public',
        ]);

        $this->assertSame('foo/bar.jpg', $result->value);
    }

    public function test_it_is_a_noop_when_uploaders_repository_is_not_bound()
    {
        // Container is fresh + empty; no UploadersRepository registered.
        $entry = new FakeSettingEntry(['value' => 'foo/bar.jpg']);

        $result = UploaderColumnHydrator::hydrate($entry, [
            'name'      => 'value',
            'type'      => 'upload',
            'withFiles' => ['disk' => 'public'],
        ]);

        $this->assertSame('foo/bar.jpg', $result->value);
    }

    public function test_it_is_a_noop_when_no_uploader_is_registered_for_the_column_type_and_macro()
    {
        Container::getInstance()->instance('UploadersRepository', new FakeUploadersRepository([]));

        $entry = new FakeSettingEntry(['value' => 'foo/bar.jpg']);

        $result = UploaderColumnHydrator::hydrate($entry, [
            'name'      => 'value',
            'type'      => 'upload',
            'withFiles' => ['disk' => 'public'],
        ]);

        $this->assertSame('foo/bar.jpg', $result->value);
    }

    public function test_it_runs_the_default_uploader_for_with_files_and_hydrates_the_value()
    {
        Container::getInstance()->instance('UploadersRepository', new FakeUploadersRepository([
            'upload|withFiles' => FakeSettingsUploader::class,
        ]));

        $entry = new FakeSettingEntry(['value' => 'logo.png']);

        $result = UploaderColumnHydrator::hydrate($entry, [
            'name'      => 'value',
            'type'      => 'upload',
            'withFiles' => ['disk' => 'public', 'path' => 'settings'],
        ]);

        $this->assertSame('hydrated:logo.png', $result->value);
        $this->assertCount(1, FakeSettingsUploader::$calls);
        $this->assertSame(
            ['disk' => 'public', 'path' => 'settings'],
            FakeSettingsUploader::$calls[0]['uploadDefinition']
        );
    }

    public function test_it_runs_the_default_uploader_for_with_media()
    {
        Container::getInstance()->instance('UploadersRepository', new FakeUploadersRepository([
            'image|withMedia' => FakeSettingsUploader::class,
        ]));

        $entry = new FakeSettingEntry(['value' => 'avatar.jpg']);

        $result = UploaderColumnHydrator::hydrate($entry, [
            'name'      => 'value',
            'type'      => 'image',
            'withMedia' => ['collection' => 'settings'],
        ]);

        $this->assertSame('hydrated:avatar.jpg', $result->value);
    }

    public function test_it_honors_a_custom_uploader_class_from_the_definition()
    {
        // Even with NO default registered, an explicit `uploader` key wins.
        Container::getInstance()->instance('UploadersRepository', new FakeUploadersRepository([]));

        $entry = new FakeSettingEntry(['value' => 'doc.pdf']);

        $result = UploaderColumnHydrator::hydrate($entry, [
            'name'      => 'value',
            'type'      => 'upload',
            'withFiles' => [
                'uploader' => FakeSettingsUploader::class,
                'disk'     => 'private',
            ],
        ]);

        $this->assertSame('hydrated:doc.pdf', $result->value);
    }

    public function test_with_files_wins_when_both_macros_are_present()
    {
        Container::getInstance()->instance('UploadersRepository', new FakeUploadersRepository([
            'upload|withFiles' => FakeSettingsUploader::class,
            'upload|withMedia' => FakeSettingsAlternateUploader::class,
        ]));

        $entry = new FakeSettingEntry(['value' => 'x.png']);

        UploaderColumnHydrator::hydrate($entry, [
            'name'      => 'value',
            'type'      => 'upload',
            'withFiles' => ['disk' => 'a'],
            'withMedia' => ['disk' => 'b'],
        ]);

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
    public static array $calls = [];

    public function __construct(private array $map = []) {}

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

    public function __construct(public array $crudObject, public array $uploadDefinition) {}

    public static function for(array $crudObject, array $uploadDefinition): self
    {
        self::$calls[] = compact('crudObject', 'uploadDefinition');

        return new self($crudObject, $uploadDefinition);
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
