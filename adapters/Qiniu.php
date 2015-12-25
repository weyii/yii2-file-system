<?php
namespace weyii\filesystem\adapters;

use Yii;
use yii\base\Configurable;
use yii\base\InvalidConfigException;
use weyii\base\traits\ObjectTrait;
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;
use Qiniu\Storage\ResumeUploader;
use League\Flysystem\Util;
use League\Flysystem\Config;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Adapter\AbstractAdapter;

/**
 * 七牛文件存储
 * @package weyii\filesystem\adapters
 */
class Qiiu extends AbstractAdapter implements Configurable
{
    use ObjectTrait;

    /**
     * @var string 七牛访问秘钥Key
     */
    public $accessKey;
    /**
     * @var string 七牛访问秘钥Secret
     */
    public $accessSecret;
    /**
     * @var string 七牛存储Bucket
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
        if ($this->accessKey === null) {
            throw new InvalidConfigException('The "accessKey" propery must be set.');
        } elseif ($this->accessSecret === null) {
            throw new InvalidConfigException('The "accessSecret" propery must be set.');
        } elseif ($this->bucket === null) {
            throw new InvalidConfigException('The "bucket" propery must be set.');
        } elseif ($this->baseUrl === null) {
            throw new InvalidConfigException('The "baseUrl" propery must be set.');
        }
    }

    private $_auth;

    /**
     * @return \Qiniu\Auth
     */
    public function getAuth()
    {
        if ($this->_auth === null) {
            $this->setAuth(new Auth($this->accessKey, $this->accessSecret));
        }
        return $this->_auth;
    }

    /**
     * @param \Qiniu\Auth $auth
     */
    public function setAuth(Auth $auth)
    {
        $this->_auth = $auth;
    }

    private $_bucketManager;

    /**
     * @return \Qiniu\Storage\BucketManager
     */
    public function getBucketManager()
    {
        if ($this->_bucketManager === null) {
            $this->setBucketManager(new BucketManager($this->getAuth()));
        }
        return $this->_bucketManager;
    }

    /**
     * @param \Qiniu\Storage\BucketManager $bucketManager
     */
    public function setBucketManager(BucketManager $bucketManager)
    {
        $this->_bucketManager = $bucketManager;
    }

    private $_uploadManager;

    /**
     * @return \Qiniu\Storage\UploadManager
     */
    public function getUploadManager()
    {
        if ($this->_uploadManager === null) {
            $this->setUploadManager(new UploadManager());
        }
        return $this->_uploadManager;
    }

    /**
     * @param \Qiniu\Storage\UploadManager $uploadManager
     */
    public function setUploadManager(UploadManager $uploadManager)
    {
        $this->_uploadManager = $uploadManager;
    }

    /**
     * @param null $key
     * @param int $expires
     * @param null $policy
     * @param bool|true $strictPolicy
     * @return string
     */
    public function getUploadToken($key = null, $expires = 3600, $policy = null, $strictPolicy = true)
    {
        return $this->getAuth()->uploadToken(
            $this->bucket,
            $key,
            $expires,
            $policy,
            $strictPolicy
        );
    }

    /**
     * @param $path
     * @return string
     */
    public function getUrl($path)
    {
        $keyEsc = str_replace("%2F", "/", rawurlencode($path));
        return $this->baseUrl . '/' . $keyEsc;
    }

    /**
     * @param array $file
     * @return array
     */
    protected function normalizeData(array $file)
    {
        return [
            'type' => 'file',
            'path' => $file['key'],
            'size' => $file['fsize'],
            'mimetype' => $file['mimeType'],
            'timestamp' => (int)($file['putTime'] / 10000000) //Epoch 时间戳
        ];
    }

    /**
     * @param $directory
     * @param null $start
     * @return array
     */
    protected function listDirContents($directory, $start = null)
    {
        list($item, $start, $err) = $this->getBucketManager()->listFiles($this->bucket, $directory, $start);
        if ($err !== null) {
            return [];
        } elseif (!empty($start)) {
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
        $contents = stream_get_contents($this->readStream($path)['stream']);
        return compact('contents', 'path');
    }

    /**
     * @inheritdoc
     */
    public function readStream($path)
    {
        $url = $this->getAuth()->privateDownloadUrl($this->getUrl($path));
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
        list($ret, $err) = $this->getBucketManager()->stat($this->bucket, $path);
        if ($err !== null) {
            return false;
        }
        $ret['key'] = $path;
        return $this->normalizeData($ret);
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
        list(, $err) = $this->getUploadManager()->put($this->getUploadToken(), $path, $contents);
        if ($err !== null) {
            return false;
        }
        $mimetype = Util::guessMimeType($path, $contents);
        return compact('mimetype', 'path');
    }

    /**
     * @inheritdoc
     */
    public function updateStream($path, $resource, Config $config)
    {
        $size = Util::getStreamSize($resource);
        $resumeUploader = new ResumeUploader(
            $this->getUploadToken(),
            $path,
            $resource,
            $size,
            null,
            'application/octet-stream'
        );
        list(, $err) = $resumeUploader->upload();
        if ($err !== null) {
            return false;
        }
        return compact('size', 'path');
    }

    /**
     * @inheritdoc
     */
    public function rename($path, $newpath)
    {
        list(, $err) = $this->getBucketManager()->rename($this->bucket, $path, $newpath);
        return $err === null;
    }

    /**
     * @inheritdoc
     */
    public function copy($path, $newpath)
    {
        list(, $err) = $this->getBucketManager()->move($this->bucket, $path, $this->bucket, $newpath);
        return $err === null;
    }

    /**
     * @inheritdoc
     */
    public function delete($path)
    {
        list(, $err) = $this->getBucketManager()->delete($this->bucket, $path);
        return $err === null;
    }

    /**
     * @inheritdoc
     */
    public function deleteDir($dirname)
    {
        // 七牛无目录概念. 目前实现方案是.列举指定目录资源.批量删除
        $keys = array_map(function($file) {
            return $file['key'];
        }, $this->listDirContents($dirname));
        list(, $err) = $this->getBucketManager()->batch(BucketManager::buildBatchDelete($keys));
        return $err === null;
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