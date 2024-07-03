<?php


namespace App\Http\Service;

use App\Handlers\HttpService;
use Exception;

/**
 * php提供的es服务
 */
class TenantCodeService
{
     /**
     * 根据显示宽度获取指定的 mapbit
     *
     * @param integer $width 编号显示宽度
     *
     * @return array
     */
    private static function _getMapbit($width)
    {
        $mapBits = array(
            4 => array(
                10, 2, 11, 3, 0, 1, 9, 7, 12, 6, 4, 8, 5,
            ),
            5 => array(
                4, 3, 13, 15, 7, 8, 6, 2, 1, 10, 5, 12, 0, 11, 14, 9,
            ),
            6 => array(
                2, 7, 10, 9, 16, 3, 6, 8, 0, 4, 1, 12, 11, 13, 18, 5, 15, 17, 14,
            ),
            7 => array(
                18, 0, 2, 22, 8, 3, 1, 14, 17, 12, 4, 19, 11, 9, 13, 5, 6, 15, 10, 16, 20, 7, 21,
            ),
            8 => array(
                11, 8, 4, 0, 16, 14, 22, 7, 3, 5, 13, 18, 24, 25, 23, 10, 1, 12, 6, 21, 17, 2, 15, 9, 19, 20,
            ),
            9 => array(
                24, 23, 27, 3, 9, 16, 25, 13, 28, 12, 0, 4, 10, 18, 11, 2, 17, 1, 21, 26, 5, 15, 7, 20, 22, 14, 19, 6, 8,
            ),
            10 => array(
                32, 3, 1, 28, 21, 18, 30, 7, 12, 22, 20, 13, 16, 15, 6, 17, 9, 25, 11, 8, 4, 27, 14, 31, 5, 23, 24, 29, 0, 10, 19, 26, 2,
            ),
            11 => array(
                9, 13, 2, 29, 11, 32, 14, 33, 24, 8, 27, 4, 22, 20, 5, 0, 21, 25, 17, 28, 34, 6, 23, 26, 30, 3, 7, 19, 16, 15, 12, 31, 1, 35, 10, 18,
            ),
            12 => array(
                31, 4, 16, 33, 35, 29, 17, 37, 12, 28, 32, 22, 7, 10, 14, 26, 0, 9, 8, 3, 20, 2, 13, 5, 36, 27, 23, 15, 19, 34, 38, 11, 24, 25, 30, 21, 18, 6, 1,
            ),
        );
        return $mapBits[intval($width)];
    }

    /**
     * 格式化给定时间戳
     * https://www.jb51.net/article/69248.htm
     * @param integer $tf time format, if null use current time format
     * @return string
     */
    private static function _fmtTS($tf)
    {
        return date($tf, time());
    }

    /**
     * 根据id获取一个随机唯一编码
     * @param $id 编号
     * @param int $prefix 前缀
     * @param int $width 除前缀外长度
     * @return string
     */
    public static function generateNumber($id, $prefix = 10, $width = 8)
    {
        if ($width >= 4 && $width <= 12) {
            return sprintf("%s%s", $prefix, self::encode($id, $width));
        }
        return false;
    }

    /**
     * 编码转换
     *
     * @param integer $id id
     * @param integer $width 编号额外组成部分的显示宽度
     *
     * @return integer
     */
    public static function encode($id, $width)
    {
        $maximum = intval(str_repeat(9, $width));
        $superscript = intval(log($maximum) / log(2));
        $r = 0;
        $sign = 0x1 << $superscript;
        $id |= $sign;
        $mapbit = self::_getMapbit($width);
        for ($x = 0; $x < $superscript; $x++) {
            $v = ($id >> $x) & 0x1;
            $r |= ($v << $mapbit[$x]);
        }
        $r += $maximum - pow(2, $superscript) + 1;
        return sprintf("%0${width}s", $r);
    }

    /**
     * 根据时间生成获取唯一编号
     * @param integer $id id, mostly database primary key
     * @param integer $width 编号显示宽度
     * @param integer $tf time format
     *
     * @return string
     */
    public static function get($id, $width, $tf = 'ymdhis')
    {
        if ($width >= 4 && $width <= 12) {
            return sprintf('%s%s', self::_fmtTS($tf), self::encode($id, $width));
        }
        return false;
    }

    /**
     * 雪花算法生成唯一id
     * */
    const EPOCH = 1639040170957; //开始时间,固定一个小于当前时间的毫秒数\   1479533469598
    const max12bit = 4095;
    const max41bit = 1099511627775;

    private static $machineId = 10; // 机器id

    public static function machineId($mId = 0)
    {
        self::$machineId = $mId;
    }

    //雪花算法
    public static function createOnlyId()
    {
        // 时间戳 42字节
        $time = floor(microtime(true) * 1000);
        // 当前时间 与 开始时间 差值
        $time -= self::EPOCH;
        // 二进制的 毫秒级时间戳
        $base = decbin(self::max41bit + $time);
        // 机器id  10 字节
        if (!self::$machineId) {
            $machineid = self::$machineId;
        } else {
            $machineid = str_pad(decbin(self::$machineId), 10, "0", STR_PAD_LEFT);
        }
        // 序列数 12字节
        $random = str_pad(decbin(mt_rand(0, self::max12bit)), 12, "0", STR_PAD_LEFT);
        // 拼接
        $base = $base . $machineid . $random;
        // 转化为 十进制 返回
        return bindec($base);
    }
}
