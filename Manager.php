<?php
namespace weyii\filesystem;

use Closure;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use League\Flysystem\AdapterInterface;

/**
 * Class Manager
 * @package weyii\filesystem
 */
class Manager extends Component
{
    /**
     * @var string 默认Disk ID
     */
    public $default;
    /**
     * @var string 预定义Disk集合
     */
    private $_definitions = [];
    /**
     * @var array 实例化Disk集合
     */
    private $_disks = [];

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        if ($this->default === null) {
            throw new InvalidConfigException('The "default" property must be set.');
        }
    }

    /**
     * 获取Disk集合
     *
     * @param bool|true $returnDefinitions
     * @return array
     */
    public function getDisks($returnDefinitions = true)
    {
        return $returnDefinitions ? $this->_definitions : $this->_disks;
    }

    /**
     * 设置Disk集合
     *
     * @param array $disks
     */
    public function setDisks(array $disks)
    {
        foreach ($disks as $id => $component) {
            $this->setDisk($id, $component);
        }
    }

    /**
     * 获取Disk
     *
     * @param string|null $id 为空则取默认的Disk ID
     * @param bool|true $throwException
     * @return null|\weyii\filesystem\FilesystemInterface
     * @throws \yii\base\InvalidConfigException
     */
    public function getDisk($id = null, $throwException = true)
    {
        if ($id === null) {
            $id = $this->default;
        }

        if (isset($this->_disks[$id])) {
            return $this->_disks[$id];
        }

        if (isset($this->_definitions[$id])) {
            $definition = $this->_definitions[$id];
            if (is_object($definition) && !$definition instanceof Closure) {
                $adapter = $definition;
            } else {
                $adapter = Yii::createObject($definition);
            }
            return $this->_disks[$id] = $this->createFilesystem($adapter);
        } elseif ($throwException) {
            throw new InvalidConfigException("Unknown disk ID: $id");
        } else {
            return null;
        }
    }

    /**
     * 设置Disk
     *
     * @param $id
     * @param $definition
     * @throws \yii\base\InvalidConfigException
     */
    public function setDisk($id, $definition)
    {
        if ($definition === null) {
            unset($this->_disks[$id], $this->_definitions[$id]);
            return;
        }

        unset($this->_disks[$id]);

        if (is_object($definition) || is_callable($definition, true)) {
            // an object, a class name, or a PHP callable
            $this->_definitions[$id] = $definition;
        } elseif (is_array($definition)) {
            // a configuration array
            if (isset($definition['class'])) {
                $this->_definitions[$id] = $definition;
            } else {
                throw new InvalidConfigException("The disk configuration for the \"$id\" component must contain a \"class\" element.");
            }
        } else {
            throw new InvalidConfigException("Unexpected configuration type for the \"$id\" component: " . gettype($definition));
        }
    }

    /**
     * 创建文件系统驱动
     *
     * @param \League\Flysystem\AdapterInterface $adapter
     * @param array|null $config
     * @return \weyii\filesystem\Filesystem
     */
    protected function createFilesystem(AdapterInterface $adapter, array $config = null)
    {
        return new Filesystem($adapter, $config);
    }

    /**
     * @inheritdoc
     */
    public function __call($name, $params)
    {
        foreach ($this->getBehaviors() as $object) {
            if ($object->hasMethod($name)) {
                return call_user_func_array([$object, $name], $params);
            }
        }

        return call_user_func_array([$this->getDisk(), $name], $params);
    }
}