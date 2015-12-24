
yii2-filesystem
=================
Yii2-filesystem是 [Flysystem](https://github.com/thephpleague/flysystem)基础上基于 [Yii2](https://github.com/yiisoft/yii2) 框架的实现的扩展。 **任何存储,统一的函数调用**

###扩展存储
- Qinu 七牛云存储

### 将要实现的功能 (欢迎PR)
- 阿里云存储 
- 又拍云存储 (未实现大文件流API[还是老牌存储...], 如果实现功能后.大文件操作会有资源要求)
- 百度云存储
- 新浪云存储

使用要求
========
- php >= 5.4
- [Flysystem](https://github.com/thephpleague/flysystem) 

使用教程
========
###使用`Componser`安装 (以下2种方式)
- 命令行执行 `composer require weyii/yii2-filesystem`
- 编辑`composer.json` 

  ```php
  "require": {
      ...
      "weyii/yii2-file-system": "*"
  },
  ```
### 编辑配置文件(2种使用方式)
- 编辑`config/main.php`

  ```php
  'components' => [
    'fileSystem' => [
      'class' => 'weyii\filesystem\Collection',
          'fileSystems' => [
              //根据需求可设置多个存储, 以下来使用例子
              'local' => function() {
                  return new \weyii\filesystem\FileSystem(
                      new \weyii\filesystem\adapters\Local(\Yii::getAlias('@webroot\images'))
                  );
              },
              'qiniu' => function() {
                  return new \weyii\filesystem\FileSystem(
                      new \weyii\filesystem\adapters\Qiniu(
                          '七牛空间的 bucket',
                          '七牛空间的 access key',
                          '七牛空间的 access secret',
                          '七牛的空间域名,默认为 {bucket}.qiniu.com 选填'
                      )
                  );
              }
          ]
    ]
  ]
  ```
- 使用例子(推荐wrapper方式)

  ```php
    // 集合方式
    $local = Yii::$app->fileSystem->get('local');
    $local->write('test.txt', 'hello world');
    echo $local->read('test.txt');
    
    $qiniu = Yii::$app->fileSystem->get('qiniu');
    $qiniu->write('test.txt', 'hello world');
    echo $qiniu->read('test.txt');
    
    // wrapper 方式 (推荐)
    //等同于Yii::$app->filesystem->get('local')->write('test.txt', 'hello world');
    Yii::$app->filesystem->write('local://test.txt', 'hello world');
    //等同于Yii::$app->filesystem->get('qiniu')->write('test.txt', 'hello world');
    Yii::$app->filesystem->write('qiniu://test.txt', 'hello world');
  ```
