<?php

namespace AppBundle\Serializer;

use AppBundle\Entity\Programmer;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use Symfony\Component\Routing\RouterInterface;

class LinkSerializationSubscriber implements EventSubscriberInterface
{
    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function onPostSerialize(ObjectEvent $event)
    {
        /** @var JsonSerializationVisitor $visitor */
        $visitor = $event->getVisitor();
        //$visitor->addData('uri', 'FOO');
        /** @var Programmer $programmer */
        $programmer = $event->getObject();

        $visitor->addData(
            'uri',
            $this->router->generate('api_programmers_show', [
                'nickname' => $programmer->getNickname()
            ])
        );
    }

    public static function getSubscribedEvents()
    {
        return array(
            array(
                'event' => 'serializer.post_serialize',
                'method' => 'onPostSerialize',
                'format' => 'json',
                'class' => 'AppBundle\Entity\Programmer'
            )
        );
    }
}