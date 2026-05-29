{{-- 
    Settings package: per-row column dispatcher.

    Each setting row in the list defines its own value-cell rendering.
    Resolution order:
      1) If the row has a non-empty `column` JSON, use it verbatim.
         (Always honored, regardless of the auto_resolve_columns flag.)
      2) If `backpack.settings.auto_resolve_columns` is true, derive a
         column definition from the row's `field` JSON via the
         FieldToColumnResolver and the field_to_column_map config.
      3) Otherwise, fall back to a plain text column (legacy behavior).
--}}
@php
    $rowColumn = null;

    // 1) explicit column definition on the row (always honored)
    $explicit = $entry->getAttribute('column');
    if (! empty($explicit)) {
        $decoded = is_array($explicit) ? $explicit : json_decode($explicit, true);
        if (is_array($decoded) && ! empty($decoded)) {
            $rowColumn = $decoded;
        }
    }

    // 2) derive from field definition (opt-in via config)
    if ($rowColumn === null && config('backpack.settings.auto_resolve_columns', false)) {
        $resolver = app(\Backpack\Settings\app\Library\FieldToColumnResolver::class);
        $field = json_decode($entry->field ?? '{}', true);
        $rowColumn = $resolver->resolve(is_array($field) ? $field : []);
    }

    // 3) safety net / legacy default
    $rowColumn = $rowColumn ?: ['name' => 'value', 'type' => 'text'];

    // force the column to point at the `value` attribute regardless of what
    // was stored, since that's where the setting's value actually lives.
    $rowColumn['name'] = 'value';

    // copy display hints from the outer column so the developer can still
    // tweak things like wrapper at the controller level if needed.
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

    // If this row's column is an uploader-managed upload/image and carries
    // a `withFiles` / `withMedia` definition, the raw `$entry->value` is
    // still a stored path / json / media id. Resolve it to a URL per-row
    // (we can't use the normal `Model::retrieved` event hookup because
    // every Setting row shares the same model and `value` attribute, so
    // a global event would let the last uploader win for every row).
    $entry = \Backpack\Settings\app\Library\UploaderColumnHydrator::hydrate($entry, $rowColumn);

    // expose the resolved column under the variable the built-in views expect
    $originalColumn = $column;
    $column = $rowColumn;
@endphp

@include($resolvedView, ['column' => $rowColumn, 'entry' => $entry, 'crud' => $crud])

@php
    // restore in case Backpack reuses $column further down the row
    $column = $originalColumn;
@endphp
