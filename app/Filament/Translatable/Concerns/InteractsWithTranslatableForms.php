<?php

namespace App\Filament\Translatable\Concerns;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Normaliza dados de formulários para recursos com campos traduzíveis
 * (spatie/laravel-translatable + lara-zeus/spatie-translatable).
 *
 * Resolve três problemas conhecidos dessa integração:
 *  1. FileUpload em campo translatable: o spatie guarda string, o form espera array.
 *  2. KeyValue/Repeaters geram chaves UUID que sujam o JSON traduzido.
 *  3. Fallback de locale: preenche valores vazios com o locale padrão apenas
 *     para escalares — nunca recursivo em arrays (que corrompe dados aninhados).
 *
 * Os pipelines têm nomes próprios de propósito: a página chama
 * `translatableFill()`/`translatableSave()` dentro dos seus hooks
 * (`mutateFormDataBeforeFill`/`mutateFormDataBeforeSave`), mantendo a
 * composição livre de colisão com as concerns do lara-zeus.
 */
trait InteractsWithTranslatableForms
{
    /**
     * Pipeline de fill: envolve valores únicos de FileUpload em array e
     * aplica fallback seguro do locale padrão.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function translatableFill(array $data, Model $record, ?string $locale = null): array
    {
        $data = $this->translatableWrapFileUploadScalars($data, '', $this->translatableFileUploadPaths());

        if ($locale !== null) {
            $data = $this->translatableApplyLocaleFallback($data, $record, $locale);
        }

        return $data;
    }

    /**
     * Pipeline de save: achata arrays de arquivo de elemento único e
     * reindexa arrays chaveados por UUID (preservando campos KeyValue).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function translatableSave(array $data): array
    {
        $keyValuePaths = $this->translatableKeyValuePaths();

        $data = $this->translatableFlattenFileUploads($data, '', $this->translatableFileUploadPaths());
        $data = $this->translatableCleanUuidKeyedArrays($data, '', $keyValuePaths);

        return $data;
    }

    /**
     * Fallback seguro: para atributos traduzíveis com valor vazio no locale
     * ativo, usa o valor do locale padrão — só quando o valor for escalar.
     * Arrays nunca são copiados nem mesclados.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function translatableApplyLocaleFallback(array $data, Model $record, string $locale): array
    {
        if (! method_exists($record, 'getTranslatableAttributes') || ! method_exists($record, 'getTranslations')) {
            return $data;
        }

        $defaultLocale = (string) config('app.fallback_locale', 'en');

        foreach ($record->getTranslatableAttributes() as $attribute) {
            $value = $data[$attribute] ?? null;

            if ($value !== null && $value !== '') {
                continue;
            }

            $default = $record->getTranslations($attribute)[$defaultLocale] ?? null;

            if (is_scalar($default) && trim((string) $default) !== '') {
                $data[$attribute] = $default;
            }
        }

        return $data;
    }

    /**
     * Envolve escalares em array nos caminhos de FileUpload (qualquer profundidade).
     *
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $uploadPaths
     * @return array<array-key, mixed>
     */
    private function translatableWrapFileUploadScalars(array $data, string $currentPath, array $uploadPaths): array
    {
        foreach ($data as $key => $value) {
            $path = $currentPath === '' ? (string) $key : "{$currentPath}.{$key}";

            if (! is_array($value)) {
                if (in_array($path, $uploadPaths, true)) {
                    $data[$key] = [$value];
                }

                continue;
            }

            $data[$key] = $this->translatableWrapFileUploadScalars($value, $path, $uploadPaths);
        }

        return $data;
    }

    /**
     * Achata arrays de arquivo de elemento único nos caminhos de FileUpload
     * (qualquer profundidade). No caminho de um upload, devolve o valor
     * escalar (string do arquivo); nos demais, devolve o array recebido.
     *
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $uploadPaths
     */
    private function translatableFlattenFileUploads(array $data, string $currentPath, array $uploadPaths): mixed
    {
        foreach ($uploadPaths as $uploadPath) {
            if ($currentPath === $uploadPath && count($data) === 1 && array_key_exists(0, $data)) {
                return $data[0];
            }
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $path = $currentPath === '' ? (string) $key : "{$currentPath}.{$key}";
                $data[$key] = $this->translatableFlattenFileUploads($value, $path, $uploadPaths);
            }
        }

        return $data;
    }

    /**
     * Reindexa arrays cujo conjunto inteiro de chaves é UUID, recursivamente.
     * Subárvores de campos KeyValue são preservadas intactas.
     *
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $skipPaths
     * @return array<array-key, mixed>
     */
    private function translatableCleanUuidKeyedArrays(array $data, string $currentPath, array $skipPaths): array
    {
        if (in_array($currentPath, $skipPaths, true)) {
            return $data;
        }

        $keys = array_keys($data);

        if ($keys !== [] && $this->translatableKeysAreUuids($keys)) {
            return array_map(
                fn ($item): mixed => is_array($item)
                    ? $this->translatableCleanUuidKeyedArrays($item, $currentPath, $skipPaths)
                    : $item,
                array_values($data)
            );
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $path = $currentPath === '' ? (string) $key : "{$currentPath}.{$key}";
                $data[$key] = $this->translatableCleanUuidKeyedArrays($value, $path, $skipPaths);
            }
        }

        return $data;
    }

    /**
     * @param  list<int|string>  $keys
     */
    private function translatableKeysAreUuids(array $keys): bool
    {
        foreach ($keys as $key) {
            if (! is_string($key) || ! Str::isUuid($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Caminhos (dot notation) de todos os campos FileUpload do form da página.
     *
     * @return list<string>
     */
    protected function translatableFileUploadPaths(): array
    {
        $components = isset($this->form) && $this->form !== null
            ? $this->form->getComponents()
            : [];

        return $this->translatableCollectComponentPaths($components, '', FileUpload::class);
    }

    /**
     * Caminhos (dot notation) de todos os campos KeyValue do form da página.
     *
     * @return list<string>
     */
    protected function translatableKeyValuePaths(): array
    {
        $components = isset($this->form) && $this->form !== null
            ? $this->form->getComponents()
            : [];

        return $this->translatableCollectComponentPaths($components, '', KeyValue::class);
    }

    /**
     * @param  array<array-key, mixed>  $components
     * @return list<string>
     */
    private function translatableCollectComponentPaths(array $components, string $parentPath, string $targetClass): array
    {
        $paths = [];

        foreach ($components as $component) {
            if (! is_object($component) || ! method_exists($component, 'getName')) {
                continue;
            }

            $name = $component->getName();
            $currentPath = $name !== null && $name !== ''
                ? ($parentPath === '' ? $name : "{$parentPath}.{$name}")
                : $parentPath;

            if ($currentPath !== '' && $this->translatableComponentIs($component, $targetClass)) {
                $paths[] = $currentPath;
            }

            if ($currentPath !== '' && method_exists($component, 'getChildComponents')) {
                $paths = array_merge(
                    $paths,
                    $this->translatableCollectComponentPaths($component->getChildComponents(), $currentPath, $targetClass)
                );
            }
        }

        return $paths;
    }

    /**
     * Ponto de extensão para testes: fakes de componente não passam no
     * `instanceof` real.
     */
    protected function translatableComponentIs(object $component, string $targetClass): bool
    {
        return $component instanceof $targetClass;
    }
}
