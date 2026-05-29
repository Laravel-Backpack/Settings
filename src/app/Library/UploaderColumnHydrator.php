<?php

namespace Backpack\Settings\app\Library;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolves stored upload values into displayable form for a single Setting
 * row, just before the list-view column is rendered.
 *
 * Why this exists: the normal Backpack upload pipeline relies on
 * `CrudColumn::withFiles([...])` (or `->withMedia([...])`) being called
 * inside `setupListOperation()`. That macro registers a `Model::retrieved`
 * event that runs the configured uploader's `retrieveUploadedFiles()` to
 * transform raw stored data (a path, a JSON array of paths, a media id,
 * ...) into the URL the upload column view actually renders.
 *
 * In Settings we can't use that mechanism: every row is the SAME `Setting`
 * model with the SAME column name (`value`), but each row may have a
 * DIFFERENT uploader configuration. Registering one global retrieved event
 * per row would make every uploader run against every row's value and the
 * last one would win.
 *
 * So instead of registering events, we resolve the value per-row, on
 * demand, against just the current entry — same call the event would make
 * (`$uploader->retrieveUploadedFiles($entry)`), but scoped to one row.
 */
class UploaderColumnHydrator
{
    /**
     * Column types this hydrator is allowed to act on.
     */
    protected const UPLOAD_COLUMN_TYPES = ['upload', 'upload_multiple', 'image'];

    /**
     * Resolve any uploader-managed value on $entry->{column['name']}
     * in-place so the column view can render the URL(s).
     *
     * Safe no-op when:
     *  - the column isn't an upload column type, or
     *  - the column has no `withFiles` / `withMedia` definition, or
     *  - the UploadersRepository / requested uploader isn't available
     *    (e.g. medialibrary-uploaders not installed).
     */
    public static function hydrate(Model $entry, array $column): Model
    {
        if (! in_array($column['type'] ?? null, self::UPLOAD_COLUMN_TYPES, true)) {
            return $entry;
        }

        // Find which uploader macro applies. Order matters: only one wins.
        $macro = isset($column['withFiles']) ? 'withFiles'
               : (isset($column['withMedia']) ? 'withMedia' : null);

        if ($macro === null) {
            return $entry;
        }

        // UploadersRepository is registered by backpack/crud's service
        // provider. Bail out cleanly if the host app doesn't have it.
        if (! app()->bound('UploadersRepository')) {
            return $entry;
        }

        $uploadDefinition = is_array($column[$macro]) ? $column[$macro] : [];
        $uploaderClass    = $uploadDefinition['uploader'] ?? null;

        // If no custom uploader was provided, look up the default for this
        // column type + macro pair (e.g. ['upload' => SingleFile::class]).
        if ($uploaderClass === null) {
            $repository = app('UploadersRepository');

            if (! $repository->hasUploadFor($column['type'], $macro)) {
                return $entry;
            }

            $uploaderClass = $repository->getUploadFor($column['type'], $macro);
        }

        // Build the uploader the same way RegisterUploadEvents does, but
        // never register model events — we run it inline on this $entry.
        $uploader = $uploaderClass::for($column, $uploadDefinition);

        return $uploader->retrieveUploadedFiles($entry);
    }
}
