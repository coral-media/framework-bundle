<?php

declare(strict_types=1);

namespace CoralMedia\Bundle\FrameworkBundle\EventListener;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\NoConfigurationException;

class RouterListener implements EventSubscriberInterface
{
    protected ParameterBagInterface $parameterBag;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    protected function createWelcomeResponse(): Response
    {
        ob_start();
        include \dirname(__DIR__) . '/Resources/views/welcome.html.php';

        return new Response(ob_get_clean(), Response::HTTP_NOT_FOUND);
    }

    public function onKernelException(ExceptionEvent $event)
    {
        if (
            !$this->parameterBag->get('kernel.debug') ||
            !($throwable = $event->getThrowable()) instanceof NotFoundHttpException
        ) {
            return;
        }

        if ($throwable->getPrevious() instanceof NoConfigurationException) {
            $event->setResponse($this->createWelcomeResponse());
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -63],
        ];
    }
}
