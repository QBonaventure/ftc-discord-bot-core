<?php
declare(strict_types=1);

namespace FTCBotCore\Container\EventHandler;

use Psr\Container\ContainerInterface;
use FTCBotCore\EventHandler\ChannelPinsUpdate as ChannelPinsUpdateInstance;

class ChannelPinsUpdate
{

    public function __invoke(ContainerInterface $container, $requestedName)
    {
        return new ChannelPinsUpdateInstance();
    }

}
