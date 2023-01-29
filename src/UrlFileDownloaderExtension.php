<?php namespace Visiosoft\UrlFileDownloaderExtension;

use Anomaly\FilesModule\File\Contract\FileRepositoryInterface;
use Anomaly\FilesModule\File\FileSanitizer;
use Anomaly\FilesModule\Folder\Contract\FolderInterface;
use Anomaly\FilesModule\Folder\Contract\FolderRepositoryInterface;
use Anomaly\Streams\Platform\Addon\Extension\Extension;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use League\Flysystem\MountManager;

class UrlFileDownloaderExtension extends Extension
{

    public function saveURL($url, FolderInterface $folder = null)
    {
        $file = $this->pathToUploadedFile($url);

        $folders = app(FolderRepositoryInterface::class);

        if (!$folder) {
            $folder = $folders->findBySlug('images');
        }

        $disk = $folder->getDisk();

        $entry = app(MountManager::class)->put(
            $disk->getSlug() . '://' . $folder->getSlug() . '/' . FileSanitizer::clean($file->getClientOriginalName()),
            file_get_contents($file->getPathname())
        );

        /**
         * Generate and store extra details about image files.
         */
        if (in_array($entry->getExtension(), config('anomaly.module.files::mimes.types.image'))) {

            $size       = filesize($file->getRealPath());
            $dimensions = null;
            $mimeType   = $file->getClientMimeType();


            app(FileRepositoryInterface::class)->save(
                $entry
                    ->setAttribute('size', $size)
                    ->setAttribute('width', isset($dimensions[0]) ? $dimensions[0] : null)
                    ->setAttribute('height', isset($dimensions[1]) ? $dimensions[1] : null)
                    ->setAttribute('mime_type', $mimeType)
            );
        }

        return $entry;
    }

    public static function pathToUploadedFile($path)
    {
        $name = File::name($path);

        $extension = File::extension($path);

        $originalName = $name . '.' . $extension;

        $mimeType = \GuzzleHttp\Psr7\MimeType::fromFilename($path);

        $object = new UploadedFile($path, $originalName, $mimeType, null, false);

        return $object;
    }
}
