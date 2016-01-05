<?php
namespace weyii\filesystem\adapters;

use Yii;
use yii\base\Configurable;
use yii\base\InvalidConfigException;
use weyii\base\traits\ObjectTrait;
use OSS\OssClient;
use OSS\Model\ObjectInfo;
use League\Flysystem\Util;
use League\Flysystem\Config;
use League\Flysystem\Util\MimeType;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Adapter\AbstractAdapter;

/**
 * 阿里云OSS文件存储
 * @package weyii\filesystem\adapters
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
     * 适合服务器和OSS都在阿里云上,速度快, 并且传输可以不计费, 但是需保证都在一个地区机房内,否则内容会访问失败
     * @link https://help.aliyun.com/document_detail/oss/user_guide/oss_concept/endpoint.html?spm=5176.2020520105.0.0.DoO2aF
     * @var string 内网域名
     */
    public $lanDomain;
    /**
     * @var string 外网域名, 默认为杭州外网域名
     */
    public $wanDomain = 'oss-cn-hangzhou.aliyuncs.com';
    /**
     * 从lanDomain和wanDomain中选取, lanDomain的优先级高于wanDomain
     * @var string 最终操作域名
     */
    protected $baseUrl;
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
            throw new InvalidConfigException('The "accessKeyId" property must be set.');
        } elseif ($this->accessKeySecret === null) {
            throw new InvalidConfigException('The "accessKeySecret" property must be set.');
        } elseif ($this->bucket === null) {
            throw new InvalidConfigException('The "bucket" property must be set.');
        }

        if ($this->lanDomain !== null) {
            $this->baseUrl = $this->lanDomain;
        } elseif ($this->wanDomain !== null) {
            $this->baseUrl = $this->wanDomain;
        } else {
            throw new InvalidConfigException('The "lanDomain" or "wanDomain" property must be set.');
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
     * @param $object
     * @param $uploadFile
     * @param int $size
     * @return null
     * @throws \OSS\Core\OssException
     */
    protected function streamUpload($object, $uploadFile, $size = null)
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
     * @param \OSS\Model\ObjectInfo|array $file
     * @return array
     */
    protected function normalizeData($file)
    {
        if (is_array($file) && isset($file[0])) { // listObject
            $data = [];
            foreach ($file as $k => $v) {
                $data[$k] = $this->normalizeData($v);
            }
            return $data;
        } elseif ($file instanceof ObjectInfo) { // listObject -> ObjectInfo
            $key = $file->getKey();
            return [
                'type' => substr($key, -1) == '/' ? 'dir' : 'file',
                'path' => $key,
                'size' => $file->getSize(),
                'timestamp' => strtotime($file->getLastModified())
            ];
        } else { // Metadata
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
            'prefix' => $directory ? trim($directory, '/') . '/' : $directory,
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
        return $this->getClient()->doesObjectExist($this->bucket, $path);
    }

    /**
     * @inheritdoc
     */
    public function read($path)
    {
        if (!($resource = $this->readStream($path))) {
            return false;
        }
        $resource['contents'] = stream_get_contents($resource['stream']);
        fclose($resource['stream']);
        unset($resource['stream']);
        return $resource;
    }

    /**
     * @inheritdoc
     */
    public function readStream($path)
    {
        $url = $this->getClient()->signUrl($this->bucket, $path, 3600);
        $stream = fopen($url, 'r');
        if (!$stream) {
            return false;
        }
        return compact('stream', 'path');
    }

    /**
     * @inheritdoc
     */
    public function listContents($directory = '', $recursive = false)
    {
        return $this->listDirContents($directory);
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
        $result = $this->getClient()->putObject($this->bucket, $path, $contents);
        if ($result !== null) {
            return false;
        }
        $mimetype = Util::guessMimeType($path, $contents);
        return compact('mimetype', 'path');
    }

    /**
     * @inheritdoc
     */
    public function writeStream($path, $resource, Config $config)
    {
        $size = Util::getStreamSize($resource);
        list(, $err) = $this->streamUpload($path, $resource, $size);
        if ($err !== null) {
            return false;
        }
        return compact('size', 'path');
    }

    /**
     * @inheritdoc
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * @inheritdoc
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->writeStream($path, $resource, $config);
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
        $result = $this->getClient()->createObjectDir($this->bucket, rtrim($dirname, '/'));
        if ($result !== null) {
            return false;
        }
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