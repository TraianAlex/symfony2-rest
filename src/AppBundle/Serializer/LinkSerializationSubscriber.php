<?php

namespace AppBundle\Serializer;

use AppBundle\Annotation\Link;
use AppBundle\Entity\Programmer;
use Doctrine\Common\Annotations\Reader;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Routing\RouterInterface;

class LinkSerializationSubscriber implements EventSubscriberInterface
{
    private $router;

    private $annotationReader;

    private $expressionLanguage;

    public function __construct(RouterInterface $router, Reader $annotationReader)
    {
        $this->router = $router;
        $this->annotationReader = $annotationReader;
        $this->expressionLanguage = new ExpressionLanguage();
    }

    public function onPostSerialize(ObjectEvent $event)
    {
        /** @var JsonSerializationVisitor $visitor */
        $visitor = $event->getVisitor();
//        //$visitor->addData('uri', 'FOO');
//        /** @var Programmer $programmer */
//        $programmer = $event->getObject();
        $object = $event->getObject();
        $annotations = $this->annotationReader
            ->getClassAnnotations(new \ReflectionObject($object));

        $links = array();
        foreach ($annotations as $annotation) {
            if ($annotation instanceof Link) {
                $uri = $this->router->generate(
                    $annotation->route,
                    $this->resolveParams($annotation->params, $object)
                    //$annotation->params
                );
                $links[$annotation->name] = $uri;
            }
        }

        if ($links) {
            $visitor->addData('_links', $links);
        }

//        $visitor->addData(
//            'uri',
//            $this->router->generate('api_programmers_show', [
//                'nickname' => $programmer->getNickname()
//            ])
//        );
    }

    public static function getSubscribedEvents()
    {
        return array(
            array(
                'event' => 'serializer.post_serialize',
                'method' => 'onPostSerialize',
                'format' => 'json',
                //'class' => 'AppBundle\Entity\Programmer'
            )
        );
    }

    private function resolveParams(array $params, $object)
    {
        foreach ($params as $key => $param) {
            $params[$key] = $this->expressionLanguage
                ->evaluate($param, array('object' => $object));
        }
        return $params;
    }
}