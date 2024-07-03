<?php

namespace App\Handlers;

use Exception;
use OSS\OssClient;
use OSS\Core\OssException;

class OSSUtil
{

    protected $accessKeyId;
    protected $accessKeySecret;
    protected $endpoint;
    protected $bucket;
    protected $pre_path;


    public function __construct($params = [])
    {
        $this->accessKeyId = env('ALIYUN_OSS_ACCESSKEYID');
        $this->accessKeySecret = env('ALIYUN_OSS_ACCESSKEYSECRET');
        $this->endpoint = env('ALIYUN_OSS_ENDPOINT');
        // 存储空间名称
        $this->bucket = env('ALIYUN_OSS_BUCKETNAME');
        // <yourObjectName>上传文件到OSS时需要指定包含文件后缀在内的完整路径，例如abc/efg/123.jpg
        $this->pre_path = $params['pre_path'] ?? env('OSS_BASEDIR', 'erp/');
    }


    public  function delFile($file_name)
    {
        try {
            $ossClient = new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endpoint);
            $ossClient->deleteObject($this->bucket, $file_name);
        } catch (OssException $e) {
            info("文件删除错误信息 -> " . $e->getMessage());
            return;
        }
    }

    /**
     * 上传图片
     * @param [type] $filename
     * @param [type] $origin_url
     * @return array
     */
    public function addFileByUrl($filename, $origin_url)
    {
        try {
            $tmp_path = pathinfo($filename)['dirname'];
            mkdirs($tmp_path);
            file_put_contents($filename, file_get_contents($origin_url));

            $oss = new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endpoint);
            $data = $oss->uploadFile($this->bucket, $this->pre_path . $filename, $filename);
            @unlink($filename);
            return [
                'file_path' => $filename,
                'file_url' => $data['info']['url']
            ];
        } catch (Exception $e) {
            info("文件添加错误信息 -> " . $e->getMessage());
            return [];
        }
    }

    /**
     * 上传文件流
     * @param [type] $filename
     * @param [type] $data
     * @return array
     */
    public function addFileByData($filename, $data)
    {
        try {
            $oss = new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endpoint);
            $data = $oss->putObject($this->bucket, $this->pre_path . $filename, $data);
            return [
                'file_path' => $filename,
                'file_url' => $data['info']['url']
            ];
        } catch (Exception $e) {
            info("文件添加错误信息 -> " . $e->getMessage());
            return [];
        }
    }

    
    /**
     * 判断文件是否存在
     * @param [type] $filename
     * @return boolean
     */
    public function fileExist($filename){
        try{
            $ossClient = new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endpoint);
            $exist = $ossClient->doesObjectExist($this->bucket, $filename);
            return $exist ;
        } catch(OssException $e) {
            info("文件查询错误 -> " . $e->getMessage());
            return false;
        }
    }
}
