# psr16

psr16实现

## 安装

``` cmd
composer require psrphp/psr16
```

## 用例

``` php
$cache = new \PsrPHP\Psr16\NullAdapter;
$cache = new \PsrPHP\Psr16\LocalAdapter('./cache');

$cache->...
```
