<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use FTCBotCore\Broker\BrokerClient;
use FTCBotCore\Message\Message;
use FTCBotCore\Broker\Client\AMQPClient;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

$sm = include 'config/container.php';

$botConfig = include 'config/autoload/bot.local.php';


function alertOwner($discordClient, $ownerId)
{
    
    $error = error_get_last();
    $errorMsg = sprintf('%s > %s:%s', $error['message'], $error['file'], $error['line']); 
    $discordClient->sendDM($ownerId, 'I\'ve stopped working'."\n".$errorMsg);
}

register_shutdown_function('alertOwner', $sm->get('discord-http-client'), $botConfig['owner_id']);



$waitUponBrokerStart = function($sm) {
    $brokerConfig = $sm->get('config')['broker'];
    while (($connection = @fsockopen($brokerConfig['host'], $brokerConfig['port'])) === false) {
        $wheel = ['-', '\\', '|', '/'];
        if (!isset($string)) {
            $string = 'Waiting for broker service startup  ';
            echo $string;
        } else {
            foreach ($wheel as $char) {
                printf("%c%s", 0x08, $char);
                sleep(1);
            }
        }
    }
    echo PHP_EOL;
    fclose($connection);
    echo 'Broker service started up'.PHP_EOL;
};

$waitUponBrokerStart($sm);



/**
 * @var AMQPClient $broker
 */
$broker = $sm->get(BrokerClient::class);

echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";

$callback = function(Message $message) use ($sm) {
    echo " [x] Received '".$message->getEventType().": ", json_encode($message->getData()).PHP_EOL;
    
    if ($sm->has($message->getEventType())) {
        $handler = $sm->get($message->getEventType());
        
        return $handler($message);
    }

    return true;
};


while(true) {
    try {
        $broker->consume($callback);
    } catch(PDOException $e) {
        var_dump(get_class($e));
        var_dump($e->getMessage());
        var_dump($e->getTraceAsString());
        die();
    } catch (Exception $e) {
        var_dump(get_class($e));
        var_dump($e->getMessage());
        var_dump($e->getTraceAsString());
        echo "CONNECTION WITH BROKER LOST !\n";
//         sleep(2);
        $waitUponBrokerStart($sm);
        $broker->reconnect();
    }
};
