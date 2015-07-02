<?php namespace Spatie\MediaLibrary\Traits;

use Illuminate\Support\Collection;
use Spatie\MediaLibrary\Conversion\Conversion;
use Spatie\MediaLibrary\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\Exceptions\FileTooBig;
use Spatie\MediaLibrary\FileSystem;
use Spatie\MediaLibrary\Media;
use Exception;
use Spatie\MediaLibrary\MediaLibraryFacade as MediaLibrary;
use Spatie\MediaLibrary\Repository;

trait HasMedia
{
    public $mediaConversions = [];

    public static function bootHasMedia()
    {

        self::deleting(function (HasMediaInterface $subject) {
            $subject->media()->get()->map(function (Media $media) {
                $media->delete();
            });
        });
    }

    /**
     * Set the polymorphic relation.
     *
     * @return mixed
     */
    public function media()
    {
        return $this->morphMany(Media::class, 'model');
    }

    /**
     * Add media to media collection from a given file.
     *
     * @param string $file
     * @param string $collectionName
     * @param bool   $removeOriginal
     * @param bool   $addAsTemporary
     *
     * @return Media
     *
     * @throws \Spatie\MediaLibrary\Exceptions\FileDoesNotExist
     * @throws \Spatie\MediaLibrary\Exceptions\FileTooBig
     */
    public function addMedia($file, $collectionName, $removeOriginal = true, $addAsTemporary = false)
    {
        if (! is_file($file)) {
            throw new FileDoesNotExist();
        }

        if (filesize($file) > config('laravel-medialibrary.max_file_size')) {
            throw new FileTooBig();
        }

        $media = new Media();

        $media->name = pathinfo($file, PATHINFO_FILENAME);
        $media->file_name = pathinfo($file, PATHINFO_BASENAME);

        $media->collection_name = $collectionName;

        $media->size = filesize($file);
        $media->temp = $addAsTemporary;
        $media->manipulations = ['list' => ['or' => '90']];

        $media->save();

        $this->media()->save($media);

        app(FileSystem::class)->add($file, $media);

        if ($removeOriginal) {
            unlink($file);
        }

        return $media;
    }

    /**
     * Get media collection by its collectionName.
     *
     * @param string $collectionName
     * @param array  $filters
     *
     * @return mixed
     */
    public function getMedia($collectionName, $filters = ['temp' => 0])
    {
        return app(Repository::class)->getCollection($this, $collectionName, $filters);
    }

    /**
     * Get the first media item of a media collection.
     *
     * @param $collectionName
     * @param array $filters
     *
     * @return bool|Media
     */
    public function getFirstMedia($collectionName, $filters = [])
    {
        $media = $this->getMedia($collectionName, $filters);

        return (count($media) ? $media[0] : false);
    }

    /**
     * Get the url of the image for the given conversionName
     * for first media for the given collectionName.
     * If no profile is given, return the source's url.
     *
     * @param string $collectionName
     * @param string $conversionName
     *
     * @return string
     */
    public function getFirstMediaUrl($collectionName, $conversionName = '')
    {
        $media = $this->getFirstMedia($collectionName);

        if (! $media) {
            return false;
        }

        return $media->getUrl($conversionName);
    }


    /**
     * Update a media collection by deleting and inserting again with new values.
     *
     * @param array  $newMediaArray
     * @param string $collectionName
     *
     * @throws Exception
     */
    public function updateMedia(array $newMediaArray, $collectionName)
    {
        $this->removeMediaItemsNotPresentInArray($newMediaArray, $collectionName);

        $orderCounter = 0;

        foreach ($newMediaArray as $newMediaItem) {
            $currentMedia = Media::findOrFail($newMediaItem['id']);

            if ($currentMedia->collection_name != $collectionName) {
                throw new Exception('Media id: '.$currentMedia->id.' error: Updating the wrong collection. Expected: "'.$collectionName.'" - got: "'.$currentMedia->collection_name);
            }

            if (array_key_exists('name', $newMediaItem)) {
                $currentMedia->name = $newMediaItem['name'];
            }

            $currentMedia->order_column = $orderCounter++;

            $currentMedia->temp = 0;

            $currentMedia->save();
        }
    }

    /**
     * @param array $newMediaArray
     * @param $collectionName
     *
     * @return mixed
     */
    private function removeMediaItemsNotPresentInArray(array $newMediaArray, $collectionName)
    {
        $newMediaItems = new Collection($newMediaArray);

        foreach ($this->getMedia($collectionName, []) as $currentMedia) {
            if (!in_array($currentMedia->id, Collection::make($newMediaItems->lists('id'))->toArray())) {
                $this->removeMedia($currentMedia->id);
            }
        }
    }

    /**
     * Remove all media in the given collection.
     *
     * @param $collectionName
     */
    public function clearMediaCollection($collectionName)
    {
        $this->getMedia($collectionName)->map(function(Media $media) {
            $media->delete();
        });
    }

    /**
     * Add a conversion.
     *
     * @return \Spatie\MediaLibrary\Conversion\Conversion;
     */
    public function addMediaConversion($name)
    {
        $conversion = Conversion::create($name);

        $this->mediaConversions[] = $conversion;

        return $conversion;
    }
}