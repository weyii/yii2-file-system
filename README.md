
yii2-filesystem
=================
Yii2-filesystem是 [Flysystem](https://github.com/thephpleague/flysystem)基础上基于 [Yii2](https://github.com/yiisoft/yii2) 框架的实现的扩展。 **任何存储,统一的方法调用**

### 已支持扩展存储
- 阿里云OSS存储
- 又拍云存储
- 七牛与存储
- 本地存储
- FTP存储
- SFtp存储

####墙外世界产品(去Flysystem上找)
- Amazon S3/S2
- Dropbox
...

### 将实现的功能 (欢迎PR)

- 百度云存储
- 新浪云存储
- UFile(UCloud)云存储

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
      "weyii/yii2-filesystem": "*"
  },
  ```
### 编辑配置文件
- 编辑`config/web.php`

  ```php
    'components' => [
        ...
        'storage' => [
            'class' => 'weyii\filesystem\Manager',
            'default' => 'local',
            'disks' => [
                'local' => [
                    'class' => 'weyii\filesystem\adapters\Local',
                    'root' => '@webroot/storage' // 本地存储路径
                ],
                'qiniu' => [
                    'class' => 'weyii\filesystem\adapters\QiNiu',
                    'accessKey' => '七牛AccessKey',
                    'accessSecret' => '七牛accessSecret',
                    'bucket' => '七牛bucket空间',
                    'baseUrl' => '七牛基本访问地址, 如:http://72g7lu.com1.z0.glb.clouddn.com'
                ],
                'upyun' => [
                    'class' => 'weyii\filesystem\adapters\UpYun',
                    'operatorName' => '又拍云授权操作员账号',
                    'operatorPassword' => '又拍云授权操作员密码',
                    'bucket' => '又拍云的bucket空间',
                ],
                'aliyun' => [
                    'class' => 'weyii\filesystem\adapters\AliYun',
                    'accessKeyId' => '阿里云OSS AccessKeyID',
                    'accessKeySecret' => '阿里云OSS AccessKeySecret',
                    'bucket' => '阿里云的bucket空间',
                    // lanUrl和wanUrl样只需填写一个. 如果填写lanUrl 将优先使用lanUrl作为传输地址
                    // 外网和内网的使用参考: https://help.aliyun.com/document_detail/oss/user_guide/oss_concept/endpoint.html?spm=5176.2020520105.0.0.tpQOiL
                    'lanDomain' => 'OSS内网地址, 如:oss-cn-hangzhou-internal.aliyuncs.com', // 默认不填. 在生产环境下保证OSS和服务器同属一个区域机房部署即可, 切记不能带上bucket前缀
                    'wanDomain' => 'OSS外网地址, 如:oss-cn-hangzhou.aliyuncs.com' // 默认为杭州机房domain, 其他机房请自行替换, 切记不能带上bucket前缀
                ],
                ... // 其他如FTP, 墙外世界产品请参考Flysystem
            ]
        ],
        ...
    ]
  ```
- 使用例子

  ```php

    // 如果注册一个全局函数, 将会更简便

    if (!function_exists('storage')) {
        /**
         * Storage组件或Storage组件Disk实例
         *
         * @param null $disk
         * @return \weyii\filesystem\Manager|\weyii\filesystem\FilesystemInstance
         */
        function storage($disk = null)
        {
            if ($disk === null) {
                return Yii::$app->get('storage');
            }

            return Yii::$app->get('storage')->getDisk($disk);
        }
    }

    // 默认使用方法
    $storage = Yii::$app->get('storage'); // $storage = Yii::$app->storage;
    $storage->has('test.txt');

    $storage = storage();
    $defaultDisk = $storage->getDisk();
    $disk = $storage->getDisk('local');

    $storage->has('test.txt');
    $defaultDisk->has('test.txt');
    $disk = $disk->has('tes.txt');

    $disk = storage('local');
    $disk = $disk->has('tes.txt');

    $disks = $storage->disks;
    $testFileAlias = '@webroot/assets/test.txt';
    $testFile = Yii::getAlias($testFileAlias);
    file_put_contents($testFile, 'test.txt'); // 测试文件
    foreach ($disks as $name => $disk) {
        $disk = $storage->getDisk($name); // $disk = storage($name)

        $files = $disk->listContents();

        $disk->has('test.txt');
        $disk->get('test.txt');
        $disk->size('test.txt');
        $disk->lastModified('test.txt');

        $disk->delete('test.txt');
        $disk->copy('test.txt', 'test1.txt');
        $disk->move('test1.txt', 'test2.txt');

        $disk->prepend('test.txt', 'Prepended Text');
        $disk->prepend('test.txt', 'Appended Text');

        $disk->delete('test2.txt');
        $disk->delete(['test1.txt', 'test2.txt']);

        $disk->files('/path');
        $disk->allFiles('/path');

        $disk->directories('/path');
        $disk->allDirectories('/path');

        $has = $disk->has('test.txt');
        $has && $disk->delete('test.txt');
        $disk->put('test.txt', 'hello world!'); // storage($name)->put('test.txt', 'hello world!'); //下面的都可以这样操作
        $disk->delete('test.txt');
        $disk->putStream('test.txt', fopen($testFile, 'r')); // 流操作
        $disk->delete('test.txt');
        $disk->putFile('test.txt', $testFileAlias); // 文件路径操作
        $has && $disk->delete('test.txt');
        $disk->write('test.txt', 'Hello World!');
        $data = $disk->read('test.txt');
        $data = $disk->readStream('test.txt', fopen($testFile, 'r'));
        $disk->update('test.txt', 'Hello World!');
        $disk->updateStream('test.txt', fopen($testFile, 'r'));
        $disk->delete('test.txt');
        $disk->writeStream('test.txt', fopen($testFile, 'r'));
        $disk->has('test2.txt') && $disk->delete('test2.txt');
        $disk->rename('test.txt', 'test2.txt');
        $disk->copy('test2.txt', 'test.txt');
        $disk->createDir('dir');
        !$disk->has('dir/test2.txt') && $disk->write('dir/test2.txt', 'Hello World!');
        $disk->deleteDir('dir');
        $files = $disk->listContents();
        $size = $disk->getSize('test.txt');
        $mimeType = $disk->getMimetype('test.txt');
        $timestamp = $disk->getTimestamp('test.txt');
        $visibility = $disk->getVisibility('test.txt');
        $disk->setVisibility('test.txt', 'public');
        $metadata = $disk->getMetadata('test.txt');
        ... // 更多用法参考Flysystem
    }
  ```
