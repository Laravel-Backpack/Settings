<?php

namespace Backpack\Settings\app\Library;

use Illuminate\Database\Eloquent\Model;

/**
 * Hydrates uploader-managed values on a Setting row before its upload column renders.
 *
 * The normal Backpack flow uses a `Model::retrieved` event registered by the
 * `withFiles` / `withMedia` macro. We can't use it: all Setting rows share the same
 * model and `value` attribute, so a global event would let the last uploader win.
 * Instead we run the uploader inline, per row, on this $entry only.
 */
class UploaderColumnHydrator
{
    protected const UPLOAD_COLUMN_TYPES = ['upload', 'upload_multiple', 'image'];

    public static function hydrate(Model $entry, array &$column): Model
    {
        if (! in_array($column['type'] ?? null, self::UPLOAD_COLUMN_TYPES, true)) {
            return $entry;
        }

        $macro = isset($column['withFiles']) ? 'withFiles'
               : (isset($column['withMedia']) ? 'withMedia' : null);

        if ($macro === null || ! app()->bound('UploadersRepository')) {
            return $entry;
        }

        $uploadDefinition = is_array($column[$macro]) ? $column[$macro] : [];
        $uploaderClass    = $uploadDefinition['uploader'] ?? null;

        if ($uploaderClass === null) {
            $repository = app('UploadersRepository');

            if (! $repository->hasUploadFor($column['type'], $macro)) {
                return $entry;
            }

            $uploaderClass = $repository->getUploadFor($column['type'], $macro);
        }

        $uploader = $uploaderClass::for($column, $uploadDefinition);

        // Mirror RegisterUploadEvents::setupUploadConfigsInCrudObject() — the upload
        // column view reads these directly off the column array.
        if (method_exists($uploader, 'getDisk')) {
            $column['disk'] = $uploader->getDisk();
        }
        if (method_exists($uploader, 'getPath')) {
            $column['prefix'] = $uploader->getPath();
        }
        if (method_exists($uploader, 'useTemporaryUrl') && $uploader->useTemporaryUrl()) {
            $column['temporary']  = true;
            $column['expiration'] = $uploader->getExpirationTimeInMinutes();
        }

        return $uploader->retrieveUploadedFiles($entry);
    }
}
