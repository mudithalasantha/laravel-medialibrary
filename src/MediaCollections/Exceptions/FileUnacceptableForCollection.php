<?php

namespace Spatie\MediaLibrary\MediaCollections\Exceptions;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\File;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;

class FileUnacceptableForCollection extends FileCannotBeAdded
{
    /**
     * @param  File $file
     * @param  MediaCollection $mediaCollection
     * @param  HasMedia|null $hasMedia
     * @return FileUnacceptableForCollection
     */
    public static function create(File $file, MediaCollection $mediaCollection, $hasMedia = null)
    {
        $message = "The file with properties `{$file}` was not accepted into the collection named `{$mediaCollection->name}`";

        if ($hasMedia) {
            $modelType = get_class($hasMedia);
            $message .= " of model `{$modelType}` with id `{$hasMedia->getKey()}`";
        }

        return new static($message);
    }
}
