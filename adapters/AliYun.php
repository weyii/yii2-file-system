<?php
namespace weyii\file\system\adapters;

use Yii;
use yii\base\Configurable;
use yii\base\InvalidConfigException;
use weyii\base\components\ObjectTrait;
use OSS\OssClient;
use OSS\Model\ObjectInfo;
use League\Flysystem\Util;
use League\Flysystem\Config;
use League\Flysystem\Util\MimeType;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Adapter\AbstractAdapter;

/**
 * 阿里云OSS文件存储
 * @package weyii\file\system\adapters
 */
class AliYun extends AbstractAdapter implements Configurable
{
    use ObjectTrait;

    /**
     * @var string 阿里云访问秘钥ID
     */
    public $accessKeyId;
    /**
     * @var string 阿里云访问秘钥Secret
     */
    public $accessKeySecret;
    /**
     * @var string 阿里云OSS存储Bucket
     */
    public $bucket;
    /**
     * @var string 基本访问域名
     */
    public $baseUrl;
    /**
     * @var bool 是否私有空间, 默认公开空间
     */
    public $isPrivate = false;

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        if ($this->accessKeyId === null) {
            throw new InvalidConfigException('The "accessKeyId" propery must be set.');
        } elseif ($this->accessKeySecret === null) {
            throw new InvalidConfigException('The "accessKeySecret" propery must be set.');
        } elseif ($this->bucket === null) {
            throw new InvalidConfigException('The "bucket" propery must be set.');
        } elseif ($this->baseUrl === null) {
            throw new InvalidConfigException('The "baseUrl" propery must be set.');
        }
    }

    private $_client;

    /**
     * @return \OSS\OssClient
     */
    public function getClient()
    {
        if ($this->_client === null) {
            $this->setClient(new OssClient($this->accessKeyId, $this->accessKeySecret, $this->baseUrl));
        }
        return $this->_client;
    }

    /**
     * @param \OSS\OssClient $client
     */
    public function setClient(OssClient $client)
    {
        $this->_client = $client;
    }

    /**
     * @param \OSS\Model\ObjectInfo|array $file
     * @return array
     */
    protected function normalizeData($file)
    {
        if (is_array($file)) {
            $data = [];
            foreach ($file as $k => $v) {
                $data[$k] = $this->normalizeData($v);
            }
            return $data;
        } elseif ($file instanceof ObjectInfo) {
            return [
                'type' => 'file',
                'path' => $file->getKey(),
                'size' => $file->getSize(),
                'timestamp' => strtotime($file->getLastModified())
            ];
        } else {
            return [
                'type' => 'file',
                'path' => $file['key'],
                'size' => $file['content-length'],
                'mimetype' => $file['content-type'],
                'timestamp' => strtotime($file['last-modified'])
            ];
        }
    }

    /**
     * @param $directory
     * @param null $start
     * @return array
     */
    protected function listDirContents($directory, $start = null)
    {
        $listInfo = $this->getClient()->listObjects($this->bucket, [
            'prefix' => trim($directory, '/') . '/',
            'marker' => $start
        ]);
        $start = $listInfo->getNextMarker();
        $item = $this->normalizeData($listInfo->getObjectList());
        if (!empty($start)) {
            $item = array_merge($item, $this->listDirContents($directory, $start));
        }
        return $item;
    }

    /**
     * @inheritdoc
     */
    public function has($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @inheritdoc
     */
    public function read($path)
    {
        $contents = $this->getClient()->getObject($this->bucket, $path);
        return compact('contents', 'path');
    }

    /**
     * @inheritdoc
     */
    public function readStream($path)
    {
        $url = $this->getClient()->signUrl($this->bucket, $path, 3600);
        $stream = fopen($url, 'r');
        return compact('stream', 'path');
    }

    /**
     * @inheritdoc
     */
    public function listContents($directory = '', $recursive = false)
    {
        $files = [];
        foreach($this->listDirContents($directory) as $k => $file) {
            $pathInfo = pathinfo($file['key']);
            $files[] = array_merge($pathInfo, $this->normalizeData($file), [
                'type' => isset($pathInfo['extension']) ? 'file' : 'dir',
            ]);
        }
        return $files;
    }

    /**
     * @inheritdoc
     */
    public function getMetadata($path)
    {
        try {
            $meta = $this->getClient()->getObjectMeta($this->bucket, $path);
        } catch (\Exception $e) {
            return false;
        }

        $meta['key'] = $path;
        return $this->normalizeData($meta);
    }

    /**
     * @inheritdoc
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @inheritdoc
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @inheritdoc
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @inheritdoc
     */
    public function getVisibility($path)
    {
        return [
            'visibility' => $this->isPrivate ? AdapterInterface::VISIBILITY_PRIVATE : AdapterInterface::VISIBILITY_PUBLIC
        ];
    }

    /**
     * @inheritdoc
     */
    public function write($path, $contents, Config $config)
    {
        return $this->update($path, $contents, $config);
    }

    /**
     * @inheritdoc
     */
    public function writeStream($path, $resource, Config $config)
    {
        return $this->updateStream($path, $resource, $config);
    }

    /**
     * @inheritdoc
     */
    public function update($path, $contents, Config $config)
    {
        $result = $this->getClient()->putObject($this->bucket, $path, $contents);
        if ($result !== null) {
            return false;
        }
        $mimetype = Util::guessMimeType($path, $contents);
        return compact('mimetype', 'path');
    }

    /**
     * @param $object
     * @param $uploadFile
     * @param int $size
     * @return null
     * @throws \OSS\Core\OssException
     */
    protected function streamUpload($object, $uploadFile, $size = 0)
    {
        $client = $this->getClient();
        $bucket = $this->bucket;
        $upload_position = 0;
        $upload_file_size = $size ?: Util::getStreamSize($uploadFile);
        $extension = pathinfo($object, PATHINFO_EXTENSION);
        $options = [
            OssClient::OSS_CONTENT_TYPE => MimeType::detectByFileExtension($extension) ?: OssClient::DEFAULT_CONTENT_TYPE,
            OssClient::OSS_PART_SIZE => OssClient::OSS_MID_PART_SIZE
        ];

        $is_check_md5 = false;

        $uploadId = $client->initiateMultipartUpload($bucket, $object, $options);

        // 获取的分片
        $pieces = $client->generateMultiuploadParts($upload_file_size, (integer)$options[OssClient::OSS_PART_SIZE]);
        $response_upload_part = array();
        foreach ($pieces as $i => $piece) {
            $from_pos = $upload_position + (integer)$piece[OssClient::OSS_SEEK_TO];
            $to_pos = (integer)$piece[OssClient::OSS_LENGTH] + $from_pos - 1;
            $up_options = array(
                OssClient::OSS_FILE_UPLOAD => $uploadFile,
                OssClient::OSS_PART_NUM => ($i + 1),
                OssClient::OSS_SEEK_TO => $from_pos,
                OssClient::OSS_LENGTH => $to_pos - $from_pos + 1,
                OssClient::OSS_CHECK_MD5 => $is_check_md5,
            );
            if ($is_check_md5) {
                $content_md5 = OssUtil::getMd5SumForFile($uploadFile, $from_pos, $to_pos);
                $up_options[OssClient::OSS_CONTENT_MD5] = $content_md5;
            }
            $response_upload_part[] = $client->uploadPart($bucket, $object, $uploadId, $up_options);
        }

        $uploadParts = array();
        foreach ($response_upload_part as $i => $etag) {
            $uploadParts[] = array(
                'PartNumber' => ($i + 1),
                'ETag' => $etag,
            );
        }
        return $client->completeMultipartUpload($bucket, $object, $uploadId, $uploadParts);
    }

    /**
     * @inheritdoc
     */
    public function updateStream($path, $resource, Config $config)
    {
        $size = Util::getStreamSize($resource);
        $result = $this->streamUpload($path, $resource, $size);
        if ($result !== null) {
            return false;
        }
        return compact('size', 'path');
    }

    /**
     * @inheritdoc
     */
    public function rename($path, $newpath)
    {
        $return = $this->getClient()->copyObject($this->bucket, $path, $this->bucket, $newpath) === null;
        if ($return) { // 阿里云不能移动 只能先拷贝成功后删除旧object
            $this->getClient()->deleteObject($this->bucket, $path);
        }
        return $return;
    }

    /**
     * @inheritdoc
     */
    public function copy($path, $newpath)
    {
        return $this->getClient()->copyObject($this->bucket, $path, $this->bucket, $newpath) === null;
    }

    /**
     * @inheritdoc
     */
    public function delete($path)
    {
        return $this->getClient()->deleteObject($this->bucket, $path) === null;
    }

    /**
     * @inheritdoc
     */
    public function deleteDir($dirname)
    {
        // 阿里云无目录概念. 目前实现方案是.列举指定目录资源.批量删除
        $keys = array_map(function($file) {
            return $file['path'];
        }, $this->listDirContents($dirname));
        return $this->getClient()->deleteObjects($this->bucket, $keys) === null;
    }

    /**
     * @inheritdoc
     */
    public function createDir($dirname, Config $config)
    {
        return ['path' => $dirname];
    }

    /**
     * @inheritdoc
     */
    public function setVisibility($path, $visibility)
    {
        if ($this->isPrivate) {
            return false;
        }
        return compact('visibility');
    }
}