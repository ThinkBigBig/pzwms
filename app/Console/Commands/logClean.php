<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class logClean extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'log:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '定时清理日志文件';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // 保留14天的日志文件
        $end = time() - 14 * 24 * 3600;
        $month = date('Ym', $end);
        $day = date('Ymd', $end);

        $dirs = scandir(storage_path('logs'));
        foreach ($dirs as $dir) {
            dump($dir);
            if (in_array($dir, ['.', '..', '.gitignore', 'laravel.log'])) continue;
            $path = storage_path('logs/' . $dir);
            if (!is_dir($path)) continue;
            
            // 删除文件夹
            if ($dir < $month) {
                $this->delDir($path);
                continue;
            }

            // 保留文件夹，删除部分文件
            $this->delDir($path, $day);
        }

        return 0;
    }


    function delDir($path, $end_day = '')
    {
        $files = scandir($path);
        if (!$files) {
            rmdir($path);
            return;
        }

        foreach ($files as $file) {
            if (in_array($file, ['.', '..'])) continue;
            $path2 = $path . '/' . $file;
            if (is_dir($path . '/' . $file)) {
                $this->delDir($path2);
            }
            // 没有日期限制，全部清除
            if (!$end_day) {
                unlink($path . '/' . $file);
                continue;
            }

            // 只清除限制日期之前的文件
            if ($end_day && substr($file, 0, 8) < $end_day) {
                unlink($path . '/' . $file);
                continue;
            }
        }
        if (!$end_day) {
            rmdir($path);
        }
        return;
    }
}
