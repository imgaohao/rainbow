<?php
namespace app\command\DownloadImage;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

class Weibo extends Command
{
    public function configure()
    {
        $this->setName('downloadimage:weibo')
//            ->addArgument('url', Argument::REQUIRED, '微博主页url')
            ->setDescription('下载微博图片');
    }

    public function execute(Input $input, Output $output)
    {
        \DownloadImage::weibo(123);
    }
    
    
}