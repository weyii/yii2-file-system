<?php
namespace weyii\filesystem\adapters;

use Yii;
use yii\base\InvalidConfigException;
use League\Flysystem\Adapter\Ftp;

/**
 * 又拍云文件存储
 * @package weyii\filesystem\adapters
 */
class UpYun extends Ftp
{
    /**
     * @inheritdoc
     */
    public $configurable = [
        'bucket',
        'operatorName',
        'operatorPassword',
        'endpoinet',

        'host',
        'port',
        'username',
        'password',
        'ssl',
        'timeout',
        'root',
        'permPrivate',
        'permPublic',
        'passive',
        'transferMode',
        'systemType',
        'ignorePassiveAddress',
    ];

    /**
     * @inhertdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);

        if ($this->operatorName === null || $this->username === null) {
            throw new InvalidConfigException('The "operatorName" propery must be set.');
        } elseif ($this->operatorPassword === null || $this->password === null) {
            throw new InvalidConfigException('The "operatorPassword" propery must be set.');
        } elseif ($this->bucket === null) {
            throw new InvalidConfigException('The "bucket" propery must be set.');
        }
    }

    /**
     * @var string 又拍云存储授权账号
     */
    protected $operatorName;

    /**
     * @return string
     */
    public function getOperatorName()
    {
        return $this->operatorName;
    }

    /**
     * @param $operatorName
     * @return $this
     */
    public function setOperatorName($operatorName)
    {
        $this->operatorName = $operatorName;
        $this->setUsername($operatorName . '/' . $this->getBucket());

        return $this;
    }

    /**
     * @var string 又拍云存储授权密码
     */
    protected $operatorPassword;

    /**
     * @return string
     */
    public function getOperatorPassword()
    {
        return $this->operatorPassword;
    }

    /**
     * @param $operatorPassword
     * @return $this
     */
    public function setOperatorPassword($operatorPassword)
    {
        $this->operatorPassword = $operatorPassword;
        $this->setPassword($operatorPassword);

        return $this;
    }

    /**
     * @var string 又拍云存储Bucket
     */
    protected $bucket;

    /**
     * @return string
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * @param $bucket
     * @return $this
     */
    public function setBucket($bucket)
    {
        $this->bucket = $bucket;

        return $this;
    }

    /**
     * @var string 又拍云节点
     */
    protected $endpoinet = 'v0.api.upyun.com';
    /**
     * @inheritdoc
     */
    protected $host = 'v0.api.upyun.com';

    /**
     * @return string
     */
    public function getEndpoinet()
    {
        return $this->endpoinet;
    }

    /**
     * @param $endpoinet
     * @return $this
     */
    public function setEndpoinet($endpoinet)
    {
        $this->endpoinet = $endpoinet;
        $this->setHost($endpoinet);

        return $this;
    }
}