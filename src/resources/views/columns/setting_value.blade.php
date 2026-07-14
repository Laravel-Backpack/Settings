{{--
    Per-row column dispatcher. Resolution order for the value cell:
      1. Explicit `column` JSON on the row (always honored).
      2. Resolved from `field` JSON if `backpack.settings.auto_resolve_columns` is true.
      3. Plain text column (legacy default).
--}}
@php
    $rowColumn = null;

    $explicit = $entry->getAttribute('column');
    if (! empty($explicit)) {
        $decoded = is_array($explicit) ? $explicit : json_decode($explicit, true);
        if (is_array($decoded) && ! empty($decoded)) {
            $rowColumn = $decoded;
        }
    }

    if ($rowColumn === null && config('backpack.settings.auto_resolve_columns', false)) {
        $resolver = app(\Backpack\Settings\app\Library\FieldToColumnResolver::class);
        $field = json_decode($entry->field ?? '{}', true);
        $rowColumn = $resolver->resolve(is_array($field) ? $field : []);
    }

    $rowColumn = $rowColumn ?: ['name' => 'value', 'type' => 'text'];
    $rowColumn['name'] = 'value';

    foreach (['wrapper', 'escaped', 'prefix', 'suffix'] as $k) {
        if (! array_key_exists($k, $rowColumn) && array_key_exists($k, $column)) {
            $rowColumn[$k] = $column[$k];
        }
    }

    $resolvedType = $rowColumn['type'] ?? 'text';
    $resolvedView = 'crud::columns.'.$resolvedType;

    if (! view()->exists($resolvedView)) {
        $resolvedView = 'crud::columns.text';
    }

    $entry = \Backpack\Settings\app\Library\UploaderColumnHydrator::hydrate($entry, $rowColumn);

    // Some column views (e.g. custom_html) don't fall back to data_get($entry, name).
    if (! array_key_exists('value', $rowColumn)) {
        $rowColumn['value'] = data_get($entry, $rowColumn['name']);
    }

    $originalColumn = $column;
    $column = $rowColumn;
@endphp

@include($resolvedView, ['column' => $rowColumn, 'entry' => $entry, 'crud' => $crud])

@php
    $column = $originalColumn;
@endphp
