<?php
/**
 * Created by PhpStorm.
 * User: 白猫
 * Date: 2019/4/28
 * Time: 18:43
 */

namespace GoSwoole\Plugins\Whoops;

use GoSwoole\BaseServer\Server\Context;
use GoSwoole\BaseServer\Server\Plugin\AbstractPlugin;
use GoSwoole\BaseServer\Server\Plugin\PluginInterfaceManager;
use GoSwoole\Plugins\Aop\AopConfig;
use GoSwoole\Plugins\Aop\AopPlugin;
use GoSwoole\Plugins\Whoops\Aspect\WhoopsAspect;
use GoSwoole\Plugins\Whoops\Handler\WhoopsHandler;
use Whoops\Run;

class WhoopsPlugin extends AbstractPlugin
{

    /**
     * @var Run
     */
    private $whoops;
    /**
     * @var WhoopsConfig
     */
    protected $whoopsConfig;

    /**
     * WhoopsPlugin constructor.
     * @param WhoopsConfig|null $whoopsConfig
     * @throws \DI\DependencyException
     * @throws \ReflectionException
     */
    public function __construct(?WhoopsConfig $whoopsConfig = null)
    {
        parent::__construct();
        if ($whoopsConfig == null) {
            $whoopsConfig = new WhoopsConfig();
        }
        $this->whoopsConfig = $whoopsConfig;
        //需要aop的支持，所以放在aop后加载
        $this->atAfter(AopPlugin::class);
        //由于Aspect排序问题需要在EasyRoutePlugin之前加载
        $this->atBefore("GoSwoole\Plugins\EasyRoute\EasyRoutePlugin");
    }

    /**
     * 获取插件名字
     * @return string
     */
    public function getName(): string
    {
        return "Whoops";
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return mixed|void
     * @throws \DI\DependencyException
     * @throws \GoSwoole\BaseServer\Exception
     * @throws \ReflectionException
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $serverConfig = $pluginInterfaceManager->getServer()->getServerConfig();
        $aopPlugin = $pluginInterfaceManager->getPlug(AopPlugin::class);
        if ($aopPlugin == null) {
            $aopConfig = new AopConfig($serverConfig->getVendorDir() . "/go-swoole/base-server");
            $aopPlugin = new AopPlugin($aopConfig);
            $pluginInterfaceManager->addPlug($aopPlugin);
        }
    }

    /**
     * 在服务启动前
     * @param Context $context
     * @return mixed
     * @throws \GoSwoole\BaseServer\Exception
     */
    public function beforeServerStart(Context $context)
    {
        $this->whoopsConfig->merge();
        $serverConfig = $context->getServer()->getServerConfig();
        $this->whoops = new Run();
        $this->whoops->writeToOutput(false);
        $this->whoops->allowQuit(false);
        $handler = new WhoopsHandler();
        $handler->addResourcePath($serverConfig->getVendorDir() . "/filp/whoops/src/Whoops/Resources/");
        $handler->setPageTitle("出现错误了");
        $this->whoops->pushHandler($handler);
        //AOP注入
        $aopPlugin = $context->getServer()->getPlugManager()->getPlug(AopPlugin::class);
        if ($aopPlugin instanceof AopPlugin) {
            $aopPlugin->getAopConfig()->addIncludePath($serverConfig->getVendorDir() . "/go-swoole/base-server");
            $aopPlugin->getAopConfig()->addAspect(new WhoopsAspect($this->whoops,$this->whoopsConfig));
        } else {
            $this->error("没有添加AOP插件，Whoops无法工作");
        }
    }

    /**
     * 在进程启动前
     * @param Context $context
     * @return mixed
     */
    public function beforeProcessStart(Context $context)
    {
        $this->ready();
    }
}