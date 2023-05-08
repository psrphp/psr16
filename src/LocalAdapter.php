<?php

declare(strict_types=1);

namespace PsrPHP\Psr16;

use Composer\InstalledVersions;
use Exception;
use Psr\SimpleCache\CacheInterface;
use ReflectionClass;
use Throwable;

class LocalAdapter implements CacheInterface
{
    private $cache_dir;

    public function __construct(string $cache_dir = null)
    {
        if (is_null($cache_dir)) {
            if (class_exists(InstalledVersions::class)) {
                $cache_dir = dirname(dirname(dirname((new ReflectionClass(InstalledVersions::class))->getFileName()))) . '/runtime/cache/';
            } else {
                $cache_dir = __DIR__ . '/runtime/cache/';
            }
        }
        if (!is_dir($cache_dir)) {
            if (false === mkdir($cache_dir, 0755, true)) {
                throw new Exception('mkdir [' . $cache_dir . '] failure!');
            }
        }
        $this->cache_dir = $cache_dir;
    }

    public function get($key, $default = null)
    {
        $file = $this->getCacheFile($key);
        try {
            if (!is_file($file)) {
                return $default;
            }
            $cache = unserialize(file_get_contents($file));
            if ($cache['ttl'] < time()) {
                $this->delete($key);
                return $default;
            }
        } catch (Throwable $th) {
            return $default;
        }
        return $cache['value'];
    }

    public function set($key, $value, $ttl = null)
    {
        $file = $this->getCacheFile($key);
        try {
            $cache = [
                'key' => $key,
                'ttl' => $ttl ? time() + $ttl : 9999999999,
                'value' => $value,
            ];
            return file_put_contents($file, serialize($cache));
        } catch (Throwable $th) {
            return false;
        }
    }

    public function delete($key)
    {
        $file = $this->getCacheFile($key);
        try {
            if (is_file($file)) {
                return unlink($file);
            }
        } catch (Throwable $th) {
            return false;
        }
        return true;
    }

    public function clear()
    {
        try {
            $tmp = scandir($this->cache_dir);
            foreach ($tmp as $val) {
                if ($val != '.' && $val != '..') {
                    if (is_dir($this->cache_dir . '/' . $val)) {
                        if (!rmdir($this->cache_dir . '/' . $val)) {
                            return false;
                        }
                    } else {
                        if (!unlink($this->cache_dir . '/' . $val)) {
                            return false;
                        }
                    }
                }
            }
        } catch (Throwable $th) {
            return false;
        }
        return true;
    }

    public function getMultiple($keys, $default = null)
    {
        foreach ($keys as $key) {
            yield $key => $this->get($key, $default);
        }
    }

    public function setMultiple($values, $ttl = null)
    {
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }
        return true;
    }

    public function deleteMultiple($keys)
    {
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                return false;
            }
        }
        return true;
    }

    public function has($key)
    {
        $file = $this->getCacheFile($key);
        try {
            if (!is_file($file)) {
                return false;
            }
            $cache = unserialize(file_get_contents($file));
            if ($cache['ttl'] < time()) {
                $this->delete($key);
                return false;
            }
        } catch (Throwable $th) {
            return false;
        }
        return true;
    }

    private function getCacheFile($key)
    {
        $this->validateKey($key);
        return $this->cache_dir . '/' . $key;
    }

    /**
     * @param string $key
     *
     * @throws InvalidArgumentException
     */
    protected function validateKey($key)
    {
        if (!is_string($key) || $key === '') {
            throw new InvalidArgumentException('Key should be a non empty string');
        }

        $unsupportedMatched = preg_match('#[' . preg_quote('{}()/\@:') . ']#', $key);
        if ($unsupportedMatched > 0) {
            throw new InvalidArgumentException('Can\'t validate the specified key');
        }

        return true;
    }
}
