<?php

/**
 * Stockx 基础类
 */

namespace App\Handlers;

use App\Logics\BiddingAsyncLogic;
use App\Logics\RedisKey;
use App\Logics\Robot;
use Exception;
use Illuminate\Support\Facades\Redis;
use Kafka;

class KafkaService
{
    public function __construct()
    {
        // date_default_timezone_set('PRC');
    }

    public function consumer($broker, $group, $topic)
    {
        $config = \Kafka\ConsumerConfig::getInstance();
        $config->setMetadataRefreshIntervalMs(5000);
        $config->setMetadataBrokerList($broker);
        $config->setGroupId($group);
        $config->setBrokerVersion('1.0.0');
        $config->setTopics([$topic]);
        $config->setOffsetReset('earliest'); //偏移量重置策略，从最早消息开始消费
        $consumer = new \Kafka\Consumer();
        $start = date('Y-m-d H:i:s');
        // $consumer->setLogger($logger);
        $consumer->start(function ($topic, $part, $message) use ($start) {
            $lock = Redis::get(RedisKey::LOCK_CONSUMER_KAFKA);
            if (!$lock || $start < $lock) {
                throw new Exception('锁消失，结束进程等待重启');
            }
            try {
                // dump($topic,$part);
                if ($topic == env('KAFKA_TOPIC')) {
                    // 只处理最近10分钟的信息
                    if ($message['message']['timestamp'] > (time() - 600) . '000') {
                        $logic = new BiddingAsyncLogic();
                        // $logic->log('kafkaMessage', $message);
                        $logic->lowestPirceRefresh('CARRYME', json_decode($message['message']['value'], true));
                    }
                }
            } catch (Exception $e) {
                Robot::sendException('KafkaService ' . $e->getMessage());
            }
        });
    }
}
