<?php
namespace weyii\filesystem;

use Yii;
use yii\base\InvalidParamException;
use League\Flysystem\AdapterInterface;
use weyii\base\helpers\Collection;
use weyii\base\traits\ClassTrait;

/**
 * Class BaseFilesystem
 * @package weyii\filesystem
 */
class Filesystem extends \League\Flysystem\Filesystem implements FilesystemInterface
{
    use ClassTrait;

    /**
     * Write the contents of a file with file path.
     *
     * @param string $path
     * @param string $filePath
     * @param array $config
     * @return mixed
     */
    public function putFile($path, $filePath, array $config = [])
    {
        $resource = fopen(Yii::getAlias($filePath), 'r');
        $result = parent::putStream($path, $resource, $config);
        fclose($resource);
        return $result;
    }

    /**
     * Get the visibility for the given path.
     *
     * @param  string  $path
     * @return string
     */
    public function getVisibility($path)
    {
        if (parent::getVisibility($path) == AdapterInterface::VISIBILITY_PUBLIC) {
            return FilesystemInterface::VISIBILITY_PUBLIC;
        }

        return FilesystemInterface::VISIBILITY_PRIVATE;
    }

    /**
     * Set the visibility for the given path.
     *
     * @param  string  $path
     * @param  string  $visibility
     * @return void
     */
    public function setVisibility($path, $visibility)
    {
        return parent::setVisibility($path, $this->parseVisibility($visibility));
    }

    /**
     * Prepend to a file.
     *
     * @param  string  $path
     * @param  string  $data
     * @return int
     */
    public function prepend($path, $data)
    {
        if ($this->has($path)) {
            return $this->put($path, $data . PHP_EOL . $this->get($path));
        }

        return $this->put($path, $data);
    }

    /**
     * Append to a file.
     *
     * @param  string  $path
     * @param  string  $data
     * @return int
     */
    public function append($path, $data)
    {
        if ($this->has($path)) {
            return $this->put($path, $this->get($path) . PHP_EOL . $data);
        }

        return $this->put($path, $data);
    }

    /**
     * Delete the file at a given path.
     *
     * @param  string|array  $paths
     * @return bool
     */
    public function delete($paths)
    {
        $paths = is_array($paths) ? $paths : func_get_args();

        foreach ($paths as $path) {
            parent::delete($path);
        }

        return true;
    }

    /**
     * Move a file to a new location.
     *
     * @param  string  $from
     * @param  string  $to
     * @return bool
     */
    public function move($from, $to)
    {
        return parent::rename($from, $to);
    }

    /**
     * Get the file size of a given file.
     *
     * @param  string  $path
     * @return int
     */
    public function size($path)
    {
        return parent::getSize($path);
    }

    /**
     * Get the mime-type of a given file.
     *
     * @param  string  $path
     * @return string|false
     */
    public function mimeType($path)
    {
        return parent::getMimetype($path);
    }

    /**
     * Get the file's last modification time.
     *
     * @param  string  $path
     * @return int
     */
    public function lastModified($path)
    {
        return parent::getTimestamp($path);
    }

    /**
     * Get an array of all files in a directory.
     *
     * @param  string|null  $directory
     * @param  bool  $recursive
     * @return array
     */
    public function files($directory = null, $recursive = false)
    {
        $contents = parent::listContents($directory, $recursive);

        return $this->filterContentsByType($contents, 'file');
    }

    /**
     * Get all of the files from the given directory (recursive).
     *
     * @param  string|null  $directory
     * @return array
     */
    public function allFiles($directory = null)
    {
        return $this->files($directory, true);
    }

    /**
     * Get all of the directories within a given directory.
     *
     * @param  string|null  $directory
     * @param  bool  $recursive
     * @return array
     */
    public function directories($directory = null, $recursive = false)
    {
        $contents = parent::listContents($directory, $recursive);

        return $this->filterContentsByType($contents, 'dir');
    }

    /**
     * Get all (recursive) of the directories within a given directory.
     *
     * @param  string|null  $directory
     * @return array
     */
    public function allDirectories($directory = null)
    {
        return $this->directories($directory, true);
    }

    /**
     * Filter directory contents by type.
     *
     * @param  array  $contents
     * @param  string  $type
     * @return array
     */
    protected function filterContentsByType($contents, $type)
    {
        return Collection::make($contents)
            ->where('type', $type)
            ->pluck('path')
            ->values()
            ->all();
    }

    /**
     * Parse the given visibility value.
     *
     * @param  string|null  $visibility
     * @return string|null
     * @throws \yii\web\InvalidParamException
     */
    protected function parseVisibility($visibility)
    {
        if (is_null($visibility)) {
            return;
        }

        switch ($visibility) {
            case FilesystemInterface::VISIBILITY_PUBLIC:
                return AdapterInterface::VISIBILITY_PUBLIC;

            case FilesystemInterface::VISIBILITY_PRIVATE:
                return AdapterInterface::VISIBILITY_PRIVATE;
        }

        throw new InvalidParamException('Unknown visibility: ' . $visibility);
    }
}