<?php
namespace app\command\Task;

use think\App;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\Container;

class Weibo extends Command
{
    public function configure()
    {
        $this->setName('task:weibo')
//            ->addArgument('url', Argument::REQUIRED, '微博主页url')
            ->setDescription('');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int|null|void
     * @throws \Exception
     */
    public function execute(Input $input, Output $output)
    {
        /** @var App $app */
        $app = Container::get('app');
        $weibo = new \Weibo(['save_path' => $app->getRootPath()]);
        $weibo->get('https://www.weibo.com/u/5812298777?refer_flag=1005050010_');
    }
}