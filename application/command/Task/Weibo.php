<?php
namespace app\command\Task;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

class Weibo extends Command
{
    public function configure()
    {
        $this->setName('task:weibo')
//            ->addArgument('url', Argument::REQUIRED, '微博主页url')
            ->setDescription('');
    }

    public function execute(Input $input, Output $output)
    {
        \Weibo::get('https://weibo.com/gaohaoblog?refer_flag=1001030101_');
    }
    
    
}