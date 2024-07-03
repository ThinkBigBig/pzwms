<?php

namespace App\Handlers;


class Export
{
    protected $type = 0;
    protected $tmpname = '';
    function __construct($type, $tmpname)
    {
        $this->type  = $type;
        $this->tmpname = $tmpname;
    }

    // 直接导出excel文件
    static function excel($excelFileName, $headers, $data, $keys)
    {
        $str = "<html xmlns:o=\"urn:schemas-microsoft-com:office:office\"\r\nxmlns:x=\"urn:schemas-microsoft-com:office:excel\"\r\nxmlns=\"http://www.w3.org/TR/REC-html40\">\r\n<head>\r\n<meta http-equiv=Content-Type content=\"text/html; charset=utf-8\">\r\n</head>\r\n<body>";
        $str .= "<table border=1 align=center cellpadding=0 cellspacing=0>";
        // 拼接标题行
        foreach ($headers as $title) {
            $str .= '<tr style="height:25px;font-size:13px;font-weight: bold;">';
            foreach ($title as $key => $val) {
                $str .= '<td>' . $val . '</td>';
            }
            $str .= '</tr>';
        }

        // 拼接数据
        foreach ($data as $key => $val) {
            $str .= '<tr style="text-align: left;height:25px;font-size:13px;">';
            foreach ($keys as $k) {
                $v = $val[$k] ?? '';
                if (is_numeric($v) && $v > 100000000) {
                    $str .= "<td style='vnd.ms-excel.numberformat:@'>" . $v . "</td>";
                } elseif (is_numeric($v) && preg_match('/^[0-9]+(\.[0-9]{2})+$/', $v)) {
                    // 是两位小数的保留2位显示
                    $str .= "<td style='vnd.ms-excel.numberformat:0.00'>" . $v . "</td>";
                } elseif (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1]) (0[0-9]|1[0-9]|2[0-4]):(0[0-9]|[1-5][0-9]):(0[0-9]|[1-5][0-9])$/', $v)) {
                    // 是日期
                    $str .= "<td style='vnd.ms-excel.numberformat:yyyy-mm-dd\ hh\:mm\:ss'>" . $v . "</td>";
                } else {
                    $str .= "<td>" . $v . "</td>";
                }
            }
            $str .= "</tr>\n";
        }
        $str .= "</table></body></html>";
        // 实现文件下载
        header("Content-Type: application/vnd.ms-excel; name='excel'");
        header("Content-type: application/octet-stream");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="01simple.xlsx"');
        // header('Content-Disposition: attachment;filename*=utf-8\'zh-cn\'"' . $excelFileName . '"');
        header("Content-Disposition: attachment;filename=" . $excelFileName);
        header('Cache-Control: max-age=0');
        header("Pragma: no-cache");
        header("Expires: 0");
        exit($str);
    }




    // 将数据转存成临时文件保存到服务器
    function data2Tmp($data, $headers, $keys, $page = 1)
    {
        $tmpname = $this->tmpname;
        $str = '';
        if ($page == 1) {
            foreach ($headers as $k => $title) {
                if ($this->type == 1) {
                    $colspan = 1;
                    if ($k == 0) $colspan = count($keys);
                    $str .= '<tr style="height:40px;font-size:13px;font-weight: bold;">';
                    foreach ($title as $key => $val) {
                        $str .= '<td colspan="' . $colspan . '" style="text-align:center;vertical-align: middle;height: 40px;font-size:14px;">' . $val . '</td>';
                    }
                    $str .= '</tr>';
                } elseif ($this->type == 2) {
                    $str .= '<tr style="height:40px;font-size:13px;font-weight: bold;">';
                    foreach ($title as $key => $val) {
                        $str .= '<td colspan="' . ($val['colspan'] ?? 1) . '" rowspan="' . ($val['rowspan'] ?? 1) . '" style="text-align:center;vertical-align: middle;height: 40px;font-size:14px;">' . ($val['label'] ?? '') . '</td>';
                    }
                    $str .= '</tr>';
                }
            }
        }

        // // 拼接数据
        // foreach ($data as $key => $val) {
        //     $str .= '<tr style="text-align: left;font-size:13px;">';
        //     foreach ($keys as $k) {
        //         $v = $val[$k] ?? '';
        //         if (is_string($v)) {
        //             $str .= "<td style='vnd.ms-excel.numberformat:@'>" . $v . "</td>";
        //         } else {
        //             $str .= "<td>" . $v . "</td>";
        //         }
        //     }
        //     $str .= "</tr>\n";
        // }

        // 拼接数据
        foreach ($data as $key => $val) {
            $str .= '<tr style="text-align: left;font-size:13px;">';
            foreach ($keys as $k) {
                // $key2 = explode('.', $k);
                // if (count($key2) == 1) {
                //     $v = $val[$k] ?? '';
                // } else {
                //     $item = $val;
                //     foreach ($key2 as $tmp) {
                //         $item = $item[$tmp] ?? '';
                //     }
                //     $v = $item;
                // }
                $v = $this->getValByKey($k,$val);
                if (is_string($v)) {
                    $str .= "<td style='vnd.ms-excel.numberformat:@'>" . $v . "</td>";
                } else {
                    $str .= "<td>" . $v . "</td>";
                }
            }
            $str .= "</tr>\n";
        }
        $name = storage_path(sprintf('app/%s.txt',  $tmpname));
        if (file_exists($name)) {
            file_put_contents($name, $str, FILE_APPEND);
        } else {
            file_put_contents($name, $str);
        }
        return $tmpname;
    }

    // 将转存的临时文件导出成excel
    function tmp2File()
    {
        $tmpname = $this->tmpname;
        $name = storage_path(sprintf('app/%s.txt', $tmpname));

        $str = "<html xmlns:o=\"urn:schemas-microsoft-com:office:office\"\r\nxmlns:x=\"urn:schemas-microsoft-com:office:excel\"\r\nxmlns=\"http://www.w3.org/TR/REC-html40\">\r\n<head>\r\n<meta http-equiv=Content-Type content=\"text/html; charset=utf-8\">\r\n</head>\r\n<body>";
        $str .= "<table border=1 align=center cellpadding=0 cellspacing=0 bordercolor='lightgrey'>";
        $str .= file_get_contents($name);
        $str .= "</table></body></html>";
        @unlink($name);
        // 实现文件下载
        header("Content-Type: application/vnd.ms-excel; name='excel'");
        header("Content-type: application/octet-stream");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="01simple.xlsx"');
        header('Content-Disposition: attachment;filename="' . $tmpname . '.xlsx"');
        header('Cache-Control: max-age=0');
        header("Pragma: no-cache");
        header("Expires: 0");
        exit($str);
    }

    private function getValByKey($key, $val)
    {
        if ($this->type == 2) return $val[$key] ?? '';

        if ($this->type == 1) {
            $key2 = explode('.', $key);
            if (count($key2) == 1) {
                return $val[$key] ?? '';
            } else {
                $item = $val;
                foreach ($key2 as $tmp) {
                    $item = $item[$tmp] ?? '';
                }
                return $item;
            }
        }
    }
}
