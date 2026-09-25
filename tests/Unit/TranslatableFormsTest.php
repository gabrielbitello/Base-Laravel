<?php

namespace Tests\Unit;

use App\Filament\Translatable\Concerns\InteractsWithTranslatableForms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Tests\TestCase;

class TranslatableFormsTest extends TestCase
{
    public function test_wraps_scalar_file_upload_values_on_fill(): void
    {
        $page = $this->pageWithForm('avatar');

        $result = $page->exposedFill(['avatar' => 'a.png', 'title' => 'ok'], $this->translatableModel(), 'es');

        $this->assertSame(['avatar' => ['a.png'], 'title' => 'ok'], $result);
    }

    public function test_wraps_nested_file_upload_values_on_fill(): void
    {
        $page = $this->pageWithForm('content.cover');

        $result = $page->exposedFill(
            ['content' => ['cover' => 'x.png', 'heading' => 'Hi']],
            $this->translatableModel(),
            'es'
        );

        $this->assertSame(['content' => ['cover' => ['x.png'], 'heading' => 'Hi']], $result);
    }

    public function test_leaves_existing_arrays_at_upload_paths_untouched_on_fill(): void
    {
        $page = $this->pageWithForm('avatar');

        $data = ['avatar' => ['a.png', 'b.png']];

        $result = $page->exposedFill($data, $this->translatableModel(), 'es');

        $this->assertSame($data, $result);
    }

    public function test_flattens_single_element_upload_arrays_on_save(): void
    {
        $page = $this->pageWithForm('avatar');

        $result = $page->exposedSave(['avatar' => ['a.png']]);

        $this->assertSame(['avatar' => 'a.png'], $result);
    }

    public function test_flattens_nested_upload_arrays_on_save(): void
    {
        $page = $this->pageWithForm('content.cover');

        $result = $page->exposedSave(['content' => ['cover' => ['x.png']]]);

        $this->assertSame(['content' => ['cover' => 'x.png']], $result);
    }

    public function test_keeps_multi_file_upload_arrays_on_save(): void
    {
        $page = $this->pageWithForm('avatar');

        $data = ['avatar' => ['a.png', 'b.png']];

        $result = $page->exposedSave($data);

        $this->assertSame($data, $result);
    }

    public function test_reindexes_uuid_keyed_arrays_on_save(): void
    {
        $page = $this->pageWithForm(null);

        $result = $page->exposedSave([
            'sections' => [
                '0f8d9a1e-2b3c-4d5e-8f90-1a2b3c4d5e6f' => ['title' => 'A'],
                '1f8d9a1e-2b3c-4d5e-8f90-1a2b3c4d5e6f' => ['title' => 'B'],
            ],
        ]);

        $this->assertSame([
            'sections' => [
                ['title' => 'A'],
                ['title' => 'B'],
            ],
        ], $result);
    }

    public function test_preserves_key_value_subtrees_on_save(): void
    {
        $page = $this->pageWithForm('metadata');

        $data = ['metadata' => [
            '0f8d9a1e-2b3c-4d5e-8f90-1a2b3c4d5e6f' => 'value',
        ]];

        $result = $page->exposedSave($data);

        $this->assertSame($data, $result);
    }

    public function test_fallback_fills_empty_scalars_from_default_locale_only(): void
    {
        $page = $this->pageWithForm(null);

        $record = $this->translatableModel([
            'title' => ['en' => 'Hello', 'es' => ''],
            'gallery' => ['en' => ['a.png'], 'es' => []],
            'subtitle' => ['en' => '', 'es' => ''],
        ]);

        $result = $page->exposedFill(
            ['title' => '', 'gallery' => null, 'subtitle' => '', 'name' => 'keep'],
            $record,
            'es'
        );

        $this->assertSame('Hello', $result['title']);
        $this->assertNull($result['gallery']);
        $this->assertSame('', $result['subtitle']);
        $this->assertSame('keep', $result['name']);
    }

    public function test_fallback_keeps_non_empty_values_on_fill(): void
    {
        $page = $this->pageWithForm(null);

        $record = $this->translatableModel([
            'title' => ['en' => 'Hello', 'es' => 'Hola'],
        ]);

        $result = $page->exposedFill(['title' => 'Hola'], $record, 'es');

        $this->assertSame('Hola', $result['title']);
    }

    private function pageWithForm(?string $uploadPath): object
    {
        $components = match ($uploadPath) {
            'avatar' => [$this->fakeComponent('avatar', FileUpload::class)],
            'content.cover' => [$this->fakeComponent('content', null, [
                $this->fakeComponent('cover', FileUpload::class),
            ])],
            'metadata' => [$this->fakeComponent('metadata', KeyValue::class)],
            default => [],
        };

        $form = new class($components)
        {
            public function __construct(private array $components) {}

            public function getComponents(): array
            {
                return $this->components;
            }
        };

        return new class($form)
        {
            public $form;

            public function __construct($form)
            {
                $this->form = $form;
            }

            public function exposedFill(array $data, Model $record, string $locale): array
            {
                return $this->translatableFill($data, $record, $locale);
            }

            public function exposedSave(array $data): array
            {
                return $this->translatableSave($data);
            }

            protected function translatableComponentIs(object $component, string $targetClass): bool
            {
                return in_array($targetClass, $component->fakeClasses, true);
            }

            use InteractsWithTranslatableForms;
        };
    }

    private function fakeComponent(string $name, ?string $class, array $children = []): object
    {
        return new class($name, $class === null ? [] : [$class], $children)
        {
            public array $fakeClasses;

            private string $name;

            private array $children;

            public function __construct(string $name, array $fakeClasses, array $children)
            {
                $this->name = $name;
                $this->fakeClasses = $fakeClasses;
                $this->children = $children;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getChildComponents(): array
            {
                return $this->children;
            }
        };
    }

    private function translatableModel(array $attributes = []): Model
    {
        $model = new class extends Model
        {
            use HasTranslations;

            public array $translatable = ['title', 'gallery'];
        };

        $model->setRawAttributes(array_map(
            fn (array $translations): string => json_encode($translations),
            $attributes
        ));

        return $model;
    }
}
