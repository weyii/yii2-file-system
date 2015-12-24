<?php
namespace weyii\file\system;

use Yii;
use yii\base\Component;
use yii\base\InvalidCallException;
use yii\base\InvalidParamException;
use yii\base\InvalidConfigException;
use League\Flysystem\Filesystem;

class Collection extends Component
{
    private $_fileSystems = [];

    public function getFileSystems()
    {
        $fileSystems = [];
        foreach ($this->_fileSystems as $id => $fileSystem) {
            $fileSystems[$id] = $this->get($id);
        }
        return $fileSystems;
    }

    public function setFileSystems(array $fileSystems)
    {
        $this->_fileSystems = $fileSystems;
    }

    /**
     * 获取指定的文件存储
     * @param $id
     * @return mixed
     * @throws \yii\base\InvalidParamException
     */
    public function get($id)
    {
        if (!$this->has($id)) {
            throw new InvalidParamException("Unknown file system '{$id}'.");
        }
        if (!is_object($this->_fileSystems[$id]) || is_callable($this->_fileSystems[$id])) {
            $this->_fileSystems[$id] = $this->create($id, $this->_fileSystems[$id]);
        }
        return $this->_fileSystems[$id];
    }

    /**
     * 判断是否有文件存储
     * @param $id
     * @return bool
     */
    public function has($id)
    {
        return array_key_exists($id, $this->_fileSystems);
    }

    /**
     * 初始化文件存储
     * @param $id
     * @param $config
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    protected function create($id, $config)
    {
        $object = Yii::createObject($config);
        if (!($object instanceof Filesystem)) {
            throw new InvalidConfigException("The file system class {$id} must extend from League\\Flysystem\\Filesystem.");
        }
        return $object;
    }

    /**
     * Retrieve the prefix form an arguments array
     *
     * @param   array $arguments
     * @return  array  [:prefix, :arguments]
     */
    protected function filterPrefix(array $arguments)
    {
        if (empty($arguments)) {
            throw new InvalidCallException('At least one argument needed');
        }
        $path = array_shift($arguments);
        if (!is_string($path)) {
            throw new InvalidParamException('First argument should be a string');
        }
        if (!preg_match('#^[a-zA-Z0-9]+\:\/\/.*#', $path)) {
            throw new InvalidParamException('No prefix detected in for path: ' . $path);
        }
        list ($prefix, $path) = explode('://', $path, 2);
        array_unshift($arguments, $path);
        return array($prefix, $arguments);
    }

    /**
     * Call forwarder
     *
     * @param   string $method
     * @param   array $arguments
     * @return  mixed
     */
    public function __call($method, $arguments)
    {
        list($prefix, $arguments) = $this->filterPrefix($arguments);
        $filesystem = $this->get($prefix);
        if (method_exists($filesystem, $method)) {
            $callback = array($filesystem, $method);
            return call_user_func_array($callback, $arguments);
        }
        return parent::__call($method, $arguments);
    }
}
